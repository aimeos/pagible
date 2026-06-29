<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Generated;


/**
 * Writes a structured JSON line to the CMS log channel for every AI provider call.
 *
 * Active only when "cms.watch.channel" is set. Error messages are stripped of API-key-like
 * tokens and truncated to 200 characters. A listener failure never breaks the request.
 */
class AiLogListener
{
    use WritesLog;


    /**
     * Logs the AI provider call as a structured entry.
     */
    public function handle( Generated $event ) : void
    {
        $this->emit( 'cms.ai', $this->context( $event ) );
    }


    /**
     * Builds the structured log context, dropping null/empty values.
     *
     * @return array<string, mixed>
     */
    protected function context( Generated $event ) : array
    {
        return array_filter( [
            'request_id' => $event->requestId,
            'mutation' => $event->mutation,
            'provider' => $event->provider,
            'model' => $event->model,
            'duration_ms' => round( $event->durationMs, 1 ),
            'editor' => $event->editor,
            'tenant_id' => $event->tenant,
            'success' => $event->success,
            'error' => $this->sanitize( $event->error ),
            'input_tokens' => $event->extra['inputTokens'] ?? null,
            'output_tokens' => $event->extra['outputTokens'] ?? null,
        ], fn( $value ) => $value !== null && $value !== '' );
    }


    /**
     * Strips API-key-like tokens from the error message and truncates it.
     */
    protected function sanitize( ?string $error ) : ?string
    {
        if( $error === null ) {
            return null;
        }

        $error = (string) preg_replace( '/\b(sk-|AIza|Bearer\s+)\S+/', '[REDACTED]', $error );

        return mb_substr( $error, 0, 200 );
    }
}
