<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;


class Resource
{
    /**
     * Publishes or schedules items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @param string|null $at ISO 8601 datetime to schedule publication
     * @param array<string> $with Eager-load relations
     * @return Collection<int, Base>
     */
    public static function publish( string $model, array $ids, string $editor, ?string $at = null, array $with = ['latest'] ) : Collection
    {
        return Utils::transaction( function() use ( $model, $ids, $editor, $at, $with ) {

            $items = $model::with( $with )->whereIn( 'id', $ids )->get();

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

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

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

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

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

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

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
     * Creates a new page with version and attached relations.
     *
     * @param array<string, mixed> $input Page fields (content/meta/config go into version aux)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param array<string> $files File IDs to attach
     * @param array<string> $elements Element IDs to attach
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addPage( array $input, ?Authenticatable $user = null,
        array $files = [], array $elements = [],
        ?string $ref = null, ?string $parent = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::lockedTransaction( function() use ( $input, $editor, $files, $elements, $ref, $parent ) {

            $versionId = ( new Version )->newUniqueId();

            $page = new Page();
            $page->fill( $input );
            $page->tenant_id = Tenancy::value();
            $page->editor = $editor;

            self::position( $page, $ref, $parent );

            $page->latest_id = $versionId;
            $page->save();

            $page->files()->attach( $files );
            $page->elements()->attach( $elements );

            $data = array_diff_key( $input, array_flip( ['config', 'content', 'meta'] ) );

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => [
                    'meta' => $input['meta'] ?? new \stdClass(),
                    'config' => $input['config'] ?? new \stdClass(),
                    'content' => $input['content'] ?? [],
                ]
            ] );

            $version->elements()->attach( $elements );
            $version->files()->attach( $files );

            return $page->setRelation( 'latest', $version );
        } );
    }


    /**
     * Updates an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param array<string>|null $files File IDs to attach to version
     * @param array<string>|null $elements Element IDs to attach to version
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     */
    public static function savePage( string $id, array $input, ?Authenticatable $user = null,
        ?array $files = null, ?array $elements = null, ?string $latestId = null ) : Page
    {
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $id, $input, $user, $editor, $files, $elements, $latestId ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $id );
            $versionId = ( new Version )->newUniqueId();

            $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            array_walk( $data, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );

            $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $previousEditor = $page->latest->editor ?? '';
            $diffs = null;

            /** @var Version|null $base */
            $base = $latestId && $page->latest_id && $latestId !== $page->latest_id
                ? $page->versions()->find( $latestId ) : null;

            if( $base )
            {
                [$data, $dd] = Merge::structured( (array) $base->data, (array) $page->latest?->data, $data );

                $merged = array_replace( (array) $page->latest?->aux, $aux );
                [$merged['meta'], $md] = Merge::structured( (array) ( $base->aux->meta ?? [] ), (array) ( $page->latest?->aux->meta ?? [] ), (array) ( $merged['meta'] ?? [] ) );
                [$merged['content'], $xd] = Merge::content( (array) ( $base->aux->content ?? [] ), (array) ( $page->latest?->aux->content ?? [] ), (array) ( $merged['content'] ?? [] ) );

                $cd = null;
                if( Permission::can( 'config:page', $user ) ) {
                    [$merged['config'], $cd] = Merge::structured( (array) ( $base->aux->config ?? [] ), (array) ( $page->latest?->aux->config ?? [] ), (array) ( $merged['config'] ?? [] ) );
                } else {
                    $merged['config'] = (array) ( $page->latest?->aux->config ?? [] );
                }

                $aux = $merged;
                $diffs = array_filter( ['data' => $dd, 'meta' => $md, 'config' => $cd, 'content' => $xd] );
            }
            else
            {
                $data = array_replace( (array) $page->latest?->data, $data );
                $aux = array_replace( (array) $page->latest?->aux, $aux );
            }

            $data['domain'] ??= $page->domain ?? '';

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => $data,
                'editor' => $editor,
                'lang' => $input['lang'] ?? $page->latest?->lang,
                'aux' => $aux,
            ] );

            $version->elements()->attach( $elements ?? $page->latest?->elements()->pluck( 'id' )->all() ?? [] );
            $version->files()->attach( $files ?? $page->latest?->files()->pluck( 'id' )->all() ?? [] );

            $page->setRelation( 'latest', $version );
            $page->forceFill( ['latest_id' => $version->id] )->save();

            if( $diffs ) {
                $page->setChanges( [
                    'editor' => $previousEditor,
                    'latest' => ['id' => $versionId, 'data' => $data, 'aux' => $aux],
                    ...$diffs,
                ] );
            }

            return $page->removeVersions();
        } );
    }


    /**
     * Creates a new element with version and attached files.
     *
     * @param array<string, mixed> $input Element fields (type, name, lang, data)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $files File IDs to attach
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addElement( array $input, ?Authenticatable $user = null, array $files = [] ) : Element
    {
        Validation::element( $input['type'] ?? '' );

        if( isset( $input['data'] ) ) {
            Validation::html( $input['type'] ?? '', $input['data'] );
        }

        $input['name'] = (string) ( $input['name'] ?? '' );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $input, $editor, $files ) {

            $versionId = ( new Version )->newUniqueId();

            $element = new Element();
            $element->fill( $input );
            $element->data = $input['data'] ?? [];
            $element->tenant_id = Tenancy::value();
            $element->latest_id = $versionId;
            $element->editor = $editor;
            $element->save();

            $element->files()->attach( $files );

            $data = $input;
            ksort( $data );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $files );

            return $element->setRelation( 'latest', $version );
        } );
    }


    /**
     * Updates an existing element with a new version.
     *
     * @param string $id Element UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string>|null $files File IDs to attach to version
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If element not found
     */
    public static function saveElement( string $id, array $input, ?Authenticatable $user = null, ?array $files = null, ?string $latestId = null ) : Element
    {
        /** @var Element $element */
        $element = Element::withTrashed()->with( 'latest' )->findOrFail( $id );
        $type = $input['type'] ?? $element->type ?? ( (array) ( $element->latest->data ?? [] ) )['type'] ?? '';

        if( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        if( isset( $input['data'] ) ) {
            Validation::html( $type, $input['data'] );
        }

        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $element, $input, $editor, $files, $latestId ) {

            $versionId = ( new Version )->newUniqueId();
            $previousEditor = $element->latest->editor ?? '';

            [$data, $dd] = self::mergeOrReplace( $element, $input, $latestId );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $data ),
                'editor' => $editor,
                'lang' => $input['lang'] ?? $element->latest?->lang,
            ] );

            $version->files()->attach( $files ?? $element->latest?->files()->pluck( 'id' )->all() ?? [] );
            $element->setRelation( 'latest', $version );
            $element->forceFill( ['latest_id' => $version->id] )->save();

            if( $dd ) {
                $element->setChanges( [
                    'editor' => $previousEditor,
                    'latest' => ['id' => $versionId, 'data' => $data],
                    'data' => $dd,
                ] );
            }

            return $element->removeVersions();
        } );
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

        return Utils::transaction( function() use ( $id, $input, $editor, $latestId, $upload, $preview ) {

            /** @var File $orig */
            $orig = File::withTrashed()->with( 'latest' )->findOrFail( $id );
            $previews = $orig->latest?->data->previews ?? $orig->previews;
            $path = $orig->latest?->data->path ?? $orig->path;
            $previousEditor = $orig->latest->editor ?? '';
            $versionId = ( new Version )->newUniqueId();
            $dd = null;

            $file = clone $orig;

            [$data, $dd] = self::mergeOrReplace( $orig, $input, $latestId );
            $file->fill( $data );

            $file->previews = $input['previews'] ?? $previews;
            $file->path = $input['path'] ?? $path;
            $file->editor = $editor;

            if( $upload instanceof UploadedFile && $upload->isValid() ) {
                $file->addFile( $upload );
            }

            if( $file->path !== $path ) {
                $file->mime = Utils::mimetype( $file->path );
            }

            try
            {
                if( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $preview );
                } elseif( $upload instanceof UploadedFile && $upload->isValid() && str_starts_with( $upload->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $upload );
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
                $orig->setChanges( [
                    'editor' => $previousEditor,
                    'latest' => ['id' => $versionId, 'data' => $file->toArray()],
                    'data' => $dd,
                ] );
            }

            return $orig;
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
            $ref = Page::withTrashed()->findOrFail( $beforeId );
            $page->beforeNode( $ref );
        } elseif( $parentId ) {
            $parent = Page::withTrashed()->findOrFail( $parentId );
            $page->appendToNode( $parent );
        } elseif( $root ) {
            $page->makeRoot();
        }
    }


    /**
     * Three-way merges or replaces data based on version conflict detection.
     *
     * @param Page|Element|File $model Model with versions relation
     * @param array<string, mixed> $input Incoming data
     * @param string|null $latestId Version ID the editor was working on
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>|null}
     */
    protected static function mergeOrReplace( Base $model, array $input, ?string $latestId ) : array
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
}
