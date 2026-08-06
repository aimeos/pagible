<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;


/**
 * Owns the access catalog independently from CMS editor permissions and consumers.
 */
class Access
{
    private const MAX_CHANGE_VALUES = 250;
    private const MAX_VALUE_LENGTH = 100;
    private const PERMISSIONS = ['access:view', 'access:add', 'access:delete', 'user:access'];

    /** @var \Closure(): iterable<mixed>|null */
    private static ?\Closure $listCallback = null;

    /** @var \Closure(string): void|null */
    private static ?\Closure $addCallback = null;

    /** @var \Closure(array<int, string>): void|null */
    private static ?\Closure $deleteCallback = null;

    /** @var (\Closure(Authenticatable, ?array<int, string>): iterable<mixed>)|null */
    private static ?\Closure $userAccessCallback = null;

    /** @var \Closure(string): void|null */
    private static ?\Closure $activateCallback = null;

    /** @var \Closure(Authenticatable): void|null */
    private static ?\Closure $prepareCallback = null;

    /** @var (\Closure(Authenticatable): (iterable<mixed>|null))|null */
    private static ?\Closure $grantsCallback = null;

    /** @var (\Closure(Authenticatable): (iterable<mixed>|null))|null */
    private static ?\Closure $extendCallback = null;

    /** @var array<string, true>|null */
    private ?array $catalog = null;

    /** @var \WeakMap<object, array<int, string>> */
    private \WeakMap $allowed;

    /** @var \WeakMap<object, array<string, bool>> */
    private \WeakMap $grants;

    /** @var \WeakMap<object, bool> */
    private \WeakMap $resolved;

    private ?string $tenant = null;


    /**
     * Initializes request-local access and grant caches.
     */
    public function __construct()
    {
        $this->allowed = new \WeakMap();
        $this->grants = new \WeakMap();
        $this->resolved = new \WeakMap();
    }


    /**
     * Configures a custom access catalog for the current context.
     *
     * @param \Closure(): iterable<mixed>|null $list Callback returning access values or NULL to reset
     * @param \Closure(string): void|null $add Optional callback adding an access value
     * @param \Closure(array<int, string>): void|null $delete Optional callback deleting access values
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Optional effective-grants resolver
     * @param (\Closure(Authenticatable, ?array<int, string>): iterable<mixed>)|null $userAccess Optional direct-assignment handler; NULL values read, arrays atomically replace
     */
    public static function using( ?\Closure $list, ?\Closure $add = null, ?\Closure $delete = null,
        ?\Closure $grants = null, ?\Closure $userAccess = null ) : void
    {
        self::configure( $list, $add, $delete, $grants, $userAccess );
    }


    /**
     * Adds grants from an optional package without replacing the configured access resolver.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants
     */
    public static function extend( ?\Closure $grants ) : void
    {
        self::$extendCallback = $grants;
        app()->forgetInstance( self::class );
    }


    /**
     * Returns frontend access values assigned directly to the user.
     *
     * @return array<int, string>
     */
    public function assigned( Authenticatable $user ) : array
    {
        return $this->userAccess( $user );
    }


    /**
     * Lists the access values available in the current context.
     *
     * @return array<int, string>
     */
    public function list() : array
    {
        return array_keys( $this->catalog() );
    }


    /**
     * Tests catalog membership.
     */
    public function has( string $value ) : bool
    {
        return $this->known( [$value] ) !== [];
    }


    /**
     * Returns supplied values that exist in the configured access catalog.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public function known( iterable $values ) : array
    {
        $catalog = $this->catalog();
        $values = self::normalize( $values );

        return array_values( array_filter( $values, fn( string $value ) => isset( $catalog[$value] ) ) );
    }


    /**
     * Searches access values by case-insensitive prefix.
     *
     * @return array<int, string>
     */
    public function search( string $term = '', int $limit = 50 ) : array
    {
        $term = mb_substr( trim( $term ), 0, self::MAX_VALUE_LENGTH );
        $limit = max( 1, min( 100, $limit ) );
        $term = mb_strtolower( $term );

        return array_slice( array_values( array_filter(
            array_keys( $this->catalog() ),
            fn( string $value ) => $term === '' || str_starts_with( mb_strtolower( $value ), $term ),
        ) ), 0, $limit );
    }


    /**
     * Adds an access value and returns the refreshed catalog.
     *
     * @return array<int, string>
     */
    public function add( string $value ) : array
    {
        if( !self::$addCallback ) {
            throw new Exception( 'Adding access values is not available.' );
        }

        $value = self::value( $value );

        $catalog = $this->catalog();

        if( isset( $catalog[$value] ) ) {
            throw new Exception( sprintf( 'Access value "%s" already exists.', $value ) );
        }

        (self::$addCallback)( $value );
        $this->refresh();

        return $this->list();
    }


    /**
     * Deletes access values and returns the refreshed catalog.
     *
     * Missing values are ignored so concurrent catalog changes remain safe.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public function delete( iterable $values ) : array
    {
        if( !self::$deleteCallback ) {
            throw new Exception( 'Deleting access values is not available.' );
        }

        $values = self::normalize( $values );

        if( count( $values ) > self::MAX_CHANGE_VALUES ) {
            throw new Exception( sprintf( 'No more than %d access values may be deleted at once.', self::MAX_CHANGE_VALUES ) );
        }

        $catalog = $this->catalog();
        $values = array_values( array_filter( $values, fn( $value ) => isset( $catalog[$value] ) ) );

        if( $values === [] ) {
            return array_keys( $catalog );
        }

        (self::$deleteCallback)( $values );
        $this->refresh();

        return $this->list();
    }


    /**
     * Replaces frontend access values assigned directly to the user.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public function set( Authenticatable $user, iterable $values ) : array
    {
        $tenant = Tenancy::value();

        if( !Tenancy::allows( $user, $tenant ) ) {
            throw new Exception( 'Frontend access can only be changed for users in the current tenant.' );
        }

        return $this->replace( $user, $this->changes( $values ), $tenant );
    }


    /**
     * Returns canonical access values.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public static function normalize( iterable $values ) : array
    {
        $result = [];

        foreach( $values as $value )
        {
            if( !is_string( $value ) ) {
                throw new Exception( 'Access values must be non-empty strings.' );
            }

            $result[self::value( $value )] = true;
        }

        $result = array_keys( $result );
        sort( $result, SORT_STRING );

        return $result;
    }


    /**
     * Returns candidate access values granted to the user.
     *
     * @param iterable<mixed>|null $values Candidate values or NULL for all available values
     * @return array<int, string>
     */
    public function allowed( Authenticatable $user, ?iterable $values = null ) : array
    {
        $this->context();
        $catalog = $this->catalog();

        if( $values && !is_array( $values ) ) {
            $values = iterator_to_array( $values, false );
        }

        if( $values === null && isset( $this->allowed[$user] ) ) {
            return $this->allowed[$user];
        }

        $prepared = isset( $this->grants[$user] );

        if( !$prepared )
        {
            $extra = self::$extendCallback ? ( self::$extendCallback )( $user ) : [];
            $granted = array_fill_keys( self::normalize( $extra ?? [] ), true );
            $this->grants[$user] = $granted = array_intersect_key( $granted, $catalog );
        }
        else
        {
            $granted = $this->grants[$user];
        }

        if( !$prepared && self::$prepareCallback ) {
            (self::$prepareCallback)( $user );
        }

        if( !isset( $this->resolved[$user] ) && self::$grantsCallback )
        {
            if( ( $resolved = ( self::$grantsCallback )( $user ) ) !== null )
            {
                $granted += array_fill_keys( self::normalize( $resolved ), true );
                $this->grants[$user] = $granted = array_intersect_key( $granted, $catalog );
                $this->resolved[$user] = true;
            }
            else
            {
                $this->resolved[$user] = false;
            }
        }

        if( ( $this->resolved[$user] ?? false ) === true )
        {
            $result = $this->filter( $values ?? array_keys( $catalog ), $granted );

            if( $values === null ) {
                $this->allowed[$user] = $result;
            }

            return $result;
        }

        $gate = Gate::forUser( $user );
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

        $this->grants[$user] = $granted;

        if( $values === null ) {
            $this->allowed[$user] = $result;
        }

        return $result;
    }


    /**
     * Configures the access catalog through silber/bouncer.
     *
     * Requires silber/bouncer 1.0.2 or newer.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function bouncer( ?\Closure $grants = null ) : void
    {
        $class = 'Silber\\Bouncer\\Bouncer';

        self::configure(
            list: fn() => self::modelNames(
                self::call( $class, 'ability' ),
                ['entity_type' => null],
            ),
            activate: fn( string $tenant ) => self::call( self::call( $class, 'scope' ), 'to', $tenant ),
            add: function( string $value ) use ( $class ) {
                self::modelAdd( self::call( $class, 'ability' ), $value );
                self::call( $class, 'refresh' );
            },
            delete: function( array $values ) use ( $class ) {
                self::modelDelete( self::call( $class, 'ability' ), $values, ['entity_type' => null] );
                self::call( $class, 'refresh' );
            },
            grants: $grants,
            userAccess: function( Authenticatable $user, ?array $values ) use ( $class ) {
                $current = self::bouncerAssigned( $user );

                if( $values === null ) {
                    return $current;
                }

                $assign = array_values( array_diff( $values, $current ) );
                $remove = array_values( array_diff( $current, $values ) );

                if( $assign ) {
                    self::call( self::call( $class, 'allow', $user ), 'to', $assign );
                }

                if( $remove ) {
                    self::call( self::call( $class, 'disallow', $user ), 'to', $remove );
                }

                if( $assign || $remove ) {
                    self::call( $class, 'refreshFor', $user );
                }

                return self::bouncerAssigned( $user );
            },
        );
    }


    /**
     * Configures the access catalog through santigarcor/laratrust.
     *
     * Requires santigarcor/laratrust 8.3.0 or newer.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function laratrust( ?\Closure $grants = null ) : void
    {
        $model = config( 'laratrust.models.permission' );

        self::configure(
            list: fn() => self::laratrustGates( self::modelNames( $model ) ),
            add: fn( string $value ) => self::modelAdd( $model, $value ),
            delete: fn( array $values ) => self::modelDelete( $model, $values ),
            grants: $grants,
            userAccess: function( Authenticatable $user, ?array $values ) {
                $current = self::laratrustAssigned( $user );

                if( $values === null ) {
                    return $current;
                }

                $assign = array_values( array_diff( $values, $current ) );
                $remove = array_values( array_diff( $current, $values ) );
                $team = config( 'laratrust.teams.enabled', false ) ? Tenancy::value() : null;

                if( $assign ) {
                    self::call( $user, 'givePermissions', $assign, $team );
                }

                if( $remove ) {
                    self::call( $user, 'removePermissions', $remove, $team );
                }

                return self::laratrustAssigned( $user );
            },
        );
    }


    /**
     * Configures the access catalog through spatie/laravel-permission.
     *
     * Requires spatie/laravel-permission 6.2.0 or newer.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants Effective-grants resolver
     */
    public static function spatie( ?\Closure $grants = null ) : void
    {
        $registrar = 'Spatie\\Permission\\PermissionRegistrar';
        $model = config(
            'permission.models.permission',
            'Spatie\\Permission\\Models\\Permission',
        );
        $guard = config( 'auth.defaults.guard', 'web' );

        self::configure(
            list: fn() => self::modelNames( $model, ['guard_name' => $guard] ),
            activate: fn( string $tenant ) => self::call( $registrar, 'setPermissionsTeamId', $tenant ),
            prepare: fn( Authenticatable $user ) => self::prepareSpatie( $user ),
            add: function( string $value ) use ( $model, $guard ) {
                self::call( self::model( $model ), 'findOrCreate', $value, $guard );
            },
            delete: function( array $values ) use ( $model, $guard ) {
                self::modelDelete( $model, $values, ['guard_name' => $guard] );
            },
            grants: $grants,
            userAccess: function( Authenticatable $user, ?array $values ) {
                self::prepareSpatie( $user );

                if( $values !== null ) {
                    self::call( $user, 'syncPermissions', $values );
                    self::prepareSpatie( $user );
                }

                return self::itemNames( self::call( $user, 'getDirectPermissions' ) );
            },
        );
    }


    /**
     * Loads and validates the access catalog for the active tenant.
     *
     * @return array<string, true>
     */
    private function catalog() : array
    {
        $this->context();

        if( ( $catalog = $this->catalog ) !== null ) {
            return $catalog;
        }

        $values = self::$listCallback ? ( self::$listCallback )() : [];
        $catalog = [];

        foreach( $values as $value )
        {
            if( !is_string( $value ) ) {
                throw new Exception( 'Access values must be non-empty strings.' );
            }

            $catalog[self::value( $value )] = true;
        }

        ksort( $catalog, SORT_STRING );
        $this->catalog = $catalog;

        return $catalog;
    }


    /**
     * Invokes a method on a service instance or object.
     */
    private static function call( object|string $target, string $method, mixed ...$args ) : mixed
    {
        $target = is_string( $target ) ? app( $target ) : $target;
        return $target->{$method}( ...$args );
    }


    /**
     * Returns validated catalog values for an assignment change.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    private function changes( iterable $values ) : array
    {
        $values = self::normalize( $values );

        if( count( $values ) > self::MAX_CHANGE_VALUES ) {
            throw new Exception( sprintf( 'No more than %d user access values may be changed at once.', self::MAX_CHANGE_VALUES ) );
        }

        if( $unknown = array_diff( $values, $this->list() ) ) {
            throw new Exception( sprintf( 'Unknown frontend access value "%s".', reset( $unknown ) ) );
        }

        return $values;
    }


    /**
     * Activates the current tenant and clears request-local state when it changes.
     */
    private function context() : void
    {
        $tenant = Tenancy::value();

        if( $this->tenant === $tenant ) {
            return;
        }

        $this->refresh();

        if( self::$activateCallback ) {
            (self::$activateCallback)( $tenant );
        }

        $this->tenant = $tenant;
    }


    /**
     * Filters candidate values by a resolved grant map.
     *
     * @param iterable<mixed> $values
     * @param array<string, bool> $granted
     * @return array<int, string>
     */
    private function filter( iterable $values, array $granted ) : array
    {
        $result = $seen = [];

        foreach( $values as $value )
        {
            if( !is_string( $value ) || !isset( $granted[$value] ) || isset( $seen[$value] ) ) {
                continue;
            }

            $seen[$value] = true;
            $result[] = $value;
        }

        return $result;
    }


    /**
     * Returns names from provider model collections.
     *
     * @return array<int, mixed>
     */
    private static function itemNames( mixed $items ) : array
    {
        if( !is_iterable( $items ) ) {
            throw new Exception( 'Access provider returned an invalid assignment list.' );
        }

        $result = [];

        foreach( $items as $item ) {
            $result[] = data_get( $item, 'name' );
        }

        return $result;
    }


    /**
     * Returns Bouncer abilities assigned directly in the active scope.
     *
     * @return array<int, mixed>
     */
    private static function bouncerAssigned( Authenticatable $user ) : array
    {
        $relation = self::call( $user, 'abilities' );

        if( !is_object( $relation ) ) {
            throw new Exception( 'Bouncer user abilities relation is not available.' );
        }

        $related = self::call( $relation, 'getRelated' );

        if( !$related instanceof Model ) {
            throw new Exception( 'Bouncer ability model is not available.' );
        }

        self::call( $relation, 'whereNull', $related->qualifyColumn( 'entity_type' ) );
        self::call( $relation, 'wherePivot', 'forbidden', false );

        return self::itemNames( self::call( $relation, 'get', [$related->qualifyColumn( 'name' )] ) );
    }


    /**
     * Registers tenant-aware Laratrust gates for catalog values loaded on demand.
     *
     * @param array<int, mixed> $values
     * @return array<int, mixed>
     */
    private static function laratrustGates( array $values ) : array
    {
        foreach( $values as $value )
        {
            if( !is_string( $value ) || trim( $value ) === ''
                || ( Gate::has( $value ) && !config( 'laratrust.permissions_as_gates', false ) )
            ) {
                continue;
            }

            Gate::define( $value, function( Authenticatable $user ) use ( $value ) {
                $team = config( 'laratrust.teams.enabled', false ) ? Tenancy::value() : null;
                return (bool) self::call( $user, 'isAbleTo', $value, $team );
            } );
        }

        return $values;
    }


    /**
     * Returns Laratrust permissions assigned directly in the active team.
     *
     * @return array<int, mixed>
     */
    private static function laratrustAssigned( Authenticatable $user ) : array
    {
        $relation = self::call( $user, 'permissions' );

        if( !is_object( $relation ) ) {
            throw new Exception( 'Laratrust user permissions relation is not available.' );
        }

        if( config( 'laratrust.teams.enabled', false ) )
        {
            $helper = 'Laratrust\\Helper';
            $key = (string) config( 'laratrust.foreign_keys.team', 'team_id' );
            $team = $helper::getIdFor( Tenancy::value(), 'team' );

            self::call( $relation, 'wherePivot', $key, $team );
        }

        return self::itemNames( self::call( $relation, 'get', ['name'] ) );
    }


    /**
     * Clears Spatie relations which vary by active team.
     */
    private static function prepareSpatie( Authenticatable $user ) : void
    {
        if( !$user instanceof Model ) {
            throw new Exception( 'Spatie access requires an Eloquent user model.' );
        }

        $user->unsetRelation( 'roles' );
        $user->unsetRelation( 'permissions' );
    }


    /**
     * Synchronizes CMS permissions with the configured catalog capabilities.
     */
    private static function syncPermissions() : void
    {
        Permission::unregister( self::PERMISSIONS );

        if( self::$listCallback )
        {
            Permission::register( 'access:view' );

            if( self::$addCallback ) {
                Permission::register( 'access:add' );
            }

            if( self::$deleteCallback ) {
                Permission::register( 'access:delete' );
            }

            if( self::$userAccessCallback ) {
                Permission::register( 'user:access' );
            }
        }
    }


    /**
     * Stores access-adapter callbacks and resets the resolved service.
     */
    private static function configure( ?\Closure $list, ?\Closure $activate = null,
        ?\Closure $prepare = null, ?\Closure $add = null, ?\Closure $delete = null,
        ?\Closure $grants = null, ?\Closure $userAccess = null ) : void
    {
        self::$listCallback = $list;
        self::$activateCallback = $activate;
        self::$prepareCallback = $prepare;
        self::$addCallback = $add;
        self::$deleteCallback = $delete;
        self::$grantsCallback = $grants;
        self::$userAccessCallback = $userAccess;
        self::syncPermissions();

        app()->forgetInstance( self::class );
    }


    /**
     * Clears all request-local catalog and grant caches.
     */
    private function refresh() : void
    {
        $this->catalog = null;
        $this->allowed = new \WeakMap();
        $this->grants = new \WeakMap();
        $this->resolved = new \WeakMap();
    }


    /**
     * Replaces direct assignments while serializing persisted-user changes.
     *
     * @param array<int, string> $values
     * @return array<int, string>
     */
    private function replace( Authenticatable $user, array $values, string $tenant ) : array
    {
        if( !$user instanceof Model || !$user->exists || $user->getKey() === null ) {
            return $this->userAccess( $user, $values );
        }

        return $user->getConnection()->transaction( function() use ( $tenant, $user, $values ) {
            /** @var Model&Authenticatable $locked */
            $locked = $user->newQuery()->whereKey( $user->getKey() )->lockForUpdate()->firstOrFail();

            if( !Tenancy::allows( $locked, $tenant ) ) {
                throw new Exception( 'Frontend access can only be changed for users in the current tenant.' );
            }

            return $this->userAccess( $locked, $values );
        } );
    }


    /**
     * Reads or atomically replaces direct access assignments for one user.
     *
     * @param array<int, string>|null $values NULL reads; an array replaces the complete set
     * @return array<int, string>
     */
    private function userAccess( Authenticatable $user, ?array $values = null ) : array
    {
        if( !( $callback = self::$userAccessCallback ) ) {
            throw new Exception( 'User access assignments are not available.' );
        }

        $this->context();
        $result = $callback( $user, $values );

        if( $values !== null )
        {
            $this->allowed = new \WeakMap();
            $this->grants = new \WeakMap();
            $this->resolved = new \WeakMap();
        }

        return $this->known( $result );
    }


    /**
     * Resolves and validates a configured permission model.
     */
    private static function model( mixed $model ) : Model
    {
        if( is_string( $model ) ) {
            $model = new $model();
        }

        if( !$model instanceof Model ) {
            throw new Exception( 'Configured permission model must be an Eloquent model.' );
        }

        return $model;
    }


    /**
     * Adds a value through the configured permission model.
     */
    private static function modelAdd( mixed $model, string $value ) : void
    {
        self::model( $model )->newQuery()->create( ['name' => $value] );
    }


    /**
     * Deletes named values through the configured permission model.
     *
     * @param array<int, string> $values
     * @param array<string, mixed> $where
     */
    private static function modelDelete( mixed $model, array $values, array $where = [] ) : void
    {
        $model = self::model( $model );

        $model->getConnection()->transaction( function() use ( $model, $values, $where ) {
            $query = $model->newQuery();

            foreach( $where as $column => $value ) {
                $query->where( $column, $value );
            }

            $query->whereIn( 'name', $values )->get()->each->delete();
        } );
    }


    /**
     * Returns an ordered list of configured permission names.
     *
     * @param array<string, mixed> $where
     * @return array<int, mixed>
     */
    private static function modelNames( mixed $model, array $where = [] ) : array
    {
        $query = self::model( $model )->newQuery();

        foreach( $where as $column => $value ) {
            $query->where( $column, $value );
        }

        $values = $query->distinct()->orderBy( 'name' )
            ->pluck( 'name' )
            ->all();

        return $values;
    }


    /**
     * Validates and normalizes one access value.
     */
    private static function value( string $value ) : string
    {
        if( ( $value = trim( $value ) ) === '' ) {
            throw new Exception( 'Access values must be non-empty strings.' );
        }

        if( mb_strlen( $value ) > self::MAX_VALUE_LENGTH ) {
            throw new Exception( sprintf( 'Access values may not be longer than %d characters.', self::MAX_VALUE_LENGTH ) );
        }

        return $value;
    }
}
