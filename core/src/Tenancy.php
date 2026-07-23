<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Tenancy class for tenancy value lookups.
 */
class Tenancy
{
    /**
     * Anonymous callback which provides the value of the current tenant.
     */
    public static ?\Closure $callback = null;

    /**
     * Optional override for the user-to-tenant access check. When null, allows() compares the
     * user's tenant_id with the channel's tenant. Receives the authenticated user and tenant ID.
     *
     * @var (\Closure(?Authenticatable, string): bool)|null
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
        $this->id = $id;
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
     * Pagible runs on a single shared database (no multi-database tenancy), so the user must
     * belong to the tenant: by default the user's tenant_id must equal the channel's tenant.
     * Override Tenancy::$access for custom binding logic.
     *
     * @param ?Authenticatable $user Authenticated user (must expose a tenant_id)
     * @param string $tenant Tenant ID
     */
    public static function allows( ?Authenticatable $user, string $tenant ) : bool
    {
        if( ( $access = self::$access ) !== null ) {
            return $access( $user, $tenant );
        }

        $id = data_get( $user, 'tenant_id' );
        return is_string( $id ) && $id === $tenant;
    }


    /**
     * Sets up Pagible tenancy for stancl/tenancy.
     */
    public static function stancl() : void
    {
        /** @phpstan-ignore function.notFound */
        self::$callback = fn() : string => (string) tenant()?->getTenantKey();
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
}
