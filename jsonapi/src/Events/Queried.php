<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for read-only JSON:API requests.
 */
final class Queried
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly float $durationMs = 0.0,
        public readonly string $domain = '',
        public readonly string $includes = '',
        public readonly string $tenant = '',
    ) {}
}
