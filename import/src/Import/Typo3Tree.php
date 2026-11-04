<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Navigates the TYPO3 page tree to select pages and find their parents and site roots.
 */
class Typo3Tree
{
    /** @var array<int, int>|null Positions of the TYPO3 pages by UID */
    protected ?array $positions = null;


    /**
     * @param Collection<int, mixed> $pages TYPO3 pages keyed by UID
     */
    public function __construct( protected Collection $pages )
    {
    }


    /**
     * Returns the TYPO3 page UIDs of the --page options or NULL for a full import.
     *
     * @param array<mixed> $values Values of the --page option
     * @return list<int>|null Unique page UIDs or NULL if no page is selected
     * @throws \InvalidArgumentException If a value isn't a page UID
     */
    public static function uids( array $values ): ?array
    {
        if( $values === [] ) {
            return null;
        }

        $ids = [];

        foreach( $values as $value )
        {
            $value = (string) $value;

            if( !ctype_digit( $value ) || (int) $value < 1 ) {
                throw new \InvalidArgumentException( "Invalid TYPO3 page UID: {$value}" );
            }

            $ids[] = (int) $value;
        }

        return array_values( array_unique( $ids ) );
    }


    /**
     * Returns a key which sorts the TYPO3 pages in tree order, parents before their children and siblings in their order.
     *
     * The keys must be compared as strings.
     */
    public function order( object $t3Page ): string
    {
        $this->positions ??= array_flip( $this->pages->keys()->map( fn( $uid ) => (int) $uid )->all() );

        $page = $t3Page;
        $seen = [];
        $key = sprintf( '%010d', $this->positions[(int) ( $page->uid ?? 0 )] ?? 0 );

        while( (int) ( $page->pid ?? 0 ) > 0 )
        {
            $page = $this->next( $page, $seen );
            $key = sprintf( '%010d', $this->positions[(int) ( $page->uid ?? 0 )] ?? 0 ) . $key;
        }

        return $key;
    }


    /**
     * Returns why the TYPO3 page or translation isn't visible for all visitors now or NULL if it is.
     *
     * Translations are restricted like their page in default language and the restrictions of
     * the parent pages with "Extend to subpages" apply to their subpages too.
     */
    public function restriction( object $t3Page ): ?string
    {
        $page = $t3Page;
        $seen = [];

        if( (int) ( $t3Page->sys_language_uid ?? 0 ) > 0 )
        {
            if( ( $reason = self::restricted( $t3Page ) ) !== null ) {
                return $reason;
            }

            $page = $this->pages->get( (int) ( $t3Page->l10n_parent ?? 0 ) );
        }

        for( $own = true; $page && !isset( $seen[$uid = (int) ( $page->uid ?? 0 )] ); $own = false, $page = $this->parent( $page ) )
        {
            $seen[$uid] = true;

            if( ( $own || !empty( $page->extendToSubpages ) ) && ( $reason = self::restricted( $page ) ) !== null ) {
                return $reason;
            }
        }

        return null;
    }


    /**
     * Returns why the TYPO3 record itself isn't visible for all visitors now or NULL if it is.
     *
     * Records restricted to frontend user groups are hidden for anonymous visitors unless
     * "hide at login" (-1) is one of the groups. Expired and scheduled records aren't visible.
     */
    public static function restricted( object $record ): ?string
    {
        $groups = array_diff( array_map( 'trim', explode( ',', (string) ( $record->fe_group ?? '' ) ) ), [''] );

        if( $groups !== [] && !array_intersect( $groups, ['0', '-1'] ) ) {
            return 'it is restricted to TYPO3 frontend user groups';
        }

        if( ( $end = (int) ( $record->endtime ?? 0 ) ) > 0 && $end <= time() ) {
            return 'it expired on ' . date( 'Y-m-d H:i', $end );
        }

        if( ( $start = (int) ( $record->starttime ?? 0 ) ) > time() ) {
            return 'it is scheduled for ' . date( 'Y-m-d H:i', $start ) . ', enable it then';
        }

        return null;
    }


    /**
     * Returns the parent of the TYPO3 page or NULL if it isn't available.
     */
    public function parent( object $t3Page ): ?\stdClass
    {
        return $this->pages->get( (int) ( $t3Page->pid ?? 0 ) );
    }


    /**
     * Returns the site root page of the TYPO3 page or the page on the top level if there's no site root.
     */
    public function root( object $t3Page ): object
    {
        $page = $t3Page;
        $seen = [];

        while( (int) ( $page->pid ?? 0 ) > 0 && empty( $page->is_siteroot ) ) {
            $page = $this->next( $page, $seen );
        }

        return $page;
    }


    /**
     * Returns the TYPO3 pages with the UIDs in the given order.
     *
     * @param list<int> $uids TYPO3 page UIDs
     * @return Collection<int, mixed>
     * @throws \InvalidArgumentException If one of the pages doesn't exist
     */
    public function select( array $uids ): Collection
    {
        if( $missing = array_values( array_diff( $uids, $this->pages->keys()->map( fn( $uid ) => (int) $uid )->all() ) ) ) {
            throw new \InvalidArgumentException( 'TYPO3 page UID not found: ' . implode( ', ', $missing ) );
        }

        return Collection::make( $uids )->map( fn( $uid ) => $this->pages->get( $uid ) )->values();
    }


    /**
     * Returns the parent of the page and detects cycles in the page tree.
     *
     * @param array<int, bool> $seen UIDs of the pages already visited
     * @throws \RuntimeException If the parent is missing or the page was visited before
     */
    protected function next( object $page, array &$seen ): object
    {
        $uid = (int) ( $page->uid ?? 0 );

        if( isset( $seen[$uid] ) ) {
            throw new \RuntimeException( "Cycle detected in TYPO3 page tree at page [{$uid}]." );
        }

        $seen[$uid] = true;
        $pid = (int) ( $page->pid ?? 0 );

        return $this->pages->get( $pid ) ?? throw new \RuntimeException( sprintf( 'TYPO3 parent page [%d] is missing from the source tree.', $pid ) );
    }
}
