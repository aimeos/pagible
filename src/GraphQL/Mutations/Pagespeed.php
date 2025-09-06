<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Illuminate\Support\Facades\Cache;
use GraphQL\Error\Error;


final class Pagespeed
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke($rootValue, array $args): ?array
    {
        if( empty( $url = $args['url'] ?? '' ) ) {
            throw new Error( 'URL must be a non-empty string' );
        }

        try {
            return Cache::remember( "pagespeed:$url", 43200, fn() => Analytics::pagespeed( $url ) );
        } catch ( \Exception $e ) {
            return null;
        }
    }
}
