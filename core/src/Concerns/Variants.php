<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageNode;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


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
     * @return Page Page with the new variant
     * @throws Exception If the language is invalid or the page already has a variant in that language
     */
    public static function addVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
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
     * @return Page Trashed page variant
     * @throws Exception If the variant is the source variant of the page
     */
    public static function dropVariant( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        return self::variantLifecycle( 'dropped', [$id], $lang, $user, true )->firstOrFail();
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
        return self::variantLifecycle( 'restored', [$id], $lang, $user, true )->firstOrFail();
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

        [$page, $changed] = Utils::transaction( function() use ( $id, $lang ) {

            /** @var Page|null $page */
            $page = Page::withTrashed()->language( $lang )->lockForUpdate()->find( $id );

            if( !$page ) {
                throw new Exception( sprintf( 'Page "%1$s" has no variant in language "%2$s"', $id, $lang ) );
            }

            if( $page->source === $lang ) {
                return [$page, false];
            }

            Sync::rebase( $page );

            PageNode::withTrashed()->whereKey( $page->id )->toBase()->update( ['source' => $lang] );

            Sync::state( $page, [], false )->forceFill( ['source' => $lang] )->syncOriginal();

            return [$page, true];
        } );

        if( $changed )
        {
            // the former and the new source variants are listed for the languages without variant
            if( Scout::usesExternalSearch() ) {
                Scout::reindex( Page::class, [(string) $page->id] );
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
     * @return Collection<int, Page> Pages with the variants
     * @throws Exception If a variant is the source variant
     */
    public static function ignoreVariants( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $editor = Utils::editor( $user );
        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        Utils::transaction( function() use ( $ids, $lang, $hashes ) {

            $pages = self::pages( Page::withTrashed()->language( $lang )->whereKey( $ids )->lockForUpdate()
                ->get( ['id', 'variant_id', 'lang', 'source'] ) );

            if( $pages->count() !== count( $ids ) ) {
                throw ( new ModelNotFoundException() )->setModel( Page::class, $ids );
            }

            if( $pages->contains( fn( Page $page ) => $page->isSourceVariant() ) ) {
                throw new Exception( 'The source language can\'t be marked as up to date' );
            }

            foreach( $pages as $page ) {
                Sync::state( $page, $hashes[(string) $page->id] ?? throw ( new ModelNotFoundException() )->setModel( Page::class, (string) $page->id ), false );
            }
        } );

        $pages = self::pages( Page::withTrashed()->language( $lang )->whereKey( $ids )->get( self::variantColumns( $fields ) ) );

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
     * @return Page Page with the variant and its new draft
     * @throws Exception If the language is the source language or its variant is in the trash
     */
    public static function translatePage( string $id, string $lang, ?Authenticatable $user = null, ?callable $translate = null ) : Page
    {
        // existing variants in unconfigured languages (e.g. imported ones) can still be translated
        if( !Utils::isLocale( $lang ) && !PageVariant::where( 'page_id', $id )->where( 'lang', $lang )->exists() ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        [$source, $variant] = self::translatable( $id, $lang );

        // translate outside of the transaction because the AI call is slow
        $result = Sync::translate( $source, $variant, $lang, $translate );
        $result['data'] = Validation::truncate( Page::class, $result['data'] );
        $editor = $result['translated'] ? Sync::EDITOR : Utils::editor( $user );

        // up to date variants need no new draft
        if( $variant && !$variant->stale && !Hashes::stale( $result['hashes'], (array) $variant->hashes ) ) {
            return $variant;
        }

        $page = Utils::transaction( function() use ( $source, $variant, $lang, $result, $editor, $user ) {

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

                $refs = self::refs( $result['aux'], $user );
                return self::addVersion( self::insertVariant( $source, $data, $hashes, $stale, $editor ), $data, $result['aux'], $editor, $refs );
            }

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang )->with( 'latest' )->lockForUpdate()->findOrFail( $source->id );

            // merges with drafts saved by editors in the meantime
            [$data, $aux, $diffs] = Merge::page( $page, $result['data'], $result['aux'], $variant->latest_id, $user );

            self::addVersion( $page, $data, $aux, $editor, self::refs( $aux, $user ), $diffs, $lang );

            Sync::state( $page, $hashes, $stale );
            $page->announce( 'saved', $editor );

            return $page;
        } );

        // new variants have only one version
        $variant
            ? self::pruneVersions( Page::class, [$page->getVersionKey()] )
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
     * @return Collection<int, Page> Changed page variants
     */
    public static function variants( string $action, array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $action = match( $action ) {
            'drop' => 'dropped',
            'restore' => 'restored',
            'purge' => 'purged',
            default => throw new \InvalidArgumentException( sprintf( 'Invalid variant action "%1$s"', $action ) ),
        };

        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        return self::variantLifecycle( $action, $ids, $lang, $user, false, $fields );
    }


    /**
     * Trashes, restores or deletes the language variants of the pages.
     *
     * The variants are changed in a transaction, the search index, the caches of the pages
     * and the event listeners are updated after commit.
     *
     * @param 'dropped'|'purged'|'restored' $action Lifecycle action
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no matching variant in the language or it's the source variant
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, Page> Changed page variants
     */
    protected static function variantLifecycle( string $action, array $ids, string $lang, ?Authenticatable $user = null,
        bool $strict = false, array $fields = [] ) : Collection
    {
        $editor = Utils::editor( $user );
        $columns = self::variantColumns( $fields );

        $pages = match( $action ) {
            'dropped' => self::trashVariants( $ids, $lang, $editor, $strict, $columns ),
            'purged' => self::deleteVariants( $ids, $lang, $strict, $columns ),
            'restored' => self::untrashVariants( $ids, $lang, $editor, $strict, $columns ),
        };

        // after commit to keep the index if the transaction fails, the database index keeps trashed variants
        if( $pages->isNotEmpty() && ( $action !== 'dropped' || Scout::usesExternalSearch() ) )
        {
            $action === 'restored' || ( $action === 'dropped' && config( 'scout.soft_delete' ) )
                ? Scout::index( Page::class, $pages->pluck( 'id' )->map( strval( ... ) )->all(), $fields ? null : $pages )
                : Scout::unindex( Page::class, $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );
        }

        // pages in the source language are found by the languages of their variants
        Scout::sources( $pages->pluck( 'id' )->map( strval( ... ) )->all() );

        // trashed variants were invalidated when they were dropped
        self::invalidatePages( $pages->filter( fn( Page $page ) => $action !== 'purged' || $page->getAttribute( 'variant_deleted_at' ) === null ) );
        self::watch( $action, $pages, $lang, $editor );

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
     * @return Collection<int, Page> Trashed page variants
     */
    protected static function trashVariants( array $ids, string $lang, string $editor, bool $strict, array $columns = ['*'] ) : Collection
    {
        $dropped = Utils::transaction( function() use ( $ids, $lang, $editor, $strict ) {

            $pages = self::deletableVariants( $ids, $lang, false, $strict );
            $time = ( new PageVariant() )->freshTimestamp();

            foreach( $pages->pluck( 'variant_id' )->chunk( 500 ) as $chunk ) {
                PageVariant::whereKey( $chunk->all() )->update( ['deleted_at' => $time, 'editor' => $editor] );
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        // the complete pages are loaded after commit to keep the locks short
        return self::pages( $dropped ? Page::withTrashed()->language( $lang, true )->whereKey( $dropped )->get( $columns ) : [] );
    }


    /**
     * Deletes the language variants of the pages including their versions.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param array<string> $columns Columns of the returned pages
     * @return Collection<int, Page> Purged page variants
     */
    protected static function deleteVariants( array $ids, string $lang, bool $strict, array $columns = ['*'] ) : Collection
    {
        // the complete pages are required after the variants are deleted, but not locked
        $all = self::pages( Page::withTrashed()->language( $lang, true )->whereKey( $ids )->get( $columns ) )->keyBy( 'variant_id' );

        return Utils::transaction( function() use ( $ids, $lang, $strict, $all, $columns ) {

            $pages = self::deletableVariants( $ids, $lang, true, $strict );

            // variants added after the pages were fetched need their domain and path for cache invalidation
            $missing = $pages->reject( fn( Page $page ) => $all->has( $page->variant_id ) )->pluck( 'id' )->all();
            $added = $missing
                ? self::pages( Page::withTrashed()->language( $lang, true )->whereKey( $missing )->get( $columns ) )->keyBy( 'variant_id' )
                : collect();

            $pages = $pages->map( fn( Page $page ) : Page => $all->get( $page->variant_id ) ?? $added->get( $page->variant_id ) ?? $page );

            // same as PageVariant::prune() for all variants at once
            foreach( $pages->pluck( 'variant_id' )->chunk( 500 ) as $chunk )
            {
                Version::where( 'versionable_type', PageVariant::class )->whereIn( 'versionable_id', $chunk->all() )->delete();
                PageVariant::withTrashed()->whereKey( $chunk->all() )->forceDelete();
            }

            return $pages->each( fn( Page $page ) => $page->exists = false );
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
     * @return Collection<int, Page> Restored page variants
     */
    protected static function untrashVariants( array $ids, string $lang, string $editor, bool $strict, array $columns = ['*'] ) : Collection
    {
        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        $restored = Utils::transaction( function() use ( $ids, $lang, $editor, $strict, $hashes ) {

            $pages = self::pages( Page::withTrashed()->language( $lang, true )->whereNotNull( 'variant_deleted_at' )
                ->whereKey( $ids )->lockForUpdate()->get( ['id', 'variant_id', 'hashes'] ) );

            if( $strict && $pages->isEmpty() ) {
                throw ( new ModelNotFoundException() )->setModel( Page::class, $ids );
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
                foreach( array_chunk( $list, 500 ) as $chunk ) {
                    PageVariant::withTrashed()->whereKey( $chunk )->update( ['deleted_at' => null, 'editor' => $editor, 'stale' => (bool) $stale] );
                }
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        return self::pages( $restored ? Page::withTrashed()->language( $lang )->whereKey( $restored )->get( $columns ) : [] );
    }


    /**
     * Inserts the record of a new page variant without a version.
     *
     * @param Page $source Page with its source variant
     * @param array<string, mixed> $data Page data including the language, domain and path of the variant
     * @param array<string, string> $hashes Hashes of the new variant
     * @param bool $stale If the new variant needs an update
     * @param string $editor Name of the editing user
     * @return Page Page with the new variant
     */
    public static function insertVariant( Page $source, array $data, array $hashes, bool $stale, string $editor ) : Page
    {
        $variant = self::variantRow( (string) $source->id, [], array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) ) + [
            'hashes' => $hashes,
            'stale' => $stale,
            'editor' => $editor,
        ] );

        PageVariant::withoutGlobalScopes()->toBase()->insert( $variant );

        return Page::variant( (string) $variant['id'] )->firstOrFail();
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
     * @return Collection<int, Page> Pages with the variant of the language
     * @throws Exception If strict and the variant is the source variant of a page
     */
    protected static function deletableVariants( array $ids, string $lang, bool $trashed = false, bool $strict = false ) : Collection
    {
        // only the columns required to check the variants to keep the locked rows small
        $pages = self::pages( Page::withTrashed()->language( $lang, $trashed )->whereKey( $ids )->lockForUpdate()
            ->get( ['id', 'variant_id', 'lang', 'source'] ) );

        if( $strict && $pages->count() !== count( $ids ) ) {
            throw ( new ModelNotFoundException() )->setModel( Page::class, $ids );
        }

        if( $strict && $pages->contains( fn( Page $page ) => $page->isSourceVariant() ) ) {
            throw new Exception( 'The source language can not be deleted, change the source language first' );
        }

        return $pages->reject( fn( Page $page ) => $page->isSourceVariant() )->values();
    }


    /**
     * Returns the pages of the list.
     *
     * @param iterable<mixed> $items List of models
     * @return Collection<int, Page> List of pages
     */
    protected static function pages( iterable $items ) : Collection
    {
        $list = [];

        foreach( $items as $item ) {
            if( $item instanceof Page ) {
                $list[] = $item;
            }
        }

        return collect( $list );
    }


    /**
     * Returns the source variant and the variant of the language to translate.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @return array{0: Page, 1: Page|null} Page with the source variant and with the variant if it exists
     * @throws Exception If the language is the source language or its variant is in the trash
     */
    protected static function translatable( string $id, string $lang ) : array
    {
        /** @var Page $source */
        $source = Page::with( 'latest' )->findOrFail( $id );

        if( $source->lang === $lang ) {
            throw new Exception( 'The source language can\'t be translated' );
        }

        /** @var Page|null $variant */
        $variant = Page::withTrashed()->language( $lang, true )->with( 'latest' )->find( $id );

        if( $variant && $variant->getAttribute( 'variant_deleted_at' ) !== null ) {
            throw new Exception( sprintf( 'Language "%1$s" is in the trash, restore it instead', $lang ) );
        }

        return [$source, $variant];
    }


    /**
     * Returns a path that isn't used by another page variant in the domain.
     *
     * @param string $domain Domain name
     * @param string $path Preferred path
     * @param string $lang Language code appended on collisions
     * @param array<string, array<string, bool>> $known Known paths by domain, TRUE if used, updated with the returned path
     * @param array<string, array<string, string>> $loaded Languages by domain and path whose candidates are all in $known
     * @return string Unique path
     */
    protected static function uniquePath( string $domain, string $path, string $lang, array &$known = [], array $loaded = [] ) : string
    {
        $base = $path === '' ? $lang : $path . '-' . $lang;
        $complete = ( $loaded[$domain][$path] ?? null ) === $lang;
        $candidate = $path;
        $num = 1;

        while( $known[$domain][$candidate] ?? ( !$complete && PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $candidate )->exists() ) ) {
            $candidate = $num > 1 ? $base . '-' . $num : $base;
            $num++;
        }

        $known[$domain][$candidate] = true;
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

        $domain = $ancestor && $ancestor->id === $page->parent_id ? (string) $ancestor->domain : (string) $page->domain;
        $prefix = $ancestor && $ancestor->path !== '' ? $ancestor->path . '/' : '';

        // the path must fit into the column including the "-<lang>-<num>" suffix added for used paths
        $len = 255 - mb_strlen( $lang ) - 6;
        $path = $prefix . rtrim( mb_substr( basename( $path ), 0, max( 1, $len - mb_strlen( $prefix ) ) ), '-' );
        // the inherited prefix of deeply nested pages may already be too long itself
        $path = mb_strlen( $path ) > $len ? rtrim( mb_substr( $path, 0, $len ), '-/' ) : $path;

        // the used candidates are loaded at once instead of one query per collision
        [$known, $loaded] = self::knownPaths( collect( [( new PageVariant() )->forceFill( ['domain' => $domain, 'path' => $path, 'lang' => $lang] )] ) );

        return [$domain, self::uniquePath( $domain, $path, $lang, $known, $loaded )];
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
            ...Page::REQUIRED_COLUMNS,
            'variant_deleted_at',
            ...array_intersect( Page::RESPONSE_COLUMNS, $fields ),
        ] ) ) : ['*'];
    }


    /**
     * Announces unversioned changes of page variants as bulk event for the audit log, broadcasts and webhooks.
     *
     * @param string $action Action name, e.g. "source", "added", "dropped", "restored", "purged", "translated" or "ignored"
     * @param Collection<int, Page> $pages Affected pages with the variant of the language
     * @param string $lang Language code of the affected variants
     * @param string $editor Name of the editing user
     */
    protected static function watch( string $action, Collection $pages, string $lang, string $editor ) : void
    {
        $ids = array_values( $pages->pluck( 'id' )->map( strval( ... ) )->all() );

        Page::announceBulk( 'page', $ids, $pages->mapWithKeys( fn( Page $page ) => [(string) $page->id => (string) $page->latest_id] )->all(),
            [], $editor, $action, [], array_fill_keys( $ids, $lang ) );
    }
}
