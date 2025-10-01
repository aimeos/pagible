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

        try {
            $data = (array) Cache::remember( "stats:$url:$days", 3600, fn() => Analytics::driver()->stats( $url, $days ) ) ?? [];
        } catch ( \Throwable $e ) {
            $data['errors'][] = $this->error( $e );
        }

        try {
            $data = array_merge( $data, Cache::remember( "search:$url:$days", 3600, fn() => Analytics::search( $url, $days ) ) ?? [] );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $this->error( $e );
        }

        try {
            $data['queries'] = Cache::remember( "queries:$url:$days", 3600, fn() => Analytics::queries( $url, $days ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $this->error( $e );
        }

        try {
            $data['pagespeed'] = Cache::remember( "pagespeed:$url", 3600, fn() => Analytics::pagespeed( $url ) );
        } catch ( \Throwable $e ) {
            $data['errors'][] = $this->error( $e );
        }

        return $data;
    }


    /**
     * Returns the error message for the given exception
     *
     * @param \Throwable $e Thrown exception
     * @return string Error message
     */
    protected function error( \Throwable $e ) : string
    {
        $parts = array_slice( explode( DIRECTORY_SEPARATOR, $e->getFile() ), -5 );
        $line = join( DIRECTORY_SEPARATOR, $parts ) . ':' . $e->getLine();
        $msg = $e->getMessage();

        if( $data = json_decode( $msg, true ) ) {
            $msg = $data;
        }

        if( is_array( $msg ) && isset( $msg['error'] ) ) {
            $msg = $msg['error'];
        }

        if( is_array( $msg ) && isset( $msg['message'] ) ) {
            $msg = $msg['message'];
        }

        return json_encode( $msg ) . ' in ' . $line;
    }
}
