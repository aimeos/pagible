<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Query;

use Aimeos\Nestedset\QueryBuilder;


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
     * Uses the variant of the given language or the source variant for pages without that variant.
     *
     * @param string $lang Language code
     * @param bool $trashed Include a soft-deleted variant of the language
     * @return static Same builder for fluent interface
     */
    public function fallback( string $lang, bool $trashed = false ) : static
    {
        $this->base()->variants( 'fallback', $lang, $trashed );
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
     * Uses the variant of the given language if it's published and enabled, else the source variant.
     *
     * Pages whose variant in that language is missing, unpublished or disabled are returned
     * with their source variant, so the "lang" column tells if the variant is a fallback.
     *
     * @param string $lang Language code
     * @return static Same builder for fluent interface
     */
    public function visible( string $lang ) : static
    {
        $this->base()->variants( 'visible', $lang );
        return $this;
    }


    /**
     * Uses the variant of the given language editors or visitors see.
     *
     * @param string $lang Language code
     * @param bool $editor TRUE for editors who see unpublished variants, FALSE for visitors
     * @return static Same builder for fluent interface
     */
    public function localized( string $lang, bool $editor ) : static
    {
        return $editor ? $this->fallback( $lang ) : $this->visible( $lang );
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
     * Models of the page table, e.g. Page and Nav, read from the page view
     * and keep the selected variants.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @return $this
     */
    public function setModel( \Illuminate\Database\Eloquent\Model $model )
    {
        $variant = $this->query instanceof PageBuilder ? $this->query->variant : null;

        parent::setModel( $model );

        if( $this->query instanceof PageBuilder && $model->getTable() === PageBuilder::ALIAS ) {
            $this->query->variants( ...( $variant ?? ['source'] ) );
        }

        return $this;
    }


    /**
     * Add subselect queries to include an aggregate value for a relationship.
     *
     * The page view can't be qualified by its aliased FROM clause.
     *
     * @param mixed $relations
     * @param \Illuminate\Contracts\Database\Query\Expression|string $column
     * @param string|null $function
     * @return $this
     */
    public function withAggregate( $relations, $column, $function = null )
    {
        if( !empty( $relations ) && $this->query->columns === null && $this->query->from !== $this->getModel()->getTable() ) {
            $this->query->select( [$this->getModel()->getTable() . '.*'] );
        }

        return parent::withAggregate( $relations, $column, $function );
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
     * The structure queries of the nested set use the alias of the page view.
     */
    protected function wrappedTable() : string
    {
        return $this->query->getGrammar()->wrapTable( $this->getModel()->getTable() );
    }
}
