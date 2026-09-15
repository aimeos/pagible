<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


class DeliverWebhookTest extends WebhookTestAbstract
{
    use RefreshDatabase;


    public function testJobLoadsCurrentCredentialsAndSucceeds() : void
    {
        $webhook = $this->webhook( [
            'failures' => 2,
            'last_error' => ['reason' => 'http_error', 'status' => 503],
        ] );
        $client = new class extends WebhookClient {
            public ?Webhook $webhook = null;
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                $this->webhook = $webhook;
                return 204;
            }
        };
        Carbon::setTestNow( '2026-09-15 12:00:00 UTC' );

        try {
            $this->job( $webhook )->handle( $client );
        } finally {
            Carbon::setTestNow();
        }

        $webhook->refresh();

        $this->assertSame( 'test-secret', $client->webhook?->secret );
        $this->assertSame( 0, $webhook->failures );
        $this->assertNull( $webhook->last_error );
        $this->assertSame( '2026-09-15T12:00:00+00:00', $webhook->last_success_at?->toIso8601String() );
    }


    public function testChangedRevisionInactiveOrExpiredJobIsCancelled() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                $this->calls++;
                return 204;
            }
        };

        $webhook->forceFill( ['revision' => 2] )->save();
        $this->job( $webhook, revision: 1 )->handle( $client );
        $webhook->forceFill( ['status' => false] )->save();
        $this->job( $webhook, revision: 2 )->handle( $client );
        $this->job( $webhook, revision: 2, expiresAt: now()->subSecond()->timestamp )->handle( $client );

        $this->assertSame( 0, $client->calls );
    }


    public function testGloballyDisabledJobIsCancelled() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public int $calls = 0;
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                $this->calls++;
                return 204;
            }
        };
        config( ['cms.webhooks.enabled' => false] );

        $this->job( $webhook )->handle( $client );

        $this->assertSame( 0, $client->calls );
    }


    public function testPermanentFailureIsRecordedWithoutRetry() : void
    {
        $success = Carbon::parse( '2026-09-14 12:00:00 UTC' );
        $webhook = $this->webhook( ['last_success_at' => $success] );
        Log::spy();
        $client = new class extends WebhookClient {
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                return 410;
            }
        };

        $this->job( $webhook )->handle( $client );
        $webhook->refresh();

        $this->assertSame( 1, $webhook->failures );
        $this->assertSame( 'http_error', $webhook->last_error['reason'] );
        $this->assertSame( 410, $webhook->last_error['status'] );
        $this->assertTrue( $success->equalTo( $webhook->last_success_at ) );
        Log::shouldHaveReceived( 'warning' )->once()->with(
            'cms.webhook.delivery_failed', \Mockery::on( fn( array $data ) =>
                $data['webhook_id'] === $webhook->id && $data['status'] === 410
            )
        );
    }


    public function testRetryableFailureThrowsAndFinalFailureIsRecordedAtomically() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                return 503;
            }
        };
        $job = $this->job( $webhook );

        try {
            $job->handle( $client );
            $this->fail( 'Expected retryable webhook exception.' );
        } catch( WebhookException $e ) {
            $job->failed( $e );
        }

        $webhook->refresh();
        $this->assertSame( 1, $webhook->failures );
        $this->assertSame( 503, $webhook->last_error['status'] );
    }


    public function testPolicyFailureIsRecordedWithoutRetry() : void
    {
        $webhook = $this->webhook();
        $client = new class extends WebhookClient {
            public function send( Webhook $webhook, string $event, string $deliveryId, string $body ) : int
            {
                throw new WebhookException( 'destination_not_allowed' );
            }
        };

        $this->job( $webhook )->handle( $client );
        $webhook->refresh();

        $this->assertSame( 1, $webhook->failures );
        $this->assertSame( 'destination_not_allowed', $webhook->last_error['reason'] );
    }


    private function job( Webhook $webhook, ?int $revision = null, ?int $expiresAt = null ) : DeliverWebhook
    {
        return new DeliverWebhook(
            $webhook->id,
            $webhook->tenant_id,
            $revision ?? $webhook->revision,
            'page.published',
            'delivery-id',
            '{"event":"page.published"}',
            $expiresAt ?? now()->addHour()->timestamp,
        );
    }
}
