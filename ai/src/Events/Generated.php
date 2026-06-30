<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for AI provider calls.
 */
final class Generated
{
    use Dispatchable;

    public function __construct(
        public readonly string $mutation,
        public readonly string $provider = '',
        public readonly string $model = '',
        public readonly float $durationMs = 0.0,
        public readonly string $editor = '',
        public readonly string $tenant = '',
        public readonly bool $success = true,
        public readonly ?string $error = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {}
}
