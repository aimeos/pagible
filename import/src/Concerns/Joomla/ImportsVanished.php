<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
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
 * Requires the Imports trait used by the same class for the objects of the run.
 */
trait ImportsVanished
{
    /**
     * Disables the pages of previous imports whose Joomla records aren't imported any more and their variants.
     *
     * Pages saved by this import are never disabled. Pages using the URL path of an imported article, a visible category
     * or a menu item of the imported menu aren't disabled either, e.g. if their import failed or was skipped this time.
     */
    protected function disableVanished(): void
    {
        $count = 0;

        foreach( $this->vanishedPages() as $page )
        {
            $path = '/' . ltrim( (string) $page->path, '/' );
            // the variants are disabled together, so a failure leaves the page and its variants enabled
            $count += (int) $this->transaction( "page {$path}", function() use ( $page ) {
                foreach( $this->vanishedVariants( $page ) as $variant ) {
                    $this->disableVariant( $variant );
                }
                return true;
            } );
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
        foreach( $this->vanishedPages() as $page )
        {
            foreach( $this->vanishedVariants( $page ) as $variant ) {
                $this->line( '  Disable /' . ltrim( (string) $variant->path, '/' ) . " [{$variant->lang}] ({$this->vanishedReason()})" );
            }
        }
    }


    /**
     * Returns the enabled content pages of previous imports which aren't used by the current import.
     *
     * Trashed pages and variants aren't returned, pages with a variant in the import language changed in Pagible since
     * have another editor. The pages saved by this import are skipped while reading because the lists can be too long for the query.
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
     * Returns the enabled variants of the page saved by the import, the one in the import language first.
     *
     * Variants changed in Pagible since have another editor and aren't returned, like they aren't overwritten by saveTranslation().
     *
     * @param PageVariant $page Variant of the page in the import language
     * @return Collection<int, PageVariant> Variants with ID, language and path
     */
    protected function vanishedVariants( PageVariant $page ): Collection
    {
        return PageVariant::where( 'page_id', $page->page_id )->where( 'editor', $this->editor )->where( 'status', '!=', Pages::STATUS_DISABLED )
            ->select( ['id', 'page_id', 'path', 'lang'] )->get()
            ->sortBy( fn( $variant ) => $variant->lang === $this->lang ? '' : (string) $variant->lang )->values();
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
     * Publishes a version of the variant which disables it, a later import of its Joomla record enables it again.
     *
     * @param PageVariant $variant Variant with page ID and language
     */
    protected function disableVariant( PageVariant $variant ): void
    {
        if( !( $page = PageView::language( (string) $variant->lang )->find( $variant->page_id ) )
            || !( $version = $page->versions()->where( 'published', true )->first() )
        ) {
            return;
        }

        $data = array_replace( array_diff_key( (array) $version->data, ['scheduled' => null] ), ['status' => Pages::STATUS_DISABLED] );
        $aux = (array) json_decode( (string) json_encode( $version->aux ), true );

        Pages::publish( $page, $data, $aux, $version->files()->allRelatedIds()->map( strval( ... ) )->all(),
            $version->elements()->allRelatedIds()->map( strval( ... ) )->all(), (string) $page->lang, $this->editor );

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
