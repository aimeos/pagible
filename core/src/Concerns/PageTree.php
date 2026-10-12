<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageQuery;
use Aimeos\Cms\Query\SubtreeRelation;
use Aimeos\Nestedset\AncestorsRelation;
use Aimeos\Nestedset\DescendantsRelation;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;


/**
 * Page tree and navigation of the page view
 *
 * Relations to the ancestors, parent, children and sub-pages, localized to the
 * language of the page. The tree structure itself is written by the Page model.
 *
 * Overrides methods of the nested set NodeTrait, which must be resolved by "insteadof"
 * in the model because NestedSet::isNode() requires NodeTrait at the model class.
 */
trait PageTree
{
    /**
     * Ancestors and self collection cache for performance reasons.
     *
     * @var Collection<int, PageView>|null
     */
    protected ?Collection $cachedAncestorsAndSelf = null;


    /**
     * Returns a query builder for the full page tree including disabled and trashed pages.
     *
     * @param string|null $id Root page ID or null for all root pages
     * @return \Aimeos\Nestedset\QueryBuilder<Nav> Query builder for further chaining, call ->get()->toTree() to execute
     */
    public static function tree( ?string $id = null ) : \Aimeos\Nestedset\QueryBuilder
    {
        $root = $id ? static::withTrashed()->findOrFail( $id ) : null;
        $maxDepth = ( $root?->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 );

        $lft = NestedSet::LFT;
        $rgt = NestedSet::RGT;
        $depth = NestedSet::DEPTH;

        $builder = Nav::withTrashed()
            ->select( 'id', 'tenant_id', 'parent_id', 'name', 'title', 'tag', 'type', 'path', 'domain', 'lang', 'source', 'to', 'status', 'latest_id', $lft, $rgt, $depth )
            ->orderBy( $lft );

        if( $root ) {
            $builder->where( $lft, '>=', $root->getLft() )
                ->where( $rgt, '<=', $root->getRgt() )
                ->whereIn( $depth, range( (int) $root->getDepth(), $maxDepth ) );
        } else {
            $builder->whereIn( $depth, range( 0, $maxDepth ) );
        }

        return $builder;
    }


    /**
     * Get query ancestors of the node.
     *
     * @return  AncestorsRelation
     */
    public function ancestors() : AncestorsRelation
    {
        $builder = $this->newScopedQuery()
            ->select( Nav::SELECT_COLUMNS )
            ->setModel( new Nav() )
            ->defaultOrder();

        // ancestors provide the inherited config, so they fall back to the source variant in both modes
        $this->localize( $builder, false );
        return new AncestorsRelation( $builder, $this );
    }


    /**
     * Relation to children.
     *
     * @return HasMany<Nav, $this>
     */
    public function children() : HasMany
    {
        $relation = $this->hasMany( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )
            ->setModel( new Nav() )
            ->defaultOrder();

        $this->localize( $relation->getQuery() );
        return $relation;
    }


    /**
     * Returns ancestors including self (root→self), cached per instance.
     *
     * @return Collection<int, PageView>
     */
    public function getAncestorsAndSelfAttribute() : Collection
    {
        return $this->cachedAncestorsAndSelf ??= collect( $this->ancestors )->push( $this );
    }


    /**
     * Returns the number of descendants below this node.
     *
     * Derived from the nested set bounds without a query: the range [lft, rgt] spans the node
     * and all its descendants at two slots each, so the count excludes the node itself. Zero for
     * a leaf, so it still reads as "no children" where a boolean was expected, while a recursive
     * bulk edit can size itself ("apply to N pages") from it. An unsaved node (null bounds) has no
     * descendants - the ?? 0 keeps that case from doing null arithmetic.
     *
     * @return int Number of descendant pages
     */
    public function getHasAttribute() : int
    {
        return intdiv( ( $this->getRgt() ?? 0 ) - ( $this->getLft() ?? 0 ) - 1, 2 );
    }


    /**
     * Get the menu for the page.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function menu() : DescendantsRelation
    {
        return ( $this->ancestors->first() ?? $this )->subtree();
    }


    /**
     * Relation to the parent.
     *
     * @return BelongsTo<Nav, $this>
     */
    public function parent() : BelongsTo
    {
        $relation = $this->belongsTo( Nav::class, $this->getParentIdName() )
            ->select( Nav::SELECT_COLUMNS )->setModel( new Nav() );

        // the parent page always exists, so it falls back to the source variant in both modes
        $this->localize( $relation->getQuery(), false );
        return $relation;
    }


    /**
     * Create a new instance of the given model.
     *
     * Relations are eager loaded from a new instance of the query model, so a language
     * set at the query model (e.g. by the JSON:API "lang" filter) is passed to the new
     * instance for localizing the navigation relations.
     *
     * @param array<string, mixed> $attributes
     * @param bool $exists
     * @return static
     */
    public function newInstance( $attributes = [], $exists = false )
    {
        $model = parent::newInstance( $attributes, $exists );
        $lang = $this->attributes['lang'] ?? '';

        if( !$this->exists && !$exists && $lang !== '' && ( $model->attributes['lang'] ?? '' ) === '' ) {
            $model->attributes['lang'] = $lang;
        }

        return $model;
    }


    /**
     * Get query for the complete sub-tree up to three levels.
     *
     * @return DescendantsRelation Eloquent relationship to the descendants of the page
     */
    public function subtree() : DescendantsRelation
    {
        $depth = $this->getDepthName();

        // restrict maximum depth to three levels for performance reasons
        $maxDepth = ( $this->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 );

        $builder = $this->newScopedQuery()
            ->select( Nav::SELECT_COLUMNS )
            ->whereIn( $depth, range( 0, $maxDepth ) )
            ->defaultOrder();

        // sub-pages of disabled pages and, when hiding untranslated pages, of pages
        // without a variant in that language are pruned by the relation
        $this->localize( $builder );

        if( \Aimeos\Cms\Permission::can( 'page:view', Auth::user() ) ) {
            $builder->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return new SubtreeRelation( $builder->setModel( new Nav() ), $this );
    }


    /**
     * Tests if pages without a visible variant in the current language are shown in their source language.
     *
     * @return bool TRUE for the "source" fallback, FALSE if such pages are hidden
     */
    public static function fallbackToSource() : bool
    {
        return config( 'cms.translate.fallback', 'hide' ) === 'source';
    }


    /**
     * Uses the variants in the language of this page for a navigation query.
     *
     * Pages without a visible variant in that language are left out or replaced by their
     * source variant depending on the "cms.translate.fallback" setting. Editors see
     * unpublished variants too. Queries of models without a language, e.g. when eager
     * loading relations, keep using the source variants.
     *
     * @param \Illuminate\Database\Eloquent\Builder<*> $builder Page or navigation query
     * @param bool $hide Leave out pages without variant in "hide" mode, FALSE always falls back to the source variant
     * @return bool TRUE if the query has been restricted to the language, FALSE if not
     */
    public function localize( $builder, bool $hide = true ) : bool
    {
        $lang = $this->attributes['lang'] ?? null;

        if( !is_string( $lang ) || $lang === '' || !$builder instanceof PageQuery ) {
            return false;
        }

        $editor = \Aimeos\Cms\Permission::can( 'page:view', Auth::user() );

        // disabled variants are left out for visitors like in the "source" mode
        $hide && !self::fallbackToSource()
            ? $builder->language( $lang )->when( !$editor, fn( $q ) => $q->where( $q->qualifyColumn( 'status' ), '<>', 0 ) )
            : $builder->localized( $lang, $editor );

        return true;
    }


    /**
     * Returns the query for the structure of the page tree.
     *
     * The nested set writes the page tree directly instead of the page view.
     *
     * @param string|null $table Table name
     * @return \Aimeos\Nestedset\QueryBuilder<PageView> Query builder including trashed nodes
     */
    public function newNestedSetQuery( ?string $table = null ) : \Aimeos\Nestedset\QueryBuilder
    {
        return ( new Page() )->newNestedSetQuery( $table );
    }


    /**
     * The Page model deletes the descendants.
     */
    protected function deleteDescendants() : void
    {
    }


    /**
     * The Page model restores the descendants.
     *
     * @param \Carbon\Carbon|null $deletedAt Time the page has been moved into the trash
     */
    protected function restoreDescendants( ?\Carbon\Carbon $deletedAt ) : void
    {
    }
}
