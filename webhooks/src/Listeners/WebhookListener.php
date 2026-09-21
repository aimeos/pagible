<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Jobs\DeliverEndpoint;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookManager;
use Illuminate\Queue\SyncQueue;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;


/**
 * Fans one committed CMS lifecycle event out into encrypted delivery jobs for subscriptions and endpoints.
 */
class WebhookListener
{
    public function __construct( private WebhookConfig $config )
    {
    }


    public function handle( Event|Bulk $event ) : void
    {
        if( !(bool) config( 'cms.webhooks.enabled', false )
            || !( $name = $this->name( $event ) )
            || !( $tenant = $event->tenant ) && \Aimeos\Cms\Tenancy::$callback !== null
        ) {
            return;
        }

        try
        {
            // An invalid deny list stops all deliveries instead of allowing what it should block,
            // the admin panel shows why no events are sent
            if( $reason = $this->config->blocked() ) {
                $this->config->warn( 'cms.webhook.delivery_blocked', ['reason' => $reason] );
                return;
            }

            $webhooks = Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->where( 'status', 1 )
                ->whereJsonContains( 'events', $name )
                ->pluck( 'revision', 'id' );

            $endpoints = $this->config->subscribed( $name, $tenant );

            if( $webhooks->isEmpty() && $endpoints === [] ) {
                return;
            }

            $payload = [
                'event' => $name,
                'tenant_id' => $tenant,
                'timestamp' => now()->utc()->toRfc3339String( true ),
                'data' => $this->data( $event ),
            ];

            $body = json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
            $expires = now()->addSeconds(
                max( 60, (int) config( 'cms.webhooks.queue.max_age', 86400 ) )
            )->getTimestamp();

            $jobs = $webhooks->map( fn( mixed $revision, string $id ) =>
                new DeliverWebhook(
                    $id,
                    $tenant,
                    (int) $revision,
                    $name,
                    (string) Str::uuid(),
                    $body,
                    $expires,
                )
            )->values()->all();

            foreach( $endpoints as $endpoint => $revision ) {
                $jobs[] = new DeliverEndpoint(
                    $endpoint,
                    $tenant,
                    $revision,
                    $name,
                    (string) Str::uuid(),
                    $body,
                    $expires,
                );
            }

            // Lost deliveries aren't retried, so editors must see that the subscriptions missed events
            if( !$this->queue( $jobs ) ) {
                $this->lost( $tenant, $webhooks->keys()->all() );
            }
        }
        catch( \Throwable $e ) {
            report( $e );
        }
    }


    /**
     * Logs the lost deliveries and shows the reason for the subscriptions in the admin panel.
     *
     * @param array<int, int|string> $ids Subscription IDs
     */
    private function lost( string $tenant, array $ids ) : void
    {
        $this->config->warn( 'cms.webhook.delivery_blocked', ['reason' => 'queue_failed'] );

        if( $ids !== [] )
        {
            Webhook::withoutTenancy()
                ->where( 'tenant_id', $tenant )
                ->whereIn( 'id', $ids )
                ->toBase()
                ->update( ['last_error' => Webhook::error( 'queue_failed' )] );
        }
    }


    /**
     * Pushes the delivery jobs to the queue.
     *
     * @param array<int, DeliverWebhook|DeliverEndpoint> $jobs
     * @return bool TRUE if the jobs were queued, FALSE if pushing them failed
     */
    private function queue( array $jobs ) : bool
    {
        $name = (string) config( 'cms.webhooks.queue.name', 'cms-webhooks' );

        try {
            $queue = Queue::connection( $this->config->connection() );
        } catch( \Throwable $e ) {
            report( $e );
            return false;
        }

        // Synchronous jobs run immediately and one failing destination must not stop the others
        if( $queue instanceof SyncQueue )
        {
            foreach( $jobs as $job )
            {
                try {
                    $queue->bulk( [$job], queue: $name );
                } catch( \Throwable $e ) {
                    report( $e );
                }
            }

            return true;
        }

        // Before pushing because a queue worker may process the jobs immediately
        $waiting = $this->config->queued();

        try {
            $queue->bulk( $jobs, queue: $name );
        } catch( \Throwable $e ) {
            report( $e );

            // No delivery waits for a queue worker, so the marker would report a stalled worker instead
            if( $waiting ) {
                $this->config->processed();
            }

            return false;
        }

        return true;
    }


    /**
     * Returns stable event references without reloading mutable content models.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function data( Event|Bulk $event ) : array
    {
        if( $event instanceof Bulk ) {
            return array_map( fn( string $id ) => [
                'id' => $id,
                'version_id' => $event->projected[$id] ?? $event->latest[$id] ?? '',
            ], $event->ids );
        }

        $projection = $event instanceof Published ? $event->projection : [];
        $versionId = $projection['version_id'] ?? $event->latest_id;
        $data = ['id' => $event->id, 'version_id' => $versionId];

        if( $event->contentType === 'page' ) {
            foreach( ['path', 'domain'] as $field ) {
                if( is_string( $value = ( $projection ?: $event->data )[$field] ?? null ) ) {
                    $data[$field] = $value;
                }
            }
        }

        return $data;
    }


    private function name( Event|Bulk $event ) : ?string
    {
        $action = $event instanceof Bulk ? $event->action : strtolower( class_basename( $event ) );

        if( $action === 'published' && ( $event instanceof Bulk
            ? ( $event->data['published'] ?? false ) !== true
            : !( $event instanceof Published && ( $event->published || $event->projection !== [] ) )
        ) ) {
            return null;
        }

        $name = $event->contentType . '.' . ( $action === 'dropped' ? 'deleted' : $action );
        return in_array( $name, WebhookManager::EVENTS, true ) ? $name : null;
    }
}
