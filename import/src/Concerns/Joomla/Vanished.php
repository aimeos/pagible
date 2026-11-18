<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;


/**
 * Disables the pages of previous imports whose Joomla articles, categories or menu items aren't imported any more.
 *
 * The pages don't store the IDs of their Joomla records, they are found by their URL paths like by savePage(). Pages of
 * articles which were deleted, trashed, unpublished, moved to a category which isn't imported or aren't selected
 * any more get no URL path of the current import. Their pages and the variants of their translations are disabled by a new
 * version, so they are enabled again by the next import which renders their articles. Only enabled content pages of the
 * import with the same editor, language and domain are disabled, i.e. not the root page, link pages of menu items and
 * redirects, pages and variants changed in Pagible since or pages of other domains and languages.
 *
 * Requires the Shared trait used by the same class for the objects of the run.
 */
trait Vanished
{
    /**
     * Disables the pages of previous imports whose Joomla records aren't imported any more and their variants.
     *
     * Pages saved by this import are never disabled. Pages using the URL path of an imported article, a visible category
     * or a menu item of the imported menu aren't disabled either, e.g. if their import failed or was skipped this time.
     * The variants, their pages and published versions are fetched for a chunk of pages at once.
     */
    protected function disableVanished(): void
    {
        $count = 0;

        foreach( $this->vanishedPages()->chunk( DB::IN_SIZE ) as $chunk )
        {
            $variants = $this->vanishedVariants( $chunk->pluck( 'page_id' )->map( strval( ... ) )->all() );
            $all = $variants->flatten( 1 );
            $views = $this->variantViews( $all );
            $published = $this->publishedVersions( $all );

            foreach( $chunk as $page )
            {
                $path = '/' . ltrim( (string) $page->path, '/' );
                $ids = $variants->get( (string) $page->page_id, Collection::make() )->pluck( 'id' )->map( strval( ... ) );

                // the variants are disabled together, so a failure leaves the page and its variants enabled
                $count += (int) $this->transaction( "page {$path}", function() use ( $ids, $views, $published ) {
                    $disabled = false;

                    // variants without published version or page can't be disabled, so their pages aren't counted
                    foreach( $ids as $id )
                    {
                        if( isset( $views[$id], $published[$id] ) )
                        {
                            $this->disableVariant( $views[$id], $published[$id] );
                            $disabled = true;
                        }
                    }

                    return $disabled;
                } );
            }
        }

        if( $count ) {
            $this->warn( "Disabled {$count} pages of previous imports whose Joomla articles, categories or menu items aren't imported any more." );
        }
    }


    /**
     * Lists the pages and variants of previous imports which an import would disable, see disableVanished().
     *
     * The dry run saves no pages, so only the URL paths of the imported records decide.
     */
    protected function printVanished(): void
    {
        foreach( $this->vanishedPages()->chunk( DB::IN_SIZE ) as $chunk )
        {
            $variants = $this->vanishedVariants( $chunk->pluck( 'page_id' )->map( strval( ... ) )->all() );

            foreach( $chunk as $page )
            {
                foreach( $variants->get( (string) $page->page_id, Collection::make() ) as $variant ) {
                    $this->line( '  Disable /' . ltrim( (string) $variant->path, '/' ) . " [{$variant->lang}] ({$this->vanishedReason()})" );
                }
            }
        }
    }


    /**
     * Returns the enabled content pages of previous imports which aren't used by the current import.
     *
     * Trashed pages and variants aren't returned, pages with a variant in the import language changed in Pagible since
     * have another editor. The pages saved by this import are skipped while reading because the lists can be too long for
     * the query, so only the few vanished ones of the many pages of the import are hydrated and kept.
     *
     * @return Collection<int, PageVariant> Variants of the pages in the import language with page ID, path and language
     */
    protected function vanishedPages(): Collection
    {
        $known = $this->importedPaths();

        return PageVariant::whereHas( 'page', fn( $query ) => $query->withoutTrashed() )
            ->where( 'domain', $this->domain )->where( 'lang', $this->lang )->where( 'editor', $this->editor )
            ->where( 'to', '' )->where( 'tag', '!=', Pages::TAG_ROOT )->where( 'status', '!=', Pages::STATUS_DISABLED )
            ->orderBy( 'path' )->select( ['id', 'page_id', 'path', 'lang'] )->cursor()
            ->filter( fn( $variant ) => !isset( $this->usedPages[(string) $variant->page_id] ) && !isset( $known[trim( (string) $variant->path, '/' )] ) )
            ->values()->collect();
    }


    /**
     * Returns the enabled variants of the pages saved by the import, the one in the import language first.
     *
     * Variants changed in Pagible since have another editor and aren't returned, like they aren't overwritten by saveTranslation().
     *
     * @param array<int, string> $pageIds IDs of the vanished pages
     * @return Collection<string, Collection<int, PageVariant>> Variants with ID, page ID, language and path by page ID
     */
    protected function vanishedVariants( array $pageIds ): Collection
    {
        if( $pageIds === [] ) {
            return Collection::make();
        }

        return PageVariant::whereIn( 'page_id', $pageIds )->where( 'editor', $this->editor )->where( 'status', '!=', Pages::STATUS_DISABLED )
            ->select( ['id', 'page_id', 'path', 'lang'] )->get()
            ->sortBy( fn( $variant ) => $variant->lang === $this->lang ? '' : (string) $variant->lang )
            ->groupBy( fn( $variant ) => (string) $variant->page_id )
            ->map( fn( $variants ) => $variants->values() )
            ->toBase();
    }


    /**
     * Returns the URL paths of the pages of the imported articles, the visible categories and the menu items of the imported menu.
     *
     * Contains the paths of all articles selected for the import, also of the ones kept by the --since option whose rows
     * aren't fetched. The paths of categories without imported articles and of restricted menu items are included too, so
     * pages of Joomla categories and menu items which still exist aren't disabled.
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function importedPaths(): array
    {
        $urls = $this->run->urls;
        $categoryItems = $this->wiring->categoryItems;
        $paths = [];

        foreach( array_keys( $this->run->articles->categoryIds ) as $id ) {
            $paths[] = $urls->articleUrl( $id );
        }

        foreach( $this->wiring->site->categories->keys() as $catId )
        {
            if( $category = $categoryItems->category( (int) $catId ) ) {
                $paths[] = $urls->categoryPath( $category );
            }

            $paths[] = $urls->categoryUrl( (int) $catId );
        }

        foreach( $this->wiring->menu->tree() as $node ) {
            $paths[] = $urls->menuPath( $node->item );
        }

        $known = [];

        foreach( $paths as $path )
        {
            // absolute URLs are the ones of variants in languages with own domains
            if( $path !== null && !str_contains( $path, '://' ) ) {
                $known[trim( $path, '/' )] = true;
            }
        }

        return $known;
    }


    /**
     * Returns the pages of the variants in their languages at once, one query per language.
     *
     * @param Collection<int, PageVariant> $variants Variants with ID, page ID and language
     * @return array<string, PageView> Pages in the languages of the variants by variant ID
     */
    protected function variantViews( Collection $variants ): array
    {
        $views = [];

        foreach( $variants->groupBy( fn( $variant ) => (string) $variant->lang ) as $lang => $list )
        {
            $pageIds = $list->pluck( 'page_id' )->map( strval( ... ) )->unique()->values()->all();

            foreach( PageView::language( (string) $lang )->whereKey( $pageIds )->get() as $view ) {
                $views[(string) $view->variant_id] = $view;
            }
        }

        return $views;
    }


    /**
     * Returns the latest published versions of the variants with the IDs of their files and shared elements at once.
     *
     * The versions are looked up by their IDs first, so only the latest one of each variant is fetched with its data.
     *
     * @param Collection<int, PageVariant> $variants Variants with ID
     * @return array<string, array{version: Version, files: array<int, string>, elements: array<int, string>}> Versions and references by variant ID
     */
    protected function publishedVersions( Collection $variants ): array
    {
        if( ( $variantIds = $variants->pluck( 'id' )->map( strval( ... ) )->unique()->values()->all() ) === [] ) {
            return [];
        }

        // the same order as PageView::versions(), the first version of each variant is its latest published one
        $ids = Version::where( 'versionable_type', ( new PageView() )->getMorphClass() )->whereIn( 'versionable_id', $variantIds )
            ->where( 'published', true )->orderByDesc( 'created_at' )->orderByDesc( 'id' )->toBase()
            ->get( ['id', 'versionable_id'] )->unique( 'versionable_id' )->pluck( 'id' )->map( strval( ... ) )->values()->all();

        if( $ids === [] ) {
            return [];
        }

        $files = $this->versionRefs( ( new Version() )->files(), $ids );
        $elements = $this->versionRefs( ( new Version() )->elements(), $ids );
        $list = [];

        foreach( Version::whereIn( 'id', $ids )->get() as $version )
        {
            $id = (string) $version->id;
            $list[(string) $version->versionable_id] = ['version' => $version, 'files' => $files[$id] ?? [], 'elements' => $elements[$id] ?? []];
        }

        return $list;
    }


    /**
     * Returns the IDs of the files or shared elements referenced by the versions, also of trashed ones like allRelatedIds().
     *
     * @param BelongsToMany<covariant \Illuminate\Database\Eloquent\Model, Version> $relation Files or elements relation of a version
     * @param array<int, string> $ids Version IDs
     * @return array<string, array<int, string>> Referenced IDs by version ID
     */
    protected function versionRefs( BelongsToMany $relation, array $ids ): array
    {
        $foreign = $relation->getForeignPivotKeyName();
        $related = $relation->getRelatedPivotKeyName();
        $refs = [];

        foreach( $relation->newPivotStatement()->whereIn( $foreign, $ids )->get( [$foreign, $related] ) as $row ) {
            $refs[(string) $row->{$foreign}][] = (string) $row->{$related};
        }

        return $refs;
    }


    /**
     * Publishes a version of the page in the language of the variant which disables it, a later import of its Joomla record enables it again.
     *
     * @param PageView $page Page in the language of the variant
     * @param array{version: Version, files: array<int, string>, elements: array<int, string>} $published Latest published version
     *  of the variant and the IDs of its files and shared elements, see publishedVersions()
     */
    protected function disableVariant( PageView $page, array $published ): void
    {
        $version = $published['version'];
        $data = array_replace( array_diff_key( (array) $version->data, ['scheduled' => null] ), ['status' => Pages::STATUS_DISABLED] );
        $aux = (array) json_decode( (string) json_encode( $version->aux ), true );

        Pages::publish( $page, $data, $aux, $published['files'], $published['elements'], (string) $page->lang, $this->editor );

        $this->notice( 'Disabled', '/' . ltrim( (string) $page->path, '/' ) . " [{$page->lang}]", $this->vanishedReason() );
    }


    /**
     * Returns why the pages of previous imports are disabled.
     */
    protected function vanishedReason(): string
    {
        return "its Joomla article, category or menu item isn't imported any more, e.g. it was deleted, trashed or unpublished";
    }
}
