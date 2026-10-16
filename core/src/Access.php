<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Access\Adapter;
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

    /** @var array<string, true>|null */
    private ?array $catalog = null;

    /** @var \WeakMap<object, array<int, string>> */
    private \WeakMap $allowed;

    private ?string $tenant = null;

    /**
     * Initializes the request-local access cache.
     */
    public function __construct()
    {
        $this->allowed = new \WeakMap();
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
        self::configure( new Adapter(
            list: $list,
            add: $add,
            delete: $delete,
            grants: $grants,
            userAccess: $userAccess,
        ) );
    }


    /**
     * Adds grants from an optional package without replacing the configured access resolver.
     *
     * @param (\Closure(Authenticatable): (iterable<mixed>|null))|null $grants
     */
    public static function extend( ?\Closure $grants ) : void
    {
        Hooks::get()->extend = $grants;
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
        return array_map( 'strval', array_keys( $this->catalog() ) );
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
            $this->list(),
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
        if( !( $callback = Hooks::get()->access->add ) ) {
            throw new Exception( 'Adding access values is not available.' );
        }

        $value = self::value( $value );

        $catalog = $this->catalog();

        if( isset( $catalog[$value] ) ) {
            throw new Exception( sprintf( 'Access value "%s" already exists.', $value ) );
        }

        $callback( $value );
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
        if( !( $callback = Hooks::get()->access->delete ) ) {
            throw new Exception( 'Deleting access values is not available.' );
        }

        $values = self::normalize( $values );

        if( count( $values ) > self::MAX_CHANGE_VALUES ) {
            throw new Exception( sprintf( 'No more than %d access values may be deleted at once.', self::MAX_CHANGE_VALUES ) );
        }

        if( ( $values = $this->known( $values ) ) === [] ) {
            return $this->list();
        }

        $callback( $values );
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

        $result = array_map( 'strval', array_keys( $result ) );
        sort( $result, SORT_STRING );

        return $result;
    }


    /**
     * Returns the access values granted to the user.
     *
     * @return array<int, string>
     */
    public function allowed( Authenticatable $user ) : array
    {
        $this->context();
        $catalog = $this->catalog();

        if( isset( $this->allowed[$user] ) ) {
            return $this->allowed[$user];
        }

        $hooks = Hooks::get();
        $adapter = $hooks->access;
        $extra = ( $extend = $hooks->extend ) ? $extend( $user ) : [];
        $granted = array_intersect_key( array_fill_keys( self::normalize( $extra ?? [] ), true ), $catalog );

        if( $adapter->prepare ) {
            ( $adapter->prepare )( $user );
        }

        $resolved = $adapter->grants ? ( $adapter->grants )( $user ) : null;

        if( $resolved !== null )
        {
            // the grants callback returns the complete set of granted values
            $granted = array_intersect_key( $granted + array_fill_keys( self::normalize( $resolved ), true ), $catalog );
        }
        else
        {
            $gate = Gate::forUser( $user );

            foreach( $catalog as $value => $_ ) {
                $granted[$value] ??= $gate->allows( (string) $value );
            }
        }

        return $this->allowed[$user] = array_values( array_filter( $this->list(), fn( string $value ) => !empty( $granted[$value] ) ) );
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
        self::configure( Access\Bouncer::adapter( $grants ) );
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
        self::configure( Access\Laratrust::adapter( $grants ) );
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
        self::configure( Access\Spatie::adapter( $grants ) );
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

        $list = Hooks::get()->access->list;
        $values = $list ? $list() : [];

        return $this->catalog = array_fill_keys( self::normalize( $values ), true );
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

        if( $activate = Hooks::get()->access->activate ) {
            $activate( $tenant );
        }

        $this->tenant = $tenant;
    }


    /**
     * Synchronizes CMS permissions with the configured catalog capabilities.
     */
    private static function syncPermissions() : void
    {
        Permission::unregister( self::PERMISSIONS );

        $adapter = Hooks::get()->access;

        if( $adapter->list )
        {
            Permission::register( 'access:view' );

            if( $adapter->add ) {
                Permission::register( 'access:add' );
            }

            if( $adapter->delete ) {
                Permission::register( 'access:delete' );
            }

            if( $adapter->userAccess ) {
                Permission::register( 'user:access' );
            }
        }
    }


    /**
     * Stores the access adapter and resets the resolved service.
     */
    private static function configure( Adapter $adapter ) : void
    {
        Hooks::get()->access = $adapter;
        self::syncPermissions();

        app()->forgetInstance( self::class );
    }


    /**
     * Clears all request-local catalog and grant caches.
     */
    private function refresh() : void
    {
        $this->catalog = null;
        $this->reset();
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

        return Tenancy::lock( $user, $tenant, 'Frontend access can only be changed for users in the current tenant.',
            fn( Authenticatable&Model $locked ) => $this->userAccess( $locked, $values )
        );
    }


    /**
     * Clears the request-local access cache of all users.
     */
    private function reset() : void
    {
        $this->allowed = new \WeakMap();
    }


    /**
     * Reads or atomically replaces direct access assignments for one user.
     *
     * @param array<int, string>|null $values NULL reads; an array replaces the complete set
     * @return array<int, string>
     */
    private function userAccess( Authenticatable $user, ?array $values = null ) : array
    {
        if( !( $callback = Hooks::get()->access->userAccess ) ) {
            throw new Exception( 'User access assignments are not available.' );
        }

        $this->context();
        $result = $callback( $user, $values );

        if( $values !== null ) {
            $this->reset();
        }

        return $this->known( $result );
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
