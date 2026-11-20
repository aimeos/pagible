<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;


/**
 * Runs queued jobs as the user who queued them, only class, guard and ID are serialized.
 */
trait ActsAsUser
{
    /**
     * Class of the user, named like in older queued jobs to keep them executable.
     */
    protected ?string $model = null;

    /**
     * Authentication guard the user was authenticated with.
     */
    protected ?string $guard = null;

    /**
     * FALSE in older jobs queued without user outside Permission::system(), not executed.
     */
    protected ?bool $system = null;

    /**
     * ID of the user who queued the job or NULL for the system.
     */
    protected int|string|null $userId = null;


    /**
     * Executes the callback as the user who queued the job and restores the previous user for later jobs.
     *
     * @param \Closure(Authenticatable|null): mixed $fn Receives the user or NULL if queued inside Permission::system()
     * @return mixed Return value of the callback
     * @throws PermissionException If the user doesn't exist anymore
     * @throws Exception If the guard has no user provider
     */
    protected function acting( \Closure $fn ) : mixed
    {
        if( $this->userId === null && $this->system !== false ) {
            return Permission::system( fn() => $fn( null ) );
        }

        $user = $this->user() ?? throw new PermissionException( __( 'Insufficient permissions' ) );

        $default = Auth::getDefaultDriver();
        Auth::shouldUse( (string) $this->guard );

        $previous = Auth::hasUser() ? Auth::user() : null;
        Auth::setUser( $user );

        try {
            return $fn( $user );
        } finally {
            $previous ? Auth::setUser( $previous ) : Auth::forgetUser();
            Auth::shouldUse( $default );
        }
    }


    /**
     * Stores the user the job will be executed as.
     *
     * @param Authenticatable|null $user User who queued the job or NULL if queued inside Permission::system()
     * @param string|null $guard Authentication guard the user was authenticated with, the default guard if NULL
     */
    protected function remember( ?Authenticatable $user, ?string $guard = null ) : void
    {
        $this->model = $user ? get_class( $user ) : null;
        $this->guard = $user ? ( $guard ?? Auth::getDefaultDriver() ) : null;
        $this->userId = $user?->getAuthIdentifier();
    }


    /**
     * Returns the user who queued the job.
     *
     * @return Authenticatable|null User or NULL if not available anymore
     */
    private function user() : ?Authenticatable
    {
        $provider = Auth::createUserProvider( config( "auth.guards.{$this->guard}.provider" ) )
            ?? throw new Exception( __( 'No user provider configured for guard ":guard"', ['guard' => (string) $this->guard] ) );
        $user = $provider->retrieveById( $this->userId );

        // guards without own provider (e.g. Sanctum) may load another model
        return $user && get_class( $user ) === $this->model ? $user : null;
    }
}
