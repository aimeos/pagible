<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Pulse\Metric;
use Illuminate\Support\Facades\RateLimiter;


abstract class Recorder
{
    private const ERROR_REPORT_DECAY_SECONDS = 60;
    private const ERROR_REPORT_MAX_ATTEMPTS = 1;


    /**
     * Records one Pulse entry without letting Pulse failures break the request.
     *
     * @param array<string, mixed> $key Aggregation dimensions
     * @param list<'count'|'min'|'max'|'sum'|'avg'> $aggregates Pulse aggregate methods
     */
    protected function entry( string $type, array $key, ?int $value = null, array $aggregates = ['count'] ) : void
    {
        try {
            if( !( $pulse = $this->pulse() ) || !method_exists( $pulse, 'record' ) ) {
                return;
            }

            $entry = $pulse->record( Metric::type( $type, (string) ( $key['tenant'] ?? '' ) ),
                $this->key( Metric::key( $key ) ), $value );

            foreach( $aggregates as $aggregate )
            {
                if( is_object( $entry ) && method_exists( $entry, $aggregate ) ) {
                    $entry->{$aggregate}();
                }
            }
        } catch( \Throwable $e ) {
            $this->report( $e );
        }
    }


    /**
     * Records a duration metric with Pulse's latency aggregates.
     *
     * @param array<string, mixed> $key Aggregation dimensions
     */
    protected function latency( string $type, array $key, float|int $duration ) : void
    {
        $this->entry( $type, $key, $this->ms( $duration ), Metric::LATENCY );
    }


    protected function pulse() : ?object
    {
        $app = app();

        if( $app->bound( \Laravel\Pulse\Pulse::class ) ) {
            return $app->make( \Laravel\Pulse\Pulse::class );
        }

        return $app->bound( 'pulse' ) ? $app->make( 'pulse' ) : null;
    }


    /**
     * @param array<string, mixed> $fields
     */
    protected function key( array $fields ) : string
    {
        $fields = array_filter( $fields, fn( $value ) => $value !== null && $value !== '' );

        try {
            return json_encode( $fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
        } catch( \JsonException ) {
            return '{}';
        }
    }


    protected function ms( float|int $duration ) : int
    {
        return max( 0, (int) round( $duration ) );
    }


    protected function prefixed( string $prefix, string $action ) : string
    {
        return str_contains( $action, ':' ) ? $action : $prefix . ':' . $action;
    }


    protected function report( \Throwable $e ) : void
    {
        try {
            RateLimiter::attempt(
                'cms-pulse-recorder:' . hash( 'sha256', get_class( $e ) . '|' . $e->getMessage() ),
                self::ERROR_REPORT_MAX_ATTEMPTS,
                fn() => report( $e ),
                self::ERROR_REPORT_DECAY_SECONDS,
            );
        } catch( \Throwable ) {
            // Reporting must never make an optional recorder fail the request.
        }
    }
}
