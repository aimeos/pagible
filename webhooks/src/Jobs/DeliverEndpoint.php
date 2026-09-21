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
        public readonly string $revision,
        string $event,
        string $deliveryId,
        string $body,
        int $expiresAt,
    ) {
        parent::__construct( $tenant, $event, $deliveryId, $body, $expiresAt );
    }


    protected function circuit() : WebhookCircuit
    {
        return WebhookCircuit::endpoint( $this->endpoint, $this->revision );
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


    protected function target( WebhookConfig $config ) : ?array
    {
        $target = $config->endpoint( $this->endpoint, $this->event, $this->tenant );

        // Only a changed URL cancels the delivery, changed secrets sign it with the new ones
        return $target && hash_equals( $config->revision( $target ), $this->revision ) ? $target : null;
    }
}
