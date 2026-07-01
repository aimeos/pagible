<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit/metrics event for frontend page requests.
 */
final class Viewed
{
    use Dispatchable;

    public function __construct(
        public readonly string $path,
        public readonly string $domain = '',
        public readonly int $status = 200,
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
    ) {}
}
