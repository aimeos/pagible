<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;


/**
 * Tenancy class for tenancy value lookups.
 */
class Tenancy
{
    /**
     * Anonymous callback which provides the value of the current tenant.
     *
     * @deprecated Use Tenancy::valueUsing() instead, the property will be removed in a future release
     */
    public static ?\Closure $callback = null;

    /**
     * Optional override for the user-to-tenant access check.
     *
     * @var (\Closure(?Authenticatable, string): bool)|null
     * @deprecated Use Tenancy::allowsUsing() instead, the property will be removed in a future release
     */
    public static ?\Closure $access = null;

    /**
     * Current tenant value.
     */
    private string $id;


    /**
     * Creates a new tenancy instance with the given tenant ID.
     *
     * @param string $id Tenant ID
     */
    public function __construct( string $id )
    {
        $this->id = self::check( $id );
    }


    /**
     * Validates a tenant ID for database and storage namespace use.
     *
     * The empty ID denotes the default tenant. Named tenants must not overlap
     * the default tenant's UUID-owned storage directories. Named IDs are
     * bounded URL-safe names because they are also used in storage paths.
     * Dots may separate non-empty name parts, allowing domain names.
     *
     * @param string $id Candidate tenant ID
     * @return string Validated tenant ID
     * @throws \InvalidArgumentException If the ID is unsafe, too long, or overlaps a default-tenant UUID directory
     */
    public static function check( string $id ) : string
    {
        if( $id && ( preg_match( '/\A(?=.{1,100}\z)[A-Za-z0-9_-]+(?:\.[A-Za-z0-9_-]+)*\z/', $id ) !== 1 || Str::isUuid( $id ) ) ) {
            throw new \InvalidArgumentException( 'Invalid tenant ID' );
        }

        return $id;
    }


    /**
     * Returns the tenant ID of this instance.
     *
     * @return string Tenant ID
     */
    public function id() : string
    {
        return $this->id;
    }


    /**
     * Returns whether the user may access the given tenant.
     *
     * Without tenancy configuration, any authenticated user belongs to the single CMS tenant.
     * Otherwise, the user's tenant_id must equal the current tenant by default. Use
     * Tenancy::allowsUsing() for custom binding logic.
     *
     * @param ?Authenticatable $user Authenticated user (must expose a tenant_id when tenancy is configured)
     * @param string $tenant Tenant ID
     */
    public static function allows( ?Authenticatable $user, string $tenant ) : bool
    {
        if( !$user || ( $tenant === '' && self::configured() ) ) {
            return false;
        }

        if( ( $access = Hooks::get()->tenantAccess ?? self::$access ) !== null ) {
            return $access( $user, $tenant );
        }

        if( $tenant === '' ) {
            return true;
        }

        $id = data_get( $user, 'tenant_id' );

        return is_string( $id ) && $id === $tenant;
    }


    /**
     * Sets the custom user-to-tenant access check used by allows().
     *
     * The callback receives the authenticated user and the tenant ID and returns whether the user
     * may access the tenant. NULL restores the default check comparing the user's tenant_id.
     *
     * @param (\Closure(?Authenticatable, string): bool)|null $callback Access check or NULL for the default
     */
    public static function allowsUsing( ?\Closure $callback ) : void
    {
        Hooks::get()->tenantAccess = $callback;
    }


    /**
     * Returns whether a callback providing the current tenant is configured.
     *
     * @return bool TRUE for multi-tenant setups, FALSE if tenancy isn't configured at all
     */
    public static function configured() : bool
    {
        return self::callback() !== null;
    }


    /**
     * Returns the tenant ID provided by the configured callback.
     *
     * @return string Tenant ID or an empty string if no tenancy is configured
     * @internal Use value() to get the ID of the current tenant
     */
    public static function resolve() : string
    {
        return ( $callback = self::callback() ) ? $callback() : '';
    }


    /**
     * Runs a callback in a transaction with the user row locked and the tenant re-checked.
     *
     * @template T
     * @param Authenticatable&Model $user Persisted user model
     * @param string $tenant Tenant ID the user must belong to
     * @param string $error Exception message if the locked user isn't in the tenant
     * @param \Closure(Authenticatable&Model):T $callback Receives the locked user
     * @return T
     */
    public static function lock( Authenticatable&Model $user, string $tenant, string $error, \Closure $callback ) : mixed
    {
        return $user->getConnection()->transaction( function() use ( $callback, $error, $tenant, $user ) {
            $locked = $user->newQuery()->whereKey( $user->getKey() )->lockForUpdate()->firstOrFail();

            if( !self::allows( $locked, $tenant ) ) {
                throw new Exception( $error );
            }

            return $callback( $locked );
        } );
    }


    /**
     * Sets up Pagible tenancy for stancl/tenancy.
     */
    public static function stancl() : void
    {
        $initializing = 'Stancl\\Tenancy\\Events\\InitializingTenancy';
        $ended = 'Stancl\\Tenancy\\Events\\TenancyEnded';
        $contract = 'Stancl\\Tenancy\\Contracts\\Tenant';

        if( !class_exists( $initializing ) ) {
            throw new \LogicException( 'stancl/tenancy must be installed before calling Tenancy::stancl().' );
        }

        $hooks = Hooks::get();

        if( $hooks->managed ) {
            return;
        }

        $hooks->managed = true;

        // same as the tenant() helper of stancl/tenancy
        self::valueUsing( function() use ( $contract ) : string {
            $tenant = app()->bound( $contract ) ? app( $contract ) : null;
            return is_object( $tenant ) && method_exists( $tenant, 'getTenantKey' ) ? (string) $tenant->getTenantKey() : '';
        } );

        Event::listen( $initializing, function( object $event ) {
            $tenant = data_get( $event, 'tenancy.tenant' );

            if( !is_object( $tenant ) || !method_exists( $tenant, 'getTenantKey' ) ) {
                throw new \LogicException( 'Stancl initialization event contains no valid tenant.' );
            }

            self::set( (string) $tenant->getTenantKey() );
        } );

        Event::listen( $ended, fn() => self::set( '' ) );
    }


    /**
     * Returns whether the tenant lifecycle is managed by stancl/tenancy.
     *
     * In managed mode, operations can only run in the tenant context initialized by Stancl.
     */
    public static function managed() : bool
    {
        return Hooks::get()->managed;
    }


    /**
     * Runs an operation in the requested tenant context.
     *
     * Stancl owns its tenant lifecycle and must have initialized the context before
     * the operation starts. Generic integrations are switched temporarily and restored.
     *
     * @template T
     * @param \Closure():T $callback
     * @return T
     */
    public static function run( string $id, \Closure $callback ) : mixed
    {
        $previous = self::value();

        if( $previous === $id ) {
            return $callback();
        }

        if( self::managed() ) {
            throw new \LogicException( 'Operation was not initialized in its tenant context.' );
        }

        self::set( $id );

        try {
            return $callback();
        } finally {
            self::set( $previous );
        }
    }


    /**
     * Replaces the current tenant.
     */
    public static function set( string $id ) : void
    {
        app()->instance( self::class, new self( $id ) );
    }


    /**
     * Returns the value for the tenant column in the models.
     *
     * @return string ID of the current tenant
     */
    public static function value() : string
    {
        return app( self::class )->id();
    }


    /**
     * Sets the callback which provides the ID of the current tenant.
     *
     * The current tenant is resolved again from the new callback. NULL disables tenancy, so
     * all content belongs to the single default tenant.
     *
     * @param (\Closure(): string)|null $callback Callback returning the tenant ID or NULL to disable tenancy
     */
    public static function valueUsing( ?\Closure $callback ) : void
    {
        Hooks::get()->tenant = $callback;
        app()->forgetInstance( self::class );
    }


    /**
     * Returns the configured callback which provides the ID of the current tenant.
     *
     * @return (\Closure(): string)|null Tenant callback or NULL if tenancy isn't configured
     */
    private static function callback() : ?\Closure
    {
        return Hooks::get()->tenant ?? self::$callback;
    }
}
