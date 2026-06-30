<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;


/**
 * Shared watch helpers for structured CMS audit/observability logging.
 */
class Watch
{
    /**
     * Returns the configured watch log channel, or null when watch logging is disabled.
     */
    public static function channel() : ?string
    {
        $channel = config( 'cms.watch.channel' );

        return is_string( $channel ) && $channel !== '' ? $channel : null;
    }


    /**
     * Builds and dispatches a watch event only when watch logging and the optional flag are enabled.
     *
     * @param \Closure(): object $factory Deferred event factory
     */
    public static function dispatchWhen( ?string $flag, \Closure $factory ) : void
    {
        if( !self::enabled( $flag ) ) {
            return;
        }

        try {
            event( $factory() );
        } catch( \Throwable $e ) {
            error_log( 'CMS watch event error: ' . $e->getMessage() );
        }
    }


    /**
     * Returns the elapsed milliseconds since a start timestamp.
     */
    public static function duration( int|float|null $start ) : float
    {
        return $start !== null ? ( hrtime( true ) - $start ) / 1e6 : 0.0;
    }


    /**
     * Tells whether watch logging and the optional feature flag are enabled.
     */
    public static function enabled( ?string $flag = null ) : bool
    {
        return self::channel() !== null && ( $flag === null || (bool) config( $flag, false ) );
    }


    /**
     * Subscribes several log listeners when watch logging is enabled.
     *
     * @param array<string, string|array{0: string, 1?: string}> $listeners Event class => listener class or [listener class, method]
     */
    public static function listen( array $listeners ) : void
    {
        if( !self::enabled() ) {
            return;
        }

        foreach( $listeners as $event => $listener )
        {
            if( is_array( $listener ) ) {
                Event::listen( $event, [$listener[0], $listener[1] ?? 'handle'] );
                continue;
            }

            Event::listen( $event, [$listener, 'handle'] );
        }
    }


    /**
     * Writes the entry to the CMS log channel, swallowing any error so it never breaks the request.
     *
     * @param string $message Log message, e.g. "cms.page"
     * @param array<string, mixed> $context Structured entry fields
     */
    public static function emit( string $message, array $context ) : void
    {
        if( !( $channel = self::channel() ) ) {
            return;
        }

        try {
            Log::channel( $channel )->info( $message, self::context( $context ) );
        } catch( \Throwable $e ) {
            error_log( 'CMS watch listener error: ' . $e->getMessage() );
        }
    }


    /**
     * Adds standard watch context fields and removes null/empty-string values.
     *
     * @param array<string, mixed> $context Log context fields
     * @return array<string, mixed>
     */
    public static function context( array $context ) : array
    {
        return array_filter( ['request_id' => Utils::requestId()] + $context, fn( $value ) =>
            $value !== null && $value !== ''
        );
    }


    /**
     * Pseudonymizes a value with a keyed SHA-256 HMAC when anonymization is enabled.
     */
    public static function mask( string $value, ?bool $anon = null ) : string
    {
        if( $value === '' ) {
            return '';
        }

        $anon ??= (bool) config( 'cms.watch.anonymize', true );

        return $anon ? hash_hmac( 'sha256', $value, (string) config( 'app.key' ) ) : $value;
    }


    /**
     * Tells whether the current high-volume entry should be kept.
     */
    public static function sampled() : bool
    {
        $rate = (float) config( 'cms.watch.sample', 1.0 );

        return $rate >= 1.0 || mt_rand() / mt_getrandmax() < $rate;
    }


    /**
     * Returns a start timestamp only when watch logging and the optional flag are enabled.
     */
    public static function start( ?string $flag = null ) : int|float|null
    {
        return self::enabled( $flag ) ? hrtime( true ) : null;
    }


    /**
     * Registers a daily JSON log channel for CMS watch logs when one is enabled but undefined.
     */
    public static function registerChannel() : void
    {
        if( !( $channel = self::channel() ) || config( "logging.channels.{$channel}" ) ) {
            return;
        }

        config( ["logging.channels.{$channel}" => [
            'driver' => 'daily',
            'path' => storage_path( 'logs/cms.log' ),
            'level' => 'info',
            'days' => 14,
            'formatter' => JsonFormatter::class,
            'replace_placeholders' => true,
        ]] );
    }
}
