<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Change;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Models\Webhook;


/**
 * Builds the JSON bodies of the webhook deliveries.
 */
class WebhookPayload
{
    /** Maximum JSON size of the fields added by the application per change, so bulk events fit into queue jobs */
    public const MAX_EXTRA = 1024;


    public function __construct( private WebhookConfig $config )
    {
    }


    /**
     * Returns the JSON body of a delivery for the CMS lifecycle event.
     */
    public function forEvent( Event|Bulk $event, string $name ) : string
    {
        return $this->body( $name, $event->tenant, $this->data( $event, $name ) );
    }


    /**
     * Returns the JSON body of a test delivery to the subscription.
     */
    public function forPing( string $tenant, Webhook $webhook ) : string
    {
        return $this->body( 'webhook.ping', $tenant, ['id' => $webhook->id] );
    }


    /**
     * Returns the JSON body of a delivery.
     *
     * @param array<string, mixed>|list<array<string, mixed>> $data Event data
     */
    private function body( string $name, string $tenant, array $data ) : string
    {
        return json_encode( [
            'event' => $name,
            'tenant_id' => $tenant,
            'timestamp' => now()->utc()->toRfc3339String( true ),
            'data' => $data,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
    }


    /**
     * Returns stable event references without reloading mutable content models.
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    private function data( Event|Bulk $event, string $name ) : array
    {
        $changes = $event->changes();
        $extra = $this->extra( $changes, $name, $event->tenant );

        $refs = array_map( fn( Change $change ) => array_filter( [
            'id' => $change->id,
            'version_id' => $change->versionId,
            'path' => $change->path,
            'domain' => $change->domain,
            'lang' => $change->lang,
            'extra' => $extra[$change->id] ?? null,
        ], fn( $value ) => $value !== null ), $changes );

        return $event instanceof Bulk ? $refs : $refs[0];
    }


    /**
     * Returns the non-empty fields of the application per change ID without the ones exceeding MAX_EXTRA
     * or which can't be encoded as JSON, so one invalid value doesn't drop the fields of the other changes.
     *
     * @param list<Change> $changes
     * @return array<string, array<string, mixed>>
     */
    private function extra( array $changes, string $name, string $tenant ) : array
    {
        if( !app()->bound( 'cms.webhooks.payload' ) ) {
            return [];
        }

        try
        {
            // Queries of the application can't use the data of another tenant than the one of the changes,
            // switching the tenant fails in stancl/tenancy mode and the event is sent without the fields
            $callback = app( 'cms.webhooks.payload' );
            $result = \Aimeos\Cms\Tenancy::run( $tenant, fn() => $callback( $changes, $name ) );
            $result = is_array( $result ) ? array_filter( $result, fn( $fields ) => is_array( $fields ) && $fields ) : [];
            $count = count( $result );

            $result = array_filter( $result, fn( $fields ) =>
                ( $json = json_encode( $fields, JSON_UNESCAPED_SLASHES ) ) !== false && strlen( $json ) <= self::MAX_EXTRA
            );

            if( count( $result ) < $count ) {
                $this->config->warn( 'cms.webhook.extra_dropped', ['event' => $name, 'max' => self::MAX_EXTRA] );
            }

            return $result;
        }
        catch( \Throwable $e )
        {
            // Errors of the application must not drop the events of the CMS, they are sent without the fields
            // and logged throttled without the message, which may contain values and would change the throttle key
            $this->config->warn( 'cms.webhook.extra_failed', ['event' => $name, 'error' => $e::class, 'location' => $e->getFile() . ':' . $e->getLine()] );
            return [];
        }
    }
}
