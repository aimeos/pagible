<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Categories the Joomla router uses and the IDs of their parents, shared by the routes of all languages.
 */
class JoomlaRouteCategories
{
    /** @var array<int, list<int>> IDs of the routed categories and their parents by category ID, empty for categories which aren't routed */
    protected array $lineages = [];


    /**
     * Initializes the object.
     *
     * Only categories Joomla routes are passed, which are published and guests can see.
     *
     * @param Collection<array-key, \stdClass> $categories Categories by ID
     */
    public function __construct( protected Collection $categories )
    {
    }


    /**
     * Returns the routed category.
     *
     * @return \stdClass|null Category or NULL if it isn't routed
     */
    public function get( int $catId ): ?\stdClass
    {
        return $this->categories->get( $catId );
    }


    /**
     * Returns the IDs of the category and its parent categories, the category first.
     *
     * @return list<int> Category IDs or an empty array if a category below the root category isn't routed
     */
    public function lineage( int $catId ): array
    {
        // the root category isn't in the categories
        return $this->lineages[$catId] ??= JoomlaCategoryTree::lineage( $this->categories, $catId )->rootIds();
    }


    /**
     * Returns the IDs of the categories below the first one matching the condition.
     *
     * @param list<int> $lineage IDs of the category and its parents, the category first
     * @param \Closure(int): bool $top Returns TRUE for the category whose children are returned
     * @return list<int> IDs of the categories below, the category first, or all IDs if none matches
     */
    public static function below( array $lineage, \Closure $top ): array
    {
        $ids = [];

        foreach( $lineage as $id )
        {
            if( $top( $id ) ) {
                break;
            }

            $ids[] = $id;
        }

        return $ids;
    }
}
