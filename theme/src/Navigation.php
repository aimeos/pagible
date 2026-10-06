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
     * Returns the base navigation query including the latest versions for editors.
     *
     * @return \Aimeos\Nestedset\QueryBuilder<Nav>
     */
    private function query() : \Aimeos\Nestedset\QueryBuilder
    {
        $query = Nav::select( Nav::SELECT_COLUMNS )->access( $this->user );

        if( Permission::can( 'page:view', $this->user ) ) {
            $query->with( ['latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'data' )] );
        }

        return $query;
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
