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
final class PageInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $tenant;


    /** @param list<string> $paths */
    public function __construct(
        public readonly string $domain,
        public readonly array $paths,
    )
    {
        $this->tenant = Tenancy::value();
    }
}
