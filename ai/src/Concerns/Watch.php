<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Generated;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Support\Facades\Auth;


/**
 * Dispatches the AI audit event (Generated) around a provider call.
 *
 * Shared by the AI mutations, MCP tools and the chat controller so the operation, provider/model,
 * duration and success state are recorded consistently for every AI provider call.
 */
trait Watch
{
    /**
     * Dispatches an AI audit event for a single provider call.
     *
     * @param string $op Operation key, e.g. 'write' or 'generate-image'
     * @param string|null $provider Configured AI provider
     * @param string|null $model Configured AI model
     * @param float $start Start time captured from hrtime( true )
     * @param bool $success Whether the provider call succeeded
     * @param string|null $error Error message on failure
     * @param array<string, mixed> $extra Optional token usage (inputTokens/outputTokens)
     * @param string|null $editor Editor identifier; resolved from the authenticated user when null
     */
    protected function generated( string $op, ?string $provider, ?string $model, float $start,
        bool $success = true, ?string $error = null, array $extra = [], ?string $editor = null ) : void
    {
        event( new Generated(
            mutation: $op,
            provider: $provider ?? '',
            model: $model ?? '',
            durationMs: ( hrtime( true ) - $start ) / 1e6,
            editor: $editor ?? Utils::editor( Auth::user() ),
            tenant: Tenancy::value(),
            success: $success,
            error: $error,
            extra: $extra,
        ) );
    }
}
