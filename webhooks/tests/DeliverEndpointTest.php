<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Jobs\DeliverEndpoint;
use Aimeos\Cms\WebhookConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


class DeliverEndpointTest extends WebhookTestAbstract
{
    protected function tearDown() : void
    {
        config( ['cms.webhooks.endpoints' => []] );
        parent::tearDown();
    }


    public function testDeliversToConfiguredEndpoint() : void
    {
        $this->configure();
        $client = new StubEndpointClient();

        $this->job()->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( [[[
            'url' => 'http://indexer:8080/hook',
            'secrets' => [self::secret( 's' )],
            'ca' => __FILE__,
            'internal' => true,
        ], '{"event":"page.published"}']], $client->calls );
    }


    public function testChangedRemovedUnsubscribedOrExpiredJobIsCancelled() : void
    {
        $client = new StubEndpointClient();
        $changes = [
            'url' => ['url' => 'http://indexer:9090/hook'],
            'events' => ['events' => ['page.deleted']],
            'tenants' => ['tenants' => ['other']],
        ];

        foreach( $changes as $change ) {
            $this->configure();
            $job = $this->job();
            $this->configure( $change );
            $job->handle( $client, app( WebhookConfig::class ) );
        }

        $this->configure();
        $job = $this->job();
        config( ['cms.webhooks.endpoints' => []] );
        $job->handle( $client, app( WebhookConfig::class ) );

        $this->configure();
        $this->job( now()->subSecond()->timestamp )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( [], $client->calls );
    }


    public function testExpiredDeliveryIsLogged() : void
    {
        $this->configure();
        $client = new StubEndpointClient();
        Log::spy();

        $this->job( now()->subSecond()->timestamp )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( [], $client->calls );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_expired', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['tenant_id'] === 'acme'
            )
        );
    }


    public function testUnrelatedChangeKeepsQueuedDelivery() : void
    {
        $this->configure();
        $job = $this->job();
        $this->configure( ['events' => ['page.published', 'file.purged'], 'ca' => __DIR__ . '/DeliverWebhookTest.php'] );
        $client = new StubEndpointClient();

        $job->handle( $client, app( WebhookConfig::class ) );

        $this->assertCount( 1, $client->calls );
    }


    public function testEquivalentUrlKeepsQueuedDelivery() : void
    {
        $this->configure();
        $job = $this->job();
        $this->configure( ['url' => 'http://INDEXER:8080/sub/../hook'] );
        $client = new StubEndpointClient();

        $job->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( 'http://indexer:8080/hook', $client->calls[0][0]['url'] ?? null );
    }


    public function testChangedSecretsSignQueuedDelivery() : void
    {
        $this->configure();
        $job = $this->job();
        $secrets = [self::secret( 't' ), self::secret( 's' )];
        $this->configure( ['secret' => $secrets] );
        $client = new StubEndpointClient();

        $job->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( $secrets, $client->calls[0][0]['secrets'] ?? null );
    }


    public function testPermanentFailureIsLoggedWithoutRetry() : void
    {
        $this->configure();
        Log::spy();
        $client = new StubEndpointClient();
        $client->status = 410;

        $this->job()->handle( $client, app( WebhookConfig::class ) );

        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['tenant_id'] === 'acme'
                && $data['reason'] === 'http_error' && $data['status'] === 410
            )
        );
    }


    public function testTemporaryFailureIsRetriedAndLoggedOncePerInterval() : void
    {
        $this->configure();
        Log::spy();
        $client = new StubEndpointClient();
        $client->status = 503;

        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            // The second delivery fails after the pause and uses the next backoff delay
            foreach( ['delivery-1' => 30, 'delivery-2' => 120] as $deliveryId => $delay )
            {
                $job = $this->job( deliveryId: $deliveryId )->withFakeQueueInteractions();
                $job->handle( $client, app( WebhookConfig::class ) );
                $job->assertReleased( $delay );
                Carbon::setTestNow( now()->addSeconds( $delay ) );
            }
        } finally {
            Carbon::setTestNow();
        }

        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_retried', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['status'] === 503
            )
        );
    }


    public function testTemporaryFailureIsLoggedIfItCantBeRetried() : void
    {
        $this->configure();
        Log::spy();
        $client = new StubEndpointClient();
        $client->status = 503;

        $this->job()->handle( $client, app( WebhookConfig::class ) );

        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['status'] === 503
            )
        );
    }


    public function testPausedEndpointIsPausedForAllTenants() : void
    {
        $this->configure( ['tenants' => ['acme', 'other']] );
        $client = new StubEndpointClient();
        $client->status = 503;

        $this->job()->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $job = $this->job( tenant: 'other' )->withFakeQueueInteractions();
        $job->handle( $client, app( WebhookConfig::class ) );

        $this->assertCount( 1, $client->calls );
        $this->assertTrue( $job->job->isReleased() );
    }


    public function testChangedEndpointDoesntWaitForThePausedDestination() : void
    {
        $this->configure();
        $client = new StubEndpointClient();
        $client->status = 503;
        $jobs = [
            $this->job()->withFakeQueueInteractions(),
            $this->job( now()->addSeconds( 60 )->timestamp )->withFakeQueueInteractions(),
        ];

        $this->job()->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->configure( ['url' => 'http://indexer:9090/hook'] );
        Log::spy();

        foreach( $jobs as $job ) {
            $job->handle( $client, app( WebhookConfig::class ) );
            $this->assertFalse( $job->job->isReleased() );
        }

        $this->assertCount( 1, $client->calls );
        Log::shouldNotHaveReceived( 'warning' );
    }


    public function testEndpointInvalidatedAfterQueueingIsLoggedWithoutDelivery() : void
    {
        $this->configure();
        $job = $this->job();
        $this->configure( ['ca' => '/nonexistent/ca.pem'] );
        $client = new StubEndpointClient();
        Log::spy();

        $job->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( [], $client->calls );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['reason'] === 'invalid_ca'
            )
        );
    }


    public function testInvalidDenyListBlocksQueuedDelivery() : void
    {
        $this->configure( ['url' => 'http://10.0.0.5:8080/hook'] );
        $job = $this->job();
        $client = new StubEndpointClient();
        config( ['cms.webhooks.deny_cidrs' => ['not-a-cidr']] );
        Log::spy();

        try {
            $job->handle( $client, app( WebhookConfig::class ) );
        } finally {
            config( ['cms.webhooks.deny_cidrs' => []] );
        }

        $this->assertSame( [], $client->calls );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'indexer' && $data['reason'] === 'invalid_policy'
            )
        );
    }


    /**
     * @param array<string, mixed> $values
     */
    private function configure( array $values = [] ) : void
    {
        config( ['cms.webhooks.endpoints' => ['indexer' => $values + [
            'url' => 'http://indexer:8080/hook',
            'secret' => self::secret( 's' ),
            'events' => ['page.published'],
            'tenants' => ['acme'],
            'ca' => __FILE__,
        ]]] );
    }


    private function job( ?int $expiresAt = null, string $deliveryId = 'delivery-id', string $tenant = 'acme' ) : DeliverEndpoint
    {
        $revision = app( WebhookConfig::class )->subscribed( 'page.published', $tenant )['indexer'] ?? '';

        return new DeliverEndpoint(
            'indexer',
            $tenant,
            $revision,
            'page.published',
            $deliveryId,
            '{"event":"page.published"}',
            $expiresAt ?? now()->addHour()->timestamp,
        );
    }
}
