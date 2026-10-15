<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource\Support;

use Aimeos\Cms\DB;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;


/**
 * Drops, restores and purges pages, elements and files.
 *
 * @internal Use the drop(), purge() and restore() methods of the resource classes instead
 */
final class Lifecycle
{
    /**
     * Soft-deletes items by ID.
     *
     * @template T of Base
     * @param class-string<T> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, T>
     */
    public static function drop( string $model, array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        return self::lifecycle( $model, $ids, LifecycleAction::Dropped, $user, $fields );
    }


    /**
     * Permanently deletes items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     * Calls purge() on File models to clean up storage.
     *
     * @template T of Base
     * @param class-string<T> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, T>
     */
    public static function purge( string $model, array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        return self::lifecycle( $model, $ids, LifecycleAction::Purged, $user, $fields );
    }


    /**
     * Restores soft-deleted items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     *
     * @template T of Base
     * @param class-string<T> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, T>
     */
    public static function restore( string $model, array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        return self::lifecycle( $model, $ids, LifecycleAction::Restored, $user, $fields );
    }


    /**
     * Applies and announces a lifecycle action while preserving Page tree semantics and route invalidation.
     *
     * @template T of Base
     * @param class-string<T> $model
     * @param array<string> $ids
     * @param LifecycleAction $action
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, T>
     */
    private static function lifecycle( string $model, array $ids, LifecycleAction $action,
        ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( $model::PERM . ':' . $action->verb(), $user );

        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );
        $editor = Utils::editor( $user );

        /** @var Collection<int, T> $items */
        $items = $model === PageView::class
            ? self::lifecyclePages( $ids, $action, $editor, $fields )
            : self::lifecycleItems( $model, $ids, $action, $editor, $fields );

        return $items;
    }


    /**
     * Applies and announces a lifecycle action to pages including their subtrees and variants.
     *
     * @param array<string> $ids Page IDs
     * @param LifecycleAction $action
     * @param string $editor Name of the editing user
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    private static function lifecyclePages( array $ids, LifecycleAction $action, string $editor, array $fields ) : Collection
    {
        $apply = fn() => self::applyPages( $ids, $action, $editor, $fields );

        $run = $action !== LifecycleAction::Dropped
            ? fn() => DB::lockedTransaction( $apply )
            : fn() => DB::transaction( $apply );

        [$items, $pages, $variants, $routes] = PageView::locked( fn() => Scout::mute( [PageView::class], $run ) );

        if( $action !== LifecycleAction::Restored ) {
            Invalidation::routes( $routes );
        }

        self::announceLifecycle( $items, $action, $editor, announce: true );
        self::indexPages( $action, $pages, $variants );

        return $items;
    }


    /**
     * Applies a lifecycle action to the locked pages within a transaction.
     *
     * @param array<string> $ids Page IDs
     * @param LifecycleAction $action
     * @param string $editor Name of the editing user
     * @param array<string> $fields Requested response fields
     * @return array{EloquentCollection<int, Base>, array<string>, array<string>, array<string, array<string>>}
     *  Changed pages, IDs of all pages and variants in their subtrees and the variant paths by domain
     */
    private static function applyPages( array $ids, LifecycleAction $action, string $editor, array $fields ) : array
    {
        /** @var EloquentCollection<int, Base> $items */
        $items = PageView::withTrashed()->whereIn( 'id', $ids )->select( $fields ? [
            ...PageView::REQUIRED_COLUMNS,
            ...array_intersect( PageView::RESPONSE_COLUMNS, $fields ),
        ] : PageView::SELECT_COLUMNS )->get();

        if( $items->isEmpty() ) {
            return [$items, [], [], []];
        }

        // the URLs of all language variants are invalidated and pages are indexed per variant,
        // so collect the variants before they are removed
        [$pages, $variants, $routes] = $action === LifecycleAction::Restored
            ? [self::restoredPages( $items ), [], []]
            : self::subtreeVariants( $items );

        // The versions are removed together with the page variants, the route is in data but not the content in aux
        if( $action === LifecycleAction::Purged && Base::announces( Purged::class ) ) {
            $items->load( ['latest' => fn( $query ) => $query->select( [...Version::SELECT_COLUMNS, 'publish_at', 'created_at'] )] );
        }

        // the variants are removed by the foreign key when their pages are purged, the versions aren't
        if( $action === LifecycleAction::Purged ) {
            PageVariant::purgeVersions( $variants );
        }

        self::pageLifecycle( $items, $action, $editor );
        return [$items, $pages, $variants, $routes];
    }


    /**
     * Returns the IDs of the restored pages to index.
     *
     * @param EloquentCollection<int, Base> $items Restored pages
     * @return array<string> IDs of the restored pages, including their subtrees for external search engines
     */
    private static function restoredPages( EloquentCollection $items ) : array
    {
        if( !Scout::usesExternalSearch() ) {
            return array_map( strval( ... ), $items->modelKeys() );
        }

        // subtrees can be large, so only plain values are kept
        return Page::withTrashed()->where( fn( $query ) => self::whereSubtrees( $query, $items, NestedSet::LFT ) )
            ->orderBy( NestedSet::LFT )->lockForUpdate()->toBase()->pluck( 'id' )->map( strval( ... ) )->all();
    }


    /**
     * Locks and returns the variants within the subtrees of the given pages.
     *
     * The variants are locked together with their tree rows in one query without the page IDs.
     *
     * @param EloquentCollection<int, Base> $items Pages
     * @return array{array<string>, array<string>, array<string, array<string>>}
     *  IDs of the pages and variants in the subtrees and the variant paths by domain
     */
    private static function subtreeVariants( EloquentCollection $items ) : array
    {
        $variant = new PageVariant();
        $query = Page::withTrashed();

        $rows = $query->join( $variant->getTable(), $variant->qualifyColumn( 'page_id' ), '=', $query->qualifyColumn( 'id' ) )
            ->where( fn( $query ) => self::whereSubtrees( $query, $items, $query->qualifyColumn( NestedSet::LFT ) ) )
            ->orderBy( $query->qualifyColumn( NestedSet::LFT ) )
            ->lockForUpdate()->toBase()
            ->get( $variant->qualifyColumns( ['id', 'page_id', 'domain', 'path'] ) );

        $routes = [];
        $variants = [];

        foreach( $rows as $row ) {
            $routes[(string) $row->domain][] = (string) $row->path;
            $variants[] = (string) $row->id;
        }

        $pages = $rows->pluck( 'page_id' )->map( strval( ... ) )->unique()->values()->all();

        return [$pages, $variants, $routes];
    }


    /**
     * Adds the conditions matching the subtrees of the given pages to the query.
     *
     * @param \Illuminate\Database\Eloquent\Builder<Page> $query Query to add the conditions to
     * @param EloquentCollection<int, Base> $items Pages with their nested set bounds
     * @param string $column Name of the left nested set column
     */
    private static function whereSubtrees( $query, EloquentCollection $items, string $column ) : void
    {
        foreach( PageView::topmost( $items ) as $root ) {
            $query->orWhereBetween( $column, [$root->getAttribute( NestedSet::LFT ), $root->getAttribute( NestedSet::RGT )] );
        }
    }


    /**
     * Updates the search index for the changed pages.
     *
     * @param LifecycleAction $action
     * @param array<string> $pages IDs of the pages in the changed subtrees
     * @param array<string> $variants IDs of the variants in the changed subtrees
     */
    private static function indexPages( LifecycleAction $action, array $pages, array $variants ) : void
    {
        if( $action === LifecycleAction::Dropped && !Scout::usesExternalSearch() ) {
            return;
        }

        if( Scout::keeps( $action ) ) {
            // all variants of restored or soft deleted pages change
            Scout::index( PageView::class, $pages );
        } else {
            // pages are indexed per variant
            Scout::unindex( PageView::class, Scout::usesSearchIndex() ? $variants : [] );
        }
    }


    /**
     * Applies and announces a lifecycle action to elements or files in locked chunks.
     *
     * @param class-string<Base> $model Element or File class
     * @param array<string> $ids Item IDs
     * @param LifecycleAction $action
     * @param string $editor Name of the editing user
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    private static function lifecycleItems( string $model, array $ids, LifecycleAction $action, string $editor, array $fields ) : Collection
    {
        $isolated = $action === LifecycleAction::Purged && $model::purgesStorage();
        $announce = !$isolated || count( $ids ) === 1;
        sort( $ids, SORT_STRING );

        // purged files are announced with their publication state, which is removed with their versions
        $states = $isolated && $announce && Base::announces( Purged::class );
        $apply = fn( array $ids ) => self::lifecycleChunk( $model, $ids, $action, $editor, $fields, $states );

        $batch = fn() => collect( $ids )->chunk( 100 )->reduce(
            fn( Collection $items, Collection $chunk ) => $items->concat( $apply( $chunk->all() ) ),
            collect(),
        );

        $items = $model::locked( fn() => Scout::mute( [$model], function() use ( $apply, $batch, $ids, $isolated ) {
            try {
                return DB::transaction( $batch );
            } catch( \Exception $e ) {
                if( !$isolated ) {
                    throw $e;
                }
            }

            // files whose storage can't be cleaned up must not prevent purging the others
            $items = collect();

            foreach( $ids as $id ) {
                try {
                    $items->push( ...DB::transaction( fn() => $apply( [$id] ) ) );
                } catch( \Exception $e ) {
                    report( $e );
                }
            }

            return $items;
        } ) );

        self::announceLifecycle( $items, $action, $editor, $announce );

        /** @var array<string> $changed */
        $changed = $items->pluck( 'id' )->all();

        if( Scout::keeps( $action ) ) {
            Scout::index( $model, $changed );
        } else {
            Scout::unindex( $model, $changed );
        }

        return $items;
    }


    /**
     * Applies a lifecycle action to one locked chunk of elements or files.
     *
     * @param class-string<Base> $model Element or File class
     * @param array<string> $ids Item IDs
     * @param LifecycleAction $action
     * @param string $editor Name of the editing user
     * @param array<string> $fields Requested response fields
     * @param bool $states TRUE to load the publication states of the latest versions
     * @return EloquentCollection<int, Base> Changed items
     */
    private static function lifecycleChunk( string $model, array $ids, LifecycleAction $action, string $editor, array $fields,
        bool $states ) : EloquentCollection
    {
        $query = $model::withTrashed()->whereIn( 'id', $ids );

        if( $fields )
        {
            $instance = new $model();
            $required = $instance->qualifyColumns( ['id', 'tenant_id', 'latest_id', 'deleted_at'] );

            array_push( $required, ...$model::lifecycleColumns( $action ) );

            $response = [...$instance->getVisible(), 'editor', 'created_at', 'updated_at'];
            $query->select( array_values( array_unique( [
                ...$required,
                ...array_intersect( $response, $fields ),
            ] ) ) );
        }

        /** @var EloquentCollection<int, Base> $items */
        $items = $query->orderBy( 'id' )->lockForUpdate()->get();

        if( $items->isEmpty() ) {
            return $items;
        }

        // Before purging because the references are removed with the items, dispatched after commit
        // Pages using deleted items were invalidated when they were deleted
        $refs = $action === LifecycleAction::Purged ? $items->reject( fn( Base $item ) => $item->trashed() ) : $items;
        $model::invalidateRefs( array_map( strval( ... ), $refs->modelKeys() ) );

        if( $states ) {
            $items->load( ['latest' => fn( $query ) => $query->select( 'id', 'published', 'publish_at', 'created_at' )] );
        }

        $model::lifecycle( $items, $action, $editor );
        return $items;
    }


    /**
     * Announces the lifecycle action for the changed items.
     *
     * @param Collection<int, Base> $items Changed items
     * @param LifecycleAction $action
     * @param string $editor Name of the editing user
     * @param bool $announce FALSE to announce purged items as one bulk event
     */
    private static function announceLifecycle( Collection $items, LifecycleAction $action, string $editor, bool $announce ) : void
    {
        if( $action === LifecycleAction::Dropped ) {
            Base::announceMany( $items, $action, $editor, [
                'deleted_at' => (string) ( $items->first()->deleted_at ?? now() ),
            ] );
        } elseif( $action === LifecycleAction::Restored ) {
            Base::announceMany( $items, $action, $editor, ['deleted_at' => null] );
        } else {
            Base::announceMany( $items, $action, $editor, bulk: !$announce );
        }
    }


    /**
     * Applies a lifecycle action to each locked page to keep the nested set consistent.
     *
     * @param EloquentCollection<int, Base> $items Pages
     * @param LifecycleAction $action Lifecycle action
     * @param string $editor Name of the editing user
     */
    private static function pageLifecycle( EloquentCollection $items, LifecycleAction $action, string $editor ) : void
    {
        if( $action === LifecycleAction::Purged )
        {
            Page::purge( $items );

            foreach( $items as $item ) {
                $item->exists = false;
            }

            return;
        }

        $at = ( new Page() )->freshTimestamp();

        if( $action === LifecycleAction::Dropped )
        {
            Page::trashSubtrees( $items, $at );

            foreach( $items as $item ) {
                $item->forceFill( ['deleted_at' => $at] )->syncOriginalAttribute( 'deleted_at' );
                $item->editor = $editor;
            }

            return;
        }

        Page::restoreSubtrees( $items, $at );

        foreach( $items as $item )
        {
            /** @var PageView $item */
            $item->forceFill( ['deleted_at' => null] )->syncOriginalAttribute( 'deleted_at' );
            PageRows::save( $item, ['editor' => $editor] );
        }
    }
}
