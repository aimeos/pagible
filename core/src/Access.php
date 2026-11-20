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
     * Each call adds another callback, so several packages can add their grants.
     *
     * @param \Closure(Authenticatable): (iterable<mixed>|null) $grants Callback returning the additional grants of the user
     */
    public static function extend( \Closure $grants ) : void
    {
        $hooks = Hooks::get();
        $hooks->extend = [...$hooks->extend, $grants];
        app()->forgetInstance( self::class );
    }


    /**
     * Removes the grant callbacks added by all packages using extend().
     *
     * @internal Only for resetting the state between tests
     */
    public static function resetExtensions() : void
    {
        Hooks::get()->extend = [];
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

        if( $this->has( $value = self::value( $value ) ) ) {
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

        if( $values = $this->known( $values ) )
        {
            $callback( $values );
            $this->refresh();
        }

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
        $error = 'Frontend access can only be changed for users in the current tenant.';

        if( !Tenancy::allows( $user, $tenant ) ) {
            throw new Exception( $error );
        }

        $values = self::normalize( $values );

        if( count( $values ) > self::MAX_CHANGE_VALUES ) {
            throw new Exception( sprintf( 'No more than %d user access values may be changed at once.', self::MAX_CHANGE_VALUES ) );
        }

        if( $unknown = array_diff( $values, $this->known( $values ) ) ) {
            throw new Exception( sprintf( 'Unknown frontend access value "%s".', reset( $unknown ) ) );
        }

        // persisted users are locked to serialize the changes
        if( !$user instanceof Model || !$user->exists || $user->getKey() === null ) {
            return $this->userAccess( $user, $values );
        }

        return Tenancy::lock( $user, $tenant, $error, fn( Authenticatable&Model $locked ) => $this->userAccess( $locked, $values ) );
    }


    /**
     * Returns canonical access values.
     *
     * @param iterable<mixed> $values
     * @return array<int, string>
     */
    public static function normalize( iterable $values, ?int $max = null ) : array
    {
        return Validation::stringList( $values, 'Access values', self::MAX_VALUE_LENGTH, $max );
    }


    /**
     * Returns the access values granted to the user.
     *
     * @return array<int, string>
     */
    public function allowed( Authenticatable $user ) : array
    {
        $catalog = $this->catalog();

        if( isset( $this->allowed[$user] ) ) {
            return $this->allowed[$user];
        }

        $hooks = Hooks::get();
        $adapter = $hooks->access;
        $extra = [];

        foreach( $hooks->extend as $extend ) {
            $extra = [...$extra, ...( $extend( $user ) ?? [] )];
        }

        $granted = array_intersect_key( array_fill_keys( self::normalize( $extra ), true ), $catalog );

        if( $adapter->prepare ) {
            ( $adapter->prepare )( $user );
        }

        $resolved = $adapter->grants ? ( $adapter->grants )( $user ) : null;

        if( $resolved !== null )
        {
            // the grants callback returns the complete set of granted values
            $granted += array_fill_keys( self::normalize( $resolved ), true );
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
     * Stores the access adapter and resets the resolved service.
     */
    private static function configure( Adapter $adapter ) : void
    {
        Hooks::get()->access = $adapter;

        // synchronizes the CMS permissions with the catalog capabilities
        Permission::unregister( self::PERMISSIONS );

        if( $adapter->list )
        {
            Permission::register( array_keys( array_filter( [
                'access:view' => true,
                'access:add' => $adapter->add,
                'access:delete' => $adapter->delete,
                'user:access' => $adapter->userAccess,
            ] ) ) );
        }

        app()->forgetInstance( self::class );
    }


    /**
     * Clears all request-local catalog and grant caches.
     */
    private function refresh() : void
    {
        $this->catalog = null;
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
            $this->allowed = new \WeakMap(); // clears the access cache of all users
        }

        return $this->known( $result );
    }


    /**
     * Validates and normalizes one access value.
     */
    private static function value( string $value ) : string
    {
        return self::normalize( [$value] )[0];
    }
}
