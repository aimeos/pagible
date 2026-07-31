<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierMollie;
use Aimeos\Cms\CashierMolliePayment;
use Aimeos\Cms\CashierMolliePlan;
use Aimeos\Cms\CashierMollieServiceProvider;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierToken;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Mollie\Contracts\GetMolliePayment;
use Laravel\Cashier\Mollie\GetMolliePayment as MolliePayment;
use Laravel\Cashier\Plan\Contracts\PlanRepository;
use Mollie\Api\Exceptions\ApiException;
use Mollie\Api\Resources\Payment as PaymentResource;
use Mollie\Api\Types\PaymentStatus;
use Mollie\Api\Types\RefundStatus;


class CashierMollieTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    private \App\Models\User $stored;


    protected function setUp(): void
    {
        parent::setUp();

        $this->stored = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
    }


    public function testAccessIndexesSupportWebhookSourceLookup(): void
    {
        $this->assertTrue( Schema::hasIndex( 'order_items', 'cms_mollie_order_items_order' ) );
        $this->assertTrue( Schema::hasIndex( 'payments', 'cms_mollie_payments_source' ) );
        $this->assertTrue( Schema::hasIndex( 'orders', 'cms_mollie_orders_source' ) );
    }


    public function testCancellationRejectsUnrelatedSubscription(): void
    {
        $class = Cashier::$subscriptionModel;
        $subscription = $class::forceCreate( [
            'name' => 'application-premium',
            'plan' => 'application.monthly',
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'quantity' => 1,
            'tax_percentage' => 0,
            'cycle_started_at' => now(),
            'cycle_ends_at' => now()->addMonth(),
        ] );

        $this->expectException( ModelNotFoundException::class );
        app( CashierMollie::class )->cancel( $this->stored, (string) $subscription->getKey() );
    }


    public function testFirstPaymentRouteRequestsSynchronousProjection(): void
    {
        $payment = \Mockery::mock( PaymentResource::class );
        $payments = \Mockery::mock( MolliePayment::class );
        $payments->shouldReceive( 'execute' )
            ->once()
            ->with( 'tr_first', ['embed' => 'refunds,chargebacks'] )
            ->andReturn( $payment );
        $provider = \Mockery::mock( CashierMollie::class );
        $provider->shouldReceive( 'webhook' )
            ->once()
            ->with( $payment, true )
            ->andReturn( true );
        $route = Route::getRoutes()->getByName( 'webhooks.mollie.first_payment' );
        request()->setRouteResolver( fn() => $route );

        ( new CashierMolliePayment( $payments, $provider ) )->execute( 'tr_first' );
    }


    public function testFullRemoteRefundStopsWebhookAndRevokesAccess(): void
    {
        $order = $this->order( [
            'mollie_payment_id' => 'tr_remote_refund',
            'mollie_payment_status' => PaymentStatus::PAID,
        ] );
        $this->payment( $order, ['mollie_payment_id' => 'tr_remote_refund'] );
        app( CashierAccess::class )->grant(
            $this->stored,
            'test',
            'frontend.course',
            'mollie',
            'tr_remote_refund',
            null,
        );
        $payment = new MolliePaymentStub( 'tr_remote_refund' );
        $payment->amountRefunded = (object) ['value' => '10.00', 'currency' => 'EUR'];
        $payment->refundItems = [(object) [
            'createdAt' => now()->toISOString(),
            'status' => RefundStatus::REFUNDED,
        ]];

        $this->assertFalse( app( CashierMollie::class )->webhook( $payment ) );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|mollie|tr_remote_refund']['role'] );
    }


    public function testOneTimePaymentLifecycle(): void
    {
        $token = app( CashierToken::class )->create( $this->stored, 'mollie', [
            'access' => 'frontend.course',
            'kind' => 'once',
            'reference' => '19.00',
        ] );
        $payment = new MolliePaymentStub( 'tr_once', $token );

        $this->assertTrue( app( CashierMollie::class )->webhook(
            $payment,
            firstPayment: true,
        ) );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.course', $stored['test|mollie|tr_once']['role'] );
        $this->assertNull( $stored['test|mollie|tr_once']['end'] );
    }


    public function testPaidRenewalProjectsAccessBeforeWebhookAcknowledgement(): void
    {
        $first = now()->addMonth()->startOfSecond();
        $renewed = now()->addMonths( 2 )->startOfSecond();
        $subscription = $this->subscription( $first );
        $subscription::withoutEvents( fn() => $subscription->update( [
            'cycle_ends_at' => $renewed,
        ] ) );
        $order = $this->order( [
            'mollie_payment_id' => 'tr_renewal',
            'mollie_payment_status' => PaymentStatus::OPEN,
        ] );
        $this->payment( $order, ['mollie_payment_id' => 'tr_renewal'] );
        $this->item( $order, $subscription );

        $this->assertTrue( app( CashierMollie::class )->webhook(
            new MolliePaymentStub( 'tr_renewal' ),
        ) );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame(
            $renewed->timestamp * 1000,
            $stored['test|mollie|' . $subscription->getKey()]['end'],
        );
    }


    public function testPaidRenewalUsesOnlyPagibleSubscriptionsAndPreloadsOwners(): void
    {
        $end = now()->addMonth()->startOfSecond();
        $included = $this->subscription( $end );
        $class = Cashier::$subscriptionModel;
        $excluded = $class::withoutEvents( fn() => $class::forceCreate( [
            'name' => 'application-premium',
            'plan' => 'application.monthly',
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'quantity' => 1,
            'tax_percentage' => 0,
            'cycle_started_at' => now(),
            'cycle_ends_at' => $end,
        ] ) );
        $order = $this->order( ['mollie_payment_id' => 'tr_filtered'] );
        $this->payment( $order, ['mollie_payment_id' => 'tr_filtered'] );
        $this->item( $order, $included );
        $this->item( $order, $excluded );

        $provider = new class(
            app( CashierAccess::class ),
            app( CashierToken::class ),
            app( CashierMolliePlan::class ),
        ) extends CashierMollie {
            /** @var list<array{string, bool}> */
            public array $seen = [];


            public function subscription( object $subscription, bool $cancelled = false,
                ?\DateTimeInterface $at = null
            ) : void {
                $this->seen[] = [
                    (string) ( $subscription->id ?? '' ),
                    $subscription instanceof Model && $subscription->relationLoaded( 'owner' ),
                ];

                parent::subscription( $subscription, $cancelled, $at );
            }
        };

        $this->assertTrue( $provider->webhook( new MolliePaymentStub( 'tr_filtered' ) ) );
        $this->assertSame( [[(string) $included->getKey(), true]], $provider->seen );
    }


    public function testProjectionFailureAbortsWebhookAndCanRetry(): void
    {
        $token = app( CashierToken::class )->create( $this->stored, 'mollie', [
            'access' => 'frontend.course',
            'kind' => 'once',
            'reference' => '19.00',
        ] );
        $payment = new MolliePaymentStub( 'tr_retry', $token );
        $this->app->instance( CashierAccess::class, new class extends CashierAccess {
            public function grant( Authenticatable $user, string $tenant, string $role,
                string $provider, string $id, ?\DateTimeInterface $end, ?\DateTimeInterface $at = null
            ) : void {
                throw new \RuntimeException( 'Projection unavailable.' );
            }
        } );
        $this->app->forgetInstance( CashierMollie::class );

        try {
            app( CashierMollie::class )->webhook( $payment, true );
            $this->fail( 'The projection failure must abort webhook processing.' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'Projection unavailable.', $e->getMessage() );
        }

        $this->assertNull( $this->storedAccess() );
        $this->app->bind( CashierAccess::class, fn() => new CashierAccess() );
        $this->app->forgetInstance( CashierMollie::class );

        $this->assertTrue( app( CashierMollie::class )->webhook( $payment, true ) );
        $this->assertSame(
            'frontend.course',
            $this->storedAccess()['test|mollie|tr_retry']['role'] ?? null,
        );
    }


    public function testProviderMigrationsPublishAfterUpstreamCashierMigrations(): void
    {
        $provider = ServiceProvider::pathsToPublish(
            CashierMollieServiceProvider::class,
            'cashier-migrations',
        );
        $all = ServiceProvider::pathsToPublish( null, 'cashier-migrations' );
        $ours = array_values( $provider );
        $upstream = array_values( array_diff( $all, $ours ) );

        $this->assertCount( 2, $ours );
        $this->assertNotEmpty( $upstream );
        $this->assertTrue( basename( $ours[0] ) < basename( $ours[1] ) );

        foreach( $upstream as $migration ) {
            $this->assertTrue( basename( $migration ) < basename( $ours[0] ) );
        }
    }


    public function testRemoteApiFailureIsRetryable(): void
    {
        $payments = \Mockery::mock( MolliePayment::class );
        $payments->shouldReceive( 'execute' )
            ->once()
            ->with( 'tr_unavailable', [] )
            ->andThrow( \Mockery::mock( ApiException::class ) );

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'Unable to retrieve the Mollie payment.' );

        ( new CashierMolliePayment(
            $payments,
            app( CashierMollie::class ),
        ) )->execute( 'tr_unavailable' );
    }


    public function testRemoteRefundWithoutProviderTimeIsRetryable(): void
    {
        $payment = new MolliePaymentStub( 'tr_missing_time' );
        $payment->amountRefunded = (object) ['value' => '10.00', 'currency' => 'EUR'];
        $payment->refundItems = [(object) ['status' => RefundStatus::REFUNDED]];

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'Mollie adverse event time is unavailable.' );

        app( CashierMollie::class )->webhook( $payment );
    }


    public function testScheduledCancellationKeepsPaidEndAndImmediateCancellationRevokes(): void
    {
        $provider = app( CashierMollie::class );
        $end = now()->addMonth()->startOfSecond();
        $subscription = (object) [
            'id' => 123,
            'name' => $this->entitlement( 'frontend.pro' ),
            'plan' => $this->plan(),
            'owner' => $this->stored,
            'cycle_ends_at' => $end,
        ];

        $provider->subscription( $subscription );
        $subscription->ends_at = $end;
        $subscription->cycle_ends_at = null;
        $provider->subscription( $subscription, true );

        $this->assertSame(
            $end->timestamp * 1000,
            $this->storedAccess()['test|mollie|123']['end'] ?? null,
        );

        $subscription->ends_at = now()->subSecond();
        $provider->subscription( $subscription, true );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|mollie|123']['role'] );
    }


    public function testServiceProviderBindsDriver(): void
    {
        $this->assertInstanceOf( CashierMollie::class, app( CashierProvider::class ) );
        $this->assertInstanceOf( CashierMolliePayment::class, app( GetMolliePayment::class ) );
    }


    public function testServiceProviderSchedulesBilling(): void
    {
        $events = collect( app( Schedule::class )->events() );
        $cashier = $events->first( fn( $event ) =>
            str_contains( (string) $event->command, 'cashier:run' )
        );

        $this->assertNotNull( $cashier );
        $this->assertTrue( $cashier->onOneServer );
        $this->assertTrue( $cashier->runInBackground );

        $this->assertSame( '*/5 * * * *', $cashier->expression );
        $this->assertTrue( $cashier->withoutOverlapping );
    }


    public function testSubscriptionCreationAndAccessRollbackTogether(): void
    {
        $this->app->instance( CashierAccess::class, new class extends CashierAccess {
            public function grant( Authenticatable $user, string $tenant, string $role,
                string $provider, string $id, ?\DateTimeInterface $end, ?\DateTimeInterface $at = null
            ) : void {
                throw new \RuntimeException( 'Projection unavailable.' );
            }
        } );
        $this->app->forgetInstance( CashierMollie::class );

        try {
            DB::transaction( fn() => $this->subscription( now()->addMonth() ) );
            $this->fail( 'Subscription creation must fail with its access projection.' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'Projection unavailable.', $e->getMessage() );
        }

        $this->assertSame( 0, DB::table( 'subscriptions' )->count() );
        $this->assertNull( $this->storedAccess() );
    }


    public function testSubscriptionPlanMustMatchEntitlement(): void
    {
        $provider = app( CashierMollie::class );
        $first = now()->addMonth()->startOfSecond();
        $subscription = (object) [
            'id' => 123,
            'name' => $this->entitlement( 'frontend.pro' ),
            'plan' => $this->plan( 'frontend.pro' ),
            'owner' => $this->stored,
            'cycle_ends_at' => $first,
        ];

        $provider->subscription( $subscription );
        $subscription->plan = $this->plan( 'frontend.basic' );
        $subscription->cycle_ends_at = now()->addMonths( 2 )->startOfSecond();
        $provider->subscription( $subscription );

        $this->assertSame(
            $first->timestamp * 1000,
            $this->storedAccess()['test|mollie|123']['end'] ?? null,
        );
    }


    public function testUnknownWebhookPaymentIsAcknowledgedWithoutRetry(): void
    {
        $payments = \Mockery::mock( MolliePayment::class );
        $exception = \Mockery::mock( ApiException::class );
        $exception->shouldReceive( 'getStatusCode' )->once()->andReturn( 404 );
        $payments->shouldReceive( 'execute' )
            ->once()
            ->with( 'tr_unknown', ['embed' => 'refunds,chargebacks'] )
            ->andThrow( $exception );
        $route = Route::getRoutes()->getByName( 'webhooks.mollie.default' );
        request()->setRouteResolver( fn() => $route );

        try {
            ( new CashierMolliePayment(
                $payments,
                app( CashierMollie::class ),
            ) )->execute( 'tr_unknown' );
            $this->fail( 'Unknown webhook payments must be acknowledged.' );
        } catch( HttpResponseException $e ) {
            $this->assertSame( 204, $e->getResponse()->getStatusCode() );
        }
    }


    public function testWebhookRoutesRejectMalformedPaymentIds(): void
    {
        foreach( [
            'webhooks.mollie.default',
            'webhooks.mollie.aftercare',
            'webhooks.mollie.first_payment',
        ] as $route ) {
            $this->post( route( $route ), ['id' => 'invalid'] )->assertBadRequest();
            $this->post( route( $route ), ['id' => ['tr_invalid']] )->assertBadRequest();
        }
    }


    public function testWebhookRoutesUseOpaquePaths(): void
    {
        $key = (string) config( 'app.key' );
        $token = hash_hmac( 'sha256', 'cms-cashier-mollie-webhook', $key );

        foreach( [
            'webhooks.mollie.default',
            'webhooks.mollie.aftercare',
            'webhooks.mollie.first_payment',
        ] as $name ) {
            $this->assertStringEndsWith(
                '/' . $token,
                (string) Route::getRoutes()->getByName( $name )?->uri(),
            );
        }

        $this->post( '/webhooks/mollie', ['id' => 'tr_public'] )->assertNotFound();
        $this->post( '/webhooks/mollie/aftercare', ['id' => 'tr_public'] )->assertNotFound();
        $this->post( '/webhooks/mollie/first-payment', ['id' => 'tr_public'] )->assertNotFound();
    }


    public function testWebhookRoutesAreNotRateLimited(): void
    {
        foreach( [
            'webhooks.mollie.default',
            'webhooks.mollie.aftercare',
            'webhooks.mollie.first_payment',
        ] as $name ) {
            $middleware = Route::getRoutes()->getByName( $name )?->middleware() ?? [];

            $this->assertSame(
                [],
                array_values( array_filter( $middleware, fn( $item ) =>
                    is_string( $item ) && str_starts_with( $item, 'throttle:' )
                ) ),
            );
        }
    }


    public function testPlanRejectsInvalidBillingIntervals(): void
    {
        foreach( [0, 366, '30'] as $interval )
        {
            try {
                app( CashierMolliePlan::class )->create( [
                    'reference' => '19.00',
                    'currency' => 'EUR',
                    'interval' => $interval,
                    'description' => 'Professional',
                ], $this->entitlement( 'frontend.pro' ) );
                $this->fail( 'Invalid Mollie billing interval accepted.' );
            } catch( \InvalidArgumentException ) {
                $this->addToAssertionCount( 1 );
            }
        }
    }


    public function testPlanSupportsLocalizedDescription(): void
    {
        $description = str_repeat( '購', 20 );
        $name = app( CashierMolliePlan::class )->create( [
            'reference' => '19.00',
            'currency' => 'EUR',
            'interval' => 30,
            'description' => $description,
        ], $this->entitlement( 'frontend.pro' ) );

        $this->assertLessThanOrEqual( 255, strlen( $name ) );
        $this->assertSame(
            $description,
            app( CashierToken::class )->parse( substr( $name, 4 ) )['d'] ?? null,
        );
        $this->assertNotNull( CashierMolliePlan::find( $name ) );
    }


    public function testPlanSurvivesApplicationKeyRotation(): void
    {
        $old = 'base64:' . base64_encode( 'old-mollie-plan-key' );
        $new = 'base64:' . base64_encode( 'new-mollie-plan-key' );
        config()->set( 'app.key', $old );
        config()->set( 'app.previous_keys', [] );

        $name = app( CashierMolliePlan::class )->create( [
            'reference' => '19.00',
            'currency' => 'EUR',
            'interval' => 30,
            'description' => 'Professional',
        ], $this->entitlement( 'frontend.pro' ) );

        config()->set( 'app.key', $new );
        config()->set( 'app.previous_keys', [$old] );

        $this->assertNotNull( CashierMolliePlan::find( $name ) );
    }


    public function testPlanTrimsDescriptionToProviderLimit(): void
    {
        $description = str_repeat( '購', 80 );
        $name = app( CashierMolliePlan::class )->create( [
            'reference' => '19.00',
            'currency' => 'EUR',
            'interval' => 30,
            'description' => $description,
        ], $this->entitlement( 'frontend.pro' ) );
        $stored = app( CashierToken::class )->parse( substr( $name, 4 ) );

        $this->assertLessThanOrEqual( 255, strlen( $name ) );
        $this->assertIsArray( $stored );
        $this->assertNotSame( '', $stored['d'] );
        $this->assertStringStartsWith( $stored['d'], $description );
        $this->assertNotNull( CashierMolliePlan::find( $name ) );
    }


    public function testPlanUsesSharedSignedPayload(): void
    {
        $type = $this->entitlement( 'frontend.pro' );
        $name = app( CashierMolliePlan::class )->create( [
            'reference' => '19.00',
            'currency' => 'EUR',
            'interval' => 30,
            'description' => 'Professional',
        ], $type );
        $data = app( CashierToken::class )->parse( substr( $name, 4 ) );

        $this->assertStringStartsWith( 'cms.', $name );
        $this->assertInstanceOf( CashierMolliePlan::class, app( PlanRepository::class ) );
        $this->assertNotNull( CashierMolliePlan::find( $name ) );
        $this->assertSame( '19.00', $data['v'] ?? null );
        $this->assertSame( 'EUR', $data['c'] ?? null );
        $this->assertSame( 30, $data['i'] ?? null );
        $this->assertSame( 'Professional', $data['d'] ?? null );
    }


    private function entitlement( string $role ): string
    {
        return CashierAccess::subscription( 'test', $role );
    }


    private function item( Model $order, Model $orderable ): void
    {
        $class = Cashier::$orderItemModel;
        $class::forceCreate( [
            'process_at' => now(),
            'orderable_type' => $orderable->getMorphClass(),
            'orderable_id' => $orderable->getKey(),
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'description' => 'Professional',
            'currency' => 'EUR',
            'quantity' => 1,
            'unit_price' => 1000,
            'tax_percentage' => 0,
            'order_id' => $order->getKey(),
        ] );
    }


    /**
     * @param array<string, mixed> $data
     */
    private function order( array $data = [] ): \Illuminate\Database\Eloquent\Model
    {
        $class = Cashier::$orderModel;

        return $class::forceCreate( $data + [
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'number' => 'ORDER-' . uniqid(),
            'currency' => 'EUR',
            'subtotal' => 1000,
            'tax' => 0,
            'total' => 1000,
            'total_due' => 1000,
            'amount_refunded' => 0,
            'amount_charged_back' => 0,
            'mollie_payment_status' => PaymentStatus::OPEN,
            'processed_at' => now(),
        ] );
    }


    /**
     * @param array<string, mixed> $data
     */
    private function payment( \Illuminate\Database\Eloquent\Model $order,
        array $data = []
    ): \Illuminate\Database\Eloquent\Model {
        $class = Cashier::$paymentModel;

        return $class::forceCreate( $data + [
            'order_id' => $order->getKey(),
            'mollie_payment_id' => 'tr_' . uniqid(),
            'mollie_payment_status' => PaymentStatus::PAID,
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'currency' => 'EUR',
            'amount' => 1000,
            'amount_refunded' => 0,
            'amount_charged_back' => 0,
        ] );
    }


    private function plan( string $role = 'frontend.pro' ): string
    {
        return app( CashierMolliePlan::class )->create( [
            'reference' => '19.00',
            'currency' => 'EUR',
            'interval' => 30,
            'description' => 'Professional',
        ], $this->entitlement( $role ) );
    }


    private function storedAccess(): ?array
    {
        $access = $this->stored->refresh()->getAttribute( 'access' );
        return is_array( $access ) ? $access : null;
    }


    private function subscription( \DateTimeInterface $end,
        string $role = 'frontend.pro'
    ): \Illuminate\Database\Eloquent\Model
    {
        $class = Cashier::$subscriptionModel;

        return $class::forceCreate( [
            'name' => $this->entitlement( $role ),
            'plan' => $this->plan( $role ),
            'owner_type' => $this->stored->getMorphClass(),
            'owner_id' => $this->stored->getKey(),
            'quantity' => 1,
            'tax_percentage' => 0,
            'cycle_started_at' => now(),
            'cycle_ends_at' => $end,
        ] );
    }
}


class MolliePaymentStub
{
    public object $amount;
    public object $amountChargedBack;
    public object $amountRefunded;
    public array $chargebackItems = [];
    public string $createdAt;
    public array $refundItems = [];
    public ?object $metadata;


    public function __construct( public string $id, ?string $token = null )
    {
        $this->amount = (object) ['value' => '10.00', 'currency' => 'EUR'];
        $this->amountChargedBack = (object) ['value' => '0.00', 'currency' => 'EUR'];
        $this->amountRefunded = (object) ['value' => '0.00', 'currency' => 'EUR'];
        $this->createdAt = now()->toISOString();
        $this->metadata = $token === null ? null : (object) ['cms' => $token];
    }


    /**
     * @return array<int, object>
     */
    public function chargebacks(): array
    {
        return $this->chargebackItems;
    }


    public function isPaid(): bool
    {
        return true;
    }


    /**
     * @return array<int, object>
     */
    public function refunds(): array
    {
        return $this->refundItems;
    }
}
