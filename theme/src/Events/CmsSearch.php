<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit/metrics event for frontend searches.
 */
final class CmsSearch
{
    use Dispatchable;

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
