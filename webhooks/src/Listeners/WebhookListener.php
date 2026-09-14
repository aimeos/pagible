<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Jobs\DeliverWebhook;
use Aimeos\Cms\Models\Webhook;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;


/**
 * Fans one committed CMS lifecycle event out into encrypted delivery jobs.
 */
class WebhookListener
{
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
            $webhooks = Webhook::withoutTenancy()
                ->select( 'id', 'tenant_id', 'status', 'revision', 'events' )
                ->where( 'tenant_id', $tenant )
                ->where( 'status', 1 )
                ->get()
                ->filter( function( Webhook $webhook ) use ( $name ) {
                    try {
                        return in_array( $name, (array) $webhook->events, true );
                    } catch( \Throwable ) {
                        return false;
                    }
                } );

            if( $webhooks->isEmpty() ) {
                return;
            }

            $payload = [
                'event' => $name,
                'tenant_id' => $tenant,
                'timestamp' => now()->utc()->toIso8601String(),
                'data' => $this->data( $event ),
            ];

            if( (bool) config( 'cms.webhooks.payload.editor', false ) ) {
                $payload['editor'] = $event->editor;
            }

            $body = json_encode( $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
            $expires = now()->addSeconds(
                max( 60, (int) config( 'cms.webhooks.queue.max_age', 86400 ) )
            )->getTimestamp();

            $jobs = $webhooks->map( fn( Webhook $webhook ) =>
                new DeliverWebhook(
                    (string) $webhook->id,
                    $tenant,
                    (int) $webhook->revision,
                    $name,
                    (string) Str::uuid(),
                    $body,
                    $expires,
                )
            )->all();
            $connection = config( 'cms.webhooks.queue.connection' );

            Queue::connection( is_string( $connection ) && $connection !== '' ? $connection : null )->bulk(
                $jobs,
                queue: (string) config( 'cms.webhooks.queue.name', 'cms-webhooks' ),
            );
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
        if( $event instanceof Bulk ) {
            return array_map( fn( string $id ) => [
                'id' => $id,
                'version_id' => $event->latest[$id] ?? '',
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
        if( !in_array( $event->contentType, ['page', 'element', 'file'], true ) ) {
            return null;
        }

        if( $event instanceof Published ) {
            return $event->published || $event->projection !== []
                ? $event->contentType . '.published'
                : null;
        }

        if( $event instanceof Moved ) {
            return $event->contentType === 'page' ? 'page.moved' : null;
        }

        if( $event instanceof Dropped ) {
            return $event->contentType . '.deleted';
        }

        if( $event instanceof Restored ) {
            return $event->contentType . '.restored';
        }

        if( $event instanceof Purged ) {
            return $event->contentType . '.purged';
        }

        if( $event instanceof Bulk ) {
            return match( $event->action ) {
                'published' => ( $event->data['published'] ?? false ) === true
                    ? $event->contentType . '.published' : null,
                'dropped' => $event->contentType . '.deleted',
                'restored' => $event->contentType . '.restored',
                'purged' => $event->contentType . '.purged',
                default => null,
            };
        }

        return null;
    }
}
