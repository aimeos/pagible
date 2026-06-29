<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Utils;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit/metrics event for frontend searches.
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. Fired only when "cms.theme.watch" is enabled.
 */
final class Searched
{
    use Dispatchable, SerializesModels;

    /**
     * Correlation ID shared by all events of the same request (empty when not available).
     */
    public readonly string $requestId;


    /**
     * @param string $query Search term
     * @param int $results Total number of matching pages
     * @param int $page Pagination page number
     * @param float $durationMs Search duration in milliseconds
     * @param string $domain Requested domain
     * @param string $lang Requested language
     * @param string $tenant Tenant ID the search belongs to
     * @param string|null $requestId Correlation ID; taken from the X-Request-Id header when null
     */
    public function __construct(
        public readonly string $query,
        public readonly int $results,
        public readonly int $page,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $lang = '',
        public readonly string $tenant = '',
        ?string $requestId = null,
    ) {
        $this->requestId = $requestId ?? Utils::requestId();
    }
}
