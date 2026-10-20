<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Illuminate\Foundation\Http\Events\RequestHandled;


/**
 * Sets the CDN cache headers of cacheable pages.
 */
class CdnHeaders
{
    /**
     * Sets the configured CDN cache lifetime of cacheable pages.
     */
    public function handle( RequestHandled $event ) : void
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
}
