<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


/**
 * Audit event for AI provider calls (text/image/audio generation, translation, refinement).
 *
 * Plain event dispatched through the normal event pipeline so log and metrics listeners run
 * without websocket broadcasting. Carries the operation, provider/model, duration, success state
 * and optional token usage in "extra".
 */
final class Generated
{
    use Dispatchable, SerializesModels;

    /**
     * @param string $mutation Operation key, e.g. 'write', 'imagine' or 'generate-image'
     * @param string $provider AI provider used for the call
     * @param string $model AI model used for the call
     * @param float $durationMs Provider call duration in milliseconds
     * @param string $editor Editor identifier that triggered the call
     * @param string $tenant Tenant ID the call belongs to
     * @param bool $success Whether the provider call succeeded
     * @param string|null $error Error message on failure
     * @param array<string, mixed> $extra Optional token usage (inputTokens/outputTokens)
     */
    public function __construct(
        public readonly string $mutation,
        public readonly string $provider = '',
        public readonly string $model = '',
        public readonly float $durationMs = 0.0,
        public readonly string $editor = '',
        public readonly string $tenant = '',
        public readonly bool $success = true,
        public readonly ?string $error = null,
        public readonly array $extra = [],
    ) {}
}
