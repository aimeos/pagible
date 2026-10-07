<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Events\Translation;
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
     * Adds a language variant to a page as untranslated copy of the latest source draft.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the new variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Page with the new variant
     * @throws Exception If the language is invalid or the page already has a variant in that language
     */
    public static function addVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        $lang = self::checkLang( $lang );
        $editor = Utils::editor( $user );

        $page = Utils::transaction( function() use ( $id, $lang, $editor, $user ) {

            /** @var Page $source */
            $source = Page::withTrashed()->with( 'latest' )->findOrFail( $id );

            $existing = PageVariant::withTrashed()->where( 'page_id', $source->id )->where( 'lang', $lang )->first( ['id', 'deleted_at'] );

            if( $existing ) {
                throw new Exception( $existing->trashed()
                    ? sprintf( 'Language "%1$s" is in the trash, restore it instead', $lang )
                    : sprintf( 'Language "%1$s" already exists', $lang )
                );
            }

            $data = (array) ( $source->latest->data ?? $source->only( [...Hashes::PAGE_FIELDS, 'path', 'domain', 'to', 'status'] ) );
            $aux = (array) ( $source->latest->aux ?? [] );

            [$domain, $path] = self::variantPath( $source, $lang, (string) ( $data['path'] ?? $source->path ) );
            $data = ['lang' => $lang, 'domain' => $domain, 'path' => $path] + $data;

            $variant = new PageVariant();
            $variant->forceFill( array_intersect_key( $data, array_flip( ['to', 'name', 'title', 'type', 'theme', 'tag', 'cache'] ) ) + [
                'page_id' => $source->id,
                'lang' => $lang,
                'domain' => $domain,
                'path' => $path,
                'status' => 0,
                'hashes' => self::copyHashes( $data, $aux ),
                'stale' => true,
                'editor' => $editor,
            ] )->save();

            return self::addVersion( Page::variant( (string) $variant->id )->firstOrFail(), $data, $aux, $editor, $user );
        } );

        self::watch( 'added', $page, [$lang], $editor );

        return $page;
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

        return Utils::lockedTransaction( function() use ( $id, $ref, $parent, $editor, $user ) {

            /** @var Page $root */
            $root = Page::select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )->findOrFail( $id );

            $pages = Page::select( 'id', 'parent_id', 'source', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
                ->where( NestedSet::LFT, '>=', $root->getLft() )
                ->where( NestedSet::RGT, '<=', $root->getRgt() )
                ->orderBy( NestedSet::LFT )
                ->get();

            Page::checkBulk( $pages->count() );

            $variants = PageVariant::whereIn( 'page_id', $pages->pluck( 'id' )->all() )
                ->orderBy( 'lang' )->get()->groupBy( 'page_id' );

            $versions = Version::with( ['files:id', 'elements:id'] )
                ->whereIn( 'id', $variants->flatten()->pluck( 'latest_id' )->filter()->all() )
                ->get()->keyBy( 'id' );

            $copies = [];
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
                    $data = (array) ( $version->data ?? $variant->only( ['path', 'domain', 'to', 'name', 'title', 'type', 'theme', 'tag', 'cache', 'status'] ) );
                    $aux = (array) ( $version->aux ?? ['content' => $variant->content, 'meta' => $variant->meta, 'config' => $variant->config] );

                    $data['lang'] = $variant->lang;
                    $data['domain'] = (string) ( $data['domain'] ?? $variant->domain );
                    $data['path'] = self::uniquePath( $data['domain'], (string) ( $data['path'] ?? $variant->path ), $variant->lang );

                    $fields = array_intersect_key( $data, array_flip( ['lang', 'path', 'domain', 'to', 'name', 'title', 'type', 'theme', 'tag', 'cache', 'status'] ) );

                    if( $page === null )
                    {
                        $page = new Page();
                        $page->forceFill( $fields + [
                            'source' => $variant->lang,
                            'hashes' => $variant->hashes,
                            'stale' => $variant->stale,
                            'editor' => $editor,
                        ] );

                        isset( $copies[(string) $orig->parent_id] ) && $orig->id !== $root->id
                            ? $page->appendToNode( $copies[(string) $orig->parent_id] )
                            : $page->position( $ref, $parent );

                        $page->save();
                        $copies[(string) $orig->id] = $page;
                        $copy = $page;
                    }
                    else
                    {
                        $new = new PageVariant();
                        $new->forceFill( $fields + [
                            'page_id' => $page->id,
                            'hashes' => $variant->hashes,
                            'stale' => $variant->stale,
                            'editor' => $editor,
                        ] )->save();

                        $copy = Page::variant( (string) $new->id )->firstOrFail();
                    }

                    $refs = $version ? [
                        'files' => $version->files->pluck( 'id' )->all(),
                        'elements' => $version->elements->pluck( 'id' )->all(),
                    ] : null;

                    $copy = self::addVersion( $copy, $data, $aux, $editor, $user, $refs );
                    $result ??= $copy;
                }

                $page?->announce( 'added', $editor );
            }

            if( !$result ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variants', $id ) );
            }

            return $result;
        } );
    }


    /**
     * Moves a language variant of a page to the trash.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Trashed page variant
     * @throws Exception If the variant is the source variant of the page
     */
    public static function dropVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        $page = Utils::transaction( function() use ( $id, $lang, $editor ) {

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang )->lockForUpdate()->findOrFail( $id );

            if( $page->lang === $page->source ) {
                throw new Exception( 'The source language can not be deleted, change the source language first' );
            }

            $time = ( new PageVariant() )->freshTimestamp();

            PageVariant::whereKey( $page->variant_id )->update( ['deleted_at' => $time, 'editor' => $editor] );
            $page->forceFill( ['variant_deleted_at' => $time, 'editor' => $editor] )->syncOriginal();

            return $page;
        } );

        if( Scout::usesExternalSearch() ) {
            config( 'scout.soft_delete' )
                ? Scout::index( Page::class, [(string) $page->id], collect( [$page] ) )
                : Scout::unindex( Page::class, [$page->variant_id] );
        }

        self::invalidatePages( [$page] );
        self::watch( 'dropped', $page, [$lang], $editor );

        return $page;
    }


    /**
     * Deletes a language variant of a page including its versions.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Purged page variant
     * @throws Exception If the variant is the source variant of the page
     */
    public static function purgeVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        $page = Utils::transaction( function() use ( $id, $lang ) {

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang, true )->lockForUpdate()->findOrFail( $id );

            if( $page->lang === $page->source ) {
                throw new Exception( 'The source language can not be deleted, change the source language first' );
            }

            Version::where( 'versionable_type', PageVariant::class )
                ->where( 'versionable_id', $page->variant_id )
                ->delete();

            PageVariant::withTrashed()->whereKey( $page->variant_id )->forceDelete();
            $page->exists = false;

            return $page;
        } );

        if( $page->getAttribute( 'variant_deleted_at' ) === null ) {
            self::invalidatePages( [$page] );
        }

        Scout::unindex( Page::class, [$page->variant_id] );
        self::watch( 'purged', $page, [$lang], $editor );

        return $page;
    }


    /**
     * Restores a trashed language variant of a page.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Restored page variant
     */
    public static function restoreVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        $page = Utils::transaction( function() use ( $id, $lang, $editor ) {

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang, true )->whereNotNull( 'variant_deleted_at' )
                ->lockForUpdate()->findOrFail( $id );

            $source = Page::withTrashed()->findOrFail( $id );
            $hashes = (array) $page->hashes;
            $stale = empty( $hashes ) || Hashes::stale( self::publishedHashes( $source ), $hashes );

            PageVariant::withTrashed()->whereKey( $page->variant_id )->update( [
                'deleted_at' => null,
                'editor' => $editor,
                'stale' => $stale,
            ] );

            $page->forceFill( ['variant_deleted_at' => null, 'editor' => $editor, 'stale' => $stale] )->syncOriginal();

            return $page;
        } );

        Scout::index( Page::class, [(string) $page->id], collect( [$page] ) );
        self::invalidatePages( [$page] );
        self::watch( 'restored', $page, [$lang], $editor );

        return $page;
    }


    /**
     * Changes the source language of a page.
     *
     * The hashes of the other variants are reset to the hashes of the new source for the
     * element IDs available in both, so later updates only contain real changes.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of an existing variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Page in the new source language
     * @throws Exception If the page has no variant in that language
     */
    public static function setSource( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        $editor = Utils::editor( $user );

        $result = Utils::transaction( function() use ( $id, $lang ) {

            /** @var Page|null $page */
            $page = Page::withTrashed()->language( $lang )->lockForUpdate()->find( $id );

            if( !$page ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variant in language "%2$s"', $id, $lang ) );
            }

            if( $page->source === $lang ) {
                return [$page, false];
            }

            $hashes = self::publishedHashes( $page );
            $elements = array_flip( Hashes::elements( $hashes ) );

            $variants = PageVariant::withTrashed()->where( 'page_id', $page->id )
                ->where( 'id', '!=', $page->variant_id )
                ->get( ['id', 'lang', 'content', 'hashes', 'stale'] );

            foreach( $variants as $variant )
            {
                // the former source is the origin of all linked variants
                $linked = !empty( $variant->hashes ) || $variant->lang === $page->source;
                $new = [];

                foreach( (array) $variant->content as $item )
                {
                    $elid = ( (array) $item )['id'] ?? null;

                    if( is_scalar( $elid ) && isset( $elements[(string) $elid] ) ) {
                        $new['el:' . $elid] = $hashes['el:' . $elid];
                    }
                }

                if( $linked ) {
                    $new += array_filter( $hashes, fn( $key ) => !str_starts_with( $key, 'el:' ), ARRAY_FILTER_USE_KEY );
                }

                PageVariant::withTrashed()->whereKey( $variant->id )->update( [
                    'hashes' => json_encode( (object) $new ),
                    'stale' => !$linked || Hashes::stale( $hashes, $new ),
                ] );
            }

            PageVariant::whereKey( $page->variant_id )->update( ['hashes' => '{}', 'stale' => false] );
            Page::withTrashed()->whereKey( $page->id )->toBase()->update( ['source' => $lang] );

            $page->forceFill( ['source' => $lang, 'hashes' => [], 'stale' => false] )->syncOriginal();

            return [$page, true];
        } );

        [$page, $changed] = $result;

        if( $changed ) {
            self::watch( 'source', $page, [$lang], $editor );
        }

        return $page;
    }

    /**
     * Trashes, restores or purges the variants of several pages in one language.
     *
     * Pages without a matching variant and source variants, which can't be deleted, are skipped.
     *
     * @param string $action "drop", "restore" or "purge"
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Collection<int, Page> Changed page variants
     */
    public static function variants( string $action, array $ids, string $lang, ?Authenticatable $user = null ) : Collection
    {
        $method = match( $action ) {
            'drop' => 'dropVariant',
            'restore' => 'restoreVariant',
            'purge' => 'purgeVariant',
            default => throw new \InvalidArgumentException( sprintf( 'Invalid variant action "%1$s"', $action ) ),
        };

        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        $items = collect();

        foreach( $ids as $id )
        {
            try {
                $items->push( self::$method( $id, $lang, $user ) );
            } catch( Exception | \Illuminate\Database\Eloquent\ModelNotFoundException $e ) {
                // source variant or no variant in that language
            }
        }

        return $items;
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
     * Invalidates the routes of the given Page models, grouped by domain.
     *
     * Non-Page values are ignored so lifecycle collections can be passed without additional filtering.
     *
     * @param iterable<array-key, mixed> $pages Candidate Page models
     */
    public static function invalidatePages( iterable $pages ) : void
    {
        $paths = [];

        foreach( $pages as $page ) {
            if( $page instanceof Page ) {
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
     * Validates a language code.
     *
     * @param string $lang Language code, e.g. "en" or "zh-Hant"
     * @return string Validated language code
     * @throws Exception If the language code is invalid
     */
    protected static function checkLang( string $lang ) : string
    {
        if( strlen( $lang ) > 10 || !preg_match( '/^[a-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $lang ) ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        return $lang;
    }


    /**
     * Returns the hashes of an untranslated copy of the source.
     *
     * Items with texts to translate get an empty hash, so they still count as changed.
     *
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed> $aux Content, meta and config
     * @return array<string, string> Hashes by key
     */
    protected static function copyHashes( array $data, array $aux ) : array
    {
        $hashes = Hashes::page( $data, $aux['content'] ?? [], $aux['meta'] ?? [], $aux['config'] ?? [] );
        $refs = [];

        foreach( (array) ( $aux['content'] ?? [] ) as $item )
        {
            $item = (array) $item;

            if( ( $item['type'] ?? null ) === 'reference' && isset( $item['id'] ) ) {
                $refs['el:' . $item['id']] = true;
            }
        }

        foreach( $hashes as $key => $hash )
        {
            if( $key === 'page:title' || $key === 'page:name' || str_starts_with( $key, 'meta:' )
                || str_starts_with( $key, 'el:' ) && !isset( $refs[$key] )
            ) {
                $hashes[$key] = '';
            }
        }

        return $hashes;
    }


    /**
     * Returns the hashes of the published variant of the page.
     *
     * @param Page $page Page with the variant
     * @return array<string, string> Hashes by key
     */
    protected static function publishedHashes( Page $page ) : array
    {
        return Hashes::page( $page->only( Hashes::PAGE_FIELDS ), $page->content, $page->meta, $page->config );
    }


    /**
     * Returns a path that isn't used by another page variant in the domain.
     *
     * @param string $domain Domain name
     * @param string $path Preferred path
     * @param string $lang Language code appended on collisions
     * @return string Unique path
     */
    protected static function uniquePath( string $domain, string $path, string $lang ) : string
    {
        $base = $path === '' ? $lang : $path . '-' . $lang;
        $candidate = $path;
        $num = 1;

        while( PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $candidate )->exists() ) {
            $candidate = $num++ === 1 ? $base : $base . '-' . $num;
        }

        return $candidate;
    }


    /**
     * Returns the domain and path of a new page variant.
     *
     * The path is the slug of the source path prefixed with the path of the nearest
     * ancestor having a variant in that language. The domain is the one of the parent
     * variant in that language or the domain of the source variant.
     *
     * @param Page $page Page with its source variant
     * @param string $lang Language code of the new variant
     * @param string $path Path of the source variant
     * @return array{0: string, 1: string} Domain and path
     */
    protected static function variantPath( Page $page, string $lang, string $path ) : array
    {
        $ancestor = DB::connection( config( 'cms.db', 'sqlite' ) )
            ->table( 'cms_pages as p' )
            ->join( 'cms_page_variants as v', 'v.page_id', '=', 'p.id' )
            ->where( 'p.tenant_id', Tenancy::value() )
            ->where( 'p.' . NestedSet::LFT, '<', $page->getLft() )
            ->where( 'p.' . NestedSet::RGT, '>', $page->getRgt() )
            ->where( 'v.lang', $lang )
            ->whereNull( 'v.deleted_at' )
            ->orderByDesc( 'p.' . NestedSet::DEPTH )
            ->first( ['p.id', 'v.path', 'v.domain'] );

        $slug = basename( $path );
        $domain = $ancestor && $ancestor->id === $page->parent_id ? (string) $ancestor->domain : (string) $page->domain;
        $path = $ancestor && $ancestor->path !== '' ? $ancestor->path . '/' . $slug : $slug;

        return [$domain, self::uniquePath( $domain, $path, $lang )];
    }


    /**
     * Dispatches the watch event for unversioned changes of page variants.
     *
     * @param string $action Action name
     * @param Page $page Affected page
     * @param array<int, string> $langs Affected languages
     * @param string $editor Name of the editing user
     */
    protected static function watch( string $action, Page $page, array $langs, string $editor ) : void
    {
        Watch::dispatch( Translation::class, fn() => new Translation(
            $action, (string) $page->id, $langs, $editor, false, (string) $page->tenant_id
        ) );
    }


    /**
     * Marks the translations of the published source variants as stale if the source changed.
     *
     * Trashed variants are skipped because restoring them recomputes the flag.
     *
     * @param iterable<Page> $pages Published pages with their source variant
     */
    public static function staleVariants( iterable $pages ) : void
    {
        $sources = $hashes = [];

        foreach( $pages as $page )
        {
            if( $page->isSourceVariant() && $page->source ) {
                $sources[(string) $page->id] = $page;
            }
        }

        foreach( array_chunk( array_keys( $sources ), 100 ) as $chunk )
        {
            $ids = [];
            $variants = PageVariant::whereIn( 'page_id', $chunk )->where( 'stale', false )
                ->get( ['id', 'page_id', 'lang', 'hashes'] );

            foreach( $variants as $variant )
            {
                $source = $sources[$variant->page_id];

                if( $variant->lang !== $source->source && ( empty( $variant->hashes )
                    || Hashes::stale( $hashes[$variant->page_id] ??= self::publishedHashes( $source ), (array) $variant->hashes ) )
                ) {
                    $ids[] = $variant->id;
                }
            }

            if( !empty( $ids ) ) {
                PageVariant::whereIn( 'id', $ids )->toBase()->update( ['stale' => true] );
            }
        }
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
        $variantKeys = [];

        if( !$isPage ) {
            sort( $ids, SORT_STRING );
        }

        $apply = function( array $ids ) use ( $action, $announce, $editor, $fields, $isPage, $model, &$pages, &$variantKeys ) {
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
                $pages = self::pageSubtree( $items )->select( 'id', 'tenant_id', 'domain', 'path', NestedSet::LFT )
                    ->orderBy( NestedSet::LFT )->lockForUpdate()->get();
            } elseif( $isPage ) {
                $pages = $items;
            }

            // Pages are indexed per variant, collect them before they are removed
            if( $isPage && Scout::usesSearchIndex()
                && ( $action === 'purged' || $action === 'dropped' && Scout::usesExternalSearch() && !config( 'scout.soft_delete' ) )
            ) {
                foreach( $pages->pluck( 'id' )->chunk( 500 ) as $chunk ) {
                    array_push( $variantKeys, ...PageVariant::withTrashed()->whereIn( 'page_id', $chunk->all() )->pluck( 'id' )->all() );
                }
            }

            // Purged files are announced with their publication state, which is removed with their versions
            if( $action === 'purged' && $model === File::class && $announce && Base::announces( Purged::class ) ) {
                $items->load( ['latest' => fn( $query ) => $query->select( 'id', 'published', 'publish_at', 'created_at' )] );
            }

            // The versions are removed together with the page variants
            if( $action === 'purged' && $isPage && $announce && Base::announces( Purged::class ) ) {
                $items->load( 'latest' );
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
            self::invalidatePages( $pages );
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
            Scout::unindex( $model, $isPage ? $variantKeys : $changed );
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
     * @param bool $copy TRUE to prefetch the references of the latest versions which are copied to the new ones
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    protected static function bulk( string $model, array $ids, array $input, ?Authenticatable $user, \Closure $save, bool $copy = true ) : array
    {
        if( empty( $ids ) || empty( $input ) ) {
            return ['ids' => [], 'latest' => [], 'data' => [], 'failed' => 0];
        }

        $editor = Utils::editor( $user );
        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );

        // suppress Scout's per-save reindex; the whole batch is reindexed once below
        $latest = Scout::mute( [$model], function() use ( $ids, $model, $save, $copy, $editor ) {
            $result = [];

            foreach( array_chunk( $ids, 50 ) as $chunk )
            {
                $refs = $copy ? $model::refs( $chunk ) : [];

                foreach( $chunk as $id )
                {
                    try
                    {
                        /** @var Page|File|Element|null $item */
                        $item = $model::locked( fn() => Utils::transaction( fn() => $save( $id, $refs, $editor ) ) );

                        if( $item ) {
                            $result[(string) $item->id] = (string) $item->latest_id;
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

        $model::locked( fn() => self::pruneVersions( $model, $saved ) );

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
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     * @throws Exception If the new source language in $input['source'] has no variant
     */
    public static function savePage( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null,
        ?string $lang = null ) : Page
    {
        $source = isset( $input['source'] ) ? (string) $input['source'] : null;
        $input = Validation::page( $input, $user );
        $editor = Utils::editor( $user );

        return Utils::transaction( function() use ( $id, $input, $user, $editor, $latestId, $lang, $source ) {

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang )->with( 'latest' )->findOrFail( $id );

            if( !empty( $input ) || $source === null )
            {
                self::applyPage( $page, $input, $editor, $latestId, $user );
                $page->announce( 'saved', $editor );
                self::pruneVersions( Page::class, [$page->id] );
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
     * @return array{ids: list<string>, latest: array<string, string>, data: array<string, mixed>, failed: int}
     */
    public static function bulkPage( array $ids, array $input, ?Authenticatable $user = null, bool $descendants = false ) : array
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

        $copy = !array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );

        return self::bulk( Page::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user ) : ?Page {

            if( !( $page = Page::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            // bulk only creates new draft versions, so the cached published output is unchanged
            self::applyPage( $page, $input, $editor, null, $user, $refs[$page->latest_id ?? ''] ?? null );

            return $page;
        }, $copy );
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
