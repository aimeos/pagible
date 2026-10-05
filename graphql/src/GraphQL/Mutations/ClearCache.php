<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Resource;
use Aimeos\Nestedset\NestedSet;


final class ClearCache
{
    /**
     * @param null $rootValue
     * @param array{ids: string[]} $args
     */
    public function __invoke( $rootValue, array $args ) : int
    {
        if( empty( $ids = array_values( array_unique( $args['ids'] ?? [] ) ) ) ) {
            return 0;
        }

        $roots = Page::query()
            ->withTrashed()
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereIn( 'id', $ids )
            ->get();

        if( count( $ids ) !== $roots->count() ) {
            throw new \Aimeos\Cms\Exception( 'Page not found' );
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Page> $pages */
        $pages = Resource::pageSubtree( $roots )->get( ['domain', 'path'] );

        Resource::invalidatePages( $pages );

        return $pages->unique( fn( $page ) => $page->domain . '|' . $page->path )->count();
    }
}
