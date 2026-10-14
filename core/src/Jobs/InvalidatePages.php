<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


/**
 * Invalidates the pages using changed shared elements or files.
 */
class InvalidatePages implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use RoutesQueue;

    public int $tries = 3;


    /**
     * @param string $tenant Tenant ID
     * @param array<string> $ids Page variant UUIDs
     */
    public function __construct( public string $tenant, public array $ids )
    {
        $this->routeQueue();
    }


    /**
     * Invalidates the pages of the tenant.
     */
    public function handle(): void
    {
        Tenancy::run( $this->tenant, fn() => Invalidation::pages(
            PageView::allVariants()->whereIn( 'variant_id', $this->ids )->select( 'id', 'domain', 'path' )->get()
        ) );
    }
}
