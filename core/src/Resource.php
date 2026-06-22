<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Support\Facades\DB;


class Resource
{
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
        Validation::element( $input['type'] ?? '' );

        if( isset( $input['data'] ) ) {
            Validation::html( $input['type'] ?? '', $input['data'] );
        }

        if( $input['type'] ?? null ) {
            $input['data'] = (array) Validation::defaults( $input['type'], $input['data'] ?? [] );
        }

        $input['name'] = (string) ( $input['name'] ?? '' );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $input, $editor ) {

            $versionId = ( new Version )->newUniqueId();

            $element = new Element();
            $element->fill( $input );
            $element->data = $input['data'] ?? [];
            $element->tenant_id = Tenancy::value();
            $element->latest_id = $versionId;
            $element->editor = $editor;
            $element->save();

            $fileIds = self::elementFiles( $input );

            $element->files()->attach( $fileIds );

            $data = $input;
            ksort( $data );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $fileIds );

            // Re-index with the latest version loaded so the draft (latest=true)
            // row is written; on $element->save() above the version did not exist yet.
            $element->setRelation( 'latest', $version )->searchable();

            $element->announce( 'added', $editor );

            return $element;
        } );
    }


    /**
     * Persists a prepared file as a new media item with its first version.
     *
     * The caller fills the file's path, mime, previews, name, lang and
     * description; this method stores it, creates the initial version, indexes
     * it and broadcasts the change.
     *
     * @param File $file Prepared file model (path/mime/previews/name set)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return File
     */
    public static function addFile( File $file, ?Authenticatable $user = null ) : File
    {
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $file, $editor ) {

            $versionId = ( new Version )->newUniqueId();

            $file->tenant_id ??= Tenancy::value();
            $file->latest_id = $versionId;
            $file->editor = $editor;
            $file->save();

            $version = $file->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => $file->lang,
                'editor' => $editor,
                'data' => [
                    'lang' => $file->lang,
                    'name' => $file->name,
                    'mime' => $file->mime,
                    'path' => $file->path,
                    'previews' => $file->previews,
                    'description' => $file->description,
                    'transcription' => $file->transcription,
                ],
            ] );

            // Re-index with the latest version loaded so the draft (latest=true)
            // row is written; on $file->save() above the version did not exist yet.
            $file->setRelation( 'latest', $version )->searchable();

            $file->announce( 'added', $editor );

            return $file;
        } );
    }


    /**
     * Creates a new page with version and attached relations.
     *
     * Files and elements attached to the page are derived from the content, meta and config data.
     *
     * @param array<string, mixed> $input Page fields (content/meta/config go into version aux)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addPage( array $input, ?Authenticatable $user = null,
        ?string $ref = null, ?string $parent = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::lockedTransaction( function() use ( $input, $editor, $ref, $parent ) {

            $versionId = ( new Version )->newUniqueId();

            $page = new Page();
            $page->fill( $input );
            $page->tenant_id = Tenancy::value();
            $page->editor = $editor;

            self::position( $page, $ref, $parent );

            $page->latest_id = $versionId;
            $page->save();

            $refs = self::refs( [
                'content' => $input['content'] ?? [],
                'meta' => $input['meta'] ?? [],
                'config' => $input['config'] ?? [],
            ] );

            $fileIds = self::available( File::class, $refs['files'] );
            $elementIds = self::available( Element::class, $refs['elements'] );

            $page->files()->attach( $fileIds );
            $page->elements()->attach( $elementIds );

            $data = array_diff_key( $input, array_flip( ['config', 'content', 'meta'] ) );

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => [
                    'meta' => (object) ( $input['meta'] ?? [] ),
                    'config' => (object) ( $input['config'] ?? [] ),
                    'content' => $input['content'] ?? [],
                ]
            ] );

            $version->elements()->attach( $elementIds );
            $version->files()->attach( $fileIds );

            // Re-index with the latest version loaded so the draft (latest=true)
            // row is written; on $page->save() above the version did not exist yet.
            $page->setRelation( 'latest', $version )->searchable();

            $page->announce( 'added', $editor );

            return $page;
        } );
    }


    /**
     * Soft-deletes items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @return Collection<int, Base>
     */
    public static function drop( string $model, array $ids, string $editor ) : Collection
    {
        return Utils::transaction( function() use ( $model, $ids, $editor ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )
                ->when( is_a( $model, Page::class, true ), fn( $q ) => $q->select( Page::SELECT_COLUMNS ) )
                ->get();

            foreach( $items as $item )
            {
                $item->editor = $editor;
                $item->delete();

                if( $item instanceof Page ) {
                    Cache::forget( Page::key( $item ) );
                }

                $item->announce( 'dropped', $editor );
            }

            return $items;
        } );
    }


    /**
     * Moves a page to a new position in the tree and broadcasts the change.
     *
     * @param string $id Page UUID
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $root Whether to make the page a root node when no ref/parent given
     * @return Page
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     */
    public static function movePage( string $id, ?string $ref = null, ?string $parent = null,
        ?Authenticatable $user = null, bool $root = true ) : Page
    {
        $editor = Utils::editor( $user );

        return Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor, $root ) {

            /** @var Page $page */
            $page = Page::withTrashed()->findOrFail( $id );
            $page->editor = $editor;

            self::position( $page, $ref, $parent, $root );

            Page::withoutSyncingToSearch( fn() => $page->save() );

            $page->announce( 'moved', $editor );

            return $page;
        } );
    }


    /**
     * Positions a page in the tree relative to a sibling or parent.
     *
     * @param Page $page The page to position
     * @param string|null $beforeId ID of sibling to insert before
     * @param string|null $parentId ID of parent to append to
     * @param bool $root Whether to make the page a root node when no ref/parent given
     */
    public static function position( Page $page, ?string $beforeId = null, ?string $parentId = null, bool $root = false ) : void
    {
        if( $beforeId ) {
            $ref = Page::withTrashed()->select( 'id', 'tenant_id', 'parent_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )->findOrFail( $beforeId );
            $page->beforeNode( $ref );
        } elseif( $parentId ) {
            $parent = Page::withTrashed()->select( 'id', 'tenant_id', 'parent_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )->findOrFail( $parentId );
            $page->appendToNode( $parent );
        } elseif( $root ) {
            $page->makeRoot();
        }
    }


    /**
     * Publishes or schedules items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @param string|null $at ISO 8601 datetime to schedule publication
     * @param array<string|int, string|\Closure> $with Eager-load relations
     * @return Collection<int, Base>
     */
    public static function publish( string $model, array $ids, string $editor, ?string $at = null, array $with = ['latest'] ) : Collection
    {
        return Utils::transaction( function() use ( $model, $ids, $editor, $at, $with ) {

            $items = $at
                ? $model::select( 'id', 'latest_id' )->whereIn( 'id', $ids )->get()
                : $model::with( $with )->whereIn( 'id', $ids )->get();

            foreach( $items as $item )
            {
                if( $latest = $item->latest )
                {
                    if( $at )
                    {
                        $latest->publish_at = $at;
                        $latest->editor = $editor;
                        $latest->save();
                    }
                    else
                    {
                        $item->publish( $latest );
                    }
                }

                $item->announce( 'published', $editor );
            }

            return $items;
        } );
    }


    /**
     * Permanently deletes items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     * Calls purge() on File models to clean up storage.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @return Collection<int, Base>
     */
    public static function purge( string $model, array $ids, string $editor ) : Collection
    {
        $callback = function() use ( $model, $ids, $editor ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )
                ->when( is_a( $model, Page::class, true ), fn( $q ) => $q->select( Page::SELECT_COLUMNS ) )
                // eager-load latest only when broadcasting, so the per-item removed
                // events don't lazy-load each version (N+1)
                ->when( config( 'cms.broadcast' ), fn( $q ) => $q->with( 'latest' ) )
                ->get();

            foreach( $items as $item )
            {
                $item->announce( 'purged', $editor );

                if( $item instanceof File ) {
                    $item->purge();
                } else {
                    $item->forceDelete();
                }

                if( $item instanceof Page ) {
                    Cache::forget( Page::key( $item ) );
                }
            }

            return $items;
        };

        return is_a( $model, Page::class, true )
            ? Utils::lockedTransaction( $callback )
            : Utils::transaction( $callback );
    }


    /**
     * Restores soft-deleted items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @return Collection<int, Base>
     */
    public static function restore( string $model, array $ids, string $editor ) : Collection
    {
        $callback = function() use ( $model, $ids, $editor ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )
                ->when( is_a( $model, Page::class, true ), fn( $q ) => $q->select( Page::SELECT_COLUMNS ) )
                ->get();

            foreach( $items as $item )
            {
                $item->editor = $editor;
                $item->restore();

                $item->announce( 'restored', $editor );
            }

            return $items;
        };

        return is_a( $model, Page::class, true )
            ? Utils::lockedTransaction( $callback )
            : Utils::transaction( $callback );
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
        $type = $input['type'] ?? $element->type ?? ( (array) ( $element->latest->data ?? [] ) )['type'] ?? '';

        if( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        if( isset( $input['data'] ) ) {
            Validation::html( $type, $input['data'] );
            $input['data'] = (array) Validation::defaults( $type, $input['data'] );
        }

        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $element, $input, $editor, $latestId ) {
            return self::applyElement( $element, $input, $editor, $latestId );
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

        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $editor = Utils::editor( $user );

        return self::bulk( Element::class, $ids, $input, $editor, function( string $id ) use ( $input, $editor ) : ?Element {
            $element = Element::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id );
            return $element ? self::applyElement( $element, $input, $editor, null, false ) : null;
        } );
    }


    /**
     * Ensures a client-supplied file path stays within the current tenant's storage or is a system URL.
     *
     * @param string|null $path Storage path or URL provided by the caller
     * @return string|null The validated path or null if none was given
     * @throws \Aimeos\Cms\Exception If the path escapes the tenant's storage directory
     */
    protected static function checkPath( ?string $path ) : ?string
    {
        if( $path === null ) {
            return null;
        }

        if( str_starts_with( $path, 'http' ) ) {
            return $path;
        }

        $prefix = rtrim( 'cms/' . Tenancy::value(), '/' ) . '/';

        // The prefix check alone is not enough: "cms/1/../2/secret.jpg" starts with the
        // tenant prefix but the storage engine resolves the ".." into another tenant's
        // directory (cross-tenant read/delete). Legitimate paths never contain ".." or
        // null bytes (see File::filename()), so reject both outright.
        if( !str_starts_with( $path, $prefix ) || str_contains( $path, '..' ) || str_contains( $path, "\0" ) ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid file path "%s"', $path ) );
        }

        return $path;
    }


    /**
     * Validates a list of client-supplied preview paths against the current tenant's storage.
     *
     * @param mixed $previews List of storage paths or URLs provided by the caller
     * @return array<int|string, mixed>|null The validated list or null if none was given
     * @throws \Aimeos\Cms\Exception If any path escapes the tenant's storage directory
     */
    protected static function checkPaths( mixed $previews ) : ?array
    {
        if( $previews === null ) {
            return null;
        }

        foreach( (array) $previews as $preview ) {
            self::checkPath( (string) $preview );
        }

        return (array) $previews;
    }


    /**
     * Updates file metadata and creates a new version with optional merge.
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
        $editor = Utils::editor( $user );

        // Store the uploaded file and generate its previews outside the
        // transaction (they don't depend on the existing record) to keep slow
        // disk and image work off the database connection.
        $stored = null;
        $storedPreviews = null;

        if( $upload instanceof UploadedFile && $upload->isValid() )
        {
            $tmp = new File();
            $tmp->addFile( $upload );
            $stored = $tmp->path;

            $useUpload = str_starts_with( $upload->getClientMimeType(), 'image/' )
                && !( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) );

            if( $useUpload )
            {
                try {
                    $tmp->addPreviews( $upload );
                    $storedPreviews = (array) $tmp->previews;
                } catch( \Throwable $t ) {
                    $tmp->removePreviews();
                    throw $t;
                }
            }
        }

        return Utils::transaction( function() use ( $id, $input, $editor, $latestId, $preview, $stored, $storedPreviews ) {

            /** @var File $orig */
            $orig = File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] )->findOrFail( $id );

            return self::applyFile( $orig, $input, $editor, $latestId, $stored, $storedPreviews, $preview );
        } );
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

        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $editor = Utils::editor( $user );

        return self::bulk( File::class, $ids, $input, $editor, function( string $id ) use ( $input, $editor ) : ?File {
            $file = File::withTrashed()
                ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] )
                ->lockForUpdate()->find( $id );
            return $file ? self::applyFile( $file, $input, $editor, null, null, null, null, false ) : null;
        } );
    }


    /**
     * Saves the same input to several items of one type as a single best-effort batch.
     *
     * @param class-string<Element>|class-string<File>|class-string<Page> $model Model class being saved
     * @param array<string> $ids Item ids to save, in processing order
     * @param array<string, mixed> $input Shared fields applied to every item
     * @param string $editor Name of the editing user
     * @param \Closure(string): (Page|File|Element|null) $save Loads one locked row, applies the change and returns it (NULL to skip)
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    protected static function bulk( string $model, array $ids, array $input, string $editor, \Closure $save ) : array
    {
        $ids = array_values( array_unique( $ids ) );

        // suppress Scout's per-save reindex; the whole batch is reindexed once below
        $model::disableSearchSyncing();

        try {
            $map = self::saveEach( $ids, $save );
        } finally {
            $model::enableSearchSyncing();
        }

        // reindex the saved items once, chunked like cms:index; drop the soft-delete scope so
        // trashed items (recursive saves include them) are reindexed regardless of scout.soft_delete
        if( $saved = array_keys( $map->all() ) ) {
            $model::makeAllSearchableQuery()
                ->withoutGlobalScope( SoftDeletingScope::class )
                ->whereKey( $saved )
                ->chunk( 50, fn( $items ) => ( new $model )->syncMakeSearchable( $items ) );
        }

        $result = self::bulkResult( $map, $input, count( $ids ) );

        Base::announceBulk( strtolower( class_basename( $model ) ), $result['ids'], $result['latest'], $result['data'], $editor );

        return $result;
    }


    /**
     * Saves each id in its own short transaction and returns the saved id => latest-version-id map.
     *
     * A failing item is reported and skipped; \Error still bubbles up.
     *
     * @param array<string> $ids Unique IDs to save (callers deduplicate before calling)
     * @param \Closure(string): (Page|File|Element|null) $save Loads one locked row, applies the change and returns it (NULL to skip)
     * @return Collection<string, string> Saved item id => its new latest version id
     */
    protected static function saveEach( array $ids, \Closure $save ) : Collection
    {
        /** @var Collection<string, string> $result */
        $result = new Collection();

        foreach( $ids as $id )
        {
            try {
                /** @var Page|File|Element|null $item */
                $item = Utils::transaction( fn() => $save( $id ) );

                if( $item ) {
                    $result->put( (string) $item->id, (string) $item->latest_id );
                }
            } catch( \Exception $e ) {
                report( $e );
            }
        }

        return $result;
    }


    /**
     * Builds the broadcast-shaped result of a bulk operation from the saved map and applied input.
     *
     * The shared fields carry published=false (each save is a new draft) and the new modified time
     * so every patched row advances its "modified" date instead of showing a stale one.
     *
     * @param Collection<string, string> $map Saved item id => its new latest version id
     * @param array<string, mixed> $input Fields applied to every saved item
     * @param int $attempted Number of unique items the operation tried to save
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    protected static function bulkResult( Collection $map, array $input, int $attempted ) : array
    {
        $latest = $map->all();

        return [
            'ids' => array_keys( $latest ),
            'latest' => $latest,
            'data' => $input + ['published' => false, 'updated_at' => (string) now()],
            'failed' => max( 0, $attempted - count( $latest ) ),
        ];
    }


    /**
     * Creates a new version for an already loaded file from the given input.
     *
     * Shared by saveFile() (single) and bulkFile() (bulk). Must run inside a transaction.
     *
     * @param File $orig File model with the "latest" relation loaded
     * @param array<string, mixed> $input File fields to update (merged with latest version)
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param string|null $stored Path of an already stored upload, if any
     * @param array<int|string, mixed>|null $storedPreviews Previews generated for the stored upload, if any
     * @param UploadedFile|false|null $preview Preview upload, false to clear, null for auto-detect
     * @param bool $announce TRUE to broadcast a "saved" event, FALSE for bulk
     * @return File Updated file with the new version as "latest"
     */
    protected static function applyFile( File $orig, array $input, string $editor, ?string $latestId = null,
        ?string $stored = null, ?array $storedPreviews = null, UploadedFile|false|null $preview = null, bool $announce = true ) : File
    {
        $previews = $orig->latest?->data->previews ?? $orig->previews;
        $path = $orig->latest?->data->path ?? $orig->path;
        $previousEditor = $orig->latest->editor ?? '';
        $versionId = ( new Version )->newUniqueId();

        $file = clone $orig;

        [$data, $dd] = self::merge( $orig, $input, $latestId );
        $file->fill( $data );

        $file->previews = self::checkPaths( $input['previews'] ?? null ) ?? $previews;
        $file->path = $stored ?? self::checkPath( $input['path'] ?? null ) ?? $path;
        $file->editor = $editor;

        if( $file->path !== $path && !str_starts_with( $file->path, 'http' ) ) {
            $file->mime = Utils::mimetype( $file->path );
        }

        try
        {
            if( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) ) {
                $file->addPreviews( $preview );
            } elseif( $storedPreviews !== null ) {
                $file->previews = $storedPreviews;
            } elseif( $file->path !== $path && str_starts_with( $file->path, 'http' ) && Utils::isValidUrl( $file->path ) ) {
                $file->addPreviews( $file->path );
            } elseif( $preview === false ) {
                $file->previews = [];
            }
        }
        catch( \Throwable $t )
        {
            $file->removePreviews();
            throw $t;
        }

        $version = $file->versions()->forceCreate( [
            'id' => $versionId,
            'lang' => $file->lang,
            'editor' => $editor,
            'data' => $file->toArray(),
        ] );

        $orig->setRelation( 'latest', $version );
        $orig->forceFill( ['latest_id' => $version->id] )->save();
        $file->removeVersions();

        if( $dd ) {
            $orig->setChanged( [
                'editor' => $previousEditor,
                'latest' => ['id' => $versionId, 'data' => $file->toArray()],
                'data' => $dd,
            ] );
        }

        if( $announce ) {
            $orig->announce( 'saved', $editor );
        }

        return $orig;
    }


    /**
     * Updates an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     */
    public static function savePage( string $id, array $input, ?Authenticatable $user = null,
        ?string $latestId = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $id, $input, $user, $editor, $latestId ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $id );

            return self::applyPage( $page, $input, $editor, $latestId, $user );
        } );
    }


    /**
     * Applies the same partial input to multiple pages, optionally including all sub-pages.
     *
     * @param array<string> $ids Page IDs to update
     * @param array<string, mixed> $input Partial page input applied to every page
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $descendants TRUE to also update all sub-pages of the given pages
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public static function bulkPage( array $ids, array $input, ?Authenticatable $user = null,
        bool $descendants = false ) : array
    {
        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        // recursive save: expand to the whole subtree in depth-first order (defaultOrder() is the
        // nested-set pre-order traversal) so each parent is saved before its children
        if( $descendants )
        {
            $roots = Page::withTrashed()->whereIn( 'id', $ids )->get( ['id', NestedSet::LFT, NestedSet::RGT] );

            $ids = Page::withTrashed()->where( function( $builder ) use ( $roots ) {
                foreach( $roots as $root ) {
                    $builder->whereDescendantOrSelf( $root, 'or' );
                }
            } )->defaultOrder()->pluck( 'id' )->all();
        }

        return self::bulk( Page::class, $ids, $input, $editor, function( string $id ) use ( $input, $editor, $user ) : ?Page {
            if( !( $page = Page::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            // bulk only creates new draft versions, so the cached published output is unchanged
            self::applyPage( $page, $input, $editor, null, $user, false );

            return $page;
        } );
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
     * @param bool $announce TRUE to broadcast a "saved" event, FALSE for bulk
     * @return Page Updated page with the new version as "latest"
     */
    protected static function applyPage( Page $page, array $input, string $editor,
        ?string $latestId = null, ?Authenticatable $user = null, bool $announce = true ) : Page
    {
        $versionId = ( new Version )->newUniqueId();

        $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
        array_walk( $data, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );

        $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );

        // Only cast what was actually provided. Omitted meta/config keys must stay
        // absent so mergePage() preserves them from the latest version, exactly like
        // content. Force-defaulting them to empty objects would overwrite the latest.
        if( array_key_exists( 'meta', $aux ) ) {
            $aux['meta'] = (object) $aux['meta'];
        }

        if( array_key_exists( 'config', $aux ) ) {
            $aux['config'] = (object) $aux['config'];
        }

        $previousEditor = $page->latest->editor ?? '';

        [$data, $aux, $diffs] = self::mergePage( $page, $data, $aux, $latestId, $user );

        $data['domain'] ??= $page->domain ?? '';

        $version = $page->versions()->forceCreate( [
            'id' => $versionId,
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $page->latest?->lang,
            'aux' => $aux,
        ] );

        $refs = self::refs( $aux );

        $version->files()->attach( self::available( File::class, $refs['files'] ) );
        $version->elements()->attach( self::available( Element::class, $refs['elements'] ) );

        $page->setRelation( 'latest', $version );
        $page->forceFill( ['latest_id' => $version->id] )->save();

        if( $diffs ) {
            $page->setChanged( [
                'editor' => $previousEditor,
                'latest' => ['id' => $versionId, 'data' => $data, 'aux' => $aux],
                ...$diffs,
            ] );
        }

        if( $announce ) {
            $page->announce( 'saved', $editor );
        }

        return $page->removeVersions();
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
     * @param bool $announce TRUE to broadcast a "saved" event, FALSE for bulk
     * @return Element Updated element with the new version as "latest"
     */
    protected static function applyElement( Element $element, array $input, string $editor, ?string $latestId = null, bool $announce = true ) : Element
    {
        $versionId = ( new Version )->newUniqueId();
        $previousEditor = $element->latest->editor ?? '';

        [$data, $dd] = self::merge( $element, $input, $latestId );

        $version = $element->versions()->forceCreate( [
            'id' => $versionId,
            'data' => array_map( fn( $v ) => $v ?? '', $data ),
            'editor' => $editor,
            'lang' => $input['lang'] ?? $element->latest?->lang,
        ] );

        $version->files()->attach( self::elementFiles( $data ) );
        $element->setRelation( 'latest', $version );
        $element->forceFill( ['latest_id' => $version->id] )->save();

        if( $dd ) {
            $element->setChanged( [
                'editor' => $previousEditor,
                'latest' => ['id' => $versionId, 'data' => $data],
                'data' => $dd,
            ] );
        }

        if( $announce ) {
            $element->announce( 'saved', $editor );
        }

        return $element->removeVersions();
    }


    /**
     * Three-way merges or replaces page data and aux based on version conflict detection.
     *
     * @param Page $page Page model with versions relation
     * @param array<string, mixed> $data Incoming page data
     * @param array<string, mixed> $aux Incoming aux (meta/config/content)
     * @param string|null $latestId Version ID the editor was working on
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user Current user
     * @return array{0: array<string, mixed>, 1: array<string, mixed>, 2: array<string, mixed>|null}
     */
    protected static function mergePage( Page $page, array $data, array $aux, ?string $latestId, ?Authenticatable $user = null ) : array
    {
        $latestData = (array) ( $page->latest?->data );
        $latestAux = (array) ( $page->latest?->aux );

        if( $latestId && $page->latest_id && $latestId !== $page->latest_id )
        {
            /** @var Version|null $base */
            $base = $page->versions()->find( $latestId );

            if( $base )
            {
                $latestMeta = (array) ( $page->latest?->aux->meta ?? [] );
                $latestContent = (array) ( $page->latest?->aux->content ?? [] );
                $latestConfig = (array) ( $page->latest?->aux->config ?? [] );

                [$data, $dd] = Merge::structured( (array) $base->data, $latestData, $data );

                $merged = array_replace( $latestAux, $aux );
                [$merged['meta'], $md] = Merge::structured( (array) ( $base->aux->meta ?? [] ), $latestMeta, (array) ( $merged['meta'] ?? [] ) );
                [$merged['content'], $xd] = Merge::content( (array) ( $base->aux->content ?? [] ), $latestContent, (array) ( $merged['content'] ?? [] ) );

                $cd = null;
                if( Permission::can( 'page:config', $user ) ) {
                    [$merged['config'], $cd] = Merge::structured( (array) ( $base->aux->config ?? [] ), $latestConfig, (array) ( $merged['config'] ?? [] ) );
                } else {
                    $merged['config'] = $latestConfig;
                }

                $diffs = array_filter( ['data' => $dd, 'meta' => $md, 'config' => $cd, 'content' => $xd] );
                return [$data, $merged, $diffs ?: null];
            }
        }

        return [
            array_replace( $latestData, $data ),
            array_replace( $latestAux, $aux ),
            null
        ];
    }


    /**
     * Three-way merges or replaces data based on version conflict detection.
     *
     * @param Page|Element|File $model Model with versions relation
     * @param array<string, mixed> $input Incoming data
     * @param string|null $latestId Version ID the editor was working on
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>|null}
     */
    protected static function merge( Base $model, array $input, ?string $latestId ) : array
    {
        if( $latestId && $model->latest_id && $latestId !== $model->latest_id )
        {
            $base = $model->versions()->find( $latestId );

            if( $base ) {
                return Merge::structured( (array) $base->data, (array) $model->latest?->data, $input );
            }
        }

        return [array_replace( (array) $model->latest?->data, $input ), null];
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
        $ids = array_values( array_unique( array_filter( $ids ) ) );
        $existing = $ids ? $model::whereIn( 'id', $ids )->pluck( 'id' )->all() : [];

        // Compare case-insensitively: SQL Server stores/returns UUIDs uppercased via
        // the id accessor, so a case-sensitive diff would flag matching IDs as missing.
        if( $missing = array_udiff( $ids, $existing, 'strcasecmp' ) ) {
            throw new Exception( sprintf( '%s not available: %s', class_basename( $model ), implode( ', ', $missing ) ) );
        }

        return $ids;
    }


    /**
     * Returns the available file IDs referenced by an element's field data.
     *
     * @param array<string, mixed> $data Element version data (fields stored under "data")
     * @return array<string> File IDs confirmed to exist
     * @throws Exception If any referenced file is no longer available
     */
    protected static function elementFiles( array $data ) : array
    {
        $files = [];
        self::collectFiles( $data['data'] ?? [], $files );

        return self::available( File::class, $files );
    }


    /**
     * Collects the file IDs and element refids referenced by a page version's aux data.
     *
     * @param array<string, mixed> $aux Merged aux with "content" (list) and "meta"/"config" (keyed objects)
     * @return array{files: array<string>, elements: array<string>}
     */
    protected static function refs( array $aux ) : array
    {
        $files = [];
        $elements = [];

        foreach( (array) ( $aux['content'] ?? [] ) as $block )
        {
            $block = (array) $block;

            if( !empty( $block['refid'] ) ) {
                $elements[] = $block['refid'];
            }

            self::collectFiles( $block['data'] ?? [], $files );
        }

        foreach( ['meta', 'config'] as $section )
        {
            foreach( (array) ( $aux[$section] ?? [] ) as $entry ) {
                self::collectFiles( $entry, $files );
            }
        }

        return [
            'files' => array_values( array_unique( $files ) ),
            'elements' => array_values( array_unique( $elements ) ),
        ];
    }


    /**
     * Recursively collects the IDs of {id, type: "file"} nodes into the given list.
     *
     * @param mixed $node Data node to scan (array, object or scalar)
     * @param array<string> $files List of collected file IDs, modified in place
     */
    private static function collectFiles( mixed $node, array &$files ) : void
    {
        if( !is_array( $node ) && !is_object( $node ) ) {
            return;
        }

        $arr = (array) $node;

        if( ( $arr['type'] ?? null ) === 'file' && !empty( $arr['id'] ) ) {
            $files[] = (string) $arr['id'];
            return;
        }

        foreach( $arr as $value ) {
            self::collectFiles( $value, $files );
        }
    }
}
