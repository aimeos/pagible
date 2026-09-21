<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Support\Facades\Cache;


/**
 * Pauses the deliveries to one destination after temporary failures, following the backoff schedule.
 *
 * After a pause ended, only one delivery probes the destination while the others wait for its
 * result. A successful probe resumes all deliveries, a failed one pauses the destination for the
 * next delay of the schedule. An unavailable cache never pauses deliveries.
 */
final class WebhookCircuit
{
    /** Seconds the state is kept after a pause, so the next failure continues the schedule */
    private const WINDOW = 86400;

    /** @var array{until: int, failures: int, reason: string, status: int|null}|null */
    private ?array $state = null;
    private bool $loaded = false;
    private string $key;


    private function __construct( string $id )
    {
        $this->key = 'cms-webhooks-circuit:' . hash( 'sha256', $id );
    }


    public static function endpoint( string $name, string $revision ) : self
    {
        // One endpoint is a single destination for all tenants
        return new self( 'endpoint:' . $name . ':' . $revision );
    }


    public static function webhook( string $tenant, string $id, int $revision ) : self
    {
        return new self( 'webhook:' . $tenant . ':' . $id . ':' . $revision );
    }


    /**
     * Reads the states of several destinations with one cache request.
     *
     * @param array<int, self> $circuits
     */
    public static function load( array $circuits ) : void
    {
        $pending = array_filter( $circuits, fn( self $circuit ) => !$circuit->loaded );

        if( $pending === [] ) {
            return;
        }

        try {
            $states = Cache::many( array_map( fn( self $circuit ) => $circuit->key, $pending ) );
        } catch( \Throwable ) {
            $states = []; // an unavailable cache never pauses deliveries
        }

        foreach( $pending as $circuit ) {
            $circuit->set( $states[$circuit->key] ?? null );
        }
    }


    /**
     * Resumes the deliveries to the destination.
     */
    public function close() : void
    {
        // Avoids cache writes for each successful delivery if the destination wasn't paused
        if( $this->loaded && $this->state === null ) {
            return;
        }

        $this->state = null;
        $this->loaded = true;

        try {
            Cache::forget( $this->key );
            Cache::forget( $this->key . ':probe' );
        } catch( \Throwable ) {
            // an unavailable cache never pauses deliveries
        }
    }


    /**
     * Pauses the destination after a temporary failure for the next delay of the backoff schedule.
     *
     * Only a failure after the pause ended advances the schedule, failures of parallel deliveries
     * while the destination is already paused don't, but they can extend the pause by Retry-After.
     *
     * @param non-empty-list<int> $delays Backoff schedule whose last delay repeats
     * @param int $retryAfter Seconds the receiver asked to wait, used if longer than the delay
     * @return int|null Seconds until the destination is tried again or NULL if the cache can't store the state
     */
    public function open( string $reason, ?int $status, array $delays, int $retryAfter = 0 ) : ?int
    {
        try
        {
            // Parallel deliveries may have paused the destination in the meantime
            $this->loaded = false;
            $state = $this->state();
            $now = now()->getTimestamp();

            if( $state !== null && $state['until'] > $now )
            {
                if( $state['until'] >= $now + $retryAfter ) {
                    return $state['until'] - $now;
                }

                // Receivers asking to wait longer extend the pause without advancing the schedule
                $failures = $state['failures'];
                $pause = $retryAfter;
            }
            else
            {
                $failures = ( $state['failures'] ?? 0 ) + 1;
                $pause = max( $delays[min( $failures, count( $delays ) ) - 1], $retryAfter );
            }

            $state = ['until' => $now + $pause, 'failures' => $failures, 'reason' => $reason, 'status' => $status];

            // Stores like "null" don't keep the state, so the destination isn't paused
            if( !Cache::put( $this->key, $state, $pause + self::WINDOW ) ) {
                return null;
            }

            $this->state = $state;
            Cache::forget( $this->key . ':probe' );

            return $pause;
        }
        catch( \Throwable )
        {
            return null;
        }
    }


    /**
     * Returns the pause of the destination.
     *
     * @return array{until: int, reason: string, status: int|null}|null NULL if deliveries aren't paused
     */
    public function paused() : ?array
    {
        $state = $this->state();

        if( $state === null || $state['until'] <= now()->getTimestamp() ) {
            return null;
        }

        return ['until' => $state['until'], 'reason' => $state['reason'], 'status' => $state['status']];
    }


    /**
     * Tests if a delivery may be sent to a destination which isn't paused.
     *
     * After a pause ended, only one delivery is allowed until it succeeded, failed or $seconds passed.
     */
    public function probe( int $seconds ) : bool
    {
        if( $this->state() === null ) {
            return true;
        }

        try {
            return Cache::add( $this->key . ':probe', true, max( 1, $seconds ) );
        } catch( \Throwable ) {
            return true;
        }
    }


    /**
     * Stores the state read from the cache.
     *
     * @return array{until: int, failures: int, reason: string, status: int|null}|null
     */
    private function set( mixed $state ) : ?array
    {
        $this->loaded = true;
        $this->state = null;

        if( is_array( $state ) )
        {
            $this->state = [
                'until' => (int) ( $state['until'] ?? 0 ),
                'failures' => max( 1, (int) ( $state['failures'] ?? 1 ) ),
                'reason' => is_string( $state['reason'] ?? null ) ? $state['reason'] : 'http_error',
                'status' => is_int( $state['status'] ?? null ) ? $state['status'] : null,
            ];
        }

        return $this->state;
    }


    /**
     * @return array{until: int, failures: int, reason: string, status: int|null}|null
     */
    private function state() : ?array
    {
        if( $this->loaded ) {
            return $this->state;
        }

        try {
            $state = Cache::get( $this->key );
        } catch( \Throwable ) {
            $state = null;
        }

        return $this->set( $state );
    }
}
