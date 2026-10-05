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
     * Each source is cached for an hour and failing sources add an entry to "errors".
     *
     * @param string $url Full URL of the page
     * @param int $days Number of days to look back
     * @return array<string, mixed> Merged metrics data
     */
    public static function get( string $url, int $days ) : array
    {
        $data = [];

        try {
            $data = (array) Cache::remember( "stats:$url:$days", 3600, fn() => Analytics::driver()->stats( $url, $days ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $data = array_merge( $data, Cache::remember( "search:$url:$days", 3600, fn() => Analytics::search( $url, $days ) ) ?? [] );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $data['queries'] = Cache::remember( "queries:$url:$days", 3600, fn() => Analytics::queries( $url, $days ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        try {
            $data['pagespeed'] = Cache::remember( "pagespeed:$url", 3600, fn() => Analytics::pagespeed( $url ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $e->getMessage();
        }

        return $data;
    }
}
