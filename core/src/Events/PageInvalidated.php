<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Requests invalidation of one rendered page route or a complete domain.
 */
final class PageInvalidated implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public readonly string $tenant;


    public function __construct(
        public readonly string $domain,
        public readonly ?string $path = null,
    ) {
        $this->tenant = Tenancy::value();
    }
}
