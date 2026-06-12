<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\ContentSaved;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
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

            return $element;
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

            return $page;
        } );
    }


    /**
     * Dispatches a broadcast event after the current transaction commits.
     *
     * @param Base $model Model with latest relation loaded
     * @param Authenticatable|null $user Authenticated user
     */
    public static function broadcast( Base $model, ?Authenticatable $user = null ) : void
    {
        if( !config( 'cms.broadcast' ) || !( $version = $model->latest ) ) {
            return;
        }

        $editor = Utils::editor( $user );
        $type = strtolower( class_basename( $model ) );
        $aux = $model instanceof Page ? (array) $version->aux : null;

        DB::afterCommit( function() use ( $type, $model, $version, $editor, $aux ) {
            try {
                ContentSaved::dispatch( $type, (string) $model->id, (string) $version->id, $editor, (array) $version->data, $aux );
            } catch( \Throwable $e ) {
                report( $e );
            }
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
            }

            return $items;
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
     * @return Collection<int, Base>
     */
    public static function purge( string $model, array $ids ) : Collection
    {
        $callback = function() use ( $model, $ids ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )
                ->when( is_a( $model, Page::class, true ), fn( $q ) => $q->select( Page::SELECT_COLUMNS ) )
                ->get();

            foreach( $items as $item )
            {
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

            return $element->removeVersions();
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
                    $storedPreviews = $tmp->previews;
                } catch( \Throwable $t ) {
                    $tmp->removePreviews();
                    throw $t;
                }
            }
        }

        return Utils::transaction( function() use ( $id, $input, $editor, $latestId, $preview, $stored, $storedPreviews ) {

            /** @var File $orig */
            $orig = File::withTrashed()->with( ['latest' => fn( $q ) => $q->select( 'id', 'versionable_id', 'data', 'lang', 'editor' )] )->findOrFail( $id );
            $previews = $orig->latest?->data->previews ?? $orig->previews;
            $path = $orig->latest?->data->path ?? $orig->path;
            $previousEditor = $orig->latest->editor ?? '';
            $versionId = ( new Version )->newUniqueId();
            $dd = null;

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

            return $orig;
        } );
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

            return $page->removeVersions();
        } );
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
