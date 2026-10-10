<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;


/**
 * Lazy, request-local frontend navigation view model.
 */
final class Navigation
{
    /** @var Collection<int, Page>|null */
    private ?Collection $ancestors = null;

    /** @var array<int, Collection<int, Page>> */
    private array $items = [];

    /** @var Collection<int, object{lang: string, url: string}&\stdClass>|null */
    private ?Collection $variants = null;

    /**
     * Creates a request-local navigation view for a page and frontend user.
     */
    public function __construct(
        private Page $page,
        private ?Authenticatable $user,
    ) {}


    /**
     * Returns visible ancestors of the current page.
     *
     * @return Collection<int, Page>
     */
    public function ancestors() : Collection
    {
        return $this->ancestors ??= $this->visible( $this->query()->whereAncestorOf( $this->page )->defaultOrder()->get() );
    }


    /**
     * Returns and memoizes the visible navigation tree for a root level.
     *
     * @param int $level Zero-based ancestor level
     * @return Collection<int, Page>
     */
    public function items( int $level = 0 ) : Collection
    {
        if( isset( $this->items[$level] ) ) {
            return $this->items[$level];
        }

        $start = $this->ancestors()->concat( [$this->page] )->skip( $level )->first();

        if( !$start instanceof Page ) {
            return $this->items[$level] = collect();
        }

        $lft = $this->page->getLftName();
        $items = $this->query()
            ->where( $lft, '>', $start->getLft() )
            ->where( $this->page->getRgtName(), '<', $start->getRgt() )
            ->whereIn( $this->page->getDepthName(), range(
                (int) $start->getDepth(),
                ( $start->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 ),
            ) )
            ->orderBy( $lft )
            ->get()
            ->toTree( $start );

        return $this->items[$level] = $this->visible( $items, true );
    }


    /**
     * Returns the published variants of the current page for language switchers and hreflang links.
     *
     * Only variants which are enabled and don't redirect are listed, without fallbacks.
     *
     * @return Collection<int, object{lang: string, url: string}&\stdClass> Variants with language and URL ordered by language
     */
    public function variants() : Collection
    {
        return $this->variants ??= $this->page->variants
            ->filter( fn( PageVariant $variant ) => $variant->to === '' )
            ->map( fn( PageVariant $variant ) => (object) [
                'lang' => $variant->lang,
                'url' => cmsroute( 'cms.page', ['path' => $variant->path], $variant->domain ),
            ] )
            ->values();
    }


    /**
     * Returns the base navigation query including the latest versions for editors.
     *
     * Each page is returned in the language of the current page if it has a visible variant
     * in that language, otherwise in its source language. Editors see unpublished variants.
     *
     * @return \Aimeos\Nestedset\QueryBuilder<Nav>
     */
    private function query() : \Aimeos\Nestedset\QueryBuilder
    {
        $query = Nav::select( Nav::SELECT_COLUMNS )->access( $this->user );
        $lang = (string) $this->page->lang;

        $editor = Permission::can( 'page:view', $this->user );

        $editor && $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );

        if( $lang !== '' && !Page::fallbackToSource() )
        {
            // translations without visible variant are pruned in "hide" mode, so only the variants of the language are fetched (indexable)
            $query->language( $lang );
            $editor || $query->where( fn( $q ) => $q->where( $q->qualifyColumn( 'status' ), '<>', 0 )
                ->orWhereColumn( $q->qualifyColumn( 'lang' ), $q->qualifyColumn( 'source' ) ) );
        }
        elseif( $lang !== '' )
        {
            $query->localized( $lang, $editor );
        }

        return $query;
    }


    /**
     * Tests if the page must be left out because it has no visible variant in the current language.
     *
     * Pages in their source language are shown instead if the "source" fallback is configured
     * and the source variant is visible. Otherwise, the page is hidden with its sub-pages.
     *
     * @param Page $page Navigation item in the current or its source language
     * @param int $status Status of the navigation item
     * @return bool TRUE if the page and its sub-pages are hidden, FALSE if not
     */
    private function fallback( Page $page, int $status ) : bool
    {
        $lang = (string) $this->page->lang;

        if( $lang === '' || $page->lang === $lang ) {
            return false;
        }

        return !Page::fallbackToSource() || $status !== 1;
    }


    /**
     * Filters unpublished nodes and recursively prunes hidden branches.
     *
     * @param iterable<int, mixed> $items
     * @return Collection<int, Page>
     */
    private function visible( iterable $items, bool $nested = false ) : Collection
    {
        $result = [];

        foreach( $items as $page )
        {
            if( !$page instanceof Page ) {
                continue;
            }

            $status = $page->status;

            if( $page->relationLoaded( 'latest' ) ) {
                $status = $page->getRelation( 'latest' )?->data->status ?? $status;
            }

            if( (int) $status === 2 ) {
                continue;
            }

            // source variant of a page without visible variant in the current language
            if( $this->fallback( $page, (int) $status ) ) {
                continue;
            }

            $children = collect();

            if( $nested && $page->relationLoaded( 'children' ) ) {
                $children = $this->visible( $page->getRelation( 'children' ), true );
                $page->setRelation( 'children', $children );
            }

            if( (int) $status !== 1 )
            {
                foreach( $children as $child ) {
                    $result[] = $child;
                }

                continue;
            }

            $result[] = $page;
        }

        return collect( $result );
    }
}
