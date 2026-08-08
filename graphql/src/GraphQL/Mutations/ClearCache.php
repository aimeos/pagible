<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\Page;
use Aimeos\Nestedset\NestedSet;


final class ClearCache
{
    /**
     * @param null $rootValue
     * @param array{ids: string[]} $args
     */
    public function __invoke( $rootValue, array $args ) : int
    {
        $ids = array_values( array_unique( $args['ids'] ?? [] ) );

        $roots = Page::query()
            ->withTrashed()
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereIn( 'id', $ids )
            ->get();

        if (count( $ids ) !== $roots->count()) {
            abort( 404 );
        }

        $pages = collect();

        foreach( $roots as $root ) {
            $left = (int) $root->getAttribute( NestedSet::LFT );
            $right = (int) $root->getAttribute( NestedSet::RGT );

            $pages = $pages->merge( Page::query()
                ->withTrashed()
                ->whereBetween( NestedSet::LFT, [$left, $right] )
                ->get( ['domain', 'path'] )
            );
        }

        $pages = $pages->unique( fn( $page ) => $page->getAttribute( 'domain' ) . '|' . $page->getAttribute( 'path' ) );
        $paths = [];

        foreach( $pages as $page ) {
            $domain = (string) $page->getAttribute( 'domain' );
            $paths[$domain][] = (string) $page->getAttribute( 'path' );
        }

        foreach( $paths as $domain => $items ) {
            PageInvalidated::dispatch( (string) $domain, $items );
        }

        return $pages->count();
    }
}
