<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Jobs\InvalidatePages;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Aimeos\Nestedset\NestedSet;


class Resource
{
    use Concerns\Variants;

    public const MAX_RELOCATE = 100;


    /**
     * Creates a new element with version and attached files.
     *
     * Files attached to the version are derived from the element's content data.
     *
     * @param array<string, mixed> $input Element fields (type, name, lang, data)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addElement( array $input, ?Authenticatable $user = null ) : Element
    {
        Validation::limits( Element::class, $input );
        $input['data'] = Validation::element( $input['type'] ?? '', $input['data'] ?? [] );

        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $input, $editor, $user ) {

            $fileIds = self::elementFiles( $input, $user );

            $element = new Element();
            $element->setUniqueIds();
            $element->fill( $input );
            $element->editor = $editor;

            // Saves the element with its draft version so the draft (latest=true) row is indexed
            $element->draft( [
                'data' => $element->toArray(),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
            ], ['files' => $fileIds] );

            $element->files()->attach( $fileIds );
            $element->announce( 'added', $editor );

            return $element;
        } );
    }


    /**
     * Persists a prepared file as a new media item with its first version.
     *
     * The caller fills the file's path, mime, previews, name, lang, description
     * and transcription; this method stores it, creates the initial version,
     * indexes it and broadcasts the change.
     *
     * @param File $file Prepared file model (path/mime/previews/name set)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File
     */
    public static function addFile( File $file, ?Authenticatable $user = null ) : File
    {
        $editor = Utils::editor( $user );
        $tenant = Tenancy::value();
        $file->setUniqueIds();
        $file->checkPaths( [$file->path, ...(array) $file->previews] );

        try
        {
            Validation::limits( File::class, $file->only( ['lang', 'mime', 'name', 'path'] ) );

            $result = Utils::storageLock( $tenant,
                fn() => Utils::fileLock( $tenant, (string) $file->id, function() use ( $file, $editor ) {
                    $file->checkStored( [$file->path, ...(array) $file->previews] );

                    return Utils::transaction( function() use ( $file, $editor ) {
                        $file->editor = $editor;

                        // Saves the file with its draft version so the draft (latest=true) row is indexed
                        $file->draft( [
                            'lang' => $file->lang,
                            'editor' => $editor,
                            ...File::snapshot( $file->toArray() ),
                        ], [] );

                        return $file;
                    } );
                } ),
            );
        }
        catch( \Throwable $t )
        {
            $file->removePreviews()->removeFile();
            throw $t;
        }

        $result->announce( 'added', $editor );
        return $result;
    }


    /**
     * Creates a new page with version and attached relations.
     *
     * Files and elements attached to the page are derived from the content, meta and config data.
     *
     * @param array<string, mixed> $input Page fields (content/meta/config go into version aux), "lang" defaults to the app locale
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addPage( array $input, ?Authenticatable $user = null, ?string $ref = null, ?string $parent = null ) : Page
    {
        $input['lang'] = ( $input['lang'] ?? null ) ?: (string) config( 'app.locale', 'en' );
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::lockedTransaction( function() use ( $input, $editor, $ref, $parent, $user ) {

            $aux = [
                'content' => $input['content'] ?? [],
                'meta' => $input['meta'] ?? [],
                'config' => $input['config'] ?? [],
            ];
            $refs = self::refs( $aux, $user );

            $page = new Page();
            $page->setUniqueIds();
            $page->fill( $input );
            $page->editor = $editor;

            $page->position( $ref, $parent );

            // Saves the page with its draft version so the draft (latest=true) row is indexed
            $page->draft( [
                'data' => array_diff_key( $input, $aux ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => $aux,
            ], $refs );

            $page->files()->attach( $refs['files'] );
            $page->elements()->attach( $refs['elements'] );

            $page->announce( 'added', $editor );

            return $page;
        } );
    }


    /**
     * Copies a page and its sub-pages with all language variants.
     *
     * The latest version of each variant becomes the first version of its copy, element IDs
     * and hashes stay unchanged. Colliding paths get the language code appended.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Copy of the page in its source language
     */
    public static function copyPage( string $id, ?string $ref = null, ?string $parent = null, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        // the lock must last until all pages of the subtree are copied (about 0.05s per page
        // and 0.01s per additional variant, which are inserted in bulk)
        $node = Page::select( 'id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );
        $count = intdiv( $node->getRgt() - $node->getLft() + 1, 2 );
        $variants = PageVariant::whereIn( 'page_id', fn( $q ) => $q->select( 'id' )->from( 'cms_pages' )
            ->where( 'tenant_id', \Aimeos\Cms\Tenancy::value() )
            ->whereBetween( NestedSet::LFT, [$node->getLft(), $node->getRgt()] )
        )->count();
        $lifetime = (int) ceil( $count / 20 + max( 0, $variants - $count ) / 100 );

        $result = Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor, $user ) {

            /** @var Page $root */
            $root = Page::select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );

            $pages = Page::select( 'id', 'parent_id', 'source', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
                ->where( NestedSet::LFT, '>=', $root->getLft() )
                ->where( NestedSet::RGT, '<=', $root->getRgt() )
                ->orderBy( NestedSet::LFT )
                ->get();

            Page::checkBulk( $pages->count() );

            $variants = PageVariant::select( ['id', 'page_id', 'lang', 'latest_id', 'hashes', 'stale', ...PageVariant::FIELDS] )
                ->whereIn( 'page_id', $pages->pluck( 'id' )->all() )
                ->orderBy( 'lang' )->get();

            $versions = self::copyVersions( $variants );
            $known = self::knownPaths( $variants, $versions );
            $sizes = self::copySizes( self::pages( $pages ), $variants->pluck( 'page_id', 'page_id' )->all(), (string) $root->id );
            $variants = $variants->groupBy( 'page_id' );
            $copies = $next = $rows = [];
            $result = null;

            /** @var Page $orig */
            foreach( $pages as $orig )
            {
                $list = $variants->get( (string) $orig->id );
                $source = $list?->firstWhere( 'lang', $orig->source ) ?? $list?->first();

                if( !$list || !$source ) {
                    continue;
                }

                $page = null;

                foreach( $list->sortBy( fn( $v ) => $v->id === $source->id ? 0 : 1 ) as $variant )
                {
                    $version = $versions->get( (string) $variant->latest_id );
                    $data = (array) ( $version->data ?? $variant->only( PageVariant::FIELDS ) );
                    $aux = (array) ( $version->aux ?? self::variantAux( (string) $variant->id ) );

                    $data['lang'] = $variant->lang;
                    $data['domain'] = (string) ( $data['domain'] ?? $variant->domain );
                    $data['path'] = self::uniquePath( $data['domain'], (string) ( $data['path'] ?? $variant->path ), $variant->lang, $known );

                    $refs = $version ? [
                        'files' => $version->files->pluck( 'id' )->all(),
                        'elements' => $version->elements->pluck( 'id' )->all(),
                    ] : self::refs( $aux, $user );

                    // the other variants and their versions are inserted in bulk
                    if( $page !== null )
                    {
                        $rows[] = self::copyRows( $page, $variant, $data, $aux, $refs, $editor );
                        continue;
                    }

                    $page = new Page();
                    $page->forceFill( array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) ) + [
                        'source' => $variant->lang,
                        'hashes' => $variant->hashes,
                        'stale' => $variant->stale,
                        'editor' => $editor,
                    ] );

                    $pid = (string) $orig->parent_id;

                    // descendants are placed into the gap opened once for the whole subtree
                    if( $orig->id !== $root->id && isset( $sizes[(string) $orig->id], $copies[$pid], $next[$pid] ) )
                    {
                        $lft = $next[$pid];
                        $rgt = $lft + 2 * $sizes[(string) $orig->id] - 1;
                        $next[$pid] = $rgt + 1;

                        $page->rawNode( $lft, $rgt, $copies[$pid]->id, $copies[$pid]->getDepth() + 1 );
                        $page->save();
                    }
                    else
                    {
                        isset( $copies[$pid] ) && $orig->id !== $root->id
                            ? $page->appendToNode( $copies[$pid] )
                            : $page->position( $ref, $parent );

                        $page->save();

                        if( ( $size = $sizes[(string) $orig->id] ?? 1 ) > 1 && ( $rgt = $page->getRgt() ) !== null )
                        {
                            $page->newNestedSetQuery()->makeGap( $rgt, 2 * ( $size - 1 ) );
                            $page->refreshNode();
                        }
                    }

                    $copies[(string) $orig->id] = $page;
                    $next[(string) $orig->id] = $page->getLft() + 1;

                    $copy = self::addVersion( $page, $data, $aux, $editor, $user, $refs );
                    $result ??= $copy;
                }

                $page?->announce( 'added', $editor );
            }

            if( !$result ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variants', $id ) );
            }

            self::insertCopies( $rows );

            return [$result, $rows ? array_map( fn( Page $page ) => (string) $page->id, array_values( $copies ) ) : []];
        }, $lifetime );

        // the variants inserted in bulk aren't indexed by saving the models
        if( !empty( $result[1] ) && Scout::usesSearchIndex() ) {
            Scout::index( Page::class, $result[1] );
        }

        return $result[0];
    }


    /**
     * Returns the records of the page variant copy and its first version.
     *
     * @param Page $page Copied page with its source variant
     * @param PageVariant $variant Original page variant
     * @param array<string, mixed> $data Version data of the copied variant
     * @param array<string, mixed> $aux Content, meta and config of the copied variant
     * @param array<string, array<int, string>> $refs File and element IDs referenced by the version
     * @param string $editor Name of the editor
     * @return array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>} Records to insert
     */
    protected static function copyRows( Page $page, PageVariant $variant, array $data, array $aux, array $refs, string $editor ) : array
    {
        $fields = array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) );
        $tenant = \Aimeos\Cms\Tenancy::value();

        $copy = ( new PageVariant() )->forceFill( array_filter( $fields, fn( $v ) => $v !== null ) + [
            'tenant_id' => $tenant,
            'page_id' => $page->id,
            'hashes' => (array) $variant->hashes,
            'stale' => (bool) $variant->stale,
            'editor' => $editor,
        ] );
        $copy->setUniqueIds();

        // same as the "saving" event of the versions which isn't fired for bulk inserts
        $version = ( new Version() )->forceFill( [
            'tenant_id' => $tenant,
            'versionable_type' => PageVariant::class,
            'versionable_id' => $copy->id,
            'lang' => $data['lang'] ?? $variant->lang,
            'data' => array_replace( $data, ['scheduled' => 0] ),
            'aux' => $aux,
            'editor' => $editor,
        ] );
        $version->setUniqueIds();
        $version->setCreatedAt( $version->freshTimestamp() );

        $time = $copy->freshTimestamp();
        $copy->setAttribute( 'latest_id', $version->id );
        $copy->setCreatedAt( $time )->setUpdatedAt( $time );

        return ['variant' => $copy->getAttributes(), 'version' => $version->getAttributes(), 'refs' => array_filter( $refs )];
    }


    /**
     * Inserts the copied page variants, their versions and the references of the versions.
     *
     * @param array<int, array{variant: array<string, mixed>, version: array<string, mixed>, refs: array<string, array<int, string>>}> $rows Records to insert
     */
    protected static function insertCopies( array $rows ) : void
    {
        $pivots = [];
        $version = new Version();

        foreach( $rows as $row )
        {
            foreach( $row['refs'] as $relation => $ids )
            {
                /** @var \Illuminate\Database\Eloquent\Relations\BelongsToMany<Base, Version> $rel */
                $rel = $version->{$relation}();

                foreach( array_unique( $ids ) as $refId ) {
                    $pivots[$rel->getTable()][] = [$rel->getForeignPivotKeyName() => $row['version']['id'], $rel->getRelatedPivotKeyName() => $refId];
                }
            }
        }

        // versions first because of the foreign keys of the references
        self::insertRows( Version::query()->toBase(), array_column( $rows, 'version' ) );
        self::insertRows( PageVariant::query()->toBase(), array_column( $rows, 'variant' ) );

        foreach( $pivots as $table => $list ) {
            self::insertRows( $version->getConnection()->table( $table ), $list );
        }
    }


    /**
     * Inserts the records in chunks which stay below the parameter limits of the databases.
     *
     * @param \Illuminate\Database\Query\Builder $query Query builder for the table
     * @param array<int, array<string, mixed>> $records Records to insert
     */
    protected static function insertRows( \Illuminate\Database\Query\Builder $query, array $records ) : void
    {
        $groups = [];

        // bulk inserts require the same columns in each record
        foreach( $records as $record )
        {
            ksort( $record );
            $groups[implode( ',', array_keys( $record ) )][] = $record;
        }

        foreach( $groups as $list )
        {
            // SQL Server allows up to 2100 parameters per statement
            foreach( array_chunk( $list, max( 1, intdiv( 2000, count( $list[0] ) ) ) ) as $chunk ) {
                ( clone $query )->insert( $chunk );
            }
        }
    }


    /**
     * Returns the number of copied pages in the subtree of each page copied below the root page.
     *
     * Pages are only copied if they have variants and their parent page is copied too.
     *
     * @param Collection<int, Page> $pages Pages of the subtree in tree order
     * @param array<string, mixed> $available IDs of the pages with variants as keys
     * @param string $rootId ID of the root page of the subtree
     * @return array<string, int> Number of copied pages including the page itself by page ID
     */
    protected static function copySizes( Collection $pages, array $available, string $rootId ) : array
    {
        $sizes = [];

        foreach( $pages as $page )
        {
            $id = (string) $page->id;

            if( isset( $available[$id] ) && ( $id === $rootId || isset( $sizes[(string) $page->parent_id] ) ) ) {
                $sizes[$id] = 1;
            }
        }

        foreach( $pages->reverse() as $page )
        {
            $id = (string) $page->id;

            if( $id !== $rootId && isset( $sizes[$id], $sizes[(string) $page->parent_id] ) ) {
                $sizes[(string) $page->parent_id] += $sizes[$id];
            }
        }

        return $sizes;
    }


    /**
     * Returns the latest versions of the page variants with their file and element references.
     *
     * @param Collection<int, PageVariant> $variants Page variants
     * @return Collection<string, Version> Versions by ID
     */
    protected static function copyVersions( Collection $variants ) : Collection
    {
        $versions = [];

        foreach( $variants->pluck( 'latest_id' )->filter()->chunk( 500 ) as $chunk )
        {
            foreach( Version::with( ['files:id', 'elements:id'] )->whereIn( 'id', $chunk->all() )->get() as $version ) {
                $versions[(string) $version->id] = $version;
            }
        }

        return collect( $versions );
    }


    /**
     * Returns the content, meta and config of a page variant without versions.
     *
     * @param string $id Page variant ID
     * @return array<string, mixed> Content, meta and config data
     */
    protected static function variantAux( string $id ) : array
    {
        $variant = PageVariant::select( 'id', 'content', 'meta', 'config' )->findOrFail( $id );
        return ['content' => $variant->content, 'meta' => $variant->meta, 'config' => $variant->config];
    }


    /**
     * Returns which of the paths the copies of the page variants are likely to use already exist.
     *
     * @param Collection<int, PageVariant> $variants Page variants to copy
     * @param Collection<string, Version> $versions Latest versions of the variants by ID
     * @return array<string, array<string, bool>> Known paths by domain, TRUE if used
     */
    protected static function knownPaths( Collection $variants, Collection $versions ) : array
    {
        $known = [];

        foreach( $variants as $variant )
        {
            $data = (array) ( $versions->get( (string) $variant->latest_id )->data ?? [] );
            $domain = (string) ( $data['domain'] ?? $variant->domain );
            $path = (string) ( $data['path'] ?? $variant->path );

            $known[$domain][$path] = false;
            $known[$domain][$path === '' ? $variant->lang : $path . '-' . $variant->lang] = false;
        }

        foreach( $known as $domain => $paths )
        {
            foreach( array_chunk( array_map( 'strval', array_keys( $paths ) ), 500 ) as $chunk )
            {
                foreach( PageVariant::withTrashed()->where( 'domain', $domain )->whereIn( 'path', $chunk )->pluck( 'path' ) as $path ) {
                    $known[$domain][(string) $path] = true;
                }
            }
        }

        return $known;
    }


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
        return self::lifecycle( $model, $ids, 'dropped', $user, $fields );
    }


    /**
     * Invalidates the routes of the given Page or PageVariant models, grouped by domain.
     *
     * Other values are ignored so lifecycle collections can be passed without additional filtering.
     *
     * @param iterable<array-key, mixed> $pages Candidate Page or PageVariant models
     */
    public static function invalidatePages( iterable $pages ) : void
    {
        $paths = [];

        foreach( $pages as $page ) {
            if( $page instanceof Page || $page instanceof PageVariant ) {
                $paths[(string) $page->domain][] = (string) $page->path;
            }
        }

        foreach( $paths as $domain => $domainPaths ) {
            PageInvalidated::dispatch( (string) $domain, array_values( array_unique( $domainPaths ) ) );
        }
    }


    /**
     * Invalidates the published pages using the Elements, the Files directly or the Files through an Element.
     *
     * The pages are invalidated by queued jobs, so publishing content shared by many pages doesn't slow down
     * the request. The jobs get the page variant IDs because the references of purged items don't exist any more when they run.
     *
     * @param array<string> $elements Element UUIDs
     * @param array<string> $files File UUIDs
     */
    public static function invalidateRefs( array $elements, array $files = [] ) : void
    {
        // The page table isn't joined because MySQL can't optimize IN() subqueries with UNION, which would scan all pages.
        // Deleted pages are skipped by the queued jobs instead.
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $queries = [];

        if( $elements ) {
            $queries[] = $db->table( 'cms_page_element' )->select( 'variant_id' )->whereIn( 'element_id', $elements );
        }

        if( $files )
        {
            $queries[] = $db->table( 'cms_page_file' )->select( 'variant_id' )->whereIn( 'file_id', $files );
            $queries[] = $db->table( 'cms_element_file as ef' )
                ->join( 'cms_page_element as pe', 'pe.element_id', '=', 'ef.element_id' )
                ->select( 'pe.variant_id' )->whereIn( 'ef.file_id', $files );
        }

        if( !$query = array_shift( $queries ) ) {
            return;
        }

        foreach( $queries as $union ) {
            $query->unionAll( $union );
        }

        // Jobs dispatched after commit are kept in memory until then, so reading the IDs in chunks wouldn't save memory
        foreach( $query->pluck( 'variant_id' )->unique()->chunk( 1000 ) as $chunk ) {
            InvalidatePages::dispatch( Tenancy::value(), $chunk->map( strval( ... ) )->values()->all() )->afterCommit();
        }
    }


    /**
     * Moves a page to a new position in the tree and broadcasts the change.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     */
    public static function movePage( string $id, ?string $ref = null, ?string $parent = null, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        $page = Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor ) {

            /** @var Page $page */
            $page = Page::withTrashed()->findOrFail( $id );
            $page->editor = $editor;

            $page->position( $ref, $parent );

            Page::withoutSyncingToSearch( fn() => $page->save() );

            return $page;
        } );

        $page->announce( 'moved', $editor );
        return $page;
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
        return self::lifecycle( $model, $ids, 'purged', $user, $fields );
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
        return self::lifecycle( $model, $ids, 'restored', $user, $fields );
    }


    /**
     * Moves the managed paths of several Files to another logical disk under tenant and File locks.
     *
     * @param array<string> $ids File UUIDs
     * @param string $disk Target logical disk, either "public" or "private"
     * @param Authenticatable|null $user Authenticated user authorizing and recording the relocation
     * @return Collection<int, File>
     * @throws Exception If permissions, path ownership, remote paths, or storage verification prevent relocation
     */
    public static function relocateFiles( array $ids, string $disk,
        ?Authenticatable $user = null ) : Collection
    {
        File::diskName( $disk );
        $ids = array_values( array_unique( $ids ) );

        if( count( $ids ) > self::MAX_RELOCATE ) {
            throw new Exception( sprintf(
                'No more than %d files may be relocated at once.',
                self::MAX_RELOCATE,
            ) );
        }

        if( !$ids ) {
            return ( new File() )->newCollection();
        }

        $tenant = Tenancy::value();
        $editor = Utils::editor( $user );
        $changed = [];

        $found = File::whereIn( 'id', $ids )->pluck( 'id' )->map( strval(...) )->flip();

        foreach( $ids as $id ) {
            if( !$found->has( $id ) ) {
                File::findOrFail( $id );
            }
        }

        Permission::check( 'file:relocate', $user );

        try
        {
            return Utils::storageLock( $tenant, function() use ( &$changed, $disk, $editor, $ids, $tenant ) {
                $result = [];

                foreach( $ids as $id )
                {
                    $result[] = Utils::fileLock( $tenant, $id, function() use (
                        &$changed, $disk, $editor, $id
                    ) {
                        $file = File::findOrFail( $id );

                        if( $file->getAttribute( 'disk' ) === $disk ) {
                            return $file;
                        }

                        $file->relocate( $disk, $editor );
                        $changed[$id] = $file;

                        return $file;
                    } );
                }

                return ( new File() )->newCollection( $result );
            } );
        }
        finally
        {
            if( $changed )
            {
                $files = ( new File() )->newCollection( array_values( $changed ) );

                self::invalidateRefs( [], $files->modelKeys() );
                File::announceMany( $files, 'saved', $editor, ['disk' => $disk], true );
            }
        }
    }


    /**
     * Updates an existing element with a new version.
     *
     * @param string $id Element UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If element not found
     */
    public static function saveElement( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null ) : Element
    {
        /** @var Element $element */
        $element = Element::withTrashed()->with( 'latest' )->findOrFail( $id );
        $type = $input['type'] ?? $element->type;
        Validation::limits( Element::class, $input );

        if( isset( $input['data'] ) ) {
            $input['data'] = Validation::element( $type, $input['data'], isset( $input['type'] ) );
        } elseif( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $element, $input, $editor, $latestId, $user ) {

            self::applyElement( $element, $input, $editor, $latestId, $user );
            $element->announce( 'saved', $editor );
            self::pruneVersions( Element::class, [$element->id] );

            return $element;
        } );
    }


    /**
     * Applies the same input to several shared content elements at once.
     *
     * @param array<string> $ids Element IDs to update
     * @param array<string, mixed> $input Fields applied to every element (e.g. ['lang' => 'de'])
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     * @throws Exception If the input carries the element-specific "type" or "data"
     */
    public static function bulkElement( array $ids, array $input, ?Authenticatable $user = null ) : array
    {
        if( isset( $input['type'] ) || isset( $input['data'] ) ) {
            throw new Exception( 'Bulk edits cannot change the type or data of an element' );
        }

        Validation::limits( Element::class, $input );

        return self::bulk( Element::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user ) : ?Element {
            $element = Element::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id );
            return $element ? self::applyElement( $element, $input, $editor, null, $user, $refs[$element->latest_id ?? ''] ?? null ) : null;
        } );
    }


    /**
     * Creates the first version of a new page variant.
     *
     * @param Page $page Page with the variant the version belongs to
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed> $aux Content, meta and config
     * @param string $editor Name of the editing user
     * @param Authenticatable|null $user Authenticated user accepting the references
     * @param array{files: array<string>, elements: array<string>}|null $refs Known file and element references
     * @return Page Page with the new version as "latest"
     */
    protected static function addVersion( Page $page, array $data, array $aux, string $editor,
        ?Authenticatable $user = null, ?array $refs = null ) : Page
    {
        $page->draft( [
            'data' => $data,
            'lang' => $data['lang'] ?? $page->lang,
            'editor' => $editor,
            'aux' => $aux,
        ], $refs ?? self::refs( $aux, $user ) );

        return $page;
    }


    /**
     * Applies and announces a lifecycle action while preserving Page tree semantics and route invalidation.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param 'dropped'|'purged'|'restored' $action
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    protected static function lifecycle( string $model, array $ids, string $action,
        ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );
        $editor = Utils::editor( $user );
        $isPage = $model === Page::class;
        $announce = $model !== File::class || $action !== 'purged' || count( $ids ) === 1;
        $pages = collect();
        $variants = collect();

        if( !$isPage ) {
            sort( $ids, SORT_STRING );
        }

        $apply = function( array $ids ) use ( $action, $announce, $editor, $fields, $isPage, $model, &$pages, &$variants ) {
            $query = $model::withTrashed()->whereIn( 'id', $ids );

            if( $isPage ) {
                $query->select( $fields ? [
                    ...Page::REQUIRED_COLUMNS,
                    ...array_intersect( Page::RESPONSE_COLUMNS, $fields ),
                ] : Page::SELECT_COLUMNS );
            } elseif( $fields ) {
                $instance = new $model();
                $required = $instance->qualifyColumns( ['id', 'tenant_id', 'latest_id', 'deleted_at'] );

                if( $model === File::class && $action === 'purged' ) {
                    array_push( $required, 'path', 'previews' );
                }

                if( $action === 'restored' ) {
                    array_push( $required, ...( $model === File::class ? File::SELECT_COLUMNS : Element::SELECT_COLUMNS ) );
                }

                $response = [...$instance->getVisible(), 'editor', 'created_at', 'updated_at'];
                $query->select( array_values( array_unique( [
                    ...$required,
                    ...array_intersect( $response, $fields ),
                ] ) ) );
            }

            if( !$isPage ) {
                $query->orderBy( 'id' )->lockForUpdate();
            }

            /** @var \Illuminate\Database\Eloquent\Collection<int, Base> $items */
            $items = $query->get();

            if( $items->isEmpty() ) {
                return $items;
            }

            // Before purging because the references are removed with the items, dispatched after commit
            if( !$isPage )
            {
                // Pages using deleted items were invalidated when they were deleted
                $refs = $action === 'purged' ? $items->reject( fn( Base $item ) => $item->trashed() ) : $items;
                $keys = array_map( strval( ... ), $refs->modelKeys() );
                $model === File::class ? self::invalidateRefs( [], $keys ) : self::invalidateRefs( $keys );
            }

            if( $isPage && ( $action !== 'restored' || Scout::usesExternalSearch() ) ) {
                $pages = self::pageSubtree( $items )->select( 'id', 'tenant_id', NestedSet::LFT )
                    ->orderBy( NestedSet::LFT )->lockForUpdate()->get();
            } elseif( $isPage ) {
                $pages = $items;
            }

            // the URLs of all language variants are invalidated and pages are indexed per variant,
            // so collect the variants before they are removed
            if( $isPage && $action !== 'restored' ) {
                foreach( $pages->pluck( 'id' )->chunk( 500 ) as $chunk ) {
                    $variants->push( ...PageVariant::withTrashed()->whereIn( 'page_id', $chunk->all() )->get( ['id', 'domain', 'path'] ) );
                }
            }

            // Purged files are announced with their publication state, which is removed with their versions
            if( $action === 'purged' && $model === File::class && $announce && Base::announces( Purged::class ) ) {
                $items->load( ['latest' => fn( $query ) => $query->select( 'id', 'published', 'publish_at', 'created_at' )] );
            }

            // The versions are removed together with the page variants, the route is in data but not the content in aux
            if( $action === 'purged' && $isPage && $announce && Base::announces( Purged::class ) ) {
                $items->load( ['latest' => fn( $query ) => $query->select( [...Version::SELECT_COLUMNS, 'publish_at', 'created_at'] )] );
            }

            $model::lifecycle( $items, $action, $editor );
            return $items;
        };

        $batch = $isPage
            ? fn() => $apply( $ids )
            : fn() => collect( $ids )->chunk( 100 )->reduce(
                fn( Collection $items, Collection $chunk ) => $items->concat( $apply( $chunk->all() ) ),
                collect(),
            );

        $run = $isPage && $action !== 'dropped'
            ? fn() => Utils::lockedTransaction( $batch )
            : fn() => Utils::transaction( $batch );

        $change = fn() => Scout::mute( [$model], function() use ( $action, $apply, $ids, $model, $run ) {
            try {
                return $run();
            } catch( \Exception $e ) {
                if( $model !== File::class || $action !== 'purged' ) {
                    throw $e;
                }
            }

            $items = collect();

            foreach( $ids as $id ) {
                try {
                    $items->push( ...Utils::transaction( fn() => $apply( [$id] ) ) );
                } catch( \Exception $e ) {
                    report( $e );
                }
            }

            return $items;
        } );
        $items = $model::locked( $change );

        if( $isPage && $action !== 'restored' )
        {
            self::invalidatePages( $variants );
        }

        if( $action === 'dropped' ) {
            Base::announceMany( $items, $action, $editor, [
                'deleted_at' => (string) ( $items->first()->deleted_at ?? now() ),
            ] );
        } elseif( $action === 'restored' ) {
            Base::announceMany( $items, $action, $editor, ['deleted_at' => null] );
        } elseif( $action === 'purged' ) {
            Base::announceMany( $items, $action, $editor, bulk: !$announce );
        }

        /** @var array<string> $changed */
        $changed = ( $isPage ? $pages : $items )->pluck( 'id' )->all();

        if( $isPage && $action === 'dropped' && !Scout::usesExternalSearch() ) {
            return $items;
        } elseif( $action === 'restored' ) {
            // all variants of restored pages change
            Scout::index( $model, $changed, $isPage ? null : $items );
        } elseif( $action === 'dropped' && config( 'scout.soft_delete' ) ) {
            Scout::index( $model, $changed );
        } else {
            // pages are indexed per variant
            $keys = $isPage ? ( Scout::usesSearchIndex() ? $variants->pluck( 'id' )->map( strval( ... ) )->all() : [] ) : $changed;
            Scout::unindex( $model, $keys );
        }

        return $items;
    }


    /**
     * Returns the query for the complete subtrees of the given non-empty list of pages, including trashed ones.
     *
     * @param iterable<\Illuminate\Database\Eloquent\Model> $roots Pages with their nested set bounds
     * @return \Aimeos\Nestedset\QueryBuilder<Page>
     */
    public static function pageSubtree( iterable $roots ) : \Aimeos\Nestedset\QueryBuilder
    {
        $list = self::pageRoots( $roots );

        /** @var \Aimeos\Nestedset\QueryBuilder<Page> */
        return Page::withTrashed()->where( function( $query ) use ( $list ) {
            $query->whereRaw( '1 = 0' ); // no roots must match no pages

            foreach( $list as $root ) {
                $query->whereDescendantOrSelf( $root, 'or' );
            }
        } );
    }


    /**
     * Returns the pages in tree order without the ones within the subtree of another given page.
     *
     * @param iterable<\Illuminate\Database\Eloquent\Model> $roots Pages with their nested set bounds
     * @return Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    protected static function pageRoots( iterable $roots ) : Collection
    {
        $right = null;

        return collect( $roots )
            ->sortBy( fn( $root ) => (int) $root->getAttribute( NestedSet::LFT ) )
            ->filter( function( $root ) use ( &$right ) {
                if( $right !== null && (int) $root->getAttribute( NestedSet::LFT ) < $right ) {
                    return false;
                }

                $right = (int) $root->getAttribute( NestedSet::RGT );
                return true;
            } )
            ->values();
    }


    /**
     * Ingests optional file data, updates metadata, and creates a conflict-aware version.
     *
     * Storage work completes before the transaction; the File lock then verifies the prepared paths and logical disk.
     *
     * @param string $id File UUID
     * @param array<string, mixed> $input File fields to update
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param UploadedFile|null $upload File upload to store
     * @param UploadedFile|false|null $preview Preview upload, false to clear, null for auto-detect
     * @return File
     */
    public static function saveFile( string $id, array $input, ?Authenticatable $user = null,
        ?string $latestId = null, ?UploadedFile $upload = null, UploadedFile|false|null $preview = null ) : File
    {
        Validation::limits( File::class, $input );
        $editor = Utils::editor( $user );
        $tenant = Tenancy::value();

        // Prepare storage and remote image work before opening the transaction.
        $tmp = ( new File() )->forceFill( ['id' => $id] );
        $currentPath = null;
        $stored = null;
        $storedMime = null;
        $storedPreviews = null;
        $disk = 'public';
        $prepared = false;

        try
        {
            $newPath = File::checkPath( $input['path'] ?? null );

            if( $upload || $preview instanceof UploadedFile || $newPath !== null )
            {
                /** @var File $current */
                $current = self::file()->findOrFail( $id );
                $currentPath = (string) ( $current->latest?->data->path ?? $current->path );
                $disk = (string) $current->getAttribute( 'disk' );
                $tmp->disk = $disk;
                $tmp->name = (string) ( $input['name'] ?? $current->latest?->data->name ?? $current->name );
                $privateRemote = $disk === 'private' && str_starts_with( (string) $newPath, 'http' );

                if( $newPath !== null && !$privateRemote ) {
                    $current->checkPaths( [$newPath] );
                }
            }

            $source = $upload ?? ( $newPath !== null && $newPath !== $currentPath ? $newPath : null );

            if( $source !== null || $preview instanceof UploadedFile )
            {
                $prepared = true;
                $tmp->ingest( $source, $preview instanceof UploadedFile ? $preview : null );
                $local = $upload !== null || $disk === 'private' && is_string( $source )
                    && str_starts_with( $source, 'http' );
                $stored = $local ? $tmp->path : null;
                $storedMime = $source !== null ? (string) $tmp->mime : null;

                if( $preview instanceof UploadedFile
                    || $source instanceof UploadedFile && str_starts_with( (string) $source->getMimeType(), 'image/' )
                    || is_string( $source ) && str_starts_with( $source, 'http' )
                ) {
                    $storedPreviews = (array) $tmp->previews;
                }
                elseif( is_string( $source ) && $preview === null && !isset( $input['previews'] ) ) {
                    // previews of the previous image don't belong to the new path, they are created for supported images
                    $storedPreviews = $tmp->syncPreviews( [] ) ?? [];
                }
            }

            $file = Utils::storageLock( $tenant,
                fn() => Utils::fileLock( $tenant, $id, function() use ( $id, $input, $editor, $latestId,
                    $preview, $stored, $storedMime, $storedPreviews, $disk, $prepared ) {
                        /** @var File $file */
                        $file = self::file()->findOrFail( $id );

                        if( $prepared && $file->getAttribute( 'disk' ) !== $disk ) {
                            throw new Exception( 'File disk changed while saving; retry the request' );
                        }

                        $file->checkStored( [$stored, ...( $storedPreviews ?? [] )] );

                        return Utils::transaction( function() use ( $file, $input, $editor, $latestId,
                            $preview, $stored, $storedMime, $storedPreviews ) {
                            self::applyFile( $file, $input, $editor, $latestId, $stored,
                                $storedPreviews, $preview, $storedMime );
                            self::pruneVersions( File::class, [$file->id] );

                            return $file;
                        } );
                    } ),
            );
        }
        catch( \Throwable $t )
        {
            try {
                ( new File() )->forceFill( [
                    'id' => $id,
                    'disk' => $disk,
                    'path' => $stored,
                    'previews' => $storedPreviews ?? [],
                ] )->removePreviews()->removeFile();
            } catch( \Throwable $cleanup ) {
                report( $cleanup );
            }

            throw $t;
        }

        $file->announce( 'saved', $editor );
        return $file;
    }


    /**
     * Applies the same input to several files at once.
     *
     * @param array<string> $ids File IDs to update
     * @param array<string, mixed> $input Fields applied to every file (e.g. ['lang' => 'de'])
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     * @throws Exception If the input carries the file-specific "path" or "previews"
     */
    public static function bulkFile( array $ids, array $input, ?Authenticatable $user = null ) : array
    {
        if( isset( $input['path'] ) || isset( $input['previews'] ) ) {
            throw new Exception( 'Bulk edits cannot change the path or previews of a file' );
        }

        Validation::limits( File::class, $input );

        return self::bulk( File::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input ) : ?File {
            $file = self::file()->lockForUpdate()->find( $id );
            return $file ? self::applyFile( $file, $input, $editor ) : null;
        } );
    }


    /**
     * Saves the same input to several items of one type as a single best-effort batch.
     *
     * @param class-string<Element>|class-string<File>|class-string<Page> $model Model class being saved
     * @param array<string> $ids Item ids to save, in processing order
     * @param array<string, mixed> $input Shared fields applied to every item
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param \Closure(string, array<string, array<string, array<string>>>, string): (Page|File|Element|null) $save Loads one locked row and applies the change using the prefetched references
     * @param (\Closure(array<string>): array<string, array<string, array<string>>>)|null $refs Prefetches the references of the latest versions copied to the new ones, NULL uses the references of the model
     * @param array<string, mixed> $extra Additional values reported in the result and event data, e.g. the language
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    protected static function bulk( string $model, array $ids, array $input, ?Authenticatable $user, \Closure $save, ?\Closure $refs = null,
        array $extra = [] ) : array
    {
        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $editor = Utils::editor( $user );
        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );

        $keys = $langs = [];
        $refs ??= fn( array $chunk ) => $model::refs( $chunk );

        // suppress Scout's per-save reindex; the whole batch is reindexed once below
        $latest = Scout::mute( [$model], function() use ( $ids, $model, $save, $refs, $editor, &$keys, &$langs ) {
            $result = [];

            foreach( array_chunk( $ids, 50 ) as $chunk )
            {
                $prefetched = $refs( $chunk );

                foreach( $chunk as $id )
                {
                    try
                    {
                        /** @var Page|File|Element|null $item */
                        $item = $model::locked( fn() => Utils::transaction( fn() => $save( $id, $prefetched, $editor ) ) );

                        if( $item ) {
                            $result[(string) $item->id] = (string) $item->latest_id;
                            $keys[] = $item->getVersionKey();
                        }

                        if( $item instanceof Page ) {
                            $langs[(string) $item->id] = (string) $item->lang;
                        }
                    }
                    catch( \Exception $e )
                    {
                        report( $e );
                    }
                }
            }

            return $result;
        } );

        $saved = array_keys( $latest );

        // reindex the saved items once, chunked like cms:index; drop the soft-delete scope so
        // trashed items (recursive saves include them) are reindexed regardless of scout.soft_delete
        if( $saved ) {
            Scout::index( $model, $saved );
        }

        $result = [
            'ids' => $saved,
            'latest' => $latest,
            'data' => $input + $extra + ['published' => false, 'updated_at' => (string) now()],
            'failed' => count( $ids ) - count( $saved ),
        ];

        Base::announceBulk( strtolower( class_basename( $model ) ), $result['ids'], $result['latest'], $result['data'], $editor, langs: $langs );

        // versions belong to the version key, e.g. the page variant
        $model::locked( fn() => self::pruneVersions( $model, $keys ) );

        return $result;
    }


    /**
     * Returns the file query with the latest version columns required for saving.
     *
     * @return \Illuminate\Database\Eloquent\Builder<File> File query including trashed files
     */
    protected static function file() : \Illuminate\Database\Eloquent\Builder
    {
        return File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor' )] );
    }


    /**
     * Three-way merges input into an already loaded File and creates its new latest version.
     *
     * Shared by saveFile() (single) and bulkFile() (bulk). Must run inside a transaction.
     *
     * @param File $orig File model with the "latest" relation loaded
     * @param array<string, mixed> $input File fields to update (merged with latest version)
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param string|null $stored Path of an already stored upload, if any
     * @param array<int|string, mixed>|null $storedPreviews Previews generated for the stored upload, if any
     * @param UploadedFile|false|null $preview False to clear previews, otherwise null after preparation
     * @param string|null $storedMime MIME type detected while preparing the new path
     * @return File Updated file with the new version as "latest"
     */
    protected static function applyFile( File $orig, array $input, string $editor, ?string $latestId = null,
        ?string $stored = null, ?array $storedPreviews = null, UploadedFile|false|null $preview = null,
        ?string $storedMime = null ) : File
    {
        $previews = $orig->latest?->data->previews ?? $orig->previews;
        $path = $orig->latest?->data->path ?? $orig->path;

        $file = clone $orig;

        $input = File::snapshot( $input );
        [$data, $aux, $diffs] = Merge::file( $orig, $input['data'], $input['aux'], $latestId );
        $file->fill( $data + $aux );

        $paths = array_map( File::checkPath( ... ), array_values( (array) ( $input['data']['previews'] ?? [] ) ) );
        array_push( $paths, $stored, ...array_values( $storedPreviews ?? [] ) );

        $file->previews = $input['data']['previews'] ?? $previews;
        $file->path = $stored ?? File::checkPath( $input['data']['path'] ?? null ) ?? $path;

        if( isset( $input['data']['path'] ) ) {
            $paths[] = $file->path;
        }

        $orig->checkPaths( $paths );
        $file->editor = $editor;

        if( $file->path !== $path )
        {
            $file->mime = $storedMime ?? Utils::mimetype( $file->path );

            Utils::checkMimetype( (string) $file->mime );
        }

        if( $storedPreviews !== null ) {
            $file->previews = $storedPreviews;
        } elseif( $preview === false ) {
            $file->previews = [];
        }

        $snapshot = File::snapshot( $file->toArray() );

        $orig->draft( [
            'lang' => $file->lang,
            'editor' => $editor,
            'data' => $snapshot['data'],
            'aux' => array_replace( $snapshot['aux'], $aux ),
        ], [], $diffs );

        return $orig;
    }


    /**
     * Updates an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @param string|null $lang Language of the page variant or null for the source variant
     * @param bool $restore TRUE if the input restores an old version, which marks translations as outdated
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     * @throws Exception If the new source language in $input['source'] has no variant
     */
    public static function savePage( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null,
        ?string $lang = null, bool $restore = false ) : Page
    {
        $source = isset( $input['source'] ) ? (string) $input['source'] : null;
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $id, $input, $user, $editor, $latestId, $lang, $source, $restore ) {

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang )->with( 'latest' )->findOrFail( $id );

            if( !empty( $input ) || $source === null )
            {
                self::applyPage( $page, $input, $editor, $latestId, $user );
                $page->announce( 'saved', $editor );
                self::pruneVersions( Page::class, [$page->getVersionKey()] );
            }

            // a restored old translation may not match the current source anymore
            if( $restore && !$page->isSourceVariant() )
            {
                PageVariant::whereKey( $page->variant_id )->toBase()->update( ['stale' => true] );
                $page->forceFill( ['stale' => true] )->syncOriginal();
            }

            if( $source !== null && $source !== $page->source )
            {
                self::setSource( (string) $page->id, $source, $user );

                $variant = PageVariant::withTrashed()->findOrFail( $page->variant_id, ['id', 'hashes', 'stale'] );
                $page->forceFill( ['source' => $source, 'hashes' => $variant->hashes, 'stale' => $variant->stale] )->syncOriginal();
            }

            return $page;
        } );
    }


    /**
     * Applies the same partial input to multiple pages, optionally including all sub-pages.
     *
     * @param array<string> $ids Page IDs to update
     * @param array<string, mixed> $input Partial page input applied to every page
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $descendants TRUE to also update all sub-pages of the given pages
     * @param string|null $lang Language of the page variants to update or NULL for the source variants
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public static function bulkPage( array $ids, array $input, ?Authenticatable $user = null, bool $descendants = false,
        ?string $lang = null ) : array
    {
        $input = Validation::page( $input, $user );

        // recursive save: expand to the whole subtree in depth-first order (defaultOrder() is the
        // nested-set pre-order traversal) so each parent is saved before its children
        if( $descendants && $ids )
        {
            Page::checkBulk( count( array_unique( $ids ) ) );
            $roots = self::pageRoots( Page::withTrashed()->whereIn( 'id', $ids )->get( ['id', NestedSet::LFT, NestedSet::RGT] ) );
            $ids = [];

            // disjoint chunks in tree order keep the pre-order traversal across chunks
            foreach( $roots->chunk( 50 ) as $chunk )
            {
                /** @var array<string> $ids */
                $ids = array_merge( $ids, self::pageSubtree( $chunk )->defaultOrder()
                    ->limit( Page::MAX_BULK + 1 - count( $ids ) )->pluck( 'id' )->all() );

                if( count( $ids ) > Page::MAX_BULK ) {
                    break;
                }
            }
        }

        // the references of the latest versions are only copied if no content, meta or config is saved
        $refs = !array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) )
            ? fn( array $chunk ) => Page::refs( $chunk, $lang )
            : fn( array $chunk ) => [];

        return self::bulk( Page::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user, $lang ) : ?Page {

            // pages without a variant in the language are skipped
            if( !( $page = Page::withTrashed()->language( $lang, true )->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            // bulk only creates new draft versions, so the cached published output is unchanged
            self::applyPage( $page, $input, $editor, null, $user, $refs[$page->latest_id ?? ''] ?? null );

            return $page;
        }, $refs, $lang !== null ? ['lang' => $lang] : [] );
    }


    /**
     * Creates a new version for an already loaded page from the given input.
     *
     * Shared by savePage() (single) and bulkPage() (bulk) so the versioning, merge
     * and reference handling stays in one place. Must run inside a transaction.
     *
     * @param Page $page Page model with the "latest" relation loaded
     * @param array<string, mixed> $input Validated page input
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param Authenticatable|null $user Current user
     * @param array<string, array<string>>|null $refs Prefetched references of the latest version to copy
     */
    protected static function applyPage( Page $page, array $input, string $editor,
        ?string $latestId = null, ?Authenticatable $user = null, ?array $refs = null ) : void
    {
        $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
        $data = array_diff_key( $input, $aux );
        $accepts = (bool) $aux;

        if( isset( $input['lang'] ) && $input['lang'] !== $page->lang && PageVariant::withTrashed()
            ->where( 'page_id', $page->id )->where( 'lang', $input['lang'] )->exists()
        ) {
            throw new Exception( sprintf( 'The page already has a variant in "%1$s"', $input['lang'] ) );
        }

        [$data, $aux, $diffs] = Merge::page( $page, $data, $aux, $latestId, $user );

        $data['domain'] ??= $page->domain;

        // without new content, meta or config, the references of the previous version are copied
        $page->draft( [
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $page->latest?->lang,
            'aux' => $aux,
        ], $accepts ? self::refs( $aux, $user ) : $refs, $diffs );
    }


    /**
     * Creates a new version for an already loaded element from the given input.
     *
     * Shared by saveElement() (single) and bulkElement() (bulk). Must run inside a transaction.
     *
     * @param Element $element Element model with the "latest" relation loaded
     * @param array<string, mixed> $input Validated element input (merged with latest version)
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param Authenticatable|null $user Current user accepting new file references
     * @param array<string, array<string>>|null $refs Prefetched references of the latest version to copy
     * @return Element Updated element with the new version as "latest"
     */
    protected static function applyElement( Element $element, array $input, string $editor,
        ?string $latestId = null, ?Authenticatable $user = null, ?array $refs = null ) : Element
    {
        [$data, $dd] = Merge::model( $element, $input, $latestId );

        // without new data, the file references of the previous version are copied
        $element->draft( [
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $element->latest?->lang,
        ], array_key_exists( 'data', $input ) ? ['files' => self::elementFiles( $data, $user )] : $refs, $dd ? ['data' => $dd] : null );

        return $element;
    }


    /**
     * Queues version pruning in bounded batches.
     *
     * @param class-string<Element>|class-string<File>|class-string<Page> $model Model class
     * @param array<string|null> $ids Model IDs
     */
    protected static function pruneVersions( string $model, array $ids ) : void
    {
        foreach( array_chunk( array_unique( array_filter( $ids, is_string(...) ) ), 50 ) as $chunk ) {
            PruneVersions::dispatch( $model, Tenancy::value(), $chunk )->afterCommit();
        }
    }


    /**
     * Returns the given IDs confirmed to exist, throwing if any is no longer available.
     *
     * @param class-string<File>|class-string<Element> $model Model class to check the IDs against
     * @param array<string> $ids Referenced IDs to verify
     * @return array<string> Deduped IDs, all confirmed to exist
     * @throws Exception If any referenced ID is no longer available
     */
    protected static function available( string $model, array $ids ) : array
    {
        $ids = array_values( array_unique( $ids ) );
        $existing = [];

        foreach( array_chunk( $ids, 500 ) as $chunk )
        {
            foreach( $model::whereIn( 'id', $chunk )->pluck( 'id' ) as $id ) {
                /** @var string $id */
                $existing[$id] = true;
            }
        }

        if( $missing = array_filter( $ids, fn( $id ) => !isset( $existing[$id] ) ) ) {
            throw new Exception( sprintf( '%s not available: %s', class_basename( $model ), implode( ', ', $missing ) ) );
        }

        return $ids;
    }


    /**
     * Returns the available file IDs referenced by an element's field data.
     *
     * @param array<string, mixed> $data Element version data (fields stored under "data")
     * @param Authenticatable|null $user Authenticated user accepting the references
     * @return array<string> File IDs confirmed to exist
     * @throws Exception If any referenced file is no longer available
     */
    protected static function elementFiles( array $data, ?Authenticatable $user = null ) : array
    {
        $files = Validation::files( $data['data'] ?? [] );
        File::checkBulk( count( $files ) );

        Permission::check( 'file:view', $user, (bool) $files );

        return self::available( File::class, $files );
    }


    /**
     * Collects the file IDs and element refids referenced by a page version's aux data.
     *
     * @param array<string, mixed> $aux Merged aux with "content" (list) and "meta"/"config" (keyed objects)
     * @param Authenticatable|null $user Authenticated user accepting the references
     * @return array{files: array<string>, elements: array<string>}
     */
    protected static function refs( array $aux, ?Authenticatable $user = null ) : array
    {
        $files = [];
        $elements = [];

        foreach( (array) ( $aux['content'] ?? [] ) as $block )
        {
            $block = (array) $block;

            if( $block['type'] === 'reference' ) {
                $elements[] = $block['refid'];
            } else {
                array_push( $files, ...( $block['files'] ?? [] ) );
            }
        }

        foreach( ['meta', 'config'] as $section )
        {
            foreach( (array) ( $aux[$section] ?? [] ) as $entry ) {
                array_push( $files, ...( (array) $entry )['files'] );
            }
        }

        $files = array_values( array_unique( $files ) );
        $elements = array_values( array_unique( $elements ) );

        Page::checkBulk( count( $files ) + count( $elements ) );

        Permission::check( 'file:view', $user, (bool) $files );
        Permission::check( 'element:view', $user, (bool) $elements );

        return [
            'files' => self::available( File::class, $files ),
            'elements' => self::available( Element::class, $elements ),
        ];
    }
}
