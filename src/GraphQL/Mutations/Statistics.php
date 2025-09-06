<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Illuminate\Support\Facades\Cache;
use GraphQL\Error\Error;


final class Statistics
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): array
    {
        $url = $args['url'] ?? '';
        $days = $args['days'] ?? 30;

        if( empty( $url ) ) {
            throw new Error( 'URL must be a non-empty string' );
        }

        if( !is_int( $days ) || $days < 1 || $days > 365 ) {
            throw new Error( 'Number of days must be an integer between 1 and 365' );
        }

        return Cache::remember( "statistics:$url:$days", 1800, fn() => Analytics::all( $url, $days ) );
    }
}
