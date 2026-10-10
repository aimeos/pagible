<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Nestedset\DescendantsRelation;
use Aimeos\Nestedset\QueryBuilder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;


/**
 * Descendants relation which leaves out the sub-pages of disabled or missing pages
 *
 * The ancestors of the root pages are fetched by the same query and the hidden branches
 * are pruned while walking the pages in tree order. This avoids a correlated ancestor
 * scan for each returned page, which can't use an index since the status is stored in
 * the page variants.
 */
class SubtreeRelation extends DescendantsRelation
{
    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints(): void
    {
        if( !static::$constraints ) return;

        $this->addEagerConstraints( [$this->parent] );
        $this->query->applyNestedSetScope();
    }


    /**
     * Adds the descendants of the page and the page itself including its ancestors.
     *
     * @param QueryBuilder<Model> $query
     * @param Model $model
     */
    protected function addEagerConstraint( QueryBuilder $query, Model $model ): void
    {
        $query->orWhereDescendantOf( $model )->orWhereAncestorOf( $model, true );
    }


    /**
     * Get the results of the relationship.
     *
     * @return EloquentCollection<int, Model>
     */
    public function getResults(): EloquentCollection
    {
        return $this->prune( $this->query->get() )
            ->filter( fn( Model $related ) => $this->parent instanceof Model && $this->matches( $this->parent, $related ) )
            ->values();
    }


    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array<int, Model> $models
     * @param EloquentCollection<int, Model> $results
     * @param string $relation
     * @return array<int, Model>
     */
    public function match( array $models, EloquentCollection $results, $relation )
    {
        return parent::match( $models, $this->prune( $results ), $relation );
    }


    /**
     * Removes the disabled pages and all pages whose parent is disabled or not available.
     *
     * @param EloquentCollection<int, Model> $results Pages in tree order including the ancestors of the root pages
     * @return EloquentCollection<int, Model> Remaining pages in tree order
     */
    protected function prune( EloquentCollection $results ): EloquentCollection
    {
        $available = [];

        return $results->filter( function( Model $page ) use ( &$available ) {
            $parentId = $page->getAttribute( 'parent_id' );

            if( (int) $page->getAttribute( 'status' ) === 0 || $parentId !== null && !isset( $available[$parentId] ) ) {
                return false;
            }

            return $available[$page->getKey()] = true;
        } )->values();
    }
}
