<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Aimeos\Nestedset\NestedSet;


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
        $type = $input['type'] ?? '';
        Validation::element( $type );

        if( isset( $input['data'] ) ) {
            Validation::html( $type, $input['data'] );
        }

        $input['data'] = (array) Validation::defaults( $type, $input['data'] ?? [] );

        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $input, $editor, $user ) {

            $versionId = ( new Version )->newUniqueId();

            $element = new Element();
            $element->fill( $input );
            $element->latest_id = $versionId;
            $element->editor = $editor;
            $element->save();

            $fileIds = self::elementFiles( $input, $user );

            $element->files()->attach( $fileIds );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => $element->toArray(),
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

        try
        {
            return Utils::fileLock( $tenant, function() use ( $file, $editor ) {
                self::checkStored( [$file->path, ...(array) $file->previews] );

                return Utils::transaction( function() use ( $file, $editor ) {
                    $versionId = ( new Version )->newUniqueId();

                    $file->latest_id = $versionId;
                    $file->editor = $editor;
                    $file->save();

                    $snapshot = File::snapshot( $file->toArray() );

                    $version = $file->versions()->forceCreate( [
                        'id' => $versionId,
                        'lang' => $file->lang,
                        'editor' => $editor,
                        'data' => $snapshot['data'],
                        'aux' => $snapshot['aux'],
                    ] );

                    // Re-index with the latest version loaded so the draft (latest=true)
                    // row is written; on $file->save() above the version did not exist yet.
                    $file->setRelation( 'latest', $version )->searchable();

                    $file->announce( 'added', $editor );

                    return $file;
                } );
            } );
        }
        catch( \Throwable $t )
        {
            $file->removePreviews()->removeFile();
            throw $t;
        }
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
    public static function addPage( array $input, ?Authenticatable $user = null, ?string $ref = null, ?string $parent = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::lockedTransaction( function() use ( $input, $editor, $ref, $parent, $user ) {

            $versionId = ( new Version )->newUniqueId();

            $page = new Page();
            $page->fill( $input );
            $page->editor = $editor;

            $page->position( $ref, $parent );

            $page->latest_id = $versionId;
            $page->save();

            $aux = [
                'content' => $input['content'] ?? [],
                'meta' => $input['meta'] ?? [],
                'config' => $input['config'] ?? [],
            ];
            $refs = self::refs( $aux, $user );

            $page->files()->attach( $refs['files'] );
            $page->elements()->attach( $refs['elements'] );

            $data = array_diff_key( $input, $aux );

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => $data,
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => $aux,
            ] );

            $version->elements()->attach( $refs['elements'] );
            $version->files()->attach( $refs['files'] );

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
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Base>
     */
    public static function drop( string $model, array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        return self::lifecycle( $model, $ids, 'dropped', $user, $fields );
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

        return Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor ) {

            /** @var Page $page */
            $page = Page::withTrashed()->findOrFail( $id );
            $page->editor = $editor;

            $page->position( $ref, $parent );

            Page::withoutSyncingToSearch( fn() => $page->save() );

            $page->announce( 'moved', $editor );

            return $page;
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

        if( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        if( isset( $input['data'] ) )
        {
            Validation::html( $type, $input['data'] );
            $input['data'] = (array) Validation::defaults( $type, $input['data'] );
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

        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $editor = Utils::editor( $user );

        return self::bulk( Element::class, $ids, $input, $editor, function( string $id ) use ( $input, $editor, $user ) : ?Element {
            $element = Element::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id );
            return $element ? self::applyElement( $element, $input, $editor, user: $user ) : null;
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
            if( Utils::isValidUrl( $path, false ) ) {
                return $path;
            }

            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid file path "%s"', $path ) );
        }

        if( ( $value = Utils::normalizePath( $path ) ) === null ) {
            throw new \Aimeos\Cms\Exception( sprintf( 'Invalid file path "%s"', $path ) );
        }

        return $value;
    }


    /**
     * Ensures prepared local files still exist while the ownership lock is held.
     *
     * @param array<array-key, mixed> $paths Prepared storage paths
     */
    protected static function checkStored( array $paths ) : void
    {
        $disk = Storage::disk( config( 'cms.disk', 'public' ) );

        foreach( $paths as $path )
        {
            if( $path === null ) {
                continue;
            }

            $value = self::checkPath( (string) $path );

            if( $value !== null && str_starts_with( $value, 'http' ) ) {
                continue;
            }

            if( $value === null || !$disk->exists( $value ) ) {
                throw new Exception( sprintf( 'Prepared file "%s" is not available', (string) $path ) );
            }
        }
    }


    /**
     * Applies and announces a lifecycle action while preserving Page tree semantics.
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

        if( !$isPage ) {
            sort( $ids, SORT_STRING );
        }

        $apply = function( array $ids ) use ( $action, $announce, $editor, $fields, $isPage, $model, &$pages ) {
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

            if( $isPage && ( $action !== 'restored' || Scout::usesExternalSearch() ) ) {
                $pages = self::pageSubtree( $items );
            } elseif( $isPage ) {
                $pages = $items;
            }

            if( $action === 'purged' )
            {
                if( $announce ) {
                    Base::announceMany( $items, $action, $editor );
                }

                if( $model === File::class ) {
                    File::purgeMany( Tenancy::value(), $items );
                }

                if( $isPage ) {
                    foreach( $items as $item ) {
                        $item->forceDelete();
                    }
                } else {
                    /** @var array<string> $affected */
                    $affected = $items->pluck( 'id' )->all();
                    $model::withTrashed()->whereIn( 'id', $affected )->forceDelete();

                    foreach( $items as $item ) {
                        $item->exists = false;
                        $item->wasRecentlyCreated = false;
                    }
                }

                return $items;
            }

            if( $isPage )
            {
                foreach( $items as $item )
                {
                    $item->editor = $editor;
                    $action === 'dropped' ? $item->delete() : $item->restore();
                }

                return $items;
            }

            /** @var array<string> $affected */
            $affected = $items->pluck( 'id' )->all();
            $time = ( new $model() )->freshTimestamp();
            $attributes = [
                'deleted_at' => $action === 'dropped' ? $time : null,
                'editor' => $editor,
                'updated_at' => $time,
            ];

            $model::withTrashed()->whereIn( 'id', $affected )->update( $attributes );

            foreach( $items as $item ) {
                $item->forceFill( $attributes )->syncOriginalAttributes( array_keys( $attributes ) );
            }

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

        $items = Scout::mute( [$model], function() use ( $action, $apply, $ids, $model, $run ) {
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

        if( $isPage && $action !== 'restored' )
        {
            $paths = [];

            foreach( $pages as $page ) {
                if( $page instanceof Page ) {
                    $paths[(string) $page->domain][] = (string) $page->path;
                }
            }

            foreach( $paths as $domain => $domainPaths ) {
                PageInvalidated::dispatch( (string) $domain, $domainPaths );
            }
        }

        if( $action === 'dropped' ) {
            Base::announceMany( $items, $action, $editor, [
                'deleted_at' => (string) ( $items->first()->deleted_at ?? now() ),
            ] );
        } elseif( $action === 'restored' ) {
            Base::announceMany( $items, $action, $editor, ['deleted_at' => null] );
        } elseif( !$announce ) {
            Base::announceMany( $items, $action, $editor, bulk: true );
        }

        /** @var array<string> $changed */
        $changed = ( $isPage ? $pages : $items )->pluck( 'id' )->all();

        if( $isPage && $action === 'dropped' && !Scout::usesExternalSearch() ) {
            return $items;
        } elseif( $action === 'restored' ) {
            Scout::index( $model, $changed, $isPage && $fields ? null : $items );
        } elseif( $action === 'dropped' && config( 'scout.soft_delete' ) ) {
            Scout::index( $model, $changed );
        } else {
            Scout::unindex( $model, $changed );
        }

        return $items;
    }


    /**
     * Loads the route and search projection for complete page subtrees.
     *
     * @param Collection<int, Base> $roots
     * @return \Aimeos\Nestedset\Collection
     */
    protected static function pageSubtree( Collection $roots ) : \Aimeos\Nestedset\Collection
    {
        if( $roots->isEmpty() ) {
            return new \Aimeos\Nestedset\Collection();
        }

        return Page::withTrashed()
            ->select( 'id', 'tenant_id', 'domain', 'path', NestedSet::LFT )
            ->where( function( $query ) use ( $roots ) {
                foreach( $roots as $root ) {
                    $query->whereDescendantOrSelf( $root, 'or' );
                }
            } )
            ->orderBy( NestedSet::LFT )
            ->lockForUpdate()
            ->get();
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
        $tenant = Tenancy::value();

        // Prepare storage and remote image work before opening the transaction.
        $tmp = new File();
        $currentPath = null;
        $stored = null;
        $storedMime = null;
        $storedPreviews = null;

        try
        {
            if( $upload || $preview instanceof UploadedFile || array_key_exists( 'path', $input ) )
            {
                /** @var File $current */
                $current = File::withTrashed()->with( ['latest' => fn( $q ) => $q
                    ->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor' )] )->findOrFail( $id );
                $currentPath = (string) ( $current->latest?->data->path ?? $current->path );
                $tmp->name = (string) ( $input['name'] ?? $current->latest?->data->name ?? $current->name );
            }

            $newPath = self::checkPath( $input['path'] ?? null );
            $source = $upload ?? ( $newPath !== null && $newPath !== $currentPath ? $newPath : null );

            if( $source !== null || $preview instanceof UploadedFile )
            {
                $tmp->prepare( $source, $preview );
                $stored = $upload ? $tmp->path : null;
                $storedMime = $source !== null ? (string) $tmp->mime : null;

                if( $preview instanceof UploadedFile
                    || $source instanceof UploadedFile && str_starts_with( (string) $source->getMimeType(), 'image/' )
                    || is_string( $source ) && str_starts_with( $source, 'http' )
                ) {
                    $storedPreviews = (array) $tmp->previews;
                }
            }

            return Utils::fileLock( $tenant, function() use ( $id, $input, $editor, $latestId,
                $preview, $stored, $storedMime, $storedPreviews ) {
                    self::checkStored( [$stored, ...( $storedPreviews ?? [] )] );

                    return Utils::transaction( function() use ( $id, $input, $editor, $latestId,
                        $preview, $stored, $storedMime, $storedPreviews ) {

                        /** @var File $file */
                        $file = File::withTrashed()->with( ['latest' => fn( $q ) => $q
                            ->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor' )] )
                            ->lockForUpdate()->findOrFail( $id );

                        self::applyFile( $file, $input, $editor, $latestId, $stored,
                            $storedPreviews, $preview, $storedMime );
                        self::pruneVersions( File::class, [$file->id] );

                        $file->announce( 'saved', $editor );

                        return $file;
                    } );
                } );
        }
        catch( \Throwable $t )
        {
            try {
                $tmp->removePreviews()->removeFile();
            } catch( \Throwable $cleanup ) {
                report( $cleanup );
            }

            throw $t;
        }
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
                ->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'aux', 'lang', 'editor' )] )
                ->lockForUpdate()->find( $id );

            return $file ? self::applyFile( $file, $input, $editor ) : null;
        } );
    }


    /**
     * Saves the same input to several items of one type as a single best-effort batch.
     *
     * @param class-string<Element>|class-string<File>|class-string<Page> $model Model class being saved
     * @param array<string> $ids Item ids to save, in processing order
     * @param array<string, mixed> $input Shared fields applied to every item
     * @param string $editor Name of the editing user
     * @param \Closure(string, mixed): (Page|File|Element|null) $save Loads one locked row and applies the change
     * @param (\Closure(array<string>): mixed)|null $prepare Prepares shared state for each 50-item window
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    protected static function bulk( string $model, array $ids, array $input, string $editor, \Closure $save, ?\Closure $prepare = null ) : array
    {
        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );

        // suppress Scout's per-save reindex; the whole batch is reindexed once below
        $latest = Scout::mute( [$model], function() use ( $ids, $prepare, $save ) {
            $result = [];

            foreach( array_chunk( $ids, 50 ) as $chunk )
            {
                $context = $prepare ? $prepare( $chunk ) : null;

                foreach( $chunk as $id )
                {
                    try
                    {
                        /** @var Page|File|Element|null $item */
                        $item = Utils::transaction( fn() => $save( $id, $context ) );

                        if( $item )
                        {
                            /** @var string $itemId */
                            $itemId = $item->id;
                            /** @var string $versionId */
                            $versionId = $item->latest_id;

                            $result[$itemId] = $versionId;
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
            'data' => $input + ['published' => false, 'updated_at' => (string) now()],
            'failed' => count( $ids ) - count( $saved ),
        ];

        Base::announceBulk( strtolower( class_basename( $model ) ), $result['ids'], $result['latest'], $result['data'], $editor );
        self::pruneVersions( $model, $saved );

        return $result;
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
        $previousEditor = $orig->latest->editor ?? '';

        $file = clone $orig;

        $base = self::base( $orig, $latestId );
        $input = File::snapshot( $input );
        [$data, $dd] = self::merge( $orig, $input['data'], $base );
        [$aux, $ad] = self::merge( $orig, $input['aux'], $base, 'aux' );
        $diffs = array_filter( ['data' => $dd, 'aux' => $ad] );
        $file->fill( $data + $aux );

        if( isset( $input['data']['previews'] ) )
        {
            foreach( $input['data']['previews'] as $previewPath ) {
                self::checkPath( $previewPath );
            }
        }

        $file->previews = $input['data']['previews'] ?? $previews;
        $file->path = $stored ?? self::checkPath( $input['data']['path'] ?? null ) ?? $path;
        $file->editor = $editor;

        if( $file->path !== $path )
        {
            $file->mime = $storedMime ?? Utils::mimetype( $file->path );

            if( !Utils::isValidMimetype( $file->mime ) ) {
                throw new Exception( sprintf( 'File type "%s" not allowed, permitted types: %s',
                    $file->mime, implode( ', ', config( 'cms.upload.mimetypes', [] ) ) ) );
            }
        }

        if( $storedPreviews !== null ) {
            $file->previews = $storedPreviews;
        } elseif( $preview === false ) {
            $file->previews = [];
        }

        $snapshot = File::snapshot( $file->toArray() );
        $snapshot['aux'] = array_replace( $snapshot['aux'], $aux );

        $version = $file->versions()->forceCreate( [
            'lang' => $file->lang,
            'editor' => $editor,
            'data' => $snapshot['data'],
            'aux' => $snapshot['aux'],
        ] );

        $orig->setRelation( 'latest', $version );
        $orig->forceFill( ['latest_id' => $version->id] )->save();

        if( $diffs )
        {
            $orig->setChanged( [
                'editor' => $previousEditor,
                'latest' => [
                    'id' => $version->id,
                    'data' => (array) $version->data,
                    'aux' => (array) $version->aux,
                ],
                ...$diffs,
            ] );
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
    public static function savePage( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $id, $input, $user, $editor, $latestId ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $id );

            self::applyPage( $page, $input, $editor, $latestId, $user );
            $page->announce( 'saved', $editor );
            self::pruneVersions( Page::class, [$page->id] );

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
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public static function bulkPage( array $ids, array $input, ?Authenticatable $user = null, bool $descendants = false ) : array
    {
        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        // recursive save: expand to the whole subtree in depth-first order (defaultOrder() is the
        // nested-set pre-order traversal) so each parent is saved before its children
        if( $descendants )
        {
            $roots = Page::withTrashed()->whereIn( 'id', $ids )
                ->orderBy( NestedSet::LFT )->orderByDesc( NestedSet::RGT )
                ->get( ['id', NestedSet::LFT, NestedSet::RGT] );

            $right = null;
            $ranges = [];
            $ids = [];

            foreach( $roots as $root )
            {
                $left = (int) $root->getAttribute( NestedSet::LFT );
                $rgt = (int) $root->getAttribute( NestedSet::RGT );

                if( $right === null || $left > $right )
                {
                    $ranges[] = [$left, $rgt];
                    $right = $rgt;
                }
            }

            foreach( array_chunk( $ranges, 50 ) as $chunk )
            {
                $found = Page::withTrashed()->where( function( $builder ) use ( $chunk ) {
                    foreach( $chunk as [$left, $right] )
                    {
                        $builder->orWhere( fn( $query ) => $query
                            ->where( NestedSet::LFT, '>=', $left )
                            ->where( NestedSet::RGT, '<=', $right ) );
                    }
                } )->defaultOrder()->limit( Page::MAX_BULK + 1 - count( $ids ) )->pluck( 'id' )->all();

                $ids = array_merge( $ids, $found );

                if( count( $ids ) > Page::MAX_BULK ) {
                    break;
                }
            }

        }

        $prepare = array_intersect_key( $input, array_flip( ['content', 'meta', 'config'] ) ) ? null : self::pageRefs(...);

        return self::bulk( Page::class, $ids, $input, $editor,
            function( string $id, mixed $context = null ) use ( $input, $editor, $user ) : ?Page {

            if( !( $page = Page::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            $refs = $context[$id] ?? null;

            if( $refs && $refs['latest'] !== $page->latest_id ) {
                $refs = null;
            }

            // bulk only creates new draft versions, so the cached published output is unchanged
            self::applyPage( $page, $input, $editor, null, $user, $refs );

            return $page;
        }, $prepare );
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
     * @param array{files: array<string>, elements: array<string>}|null $references Stored unchanged references
     */
    protected static function applyPage( Page $page, array $input, string $editor,
        ?string $latestId = null, ?Authenticatable $user = null, ?array $references = null ) : void
    {
        $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
        $data = array_diff_key( $input, $aux );
        $accepts = (bool) $aux;

        $previousEditor = $page->latest->editor ?? '';

        [$data, $aux, $diffs] = Merge::page( $page, $data, $aux, $latestId, $user );

        $data['domain'] ??= $page->domain;

        $version = $page->versions()->forceCreate( [
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $page->latest?->lang,
            'aux' => $aux,
        ] );

        $references = $accepts ? self::refs( $aux, $user ) : ( $references ?? self::refs( $aux ) );

        $version->files()->attach( $references['files'] );
        $version->elements()->attach( $references['elements'] );

        $page->setRelation( 'latest', $version );
        $page->forceFill( ['latest_id' => $version->id] )->save();

        if( $diffs )
        {
            $page->setChanged( [
                'editor' => $previousEditor,
                'latest' => ['id' => $version->id, 'data' => $data, 'aux' => $aux],
                ...$diffs,
            ] );
        }
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
     * @return Element Updated element with the new version as "latest"
     */
    protected static function applyElement( Element $element, array $input, string $editor,
        ?string $latestId = null, ?Authenticatable $user = null ) : Element
    {
        $previousEditor = $element->latest->editor ?? '';
        $accepts = array_key_exists( 'data', $input );

        [$data, $dd] = Merge::model( $element, $input, $latestId );

        $version = $element->versions()->forceCreate( [
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $element->latest?->lang,
        ] );

        $version->files()->attach( self::elementFiles( $data, $accepts ? $user : null ) );
        $element->setRelation( 'latest', $version );
        $element->forceFill( ['latest_id' => $version->id] )->save();

        if( $dd )
        {
            $element->setChanged( [
                'editor' => $previousEditor,
                'latest' => ['id' => $version->id, 'data' => $data],
                'data' => $dd,
            ] );
        }

        return $element;
    }


    /**
     * Returns stored latest-version references for a bounded page window.
     *
     * @param array<string> $ids Page IDs
     * @return array<string, array{latest: string, files: array<string>, elements: array<string>}>
     */
    protected static function pageRefs( array $ids ) : array
    {
        $pages = Page::withTrashed()->whereIn( 'id', $ids )->pluck( 'latest_id', 'id' );
        $elements = [];
        $versions = [];
        $files = [];

        foreach( $pages as $id => $versionId )
        {
            /** @var string $id */
            /** @var string $versionId */
            $elements[$id] = [];
            $files[$id] = [];

            $versions[$versionId] = $id;
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        if( $versions )
        {
            foreach( $db->table( 'cms_version_file' )->whereIn( 'version_id', array_keys( $versions ) )->get() as $row )
            {
                /** @var string $versionId */
                $versionId = $row->version_id;
                /** @var string $fileId */
                $fileId = $row->file_id;

                $files[$versions[$versionId]][] = $fileId;
            }
            foreach( $db->table( 'cms_version_element' )->whereIn( 'version_id', array_keys( $versions ) )->get() as $row )
            {
                /** @var string $versionId */
                $versionId = $row->version_id;
                /** @var string $elementId */
                $elementId = $row->element_id;

                $elements[$versions[$versionId]][] = $elementId;
            }
        }

        $result = [];

        foreach( $pages as $id => $versionId )
        {
            /** @var string $id */
            /** @var string $versionId */
            $result[$id] = [
                'latest' => $versionId,
                'files' => $files[$id],
                'elements' => $elements[$id],
            ];
        }

        return $result;
    }


    /**
     * Queues version pruning in bounded batches.
     *
     * @param class-string<Element>|class-string<File>|class-string<Page> $model Model class
     * @param array<string|null> $ids Model IDs
     */
    protected static function pruneVersions( string $model, array $ids ) : void
    {
        $ids = array_values( array_filter( $ids, is_string(...) ) );

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk ) {
            PruneVersions::dispatch( $model, Tenancy::value(), $chunk )->afterCommit();
        }
    }


    /**
     * Returns the base version when the model changed since it was read.
     *
     * @param Base $model Model with versions relation
     * @param string|null $latestId Version ID the editor was working on
     * @return Version|null Base version for the three-way merge
     */
    protected static function base( Base $model, ?string $latestId ) : ?Version
    {
        $current = $model->getAttribute( 'latest_id' );

        if( $latestId && $current && $latestId !== $current ) {
            return $model->versions()->find( $latestId );
        }

        return null;
    }


    /**
     * Three-way merges or replaces a version section based on conflict detection.
     *
     * @param Element|File $model Model with versions relation
     * @param array<string, mixed> $input Incoming data
     * @param Version|null $base Base version for the three-way merge
     * @param string $section Version section to merge
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>|null}
     */
    protected static function merge( Base $model, array $input, ?Version $base, string $section = 'data' ) : array
    {
        $latest = (array) $model->latest?->getAttribute( $section );

        if( $base ) {
            $base = (array) $base->getAttribute( $section );
            return Merge::structured( $base, $latest, array_replace( $base, $input ) );
        }

        return [array_replace( $latest, $input ), null];
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

        if( $files && $user && !Permission::can( 'file:view', $user ) ) {
            throw new Exception( 'Insufficient permissions' );
        }

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

        if( $files && $user && !Permission::can( 'file:view', $user ) ) {
            throw new Exception( 'Insufficient permissions' );
        }

        if( $elements && $user && !Permission::can( 'element:view', $user ) ) {
            throw new Exception( 'Insufficient permissions' );
        }

        return [
            'files' => self::available( File::class, $files ),
            'elements' => self::available( Element::class, $elements ),
        ];
    }
}
