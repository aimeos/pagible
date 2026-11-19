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
     * Pending models identified by their model IDs (page IDs for pages)
     */
    private const IDS = 0;

    /**
     * Pending models identified by their search keys (variant IDs for pages)
     */
    private const KEYS = 1;

    /**
     * Pending removals by search key
     */
    private const REMOVE = 2;

    /**
     * Pending pages whose variants are reindexed if their listed languages change
     */
    private const VARIANTS = 3;


    /**
     * Reindexes models by ID after the surrounding transaction commits, queued if Scout uses a queue.
     *
     * All variants of the pages are reindexed unless the search keys of the changed variants
     * are passed instead of the page IDs. The models are collected and indexed at once after
     * the outermost transaction commits, immediately without transaction or when the defer()
     * scope ends. Models of rolled back transactions aren't indexed and if the same model is
     * indexed and removed by unindex(), the later operation wins.
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

        // the search keys of other models are their IDs
        self::add( $model, $keys || $model !== Models\PageView::class ? self::KEYS : self::IDS, $ids );
    }


    /**
     * Executes the callback and indexes the models passed to index() at once afterwards.
     *
     * Use flush() to index the models collected so far, e.g. after committing a batch of
     * records. Nested calls index the models when the outermost call returns or, within a
     * transaction, after the outermost transaction commits.
     *
     * @template T
     * @param \Closure(): T $callback Callback to execute
     * @return T Callback return value
     */
    public static function defer( \Closure $callback ) : mixed
    {
        $hooks = Hooks::get();

        if( $hooks->deferring ) {
            return $callback();
        }

        $hooks->deferring = true;

        try {
            return $callback();
        } finally {
            $hooks->deferring = false;
            // within an outer transaction, the models are indexed after it commits
            DB::connection()->afterCommit( fn() => self::flush() );
        }
    }


    /**
     * Indexes and removes the models collected so far.
     */
    public static function flush() : void
    {
        $hooks = Hooks::get();
        $tenants = [];

        foreach( $hooks->index as $seq => $op ) {
            $tenants[$op['tenant']][$seq] = $op;
        }

        // models passed to index() while indexing are collected again instead of being lost
        $hooks->index = [];

        foreach( $tenants as $tenant => $ops ) {
            Tenancy::run( (string) $tenant, fn() => self::apply( $ops ) );
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
     * once like the models passed to index(), e.g. if an import adds several variants of a page.
     *
     * @param array<string> $ids Page IDs
     * @param array<string> $langs Languages of the added or removed variants or the former and new source languages
     */
    public static function variants( array $ids, array $langs ) : void
    {
        if( !$ids || !self::usesExternalSearch() ) {
            return;
        }

        self::add( Models\PageView::class, self::VARIANTS, $ids, $langs );
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
        foreach( array_chunk( array_unique( $ids ), Models\Base::CHUNK ) as $chunk )
        {
            $query = $instance::makeAllSearchableQuery()->withoutGlobalScope( SoftDeletingScope::class );
            ( $keys ? $query->whereIn( $column, $chunk ) : $query->whereKey( $chunk ) )->chunkById( 100, fn( $items ) => $instance->syncMakeSearchable( $items ), $column, $key );
        }
    }


    /**
     * Updates the search index for trashed items, which other search engines keep if soft deletes are enabled.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs or search keys (variant IDs for pages)
     * @param bool $keys TRUE if the IDs are search keys
     */
    public static function trash( string $model, array $ids, bool $keys = false ) : void
    {
        self::keepsTrashed() ? self::index( $model, $ids, $keys ) : self::unindex( $model, $ids );
    }


    /**
     * Removes models from Scout by search key after the surrounding transaction commits.
     *
     * The removals are collected like the models passed to index(), so the later operation
     * wins if the same model is indexed and removed in the same transaction.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Search keys (variant IDs for pages)
     */
    public static function unindex( string $model, array $ids ) : void
    {
        if( !$ids || !self::usesSearchIndex() ) {
            return;
        }

        self::add( $model, self::REMOVE, $ids );
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
     * Adds a pending search index operation, which is flushed after the outermost transaction commits.
     *
     * @param class-string<Models\Base> $model Model class
     * @param int $type Operation type (IDS, KEYS, REMOVE or VARIANTS)
     * @param array<string> $ids Model IDs or search keys
     * @param array<string> $langs Changed languages for the VARIANTS operation
     */
    private static function add( string $model, int $type, array $ids, array $langs = [] ) : void
    {
        $hooks = Hooks::get();
        $seq = ++$hooks->sequence;

        $hooks->index[$seq] = [
            'tenant' => Tenancy::value(),
            'model' => $model,
            'type' => $type,
            'ids' => array_map( strval( ... ), $ids ),
            'langs' => array_map( strval( ... ), $langs ),
        ];

        // operations of rolled back transactions and savepoints are discarded
        DB::connection()->afterRollBack( function() use ( $hooks, $seq ) {
            unset( $hooks->index[$seq] );
        } );

        // Registered per call because callbacks of rolled back transactions are discarded;
        // the first callback flushes all operations, so later ones are no-ops.
        // Within defer(), the operations are flushed when the scope ends or flush() is called
        if( !$hooks->deferring ) {
            DB::connection()->afterCommit( fn() => self::flush() );
        }
    }


    /**
     * Indexes and removes the models of the pending operations for the current tenant.
     *
     * The operations are applied in their order, so the later one wins for the same model.
     *
     * @param array<int, array{tenant: string, model: class-string<Models\Base>, type: int, ids: array<string>, langs: array<string>}> $ops Pending operations by sequence number
     */
    private static function apply( array $ops ) : void
    {
        $variants = ['ids' => [], 'langs' => []];
        $list = [];

        foreach( $ops as $seq => $op )
        {
            $model = $op['model'];

            foreach( $op['ids'] as $id )
            {
                match( $op['type'] ) {
                    self::VARIANTS => $variants['ids'][$id] = true,
                    self::IDS => $list[$model][self::IDS][$id] = $seq,
                    default => $list[$model][$op['type']][$id] = $seq,
                };

                // the later operation replaces an earlier one of the same search key
                if( $op['type'] === self::KEYS ) {
                    unset( $list[$model][self::REMOVE][$id] );
                } elseif( $op['type'] === self::REMOVE ) {
                    unset( $list[$model][self::KEYS][$id] );
                }
            }

            foreach( $op['langs'] as $lang ) {
                $variants['langs'][$lang] = true;
            }
        }

        $pages = $list[Models\PageView::class] ?? [];

        if( $variants['ids'] )
        {
            // pages reindexed by their page ID already include all of their variants
            $variants['ids'] = array_diff_key( $variants['ids'], $pages[self::IDS] ?? [] );

            foreach( self::affected( $variants ) as $key )
            {
                // removed variants stay removed
                if( !isset( $pages[self::REMOVE][$key] ) ) {
                    $list[Models\PageView::class][self::KEYS][$key] ??= PHP_INT_MAX;
                }
            }
        }

        if( isset( $pages[self::REMOVE], $pages[self::IDS] ) ) {
            $list[Models\PageView::class][self::REMOVE] = self::removals( $pages[self::REMOVE], $pages[self::IDS] );
        }

        foreach( $list as $model => $types )
        {
            // only pages are indexed by search keys which differ from their IDs
            foreach( [self::IDS, self::KEYS] as $type ) {
                self::dispatch( $model, array_map( strval( ... ), array_keys( $types[$type] ?? [] ) ), $type === self::KEYS && $model === Models\PageView::class );
            }
        }

        // removed last, so pages reindexed by their page ID don't add their removed variants again
        foreach( $list as $model => $types ) {
            self::remove( $model, array_map( strval( ... ), array_keys( $types[self::REMOVE] ?? [] ) ) );
        }
    }


    /**
     * Indexes the models immediately, queued if Scout uses a queue.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs or search keys
     * @param bool $keys TRUE if the IDs are search keys (variant IDs for pages)
     */
    private static function dispatch( string $model, array $ids, bool $keys ) : void
    {
        if( !$ids ) {
            return;
        }

        if( !config( 'scout.queue' ) ) {
            self::sync( $model, $ids, $keys );
            return;
        }

        $instance = new $model();

        foreach( array_chunk( $ids, Models\Base::CHUNK ) as $chunk )
        {
            dispatch( ( new IndexModels( $model, $chunk, Tenancy::value(), $keys ) )
                ->onQueue( $instance->syncWithSearchUsingQueue() )
                ->onConnection( $instance->syncWithSearchUsing() ) );
        }
    }


    /**
     * Removes models from Scout immediately by search key, queued if Scout uses a queue.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Search keys (variant IDs for pages)
     */
    private static function remove( string $model, array $ids ) : void
    {
        $instance = new $model();
        $key = $instance->getScoutKeyName();

        foreach( array_chunk( $ids, Models\Base::CHUNK ) as $chunk )
        {
            $items = $instance->newCollection( array_map(
                fn( $id ) => $instance->newInstance()->forceFill( [$key => $id] ),
                $chunk,
            ) );
            $instance->queueRemoveFromSearch( $items );
        }
    }


    /**
     * Returns the removed page variants which aren't indexed again by their page ID afterwards.
     *
     * Pages are trashed and restored by page ID while their variants are removed by search key,
     * e.g. if an import drops and restores pages in the same transaction. The restored pages
     * keep their variants unless they are removed after the pages have been indexed again.
     *
     * @param array<string, int> $removed Search keys of the removed variants and their sequence numbers
     * @param array<string, int> $pages IDs of the indexed pages and their sequence numbers
     * @return array<string, int> Search keys of the variants that stay removed and their sequence numbers
     */
    private static function removals( array $removed, array $pages ) : array
    {
        $variant = new Models\PageVariant();
        $page = new Models\Page();

        foreach( array_chunk( array_map( strval( ... ), array_keys( $removed ) ), DB::IN_SIZE ) as $chunk )
        {
            $rows = $variant->newQuery()
                ->join( $page->getTable(), $page->getQualifiedKeyName(), '=', $variant->qualifyColumn( 'page_id' ) )
                ->whereIn( $variant->getQualifiedKeyName(), $chunk )
                ->whereNull( $page->getQualifiedDeletedAtColumn() )
                ->toBase()->pluck( $variant->qualifyColumn( 'page_id' ), $variant->getQualifiedKeyName() );

            foreach( $rows as $key => $pageId )
            {
                if( ( $pages[(string) $pageId] ?? 0 ) > $removed[(string) $key] ) {
                    unset( $removed[(string) $key] );
                }
            }
        }

        return $removed;
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


    /**
     * Whether the configured Scout driver maintains a search index.
     */
    private static function usesSearchIndex() : bool
    {
        return !in_array( config( 'scout.driver' ), [null, 'null', 'collection', 'database'], true );
    }
}
