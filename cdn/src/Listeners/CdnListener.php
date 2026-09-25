<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Events\PageInvalidated;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;


/**
 * Purges the URLs of changed pages and removed public files and sets the CDN cache headers.
 */
class CdnListener
{
    public function files( FilesRemoved $event ) : void
    {
        try
        {
            Cdn::purge( array_map( fn( string $path ) => Cdn::file( $path ), $event->paths ) );
        }
        catch( \Throwable $e )
        {
            report( $e );
        }
    }


    /**
     * Sets the configured CDN cache lifetime of cacheable pages.
     */
    public function headers( RequestHandled $event ) : void
    {
        $maxage = config( 'cms.cdn.maxage' );
        $stale = max( 0, (int) config( 'cms.cdn.stale', 0 ) );
        $headers = $event->response->headers;

        // Only public pages are cached, protected pages and pages of editors are private
        if( ( !is_numeric( $maxage ) && !$stale ) || $event->request->route()?->getName() !== 'cms.page'
            || !$headers->hasCacheControlDirective( 'public' ) || !$headers->hasCacheControlDirective( 's-maxage' )
        ) {
            return;
        }

        if( is_numeric( $maxage ) )
        {
            // CDNs without s-maxage support would use the expiry date of the page lifetime instead
            $headers->addCacheControlDirective( 's-maxage', (string) max( 0, (int) $maxage ) );
            $headers->remove( 'Expires' );
        }

        if( $stale )
        {
            // Would forbid serving stale content while the CDN refreshes it
            $headers->removeCacheControlDirective( 'must-revalidate' );
            $headers->addCacheControlDirective( 'stale-while-revalidate', (string) $stale );
            $headers->addCacheControlDirective( 'stale-if-error', (string) $stale );
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
            if( !Cdn::clients() ) {
                return;
            }

            [$base, $params] = $this->base( $event->domain );

            $urls = array_map(
                fn( string $path ) => $base . URL::route( 'cms.page', ['path' => $path] + $params, false ),
                $event->paths,
            );

            Cdn::purge( $urls );
        }
        catch( \Throwable $e )
        {
            report( $e );
        }
    }


    /**
     * Returns the scheme and host of the URLs and the route parameters for the domain.
     *
     * @param string $domain Page domain, empty for the host of the configured URL
     * @return array{0: string, 1: array<string, string>} Base URL and route parameters
     */
    private function base( string $domain ) : array
    {
        $base = Cdn::base();

        // Routes are relative to the configured URL, not to the host of the current request
        if( !config( 'cms.multidomain' ) ) {
            return [$base, []];
        }

        $domain = $domain ?: (string) parse_url( $base, PHP_URL_HOST );
        return [( parse_url( $base, PHP_URL_SCHEME ) ?: 'https' ) . '://' . $domain, ['domain' => $domain]];
    }
}
