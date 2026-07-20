<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravel\Scout\Traits\ConfiguresJobOptions;


/**
 * Reconciles search documents with the current database state.
 */
final class SyncIndex implements ShouldQueue
{
    use ConfiguresJobOptions;
    use Queueable;


    /**
     * @param class-string<Element>|class-string<File>|class-string<Page> $model
     * @param list<string> $ids
     */
    public function __construct(
        public readonly string $model,
        public readonly array $ids,
        public readonly string $tenant,
        public readonly bool $trashed = false,
    ) {
        $this->configureJob();
    }


    public function handle() : void
    {
        Tenancy::run( $this->tenant, fn() => $this->sync() );
    }


    private function model() : Element|File|Page
    {
        if( !in_array( $this->model, [Element::class, File::class, Page::class], true ) ) {
            throw new \InvalidArgumentException( 'Invalid CMS search index model: ' . $this->model );
        }

        $class = $this->model;
        return new $class();
    }


    private function sync() : void
    {
        $model = $this->model();
        $items = $model::makeAllSearchableQuery()
            ->when( $this->trashed, fn( $query ) => $query->withoutGlobalScope( SoftDeletingScope::class ) )
            ->whereKey( $this->ids )
            ->get();

        $model->syncMakeSearchable( $items );

        $missing = array_values( array_diff(
            $this->ids,
            array_map( strval(...), $items->modelKeys() ),
        ) );

        $model->syncRemoveFromSearch( $model->newCollection( array_map(
            fn( string $id ) => $model->newInstance()->forceFill( [
                'id' => $id,
                'tenant_id' => $this->tenant,
            ] ),
            $missing,
        ) ) );
    }
}
