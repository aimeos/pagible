<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for AI provider calls.
 */
final class Generated implements Loggable
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


    /**
     * Returns the log entry with API-key-like tokens stripped from the truncated error message.
     *
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        $error = $this->error !== null
            ? mb_substr( (string) preg_replace( '/\b(sk-|AIza|Bearer\s+)\S+/', '[REDACTED]', $this->error ), 0, 200 )
            : null;

        return ['message' => 'cms.ai', 'fields' => [
            'mutation' => $this->mutation,
            'provider' => $this->provider,
            'model' => $this->model,
            'duration_ms' => round( $this->durationMs, 1 ),
            'editor' => $this->editor,
            'tenant_id' => $this->tenant,
            'success' => $this->success,
            'error' => $error,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
        ]];
    }
}
