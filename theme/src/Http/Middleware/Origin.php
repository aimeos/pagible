<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


/**
 * Prevents noncanonical request origins from producing shared-cacheable responses.
 */
final class Origin
{
    private const ATTRIBUTE = 'cms.origin';


    public function handle( Request $request, Closure $next ) : mixed
    {
        $canonical = self::matches( $request );
        $response = $next( $request );

        if( !$canonical && $response instanceof Response ) {
            $response->headers->set( 'Cache-Control', 'private, no-store' );
            $response->headers->remove( 'Expires' );
        }

        return $response;
    }


    /**
     * Tests whether the request matches the canonical cache origin.
     *
     * Multi-domain routes may use another hostname, but their scheme and port must
     * still match APP_URL so HTTP or alternate-port requests cannot share HTTPS data.
     */
    public static function matches( Request $request ) : bool
    {
        if( $request->attributes->has( self::ATTRIBUTE ) ) {
            return $request->attributes->get( self::ATTRIBUTE ) === true;
        }

        $url = parse_url( (string) config( 'app.url' ) );
        $scheme = is_array( $url ) ? strtolower( (string) ( $url['scheme'] ?? '' ) ) : '';
        $host = is_array( $url ) ? strtolower( rtrim( (string) ( $url['host'] ?? '' ), '.' ) ) : '';
        $port = is_array( $url ) ? (int) ( $url['port'] ?? ( $scheme === 'https' ? 443 : 80 ) ) : 0;
        $requestHost = strtolower( rtrim( $request->getHost(), '.' ) );

        $canonical = in_array( $scheme, ['http', 'https'], true )
            && $host !== ''
            && $scheme === strtolower( $request->getScheme() )
            && $port === $request->getPort()
            && ( config( 'cms.multidomain' ) || strcasecmp( $host, $requestHost ) === 0 );

        $request->attributes->set( self::ATTRIBUTE, $canonical );

        return $canonical;
    }
}
