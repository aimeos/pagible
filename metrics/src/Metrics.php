<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Aimeos\Cms\Models\PageVariant;
use Illuminate\Support\Facades\Cache;


/**
 * Cached page analytics shared by the API interfaces.
 */
class Metrics
{
    /**
     * Returns the analytics, search, query and page speed data of a page.
     *
     * Each source is cached for an hour and failing sources are reported
     * and add an entry to "errors".
     *
     * @param string $url Full URL of the page
     * @param int $days Number of days to look back
     * @return array<string, mixed> Merged metrics data
     * @throws Exception If the URL isn't the URL of a page of the current tenant
     */
    public static function get( string $url, int $days ) : array
    {
        if( !self::owned( $url ) ) {
            throw new Exception( sprintf( 'URL "%s" is not the URL of a page', $url ) );
        }

        $data = [];
        $tenant = Tenancy::value();
        $sources = [
            // source name => [data key or NULL to merge, cache key suffix, source]
            'stats' => [null, $url . "\0" . $days, fn() => Analytics::driver()->stats( $url, $days )],
            'search' => [null, $url . "\0" . $days, fn() => Analytics::search( $url, $days )],
            'queries' => ['queries', $url . "\0" . $days, fn() => Analytics::queries( $url, $days )],
            'pagespeed' => ['pagespeed', $url, fn() => Analytics::pagespeed( $url )],
        ];

        foreach( $sources as $source => [$name, $suffix, $fetch] )
        {
            try {
                // hashed, so long URLs don't exceed the key length of cache stores
                $result = Cache::remember( $source . ':' . $tenant . ':' . sha1( $suffix ), 3600, $fetch );

                if( $name ) {
                    $data[$name] = $result;
                } else {
                    $data = array_merge( $data, (array) $result );
                }
            } catch( \Throwable $e ) {
                // exception messages may contain internal details like service URLs
                report( $e );
                $data['errors'][] = sprintf( 'Fetching the %s data failed', $source );
            }
        }

        return $data;
    }


    /**
     * Tests if the URL is the URL of a page of the current tenant.
     *
     * Relative URLs use the current host. The page route may have a prefix, so all path
     * suffixes are candidates and the URL is compared to the generated page URL.
     *
     * @param string $url Absolute or relative page URL
     * @return bool TRUE if a page of the current tenant has this URL
     */
    protected static function owned( string $url ) : bool
    {
        if( ( $parts = parse_url( $url ) ) === false ) {
            return false;
        }

        $host = strtolower( $parts['host'] ?? request()->getHost() );
        $path = rawurldecode( trim( $parts['path'] ?? '', '/' ) );
        $segments = $path !== '' ? explode( '/', $path ) : [];
        $paths = array_map( fn( $idx ) => implode( '/', array_slice( $segments, $idx ) ), range( 0, count( $segments ) ) );

        return PageVariant::whereIn( 'path', $paths )->get( ['path', 'domain'] )->contains( function( $variant ) use ( $host, $path ) {
            // pages aren't rendered by the CMS without the theme package
            if( !( $page = Utils::pageUrl( (string) $variant->path, (string) $variant->domain ) ) ) {
                return $variant->path === $path && ( !config( 'cms.multidomain' ) || $variant->domain === $host );
            }

            return strtolower( (string) parse_url( $page, PHP_URL_HOST ) ) === $host
                && rawurldecode( trim( (string) parse_url( $page, PHP_URL_PATH ), '/' ) ) === $path;
        } );
    }
}
