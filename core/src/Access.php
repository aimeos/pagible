<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;


/**
 * Resolves frontend access values independently from CMS editor permissions.
 */
class Access
{
    /** @var \Closure(): iterable<mixed>|null */
    private static ?\Closure $availableCallback = null;

    /** @var \Closure(string): void|null */
    private static ?\Closure $activateCallback = null;

    /** @var \Closure(Authenticatable): void|null */
    private static ?\Closure $prepareCallback = null;

    /** @var array<string, array<string, true>> */
    private array $catalogs = [];

    /** @var \WeakMap<object, array<string, array<string, bool>>> */
    private \WeakMap $grants;


    public function __construct()
    {
        $this->grants = new \WeakMap();

        if( self::$activateCallback ) {
            ( self::$activateCallback )( Tenancy::value() );
        }
    }


    /**
     * Tests whether frontend access restrictions have been configured.
     */
    public static function isAvailable() : bool
    {
        return self::$availableCallback !== null;
    }


    /**
     * Returns the frontend access values available in the current context.
     *
     * @return array<int, string>
     */
    public function all() : array
    {
        return array_keys( $this->catalog( Tenancy::value() ) );
    }


    /**
     * Sets the callback returning frontend access values for the current context.
     *
     * @param \Closure|null $callback Callback or NULL to reset
     */
    public static function availableUsing( ?\Closure $callback ) : void
    {
        self::configure( $callback );
    }


    /**
     * Returns candidate frontend access values granted to the user by Gate.
     *
     * @param iterable<mixed>|null $values Candidate values or NULL for all available values
     * @return array<int, string>
     */
    public function allowed( Authenticatable $user, ?iterable $values = null ) : array
    {
        $tenant = Tenancy::value();
        $catalog = $this->catalog( $tenant );
        $gate = Gate::forUser( $user );

        $users = $this->grants[$user] ?? [];

        if( !array_key_exists( $tenant, $users ) && self::$prepareCallback ) {
            ( self::$prepareCallback )( $user );
        }

        $granted = $users[$tenant] ?? [];
        $result = $seen = [];

        foreach( $values ?? array_keys( $catalog ) as $value )
        {
            if( !is_string( $value ) || !isset( $catalog[$value] ) || isset( $seen[$value] ) ) {
                continue;
            }

            $seen[$value] = true;
            $granted[$value] ??= $gate->allows( $value );

            if( $granted[$value] ) {
                $result[] = $value;
            }
        }

        $users[$tenant] = $granted;
        $this->grants[$user] = $users;

        return $result;
    }


    /**
     * Configures frontend access through silber/bouncer.
     *
     * Requires silber/bouncer 1.0.2 or newer.
     */
    public static function bouncer() : void
    {
        $class = 'Silber\\Bouncer\\Bouncer';

        self::configure(
            fn() => self::modelNames( self::call( $class, 'ability' ) ),
            fn( string $tenant ) => self::call( self::call( $class, 'scope' ), 'to', $tenant ),
        );
    }


    /**
     * Configures frontend access through santigarcor/laratrust.
     *
     * Requires santigarcor/laratrust 8.3.0 or newer.
     */
    public static function laratrust() : void
    {
        self::configure( function() {
            $values = self::modelNames( config( 'laratrust.models.permission' ) );

            foreach( $values as $value )
            {
                if( !is_string( $value ) || trim( $value ) === '' ) {
                    continue;
                }

                if( Gate::has( $value ) && !config( 'laratrust.permissions_as_gates', false ) ) {
                    continue;
                }

                Gate::define( $value, function( Authenticatable $user ) use ( $value ) {
                    $team = config( 'laratrust.teams.enabled', false ) ? Tenancy::value() : null;
                    return (bool) self::call( $user, 'isAbleTo', $value, $team );
                } );
            }

            return $values;
        } );
    }


    /**
     * Configures frontend access through spatie/laravel-permission.
     *
     * Requires spatie/laravel-permission 6.2.0 or newer.
     */
    public static function spatie() : void
    {
        $registrar = 'Spatie\\Permission\\PermissionRegistrar';

        self::configure(
            fn() => self::modelNames( config(
                'permission.models.permission',
                'Spatie\\Permission\\Models\\Permission',
            ) ),
            fn( string $tenant ) => self::call( $registrar, 'setPermissionsTeamId', $tenant ),
            function( Authenticatable $user ) {
                if( !$user instanceof Model ) {
                    throw new Exception( 'Spatie access requires an Eloquent user model.' );
                }

                $user->unsetRelation( 'roles' );
                $user->unsetRelation( 'permissions' );
            },
        );
    }


    /**
     * @return array<string, true>
     */
    private function catalog( string $tenant ) : array
    {
        if( isset( $this->catalogs[$tenant] ) ) {
            return $this->catalogs[$tenant];
        }

        $catalog = [];

        foreach( self::$availableCallback ? ( self::$availableCallback )() : [] as $value )
        {
            if( !is_string( $value ) || ( $value = trim( $value ) ) === '' ) {
                throw new Exception( 'Frontend access values must be non-empty strings.' );
            }

            $catalog[$value] = true;
        }

        ksort( $catalog, SORT_STRING );

        return $this->catalogs[$tenant] = $catalog;
    }


    private static function call( object|string $target, string $method, mixed ...$args ) : mixed
    {
        $target = is_string( $target ) ? app( $target ) : $target;
        return $target->{$method}( ...$args );
    }


    private static function configure( ?\Closure $available, ?\Closure $activate = null,
        ?\Closure $prepare = null ) : void
    {
        self::$availableCallback = $available;
        self::$activateCallback = $activate;
        self::$prepareCallback = $prepare;
        app()->forgetInstance( self::class );
    }


    /**
     * @return array<int, mixed>
     */
    private static function modelNames( mixed $model ) : array
    {
        if( is_string( $model ) ) {
            $model = new $model();
        }

        if( !$model instanceof Model ) {
            throw new Exception( 'Configured permission model must be an Eloquent model.' );
        }

        return $model->newQuery()->pluck( 'name' )->all();
    }
}
