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
     * @param array{id: string} $args
     */
    public function __invoke( $rootValue, array $args ) : int
    {
        $root = Page::query()
            ->withTrashed()
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->findOrFail( $args['id'] );

        $pages = Page::query()
            ->withTrashed()
            ->whereBetween( NestedSet::LFT, [$root->getLft(), $root->getRgt()] )
            ->get( ['domain', 'path'] );
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
