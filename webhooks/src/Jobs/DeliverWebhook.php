<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookConfig;


/**
 * Delivers one immutable payload after revalidating the subscription revision.
 */
class DeliverWebhook extends BaseDelivery
{
    public function __construct(
        public readonly string $webhookId,
        string $tenant,
        public readonly int $revision,
        string $event,
        string $deliveryId,
        string $body,
        int $expiresAt,
    ) {
        parent::__construct( $tenant, $event, $deliveryId, $body, $expiresAt );
    }


    protected function circuit() : WebhookCircuit
    {
        return WebhookCircuit::webhook( $this->tenant, $this->webhookId, $this->revision );
    }


    protected function destination() : array
    {
        return ['webhook_id' => $this->webhookId, 'tenant_id' => $this->tenant];
    }


    protected function record( string $reason, ?int $status ) : void
    {
        // Delivery health isn't a configuration change, so "updated_at" is left untouched
        $this->query()->toBase()->update( ['last_error' => Webhook::error( $reason, $status )] );
    }


    protected function succeed() : void
    {
        // Healthy subscriptions are updated at most once a minute while deliveries keep succeeding
        $this->query()->toBase()->where( fn( $query ) => $query
            ->whereNotNull( 'last_error' )
            ->orWhereNull( 'last_success_at' )
            ->orWhere( 'last_success_at', '<', now()->subMinute() )
        )->update( [
            'last_error' => null,
            'last_success_at' => now(),
        ] );
    }


    /**
     * Returns the destination of the subscription if it's still active and subscribed to the event.
     */
    protected function target( WebhookConfig $config ) : ?array
    {
        return $this->query()
            ->select( 'url', 'secrets' )
            ->where( 'status', 1 )
            ->first()
            ?->target();
    }


    /**
     * Returns the query for the subscription if the delivery wasn't cancelled.
     *
     * Only deactivating and replacing the destination changes the revision, so other changes don't
     * cancel queued deliveries or reset the pause of the destination. Removing the event cancels
     * them too, so their results don't change the health of the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Builder<Webhook>
     */
    private function query() : \Illuminate\Database\Eloquent\Builder
    {
        return Webhook::withoutTenancy()
            ->where( 'tenant_id', $this->tenant )
            ->where( 'id', $this->webhookId )
            ->where( 'revision', $this->revision )
            ->whereJsonContains( 'events', $this->event );
    }
}
