<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookException;
use Aimeos\Cms\WebhookResponse;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;


class DeliverWebhookTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testJobLoadsCurrentCredentialsAndSucceeds() : void
    {
        $webhook = $this->webhook( ['last_error' => ['reason' => 'http_error', 'status' => 503]] );
        $client = new class extends WebhookClient {
            /** @var list<string> */
            public array $secrets = [];
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->secrets = $target['secrets'];
                return new WebhookResponse( 204 );
            }
        };
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
        } finally {
            Carbon::setTestNow();
        }

        $webhook->refresh();

        $this->assertSame( [self::secret( 'test' )], $client->secrets );
        $this->assertNull( $webhook->last_error );
        $this->assertSame( '2026-09-15T12:00:00+00:00', $webhook->last_success_at?->toIso8601String() );
    }


    public function testChangedRevisionInactiveOrExpiredJobIsCancelled() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( 204 );
            }
        };

        $webhook->forceFill( ['revision' => 2] )->save();
        $this->job( $webhook, revision: 1 )->handle( $client, app( WebhookConfig::class ) );
        $webhook->forceFill( ['status' => false] )->save();
        $this->job( $webhook, revision: 2 )->handle( $client, app( WebhookConfig::class ) );
        $this->job( $webhook, revision: 2, expiresAt: now()->subSecond()->timestamp )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( 0, $client->calls );
    }


    public function testExpiredDeliveryIsLoggedOncePerInterval() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( 204 );
            }
        };
        Log::spy();

        foreach( ['delivery-1', 'delivery-2'] as $deliveryId ) {
            $this->job( $webhook, expiresAt: now()->subSecond()->timestamp, deliveryId: $deliveryId )
                ->handle( $client, app( WebhookConfig::class ) );
        }

        $webhook->refresh();

        $this->assertSame( 0, $client->calls );
        $this->assertNull( $webhook->last_error );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_expired', \Mockery::on( fn( array $data ) =>
                $data['webhook_id'] === $webhook->id && $data['tenant_id'] === 'test'
            )
        );
    }


    public function testGloballyDisabledJobIsCancelled() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( 204 );
            }
        };
        config( ['cms.webhooks.enabled' => false] );

        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( 0, $client->calls );
    }


    public function testPermanentFailureIsRecordedWithoutRetry() : void
    {
        $success = Carbon::parse( '2026-09-14 12:00:00 UTC' );
        $webhook = $this->webhook( ['last_success_at' => $success] );
        Log::spy();
        $client = new class extends WebhookClient {
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                return new WebhookResponse( 410 );
            }
        };

        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
        $webhook->refresh();

        $this->assertSame( 'http_error', $webhook->last_error['reason'] );
        $this->assertSame( 410, $webhook->last_error['status'] );
        $this->assertTrue( $success->equalTo( $webhook->last_success_at ) );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['webhook_id'] === $webhook->id && $data['status'] === 410
            )
        );
    }


    public function testUndecryptableSubscriptionIsRecordedWithoutRetry() : void
    {
        $webhook = $this->undecryptable( $this->webhook() );
        $client = new StubWebhookClient();
        $job = $this->job( $webhook )->withFakeQueueInteractions();

        $job->handle( $client, app( WebhookConfig::class ) );
        $webhook->refresh();

        $job->assertNotReleased();
        $this->assertSame( [], $client->options );
        $this->assertSame( 'invalid_encryption', $webhook->last_error['reason'] ?? null );
    }


    public function testTemporaryFailureIsRetriedUntilExpiry() : void
    {
        $updated = Carbon::parse( '2026-09-14 12:00:00 UTC' );
        $webhook = $this->webhook( ['updated_at' => $updated] );
        $client = $this->client( 503 );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );
        Log::spy();

        try {
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $webhook->refresh();

            $job->assertReleased( 30 );
            $this->assertSame( 503, $webhook->last_error['status'] );
            $this->assertTrue( $updated->equalTo( $webhook->updated_at ) );
            Log::shouldNotHaveReceived( 'warning', ['cms.webhook.delivery_failed', \Mockery::any()] );
            Log::shouldHaveReceived( 'warning' )->once()->with(
                'cms.webhook.delivery_retried', \Mockery::on( fn( array $data ) =>
                    $data['webhook_id'] === $webhook->id && $data['reason'] === 'http_error' && $data['status'] === 503
                )
            );

            // Each failure after a pause uses the next backoff delay and the last one repeats
            foreach( [30 => 120, 120 => 600, 600 => 1800, 1800 => 1800] as $pause => $delay )
            {
                Carbon::setTestNow( now()->addSeconds( $pause ) );
                $job = $this->job( $webhook, expiresAt: now()->addDay()->timestamp )->withFakeQueueInteractions();
                $job->handle( $client, app( WebhookConfig::class ) );
                $job->assertReleased( $delay );
            }
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame( 5, $client->calls );
    }


    public function testFailuresDuringAPauseDontAdvanceTheSchedule() : void
    {
        $webhook = $this->webhook();
        $circuit = WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->assertSame( 30, $circuit->open( 'timeout', null, [30, 120] ) );

            // Parallel deliveries which failed while the destination was already paused wait for the same pause
            Carbon::setTestNow( '2026-09-15 12:00:10 UTC' );
            $this->assertSame( 20, $circuit->open( 'timeout', null, [30, 120] ) );

            Carbon::setTestNow( '2026-09-15 12:00:30 UTC' );
            $this->assertSame( 120, $circuit->open( 'timeout', null, [30, 120] ) );

            // Longer Retry-After requests extend the pause without skipping delays
            Carbon::setTestNow( '2026-09-15 12:02:30 UTC' );
            $this->assertSame( 300, $circuit->open( 'http_error', 429, [30, 120], 300 ) );

            // The schedule starts again after the destination was available
            $circuit->close();
            $this->assertSame( 30, $circuit->open( 'timeout', null, [30, 120] ) );

            // Parallel deliveries asking to wait longer extend the pause without advancing the schedule
            $this->assertSame( 30, $circuit->open( 'http_error', 429, [30, 120], 10 ) );
            $this->assertSame( 60, $circuit->open( 'http_error', 429, [30, 120], 60 ) );
            $this->assertSame( 429, $circuit->paused()['status'] ?? null );

            Carbon::setTestNow( '2026-09-15 12:03:30 UTC' );
            $this->assertSame( 120, $circuit->open( 'timeout', null, [30, 120, 600] ) );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testDeliveriesFollowTheScheduleWithoutACache() : void
    {
        config( ['cache.default' => 'null', 'cache.stores.null' => ['driver' => 'null']] );
        $webhook = $this->webhook();
        $client = $this->client( 503 );

        try {
            // Without a stored pause, each delivery uses the backoff delay of its attempt
            foreach( [1 => 30, 3 => 600, 10 => 1800] as $attempts => $delay )
            {
                $job = $this->job( $webhook )->withFakeQueueInteractions();
                $job->job->attempts = $attempts;
                $job->handle( $client, app( WebhookConfig::class ) );
                $job->assertReleased( $delay );
            }
        } finally {
            config( ['cache.default' => 'array'] );
        }

        $this->assertSame( 3, $client->calls );
    }


    public function testSlowResolutionIsRetriedBeforeTheJobTimesOut() : void
    {
        $webhook = $this->webhook();
        $client = new StubWebhookClient();
        // Less than the connect timeout is left before the queue worker would abort the job
        $client->resolveTime = 16000;
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );

            $this->assertNotNull( WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision )->paused() );
        } finally {
            Carbon::setTestNow();
        }

        $job->assertReleased( 30 );
        $this->assertSame( 'timeout', $webhook->refresh()->last_error['reason'] ?? null );
        $this->assertSame( [], $client->options );
    }


    public function testTemporaryFailureIsRecordedIfItCantBeRetried() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 503 );
        Log::spy();

        // Retrying after the delay would exceed the delivery lifetime
        $job = $this->job( $webhook, expiresAt: now()->addSeconds( 10 )->timestamp )->withFakeQueueInteractions();
        $job->handle( $client, app( WebhookConfig::class ) );
        $job->assertNotReleased();

        // Synchronous jobs can't be released
        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( 503, $webhook->refresh()->last_error['status'] );
        Log::shouldHaveReceived( 'warning' )->twice()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['webhook_id'] === $webhook->id && $data['status'] === 503
            )
        );
    }


    public function testFailedJobIsRecordedOrLoggedAsExpired() : void
    {
        $webhook = $this->webhook();
        Log::spy();

        $this->job( $webhook )->failed( new \RuntimeException( 'worker timeout' ) );
        $this->job( $webhook, expiresAt: now()->subSecond()->timestamp )->failed( new \RuntimeException( 'late' ) );
        $webhook->refresh();

        $this->assertSame( 'delivery_failed', $webhook->last_error['reason'] );
        Log::shouldHaveReceived( 'warning' )->with(
            'cms.webhook.delivery_expired', \Mockery::on( fn( array $data ) => $data['webhook_id'] === $webhook->id )
        )->once();
    }


    public function testCancelledDeliveriesDontWaitForThePausedDestination() : void
    {
        $client = $this->client( 503 );
        Log::spy();

        try {
            // Removing the event and replacing the destination cancel queued deliveries
            foreach( [['events' => ['page.deleted']], ['revision' => 2]] as $idx => $change )
            {
                Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );
                $webhook = $this->webhook( [
                    'url' => 'https://example.com/hooks/cms' . $idx,
                    'events' => ['page.deleted', 'page.published'],
                ] );
                $circuit = WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
                $jobs = [
                    $this->job( $webhook )->withFakeQueueInteractions(),
                    $this->job( $webhook, expiresAt: now()->addSeconds( 60 )->timestamp )->withFakeQueueInteractions(),
                    $this->job( $webhook )->withFakeQueueInteractions(),
                ];

                $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
                $this->assertNotNull( $circuit->paused() );

                $webhook->forceFill( $change )->save();
                $jobs[0]->handle( $client, app( WebhookConfig::class ) );
                $jobs[1]->handle( $client, app( WebhookConfig::class ) );

                // Cancelled deliveries don't take the place of the delivery probing the destination
                Carbon::setTestNow( '2026-09-15 12:05:01 UTC' );
                $jobs[2]->handle( $client, app( WebhookConfig::class ) );

                foreach( $jobs as $job ) {
                    $this->assertFalse( $job->job->isReleased() );
                }

                $this->assertTrue( $circuit->probe( 25 ) );
            }

            $this->assertSame( 2, $client->calls );
            Log::shouldNotHaveReceived( 'warning', ['cms.webhook.delivery_failed', \Mockery::any()] );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testTemporaryFailurePausesTheDestination() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 503 );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );

            // Paused deliveries are retried after the pause without contacting the receiver
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );

            $this->assertSame( 1, $client->calls );
            $this->assertTrue( $job->job->isReleased() );
            $this->assertGreaterThanOrEqual( 30, $job->job->releaseDelay );
            $this->assertLessThanOrEqual( 36, $job->job->releaseDelay );

            // Deliveries which can't wait are recorded with the last error
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( 1, $client->calls );
            $this->assertSame( 503, $webhook->refresh()->last_error['status'] );

            // A failure after the pause pauses the destination again immediately for the next delay
            Carbon::setTestNow( '2026-09-15 12:00:30 UTC' );
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 120 );
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( 2, $client->calls );

            // A successful delivery resumes all deliveries and restarts the schedule
            Carbon::setTestNow( '2026-09-15 12:02:30 UTC' );
            $client->status = 204;
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
            $client->status = 503;
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 30 );
            $this->assertSame( 4, $client->calls );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testPauseEndingWhileItsCheckedDefersTheDelivery() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 204 );
        $until = Carbon::parse( '2026-09-15 12:00:30 UTC' );
        $read = false;
        $after = 0;
        Log::spy();

        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );
        WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision )->open( 'timeout', null, [30] );

        Event::listen( CacheHit::class, function( CacheHit $event ) use ( &$read ) {
            $read = $read || str_starts_with( $event->key, 'cms-webhooks-circuit:' );
        } );

        // The pause ends right after paused() compared its end with the current time
        Carbon::setTestNow( function() use ( &$read, &$after, $until ) {
            return $read && $after++ > 0 ? $until->copy() : $until->copy()->subSecond();
        } );

        try {
            // Repeated because the jitter hid the problem in half of the cases
            for( $i = 0; $i < 10; $i++ )
            {
                [$read, $after] = [false, 0];
                $job = $this->job( $webhook, expiresAt: $until->timestamp + 3600 )->withFakeQueueInteractions();
                $job->handle( $client, app( WebhookConfig::class ) );

                $this->assertTrue( $job->job->isReleased() );
            }
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame( 0, $client->calls );
        Log::shouldNotHaveReceived( 'warning', ['cms.webhook.delivery_failed', \Mockery::any()] );
    }


    public function testLastRetryIsMovedBeforeExpiry() : void
    {
        $webhook = $this->webhook();
        $circuit = WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
        $client = $this->client( 503 );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );
        Log::spy();

        try {
            // The destination was paused twice before, so the next pause is 10 minutes
            $circuit->open( 'timeout', null, [30, 120, 600] );
            Carbon::setTestNow( '2026-09-15 12:00:30 UTC' );
            $circuit->open( 'timeout', null, [30, 120, 600] );
            Carbon::setTestNow( '2026-09-15 12:02:30 UTC' );
            $expires = now()->addMinutes( 5 )->timestamp;

            // The last retry runs one minute before the delivery expires instead of never
            $job = $this->job( $webhook, expiresAt: $expires )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 240 );

            // The receiver isn't called if the destination is still paused then
            Carbon::setTestNow( '2026-09-15 12:06:30 UTC' );
            $job = $this->job( $webhook, expiresAt: $expires )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertNotReleased();
            $this->assertSame( 1, $client->calls );
            Log::shouldHaveReceived( 'warning' )->with(
                'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) => $data['status'] === 503 )
            )->once();

            // The delivery is sent if the pause was lifted in the meantime, e.g. by a successful test event
            $circuit->close();
            $client->status = 204;
            $job = $this->job( $webhook, expiresAt: $expires )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( 2, $client->calls );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testRetryAfterDelaysTheRetryAndPausesTheDestination() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 429 );
        $client->retryAfter = 900;
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 900 );

            // Other deliveries wait for the requested time too
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );

            $this->assertSame( 1, $client->calls );
            $this->assertGreaterThanOrEqual( 900, $job->job->releaseDelay );
            $this->assertLessThanOrEqual( 1080, $job->job->releaseDelay );
            $this->assertEquals( now()->addSeconds( 900 ), $webhook->refresh()->paused_until );

            // Longer requests are limited to the longest backoff delay
            Carbon::setTestNow( '2026-09-15 12:15:00 UTC' );
            $client->retryAfter = 999999999;
            $job = $this->job( $webhook, expiresAt: now()->addDays( 2 )->timestamp )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 1800 );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testOnlyOneDeliveryProbesTheDestinationAfterAPause() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 503 );
        $circuit = fn() => WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
            Carbon::setTestNow( '2026-09-15 12:05:01 UTC' );

            // Another delivery is probing the destination
            $this->assertTrue( $circuit()->probe( 25 ) );

            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );

            // They wait until the queue worker would have aborted the probing delivery
            $this->assertSame( 1, $client->calls );
            $this->assertGreaterThanOrEqual( 23, $job->job->releaseDelay );
            $this->assertLessThanOrEqual( 27, $job->job->releaseDelay );

            // Deliveries which can't wait for the result are sent anyway
            $client->status = 204;
            $this->job( $webhook, expiresAt: now()->addSeconds( 60 )->timestamp )
                ->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( 2, $client->calls );

            // The successful delivery resumed the destination for all deliveries
            $this->assertTrue( $circuit()->probe( 25 ) );
            $this->assertTrue( $circuit()->probe( 25 ) );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testPermanentFailureResumesThePausedDestination() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 503 );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
            $this->assertNotNull( $webhook->refresh()->paused_until );

            // The receiver answered, so the destination is available although it rejected the delivery
            Carbon::setTestNow( '2026-09-15 12:05:01 UTC' );
            $client->status = 422;
            $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );

            // The next temporary failure starts the backoff schedule again
            $client->status = 503;
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );
            $job->assertReleased( 30 );

            $this->assertSame( 3, $client->calls );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testPermanentFailuresAndChangedRevisionsDontPauseTheDestination() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 410 );

        $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->assertSame( 2, $client->calls );

        $client->status = 503;
        $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->assertSame( 3, $client->calls );

        // Deactivating or replacing the subscription starts a new revision with a closed circuit
        $webhook->forceFill( ['revision' => 2] )->save();
        $this->job( $webhook )->withFakeQueueInteractions()->handle( $client, app( WebhookConfig::class ) );
        $this->assertSame( 4, $client->calls );
    }


    public function testRemovedEventCancelsQueuedDeliveries() : void
    {
        $webhook = $this->webhook( ['events' => ['page.deleted', 'page.published']] );
        $client = $this->client( 204 );

        $webhook->forceFill( ['events' => ['page.deleted']] )->save();
        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( 0, $client->calls );
        $this->assertNull( $webhook->refresh()->last_success_at );
    }


    public function testEventRemovedDuringDeliveryDoesntChangeTheHealth() : void
    {
        $webhook = $this->webhook( ['events' => ['page.deleted', 'page.published']] );
        $client = new class( $webhook ) extends WebhookClient {
            public function __construct( public Webhook $webhook )
            {
            }
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->webhook->forceFill( ['events' => ['page.deleted']] )->save();
                return new WebhookResponse( 410 );
            }
        };

        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
        $this->assertNull( $webhook->refresh()->last_error );
    }


    public function testHealthyWebhookIsUpdatedAtMostOncePerMinute() : void
    {
        $webhook = $this->webhook();
        $client = $this->client( 204 );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( '2026-09-15 12:00:00', $webhook->refresh()->last_success_at?->format( 'Y-m-d H:i:s' ) );

            Carbon::setTestNow( '2026-09-15 12:00:59 UTC' );
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( '2026-09-15 12:00:00', $webhook->refresh()->last_success_at?->format( 'Y-m-d H:i:s' ) );

            // A failure is cleared immediately by the next success
            $webhook->forceFill( ['last_error' => ['reason' => 'timeout']] )->save();
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $this->assertNull( $webhook->refresh()->last_error );
            $this->assertSame( '2026-09-15 12:00:59', $webhook->last_success_at?->format( 'Y-m-d H:i:s' ) );

            Carbon::setTestNow( '2026-09-15 12:02:00 UTC' );
            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $this->assertSame( '2026-09-15 12:02:00', $webhook->refresh()->last_success_at?->format( 'Y-m-d H:i:s' ) );
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame( 4, $client->calls );
    }


    public function testProcessedDeliveryShowsTheQueueIsRunning() : void
    {
        $webhook = $this->webhook();
        $config = app( WebhookConfig::class );
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $config->queued();
            Carbon::setTestNow( '2026-09-15 12:10:00 UTC' );
            $this->assertSame( now()->subMinutes( 10 )->timestamp, $config->stalled() );

            $this->job( $webhook )->handle( $this->client( 204 ), $config );
            $this->assertNull( $config->stalled() );

            // Failed jobs show a running queue too
            $config->queued();
            Carbon::setTestNow( '2026-09-15 12:20:00 UTC' );
            $this->assertNotNull( $config->stalled() );

            $this->job( $webhook )->failed( null );
            $this->assertNull( $config->stalled() );
        } finally {
            Carbon::setTestNow();
        }
    }


    public function testDeliveryHealthDoesntChangeUpdatedAt() : void
    {
        $updated = Carbon::parse( '2026-09-14 12:00:00 UTC' );
        $webhook = $this->webhook( ['updated_at' => $updated] );

        $this->job( $webhook )->handle( $this->client( 410 ), app( WebhookConfig::class ) );
        $this->assertTrue( $updated->equalTo( $webhook->refresh()->updated_at ) );
        $this->assertSame( 410, $webhook->last_error['status'] ?? null );

        $this->job( $webhook )->handle( $this->client( 204 ), app( WebhookConfig::class ) );
        $this->assertTrue( $updated->equalTo( $webhook->refresh()->updated_at ) );
        $this->assertNull( $webhook->last_error );
        $this->assertNotNull( $webhook->last_success_at );
    }


    public function testRotatedSubscriptionSignsWithBothSecrets() : void
    {
        $webhook = $this->webhook( ['secrets' => self::secrets( 'new', ['old' => now()->addHour()] )] );
        $client = new class extends WebhookClient {
            /** @var list<list<string>> */
            public array $secrets = [];
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->secrets[] = $target['secrets'];
                return new WebhookResponse( 204 );
            }
        };

        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
        $webhook->forceFill( ['secrets' => self::secrets( 'new', ['old' => now()->subSecond()] )] )->save();
        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );

        $this->assertSame( [[self::secret( 'new' ), self::secret( 'old' )], [self::secret( 'new' )]], $client->secrets );
    }


    public function testPolicyFailureIsRecordedWithoutRetry() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                throw new WebhookException( 'destination_not_allowed' );
            }
        };

        $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
        $this->assertSame( 'destination_not_allowed', $webhook->refresh()->last_error['reason'] );
    }


    public function testEmptyBackoffScheduleStillDelaysRetries() : void
    {
        $default = config( 'cms.webhooks.queue.backoff' );
        config( ['cms.webhooks.queue.backoff' => []] );

        try {
            // The queue worker would retry jobs which failed with an exception without waiting
            $this->assertSame( [60], $this->job( $this->webhook() )->backoff() );
        } finally {
            config( ['cms.webhooks.queue.backoff' => $default] );
        }
    }


    public function testOversizedResponseHeadersResumeTheDestination() : void
    {
        $webhook = $this->webhook();
        $circuit = WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
        $client = new class extends WebhookClient {
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                throw new WebhookException( 'response_headers_too_large' );
            }
        };
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $circuit->open( 'timeout', null, [30, 120] );
            Carbon::setTestNow( '2026-09-15 12:00:31 UTC' );

            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );

            // The destination answered, so the next temporary failure starts the schedule again
            $this->assertNull( $circuit->paused() );
            $this->assertSame( 30, $circuit->open( 'timeout', null, [30, 120] ) );
        } finally {
            Carbon::setTestNow();
        }

        $this->assertSame( 'response_headers_too_large', $webhook->refresh()->last_error['reason'] ?? null );
    }


    public function testAbandonedDeliveryReleasesTheDestinationForOthers() : void
    {
        $webhook = $this->webhook();
        $circuit = WebhookCircuit::webhook( 'test', $webhook->id, $webhook->revision );
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                throw new WebhookException( 'destination_not_allowed' );
            }
        };
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $circuit->open( 'timeout', null, [30, 120] );
            Carbon::setTestNow( '2026-09-15 12:00:31 UTC' );

            $this->job( $webhook )->handle( $client, app( WebhookConfig::class ) );
            $job = $this->job( $webhook )->withFakeQueueInteractions();
            $job->handle( $client, app( WebhookConfig::class ) );

            // The abandoned delivery doesn't probe the destination any more, so the next one isn't deferred
            $this->assertFalse( $job->job->isReleased() );
            $this->assertSame( 2, $client->calls );
        } finally {
            Carbon::setTestNow();
        }
    }


    private function client( int $status ) : WebhookClient
    {
        return new class( $status ) extends WebhookClient {
            public int $calls = 0;
            public int $retryAfter = 0;
            public function __construct( public int $status )
            {
            }
            public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
            {
                $this->calls++;
                return new WebhookResponse( $this->status, $this->retryAfter );
            }
        };
    }


    private function job( Webhook $webhook, ?int $revision = null, ?int $expiresAt = null,
        string $deliveryId = 'delivery-id' ) : DeliverWebhook
    {
        return new DeliverWebhook(
            $webhook->id,
            $webhook->tenant_id,
            $revision ?? $webhook->revision,
            'page.published',
            $deliveryId,
            '{"event":"page.published"}',
            $expiresAt ?? now()->addHour()->timestamp,
        );
    }
}
