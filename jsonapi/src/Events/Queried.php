<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Utils;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit event for read-only JSON:API requests.
 *
 * Plain event dispatched from the watch middleware after the response is built, so log and
 * metrics listeners run without websocket broadcasting. Carries the wall-clock duration and the
 * shape of the request (single read vs. collection search, includes).
 */
final class Queried
{
    use Dispatchable, SerializesModels;

    /**
     * Correlation ID shared by all events of the same request (empty when not available).
     */
    public readonly string $requestId;


    /**
     * @param string $action Action: 'jsonapi:read' (single resource) or 'jsonapi:search' (collection)
     * @param float $durationMs Wall-clock request duration in milliseconds
     * @param string $domain Requested domain, if any
     * @param string $includes Comma-separated list of requested includes
     * @param string $tenant Tenant ID the request belongs to
     * @param string|null $requestId Correlation ID; taken from the X-Request-Id header when null
     */
    public function __construct(
        public readonly string $action,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $includes = '',
        public readonly string $tenant = '',
        ?string $requestId = null,
    ) {
        $this->requestId = $requestId ?? Utils::requestId();
    }
}
