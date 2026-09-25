<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Announces managed storage paths which aren't served from the public disk any more after commit.
 */
final class FilesRemoved implements ShouldDispatchAfterCommit
{
    use Dispatchable;


    /**
     * @param string $tenant Tenant ID owning the storage namespace
     * @param list<string> $paths Storage paths relative to the disk root
     */
    public function __construct(
        public readonly string $tenant,
        public readonly array $paths,
    )
    {
    }
}
