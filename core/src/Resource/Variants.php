<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\ContentType;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exceptions\ConflictException;
use Aimeos\Cms\Exceptions\InvalidInputException;
use Aimeos\Cms\Translation\Hashes;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource\Support\Items;
use Aimeos\Cms\Resource\Support\PageEdits;
use Aimeos\Cms\Resource\Support\PageRows;
use Aimeos\Cms\Resource\Support\Paths;
use Aimeos\Cms\Resource\Support\Refs;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Translation\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Aimeos\Cms\VariantAction;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;


/**
 * Operations on the language variants of pages.
 *
 * Each method checks the permission of its action for the passed user, e.g. "page:save" for
 * setSource(), and throws a PermissionException if it's missing. Without user (NULL), the call is
 * anonymous and throws too unless it runs inside Permission::system(), e.g. in commands or jobs.
 *
 * Use it via the Aimeos\Cms\Facades\Variants facade, which resolves the instance from the container.
 * Applications can replace it by a subclass overriding the public or protected methods, e.g. with
 * `$this->app->singleton( \Aimeos\Cms\Resource\Variants::class, MyVariants::class )` in their service provider.
 *
 * @phpstan-import-type PageInput from Pages
 */
class Variants
{
    /**
     * Adds a language variant to a page as untranslated copy of the latest source draft.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the new variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Page with the new variant
     * @throws InvalidInputException|ConflictException If the language is invalid or the page already has a variant in that language
     */
    public function add( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        Permission::check( 'page:add', $user );
        return $this->translateVariant( $id, $lang, $user, add: true );
    }


    /**
     * Updates a language variant of an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param PageInput $input Changed fields (merged with latest version), "lang" changes the language of the variant
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return PageView Page with the saved variant
     * @throws InvalidInputException|ConflictException If the input is invalid or the new language already exists
     * @throws ModelNotFoundException If the page has no variant in that language
     */
    public function save( string $id, string $lang, array $input, ?Authenticatable $user = null, ?string $latestId = null ) : PageView
    {
        return PageEdits::save( $id, $input, $user, $latestId, $lang );
    }


    /**
     * Saves the content of an old version of a language variant as new version.
     *
     * The old content may not match the current source variant anymore, so translations are
     * marked as outdated. Reverting the source variant works like save().
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param PageInput $input Content of the old version
     * @param Authenticatable|null $user Authenticated user for permission-based validation and editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return PageView Page with the saved variant
     * @throws InvalidInputException|ConflictException If the input is invalid or the new language already exists
     * @throws ModelNotFoundException If the page has no variant in that language
     */
    public function revert( string $id, string $lang, array $input, ?Authenticatable $user = null, ?string $latestId = null ) : PageView
    {
        return PageEdits::save( $id, $input, $user, $latestId, $lang, restore: true );
    }


    /**
     * Applies the same partial input to the language variants of multiple pages, optionally including all sub-pages.
     *
     * Pages without a variant in the language are skipped.
     *
     * @param array<string> $ids Page IDs to update
     * @param string $lang Language code of the variants
     * @param PageInput $input Partial page input applied to every variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $descendants TRUE to also update all sub-pages of the given pages
     * @return BulkResult
     */
    public function bulk( array $ids, string $lang, array $input, ?Authenticatable $user = null, bool $descendants = false ) : BulkResult
    {
        return PageEdits::bulk( $ids, $input, $user, $descendants, $lang );
    }


    /**
     * Publishes or schedules the latest version of a language variant of the page.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return PageView Page with the published variant
     * @throws ModelNotFoundException If the page has no variant in that language
     */
    public function publish( string $id, string $lang, ?Authenticatable $user = null, ?string $at = null ) : PageView
    {
        return Items::one( PageView::class, $this->publishMany( [$id], $lang, $user, $at ), $id );
    }


    /**
     * Publishes or schedules the latest versions of the language variants of the pages.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return Collection<int, PageView> Pages with the published variants
     */
    public function publishMany( array $ids, string $lang, ?Authenticatable $user = null, ?string $at = null ) : Collection
    {
        return Publication::publish( PageView::class, $ids, $user, $at, $lang );
    }


    /**
     * Moves a language variant of a page to the trash.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Trashed page variant
     * @throws ConflictException If the variant is the source variant of the page
     */
    public function drop( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return $this->trashVariants( [$id], $lang, $user, strict: true )->firstOrFail();
    }


    /**
     * Restores a trashed language variant of a page.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Restored page variant
     */
    public function restore( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return $this->untrashVariants( [$id], $lang, $user, strict: true )->firstOrFail();
    }


    /**
     * Permanently deletes a language variant of a page.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Deleted page variant
     * @throws ConflictException If the variant is the source variant of the page
     */
    public function purge( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return $this->deleteVariants( [$id], $lang, $user, strict: true )->firstOrFail();
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
     * @throws ModelNotFoundException If the page has no variant in that language or it's in the trash
     */
    public function setSource( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        Permission::check( 'page:save', $user );

        $editor = Utils::editor( $user );

        [$page, $old] = DB::transaction( function() use ( $id, $lang ) {

            // trashed variants can't be the source, the language fallback counts on the source variant of each page
            $page = PageView::withTrashed()->language( $lang )->lockForUpdate()->find( $id );

            if( !$page ) {
                throw ( new ModelNotFoundException() )->setModel( PageView::class, [$id] );
            }

            if( ( $old = (string) $page->source ) === $lang ) {
                return [$page, null];
            }

            Sync::rebase( $page );

            $page->page()->toBase()->update( ['source' => $lang] );

            Sync::state( $page, [], stale: false )->forceFill( ['source' => $lang] )->syncOriginal();

            return [$page, $old];
        } );

        if( $old !== null )
        {
            // the former and the new source variants are listed for the languages without variant
            Scout::variants( [(string) $page->id], [$old, $lang] );

            $this->watch( VariantAction::Source, collect( [$page] ), $lang, $editor );
        }

        return $page;
    }


    /**
     * Marks the page variant as up to date with the published source variant without changing its content.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return PageView Page with the variant
     * @throws ConflictException If the variant is the source variant
     */
    public function ignore( string $id, string $lang, ?Authenticatable $user = null ) : PageView
    {
        return Items::one( PageView::class, $this->ignoreMany( [$id], $lang, $user ), $id );
    }


    /**
     * Marks the page variants as up to date with the published source variants without changing their content.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Pages with the variants
     * @throws ConflictException If a variant is the source variant
     */
    public function ignoreMany( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'page:save', $user );

        $editor = Utils::editor( $user );
        $ids = PageView::bulkIds( $ids );

        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        DB::transaction( function() use ( $ids, $lang, $hashes ) {

            $pages = $this->lockVariants( $ids, $lang, trashed: false, strict: true,
                error: 'The source language can\'t be marked as up to date' );

            foreach( $pages as $page ) {
                Sync::state( $page, $hashes[(string) $page->id] ?? throw ( new ModelNotFoundException() )->setModel( PageView::class, (string) $page->id ), stale: false );
            }
        } );

        $pages = $this->variantPages( $ids, $lang, $this->variantColumns( $fields ) );

        $this->watch( VariantAction::Ignored, $pages, $lang, $editor );

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
     * @throws InvalidInputException|ConflictException If the language is the source language or its variant is in the trash
     */
    public function translate( string $id, string $lang, ?Authenticatable $user = null, ?callable $translate = null ) : PageView
    {
        Permission::check( 'page:save', $user );
        return $this->translateVariant( $id, $lang, $user, $translate );
    }


    /**
     * Adds or updates the language variant of a page from its source variant.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param (callable(array<int, string>, string, ?string, string): array<int, string>)|null $translate Translate callback
     * @param bool $add TRUE if the variant must not exist yet
     * @return PageView Page with the variant and its new draft
     * @throws InvalidInputException|ConflictException If the language is the source language, its variant is in the trash or exists when adding
     */
    protected function translateVariant( string $id, string $lang, ?Authenticatable $user = null,
        ?callable $translate = null, bool $add = false ) : PageView
    {
        // existing variants in unconfigured languages (e.g. imported ones) can still be translated
        if( !Utils::isLocale( $lang ) && !PageVariant::where( 'page_id', $id )->where( 'lang', $lang )->exists() ) {
            throw new InvalidInputException( sprintf( 'Invalid language code "%1$s"', $lang ) );
        }

        [$source, $variant] = $this->translatable( $id, $lang );
        Permission::check( 'page:add', $user, !$variant );

        // trashed variants are already rejected by translatable()
        if( $add && $variant ) {
            throw new ConflictException( sprintf( 'Language "%1$s" already exists', $lang ) );
        }

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
                    throw new ConflictException( sprintf( 'Language "%1$s" already exists', $lang ) );
                }

                $path = $result['slug'] !== '' ? $result['slug'] : (string) ( $result['data']['path'] ?? '' );
                [$domain, $path] = $this->variantPath( $source, $lang, $path );
                $data = array_replace( $result['data'], ['domain' => $domain, 'path' => $path] );

                $refs = Refs::page( $result['aux'], $user );
                return PageRows::addVersion( PageRows::insert( $source, $data, $hashes, $stale, $editor ), $data, $result['aux'], $editor, $refs );
            }

            $page = PageView::withTrashed()->language( $lang )->with( 'latest' )->lockForUpdate()->findOrFail( $source->id );

            // untranslated copies get the translated slug in their path
            if( $result['slug'] !== '' ) {
                $result['data']['path'] = $this->variantPath( $page, $lang, $result['slug'], (string) ( $result['data']['path'] ?? '' ) )[1];
            }

            // merges with drafts saved by editors in the meantime
            [$data, $aux, $diffs] = Merge::page( $page, $result['data'], $result['aux'], $variant->latest_id, $user );

            PageRows::addVersion( $page, $data, $aux, $editor, Refs::page( $aux, $user ), $diffs, $lang );

            // the saved variant is reindexed after the commit, which includes the new stale flag
            Sync::state( $page, $hashes, $stale, index: false );
            $page->announce( ContentAction::Saved, $editor );

            return $page;
        } );

        // new variants have only one version
        $variant
            ? PruneVersions::dispatchChunked( PageView::class, [$page->getVersionKey()] )
            : Scout::variants( [(string) $page->id], [$lang] );

        $this->watch( $variant ? VariantAction::Translated : ContentAction::Added, collect( [$page] ), $lang, Utils::editor( $user ) );

        return $page;
    }


    /**
     * Moves the language variants of several pages to the trash.
     *
     * Pages without a matching variant and source variants, which can't be deleted, are skipped.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Trashed page variants
     */
    public function dropMany( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $ids = PageView::bulkIds( $ids );

        return $this->trashVariants( $ids, $lang, $user, strict: false, fields: $fields );
    }


    /**
     * Restores the trashed language variants of several pages.
     *
     * Pages without a matching variant and source variants, which can't be deleted, are skipped.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Restored page variants
     */
    public function restoreMany( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $ids = PageView::bulkIds( $ids );

        return $this->untrashVariants( $ids, $lang, $user, strict: false, fields: $fields );
    }


    /**
     * Permanently deletes the language variants of several pages.
     *
     * Pages without a matching variant and source variants, which can't be deleted, are skipped.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Deleted page variants
     */
    public function purgeMany( array $ids, string $lang, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        $ids = PageView::bulkIds( $ids );

        return $this->deleteVariants( $ids, $lang, $user, strict: false, fields: $fields );
    }


    /**
     * Moves the language variants of the pages to the trash.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Trashed page variants
     */
    protected function trashVariants( array $ids, string $lang, ?Authenticatable $user, bool $strict, array $fields = [] ) : Collection
    {
        Permission::check( 'page:drop', $user );
        $editor = Utils::editor( $user );

        $dropped = DB::transaction( function() use ( $ids, $lang, $editor, $strict ) {

            $pages = $this->lockVariants( $ids, $lang, trashed: false, strict: $strict );
            $time = ( new PageVariant() )->freshTimestamp();

            foreach( $pages->pluck( 'variant_id' )->chunk( DB::IN_SIZE ) as $chunk ) {
                PageVariant::whereKey( $chunk->all() )->update( ['deleted_at' => $time, 'editor' => $editor] );
            }

            return $pages->pluck( 'id' )->map( strval( ... ) )->all();
        } );

        // the complete pages are loaded after commit to keep the locks short
        $pages = $this->variantPages( $dropped, $lang, $this->variantColumns( $fields ), trashed: true );
        $keys = $pages->pluck( 'variant_id' )->map( strval( ... ) )->all();

        // the CMS search ignores trashed variants, external search engines keep them if soft deletes are enabled
        if( Scout::usesExternalSearch() )
        {
            if( Scout::keepsTrashed() ) {
                Scout::index( PageView::class, $keys, keys: true );
            } else {
                Scout::unindex( PageView::class, $keys );
            }
        }

        // the languages listed for the other variants change too
        Scout::variants( $pages->pluck( 'id' )->map( strval( ... ) )->all(), [$lang] );
        Invalidation::pages( $pages );
        $this->watch( LifecycleAction::Dropped, $pages, $lang, $editor );

        return $pages;
    }


    /**
     * Deletes the language variants of the pages including their versions.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no variant in the language or it's the source variant
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Purged page variants
     */
    protected function deleteVariants( array $ids, string $lang, ?Authenticatable $user, bool $strict, array $fields = [] ) : Collection
    {
        Permission::check( 'page:purge', $user );
        $editor = Utils::editor( $user );
        $columns = $this->variantColumns( $fields );

        // the complete pages are required after the variants are deleted, but not locked
        $all = $this->variantPages( $ids, $lang, $columns, trashed: true )->keyBy( 'variant_id' );

        $pages = DB::transaction( function() use ( $ids, $lang, $strict, $all, $columns ) {

            $pages = $this->lockVariants( $ids, $lang, trashed: true, strict: $strict );

            // variants added after the pages were fetched need their domain and path for cache invalidation
            $missing = $pages->reject( fn( PageView $page ) => $all->has( $page->variant_id ) )->pluck( 'id' )->all();
            $added = $this->variantPages( $missing, $lang, $columns, trashed: true )->keyBy( 'variant_id' );

            $pages = $pages->map( fn( PageView $page ) : PageView => $all->get( $page->variant_id ) ?? $added->get( $page->variant_id ) ?? $page );

            PageVariant::purge( $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );

            return $pages->each( fn( PageView $page ) => $page->exists = false );
        } );

        // after commit to keep the index if the transaction fails
        Scout::unindex( PageView::class, $pages->pluck( 'variant_id' )->map( strval( ... ) )->all() );
        Scout::variants( $pages->pluck( 'id' )->map( strval( ... ) )->all(), [$lang] );

        // trashed variants were invalidated when they were dropped
        Invalidation::pages( $pages->filter( fn( PageView $page ) => $page->getAttribute( 'variant_deleted_at' ) === null ) );
        $this->watch( LifecycleAction::Purged, $pages, $lang, $editor );

        return $pages;
    }


    /**
     * Restores the trashed language variants of the pages.
     *
     * @param array<string> $ids Page UUIDs
     * @param string $lang Language code of the variants
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param bool $strict TRUE to throw if a page has no trashed variant in the language
     * @param array<string> $fields Requested response fields to load only the required columns, all columns if empty
     * @return Collection<int, PageView> Restored page variants
     */
    protected function untrashVariants( array $ids, string $lang, ?Authenticatable $user, bool $strict, array $fields = [] ) : Collection
    {
        Permission::check( 'page:keep', $user );
        $editor = Utils::editor( $user );

        // hashed before locking the variants to keep the transaction short
        $hashes = Sync::sources( $ids );

        $restored = DB::transaction( function() use ( $ids, $lang, $editor, $strict, $hashes ) {

            $pages = PageRows::views( PageView::withTrashed()->language( $lang, trashed: true )->whereNotNull( 'variant_deleted_at' )
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

        $pages = $this->variantPages( $restored, $lang, $this->variantColumns( $fields ) );

        // after commit to keep the index if the transaction fails
        Scout::index( PageView::class, $pages->pluck( 'variant_id' )->map( strval( ... ) )->all(), keys: true );
        Scout::variants( $pages->pluck( 'id' )->map( strval( ... ) )->all(), [$lang] );
        Invalidation::pages( $pages );
        $this->watch( LifecycleAction::Restored, $pages, $lang, $editor );

        return $pages;
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
    protected function variantPages( array $ids, string $lang, array $columns, bool $trashed = false ) : Collection
    {
        return PageRows::views( $ids ? PageView::withTrashed()->language( $lang, $trashed )->whereKey( $ids )->get( $columns ) : [] );
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
     * @throws ConflictException If strict and the variant is the source variant of a page
     */
    protected function lockVariants( array $ids, string $lang, bool $trashed, bool $strict,
        string $error = 'The source language can not be deleted, change the source language first' ) : Collection
    {
        // only the columns required to check the variants to keep the locked rows small
        $pages = PageRows::views( PageView::withTrashed()->language( $lang, $trashed )->whereKey( $ids )->lockForUpdate()
            ->get( ['id', 'variant_id', 'lang', 'source', 'stale'] ) );

        if( $strict && $pages->count() !== count( $ids ) ) {
            throw ( new ModelNotFoundException() )->setModel( PageView::class, $ids );
        }

        if( $strict && $pages->contains( fn( PageView $page ) => $page->isSourceVariant() ) ) {
            throw new ConflictException( $error );
        }

        return $pages->reject( fn( PageView $page ) => $page->isSourceVariant() )->values();
    }


    /**
     * Returns the source variant and the variant of the language to translate.
     *
     * @param string $id Page UUID
     * @param string $lang Language code of the variant
     * @return array{0: PageView, 1: PageView|null} Page with the source variant and with the variant if it exists
     * @throws InvalidInputException|ConflictException If the language is the source language or its variant is in the trash
     */
    protected function translatable( string $id, string $lang ) : array
    {
        $source = PageView::with( 'latest' )->findOrFail( $id );

        if( $source->lang === $lang ) {
            throw new InvalidInputException( 'The source language can\'t be translated' );
        }

        $variant = PageView::withTrashed()->language( $lang, trashed: true )->with( 'latest' )->find( $id );

        if( $variant && $variant->getAttribute( 'variant_deleted_at' ) !== null ) {
            throw new ConflictException( sprintf( 'Language "%1$s" is in the trash, restore it instead', $lang ) );
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
     * @param string|null $current Current path of an existing variant which can be reused
     * @return array{0: string, 1: string} Domain and path
     */
    protected function variantPath( PageView $page, string $lang, string $path, ?string $current = null ) : array
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

        if( $current !== null ) {
            unset( $known[$domain][$current] );
        }

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
    protected function variantColumns( array $fields ) : array
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
     * @param ContentAction|LifecycleAction|VariantAction $action Action of the variant change
     * @param Collection<int, PageView> $pages Affected pages with the variant of the language
     * @param string $lang Language code of the affected variants
     * @param string $editor Name of the editing user
     */
    protected function watch( ContentAction|LifecycleAction|VariantAction $action, Collection $pages, string $lang, string $editor ) : void
    {
        $ids = array_values( $pages->pluck( 'id' )->map( strval( ... ) )->all() );
        $latest = $pages->mapWithKeys( fn( PageView $page ) => [(string) $page->id => (string) $page->latest_id] )->all();

        PageView::announceBulk( ContentType::Page, $ids, $latest, editor: $editor, action: $action, langs: array_fill_keys( $ids, $lang ) );
    }
}
