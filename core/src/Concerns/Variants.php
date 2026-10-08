<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Translation;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
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
        return self::dropVariants( [$id], $lang, $user, true )->firstOrFail();
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
        return self::purgeVariants( [$id], $lang, $user, true )->firstOrFail();
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
        return self::restoreVariants( [$id], $lang, $user, true )->firstOrFail();
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

            $hashes = Hashes::published( $page );

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

                    if( is_scalar( $elid ) && isset( $hashes['el:' . $elid] ) ) {
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

            Page::withTrashed()->whereKey( $page->id )->toBase()->update( ['source' => $lang] );

            self::syncState( $page, [], false )->forceFill( ['source' => $lang] )->syncOriginal();

            return [$page, true];
        } );

        if( $changed )
        {
            // the former and the new source variants are listed for the languages without variant
            if( Scout::usesExternalSearch() ) {
                Scout::reindex( Page::class, [(string) $page->id] );
            }

            self::watch( 'source', $page, [$lang], $editor );
        }

        return $page;
    }

    /**
     * Marks a page variant as up to date with the published source variant without changing its content.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Page Page with the variant
     * @throws Exception If the variant is the source variant
     */
    public static function ignoreChanges( string $id, string $lang, ?Authenticatable $user = null ) : Page
    {
        return self::ignoreVariants( [$id], $lang, $user )->firstOrFail();
    }


    /**
     * Marks the page variants as up to date with the published source variants without changing their content.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Collection<int, Page> Pages with the variants
     * @throws Exception If a variant is the source variant
     */
    public static function ignoreVariants( array $ids, string $lang, ?Authenticatable $user = null ) : Collection
    {
        $editor = Utils::editor( $user );
        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        // hashed before locking the variants to keep the transaction short
        $hashes = self::sourceHashes( $ids );

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
                self::syncState( $page, $hashes[(string) $page->id] ?? throw ( new ModelNotFoundException() )->setModel( Page::class, (string) $page->id ), false );
            }
        } );

        $pages = self::pages( Page::withTrashed()->language( $lang )->whereKey( $ids )->get() );

        $pages->each( fn( Page $page ) => self::watch( 'ignored', $page, [$lang], $editor ) );

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
        if( !Utils::isValidLang( $lang ) ) {
            throw new Exception( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        [$source, $variant] = self::translatable( $id, $lang );

        // translate outside of the transaction because the AI call is slow
        $result = Sync::translate( $source, $variant, $lang, $translate );
        $editor = $result['translated'] ? Sync::EDITOR : Utils::editor( $user );

        // up to date variants need no new draft
        if( $variant && !$variant->stale && (array) $variant->hashes == $result['hashes'] ) {
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

                return self::addVersion( self::insertVariant( $source, $data, $hashes, $stale, $editor ), $data, $result['aux'], $editor, $user );
            }

            /** @var Page $page */
            $page = Page::withTrashed()->language( $lang )->with( 'latest' )->lockForUpdate()->findOrFail( $source->id );

            // merges with drafts saved by editors in the meantime
            [$data, $aux, $diffs] = Merge::page( $page, $result['data'], $result['aux'], $variant->latest_id, $user );

            $page->draft( [
                'data' => $data,
                'editor' => $editor,
                'lang' => $lang,
                'aux' => $aux,
            ], self::refs( $aux, $user ), $diffs );

            self::syncState( $page, $hashes, $stale );
            $page->announce( 'saved', $editor );

            return $page;
        } );

        // new variants have only one version
        $variant
            ? self::pruneVersions( Page::class, [$page->getVersionKey()] )
            : Scout::sources( [(string) $page->id] );

        self::watch( $variant ? 'translated' : 'added', $page, [$lang], Utils::editor( $user ), $result['translated'] );

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
     * @return Collection<int, Page> Changed page variants
     */
    public static function variants( string $action, array $ids, string $lang, ?Authenticatable $user = null ) : Collection
    {
        $method = match( $action ) {
            'drop' => 'dropVariants',
            'restore' => 'restoreVariants',
            'purge' => 'purgeVariants',
            default => throw new \InvalidArgumentException( sprintf( 'Invalid variant action "%1$s"', $action ) ),
        };

        $ids = array_values( array_unique( $ids ) );
        Page::checkBulk( count( $ids ) );

        return self::$method( $ids, $lang, $user );
    }


    /**
     * Moves the language variants of the pages to the trash.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @return Collection<int, Page> Trashed page variants
     */
    protected static function dropVariants( array $ids, string $lang, ?Authenticatable $user = null, bool $strict = false ) : Collection
    {
        $editor = Utils::editor( $user );

        $dropped = Utils::transaction( function() use ( $ids, $lang, $editor, $strict ) {

            $pages = self::deletableVariants( $ids, $lang, false, $strict );
            $time = ( new PageVariant() )->freshTimestamp();

            foreach( $pages->pluck( 'variant_id' )->chunk( 500 ) as $chunk ) {
                PageVariant::whereKey( $chunk->all() )->update( ['deleted_at' => $time, 'editor' => $editor] );
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        // the complete pages are loaded after commit to keep the locks short
        $pages = self::pages( $dropped ? Page::withTrashed()->language( $lang, true )->whereKey( $dropped )->get() : [] );

        if( $pages->isNotEmpty() && Scout::usesExternalSearch() ) {
            config( 'scout.soft_delete' )
                ? Scout::index( Page::class, $pages->pluck( 'id' )->map( strval( ... ) )->all(), $pages )
                : Scout::unindex( Page::class, $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );
        }

        Scout::sources( $pages->pluck( 'id' )->map( strval( ... ) )->all() );
        self::invalidatePages( $pages );
        $pages->each( fn( Page $page ) => self::watch( 'dropped', $page, [$lang], $editor ) );

        return $pages;
    }


    /**
     * Deletes the language variants of the pages including their versions.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @return Collection<int, Page> Purged page variants
     */
    protected static function purgeVariants( array $ids, string $lang, ?Authenticatable $user = null, bool $strict = false ) : Collection
    {
        $editor = Utils::editor( $user );

        // the complete pages are required after the variants are deleted, but not locked
        $all = self::pages( Page::withTrashed()->language( $lang, true )->whereKey( $ids )->get() )->keyBy( 'variant_id' );

        $pages = Utils::transaction( function() use ( $ids, $lang, $strict, $all ) {

            $pages = self::deletableVariants( $ids, $lang, true, $strict );

            // variants added after the pages were fetched need their domain and path for cache invalidation
            $missing = $pages->reject( fn( Page $page ) => $all->has( $page->variant_id ) )->pluck( 'id' )->all();
            $added = $missing
                ? self::pages( Page::withTrashed()->language( $lang, true )->whereKey( $missing )->get() )->keyBy( 'variant_id' )
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

        // after commit to keep the index if the transaction fails
        if( $pages->isNotEmpty() ) {
            Scout::unindex( Page::class, $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );
            Scout::sources( $pages->pluck( 'id' )->map( strval( ... ) )->all() );
        }

        self::invalidatePages( $pages->filter( fn( Page $page ) => $page->getAttribute( 'variant_deleted_at' ) === null ) );
        $pages->each( fn( Page $page ) => self::watch( 'purged', $page, [$lang], $editor ) );

        return $pages;
    }


    /**
     * Restores the trashed language variants of the pages.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no trashed variant in the language
     * @return Collection<int, Page> Restored page variants
     */
    protected static function restoreVariants( array $ids, string $lang, ?Authenticatable $user = null, bool $strict = false ) : Collection
    {
        $editor = Utils::editor( $user );

        // hashed before locking the variants to keep the transaction short
        $hashes = self::sourceHashes( $ids );

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

        $pages = self::pages( $restored ? Page::withTrashed()->language( $lang )->whereKey( $restored )->get() : [] );

        if( $pages->isNotEmpty() ) {
            Scout::index( Page::class, $restored, $pages );
            Scout::sources( $restored );
        }

        self::invalidatePages( $pages );
        $pages->each( fn( Page $page ) => self::watch( 'restored', $page, [$lang], $editor ) );

        return $pages;
    }


    /**
     * Returns the hashes of the published source variants of the pages.
     *
     * @param array<string> $ids Page UUIDs
     * @return array<string, array<string, string>> Hashes by page ID
     */
    protected static function sourceHashes( array $ids ) : array
    {
        $hashes = [];

        foreach( array_chunk( $ids, 500 ) as $chunk )
        {
            // only the columns used for the hashes of the published source variants
            $sources = Page::withTrashed()->whereKey( $chunk )->get( ['id', ...Hashes::PAGE_FIELDS, 'content', 'meta', 'config'] );

            foreach( self::pages( $sources ) as $source ) {
                $hashes[(string) $source->id] = Hashes::published( $source );
            }
        }

        return $hashes;
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
        $fields = array_intersect_key( $data, array_flip( ['lang', ...PageVariant::FIELDS] ) );

        $variant = new PageVariant();
        $variant->forceFill( array_filter( $fields, fn( $v ) => $v !== null ) + [
            'page_id' => $source->id,
            'hashes' => $hashes,
            'stale' => $stale,
            'editor' => $editor,
        ] )->save();

        return Page::variant( (string) $variant->id )->firstOrFail();
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
        $source = Page::withTrashed()->with( 'latest' )->findOrFail( $id );

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
     * Stores the hashes and the stale flag of the page variant.
     *
     * @param Page $page Page with the variant
     * @param array<string, string> $hashes Hashes by key
     * @param bool $stale If the variant needs an update
     * @return Page Same page with the new hashes and stale flag
     */
    protected static function syncState( Page $page, array $hashes, bool $stale ) : Page
    {
        PageVariant::withTrashed()->whereKey( $page->variant_id )->update( ['hashes' => json_encode( (object) $hashes ), 'stale' => $stale] );
        return $page->forceFill( ['hashes' => $hashes, 'stale' => $stale] )->syncOriginal();
    }


    /**
     * Returns a path that isn't used by another page variant in the domain.
     *
     * @param string $domain Domain name
     * @param string $path Preferred path
     * @param string $lang Language code appended on collisions
     * @param array<string, array<string, bool>> $known Known paths by domain, TRUE if used, updated with the returned path
     * @return string Unique path
     */
    protected static function uniquePath( string $domain, string $path, string $lang, array &$known = [] ) : string
    {
        $base = $path === '' ? $lang : $path . '-' . $lang;
        $candidate = $path;
        $num = 1;

        while( $known[$domain][$candidate] ?? PageVariant::withTrashed()->where( 'domain', $domain )->where( 'path', $candidate )->exists() ) {
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
     * @param bool $ai Whether AI was used
     */
    protected static function watch( string $action, Page $page, array $langs, string $editor, bool $ai = false ) : void
    {
        Watch::dispatch( Translation::class, fn() => new Translation(
            $action, (string) $page->id, $langs, $editor, $ai, (string) $page->tenant_id
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

                if( $variant->lang !== $source->source
                    && Hashes::stale( $hashes[$variant->page_id] ??= Hashes::published( $source ), (array) $variant->hashes )
                ) {
                    $ids[] = $variant->id;
                }
            }

            if( !empty( $ids ) ) {
                PageVariant::whereIn( 'id', $ids )->toBase()->update( ['stale' => true] );
            }
        }
    }
}
