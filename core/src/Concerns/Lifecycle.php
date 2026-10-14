<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\DB;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;


/**
 * Drops, restores and purges pages, elements and files of the resource methods.
 */
trait Lifecycle
{
    /**
     * Soft-deletes items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
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
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
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
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    public static function restore( string $model, array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        return self::lifecycle( $model, $ids, LifecycleAction::Restored, $user, $fields );
    }


    /**
     * Applies and announces a lifecycle action while preserving Page tree semantics and route invalidation.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param LifecycleAction $action
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    protected static function lifecycle( string $model, array $ids, LifecycleAction $action,
        ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );
        $editor = Utils::editor( $user );

        return $model === PageView::class
            ? self::lifecyclePages( $ids, $action, $editor, $fields )
            : self::lifecycleItems( $model, $ids, $action, $editor, $fields );
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
        $pages = [];
        $routes = [];
        $variants = [];

        $apply = function() use ( $action, $editor, $fields, $ids, &$pages, &$routes, &$variants ) {
            /** @var EloquentCollection<int, Base> $items */
            $items = PageView::withTrashed()->whereIn( 'id', $ids )->select( $fields ? [
                ...PageView::REQUIRED_COLUMNS,
                ...array_intersect( PageView::RESPONSE_COLUMNS, $fields ),
            ] : PageView::SELECT_COLUMNS )->get();

            if( $items->isEmpty() ) {
                return $items;
            }

            // subtrees can be large, so only plain values are kept and only the tree rows are locked, not their variants
            $pages = $action !== LifecycleAction::Restored || Scout::usesExternalSearch()
                ? Page::withTrashed()->where( function( $query ) use ( $items ) {
                    foreach( PageView::topmost( $items ) as $root ) {
                        $query->orWhereBetween( NestedSet::LFT, [$root->getAttribute( NestedSet::LFT ), $root->getAttribute( NestedSet::RGT )] );
                    }
                } )->orderBy( NestedSet::LFT )->lockForUpdate()->toBase()->pluck( 'id' )->map( strval( ... ) )->all()
                : array_map( strval( ... ), $items->modelKeys() );

            // the URLs of all language variants are invalidated and pages are indexed per variant,
            // so collect the variants before they are removed
            if( $action !== LifecycleAction::Restored ) {
                foreach( array_chunk( $pages, DB::IN_SIZE ) as $chunk ) {
                    foreach( PageVariant::withTrashed()->whereIn( 'page_id', $chunk )->toBase()->get( ['id', 'domain', 'path'] ) as $row ) {
                        $routes[(string) $row->domain][] = (string) $row->path;
                        $variants[] = (string) $row->id;
                    }
                }
            }

            // The versions are removed together with the page variants, the route is in data but not the content in aux
            if( $action === LifecycleAction::Purged && Base::announces( Purged::class ) ) {
                $items->load( ['latest' => fn( $query ) => $query->select( [...Version::SELECT_COLUMNS, 'publish_at', 'created_at'] )] );
            }

            // the purged pages don't remove the already collected variants again
            if( $action === LifecycleAction::Purged ) {
                PageVariant::purge( $variants );
            }

            self::pageLifecycle( $items, $action, $editor );
            return $items;
        };

        $run = $action !== LifecycleAction::Dropped
            ? fn() => DB::lockedTransaction( $apply )
            : fn() => DB::transaction( $apply );

        $items = PageView::locked( fn() => Scout::mute( [PageView::class], $run ) );

        if( $action !== LifecycleAction::Restored ) {
            Invalidation::routes( $routes );
        }

        self::announceLifecycle( $items, $action, $editor, announce: true );

        if( $action === LifecycleAction::Dropped && !Scout::usesExternalSearch() ) {
            return $items;
        }

        if( Scout::keeps( $action ) ) {
            // all variants of restored or soft deleted pages change
            Scout::index( PageView::class, $pages );
        } else {
            // pages are indexed per variant
            Scout::unindex( PageView::class, Scout::usesSearchIndex() ? $variants : [] );
        }

        return $items;
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
    protected static function pageLifecycle( EloquentCollection $items, LifecycleAction $action, string $editor ) : void
    {
        foreach( $items as $item )
        {
            /** @var PageView $item */
            if( $action === LifecycleAction::Purged )
            {
                // positions of loaded pages are outdated if other pages were removed before
                // and pages removed together with an ancestor don't exist anymore
                Page::withTrashed()->find( $item->id )?->purge();
                $item->exists = false;
                continue;
            }

            Pages::saveNode( $item, fn( Page $node ) => $action === LifecycleAction::Dropped ? $node->delete() : $node->restore() );

            if( $action === LifecycleAction::Restored ) {
                Pages::saveVariant( $item, ['editor' => $editor] );
            } else {
                $item->editor = $editor;
            }
        }
    }
}
