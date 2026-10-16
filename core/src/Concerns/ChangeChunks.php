<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\DB;
use Aimeos\Cms\Models\Base;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;


/**
 * Locks and changes elements or files in chunks.
 */
trait ChangeChunks
{
    /**
     * Locks and changes the items in chunks of 100 within one transaction.
     *
     * The items are locked in the order of their IDs to avoid deadlocks with concurrent requests.
     *
     * @template T of Base
     * @param class-string<T> $model Element or File class
     * @param array<string> $ids Item IDs
     * @param array<string> $fields Requested response fields, all columns if empty
     * @param array<string> $columns Columns required by the change in addition to the requested fields
     * @param \Closure(EloquentCollection<int, T>): void $change Changes the locked items of one chunk
     * @return Collection<int, T> Changed items
     */
    protected function change( string $model, array $ids, array $fields, array $columns, \Closure $change ) : Collection
    {
        sort( $ids, SORT_STRING );

        return DB::transaction( fn() => collect( $ids )->chunk( 100 )->reduce( function( Collection $result, Collection $chunk ) use ( $model, $fields, $columns, $change ) {
            $query = $model::withTrashed()->whereIn( 'id', $chunk->all() );

            if( $fields )
            {
                $instance = new $model();
                $response = [...$instance->getVisible(), 'editor', 'created_at', 'updated_at'];

                $query->select( array_values( array_unique( [
                    ...$instance->qualifyColumns( ['id', 'tenant_id', 'latest_id', 'deleted_at', ...$columns] ),
                    ...array_intersect( $response, $fields ),
                ] ) ) );
            }

            /** @var EloquentCollection<int, T> $items */
            $items = $query->orderBy( 'id' )->lockForUpdate()->get();

            if( $items->isNotEmpty() ) {
                $change( $items );
            }

            return $result->concat( $items );
        }, collect() ) );
    }
}
