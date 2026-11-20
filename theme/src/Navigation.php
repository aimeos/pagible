<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Query\NavState;
use Aimeos\Cms\Query\NavTree;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;


/**
 * Lazy, request-local frontend navigation view model.
 */
final class Navigation
{
    /** @var Collection<int, PageView>|null */
    private ?Collection $ancestors = null;

    /** @var array<int, Collection<int, PageView>> */
    private array $items = [];

    /** @var Collection<int, object{lang: string, url: string}&\stdClass>|null */
    private ?Collection $variants = null;

    /**
     * Creates a request-local navigation view for a page and frontend user.
     */
    public function __construct(
        private PageView $page,
        private ?Authenticatable $user,
    ) {}


    /**
     * Returns visible ancestors of the current page.
     *
     * @return Collection<int, PageView>
     */
    public function ancestors() : Collection
    {
        return $this->ancestors ??= collect( $this->loaded() ?? $this->query()->whereAncestorOf( $this->page )->defaultOrder()->get() )
            ->whereInstanceOf( PageView::class )
            ->filter( fn( PageView $page ) => $this->state( $page ) === NavState::Show )
            ->values();
    }


    /**
     * Returns and memoizes the visible navigation tree for a root level.
     *
     * @param int $level Zero-based ancestor level
     * @return Collection<int, PageView>
     */
    public function items( int $level = 0 ) : Collection
    {
        if( isset( $this->items[$level] ) ) {
            return $this->items[$level];
        }

        $start = $this->ancestors()->concat( [$this->page] )->skip( $level )->first();

        if( !$start instanceof PageView ) {
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
            ->get();

        // the nested set collection isn't generic, so the pages are narrowed to PageView for pruning and the result
        $pages = collect( $items->all() )->whereInstanceOf( PageView::class );
        $shown = NavTree::prune( $pages, fn( PageView $page ) => $this->state( $page ), [$start->getKey() => $start->getKey()] );
        $tree = $items->make( $shown )->toTree( $start );

        return $this->items[$level] = collect( $tree->all() )->whereInstanceOf( PageView::class )->values();
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

        $editor = Permission::previews( $this->user );

        $editor && $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );

        if( $lang !== '' && !PageView::fallbackToSource() )
        {
            // translations without visible variant are pruned in "hide" mode, so only the variants of the language are fetched (indexable)
            $query->language( $lang );
            $editor || $query->where( fn( $q ) => $q->whereIn( $q->qualifyColumn( 'status' ), PageStatus::reachable() )
                ->orWhereColumn( $q->qualifyColumn( 'lang' ), $q->qualifyColumn( 'source' ) ) );
        }
        elseif( $lang !== '' )
        {
            $query->localized( $lang, $editor );
        }

        return $query;
    }


    /**
     * Returns the accessible ancestors already loaded with their access decisions for visitors.
     *
     * The ancestors of the page are localized like the navigation for visitors, so pages without
     * visible variant in the current language are left out by state() in the same way.
     *
     * @return Collection<int, PageView>|null Accessible ancestors or NULL if not loaded with access decisions
     */
    private function loaded() : ?Collection
    {
        if( !$this->page->relationLoaded( 'ancestors' ) || Permission::previews( $this->user ) ) {
            return null;
        }

        $items = $this->page->getRelation( 'ancestors' );

        if( !$items instanceof Collection || $items->contains( fn( PageView $item ) => !array_key_exists( 'access_exists', $item->getAttributes() ) ) ) {
            return null;
        }

        return $items->reject( fn( PageView $item ) => $item->access_exists && !$item->access_allowed )->values();
    }


    /**
     * Returns if the page is shown in the navigation.
     *
     * Editors see the status of the latest version. Disabled pages are skipped and their
     * sub-pages take their place, hidden pages are left out including their sub-pages. Pages
     * in their source language are shown instead of a missing variant in the current language
     * only if the "source" fallback is configured, otherwise they are left out with their sub-pages.
     *
     * @param PageView $page Navigation item in the current or its source language
     * @return NavState Navigation state of the page
     */
    private function state( PageView $page ) : NavState
    {
        $status = $page->status;

        if( $page->relationLoaded( 'latest' ) ) {
            $status = $page->getRelation( 'latest' )?->data->status ?? $status;
        }

        $lang = (string) $this->page->lang;

        // source variant of a page without visible variant in the current language
        if( $lang !== '' && $page->lang !== $lang && ( !PageView::fallbackToSource() || (int) $status !== PageStatus::Enabled->value ) ) {
            return NavState::Hide;
        }

        return match( PageStatus::tryFrom( (int) $status ) ) {
            PageStatus::Enabled => NavState::Show,
            PageStatus::Hidden => NavState::Hide,
            default => NavState::Skip,
        };
    }
}
