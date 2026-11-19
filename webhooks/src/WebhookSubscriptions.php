<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Webhook;


/**
 * Caches the active subscriptions per tenant for a short time, so bulk operations don't query them for each event.
 *
 * The instance is scoped to the request or queue job, changes of other processes are seen after the TTL.
 */
class WebhookSubscriptions
{
    /** Seconds the subscriptions are reused, so long running processes see changes made by other processes */
    private const TTL = 10;


    /** @var array<string, array{expires: int, events: array<string, list<string>>}> Expiry and events of the active subscriptions by tenant and webhook ID */
    private array $webhooks = [];


    /**
     * Discards the cached subscriptions after they have been changed in this process.
     */
    public function forget() : void
    {
        $this->webhooks = [];
    }


    /**
     * Returns the IDs of the active subscriptions of the tenant which subscribed to the event.
     *
     * @return list<string> Webhook IDs
     */
    public function subscribed( string $tenant, string $name ) : array
    {
        $webhooks = array_filter( $this->get( $tenant ), fn( array $events ) => in_array( $name, $events, true ) );
        return array_map( strval( ... ), array_keys( $webhooks ) );
    }


    /**
     * Returns the events of the active subscriptions of the tenant by webhook ID.
     *
     * @return array<string, list<string>> Subscribed events by webhook ID
     */
    private function get( string $tenant ) : array
    {
        $now = now()->getTimestamp();
        $cached = $this->webhooks[$tenant] ?? null;

        if( $cached && $cached['expires'] > $now ) {
            return $cached['events'];
        }

        $webhooks = Webhook::withoutTenancy()
            ->where( 'tenant_id', $tenant )
            ->active()
            ->get( ['id', 'events'] )
            ->mapWithKeys( fn( Webhook $webhook ) => [(string) $webhook->id => array_values( (array) $webhook->events )] )
            ->all();

        $this->webhooks[$tenant] = ['expires' => $now + self::TTL, 'events' => $webhooks];
        return $webhooks;
    }
}
