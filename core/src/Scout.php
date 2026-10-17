<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\IndexModels;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Laravel\Scout\ModelObserver;
use Illuminate\Database\Eloquent\Model;


/**
 * Search index maintenance of the CMS models.
 */
class Scout
{
    /**
     * Reindexes models by ID in bounded batches, queued if Scout uses a queue.
     *
     * All variants of the pages are reindexed unless the search keys of the changed variants
     * are passed instead of the page IDs.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs (page IDs for pages) or search keys (variant IDs for pages)
     * @param bool $keys TRUE if the IDs are search keys
     */
    public static function index( string $model, array $ids, bool $keys = false ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        if( !config( 'scout.queue' ) ) {
            self::sync( $model, $ids, $keys );
            return;
        }

        $instance = new $model();

        foreach( array_chunk( array_values( array_unique( $ids ) ), Models\Base::CHUNK ) as $chunk )
        {
            dispatch( ( new IndexModels( $model, $chunk, Tenancy::value(), $keys ) )
                ->onQueue( $instance->syncWithSearchUsingQueue() )
                ->onConnection( $instance->syncWithSearchUsing() ) );
        }
    }


    /**
     * Executes the callback without automatic Scout model synchronization.
     *
     * Already muted model classes remain muted when nested calls return.
     *
     * @template T
     * @param array<class-string<Models\Base>> $models Model classes to mute
     * @param \Closure(): T $callback Callback to execute
     * @return T Callback return value
     */
    public static function mute( array $models, \Closure $callback ) : mixed
    {
        $instances = [];

        foreach( array_unique( $models ) as $model ) {
            $instance = new $model();

            if( !ModelObserver::syncingDisabledFor( $instance ) ) {
                $instance::disableSearchSyncing();
                $instances[] = $instance;
            }
        }

        try {
            return $callback();
        } finally {
            foreach( $instances as $instance ) {
                $instance::enableSearchSyncing();
            }
        }
    }


    /**
     * Reindexes the variants of the pages whose listed languages change after language variants are added or removed.
     *
     * Variants are listed for the languages without variant whose fallback chain (see Utils::chain())
     * leads to them. Only required for external search engines. The pages are collected and reindexed
     * once after the surrounding transaction commits, e.g. if an import adds several variants of a page.
     *
     * @param array<string> $ids Page IDs
     * @param array<string> $langs Languages of the added or removed variants or the former and new source languages
     */
    public static function variants( array $ids, array $langs ) : void
    {
        if( !$ids || !self::usesExternalSearch() ) {
            return;
        }

        $hooks = Hooks::get();
        $tenant = Tenancy::value();

        foreach( $ids as $id ) {
            $hooks->variants[$tenant]['ids'][(string) $id] = true;
        }

        foreach( $langs as $lang ) {
            $hooks->variants[$tenant]['langs'][(string) $lang] = true;
        }

        // Registered per call because callbacks of rolled back transactions are discarded while
        // their pages stay pending; the first callback flushes all pages, so later ones are no-ops
        // and pages of rolled back transactions are reindexed with the next commit, which is harmless
        DB::connection()->afterCommit( function() {
            $hooks = Hooks::get();
            $pending = $hooks->variants;
            $hooks->variants = [];

            foreach( $pending as $tenant => $entry ) {
                Tenancy::run( (string) $tenant, fn() => self::index( Models\PageView::class, self::affected( $entry ), keys: true ) );
            }
        } );
    }


    /**
     * Reindexes models after the surrounding transaction commits.
     *
     * Use it instead of index() inside transactions, so the search index doesn't contain
     * uncommitted changes and rolled back changes are never indexed.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs or search keys
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    public static function reindex( string $model, array $ids, bool $keys = false ) : void
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        self::afterCommit( fn() => self::index( $model, $ids, keys: $keys ) );
    }


    /**
     * Adds all pages, elements and files including the trashed ones to the search index.
     */
    public static function rebuild() : void
    {
        // queued Scout jobs restore pages by their page ID, which loses all variants except one
        Models\Page::withTrashed()->toBase()->select( 'id' )->chunkById( Models\Base::CHUNK, function( $rows ) {
            self::index( Models\PageView::class, $rows->pluck( 'id' )->map( strval( ... ) )->all() );
        } );

        foreach( [Models\Element::class, Models\File::class] as $model ) {
            $model::makeAllSearchableQuery()->withoutGlobalScope( SoftDeletingScope::class )->searchable( 50 ); // @phpstan-ignore method.notFound
        }
    }


    /**
     * Executes the callback for the current tenant after the surrounding transaction commits.
     *
     * Without a transaction, the callback is executed immediately and it's discarded if the
     * transaction is rolled back, so the search index doesn't contain uncommitted changes.
     *
     * @param \Closure(): mixed $callback Callback to execute
     */
    private static function afterCommit( \Closure $callback ) : void
    {
        $tenant = Tenancy::value();
        DB::connection()->afterCommit( fn() => Tenancy::run( $tenant, $callback ) );
    }


    /**
     * Reindexes models immediately after loading their searchable relations.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs or search keys
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    public static function sync( string $model, array $ids, bool $keys = false ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $key = $instance->getScoutKeyName();
        $column = $instance->qualifyColumn( $key );

        // batches by search key to limit the number of page variants for many languages,
        // larger than the ID batches to load pages with only one language in one query
        foreach( array_chunk( array_values( array_unique( $ids ) ), Models\Base::CHUNK ) as $chunk )
        {
            $query = $instance::makeAllSearchableQuery()->withoutGlobalScope( SoftDeletingScope::class );
            ( $keys ? $query->whereIn( $column, $chunk ) : $query->whereKey( $chunk ) )->chunkById( 100, fn( $items ) => $instance->syncMakeSearchable( $items ), $column, $key );
        }
    }


    /**
     * Removes models from Scout by ID in bounded native batches.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Search keys (variant IDs for pages)
     */
    public static function unindex( string $model, array $ids ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        $instance = new $model();
        $key = $instance->getScoutKeyName();

        foreach( array_chunk( array_values( array_unique( $ids ) ), Models\Base::CHUNK ) as $chunk )
        {
            $items = $instance->newCollection( array_map(
                fn( $id ) => $instance->newInstance()->forceFill( [$key => $id] ),
                $chunk,
            ) );
            $instance->queueRemoveFromSearch( $items );
        }
    }


    /**
     * Whether changes of the model are synced to the search index.
     *
     * @param class-string<Model> $model Searchable model class
     * @return bool TRUE if the search index is updated, FALSE if syncing is disabled
     */
    public static function syncs( string $model ) : bool
    {
        return self::usesSearchIndex() && !ModelObserver::syncingDisabledFor( new $model() );
    }


    /**
     * Whether trashed items stay in the search index, which is the case if Scout soft deletes.
     */
    public static function keepsTrashed() : bool
    {
        return (bool) config( 'scout.soft_delete' );
    }


    /**
     * Whether visibility must be stored and filtered in an external search index.
     */
    public static function usesExternalSearch() : bool
    {
        return self::usesSearchIndex() && config( 'scout.driver' ) !== 'cms';
    }


    /**
     * Whether the configured Scout driver maintains a search index.
     */
    public static function usesSearchIndex() : bool
    {
        return !in_array( config( 'scout.driver' ), [null, 'null', 'collection', 'database'], true );
    }


    /**
     * Returns the IDs of the variants whose listed languages can change by the changed languages.
     *
     * Only languages whose fallback chain contains a changed language get another variant. The
     * variants of the chain languages and the source variants are the old and new candidates.
     *
     * @param array{ids?: array<string, bool>, langs?: array<string, bool>} $entry Pending page IDs and changed languages
     * @return array<string> Variant IDs
     */
    private static function affected( array $entry ) : array
    {
        $codes = [];
        $list = [];
        // trashed pages and variants are only indexed if the search engine keeps them
        $trashed = self::keepsTrashed();

        foreach( array_keys( $entry['langs'] ?? [] ) as $lang )
        {
            foreach( [$lang, ...Utils::locales()] as $locale )
            {
                if( in_array( $lang, $chain = Utils::chain( $locale ), true ) ) {
                    $codes = [...$codes, ...$chain];
                }
            }
        }

        foreach( array_chunk( array_keys( $entry['ids'] ?? [] ), Models\Base::CHUNK ) as $chunk )
        {
            $query = $trashed ? Models\PageView::withoutGlobalScope( SoftDeletingScope::class ) : Models\PageView::query();
            $ids = $query->allVariants( trashed: $trashed )
                ->whereIn( 'id', $chunk )
                ->where( fn( $q ) => $q->whereIn( 'lang', array_values( array_unique( $codes ) ) )->orWhereColumn( 'lang', 'source' ) )
                ->pluck( 'variant_id' )->map( strval( ... ) )->all();

            $list = [...$list, ...$ids];
        }

        return $list;
    }
}
