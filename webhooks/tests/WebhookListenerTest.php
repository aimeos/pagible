<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Jobs\DeliverEndpoint;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;


class WebhookListenerTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testMatchingEventDispatchesEncryptedTenantBoundJob() : void
    {
        $webhook = $this->webhook();
        $id = '01995d6a-cb84-7218-9bb9-79063c4bf681';
        Queue::fake();

        event( new Published(
            'page', $id, 'version-1', 'editor@testbench', [
                'path' => 'webhook-page', 'domain' => 'example.com',
            ], true,
            null, null, null, 'test', 'graphql',
        ) );

        // The queue worker must process the delivery within 10 minutes
        $this->assertNull( app( \Aimeos\Cms\WebhookConfig::class )->stalled() );
        $this->travel( 601 )->seconds();
        $this->assertIsInt( app( \Aimeos\Cms\WebhookConfig::class )->stalled() );
        $this->travelBack();

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $id, $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted
                && $job->webhookId === $webhook->id
                && $job->tenant === 'test'
                && $job->revision === 1
                && $payload['event'] === 'page.published'
                && $payload['tenant_id'] === 'test'
                && preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{3}\+00:00$/', $payload['timestamp'] ) === 1
                && $payload['data'] === [
                    'id' => $id,
                    'version_id' => 'version-1',
                    'path' => 'webhook-page',
                    'domain' => 'example.com',
                ]
                && !isset( $payload['editor'] )
                && !str_contains( serialize( $job ), self::secret( 'test' ) )
                && !str_contains( serialize( $job ), 'example.com/hooks' );
        } );
    }


    public function testConfiguredEndpointsDispatchForMatchingEventsAndTenants() : void
    {
        config( ['cms.webhooks.endpoints' => [
            'all' => [
                'url' => 'http://indexer:8080/all',
                'secret' => self::secret( 'a' ),
                'events' => ['page.published'],
            ],
            'acme' => [
                'url' => 'http://indexer:8080/acme',
                'secret' => self::secret( 'b' ),
                'events' => ['page.published'],
                'tenants' => ['acme'],
            ],
            'files' => [
                'url' => 'http://indexer:8080/files',
                'secret' => self::secret( 'c' ),
                'events' => ['file.purged'],
            ],
        ]] );
        Queue::fake();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'acme' ) );
        } finally {
            config( ['cms.webhooks.endpoints' => []] );
        }

        Queue::assertNotPushed( DeliverWebhook::class );
        Queue::assertPushed( DeliverEndpoint::class, 3 );
        Queue::assertPushed( DeliverEndpoint::class, fn( DeliverEndpoint $job ) =>
            $job->endpoint === 'all' && $job->tenant === 'test'
        );
        Queue::assertPushed( DeliverEndpoint::class, fn( DeliverEndpoint $job ) =>
            $job->endpoint === 'all' && $job->tenant === 'acme'
        );
        Queue::assertPushed( DeliverEndpoint::class, function( DeliverEndpoint $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job instanceof \Illuminate\Contracts\Queue\ShouldBeEncrypted
                && $job->endpoint === 'acme'
                && $job->tenant === 'acme'
                && $job->event === 'page.published'
                && strlen( $job->revision ) === 64
                && $payload['tenant_id'] === 'acme'
                && !str_contains( serialize( $job ), self::secret( 'b' ) )
                && !str_contains( serialize( $job ), 'indexer:8080' );
        } );
    }


    public function testSubscriptionsAndEndpointsShareOnePayload() : void
    {
        $this->webhook();
        config( ['cms.webhooks.endpoints' => ['indexer' => [
            'url' => 'http://indexer:8080/hook',
            'secret' => self::secret( 's' ),
            'events' => ['page.published'],
        ]]] );
        Queue::fake();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['cms.webhooks.endpoints' => []] );
        }

        $bodies = [];
        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( &$bodies ) {
            $bodies[] = $job->body;
            return true;
        } );
        Queue::assertPushed( DeliverEndpoint::class, function( DeliverEndpoint $job ) use ( &$bodies ) {
            $bodies[] = $job->body;
            return true;
        } );

        $this->assertCount( 2, $bodies );
        $this->assertSame( $bodies[0], $bodies[1] );
    }


    public function testInvalidEndpointIsSkippedWithoutStoppingOtherDeliveries() : void
    {
        $this->webhook();
        config( ['cms.webhooks.endpoints' => [
            'broken' => [
                'url' => 'ftp://indexer/hook',
                'secret' => self::secret( 'a' ),
                'events' => ['page.published'],
            ],
            'indexer' => [
                'url' => 'http://indexer:8080/hook',
                'secret' => self::secret( 'b' ),
                'events' => ['page.published'],
            ],
        ]] );
        Queue::fake();
        Log::spy();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['cms.webhooks.endpoints' => []] );
        }

        Queue::assertPushed( DeliverWebhook::class, 1 );
        Queue::assertPushed( DeliverEndpoint::class, 1 );
        Queue::assertPushed( DeliverEndpoint::class, fn( DeliverEndpoint $job ) => $job->endpoint === 'indexer' );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.endpoint_invalid', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'broken' && $data['reason'] === 'invalid_url'
            )
        );
    }


    public function testInvalidDenyListBlocksAllDeliveries() : void
    {
        $webhook = $this->webhook();
        config( [
            'cms.webhooks.deny_cidrs' => ['not-a-cidr'],
            'cms.webhooks.endpoints' => ['indexer' => [
                'url' => 'http://indexer:8080/hook',
                'secret' => self::secret( 's' ),
                'events' => ['page.published'],
            ]],
        ] );
        Queue::fake();
        Log::spy();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['cms.webhooks.deny_cidrs' => [], 'cms.webhooks.endpoints' => []] );
        }

        // Logged once per interval instead of once per event
        Queue::assertNothingPushed();
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_blocked', \Mockery::on( fn( array $data ) => $data['reason'] === 'invalid_policy' )
        );

        // The admin panel shows the server problem instead of an error of each subscription
        $this->assertNull( $webhook->refresh()->last_error );
    }


    public function testSyncQueueDeliversToAllDestinationsIfOneFails() : void
    {
        $default = config( 'queue.default' );
        $client = new class extends WebhookClient {
            /** @var list<string> */
            public array $calls = [];

            /**
             * @param array{url: string, secrets: list<string>, ca: string|null, internal: bool} $target
             */
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls[] = $target['url'];
                return new WebhookResponse( str_ends_with( $target['url'], '/down' ) ? 503 : 204 );
            }
        };
        $this->app->instance( WebhookClient::class, $client );
        config( [
            'queue.default' => 'sync',
            'cms.webhooks.endpoints' => [
                'down' => [
                    'url' => 'http://indexer:8080/down',
                    'secret' => self::secret( 'a' ),
                    'events' => ['page.published'],
                ],
                'up' => [
                    'url' => 'http://indexer:8080/up',
                    'secret' => self::secret( 'b' ),
                    'events' => ['page.published'],
                ],
            ],
        ] );
        Log::spy();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['queue.default' => $default, 'cms.webhooks.endpoints' => []] );
        }

        $this->assertSame( ['http://indexer:8080/down', 'http://indexer:8080/up'], $client->calls );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['endpoint'] === 'down' && $data['status'] === 503
            )
        );
    }


    public function testUnusableQueueBlocksDeliveriesWithoutBreakingTheEvent() : void
    {
        $webhook = $this->webhook();
        $default = config( 'queue.default' );
        config( [
            'queue.default' => 'null',
            'queue.connections.null' => ['driver' => 'null'],
        ] );
        Queue::fake();
        Log::spy();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['queue.default' => $default] );
        }

        Queue::assertNothingPushed();
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_blocked', \Mockery::on( fn( array $data ) => $data['reason'] === 'invalid_queue' )
        );
        $this->assertNull( $webhook->refresh()->last_error );
    }


    public static function failingQueueProvider() : iterable
    {
        yield 'push fails' => ['failing'];
        yield 'unknown driver' => ['unknown'];
    }


    #[\PHPUnit\Framework\Attributes\DataProvider( 'failingQueueProvider' )]
    public function testFailedPushIsShownOnSubscriptionsWithoutStallingTheQueue( string $driver ) : void
    {
        $webhook = $this->webhook();
        $this->failingQueue( $driver );
        Log::spy();

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['cms.webhooks.queue.connection' => null] );
        }

        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_blocked', \Mockery::on( fn( array $data ) => $data['reason'] === 'queue_failed' )
        );
        $this->assertSame( 'queue_failed', $webhook->refresh()->last_error['reason'] ?? null );

        // No delivery is waiting for a queue worker
        $this->travel( 601 )->seconds();
        $this->assertNull( app( \Aimeos\Cms\WebhookConfig::class )->stalled() );
    }


    public function testFailedPushKeepsEarlierDeliveriesWaiting() : void
    {
        $this->webhook();
        app( \Aimeos\Cms\WebhookConfig::class )->queued();
        $this->failingQueue( 'failing' );

        try {
            event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        } finally {
            config( ['cms.webhooks.queue.connection' => null] );
        }

        $this->travel( 601 )->seconds();
        $this->assertIsInt( app( \Aimeos\Cms\WebhookConfig::class )->stalled() );
    }


    public function testPublishedEventUsesProjectedVersionWhenLatestDraftIsNewer() : void
    {
        $this->webhook();
        Queue::fake();

        event( new Published(
            'page', 'page-id', 'future-version', '', [
                'path' => 'future-route', 'domain' => 'future.example',
            ], false,
            tenant: 'test', projection: [
                'version_id' => 'published-version',
                'path' => 'published-route',
                'domain' => 'published.example',
            ],
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->event === 'page.published'
                && $payload['data'] === [
                    'id' => 'page-id',
                    'version_id' => 'published-version',
                    'path' => 'published-route',
                    'domain' => 'published.example',
                ];
        } );
    }


    public function testInactiveNonmatchingScheduledAndOtherTenantEventsAreSkipped() : void
    {
        $this->webhook( ['events' => ['file.deleted']] );
        $this->webhook( ['status' => false, 'url' => 'https://example.com/hooks/inactive'] );
        Queue::fake();

        event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'test' ) );
        event( new Published( 'page', 'page-id', 'v', '', [], false, tenant: 'test' ) );
        event( new Published( 'page', 'page-id', 'v', '', [], true, tenant: 'other' ) );

        Queue::assertNothingPushed();
    }


    public function testBulkEventContainsOnlyOrderedReferences() : void
    {
        $this->webhook();
        Queue::fake();

        event( new Bulk(
            'page', ['page-2', 'page-1'], ['page-1' => 'version-1', 'page-2' => 'version-2'],
            ['published' => true], tenant: 'test', action: 'published',
            projected: ['page-2' => 'published-version-2'],
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $payload['data'] === [
                ['id' => 'page-2', 'version_id' => 'published-version-2'],
                ['id' => 'page-1', 'version_id' => 'version-1'],
            ];
        } );
    }


    public function testMaximumBulkEventIsQueued() : void
    {
        $this->webhook();
        $ids = [];
        $latest = [];

        for( $i = 0; $i < Base::MAX_BULK; $i++ ) {
            $id = sprintf( '00000000-0000-4000-8000-%012d', $i );
            $ids[] = $id;
            $latest[$id] = sprintf( '00000000-0000-4000-8001-%012d', $i );
        }

        Queue::fake();
        event( new Bulk(
            'page', $ids, $latest, ['published' => true], tenant: 'test', action: 'published',
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return count( $payload['data'] ?? [] ) === Base::MAX_BULK;
        } );
    }


    public function testDroppedMapsToDeleted() : void
    {
        $webhook = $this->webhook( ['events' => ['page.deleted']] );
        Queue::fake();

        event( new Dropped(
            'page', 'page-id', 'version-id', '', ['path' => 'old-path', 'domain' => 'example.com'],
            tenant: 'test',
        ) );

        Queue::assertPushed( DeliverWebhook::class, function( DeliverWebhook $job ) use ( $webhook ) {
            $payload = json_decode( $job->body, true, flags: JSON_THROW_ON_ERROR );

            return $job->webhookId === $webhook->id
                && $job->event === 'page.deleted'
                && $payload['data'] === [
                    'id' => 'page-id',
                    'version_id' => 'version-id',
                    'path' => 'old-path',
                    'domain' => 'example.com',
                ];
        } );
    }


    public function testLifecycleNamesAreDerivedAndAllowlisted() : void
    {
        $this->webhook( ['events' => [
            'page.moved', 'element.restored', 'file.purged', 'file.deleted',
        ]] );
        Queue::fake();

        event( new Moved( 'page', 'page-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Restored( 'element', 'element-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Purged( 'file', 'file-id', 'version-id', '', [], tenant: 'test' ) );
        event( new Bulk(
            'file', ['file-id'], ['file-id' => 'version-id'], [], tenant: 'test', action: 'dropped',
        ) );

        foreach( ['page.moved', 'element.restored', 'file.purged', 'file.deleted'] as $name ) {
            Queue::assertPushed( DeliverWebhook::class, fn( DeliverWebhook $job ) => $job->event === $name );
        }
    }


    /**
     * Uses a queue connection whose jobs can't be pushed, the "failing" driver rejects all jobs.
     */
    private function failingQueue( string $driver ) : void
    {
        Queue::extend( 'failing', fn() => new class implements \Illuminate\Queue\Connectors\ConnectorInterface {
            public function connect( array $config )
            {
                return new class extends \Illuminate\Queue\NullQueue {
                    public function bulk( $jobs, $data = '', $queue = null )
                    {
                        throw new \RuntimeException( 'Queue unavailable' );
                    }
                };
            }
        } );

        config( [
            'cms.webhooks.queue.connection' => 'broken',
            'queue.connections.broken' => ['driver' => $driver],
        ] );
    }
}
