<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\AnalyticsBridge\Facades\Analytics;
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
     */
    public static function get( string $url, int $days ) : array
    {
        $data = [];
        $sources = [
            // cache key => [data key or NULL to merge, source]
            "stats:{$url}:{$days}" => [null, fn() => Analytics::driver()->stats( $url, $days )],
            "search:{$url}:{$days}" => [null, fn() => Analytics::search( $url, $days )],
            "queries:{$url}:{$days}" => ['queries', fn() => Analytics::queries( $url, $days )],
            "pagespeed:{$url}" => ['pagespeed', fn() => Analytics::pagespeed( $url )],
        ];

        foreach( $sources as $key => [$name, $fetch] )
        {
            try {
                $result = Cache::remember( $key, 3600, $fetch );

                if( $name ) {
                    $data[$name] = $result;
                } else {
                    $data = array_merge( $data, (array) $result );
                }
            } catch( \Throwable $e ) {
                // exception messages may contain internal details like service URLs
                report( $e );
                $data['errors'][] = sprintf( 'Fetching the %s data failed', strstr( $key, ':', true ) );
            }
        }

        return $data;
    }
}
