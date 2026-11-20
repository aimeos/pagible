<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\AiError;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
use Aimeos\Prisma\Values\Observation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Facades\Auth;


/**
 * Audit event for AI provider calls.
 */
final class Generated implements Loggable
{
    use Dispatchable;


    /**
     * Maximum error message length.
     */
    private const ERROR = 200;

    /**
     * TRUE if there's no error.
     */
    public readonly bool $success;


    /**
     * @param string|null $error Redacted error message, see AiError::redact()
     */
    public function __construct(
        public readonly string $operation,
        public readonly string $provider = '',
        public readonly string $model = '',
        public readonly float $durationMs = 0.0,
        public readonly string $editor = '',
        public readonly string $tenant = '',
        public readonly ?string $error = null,
        public readonly ?int $inputTokens = null,
        public readonly ?int $outputTokens = null,
    ) {
        $this->success = $error === null;
    }


    /**
     * Returns the log entry with the truncated error message.
     *
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        $error = $this->error !== null ? mb_substr( $this->error, 0, self::ERROR ) : null;

        return ['message' => 'cms.ai', 'fields' => [
            // field name kept for existing log queries
            'mutation' => $this->operation,
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


    /**
     * Returns the observer recording AI calls, capturing editor and tenant before the call.
     *
     * @param string|null $operation Operation name override
     * @return \Closure(Observation): void Observer for Prisma::...->observe()
     */
    public static function observer( ?string $operation = null ) : \Closure
    {
        $editor = Utils::editor( Auth::user() );
        $tenant = Tenancy::value();

        return function( Observation $observation ) use ( $editor, $tenant, $operation ) {
            Watch::dispatch( self::class, fn() => new self(
                operation: $operation ?? $observation->operation,
                provider: $observation->provider,
                model: $observation->model ?? '',
                durationMs: $observation->durationMs,
                editor: $editor,
                tenant: $tenant,
                error: $observation->error ? AiError::redact( $observation->error->getMessage() ) : null,
                inputTokens: $observation->usage?->promptTokens(),
                outputTokens: $observation->usage?->completionTokens(),
            ) );
        };
    }
}
