<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Paths;
use Aimeos\Cms\Resource\Refs;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;


/**
 * Operations on the language variants of pages.
 */
trait Variants
{
    /**
     * Adds a language variant to a page as untranslated copy of the latest source draft.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the new variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Page with the new variant
     * @throws Exception If the language is invalid or the page already has a variant in that language
     */
    public static function addVariant( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        // trashed variants are rejected by translatePage()
        if( PageVariant::where( 'page_id', $id )->where( 'lang', $lang )->exists() ) {
            throw new Exception( sprintf( 'Language "%1$s" already exists', $lang ) );
        }

        return self::translatePage( $id, $lang, $user );
    }


    /**
     * Moves a language variant of a page to the trash.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Trashed page variant
     * @throws Exception If the variant is the source variant of the page
     */
    public static function dropVariant( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return self::variantLifecycle( LifecycleAction::Dropped, [$id], $lang, $user, strict: true )->firstOrFail();
    }


    /**
     * Restores a trashed language variant of a page.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Restored page variant
     */
    public static function restoreVariant( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return self::variantLifecycle( LifecycleAction::Restored, [$id], $lang, $user, strict: true )->firstOrFail();
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
     * @return PageView Page in the new source language
     * @throws Exception If the page has no variant in that language
     */
    public static function setSource( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        $editor = Utils::editor( $user );

        [$page, $changed] = DB::transaction( function() use ( $id, $lang ) {

            $page = PageView::withTrashed()->language( $lang )->lockForUpdate()->find( $id );

            if( !$page ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variant in language "%2$s"', $id, $lang ) );
            }

            if( $page->source === $lang ) {
                return [$page, false];
            }

            Sync::rebase( $page );

            $page->page()->toBase()->update( ['source' => $lang] );

            Sync::state( $page, [], stale: false )->forceFill( ['source' => $lang] )->syncOriginal();

            return [$page, true];
        } );

        if( $changed )
        {
            // the former and the new source variants are listed for the languages without variant
            if( Scout::usesExternalSearch() ) {
                Scout::reindex( PageView::class, [(string) $page->id] );
            }

            self::watch( 'source', collect( [$page] ), $lang, $editor );
        }

        return $page;
    }


    /**
     * Marks the page variants as up to date with the published source variants without changing their content.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Pages with the variants
     * @throws Exception If a variant is the source variant
     */
    public static function ignoreVariants( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $editor = Utils::editor( $user );
        $ids = array_values( array_unique( $ids ) );
        PageView::checkBulk( count( $ids ) );

        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        DB::transaction( function() use ( $ids, $lang, $hashes ) {

            $pages = self::lockVariants( $ids, $lang, trashed: false, strict: true,
                error: 'The source language can\'t be marked as up to date' );

            foreach( $pages as $page ) {
                Sync::state( $page, $hashes[(string) $page->id] ?? throw ( new ModelNotFoundException() )->setModel( PageView::class, (string) $page->id ), stale: false );
            }
        } );

        $pages = self::variantPages( $ids, $lang, self::variantColumns( $fields ) );

        self::watch( 'ignored', $pages, $lang, $editor );

        return $pages;
    }


    /**
     * Translates the source variant of a page into a language variant.
     *
     * Missing variants are created as translated copy of the source, existing ones get the
     * changes of the source merged into a new draft. Without translate callback, the content
     * is copied untranslated and the variant stays stale.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param (callable(array<int, string>, string, ?string, string): array<int, string>)|null $translate Translate callback
     * @return PageView Page with the variant and its new draft
     * @throws Exception If the language is the source language or its variant is in the trash
     */
    public static function translatePage( string $id, string $lang, ?Authenticatable $user = null, ?callable $translate = null ) : PageView
    {
        // existing variants in unconfigured languages (e.g. imported ones) can still be translated
        if( !Utils::isLocale( $lang ) && !PageVariant::where( 'page_id', $id )->where( 'lang', $lang )->exists() ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        [$source, $variant] = self::translatable( $id, $lang );

        // translate outside of the transaction because the AI call is slow
        $result = Sync::translate( $source, $variant, $lang, $translate );
        $result['data'] = Validation::truncate( PageView::class, $result['data'] );
        $editor = $result['translated'] ? Sync::EDITOR : Utils::editor( $user );

        // up to date variants need no new draft
        if( $variant && !$variant->stale && !Hashes::stale( $result['hashes'], (array) $variant->hashes ) ) {
            return $variant;
        }

        $page = DB::transaction( function() use ( $source, $variant, $lang, $result, $editor, $user ) {

            $hashes = $result['hashes'];
            $stale = in_array( '', $hashes, true );

            if( !$variant )
            {
                if( PageVariant::withTrashed()->where( 'page_id', $source->id )->where( 'lang', $lang )->exists() ) {
                    throw new Exception( sprintf( 'Language "%1$s" already exists', $lang ) );
                }

                $path = $result['slug'] !== '' ? $result['slug'] : (string) ( $result['data']['path'] ?? '' );
                [$domain, $path] = self::variantPath( $source, $lang, $path );
                $data = array_replace( $result['data'], ['domain' => $domain, 'path' => $path] );

                $refs = Refs::page( $result['aux'], $user );
                return Pages::addVersion( self::insertVariant( $source, $data, $hashes, $stale, $editor ), $data, $result['aux'], $editor, $refs );
            }

            $page = PageView::withTrashed()->language( $lang )->with( 'latest' )->lockForUpdate()->findOrFail( $source->id );

            // merges with drafts saved by editors in the meantime
            [$data, $aux, $diffs] = Merge::page( $page, $result['data'], $result['aux'], $variant->latest_id, $user );

            Pages::addVersion( $page, $data, $aux, $editor, Refs::page( $aux, $user ), $diffs, $lang );

            // the saved variant is reindexed after the commit, which includes the new stale flag
            Sync::state( $page, $hashes, $stale, index: false );
            $page->announce( ContentAction::Saved, $editor );

            return $page;
        } );

        // new variants have only one version
        $variant
            ? PruneVersions::dispatchChunked( PageView::class, [$page->getVersionKey()] )
            : Scout::sources( [(string) $page->id] );

        self::watch( $variant ? 'translated' : 'added', collect( [$page] ), $lang, Utils::editor( $user ) );

        return $page;
    }


    /**
     * Drops, restores or purges the language variants of several pages.
     *
     * Pages without a matching variant and source variants, which can't be deleted, are skipped.
     *
     * @param string $action "drop", "restore" or "purge"
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Changed page variants
     */
    public static function variants( string $action, array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $action = match( $action ) {
            'drop' => LifecycleAction::Dropped,
            'restore' => LifecycleAction::Restored,
            'purge' => LifecycleAction::Purged,
            default => throw new \InvalidArgumentException( sprintf( 'Invalid variant action "%1$s"', $action ) ),
        };

        $ids = array_values( array_unique( $ids ) );
        PageView::checkBulk( count( $ids ) );

        return self::variantLifecycle( $action, $ids, $lang, $user, strict: false, fields: $fields );
    }


    /**
     * Trashes, restores or deletes the language variants of the pages.
     *
     * The variants are changed in a transaction, the search index, the caches of the pages
     * and the event listeners are updated after commit.
     *
     * @param LifecycleAction $action Lifecycle action
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no matching variant in the language or it's the source variant
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Changed page variants
     */
    protected static function variantLifecycle( LifecycleAction $action, array $ids, string $lang, ?Authenticatable $user,
        bool $strict, array $fields = [] ) : Collection
    {
        $editor = Utils::editor( $user );
        $columns = self::variantColumns( $fields );

        $pages = match( $action ) {
            LifecycleAction::Dropped => self::trashVariants( $ids, $lang, $editor, $strict, $columns ),
            LifecycleAction::Purged => self::deleteVariants( $ids, $lang, $strict, $columns ),
            LifecycleAction::Restored => self::untrashVariants( $ids, $lang, $editor, $strict, $columns ),
        };

        $keys = $pages->pluck( 'variant_id' )->map( strval( ... ) )->all();

        // after commit to keep the index if the transaction fails, the database index keeps trashed variants
        if( $action !== LifecycleAction::Dropped || Scout::usesExternalSearch() )
        {
            Scout::keeps( $action )
                ? Scout::index( PageView::class, $keys, keys: true )
                : Scout::unindex( PageView::class, $keys );
        }

        // pages in the source language are found by the languages of their variants
        Scout::sources( $pages->pluck( 'id' )->map( strval( ... ) )->all() );

        // trashed variants were invalidated when they were dropped
        Invalidation::pages( $pages->filter( fn( PageView $page ) => $action !== LifecycleAction::Purged || $page->getAttribute( 'variant_deleted_at' ) === null ) );
        self::watch( $action->value, $pages, $lang, $editor );

        return $pages;
    }


    /**
     * Moves the language variants of the pages to the trash.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param string $editor Name of the editing user
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param array<string> $columns Columns of the returned pages
     * @return Collection<int, PageView> Trashed page variants
     */
    protected static function trashVariants( array $ids, string $lang, string $editor, bool $strict, array $columns ) : Collection
    {
        $dropped = DB::transaction( function() use ( $ids, $lang, $editor, $strict ) {

            $pages = self::lockVariants( $ids, $lang, trashed: false, strict: $strict );
            $time = ( new PageVariant() )->freshTimestamp();

            foreach( $pages->pluck( 'variant_id' )->chunk( DB::IN_SIZE ) as $chunk ) {
                PageVariant::whereKey( $chunk->all() )->update( ['deleted_at' => $time, 'editor' => $editor] );
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        // the complete pages are loaded after commit to keep the locks short
        return self::variantPages( $dropped, $lang, $columns, trashed: true );
    }


    /**
     * Deletes the language variants of the pages including their versions.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param array<string> $columns Columns of the returned pages
     * @return Collection<int, PageView> Purged page variants
     */
    protected static function deleteVariants( array $ids, string $lang, bool $strict, array $columns ) : Collection
    {
        // the complete pages are required after the variants are deleted, but not locked
        $all = self::variantPages( $ids, $lang, $columns, trashed: true )->keyBy( 'variant_id' );

        return DB::transaction( function() use ( $ids, $lang, $strict, $all, $columns ) {

            $pages = self::lockVariants( $ids, $lang, trashed: true, strict: $strict );

            // variants added after the pages were fetched need their domain and path for cache invalidation
            $missing = $pages->reject( fn( PageView $page ) => $all->has( $page->variant_id ) )->pluck( 'id' )->all();
            $added = self::variantPages( $missing, $lang, $columns, trashed: true )->keyBy( 'variant_id' );

            $pages = $pages->map( fn( PageView $page ) : PageView => $all->get( $page->variant_id ) ?? $added->get( $page->variant_id ) ?? $page );

            PageVariant::purge( $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );

            return $pages->each( fn( PageView $page ) => $page->exists = false );
        } );
    }


    /**
     * Restores the trashed language variants of the pages.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param string $editor Name of the editing user
     * @param bool $strict TRUE to throw if a page has no trashed variant in the language
     * @param array<string> $columns Columns of the returned pages
     * @return Collection<int, PageView> Restored page variants
     */
    protected static function untrashVariants( array $ids, string $lang, string $editor, bool $strict, array $columns ) : Collection
    {
        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        $restored = DB::transaction( function() use ( $ids, $lang, $editor, $strict, $hashes ) {

            $pages = Pages::views( PageView::withTrashed()->language( $lang, trashed: true )->whereNotNull( 'variant_deleted_at' )
                ->whereKey( $ids )->lockForUpdate()->get( ['id', 'variant_id', 'hashes'] ) );

            if( $strict && $pages->isEmpty() ) {
                throw ( new ModelNotFoundException() )->setModel( PageView::class, $ids );
            }

            $variantIds = [];

            foreach( $pages as $page )
            {
                $source = $hashes[(string) $page->id] ?? null;
                $stale = $source === null || Hashes::stale( $source, (array) $page->hashes );

                $variantIds[(int) $stale][] = $page->variant_id;
            }

            foreach( $variantIds as $stale => $list )
            {
                foreach( array_chunk( $list, DB::IN_SIZE ) as $chunk ) {
                    PageVariant::withTrashed()->whereKey( $chunk )->update( ['deleted_at' => null, 'editor' => $editor, 'stale' => (bool) $stale] );
                }
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        return self::variantPages( $restored, $lang, $columns );
    }


    /**
     * Returns the pages with their variants in the language.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param array<string> $columns Columns of the returned pages
     * @param bool $trashed TRUE to include trashed variants
     * @return Collection<int, PageView> Pages with their variants in the language
     */
    protected static function variantPages( array $ids, string $lang, array $columns, bool $trashed = false ) : Collection
    {
        return Pages::views( $ids ? PageView::withTrashed()->language( $lang, $trashed )->whereKey( $ids )->get( $columns ) : [] );
    }


    /**
     * Inserts the record of a new page variant without a version.
     *
     * @param PageView $source Page with its source variant
     * @param array<string, mixed> $data Page data including the language, domain and path of the variant
     * @param array<string, string> $hashes Hashes of the new variant
     * @param bool $stale If the new variant needs an update
     * @param string $editor Name of the editing user
     * @return PageView Page with the new variant
     */
    public static function insertVariant( PageView $source, array $data, array $hashes, bool $stale, string $editor ) : PageView
    {
        $variant = Pages::variantRow( (string) $source->id, Pages::variantData( $data ) + [
            'hashes' => $hashes,
            'stale' => $stale,
            'editor' => $editor,
        ] );

        PageVariant::withoutGlobalScopes()->toBase()->insert( $variant );

        return PageView::query()->variant( (string) $variant['id'] )->firstOrFail();
    }


    /**
     * Returns the locked pages with a variant in the language which isn't the source variant.
     *
     * Only the page ID, the variant ID, the language and the source language are loaded.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param bool $trashed TRUE to include trashed variants
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param string $error Error message if strict and a variant is the source variant
     * @return Collection<int, PageView> Pages with the variant of the language
     * @throws Exception If strict and the variant is the source variant of a page
     */
    protected static function lockVariants( array $ids, string $lang, bool $trashed, bool $strict,
        string $error = 'The source language can not be deleted, change the source language first' ) : Collection
    {
        // only the columns required to check the variants to keep the locked rows small
        $pages = Pages::views( PageView::withTrashed()->language( $lang, $trashed )->whereKey( $ids )->lockForUpdate()
            ->get( ['id', 'variant_id', 'lang', 'source', 'stale'] ) );

        if( $strict && $pages->count() !== count( $ids ) ) {
            throw ( new ModelNotFoundException() )->setModel( PageView::class, $ids );
        }

        if( $strict && $pages->contains( fn( PageView $page ) => $page->isSourceVariant() ) ) {
            throw new Exception( $error );
        }

        return $pages->reject( fn( PageView $page ) => $page->isSourceVariant() )->values();
    }


    /**
     * Returns the source variant and the variant of the language to translate.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @return array{0: PageView, 1: PageView|null} Page with the source variant and with the variant if it exists
     * @throws Exception If the language is the source language or its variant is in the trash
     */
    protected static function translatable( string $id, string $lang ) : array
    {
        $source = PageView::with( 'latest' )->findOrFail( $id );

        if( $source->lang === $lang ) {
            throw new Exception( 'The source language can\'t be translated' );
        }

        $variant = PageView::withTrashed()->language( $lang, trashed: true )->with( 'latest' )->find( $id );

        if( $variant && $variant->getAttribute( 'variant_deleted_at' ) !== null ) {
            throw new Exception( sprintf( 'Language "%1$s" is in the trash, restore it instead', $lang ) );
        }

        return [$source, $variant];
    }


    /**
     * Returns the domain and path of a new page variant.
     *
     * The path is the slug of the source path prefixed with the path of the nearest
     * ancestor having a variant in that language. The domain is the one of the parent
     * variant in that language or the domain of the source variant.
     *
     * @param PageView $page Page with its source variant
     * @param string $lang Language code of the new variant
     * @param string $path Path of the source variant
     * @return array{0: string, 1: string} Domain and path
     */
    protected static function variantPath( PageView $page, string $lang, string $path ) : array
    {
        $db = DB::connection();

        // the parent is the nearest ancestor in most cases and its variant is found by the unique index
        // while looking up the ancestors by the nested set scans all pages before the page
        $ancestor = $page->parent_id ? $db->table( 'cms_page_variants' )
            ->where( 'page_id', $page->parent_id )
            ->where( 'lang', $lang )
            ->where( 'tenant_id', Tenancy::value() )
            ->whereNull( 'deleted_at' )
            ->first( ['page_id as id', 'path', 'domain'] ) : null;

        $ancestor ??= $page->parent_id ? $db->table( 'cms_pages as p' )
            ->join( 'cms_page_variants as v', 'v.page_id', '=', 'p.id' )
            ->where( 'p.tenant_id', Tenancy::value() )
            ->where( 'p.' . NestedSet::LFT, '<', $page->getLft() )
            ->where( 'p.' . NestedSet::RGT, '>', $page->getRgt() )
            ->where( 'v.lang', $lang )
            ->whereNull( 'v.deleted_at' )
            ->orderByDesc( 'p.' . NestedSet::DEPTH )
            ->first( ['p.id', 'v.path', 'v.domain'] ) : null;

        $domain = $ancestor && $ancestor->id === $page->parent_id ? (string) $ancestor->domain : (string) $page->domain;
        $prefix = $ancestor && $ancestor->path !== '' ? $ancestor->path . '/' : '';

        // the path must fit into the column including the "-<lang>-<num>" suffix added for used paths
        $len = 255 - mb_strlen( $lang ) - 6;
        $path = $prefix . rtrim( mb_substr( basename( $path ), 0, max( 1, $len - mb_strlen( $prefix ) ) ), '-' );
        // the inherited prefix of deeply nested pages may already be too long itself
        $path = mb_strlen( $path ) > $len ? rtrim( mb_substr( $path, 0, $len ), '-/' ) : $path;

        // the used candidates are loaded at once instead of one query per collision
        [$known, $loaded] = Paths::known( collect( [( new PageVariant() )->forceFill( ['domain' => $domain, 'path' => $path, 'lang' => $lang] )] ) );

        return [$domain, Paths::unique( $domain, $path, $lang, $known, $loaded )];
    }


    /**
     * Returns the columns of the page variants returned by the bulk operations.
     *
     * Only the columns required by the search index, the caches and the events are loaded
     * together with the requested response fields instead of the complete content.
     *
     * @param array<string> $fields Requested response fields, all columns if empty
     * @return array<int, string> Column names
     */
    protected static function variantColumns( array $fields ) : array
    {
        return $fields ? array_values( array_unique( [
            ...PageView::REQUIRED_COLUMNS,
            'variant_deleted_at',
            ...array_intersect( PageView::RESPONSE_COLUMNS, $fields ),
        ] ) ) : ['*'];
    }


    /**
     * Announces unversioned changes of page variants as bulk event for the audit log, broadcasts and webhooks.
     *
     * @param string $action Action name, e.g. "source", "added", "dropped", "restored", "purged", "translated" or "ignored"
     * @param Collection<int, PageView> $pages Affected pages with the variant of the language
     * @param string $lang Language code of the affected variants
     * @param string $editor Name of the editing user
     */
    protected static function watch( string $action, Collection $pages, string $lang, string $editor ) : void
    {
        $ids = array_values( $pages->pluck( 'id' )->map( strval( ... ) )->all() );

        PageView::announceBulk( 'page', $ids, $pages->mapWithKeys( fn( PageView $page ) => [(string) $page->id => (string) $page->latest_id] )->all(),
            [], $editor, $action, [], array_fill_keys( $ids, $lang ) );
    }
}
