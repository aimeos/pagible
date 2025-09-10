<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Illuminate\Support\Facades\Cache;
use GraphQL\Error\Error;


final class Metrics
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): array
    {
        $url = $args['url'] ?? '';
        $days = $args['days'] ?? 30;
        $lang = $args['lang'] ?? 'en';

        if( empty( $url ) ) {
            throw new Error( 'URL must be a non-empty string' );
        }

        if( !is_int( $days ) || $days < 1 || $days > 90 ) {
            throw new Error( 'Number of days must be an integer between 1 and 90' );
        }

        $data = [];

        try {
            $data = Cache::remember( "stats:$url:$days", 3600, fn() => Analytics::driver()->stats( $url, $days ) );
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
