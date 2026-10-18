<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Listeners\LogListener;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\Formatter\JsonFormatter;


/**
 * Shared watch helpers for structured CMS audit/observability logging.
 */
class Watch
{
    private static function channel() : ?string
    {
        $channel = config( 'cms.watch.channel' );

        return is_string( $channel ) && $channel !== '' ? $channel : null;
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
     * @param string|null $flag Feature flag config key gating watch logging for this event
     */
    public static function dispatch( string $event, \Closure $factory, ?string $flag = null ) : void
    {
        if( !self::enabled( $flag ) && !Event::hasListeners( $event ) ) {
            return;
        }

        try {
            event( $factory() );
        } catch( \Throwable $e ) {
            self::failed( 'event', $e );
        }
    }


    /**
     * Writes a failure of the watch logging to the PHP error log.
     *
     * The PHP error log is used because the failure can originate from the log channel itself.
     *
     * @param string $context Failed part of the watch logging
     * @param \Throwable $e Exception thrown while logging
     */
    private static function failed( string $context, \Throwable $e ) : void
    {
        error_log( sprintf( "CMS watch %s error: %s (%s in %s:%d)\n%s", $context, $e->getMessage(),
            get_class( $e ), $e->getFile(), $e->getLine(), $e->getTraceAsString() ) );
    }


    public static function duration( int|float $start ) : float
    {
        return ( hrtime( true ) - $start ) / 1e6;
    }


    private static function enabled( ?string $flag = null ) : bool
    {
        return self::channel() !== null && ( $flag === null || (bool) config( $flag, false ) );
    }


    /**
     * Subscribes the log listener to several events when watch logging (and the optional feature flag) is enabled.
     *
     * @param array<int, class-string> $events Loggable event classes
     * @param string|null $flag Feature flag config key gating the listeners, in addition to the channel
     */
    public static function listen( array $events, ?string $flag = null ) : void
    {
        if( !self::enabled( $flag ) ) {
            return;
        }

        foreach( $events as $event ) {
            Event::listen( $event, [LogListener::class, 'handle'] );
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
            self::failed( 'listener', $e );
        }
    }


    /**
     * Writes a security-relevant warning to the configured or default log channel.
     *
     * @param array<string, mixed> $fields Structured entry fields
     */
    public static function warn( string $message, array $fields ) : void
    {
        try {
            $fields = self::fields( $fields );

            ( $channel = self::channel() )
                ? Log::channel( $channel )->warning( $message, $fields )
                : Log::warning( $message, $fields );
        } catch( \Throwable $e ) {
            self::failed( 'warning', $e );
        }
    }


    /**
     * Writes the warning at most once per interval, e.g. for problems repeated by each event or job.
     *
     * @param array<string, mixed> $fields Structured entry fields which identify the problem
     * @param int $interval Seconds until an equal warning is written again
     */
    public static function warnOnce( string $message, array $fields, int $interval = 600 ) : void
    {
        try {
            // Values from the configuration may contain invalid UTF-8 which json_encode() can't distinguish
            $new = Cache::add( 'cms-warn:' . hash( 'sha256', $message . serialize( $fields ) ), true, $interval );
        } catch( \Throwable ) {
            $new = true;
        }

        if( $new ) {
            self::warn( $message, $fields );
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
     * Returns the request correlation ID, reusing a valid inbound X-Request-Id as is.
     */
    private static function requestId() : string
    {
        $request = request();

        if( !$request->attributes->has( 'cms-request-id' ) )
        {
            $header = $request->header( 'X-Request-Id' );
            $id = is_string( $header ) && preg_match( '/\A[A-Za-z0-9._-]{1,128}\z/D', $header ) ? $header : (string) Str::uuid();

            $request->attributes->set( 'cms-request-id', $id );
        }

        $id = $request->attributes->get( 'cms-request-id' );
        return is_string( $id ) ? $id : '';
    }


    /**
     * Pseudonymizes a value with a keyed SHA-256 HMAC when anonymization is enabled.
     */
    public static function mask( string $value ) : string
    {
        if( $value === '' ) {
            return '';
        }

        return config( 'cms.watch.anonymize', true ) ? hash_hmac( 'sha256', $value, (string) config( 'app.key' ) ) : $value;
    }


    /**
     * Gets or sets the originating interface for content changes in the current request.
     *
     * Used to tag audit events with their origin: the GraphQL and MCP entry points set 'graphql'
     * resp. 'mcp', everything else (console commands, scheduled jobs) defaults to 'cli'. Stored on
     * the request instance rather than a static, so it neither leaks between requests under Octane
     * nor needs a reset.
     *
     * @param string|null $source Origin to set for this request, or null to only read it
     * @return string The current origin, defaulting to 'cli'
     */
    public static function source( ?string $source = null ) : string
    {
        $request = request();

        if( $source !== null ) {
            $request->attributes->set( 'cms-source', $source );
        }

        $value = $request->attributes->get( 'cms-source', 'cli' );
        return is_string( $value ) ? $value : 'cli';
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
