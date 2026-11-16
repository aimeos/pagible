<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Joomla category and its parents up to where walking up the category tree stopped.
 *
 * Returned by JoomlaCategoryTree::lineage().
 */
final readonly class JoomlaLineage
{
    /**
     * Initializes the object.
     *
     * @param array<int, \stdClass> $categories Category and its parents by ID, the category first
     * @param int $stop ID of the first unknown parent like the root or, if the parents are cyclic, of the first category
     *  which would be repeated, which is in the categories then and neither unknown nor the root
     */
    public function __construct(
        public array $categories,
        public int $stop
    ) {
    }


    /**
     * Returns the IDs of the category and its parents.
     *
     * @return list<int> Category IDs, the category first
     */
    public function ids(): array
    {
        return array_keys( $this->categories );
    }


    /**
     * Returns the IDs of the category and its parents including the ID where the walk stopped.
     *
     * The ID where the walk stopped is the root category for complete trees or the first parent which isn't known.
     * For cyclic parents, the ID where the walk stopped is already one of the categories and isn't added again,
     * so each ID is contained only once.
     *
     * @return list<int> Category IDs, the category first
     */
    public function pathIds(): array
    {
        $ids = $this->ids();
        return isset( $this->categories[$this->stop] ) ? $ids : [...$ids, $this->stop];
    }


    /**
     * Returns the IDs of the category and its parents if the walk up the category tree reached the root category.
     *
     * @return list<int> Category IDs, the category first, or an empty array if the root category isn't reached
     */
    public function rootIds(): array
    {
        return $this->reachesRoot() ? $this->ids() : [];
    }


    /**
     * Tests if the walk up the category tree reached the root category.
     */
    public function reachesRoot(): bool
    {
        return JoomlaRow::isRootId( $this->stop );
    }
}
