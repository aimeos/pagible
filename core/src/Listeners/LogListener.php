<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Loggable;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log for loggable events.
 *
 * A listener failure never breaks the originating operation.
 */
class LogListener
{
    public function handle( Loggable $event ) : void
    {
        $entry = $event->log();

        if( ( $entry['level'] ?? 'info' ) === 'warning' ) {
            Watch::warn( $entry['message'], $entry['fields'] );
        } elseif( empty( $entry['sample'] ) || Watch::sampled() ) {
            Watch::emit( $entry['message'], $entry['fields'] );
        }
    }
}
