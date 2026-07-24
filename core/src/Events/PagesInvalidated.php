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

    /** @var list<array{domain: string, path: string}> */
    public readonly array $routes;


    /**
     * @param list<array{domain: string, path: string}> $routes
     */
    public function __construct( array $routes )
    {
        $dedup = [];
        $this->tenant = Tenancy::value();

        foreach( $routes as $route )
        {
            $domain = (string) $route['domain'];
            $path = (string) $route['path'];
            $dedup[$domain . "\0" . $path] = [
                'domain' => $domain,
                'path' => $path,
            ];
        }

        $this->routes = array_values( $dedup );
    }
}
