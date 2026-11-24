<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Fetches the content, file references and files of the next TYPO3 pages in import order at once.
 */
trait Prefetch
{
    /** @var list<object> TYPO3 pages in import order, so their content and modification times are fetched in chunks */
    protected array $sequence = [];

    /** @var array<int, int> Positions of the TYPO3 pages in $sequence by UID */
    protected array $positions = [];

    /** @var array{0: int, 1: int} First and next position in $sequence of the pages whose content was prefetched last */
    protected array $window = [0, 0];

    /** @var array<int, Collection<int, mixed>|null> Social media image references of the upcoming pages and their translations by page UID */
    protected array $ogImages = [];


    /**
     * Remembers the order the TYPO3 pages are imported in, so their content and the modification times for --since are fetched in chunks.
     *
     * @param array<int, object> $t3Pages TYPO3 pages in import order
     */
    protected function sequence( array $t3Pages ): void
    {
        $this->sequence = array_values( $t3Pages );
        $this->positions = array_flip( array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $this->sequence ) );
        $this->window = [0, 0];
    }


    /**
     * Fetches the content and downloads the files of the page and its translations before it's imported, so the batch transaction isn't kept open during the downloads.
     *
     * Pages which aren't imported again, e.g. unchanged ones or the ones only used as parent, are skipped.
     *
     * @param PageView|null $page Existing page with the URL of the TYPO3 page
     */
    protected function preload( object $t3Page, ?PageView $page ): void
    {
        if( !( $typo3 = $this->typo3 ) || $page && ( isset( $this->usedPages[$page->id ?? ''] ) || !$this->isImported( $page ) || $this->unchangedPage( $t3Page ) ) ) {
            return;
        }

        $this->prefetchContent( $t3Page );

        try
        {
            // the content records and file references are used again when the page and its translations are imported
            $records = $this->records->page( $t3Page );

            foreach( $this->translationRecords( $t3Page )->unique( 'sys_language_uid' ) as $record )
            {
                if( ( $language = $this->languages->get( $langUid = (int) ( $record->sys_language_uid ?? 0 ), $this->siteRoot( $t3Page ) ) ) && $language['lang'] !== $this->langOf( $t3Page ) ) {
                    $records = $records->concat( $this->records->page( $t3Page, $langUid ) );
                }
            }

            $typo3->prefetch( $records );
            $paths = $this->files->paths( $typo3->references(), $typo3->texts( $records ) );
        }
        catch( \Exception )
        {
            // errors are reported when the page is imported
            return;
        }

        $this->prefetchFiles( $paths, false );

        // all files of the page are downloaded because they would be downloaded within the transaction of the page otherwise
        $this->download( [], array_filter( $paths, fn( $path ) => str_starts_with( $path, 'http' ) ) );
    }


    /**
     * Fetches the content records, file references and files of the next pages in import order at once if the ones of the page aren't fetched yet.
     *
     * The content of unchanged pages isn't fetched for --since and the content of the pages
     * fetched before is replaced, so only the content of a limited number of pages is in memory.
     */
    protected function prefetchContent( object $t3Page ): void
    {
        if( !$this->typo3 ) {
            return;
        }

        try
        {
            if( $this->records->prefetched( $t3Page ) ) {
                return;
            }

            $uid = (int) ( $t3Page->uid ?? 0 );
            $pos = $this->positions[$uid] ?? null;

            // unchanged pages are imported if they don't exist, their content is fetched on its own instead of fetching the upcoming pages again
            if( $pos !== null && $pos >= $this->window[0] && $pos < $this->window[1] ) {
                return;
            }

            $size = $this->contentSize();
            $this->window = $pos !== null ? [$pos, $pos + $size] : [0, 0];
            $t3Pages = $pos !== null ? array_slice( $this->sequence, $pos, $size ) : [$t3Page];
            $t3Pages = array_filter( $t3Pages, fn( $page ) => $page === $t3Page || !$this->unchangedPage( $page ) );
            $translated = array_filter( $t3Pages, fn( $page ) => isset( $this->translated[(int) ( $page->uid ?? 0 )] ) );

            // the translations of the pages are replaced by the ones of the upcoming pages
            $this->translations = [];
            $this->translations = $this->reader->translatedPages( array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $translated ), $this->langUids )
                ->groupBy( fn( $page ) => (int) $page->l10n_parent )->all();

            $records = $this->records->prefetch( $t3Pages, $translated );
            $refs = $this->typo3->ahead( $records );

            // the social media images of the pages and their translations are fetched at once
            $this->ogImages = [];

            if( ( $first = reset( $t3Pages ) ) && ( property_exists( $first, 'og_image' ) || property_exists( $first, 'og_title' ) ) )
            {
                $uids = array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $t3Pages );
                $uids = array_merge( $uids, ...array_map( fn( $list ) => $list->map( fn( $page ) => (int) ( $page->uid ?? 0 ) )->all(), array_values( $this->translations ) ) );
                $this->ogImages = array_fill_keys( $uids, null );

                foreach( $this->reader->pageFileReferences( $uids ) as $uid => $list )
                {
                    $this->ogImages[(int) $uid] = $list;
                    $refs = $refs->concat( $list );
                }
            }

            $paths = $this->files->paths( $refs, $this->typo3->texts( $records ) );
        }
        catch( \Exception )
        {
            // errors are reported when the page is imported
            return;
        }

        $this->prefetchFiles( $paths );
    }


    /**
     * Returns the number of the next pages whose content is fetched at once.
     *
     * The content of each page is fetched for every imported language, so fewer pages are
     * fetched for more languages to keep the number of content records in memory similar.
     * The content of at least ten pages is fetched at once, for two languages or less CONTENT_SIZE.
     */
    protected function contentSize(): int
    {
        return min( static::CONTENT_SIZE, max( 10, intdiv( static::CONTENT_SIZE * 3, 1 + count( $this->langUids ) ) ) );
    }
}
