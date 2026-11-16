<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Walks up the Joomla category tree.
 */
class JoomlaCategoryTree
{
    /**
     * Returns the first one of the category and its parents which passes the test.
     *
     * @param Collection<array-key, \stdClass> $categories Categories by ID
     * @param int $catId ID of the first category
     * @param \Closure(\stdClass): bool $test Tests the category
     * @return \stdClass|null Category or NULL if none passes the test
     */
    public static function firstAncestor( Collection $categories, int $catId, \Closure $test ): ?\stdClass
    {
        foreach( self::ancestors( $categories, $catId ) as $category )
        {
            if( $test( $category ) ) {
                return $category;
            }
        }

        return null;
    }


    /**
     * Returns the category and its parents and the ID where walking up the tree stopped.
     *
     * The walk stops at the first parent which isn't in the categories, which is the root category
     * for complete trees, or at the first category repeated if the parents are cyclic.
     *
     * @param Collection<array-key, \stdClass> $categories Categories by ID
     * @param int $catId ID of the first category
     * @return JoomlaLineage Categories by ID, the category first, and the ID where the walk stopped
     */
    public static function lineage( Collection $categories, int $catId ): JoomlaLineage
    {
        $path = [];
        $stop = $catId;

        foreach( self::ancestors( $categories, $catId ) as $id => $category )
        {
            $path[$id] = $category;
            $stop = (int) $category->parent_id;
        }

        return new JoomlaLineage( $path, $stop );
    }


    /**
     * Returns the category and its parents up to the first one which isn't in the categories.
     *
     * The root category isn't in the categories and categories which are their own parents are only returned once.
     *
     * @param Collection<array-key, \stdClass> $categories Categories by ID
     * @param int $catId ID of the first category
     * @return \Generator<int, \stdClass> Categories by ID
     */
    protected static function ancestors( Collection $categories, int $catId ): \Generator
    {
        $seen = [];

        while( !isset( $seen[$catId] ) && ( $category = $categories->get( $catId ) ) )
        {
            yield $catId => $category;

            $seen[$catId] = true;
            $catId = (int) $category->parent_id;
        }
    }
}
