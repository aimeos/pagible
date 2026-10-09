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
    /** @var int Number of pages fetched at once when resolving the filter */
    private const SIZE = 500;


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

        $ids = [];
        $page = 1;

        do
        {
            $result = $query->search( $params )->orderBy( NestedSet::LFT, 'asc' )->paginate( self::SIZE, 'page', $page );

            foreach( $result->items() as $item ) {
                $ids[] = (string) $item->id;
            }
        }
        while( $page++ < $result->lastPage() );

        return $ids;
    }
}
