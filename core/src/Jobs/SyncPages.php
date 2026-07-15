<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Laravel\Scout\Traits\ConfiguresJobOptions;


/**
 * Reconciles external page-search documents with the current database state.
 */
final class SyncPages implements ShouldQueue
{
    use ConfiguresJobOptions;
    use Queueable;


    /**
     * @param list<string> $ids
     */
    public function __construct(
        public readonly array $ids,
        public readonly string $tenant,
    ) {
        $this->configureJob();
    }


    public function handle() : void
    {
        Tenancy::run( $this->tenant, fn() => $this->sync() );
    }


    private function sync() : void
    {
        $model = new Page();
        $pages = Page::makeAllSearchableQuery()
            ->whereKey( $this->ids )
            ->get();

        $model->syncMakeSearchable( $pages );

        $missing = array_values( array_diff(
            $this->ids,
            array_map( strval(...), $pages->modelKeys() ),
        ) );

        $model->syncRemoveFromSearch( $model->newCollection( array_map(
            fn( string $id ) => ( new Page() )->forceFill( ['id' => $id, 'tenant_id' => $this->tenant] ),
            $missing,
        ) ) );
    }
}
