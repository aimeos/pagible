<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Illuminate\Database\Eloquent\Model;


/**
 * Prunes the pages which aren't shown from page trees.
 */
final class NavTree
{
    /**
     * Removes the pages which aren't shown and the pages whose parent is hidden or not available.
     *
     * The pages are walked in tree order. Sub-pages of skipped pages get the nearest shown
     * ancestor as parent ID, so building the tree from the remaining pages nests them in
     * place of the skipped page.
     *
     * @template TModel of Model
     * @param iterable<TModel> $pages Pages in tree order
     * @param \Closure(TModel): NavState $state Returns the navigation state of the page
     * @param array<int|string, int|string|null> $available Parent IDs of the available pages which aren't part of the list by page ID
     * @return list<TModel> Remaining pages in tree order
     */
    public static function prune( iterable $pages, \Closure $state, array $available = [] ) : array
    {
        $result = [];

        foreach( $pages as $page )
        {
            $parentId = $page->getAttribute( 'parent_id' );

            if( $parentId !== null )
            {
                if( !array_key_exists( $parentId, $available ) ) {
                    continue;
                }

                // sub-pages of skipped pages are nested in place of the skipped page
                if( ( $nearest = $available[$parentId] ) !== $parentId ) {
                    $page->setAttribute( 'parent_id', $nearest )->syncOriginalAttribute( 'parent_id' );
                }
            }

            $value = $state( $page );

            if( $value === NavState::Show ) {
                $available[$page->getKey()] = $page->getKey();
                $result[] = $page;
            } elseif( $value === NavState::Skip ) {
                $available[$page->getKey()] = $page->getAttribute( 'parent_id' );
            }
        }

        return $result;
    }
}
