<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Access\Adapter;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Holds the callbacks, registries and caches of the CMS for the current application.
 *
 * The instance is bound to the application container, so the state lives as long as
 * the application and is reset together with it instead of leaking through static state.
 * Use the public configurators like Access::using(), Permission::register(), Schema::source()
 * or Tenancy::valueUsing() instead of the methods of this class.
 *
 * @internal
 */
final class Hooks
{
    /** Configured access adapter */
    public Adapter $access;

    /** @var array<int, string> Registered permission actions */
    public array $actions = Permission::ACTIONS;

    /** Custom permission check callback */
    public ?\Closure $can = null;

    /** @var (\Closure(Authenticatable): (iterable<mixed>|null))|null Callback adding grants from optional packages */
    public ?\Closure $extend = null;

    /** Whether the tenant lifecycle is managed by stancl/tenancy */
    public bool $managed = false;

    /** @var \WeakMap<object, array{key: string, resolved: array<int, string>}> Cache of resolved permissions per user */
    public \WeakMap $resolved;

    /** @var (\Closure(): array<string, array<string, mixed>>)|null Callback returning the dynamic themes */
    public ?\Closure $source = null;

    /** @var array<string, array{owner: object, callbacks: list<\Closure>}> Callbacks deferred until the storage lock is released */
    public array $storage = [];

    /** @var (\Closure(): string)|null Callback returning the ID of the current tenant */
    public ?\Closure $tenant = null;

    /** @var (\Closure(?Authenticatable, string): bool)|null Custom user-to-tenant access check */
    public ?\Closure $tenantAccess = null;

    /** @var array<string, array<string, mixed>> Registered themes */
    public array $themes = [];

    /** Whether calls without user skip the permission checks, see Permission::system() */
    public bool $system = false;

    /** @var array<string, array{ids?: array<string, bool>, langs?: array<string, bool>}> Page IDs and changed languages per tenant whose variants must be reindexed after the commit */
    public array $variants = [];


    /**
     * Initializes the hooks without access adapter.
     */
    private function __construct()
    {
        $this->access = new Adapter();
        $this->resolved = new \WeakMap();
    }


    /**
     * Returns the hooks of the current application.
     */
    public static function get() : self
    {
        $app = app();

        if( $app->bound( self::class ) ) {
            return $app->make( self::class );
        }

        $app->instance( self::class, $hooks = new self() );
        return $hooks;
    }
}
