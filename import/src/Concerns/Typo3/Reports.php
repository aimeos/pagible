<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Reports the imported TYPO3 pages and prints the dry run summary.
 */
trait Reports
{
    /**
     * Counts the page and reports the result of its import including the domain.
     */
    protected function report( object $t3Page, PageView $page, string $path, string $domain, Status $status ): void
    {
        $note = " [{$domain}]" . ( $status === Status::Reused ? " (URL is used by a page which wasn't imported, was changed since or was saved by this import)" : '' );
        $this->imported( (int) ( $t3Page->uid ?? 0 ), $page, (string) ( $t3Page->title ?? '' ), $path, $status, $note );
    }


    /**
     * Returns the TYPO3 pages in the order they are imported, the descendants directly after their parent.
     *
     * @param Collection<int|string, Collection<int, mixed>> $pageMap TYPO3 pages grouped by parent ID
     * @param Collection<int, mixed>|null $roots TYPO3 root pages, the top-level pages by default
     * @return list<object> TYPO3 pages in import order
     */
    protected function treeOrder( Collection $pageMap, ?Collection $roots = null ): array
    {
        $list = [];
        $stack = ( $roots ?? $pageMap->get( 0, Collection::make() ) )->reverse()->values()->all();

        while( $page = array_pop( $stack ) )
        {
            $list[] = $page;
            array_push( $stack, ...$pageMap->get( (int) $page->uid, Collection::make() )->reverse()->values()->all() );
        }

        return $list;
    }


    /**
     * Prints a dry run summary of page hierarchy.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function printDryRun( Collection $pages ): void
    {
        $pageMap = $pages->groupBy( fn( $page ) => (int) $this->tree->pid( $page ) );

        $printTree = function( int $pid, int $depth ) use ( &$printTree, $pageMap ) {
            foreach( $pageMap->get( $pid, Collection::make() ) as $page )
            {
                $this->line( str_repeat( '  ', $depth ) . $this->dryRunLine( $page ) );
                $printTree( $page->uid, $depth + 1 );
            }
        };

        $printTree( 0, 0 );
        $this->printRedirects( $pages );
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Prints explicitly selected pages for a dry run.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function printSelectedDryRun( Collection $pages ): void
    {
        foreach( $pages as $page ) {
            $this->line( $this->dryRunLine( $page ) );
        }

        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Returns the line of the TYPO3 page printed by the dry run.
     */
    protected function dryRunLine( object $page ): string
    {
        $type = (int) ( $page->doktype ?? 0 ) === 4 ? ' [shortcut]' : '';
        $hidden = !empty( $page->hidden ) ? ' [hidden]' : '';

        return '[' . ( $page->uid ?? '' ) . '] ' . ( $page->title ?? '' ) . ' (' . ( $page->slug ?? '' ) . "){$type}{$hidden}";
    }
}
