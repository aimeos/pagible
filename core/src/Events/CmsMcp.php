<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Metrics event for MCP admin operations.
 */
final class CmsMcp
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly float $durationMs = 0.0,
        public readonly string $tenant = '',
        public readonly string $domain = '',
        public readonly bool $success = true,
    ) {}
}
