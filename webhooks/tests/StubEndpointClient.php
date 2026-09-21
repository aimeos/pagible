<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookResponse;


final class StubEndpointClient extends WebhookClient
{
    /** @var list<array{array{url: string, secrets: list<string>, ca: string|null, internal: bool}, string}> */
    public array $calls = [];
    public int $status = 204;


    /**
     * @param array{url: string, secrets: list<string>, ca: string|null, internal: bool} $target
     */
    public function send( array $target, string $deliveryId, string $body, ?int $deadline = null ) : WebhookResponse
    {
        $this->calls[] = [$target, $body];
        return new WebhookResponse( $this->status );
    }
}
