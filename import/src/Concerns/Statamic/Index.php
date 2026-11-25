<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Indexes the Statamic entries of the collections in import order including their URL paths.
 */
trait Index
{
    /** @var array<string, int> Number of the entries without URL skipped by collection handle */
    protected array $unroutable = [];
    /** @var array<int|string, true> IDs of the published entries in import order */
    protected array $entries = [];
    /** @var array<int|string, string|null> URL paths of the entries by ID, NULL if the path depends on the title */
    protected array $paths = [];
    /** @var array<int|string, string> Collection handles of the entries by ID */
    protected array $collectionOf = [];
    /** @var array<string, true> URL paths of the entries without URI and slug generated from their titles */
    protected array $titlePaths = [];
    /** @var array<string, array<int, array<string, mixed>>> Trees of the structured collections by handle */
    protected array $trees = [];
    /** @var array<string, true> Handles of the collections whose trees are built from the parents of the entries */
    protected array $parentTrees = [];


    /**
     * Fetches the IDs, collections and URL paths of all published entries and the trees of the collections.
     */
    protected function index(): void
    {
        $this->entries = $this->paths = $this->collectionOf = $this->attempted = $this->titlePaths = $this->changed = $this->unroutable = [];
        $this->localPaths = $this->localIds = $this->roots = [];
        $this->homeId = null;
        $routable = [];

        // only the IDs, collections and URLs of all entries are kept, the other columns are fetched per chunk
        foreach( $this->reader->ids( $this->since !== null ) as $row )
        {
            $collection = (string) ( $row->collection ?? '' );

            // Statamic has no URLs for entries of collections without route
            if( ( !is_string( $row->uri ?? null ) || $row->uri === '' ) && !( $routable[$collection] ??= $this->reader->routable( $collection ) ) )
            {
                $this->unroutable[$collection] = ( $this->unroutable[$collection] ?? 0 ) + 1;
                continue;
            }

            $this->entries[$row->id] = true;
            $this->collectionOf[$row->id] = $collection;
            $this->paths[$row->id] = $this->path( $row );

            // the entry with the URI "/" is the home page
            if( $this->paths[$row->id] === '' ) {
                $this->homeId ??= $row->id;
            }

            // entries of changed collections or of collections hiding entries by date are imported again
            if( $this->since !== null ) {
                $this->changed[$row->id] = $this->reader->refresh( $collection, $this->since ) ? PHP_INT_MAX : $this->modified( $row );
            }
        }

        $this->indexLocalized();
        $this->trees = $this->reader->trees();
        $this->parentTrees = [];
        $this->sortEntries();

        // the parents stored in the entry data are used if the tree isn't stored in the database
        foreach( array_values( array_unique( $this->collectionOf ) ) as $collection )
        {
            if( !isset( $this->trees[$collection] ) && $this->reader->isStructured( $collection ) && ( $tree = $this->reader->parentTree( $collection ) ) !== [] )
            {
                $this->trees[$collection] = $tree;
                $this->parentTrees[$collection] = true;
            }
        }

        // the first entry of collections whose tree expects a root entry is the home page
        foreach( $this->trees as $handle => $tree )
        {
            $first = $tree[0]['entry'] ?? null;

            if( $this->homeId === null && ( is_int( $first ) || is_string( $first ) ) && isset( $this->entries[$first] ) && $this->reader->expectsRoot( $handle ) )
            {
                $this->homeId = $first;
                $this->paths[$first] = '';
            }
        }
    }


    /**
     * Returns the URL path of the entry from its URI or slug.
     *
     * @param \stdClass $row Entry with URI and slug
     * @return string|null URL path without slashes, empty for the home page or NULL if it depends on the title
     */
    protected function path( \stdClass $row ): ?string
    {
        if( is_string( $uri = $row->uri ?? null ) && $uri !== '' ) {
            return trim( $uri, '/' );
        }

        return is_string( $slug = $row->slug ?? null ) && $slug !== '' ? $slug : null;
    }


    /**
     * Returns the collections in import order, mounted collections after the collection containing their mount entry.
     *
     * @param array<int, string> $collections Collection handles
     * @param array<string, string> $mounts Entry IDs the collections are mounted on by handle
     * @return array<int, string> Collection handles in import order
     */
    protected function order( array $collections, array $mounts ): array
    {
        $ordered = [];
        $pending = $collections;

        while( $pending !== [] )
        {
            $progress = false;

            foreach( $pending as $idx => $handle )
            {
                $owner = isset( $mounts[$handle] ) ? $this->collectionOf[$mounts[$handle]] ?? null : null;

                if( $owner === null || $owner === $handle || in_array( $owner, $ordered, true ) || !in_array( $owner, $collections, true ) )
                {
                    $ordered[] = $handle;
                    unset( $pending[$idx] );
                    $progress = true;
                }
            }

            // collections mounted on each other are imported in the given order
            if( !$progress ) {
                return array_merge( $ordered, array_values( $pending ) );
            }
        }

        return $ordered;
    }


    /**
     * Orders the entries of the collections which aren't structured like Statamic sorts them.
     *
     * Their pages are created in this order, so they have the same order as the entries in Statamic.
     */
    protected function sortEntries(): void
    {
        $entries = [];

        foreach( array_values( array_unique( $this->collectionOf ) ) as $collection )
        {
            foreach( $this->reader->sorted( $collection ) ?? [] as $id )
            {
                if( isset( $this->entries[$id] ) ) {
                    $entries[$id] = true;
                }
            }
        }

        // entries of structured collections keep their order and are imported by their tree
        $this->entries = $entries + $this->entries;
    }


    /**
     * Returns the IDs of the remaining entries of the collection in import order in chunks.
     *
     * @param string $collection Collection handle
     * @return \Generator<int, array<int, int|string>> Chunks of entry IDs
     */
    protected function chunks( string $collection ): \Generator
    {
        $chunk = [];

        foreach( $this->entries as $id => $exists )
        {
            if( ( $this->collectionOf[$id] ?? null ) !== $collection || isset( $this->attempted[$id] ) ) {
                continue;
            }

            $chunk[] = $id;

            if( count( $chunk ) >= static::TREE_SIZE )
            {
                yield $chunk;
                $chunk = [];
            }
        }

        if( $chunk ) {
            yield $chunk;
        }
    }


    /**
     * Fetches the published entries with the given IDs which weren't imported yet.
     *
     * @param array<array-key, mixed> $ids Entry IDs by key, unknown and unpublished entries are skipped
     * @return Collection<array-key, \stdClass> Entries by the keys of the IDs
     */
    protected function rows( array $ids ): Collection
    {
        $ids = array_filter( $ids, fn( $id ) => ( is_int( $id ) || is_string( $id ) ) && isset( $this->entries[$id] ) && !isset( $this->attempted[$id] ) );
        $rows = $this->reader->rows( array_values( array_unique( $ids ) ) );

        return Collection::make( $ids )->map( fn( $id ) => $rows[$id] ?? null )->filter();
    }


    /**
     * Fetches the data of the entries whose pages aren't kept and their pages, assets and files at once.
     *
     * @param Collection<array-key, \stdClass> $entries Statamic entries without data
     * @return Collection<array-key, \stdClass> Entries with data if their pages aren't kept
     */
    protected function prepare( Collection $entries ): Collection
    {
        // the topmost origin of localized entries is needed for their translations
        $entries = $entries->replace( $this->reader->withData( $entries->reject(
            fn( $entry ) => $this->kept( $entry ) && ( $this->languages === [] || ( $entry->origin_id ?? null ) === null )
        ) ) );

        $paths = [];
        $assets = [];

        foreach( $entries as $entry )
        {
            if( ( $path = $this->paths[$entry->id] ?? null ) !== null && $path !== '' ) {
                $paths[] = $path;
            }

            // the data is decoded by withData() once
            if( property_exists( $entry, 'data' ) )
            {
                $data = $this->reader->data( $entry );
                $assets = array_merge( $assets, $this->content->containers( $this->reader->containers( $entry ) )->assets( $data ) );

                if( $path === null ) {
                    $paths[] = Utils::slugify( $this->reader->title( $entry, $data ) );
                }
            }
        }

        // the pages, assets and files used by the entries are fetched at once
        $this->prefetchPages( array_values( array_unique( array_filter( $paths ) ) ) );

        $assets = array_values( array_unique( $assets ) );
        $this->reader->prefetchAssets( array_values( array_filter( $assets, fn( $asset ) => preg_match( '/^[^:\/]+::/', $asset ) === 1 ) ) );
        $this->prefetchFiles( array_map( fn( $asset ) => $this->fileUrl( $asset ), $assets ) );

        return $entries;
    }


    /**
     * Returns the tree nodes in tree order with the position of their parent node in batches.
     *
     * @param array<int, array<string, mixed>> $tree
     * @param bool $root TRUE if the first entry of the tree is the home page
     * @return \Generator<int, array<int, array{entry: int|string|null, parent: int|null, children: bool, child: int|string|null}>> Batches of the tree nodes by position
     */
    protected function flatten( array $tree, bool $root = false ): \Generator
    {
        $batch = [];
        $pos = 0;
        $stack = [[array_values( $tree ), null, 0]];

        while( ( $item = array_pop( $stack ) ) !== null )
        {
            [$nodes, $parent, $idx] = $item;

            while( $idx < count( $nodes ) )
            {
                $node = $nodes[$idx++];
                $children = is_array( $node['children'] ?? null ) ? array_values( $node['children'] ) : [];
                $entry = $node['entry'] ?? null;
                // the home page has no children and Statamic redirects "@child" to the next top level entry instead
                $first = $root && $pos === 0 ? $nodes[1]['entry'] ?? null : $children[0]['entry'] ?? null;

                $batch[$pos] = [
                    'entry' => is_int( $entry ) || is_string( $entry ) ? $entry : null,
                    'parent' => $parent,
                    'children' => $children !== [],
                    // "@child" redirects point to the first child entry
                    'child' => is_int( $first ) || is_string( $first ) ? $first : null,
                ];

                if( $children !== [] )
                {
                    // the remaining siblings are continued after the children
                    $stack[] = [$nodes, $parent, $idx];
                    $stack[] = [$children, $pos, 0];
                }

                if( count( $batch ) >= static::TREE_SIZE )
                {
                    yield $batch;
                    $batch = [];
                }

                $pos++;

                if( $children !== [] ) {
                    break;
                }
            }
        }

        if( $batch ) {
            yield $batch;
        }
    }


    /**
     * Tests if the page of the entry imported before is kept because the entry wasn't changed since.
     *
     * Entries without URI and slug need their data for the URL of their page, the home entry is always imported.
     */
    protected function kept( \stdClass $entry ): bool
    {
        return ( $this->paths[$entry->id] ?? null ) !== null && ( $this->homeId === null || (string) $entry->id !== (string) $this->homeId )
            && $this->unchangedSince( $entry->id );
    }


    /**
     * Returns the time when the entry was modified last.
     *
     * @return int Timestamp, entries without modification time are always changed
     */
    protected function modified( \stdClass $entry ): int
    {
        $date = $this->date( $entry->updated_at ?? null );
        return $date && ( $time = strtotime( $date ) ) !== false ? $time : PHP_INT_MAX;
    }
}
