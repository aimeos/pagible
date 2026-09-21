<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Watch;
use Aimeos\Cms\WebhookCircuit;
use Aimeos\Cms\WebhookClient;
use Aimeos\Cms\WebhookConfig;
use Aimeos\Cms\WebhookException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Jobs\SyncJob;


/**
 * Delivers one immutable payload and classifies the outcome into retries and terminal results.
 *
 * Temporary failures pause all deliveries to the same destination following the backoff schedule
 * and they are retried after the pause until the delivery expires, see WebhookCircuit.
 */
abstract class BaseDelivery implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue;

    /** Seconds before the delivery expires which are reserved for the last retry */
    private const MARGIN = 60;

    /** Seconds of the job timeout which are reserved for recording the result of the request */
    public const RECORD_TIME = 5;

    /** @var list<string> */
    private const TEMPORARY = [
        'connection_failed', 'resolution_failed', 'timeout', 'tls_error', 'transport_error', 'transport_unavailable',
    ];

    public int $timeout;


    public function __construct(
        public readonly string $tenant,
        public readonly string $event,
        public readonly string $deliveryId,
        public readonly string $body,
        public readonly int $expiresAt,
    ) {
        // The request must end before the queue worker aborts the job
        $this->timeout = WebhookClient::duration() + self::RECORD_TIME;
    }


    /**
     * Returns the seconds a destination is paused after consecutive temporary failures, the last value repeats.
     *
     * The queue worker also uses them to retry deliveries which failed with an exception.
     *
     * @return non-empty-list<int>
     */
    public function backoff() : array
    {
        $delays = array_values( array_map(
            fn( mixed $seconds ) => max( 1, (int) $seconds ),
            (array) config( 'cms.webhooks.queue.backoff', [30, 120, 600, 1800] ),
        ) );

        // An empty schedule would make the queue worker retry failed jobs without waiting
        return $delays ?: [60];
    }


    public function failed( ?\Throwable $exception ) : void
    {
        app( WebhookConfig::class )->processed();

        if( now()->timestamp > $this->expiresAt ) {
            app( WebhookConfig::class )->warn( 'cms.webhook.delivery_expired', $this->destination() );
            return;
        }

        // Delivery failures are handled before and queue workers retry other exceptions until the
        // delivery expired, so only synchronous deliveries end up here, e.g. after a database error
        $this->abandon( 'delivery_failed', null );
    }


    public function handle( WebhookClient $client, WebhookConfig $config ) : void
    {
        // The queue worker aborts jobs which run longer without recording the failure
        $deadline = now()->getTimestampMs() + ( $this->timeout - self::RECORD_TIME ) * 1000;

        $config->processed();

        if( !(bool) config( 'cms.webhooks.enabled', false ) ) {
            return;
        }

        // Lost deliveries must be noticed, e.g. if the workers can't keep up with the queue
        if( now()->timestamp > $this->expiresAt ) {
            $config->warn( 'cms.webhook.delivery_expired', $this->destination() );
            return;
        }

        try {
            $target = $this->target( $config );
        } catch( WebhookException $e ) {
            $this->abandon( $e->reason, null );
            return;
        }

        // Cancelled deliveries are dropped before waiting for a paused destination, otherwise they
        // would wait for the pause and then probe the destination without closing or reopening it
        if( $target === null ) {
            return;
        }

        $circuit = $this->circuit();

        if( $pause = $circuit->paused() )
        {
            // The pause may have ended since paused() read the time, which is no reason to drop the delivery
            if( !$this->defer( $this->jitter( max( 1, $pause['until'] - now()->getTimestamp() ) ) ) ) {
                $this->abandon( $pause['reason'], $pause['status'] );
            }

            return;
        }

        // Waits for the result of the delivery probing the destination after a pause, if there's enough time
        if( !$circuit->probe( $this->timeout ) && $this->defer( $this->jitter( $this->timeout ) ) ) {
            return;
        }

        try {
            $response = $client->send( $target, $this->deliveryId, $this->body, $deadline );
        } catch( WebhookException $e ) {
            if( in_array( $e->reason, self::TEMPORARY, true ) ) {
                $this->retry( $circuit, $e->reason, null, 0, $config );
                return;
            }

            // The delivery isn't retried, so the destination stays unpaused and other deliveries can probe it
            $circuit->close();

            $this->abandon( $e->reason, null );
            return;
        }

        if( $response->retryable() ) {
            $this->retry( $circuit, 'http_error', $response->status, $response->retryAfter, $config );
            return;
        }

        // Any other response proves that the destination is available again
        $circuit->close();

        if( $response->successful() ) {
            $this->succeed();
        } else {
            $this->abandon( 'http_error', $response->status );
        }
    }


    /**
     * Keeps the job alive after its delivery expired so it's handled and logged as expired.
     */
    public function retryUntil() : int
    {
        return $this->expiresAt + 3600;
    }


    /**
     * Returns the circuit which pauses all deliveries to the same destination.
     */
    abstract protected function circuit() : WebhookCircuit;


    /**
     * Returns the log fields which identify the destination.
     *
     * @return array<string, string>
     */
    abstract protected function destination() : array;


    /**
     * Records a successful delivery.
     */
    abstract protected function succeed() : void;


    /**
     * Returns the destination or NULL if the delivery was cancelled by removing or changing it.
     *
     * @return array{url: string, secrets: list<string>, ca: string|null, internal: bool}|null
     * @throws WebhookException If the destination became invalid after the delivery was queued
     */
    abstract protected function target( WebhookConfig $config ) : ?array;


    /**
     * Stores the last delivery error, also of failures which are still retried.
     */
    protected function record( string $reason, ?int $status ) : void
    {
        // Nothing is stored by default, terminal failures are logged
    }


    /**
     * Records and logs a delivery failure which isn't retried.
     */
    private function abandon( string $reason, ?int $status ) : void
    {
        // Before storing the error because failed() is also used for database errors
        Watch::warn( 'cms.webhook.delivery_failed', $this->destination() + [
            'event' => $this->event,
            'delivery_id' => $this->deliveryId,
            'reason' => $reason,
            'status' => $status,
        ] );

        $this->record( $reason, $status );
    }


    /**
     * Releases the job back to the queue if it can be retried before the delivery expires.
     *
     * Delays beyond the expiry are shortened so the job comes back shortly before instead of never.
     */
    private function defer( int $delay ) : bool
    {
        $delay = min( $delay, $this->expiresAt - self::MARGIN - now()->getTimestamp() );

        if( $delay < 1 || !$this->job || $this->job instanceof SyncJob ) {
            return false;
        }

        $this->release( $delay );
        return true;
    }


    /**
     * Adds up to 20% to the delay so deferred deliveries don't return all at once.
     */
    private function jitter( int $delay ) : int
    {
        return $delay + random_int( 0, max( 1, intdiv( $delay, 5 ) ) );
    }


    /**
     * Retries a temporary failure after pausing the destination or records it as terminal if that's not possible.
     *
     * @param int $retryAfter Seconds the receiver asked to wait, limited to the longest backoff delay
     */
    private function retry( WebhookCircuit $circuit, string $reason, ?int $status, int $retryAfter, WebhookConfig $config ) : void
    {
        $delays = $this->backoff();
        $retryAfter = min( $retryAfter, max( $delays ) );

        // Jobs which aren't processed by a queue worker have no attempts
        $attempt = min( max( $this->attempts(), 1 ), count( $delays ) );

        // Without a cache, the destination isn't paused and the delivery follows the schedule on its own
        $delay = $circuit->open( $reason, $status, $delays, $retryAfter ) ?? max( $retryAfter, $delays[$attempt - 1] );

        if( !$this->defer( $delay ) ) {
            $this->abandon( $reason, $status );
            return;
        }

        $this->record( $reason, $status );
        $config->warn( 'cms.webhook.delivery_retried', $this->destination() + [
            'reason' => $reason,
            'status' => $status,
        ] );
    }
}
