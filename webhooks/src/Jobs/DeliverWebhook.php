<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;


/**
 * Delivers one immutable payload after revalidating the subscription revision.
 */
class DeliverWebhook implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout;
    public int $tries;


    public function __construct(
        public readonly string $webhookId,
        public readonly string $tenant,
        public readonly int $revision,
        public readonly string $event,
        public readonly string $deliveryId,
        public readonly string $body,
        public readonly int $expiresAt,
    ) {
        $this->timeout = max( 1, (int) config( 'cms.webhooks.queue.timeout', 25 ) );
        $this->tries = max( 1, (int) config( 'cms.webhooks.queue.tries', 4 ) );

        if( $connection = config( 'cms.webhooks.queue.connection' ) ) {
            $this->onConnection( (string) $connection );
        }

        $this->onQueue( (string) config( 'cms.webhooks.queue.name', 'cms-webhooks' ) );
    }


    /**
     * @return list<int>
     */
    public function backoff() : array
    {
        return array_values( array_map(
            fn( mixed $seconds ) => max( 1, (int) $seconds ),
            (array) config( 'cms.webhooks.queue.backoff', [30, 120, 600] ),
        ) );
    }


    public function failed( ?\Throwable $exception ) : void
    {
        $reason = $exception instanceof WebhookException ? $exception->reason : 'delivery_failed';
        $status = $exception instanceof WebhookException ? $exception->status : null;

        $this->record( $reason, $status );
    }


    public function handle( WebhookClient $client ) : void
    {
        if( !(bool) config( 'cms.webhooks.enabled', false )
            || now()->timestamp > $this->expiresAt
        ) {
            return;
        }

        $webhook = $this->webhook();

        if( !$webhook || !in_array( $this->event, (array) $webhook->events, true ) ) {
            return;
        }

        try {
            $status = $client->send( $webhook, $this->event, $this->deliveryId, $this->body );
        } catch( WebhookException $e ) {
            if( in_array( $e->reason, ['resolution_failed', 'transport_error', 'transport_unavailable'], true ) ) {
                throw $e;
            }

            $this->record( $e->reason, $e->status );
            return;
        }

        if( $status >= 200 && $status < 300 ) {
            return;
        }

        if( in_array( $status, [408, 425, 429], true ) || $status >= 500 ) {
            throw new WebhookException( 'http_error', $status );
        }

        $this->record( 'http_error', $status );
    }


    private function record( string $reason, ?int $status ) : void
    {
        if( now()->timestamp > $this->expiresAt ) {
            return;
        }

        $model = new Webhook();
        $model->setAttribute( 'last_error', array_filter( [
            'reason' => $reason,
            'status' => $status,
            'at' => now()->utc()->toIso8601String(),
        ], fn( mixed $value ) => $value !== null ) );
        $encrypted = $model->getAttributes()['last_error'] ?? null;

        $updated = Webhook::withoutTenancy()
            ->where( 'tenant_id', $this->tenant )
            ->where( 'id', $this->webhookId )
            ->where( 'revision', $this->revision )
            ->increment( 'failures', 1, [
                'last_error' => $encrypted,
                'updated_at' => now(),
            ] );

        if( $updated ) {
            Log::warning( 'cms.webhook.delivery_failed', array_filter( [
                'webhook_id' => $this->webhookId,
                'tenant_id' => $this->tenant,
                'event' => $this->event,
                'delivery_id' => $this->deliveryId,
                'reason' => $reason,
                'status' => $status,
            ], fn( mixed $value ) => $value !== null ) );
        }
    }


    private function webhook() : ?Webhook
    {
        return Webhook::withoutTenancy()
            ->where( 'tenant_id', $this->tenant )
            ->where( 'id', $this->webhookId )
            ->where( 'revision', $this->revision )
            ->where( 'status', 1 )
            ->first();
    }
}
