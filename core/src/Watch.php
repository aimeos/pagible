<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Observed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;


/**
 * Shared watch helpers for structured CMS audit/observability logging.
 */
class Watch
{
    public static function channel() : ?string
    {
        $channel = config( 'cms.watch.channel' );

        return is_string( $channel ) && $channel !== '' ? $channel : null;
    }


    /**
     * Builds and dispatches a watch event when an in-process listener is registered for it.
     *
     * @param class-string $event Event class used to check for registered listeners
     * @param \Closure(): object $factory Deferred event factory
     */
    public static function dispatch( string $event, \Closure $factory ) : void
    {
        self::dispatchIf( null, $event, $factory );
    }


    /**
     * Builds and dispatches a watch event when the feature flag is enabled or a listener is registered.
     *
     * @param string $flag Feature flag config key gating watch logging for this event
     * @param class-string $event Event class used to check for registered listeners
     * @param \Closure(): object $factory Deferred event factory
     */
    public static function dispatchWhen( string $flag, string $event, \Closure $factory ) : void
    {
        self::dispatchIf( $flag, $event, $factory );
    }


    /**
     * Builds and dispatches a watch event when watch logging is enabled or something listens for it.
     *
     * Any in-process listener (e.g. an optional observability integration subscribing to the event)
     * makes the event fire even when watch logging is off, so the factory is only invoked when
     * something will consume the event.
     *
     * @param class-string $event Event class used to check for registered listeners
     * @param \Closure(): object $factory Deferred event factory
     */
    private static function dispatchIf( ?string $flag, string $event, \Closure $factory ) : void
    {
        if( !self::enabled( $flag ) && !Event::hasListeners( $event ) ) {
            return;
        }

        try {
            event( $factory() );
        } catch( \Throwable $e ) {
            error_log( 'CMS watch event error: ' . $e->getMessage() );
        }
    }


    public static function duration( int|float|null $start ) : float
    {
        return $start !== null ? ( hrtime( true ) - $start ) / 1e6 : 0.0;
    }


    private static function enabled( ?string $flag = null ) : bool
    {
        return self::channel() !== null && ( $flag === null || (bool) config( $flag, false ) );
    }


    /**
     * Subscribes several log listeners when watch logging (and the optional feature flag) is enabled.
     *
     * @param array<class-string, class-string> $listeners Event class => listener class
     * @param string|null $flag Feature flag config key gating the listeners, in addition to the channel
     */
    public static function listen( array $listeners, ?string $flag = null ) : void
    {
        if( !self::enabled( $flag ) ) {
            return;
        }

        foreach( $listeners as $event => $listener )
        {
            Event::listen( $event, [$listener, 'handle'] );
        }
    }


    /**
     * Writes the entry to the CMS log channel, swallowing any error so it never breaks the request.
     *
     * @param string $message Log message, e.g. "cms.page"
     * @param array<string, mixed> $fields Structured entry fields
     */
    public static function emit( string $message, array $fields ) : void
    {
        if( !( $channel = self::channel() ) ) {
            return;
        }

        try {
            Log::channel( $channel )->info( $message, self::fields( $fields ) );
        } catch( \Throwable $e ) {
            error_log( 'CMS watch listener error: ' . $e->getMessage() );
        }
    }


    /**
     * Adds standard watch fields and removes null/empty-string values.
     *
     * @param array<string, mixed> $fields Log fields
     * @return array<string, mixed>
     */
    public static function fields( array $fields ) : array
    {
        return array_filter( ['request_id' => self::requestId()] + $fields, fn( $value ) =>
            $value !== null && $value !== ''
        );
    }


    /**
     * Returns the request correlation ID, reusing a sanitized inbound X-Request-Id when present.
     */
    private static function requestId() : string
    {
        $request = request();

        if( !$request->attributes->has( 'cms-request-id' ) )
        {
            $header = $request->header( 'X-Request-Id' );
            $id = is_string( $header ) ? (string) preg_replace( '/[^A-Za-z0-9._-]/', '', $header ) : '';

            $request->attributes->set( 'cms-request-id', $id !== '' ? substr( $id, 0, 128 ) : (string) Str::uuid() );
        }

        $id = $request->attributes->get( 'cms-request-id' );
        return is_string( $id ) ? $id : '';
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
     * Dispatches an aggregation-safe CMS observation to optional consumers.
     *
     * @param array<string, bool|float|int|string|null> $dimensions Metric dimensions
     */
    public static function observe( string $source, string $action, float $durationMs = 0.0,
        ?string $tenant = null, array $dimensions = [], bool $sample = false ) : void
    {
        self::dispatch( Observed::class, fn() => new Observed(
            source: $source,
            action: $action,
            durationMs: $durationMs,
            tenant: $tenant ?? Tenancy::value(),
            dimensions: $dimensions,
            sample: $sample,
        ) );
    }


    public static function sampled() : bool
    {
        $rate = (float) config( 'cms.watch.sample', 1.0 );

        return $rate >= 1.0 || mt_rand() / mt_getrandmax() < $rate;
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
