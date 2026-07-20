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

    /** @var array<string, true>|null */
    private ?array $catalog = null;

    /** @var \WeakMap<object, array<string, bool>> */
    private \WeakMap $grants;

    /** @var \WeakMap<object, bool> */
    private \WeakMap $resolved;

    private ?string $tenant = null;


    public function __construct()
    {
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
        self::configure( list: $list, add: $add, delete: $delete, grants: $grants );
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

        if( isset( $this->catalog()[$value] ) ) {
            throw new Exception( sprintf( 'Access value "%s" already exists.', $value ) );
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
        $prepared = isset( $this->grants[$user] );
        $granted = $this->grants[$user] ?? [];

        if( !$prepared && self::$prepareCallback ) {
            ( self::$prepareCallback )( $user );
        }

        if( !isset( $this->resolved[$user] ) && self::$grantsCallback )
        {
            if( ( $resolved = ( self::$grantsCallback )( $user ) ) !== null ) {
                $granted = array_fill_keys( self::normalize( $resolved ), true );
                $this->grants[$user] = $granted;
                $this->resolved[$user] = true;
            } else {
                $this->resolved[$user] = false;
            }
        }

        if( ( $this->resolved[$user] ?? false ) === true ) {
            return $this->filter( $values ?? array_keys( $granted ), $granted );
        }

        $catalog = $this->catalog();
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

        self::configure( list: function() use ( $model ) {
            $values = self::modelNames( $model );

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
        },
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
     * @return array<string, true>
     */
    private function catalog() : array
    {
        $this->context();

        if( $this->catalog !== null ) {
            return $this->catalog;
        }

        $values = self::$listCallback ? ( self::$listCallback )() : [];
        return $this->catalog = array_fill_keys( self::normalize( $values ), true );
    }


    private static function call( object|string $target, string $method, mixed ...$args ) : mixed
    {
        $target = is_string( $target ) ? app( $target ) : $target;
        return $target->{$method}( ...$args );
    }


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


    private function refresh() : void
    {
        $this->catalog = null;
        $this->grants = new \WeakMap();
        $this->resolved = new \WeakMap();
    }


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


    private static function modelAdd( mixed $model, string $value ) : void
    {
        self::model( $model )->newQuery()->create( ['name' => $value] );
    }


    /**
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
     * @param array<string, mixed> $where
     * @return array<int, mixed>
     */
    private static function modelNames( mixed $model, array $where = [] ) : array
    {
        $query = self::model( $model )->newQuery();

        foreach( $where as $column => $value ) {
            $query->where( $column, $value );
        }

        return $query->pluck( 'name' )->all();
    }


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
