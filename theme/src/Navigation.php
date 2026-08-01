<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms;

use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
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
        if( $this->ancestors !== null ) {
            return $this->ancestors;
        }

        $query = Nav::select( Nav::SELECT_COLUMNS )
            ->access( $this->user )
            ->whereAncestorOf( $this->page )
            ->defaultOrder();

        if( Permission::can( 'page:view', $this->user ) ) {
            $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        $this->ancestors = $this->visible( $query->get() );

        return $this->ancestors;
    }


    /**
     * Returns and memoizes the visible navigation tree for a root level.
     *
     * @return Collection<int, Page>
     */
    public function items( int $level = 0 ) : Collection
    {
        return $this->items[$level] ??= $this->visible(
            $this->loadItems( $level ),
            true,
        );
    }


    /**
     * Returns the lightweight navigation tree rooted at the requested level.
     *
     * @param int $level Zero-based ancestor level
     * @return \Aimeos\Nestedset\Collection
     */
    private function loadItems( int $level ) : \Aimeos\Nestedset\Collection
    {
        $start = $this->ancestors()->concat( [$this->page] )->skip( $level )->first();

        if( !$start instanceof Page ) {
            return new \Aimeos\Nestedset\Collection();
        }

        $lft = $this->page->getLftName();
        $rgt = $this->page->getRgtName();
        $depth = $this->page->getDepthName();

        $query = Nav::select( Nav::SELECT_COLUMNS )
            ->access( $this->user )
            ->where( $lft, '>', $start->getLft() )
            ->where( $rgt, '<', $start->getRgt() )
            ->whereIn( $depth, range(
                (int) $start->getDepth(),
                ( $start->getDepth() ?? 0 ) + config( 'cms.navdepth', 2 ),
            ) )
            ->orderBy( $lft );

        if( Permission::can( 'page:view', $this->user ) ) {
            $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return $query->get()->toTree( $start );
    }


    /**
     * Filters unpublished nodes and recursively prunes nested children.
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

            $children = collect();

            if( $nested && $page->relationLoaded( 'children' ) ) {
                $children = $this->visible( $page->getRelation( 'children' ), true );
                $page->setRelation( 'children', $children );
            }

            $status = $page->status;

            if( $page->relationLoaded( 'latest' ) ) {
                $status = $page->getRelation( 'latest' )?->data->status ?? $status;
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
