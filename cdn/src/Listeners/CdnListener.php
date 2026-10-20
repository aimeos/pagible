<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Events\PageInvalidated;
use Illuminate\Support\Facades\Route;


/**
 * Purges the URLs of changed pages and removed public files.
 */
class CdnListener
{
    public function files( FilesRemoved $event ) : void
    {
        try
        {
            // Without clients, building the URLs of the removed files is unnecessary work
            if( Cdn::clients() ) {
                Cdn::purge( array_map( fn( string $path ) => Cdn::file( $path ), $event->paths ) );
            }
        }
        catch( \Throwable $e )
        {
            report( $e );
        }
    }


    public function pages( PageInvalidated $event ) : void
    {
        // Headless setups without the theme package have no page routes to purge
        if( !Route::has( 'cms.page' ) ) {
            return;
        }

        try
        {
            // Without clients, building the URLs of the changed pages is unnecessary work
            if( Cdn::clients() ) {
                Cdn::purge( array_map( fn( string $path ) => Cdn::page( $path, $event->domain ), $event->paths ) );
            }
        }
        catch( \Throwable $e )
        {
            report( $e );
        }
    }
}
