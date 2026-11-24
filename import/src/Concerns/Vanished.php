<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Collection;


/**
 * Disables the pages of previous imports whose source records aren't imported any more.
 *
 * The pages don't store the IDs of their source records, they are found by their URL paths like by savePage(). Pages of
 * articles which were deleted, trashed, unpublished, moved to a category which isn't imported or aren't selected
 * any more get no URL path of the current import. Their pages and the variants of their translations are disabled by a new
 * version, so they are enabled again by the next import which renders their articles. Only enabled content pages of the
 * import with the same editor, language and domain are disabled, i.e. not the root page, link pages of menu items and
 * redirects, pages and variants changed in Pagible since or pages of other domains and languages.
 *
 * The class using the trait returns the URL paths of the imported records by importedPaths() and why the pages are
 * disabled by vanishedReason() and vanishedMessage().
 */
trait Vanished
{
    /**
     * Disables the pages of previous imports whose source records aren't imported any more and their variants.
     *
     * Pages saved by this import are never disabled. Pages using the URL path of an imported record aren't
     * disabled either, e.g. if their import failed or was skipped this time.
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
            $published = Pages::publishedVersions( $all->pluck( 'id' )->map( strval( ... ) )->all() );

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
            $this->warn( $this->vanishedMessage( $count ) );
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

        return $this->vanishedScope( PageVariant::whereHas( 'page', fn( $query ) => $query->withoutTrashed() ) )
            ->where( 'domain', $this->domain )->where( 'lang', $this->lang )->where( 'editor', $this->editor )
            ->where( 'tag', '!=', Pages::TAG_ROOT )->where( 'status', '!=', Pages::STATUS_DISABLED )
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

        $variants = PageVariant::whereIn( 'page_id', $pageIds )->where( 'editor', $this->editor )->where( 'status', '!=', Pages::STATUS_DISABLED )
            ->select( ['id', 'page_id', 'path', 'lang'] )->get()
            ->sortBy( fn( $variant ) => $variant->lang === $this->lang ? '' : (string) $variant->lang );

        $grouped = [];

        foreach( $variants as $variant ) {
            $grouped[(string) $variant->page_id][] = $variant;
        }

        /** @var Collection<string, Collection<int, PageVariant>> */
        return Collection::make( $grouped )->map( fn( array $list ) => Collection::make( $list ) );
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

            /** @var PageView $view */
            foreach( PageView::language( (string) $lang )->whereKey( $pageIds )->get() as $view ) {
                $views[$view->variant_id] = $view;
            }
        }

        return $views;
    }


    /**
     * Publishes a version of the page in the language of the variant which disables it, a later import of its source record enables it again.
     *
     * @param PageView $page Page in the language of the variant
     * @param array{version: Version, files: array<int, string>, elements: array<int, string>} $published Latest published version
     *  of the variant and the IDs of its files and shared elements, see Pages::publishedVersions()
     */
    protected function disableVariant( PageView $page, array $published ): void
    {
        Pages::disable( $page, $published['version'], $published['files'], $published['elements'], $this->editor );

        $this->notice( 'Disabled', '/' . ltrim( (string) $page->path, '/' ) . " [{$page->lang}]", $this->vanishedReason() );
    }


    /**
     * Limits the query for the vanished pages, e.g. to the pages below the parent page of the import.
     *
     * Link pages of menu items and redirects aren't disabled by default.
     *
     * @param \Illuminate\Database\Eloquent\Builder<PageVariant> $query Query for the page variants
     * @return \Illuminate\Database\Eloquent\Builder<PageVariant> Limited query
     */
    protected function vanishedScope( \Illuminate\Database\Eloquent\Builder $query ): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where( 'to', '' );
    }


    /**
     * Returns the URL paths of the records selected for the import, also of the ones which weren't saved.
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    abstract protected function importedPaths(): array;


    /**
     * Returns why the pages of previous imports are disabled.
     */
    abstract protected function vanishedReason(): string;


    /**
     * Returns the warning shown after disabling the pages of previous imports.
     *
     * @param int $count Number of disabled pages
     */
    abstract protected function vanishedMessage( int $count ): string;
}
