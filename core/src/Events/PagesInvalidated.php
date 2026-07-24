<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Requests invalidation of rendered page routes after commit.
 */
final class PagesInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $tenant;

    /** @var array<int, array{domain: string, path: string}> */
    public readonly array $routes;


    /**
     * @param array<int, array{domain: string, path: string}> $routes
     */
    public function __construct( array $routes )
    {
        $dedup = [];
        $this->tenant = Tenancy::value();

        foreach( $routes as $route )
        {
            $dedup[$route['domain'] . "\0" . $route['path']] = $route;
        }

        $this->routes = array_values( $dedup );
    }
}
