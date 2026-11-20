<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Chat;

use Aimeos\Cms\AiClient;
use Aimeos\Cms\Exceptions\ConflictException;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;


/**
 * Stream slot of a chat user, also released if a middleware discards the response unsent.
 */
final class Slot
{
    /**
     * Maximum number of slots per key, e.g. chat streams running at the same time per user.
     */
    private const MAX = 3;

    /**
     * Minimum lifetime of a slot lock in seconds.
     */
    private const MIN_TTL = 300;

    /**
     * Seconds the slot lock outlives the agent steps.
     */
    private const MARGIN = 60;


    /**
     * @param Lock|null $lock Acquired lock or NULL if the cache doesn't support locks
     */
    private function __construct( private ?Lock $lock )
    {
    }


    /**
     * Releases the slot if the response is destroyed before it was released.
     */
    public function __destruct()
    {
        $this->release();
    }


    /**
     * Acquires one of the slots of the user in the current tenant.
     *
     * @param Authenticatable $user User streaming a chat answer
     * @param int $calls Maximum number of provider requests of the answer, i.e. the agent steps
     * @return self Acquired slot, without lock if the cache doesn't support locks
     * @throws ConflictException If all slots of the user are in use
     */
    public static function acquire( Authenticatable $user, int $calls ) : self
    {
        $key = Tenancy::key( 'cms-chat', (string) $user->getAuthIdentifier() );
        // the TTL only frees slots of killed workers, so it outlasts all agent steps
        $ttl = max( self::MIN_TTL, AiClient::duration( $calls ) + self::MARGIN );

        try {
            for( $i = 0; $i < self::MAX; $i++ )
            {
                $lock = Cache::lock( $key . ':' . $i, $ttl );

                if( $lock->get() ) {
                    return new self( $lock );
                }
            }
        } catch( \Throwable $e ) {
            Log::warning( 'Chat concurrency lock unavailable, running without it', ['message' => $e->getMessage()] );
            return new self( null );
        }

        throw new ConflictException( __( 'Too many responses are being generated' ) );
    }


    /**
     * Releases the slot, further calls do nothing.
     */
    public function release() : void
    {
        $this->lock?->release();
        $this->lock = null;
    }
}
