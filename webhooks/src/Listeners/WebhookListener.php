<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Change;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Jobs\BaseDelivery;
use Aimeos\Cms\Jobs\DeliverEndpoint;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookManager;
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
        if( !( $name = $this->name( $event ) )
            || !( $tenant = $event->tenant ) && \Aimeos\Cms\Tenancy::configured()
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
                ->pluck( 'id' );

            $endpoints = $this->config->subscribed( $name );

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
            $expires = now()->addSeconds( BaseDelivery::MAX_AGE )->getTimestamp();

            $jobs = $webhooks->map( fn( string $id ) =>
                new DeliverWebhook(
                    $id,
                    $tenant,
                    $name,
                    (string) Str::uuid(),
                    $body,
                    $expires,
                )
            )->all();

            foreach( $endpoints as $endpoint ) {
                $jobs[] = new DeliverEndpoint(
                    $endpoint,
                    $tenant,
                    $name,
                    (string) Str::uuid(),
                    $body,
                    $expires,
                );
            }

            try {
                Queue::connection( $this->config->connection() )
                    ->bulk( $jobs, queue: (string) config( 'cms.webhooks.queue.name', 'cms-webhooks' ) );
            }
            catch( \Throwable $e )
            {
                // Lost deliveries aren't retried, so editors must see that the subscriptions missed events
                report( $e );
                $this->config->warn( 'cms.webhook.delivery_blocked', ['reason' => 'queue_failed'] );

                if( $webhooks->isNotEmpty() )
                {
                    Webhook::withoutTenancy()
                        ->where( 'tenant_id', $tenant )
                        ->whereIn( 'id', $webhooks->all() )
                        ->toBase()
                        ->update( ['last_error' => Webhook::error( 'queue_failed' )] );
                }
            }
        }
        catch( \Throwable $e ) {
            report( $e );
        }
    }


    /**
     * Returns stable event references without reloading mutable content models.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function data( Event|Bulk $event ) : array
    {
        $refs = array_map( fn( Change $change ) => array_filter( [
            'id' => $change->id,
            'version_id' => $change->versionId,
            'path' => $change->path,
            'domain' => $change->domain,
            'lang' => $change->lang,
        ], fn( $value ) => $value !== null ), $event->changes() );

        return $event instanceof Bulk ? $refs : $refs[0];
    }


    private function name( Event|Bulk $event ) : ?string
    {
        $action = $event instanceof Bulk ? $event->action : strtolower( class_basename( $event ) );

        // only versions which became public are publications for webhooks, scheduled ones aren't
        if( $action === 'published' && !( $event->changes()[0]->published ?? false ) ) {
            return null;
        }

        $name = $event->contentType . '.' . ( $action === 'dropped' ? 'deleted' : $action );
        return in_array( $name, WebhookManager::EVENTS, true ) ? $name : null;
    }
}
