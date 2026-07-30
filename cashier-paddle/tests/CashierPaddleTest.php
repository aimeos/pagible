<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\CashierAccess;
use Aimeos\Cms\CashierProvider;
use Aimeos\Cms\CashierPaddle;
use Aimeos\Cms\CashierToken;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Paddle\Events\WebhookReceived;
use Symfony\Component\HttpKernel\Exception\HttpException;


class CashierPaddleTest extends CashierTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    private \App\Models\User $stored;


    protected function setUp(): void
    {
        parent::setUp();

        config()->set( 'cashier.webhook_secret', 'pdl_test' );
        $this->stored = \App\Models\User::forceCreate( [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password',
        ] );
        $this->stored->customer()->create( [
            'paddle_id' => 'ctm_1',
            'name' => 'Test',
            'email' => 'test@example.com',
        ] );
    }


    public function testCheckoutBindsCmsMetadataToServerTransaction(): void
    {
        config()->set( 'cashier.api_key', 'pdl_test' );
        Http::fake( [
            'https://api.paddle.com/transactions' => Http::response( ['data' => ['id' => 'txn_1']] ),
            'https://api.paddle.com/transactions/txn_1' => Http::response( ['data' => ['id' => 'txn_1']] ),
        ] );

        $view = app( CashierPaddle::class )->checkout( $this->stored, [
            'access' => 'frontend.pro',
            'kind' => 'subscription',
            'reference' => 'pri_monthly',
            'url' => '/account',
        ] );

        $this->assertSame( 'cms-cashier::paddle', $view->name() );
        $this->assertSame( 'txn_1', $view->getData()['transaction'] );
        $this->assertStringContainsString( '"transactionId":"txn_1"', $view->render() );
        $this->assertStringNotContainsString( 'signed', $view->render() );
        Http::assertSent( fn( $request ) => $request->method() === 'POST'
            && $request->url() === 'https://api.paddle.com/transactions'
            && $request['items'] === [['price_id' => 'pri_monthly', 'quantity' => 1]]
            && $request['customer_id'] === 'ctm_1'
        );
        Http::assertSent( function( $request ) {
            if( $request->method() !== 'PATCH'
                || $request->url() !== 'https://api.paddle.com/transactions/txn_1'
            ) {
                return false;
            }

            $bound = app( CashierToken::class )->parse( (string) $request['custom_data']['cms'] );
            $cms = is_array( $bound ) && is_string( $bound['cms'] ?? null )
                ? app( CashierToken::class )->read( $bound['cms'] )
                : null;

            return ( $bound['source'] ?? null ) === 'txn_1'
                && ( $cms['provider'] ?? null ) === 'paddle'
                && ( $cms['access'] ?? null ) === 'frontend.pro'
                && ( $cms['reference'] ?? null ) === 'pri_monthly'
                && $request['custom_data']['subscription_type']
                    === CashierAccess::subscription( 'test', 'frontend.pro' );
        } );
    }


    public function testCheckoutRequiresWebhookSecret(): void
    {
        config()->set( 'cashier.webhook_secret', '' );

        try {
            app( CashierPaddle::class )->checkout( $this->stored, [
                'access' => 'frontend.pro',
                'kind' => 'subscription',
                'reference' => 'pri_monthly',
                'url' => '/account',
            ] );
            $this->fail( 'Checkout must be disabled without webhook verification.' );
        } catch( HttpException $e ) {
            $this->assertSame( 503, $e->getStatusCode() );
        }
    }


    public function testCancellationRejectsUnrelatedSubscription(): void
    {
        $subscription = $this->stored->subscriptions()->create( [
            'type' => 'application-premium',
            'paddle_id' => 'sub_unrelated',
            'status' => 'active',
        ] );

        $this->expectException( ModelNotFoundException::class );
        app( CashierPaddle::class )->cancel( $this->stored, $subscription->paddle_id );
    }


    public function testFailureKeepsAccessAndRefundRemovesSubscription(): void
    {
        $token = $this->token( 'subscription', 'frontend.pro' );
        $webhooks = app( CashierPaddle::class );
        config()->set( 'cashier.webhook_secret', 'pdl_test' );

        event( new WebhookReceived( [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_1',
                'subscription_id' => 'sub_1',
                'customer_id' => 'ctm_1',
                'items' => [[
                    'price' => ['id' => 'pri_monthly'],
                    'quantity' => 1,
                ]],
                'billing_period' => ['ends_at' => now()->addMonth()->toISOString()],
                'custom_data' => ['cms' => $token],
            ],
        ] ) );
        $webhooks->webhook( [
            'event_type' => 'transaction.payment_failed',
            'data' => ['custom_data' => ['cms' => $token]],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.pro', $stored['test|paddle|sub_1']['role'] );

        $webhooks->webhook( [
            'event_type' => 'adjustment.updated',
            'data' => [
                'status' => 'approved',
                'action' => 'refund',
                'type' => 'partial',
                'subscription_id' => 'sub_1',
                'transaction_id' => 'txn_1',
                'custom_data' => ['cms' => $token],
            ],
        ] );

        $this->assertIsArray( $this->storedAccess() );

        $webhooks->webhook( [
            'event_type' => 'adjustment.updated',
            'data' => [
                'status' => 'approved',
                'action' => 'refund',
                'subscription_id' => 'sub_1',
                'transaction_id' => 'txn_1',
                'custom_data' => ['cms' => $token],
            ],
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|paddle|sub_1']['role'] );
    }


    public function testRefundLoadsMissingTransactionMetadataBeforeDelayedGrant(): void
    {
        $token = $this->token( 'once', 'frontend.course', source: 'txn_proof' );
        $driver = new class(
            app( CashierAccess::class ),
            app( CashierToken::class ),
            $token,
        ) extends CashierPaddle {
            public int $proofs = 0;


            public function __construct( CashierAccess $access, CashierToken $tokens,
                private string $token
            ) {
                parent::__construct( $access, $tokens );
            }


            protected function transaction( string $id ) : array|object
            {
                $this->proofs++;
                return [
                    'id' => $id,
                    'custom_data' => ['cms' => $this->token],
                ];
            }
        };

        $driver->webhook( [
            'occurred_at' => '2026-01-01T00:00:02Z',
            'event_type' => 'adjustment.updated',
            'data' => [
                'status' => 'approved',
                'action' => 'refund',
                'transaction_id' => 'txn_proof',
            ],
        ] );
        $driver->webhook( [
            'occurred_at' => '2026-01-01T00:00:01Z',
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_proof',
                'customer_id' => 'ctm_1',
                'items' => [[
                    'price' => ['id' => 'pri_monthly'],
                    'quantity' => 1,
                ]],
                'custom_data' => ['cms' => $token],
            ],
        ] );

        $stored = $this->storedAccess();
        $this->assertSame( 1, $driver->proofs );
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|paddle|txn_proof']['role'] );
        $this->assertSame(
            ( new \DateTimeImmutable( '2026-01-01T00:00:02Z' ) )->getTimestamp() * 1000,
            $stored['test|paddle|txn_proof']['at'],
        );
    }


    public function testWebhookEndpointRequiresSecret(): void
    {
        config()->set( 'cashier.webhook_secret', '' );

        $this->postJson( route( 'cashier.webhook' ), [
            'event_type' => 'subscription.created',
        ] )->assertStatus( 503 );
    }


    public function testServiceProviderBindsDriver(): void
    {
        $this->assertInstanceOf( CashierPaddle::class, app( CashierProvider::class ) );
    }


    public function testPriceKindMismatchIsIgnored(): void
    {
        app( CashierPaddle::class )->webhook( [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_1',
                'subscription_id' => 'sub_1',
                'customer_id' => 'ctm_1',
                'items' => [[
                    'price' => ['id' => 'pri_monthly'],
                    'quantity' => 1,
                ]],
                'billing_period' => ['ends_at' => now()->addMonth()->toISOString()],
                'custom_data' => ['cms' => $this->token( 'once', 'frontend.course' )],
            ],
        ] );

        $this->assertNull( $this->storedAccess() );
    }


    public function testStatusUpdateDoesNotRestoreRefundedSubscription(): void
    {
        $token = $this->token( 'subscription', 'frontend.pro' );
        $webhooks = app( CashierPaddle::class );
        config()->set( 'cashier.api_key', 'pdl_test' );
        Http::fake( [
            'https://api.paddle.com/transactions/txn_1' => Http::response( [
                'data' => ['id' => 'txn_1', 'subscription_id' => 'sub_1'],
            ] ),
        ] );
        $data = [
            'id' => 'txn_1',
            'subscription_id' => 'sub_1',
            'customer_id' => 'ctm_1',
            'items' => [[
                'price' => ['id' => 'pri_monthly'],
                'quantity' => 1,
            ]],
            'billing_period' => ['ends_at' => now()->addMonth()->toISOString()],
            'custom_data' => ['cms' => $token],
        ];

        $webhooks->webhook( [
            'occurred_at' => '2026-01-01T00:00:01Z',
            'event_type' => 'transaction.completed',
            'data' => $data,
        ] );
        $webhooks->webhook( [
            'occurred_at' => '2026-01-01T00:00:02Z',
            'event_type' => 'adjustment.updated',
            'data' => [
                'status' => 'approved',
                'action' => 'refund',
                'subscription_id' => 'sub_1',
                'transaction_id' => 'txn_1',
                'custom_data' => ['cms' => $token],
            ],
        ] );
        $webhooks->webhook( [
            'occurred_at' => '2026-01-01T00:00:03Z',
            'event_type' => 'subscription.updated',
            'data' => array_replace( $data, ['id' => 'sub_1', 'status' => 'active'] ),
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertNull( $stored['test|paddle|sub_1']['role'] );

        $webhooks->webhook( [
            'occurred_at' => '2026-01-01T00:00:04Z',
            'event_type' => 'transaction.completed',
            'data' => array_replace( $data, ['id' => 'txn_2'] ),
        ] );

        $stored = $this->storedAccess();
        $this->assertIsArray( $stored );
        $this->assertSame( 'frontend.pro', $stored['test|paddle|sub_1']['role'] );
    }


    public function testPurchasedPriceAndCustomerMustMatchSignedMetadata(): void
    {
        $webhooks = app( CashierPaddle::class );

        foreach( [
            ['customer_id' => 'ctm_1', 'reference' => 'pri_cheaper'],
            ['customer_id' => 'ctm_attacker', 'reference' => 'pri_course'],
        ] as $purchase )
        {
            $id = 'txn_' . $purchase['reference'];
            $webhooks->webhook( [
                'event_type' => 'transaction.completed',
                'data' => [
                    'id' => $id,
                    'customer_id' => $purchase['customer_id'],
                    'items' => [[
                        'price' => ['id' => $purchase['reference']],
                        'quantity' => 1,
                    ]],
                    'custom_data' => ['cms' => $this->token(
                        'once',
                        'frontend.course',
                        'pri_course',
                        $id,
                    )],
                ],
            ] );
        }

        $this->assertNull( $this->storedAccess() );
    }


    public function testTransactionBoundMetadataCannotBeReplayed(): void
    {
        app( CashierPaddle::class )->webhook( [
            'event_type' => 'transaction.completed',
            'data' => [
                'id' => 'txn_2',
                'customer_id' => 'ctm_1',
                'items' => [[
                    'price' => ['id' => 'pri_course'],
                    'quantity' => 1,
                ]],
                'custom_data' => ['cms' => $this->token(
                    'once',
                    'frontend.course',
                    'pri_course',
                    'txn_1',
                )],
            ],
        ] );

        $this->assertNull( $this->storedAccess() );
    }


    private function storedAccess(): ?array
    {
        $access = $this->stored->refresh()->getAttribute( 'access' );
        return is_array( $access ) ? $access : null;
    }


    private function token( string $kind, string $access, string $reference = 'pri_monthly',
        string $source = 'txn_1'
    ): string
    {
        $tokens = app( CashierToken::class );
        $cms = $tokens->create( $this->stored, 'paddle', [
            'access' => $access,
            'kind' => $kind,
            'reference' => $reference,
        ] );

        return $tokens->make( ['cms' => $cms, 'source' => $source] );
    }
}
