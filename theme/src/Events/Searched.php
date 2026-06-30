<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit/metrics event for frontend searches.
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. Fired only when "cms.watch.channel" and "cms.theme.watch" are enabled.
 */
final class Searched
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $query Search term
     * @param int $results Total number of matching pages
     * @param int $page Pagination page number
     * @param float $durationMs Search duration in milliseconds
     * @param string $domain Requested domain
     * @param string $lang Requested language
     * @param string $tenant Tenant ID the search belongs to
     */
    public function __construct(
        public readonly string $query,
        public readonly int $results,
        public readonly int $page,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $lang = '',
        public readonly string $tenant = '',
    ) {}
}
