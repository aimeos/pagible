<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierStripe;
use Aimeos\Cms\CashierToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Checkout;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\SubscriptionBuilder;
use Stripe\Checkout\Session;
use Symfony\Component\HttpKernel\Exception\HttpException;


class CashierStripeTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    private \App\Models\User $stored;


    protected function setUp(): void
    {
        parent::setUp();

        config()->set( 'cashier.webhook.secret', 'whsec_test' );
        $this->stored = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
    }


    public function testCheckoutReturnsCashierCheckout(): void
    {
        $checkout = new Checkout( null, Session::constructFrom( [
            'id' => 'cs_1',
            'url' => 'https://checkout.stripe.test/session',
        ] ) );
        $user = $this->checkoutUser();
        $user->shouldReceive( 'checkout' )->once()->andReturn( $checkout );

        $result = app( CashierStripe::class )->checkout( $user, [
            'access' => 'frontend.course',
            'kind' => 'once',
            'reference' => 'price_test',
            'url' => '/account',
        ] );

        $this->assertSame( $checkout, $result );
    }


    public function testCheckoutRequiresWebhookSecret(): void
    {
        config()->set( 'cashier.webhook.secret', '' );

        try {
            app( CashierStripe::class )->checkout( $this->stored, [
                'access' => 'frontend.course',
                'kind' => 'once',
                'reference' => 'price_test',
                'url' => '/account',
            ] );
            $this->fail( 'Checkout must be disabled without webhook verification.' );
        } catch( HttpException $e ) {
            $this->assertSame( 503, $e->getStatusCode() );
        }
    }


    public function testSubscriptionCheckoutUsesTenantMarker(): void
    {
        $checkout = new Checkout( null, Session::constructFrom( [
            'id' => 'cs_1',
            'url' => 'https://checkout.stripe.test/session',
        ] ) );
        $builder = \Mockery::mock( SubscriptionBuilder::class );
        $builder->shouldReceive( 'checkout' )->once()->andReturn( $checkout );
        $user = $this->checkoutUser();
        $user->shouldReceive( 'newSubscription' )
            ->once()
            ->with(
                CashierAccess::subscription( 'test', 'frontend.pro' ),
                'price_test',
            )
            ->andReturn( $builder );

        $result = app( CashierStripe::class )->checkout( $user, [
            'access' => 'frontend.pro',
            'kind' => 'subscription',
            'reference' => 'price_test',
            'url' => '/account',
        ] );

        $this->assertSame( $checkout, $result );
    }


    public function testWebhookEndpointRequiresSecret(): void
    {
        config()->set( 'cashier.webhook.secret', '' );

        $this->postJson( route( 'cashier.webhook' ), [
            'type' => 'customer.subscription.created',
        ] )->assertStatus( 503 );
    }


    public function testDelayedOneTimePaymentHasNoEndDate(): void
    {
        $token = $this->token( 'once', 'frontend.course' );
        config()->set( 'cashier.webhook.secret', 'whsec_test' );

        event( new WebhookReceived( [
            'type' => 'checkout.session.async_payment_succeeded',
            'data' => ['object' => [
                'id' => 'cs_1',
                'mode' => 'payment',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_1',
                'metadata' => ['cms' => $token],
            ]],
        ] ) );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|pi_1']['end'] );
    }


    public function testFailureKeepsPaidThroughAccessAndDeletionRemovesIt(): void
    {
        $token = $this->token( 'subscription', 'frontend.pro' );
        $webhooks = app( CashierStripe::class );

        $webhooks->webhook( [
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'subscription' => [
                    'id' => 'sub_1',
                    'status' => 'active',
                    'current_period_end' => now()->addMonth()->timestamp,
                    'items' => ['data' => [['price' => ['id' => 'price_test']]]],
                    'metadata' => ['cms' => $token],
                ],
            ]],
        ] );
        $webhooks->webhook( [
            'type' => 'invoice.payment_failed',
            'data' => ['object' => ['metadata' => ['cms' => $token]]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.pro', $stored['test|stripe|sub_1']['role'] );

        $webhooks->webhook( [
            'type' => 'customer.subscription.deleted',
            'data' => ['object' => [
                'id' => 'sub_1',
                'metadata' => ['cms' => $token],
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|sub_1']['role'] );
    }


    public function testServiceProviderBindsDriver(): void
    {
        $this->assertInstanceOf( CashierStripe::class, app( CashierProvider::class ) );
    }


    public function testOneTimePaymentHasNoEndDate(): void
    {
        $token = $this->token( 'once', 'frontend.course' );

        app( CashierStripe::class )->webhook( [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_1',
                'mode' => 'payment',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_1',
                'metadata' => ['cms' => $token],
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( [
            'test|stripe|pi_1' => [
                'role' => 'frontend.course',
                'end' => null,
                'at' => $stored['test|stripe|pi_1']['at'],
            ],
        ], $stored );
    }


    public function testOnlyPaidInvoiceRestoresRevokedSubscription(): void
    {
        $token = $this->token( 'subscription', 'frontend.pro' );
        $webhooks = app( CashierStripe::class );
        $subscription = [
            'id' => 'sub_1',
            'status' => 'active',
            'current_period_end' => now()->addMonth()->timestamp,
            'items' => ['data' => [['price' => ['id' => 'price_test']]]],
            'metadata' => ['cms' => $token],
        ];

        $webhooks->webhook( [
            'created' => 100,
            'type' => 'invoice.paid',
            'data' => ['object' => ['subscription' => $subscription]],
        ] );
        $webhooks->webhook( [
            'created' => 200,
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'subscription' => 'sub_1',
                'amount' => 1000,
                'amount_refunded' => 1000,
                'metadata' => ['cms' => $token],
            ]],
        ] );
        $webhooks->webhook( [
            'created' => 300,
            'type' => 'customer.subscription.updated',
            'data' => ['object' => $subscription],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|sub_1']['role'] );
        $this->assertSame( 200000, $stored['test|stripe|sub_1']['at'] );

        $webhooks->webhook( [
            'created' => 400,
            'type' => 'invoice.paid',
            'data' => ['object' => ['subscription' => $subscription]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.pro', $stored['test|stripe|sub_1']['role'] );
        $this->assertSame( 400000, $stored['test|stripe|sub_1']['at'] );
    }


    public function testRefundFallsBackToVerifiedCustomerOwnership(): void
    {
        \Illuminate\Support\Facades\Schema::table( 'users', function( \Illuminate\Database\Schema\Blueprint $table ) {
            $table->string( 'stripe_id' )->nullable();
        } );
        $this->stored->setAttribute( 'stripe_id', 'cus_1' )->save();
        app( CashierAccess::class )->grant(
            $this->stored,
            'test',
            'frontend.course',
            'stripe',
            'pi_1',
            null,
        );

        app( CashierStripe::class )->webhook( [
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'customer' => 'cus_1',
                'payment_intent' => 'pi_1',
                'amount' => 1000,
                'amount_refunded' => 1000,
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|pi_1']['role'] );
    }


    public function testDisputeResolvesChargeBeforeRevokingSubscription(): void
    {
        \Illuminate\Support\Facades\Schema::table( 'users', function( \Illuminate\Database\Schema\Blueprint $table ) {
            $table->string( 'stripe_id' )->nullable();
        } );
        $this->stored->setAttribute( 'stripe_id', 'cus_1' )->save();
        app( CashierAccess::class )->grant(
            $this->stored,
            'test',
            'frontend.pro',
            'stripe',
            'sub_1',
            now()->addMonth(),
        );

        $driver = new class(
            app( CashierAccess::class ),
            app( CashierToken::class ),
        ) extends CashierStripe {
            public mixed $requested = null;


            protected function charge( mixed $charge ) : array|object
            {
                $this->requested = $charge;

                return [
                    'customer' => 'cus_1',
                    'invoice' => ['subscription' => 'sub_1'],
                ];
            }
        };
        $driver->webhook( [
            'created' => now()->addSecond()->timestamp,
            'type' => 'charge.dispute.created',
            'data' => ['object' => ['charge' => 'ch_1']],
        ] );

        $stored = $this->storedAccess();
        $this->assertSame( 'ch_1', $driver->requested );
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|sub_1']['role'] );
    }


    public function testDifferentSubscriptionPriceDoesNotRenewAccess(): void
    {
        $token = $this->token( 'subscription', 'frontend.pro' );
        $webhooks = app( CashierStripe::class );
        $first = now()->addMonth()->startOfSecond();

        $webhooks->webhook( [
            'created' => 100,
            'type' => 'invoice.paid',
            'data' => ['object' => [
                'subscription' => [
                    'id' => 'sub_1',
                    'status' => 'active',
                    'current_period_end' => $first->timestamp,
                    'items' => ['data' => [['price' => ['id' => 'price_test']]]],
                    'metadata' => ['cms' => $token],
                ],
            ]],
        ] );
        $webhooks->webhook( [
            'created' => 200,
            'type' => 'customer.subscription.updated',
            'data' => ['object' => [
                'id' => 'sub_1',
                'status' => 'active',
                'current_period_end' => now()->addMonths( 2 )->timestamp,
                'items' => ['data' => [['price' => ['id' => 'price_basic']]]],
                'metadata' => ['cms' => $token],
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame(
            $first->timestamp * 1000,
            $stored['test|stripe|sub_1']['end'],
        );
        $this->assertSame( 100000, $stored['test|stripe|sub_1']['at'] );
    }


    public function testTamperedMetadataIsIgnored(): void
    {
        app( CashierStripe::class )->webhook( [
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'mode' => 'payment',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_1',
                'metadata' => ['cms' => $this->token( 'once', 'frontend.course' ) . 'x'],
            ]],
        ] );

        $this->assertNull( $this->storedAccess() );
    }


    public function testRequiredWebhookEventsAreConfigured(): void
    {
        $events = (array) config( 'cashier.webhook.events' );

        $this->assertContains( 'checkout.session.completed', $events );
        $this->assertContains( 'checkout.session.async_payment_succeeded', $events );
        $this->assertContains( 'customer.subscription.created', $events );
        $this->assertContains( 'customer.subscription.updated', $events );
        $this->assertContains( 'customer.subscription.deleted', $events );
        $this->assertContains( 'invoice.paid', $events );
        $this->assertContains( 'charge.refunded', $events );
        $this->assertContains( 'charge.dispute.created', $events );
    }


    public function testRevocationRejectsDelayedPaymentEvent(): void
    {
        $token = $this->token( 'once', 'frontend.course' );
        $webhooks = app( CashierStripe::class );

        $webhooks->webhook( [
            'id' => 'evt_refund',
            'created' => 200,
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_1',
                'amount' => 1000,
                'amount_refunded' => 1000,
                'metadata' => ['cms' => $token],
            ]],
        ] );
        $webhooks->webhook( [
            'id' => 'evt_payment',
            'created' => 100,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'id' => 'cs_1',
                'mode' => 'payment',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_1',
                'metadata' => ['cms' => $token],
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|pi_1']['role'] );
        $this->assertSame( 200000, $stored['test|stripe|pi_1']['at'] );
    }


    public function testRevocationLoadsMissingSourceMetadataBeforeDelayedGrant(): void
    {
        $token = $this->token( 'once', 'frontend.course' );
        $driver = new class(
            app( CashierAccess::class ),
            app( CashierToken::class ),
            $token,
        ) extends CashierStripe {
            public int $proofs = 0;


            public function __construct( CashierAccess $access, CashierToken $tokens,
                private string $token
            ) {
                parent::__construct( $access, $tokens );
            }


            protected function proof( array|object $data, string $id ) : array|object
            {
                $this->proofs++;
                return ['id' => $id, 'metadata' => ['cms' => $this->token]];
            }
        };

        $driver->webhook( [
            'created' => 200,
            'type' => 'charge.refunded',
            'data' => ['object' => [
                'payment_intent' => 'pi_proof',
                'amount' => 1000,
                'amount_refunded' => 1000,
            ]],
        ] );
        $driver->webhook( [
            'created' => 100,
            'type' => 'checkout.session.completed',
            'data' => ['object' => [
                'mode' => 'payment',
                'payment_status' => 'paid',
                'payment_intent' => 'pi_proof',
                'metadata' => ['cms' => $token],
            ]],
        ] );

        $stored = $this->storedAccess();
        $this->assertSame( 1, $driver->proofs );
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|stripe|pi_proof']['role'] );
        $this->assertSame( 200000, $stored['test|stripe|pi_proof']['at'] );
    }


    private function checkoutUser(): \App\Models\User
    {
        $user = \Mockery::mock( \App\Models\User::class )->makePartial();
        $user->setRawAttributes( $this->stored->getAttributes(), true );
        $user->setTable( $this->stored->getTable() );
        $user->exists = true;

        return $user;
    }


    private function storedAccess(): ?array
    {
        $access = $this->stored->refresh()->getAttribute( 'access' );
        return is_array( $access ) ? $access : null;
    }


    private function token( string $kind, string $access ): string
    {
        return app( CashierToken::class )->create( $this->stored, 'stripe', [
            'access' => $access,
            'kind' => $kind,
            'reference' => 'price_test',
        ] );
    }
}
