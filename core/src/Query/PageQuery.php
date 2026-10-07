<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Nestedset\QueryBuilder;
use Illuminate\Database\Query\Expression;


/**
 * Eloquent query builder for the page facade
 *
 * Selects which page variant is joined to the page structure. Without one of
 * the methods, the variant of the source language is used.
 *
 * @template TModel of \Aimeos\Cms\Models\Page
 * @extends QueryBuilder<TModel>
 */
class PageQuery extends QueryBuilder
{
    /**
     * Force a delete on a set of soft deleted models.
     *
     * Eloquent skips the global scopes when force deleting, so the descendants
     * of a purged page would be deleted in all tenants. Applies all scopes
     * except the soft delete scope to purge trashed descendants too.
     *
     * @return int Number of deleted pages
     */
    public function forceDelete()
    {
        return $this->withoutGlobalScope( \Illuminate\Database\Eloquent\SoftDeletingScope::class )->toBase()->delete();
    }


    /**
     * Uses the variants of all languages, so pages are returned once per language.
     *
     * @param bool $trashed Include soft-deleted variants
     * @return static Same builder for fluent interface
     */
    public function allVariants( bool $trashed = false ) : static
    {
        $this->base()->variants( 'all', null, $trashed );
        return $this;
    }


    /**
     * Uses the variant of the given language, pages without that variant are excluded.
     *
     * @param string|null $lang Language code or NULL for the source language
     * @param bool $trashed Include a soft-deleted variant
     * @return static Same builder for fluent interface
     */
    public function language( ?string $lang, bool $trashed = false ) : static
    {
        $lang === null
            ? $this->base()->variants( 'source' )
            : $this->base()->variants( 'lang', $lang, $trashed );

        return $this;
    }


    /**
     * Uses the variant of the source language (default).
     *
     * @return static Same builder for fluent interface
     */
    public function sourceVariant() : static
    {
        $this->base()->variants( 'source' );
        return $this;
    }


    /**
     * Uses the variant with the given ID.
     *
     * @param string $id Variant ID
     * @return static Same builder for fluent interface
     */
    public function variant( string $id ) : static
    {
        $this->base()->variants( 'variant', $id );
        return $this;
    }


    /**
     * Set a model instance for the model being queried.
     *
     * Keeps the derived page table if the new model uses the same table, e.g. Nav.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return $this
     */
    public function setModel( \Illuminate\Database\Eloquent\Model $model )
    {
        $from = $this->query->from;
        $bindings = $this->query->bindings['from'];

        parent::setModel( $model );

        if( $from instanceof Expression && $model->getTable() === $this->query->from ) {
            $this->query->from = $from;
            $this->query->bindings['from'] = $bindings;
        }

        return $this;
    }


    /**
     * Add subselect queries to include an aggregate value for a relationship.
     *
     * The derived page table can't be qualified by its FROM expression.
     *
     * @param mixed $relations
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string|null $function
     * @return $this
     */
    public function withAggregate( $relations, $column, $function = null )
    {
        if( !empty( $relations ) && $this->query->columns === null && $this->query->from instanceof Expression ) {
            $this->query->select( [$this->getModel()->getTable() . '.*'] );
        }

        return parent::withAggregate( $relations, $column, $function );
    }


    /**
     * Add the "updated at" column to an array of values.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected function addUpdatedAtColumn( array $values )
    {
        $from = $this->query->from;

        if( !$from instanceof Expression ) {
            return parent::addUpdatedAtColumn( $values );
        }

        try {
            $this->query->from = $this->getModel()->getTable();
            return parent::addUpdatedAtColumn( $values );
        } finally {
            $this->query->from = $from;
        }
    }


    /**
     * Returns the base query builder.
     */
    protected function base() : PageBuilder
    {
        $query = $this->getQuery();

        if( !$query instanceof PageBuilder ) {
            throw new \LogicException( 'Page queries require the page query builder' );
        }

        return $query;
    }


    /**
     * Get a wrapped table name.
     *
     * The structure queries of the nested set use the real page table.
     */
    protected function wrappedTable() : string
    {
        return $this->query->getGrammar()->wrapTable( $this->getModel()->getTable() );
    }
}
