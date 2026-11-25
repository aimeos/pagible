<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\DB as CmsDB;


/**
 * Skips the TYPO3 pages which weren't changed since the date of the --since option.
 */
trait Changes
{
    /**
     * Tests if the TYPO3 page including its translations and content wasn't changed since the date of the --since option.
     */
    protected function unchangedPage( object $t3Page ): bool
    {
        $uid = (int) ( $t3Page->uid ?? 0 );

        // the modification times of the next pages in import order are fetched at once
        if( $this->since !== null && !array_key_exists( $uid, $this->changed ) ) {
            $this->fetchChanged( isset( $this->positions[$uid] ) ? array_slice( $this->sequence, $this->positions[$uid], CmsDB::IN_SIZE ) : [$t3Page] );
        }

        return $this->unchangedSince( $uid );
    }


    /**
     * Fetches the last modification times of the TYPO3 pages including the pages their content is taken from.
     *
     * The content can be taken from another page or from the pages of the content records shown by
     * the content shortcuts, e.g. folders which aren't imported themselves.
     *
     * @param array<int, object> $t3Pages TYPO3 pages in default language
     */
    protected function fetchChanged( array $t3Pages ): void
    {
        $uids = array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $t3Pages );
        // the content source and the shortcut pages leading to the page whose content is shown
        $sources = array_map( fn( $page ) => array_values( array_unique( array_filter( [(int) ( $page->content_from_pid ?? 0 ), $this->records->sourcePage( $page )],
            fn( $uid ) => $uid > 0 && $uid !== (int) ( $page->uid ?? 0 ) ) ) ), $t3Pages );
        $sourceUids = array_merge( ...array_values( $sources ) ?: [[]] );
        $targets = $this->records->shortcutTargets( array_merge( $uids, $sourceUids ) );
        $pids = $targets ? $this->reader->contentPids( array_merge( ...array_values( $targets ) ) ) : [];
        $times = $this->reader->changed( array_merge( $uids, $sourceUids, array_values( $pids ) ) );

        if( !$times && !$this->changed ) {
            $this->warn( 'No modification dates of the TYPO3 pages found, all pages are imported.' );
        }

        foreach( $uids as $idx => $uid )
        {
            // pages without modification time are changed
            $time = $times[$uid] ?? PHP_INT_MAX;
            $contents = $targets[$uid] ?? [];

            foreach( $sources[$idx] as $source )
            {
                $time = max( $time, $times[$source] ?? PHP_INT_MAX );
                array_push( $contents, ...$targets[$source] ?? [] );
            }

            // content records which don't exist anymore don't change the page
            foreach( $contents as $content ) {
                $time = isset( $pids[$content] ) ? max( $time, $times[$pids[$content]] ?? PHP_INT_MAX ) : $time;
            }

            $this->changed[$uid] = $time;
        }

        $this->changed = Database::limit( $this->changed, 10000, count( $t3Pages ) );
    }
}
