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
    private const MAX_DELETE_VALUES = 250;
    private const MAX_VALUE_LENGTH = 100;
    private const PERMISSIONS = ['access:view', 'access:add', 'access:delete'];

    /** @var \Closure(): iterable<mixed>|null */
    private static ?\Closure $listCallback = null;

    /** @var \Closure(string): void|null */
    private static ?\Closure $addCallback = null;

    /** @var \Closure(array<int, string>): void|null */
    private static ?\Closure $deleteCallback = null;

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
     */
    public static function using( ?\Closure $list, ?\Closure $add = null, ?\Closure $delete = null,
        ?\Closure $grants = null ) : void
    {
        self::configure(
            list: $list,
            add: $add,
            delete: $delete,
            grants: $grants,
        );
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
        $values = self::normalize( $values );
        $catalog = $this->catalog();

        return array_values( array_filter(
            $values,
            fn( string $value ) => isset( $catalog[$value] ),
        ) );
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

        $limit = self::limit();

        if( count( $catalog ) >= $limit ) {
            throw self::overflow( $limit );
        }

        ( self::$addCallback )( $value );
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

        if( count( $values ) > self::MAX_DELETE_VALUES ) {
            throw new Exception( sprintf(
                'No more than %d access values may be deleted at once.',
                self::MAX_DELETE_VALUES,
            ) );
        }

        $catalog = $this->catalog();
        $values = array_values( array_filter( $values, fn( $value ) => isset( $catalog[$value] ) ) );

        if( $values === [] ) {
            return array_keys( $catalog );
        }

        ( self::$deleteCallback )( $values );
        $this->refresh();

        return $this->list();
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
        $values = $values === null || is_array( $values )
            ? $values
            : iterator_to_array( $values, false );

        if( $values === null && isset( $this->allowed[$user] ) ) {
            return $this->allowed[$user];
        }

        $prepared = isset( $this->grants[$user] );

        if( $prepared ) {
            $granted = $this->grants[$user];
        } else {
            $extra = self::$extendCallback ? ( self::$extendCallback )( $user ) : [];
            $granted = array_fill_keys( self::normalize( $extra ?? [] ), true );
            $this->grants[$user] = $granted = array_intersect_key( $granted, $catalog );
        }

        if( !$prepared && self::$prepareCallback ) {
            ( self::$prepareCallback )( $user );
        }

        if( !isset( $this->resolved[$user] ) && self::$grantsCallback )
        {
            if( ( $resolved = ( self::$grantsCallback )( $user ) ) !== null ) {
                $granted += array_fill_keys( self::normalize( $resolved ), true );
                $this->grants[$user] = $granted = array_intersect_key( $granted, $catalog );
                $this->resolved[$user] = true;
            } else {
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
            prepare: function( Authenticatable $user ) {
                if( !$user instanceof Model ) {
                    throw new Exception( 'Spatie access requires an Eloquent user model.' );
                }

                $user->unsetRelation( 'roles' );
                $user->unsetRelation( 'permissions' );
            },
            add: function( string $value ) use ( $model, $guard ) {
                self::call( self::model( $model ), 'findOrCreate', $value, $guard );
            },
            delete: function( array $values ) use ( $model, $guard ) {
                self::modelDelete( $model, $values, ['guard_name' => $guard] );
            },
            grants: $grants,
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
        $limit = self::limit();

        foreach( $values as $value )
        {
            if( !is_string( $value ) ) {
                throw new Exception( 'Access values must be non-empty strings.' );
            }

            $catalog[self::value( $value )] = true;

            if( count( $catalog ) > $limit ) {
                throw self::overflow( $limit );
            }
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
            ( self::$activateCallback )( $tenant );
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
        }
    }


    /**
     * Stores access-adapter callbacks and resets the resolved service.
     */
    private static function configure( ?\Closure $list, ?\Closure $activate = null,
        ?\Closure $prepare = null, ?\Closure $add = null, ?\Closure $delete = null,
        ?\Closure $grants = null ) : void
    {
        self::$listCallback = $list;
        self::$activateCallback = $activate;
        self::$prepareCallback = $prepare;
        self::$addCallback = $add;
        self::$deleteCallback = $delete;
        self::$grantsCallback = $grants;
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
     * Returns a bounded, ordered list of configured permission names.
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

        $limit = self::limit();
        $values = $query->distinct()->orderBy( 'name' )
            ->limit( $limit + 1 )
            ->pluck( 'name' )
            ->all();

        if( count( $values ) > $limit ) {
            throw self::overflow( $limit );
        }

        return $values;
    }


    /**
     * Returns the strict maximum number of frontend access values.
     */
    private static function limit() : int
    {
        return max( 1, (int) config( 'cms.access.sql_limit', 128 ) );
    }


    /**
     * Creates the canonical access-catalog overflow exception.
     */
    private static function overflow( int $limit ) : Exception
    {
        return new Exception( sprintf(
            'Frontend access catalog exceeds cms.access.sql_limit (%d).',
            $limit,
        ) );
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
            throw new Exception( sprintf(
                'Access values may not be longer than %d characters.',
                self::MAX_VALUE_LENGTH,
            ) );
        }

        return $value;
    }
}
