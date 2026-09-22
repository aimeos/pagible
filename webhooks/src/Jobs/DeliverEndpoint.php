<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Watch;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookConfig;


/**
 * Delivers one immutable payload to an operator-defined endpoint from the configuration.
 *
 * Endpoints have no stored health state, delivery results are written to the log instead.
 */
class DeliverEndpoint extends BaseDelivery
{
    public function __construct(
        public readonly string $endpoint,
        string $tenant,
        string $event,
        string $deliveryId,
        string $body,
        int $expiresAt,
    ) {
        parent::__construct( $tenant, $event, $deliveryId, $body, $expiresAt );
    }


    protected function circuit() : WebhookCircuit
    {
        return WebhookCircuit::endpoint( $this->endpoint );
    }


    protected function destination() : array
    {
        return ['endpoint' => $this->endpoint, 'tenant_id' => $this->tenant];
    }


    protected function succeed() : void
    {
        Watch::emit( 'cms.webhook.delivered', $this->destination() + [
            'event' => $this->event,
            'delivery_id' => $this->deliveryId,
        ] );
    }


    /**
     * Returns the current destination of the endpoint, so a changed URL or secret applies to queued deliveries too.
     */
    protected function target( WebhookConfig $config ) : ?array
    {
        return $config->endpoint( $this->endpoint, $this->event );
    }
}
