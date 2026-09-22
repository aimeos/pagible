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


    public static function endpoint( string $name ) : self
    {
        // One endpoint is a single destination for all tenants
        return new self( 'endpoint:' . $name );
    }


    public static function webhook( string $tenant, string $id ) : self
    {
        return new self( 'webhook:' . $tenant . ':' . $id );
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
     * while the destination is already paused wait for the same pause.
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

            if( $state !== null && $state['until'] > $now ) {
                return $state['until'] - $now;
            }

            $failures = ( $state['failures'] ?? 0 ) + 1;
            $pause = max( $delays[min( $failures, count( $delays ) ) - 1], $retryAfter );

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
            return Cache::add( $this->key . ':probe', true, $seconds );
        } catch( \Throwable ) {
            return true;
        }
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
            /** @var array{until: int, failures: int, reason: string, status: int|null}|null $state Only written by open() */
            $state = Cache::get( $this->key );
        } catch( \Throwable ) {
            $state = null; // an unavailable cache never pauses deliveries
        }

        $this->loaded = true;
        return $this->state = $state;
    }
}
