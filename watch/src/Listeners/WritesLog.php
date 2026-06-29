<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Illuminate\Support\Facades\Log;


/**
 * Shared writer for the watch log listeners.
 *
 * Centralizes the channel gating and error isolation so a listener failure never breaks the
 * originating request. Listeners build their structured context and hand it to emit().
 */
trait WritesLog
{
    /**
     * Writes the entry to the CMS log channel, swallowing any error so it never breaks the request.
     *
     * @param string $message Log message, e.g. "cms.page"
     * @param array<string, mixed> $context Structured entry fields (already filtered)
     */
    protected function emit( string $message, array $context ) : void
    {
        if( !( $channel = config( 'cms.watch.channel' ) ) ) {
            return;
        }

        try {
            Log::channel( $channel )->info( $message, $context );
        } catch( \Throwable $e ) {
            error_log( 'CMS watch listener error: ' . $e->getMessage() );
        }
    }
}
