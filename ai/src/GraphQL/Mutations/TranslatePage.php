<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Exception;
use Aimeos\Cms\GraphQL\Query;
use Aimeos\Cms\Jobs\TranslatePage as Job;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Illuminate\Support\Facades\Auth;
use Aimeos\Nestedset\NestedSet;


final class TranslatePage
{
    /**
     * Queues one translation job per page and language in tree order.
     *
     * The pages are passed by ID or by the filter of the page list. Only missing and stale
     * variants are translated, the pages in their source language and up to date variants
     * are skipped.
     *
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     * @return array{id: string, total: int}
     */
    public function __invoke( $rootValue, array $args ) : array
    {
        if( !isset( $args['id'] ) && !isset( $args['filter'] ) ) {
            throw new Exception( 'Pass the IDs of the pages or a filter' );
        }

        $user = Auth::user();
        $ids = isset( $args['id'] )
            ? Page::whereIn( 'id', array_unique( $args['id'] ) )->orderBy( NestedSet::LFT )->pluck( 'id' )->all()
            : $this->filtered( $args );

        return Job::dispatchPending( $ids, array_map( 'strval', $args['lang'] ), $user?->getAuthIdentifier(), Permission::can( 'page:add', $user ) );
    }


    /**
     * Returns the IDs of the pages matching the filter in tree order.
     *
     * The filter must not match more pages than allowed for bulk operations.
     *
     * @param  array<string, mixed>  $args
     * @return array<int, string> Page UUIDs
     */
    private function filtered( array $args ) : array
    {
        $query = new Query();
        $params = array_filter( [
            'filter' => (array) $args['filter'],
            'publish' => $args['publish'] ?? null,
            'lang' => $args['filter_lang'] ?? null,
        ], fn( $value ) => $value !== null );

        $search = $query->search( $params )->orderBy( NestedSet::LFT, 'asc' );
        $callback = $search->queryCallback;

        // only the IDs are needed (and the search keys to map the results)
        $search->query( function( $builder ) use ( $callback ) {
            if( $callback ) {
                $callback( $builder );
            }

            $builder->select( [$builder->qualifyColumn( 'id' ), $builder->qualifyColumn( $builder->getModel()->getScoutKeyName() )] );
        } );

        // the total is also reported by search engines limiting the number of returned hits
        $result = $search->paginate( Page::MAX_BULK, 'page', 1 );

        if( $result->total() > Page::MAX_BULK ) {
            throw new Exception( sprintf( 'The filter matches %1$d pages, no more than %2$d pages can be translated at once', $result->total(), Page::MAX_BULK ) );
        }

        return array_map( fn( $item ) => (string) $item->id, $result->items() );
    }
}
