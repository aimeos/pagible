<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Events;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Requests synchronous invalidation of rendered representations after commit.
 *
 * Listeners protecting frontend visibility must remain synchronous and must not
 * implement ShouldQueue.
 */
final class PagesInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $tenant;

    /** @var list<array{domain: string, path: string}> */
    public readonly array $routes;


    /**
     * @param list<Page> $pages
     */
    public function __construct( array $pages )
    {
        $routes = [];
        $this->tenant = Tenancy::value();

        foreach( $pages as $page )
        {
            $domain = (string) $page->domain;
            $path = (string) $page->path;
            $routes[$domain . "\0" . $path] = [
                'domain' => $domain,
                'path' => $path,
            ];
        }

        $this->routes = array_values( $routes );
    }
}
