<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\PageCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;


/**
 * Removes rendered pages before their cache TTL expires naturally.
 */
final class InvalidatePages implements ShouldQueue
{
    use Queueable;


    /**
     * @param list<array{domain: string, path: string}> $routes
     */
    public function __construct(
        public readonly array $routes,
        public readonly string $tenant,
    ) {
    }


    public function handle() : void
    {
        PageCache::invalidate( $this->routes, $this->tenant );
    }
}
