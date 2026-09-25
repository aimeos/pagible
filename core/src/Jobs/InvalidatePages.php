<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Resource;
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

    public int $tries = 3;


    /**
     * @param string $tenant Tenant ID
     * @param array<string> $ids Page UUIDs
     */
    public function __construct( public string $tenant, public array $ids )
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
    }


    /**
     * Invalidates the pages of the tenant.
     */
    public function handle(): void
    {
        Tenancy::run( $this->tenant, fn() => Resource::invalidateIds( $this->ids ) );
    }
}
