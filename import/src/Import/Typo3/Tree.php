<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Illuminate\Support\Collection;


/**
 * Navigates the TYPO3 page tree to select pages and find their parents and site roots.
 */
class Tree
{
    /** Page types which aren't imported but whose subpages are imported below their parent: spacers (199) and folders (254) */
    public const TRANSPARENT = [199, 254];

    /** Page type of the recyclers whose subpages aren't imported */
    public const RECYCLER = 255;

    /** Page type of the backend user sections which aren't visible in the frontend */
    public const BE_SECTION = 6;

    /** @var array<int, int>|null Positions of the TYPO3 pages by UID */
    protected ?array $positions = null;

    /** @var array<int, int|null> UIDs of the imported parent pages by page UID */
    protected array $pids = [];


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
     * Returns the TYPO3 pages which are imported in their order.
     *
     * Spacers, folders and recyclers aren't imported, the subpages of spacers and folders are
     * imported below the next imported ancestor and the subpages of recyclers aren't imported.
     *
     * @return Collection<int, mixed> TYPO3 pages
     */
    public function importable(): Collection
    {
        return $this->pages->filter( fn( $page ) => $this->imported( $page ) )->values();
    }


    /**
     * Tests if the TYPO3 page is imported.
     */
    public function imported( object $t3Page ): bool
    {
        $type = (int) ( $t3Page->doktype ?? 1 );
        return !in_array( $type, [...self::TRANSPARENT, self::RECYCLER], true ) && $this->pid( $t3Page ) !== null;
    }


    /**
     * Returns the UID of the imported parent page, 0 on the top level or NULL if the page isn't part of the imported tree.
     *
     * Spacers and folders are skipped, pages in recyclers or below missing pages aren't part of the tree.
     */
    public function pid( object $t3Page ): ?int
    {
        $uid = (int) ( $t3Page->uid ?? 0 );

        if( array_key_exists( $uid, $this->pids ) ) {
            return $this->pids[$uid];
        }

        $page = $t3Page;
        $seen = [$uid => true];

        while( ( $pid = (int) ( $page->pid ?? 0 ) ) > 0 )
        {
            if( isset( $seen[$pid] ) || !( $page = $this->pages->get( $pid ) ) || (int) ( $page->doktype ?? 1 ) === self::RECYCLER ) {
                return $this->pids[$uid] = null;
            }

            if( !in_array( (int) ( $page->doktype ?? 1 ), self::TRANSPARENT, true ) ) {
                return $this->pids[$uid] = $pid;
            }

            $seen[$pid] = true;
        }

        return $this->pids[$uid] = 0;
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
     * Translations are restricted like their page in default language, aren't shown if it's hidden and the restrictions of
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

            // TYPO3 doesn't show the translations of hidden pages
            if( $page && !empty( $page->hidden ) ) {
                return 'its page in the default language is hidden';
            }
        }
        elseif( (int) ( $t3Page->l18n_cfg ?? 0 ) & 1 )
        {
            // TYPO3 shows the page only in the translated languages
            return 'it is hidden in the default language';
        }

        if( $page && (int) ( $page->doktype ?? 1 ) === self::BE_SECTION ) {
            return 'it is a backend user section';
        }

        // the restrictions of folders and spacers apply to their subpages too
        for( $own = true; $page && !isset( $seen[$uid = (int) ( $page->uid ?? 0 )] ); $own = false, $page = $this->pages->get( (int) ( $page->pid ?? 0 ) ) )
        {
            $seen[$uid] = true;

            if( ( $own || !empty( $page->extendToSubpages ) ) && ( $reason = self::restricted( $page ) ) !== null ) {
                return $reason;
            }

            if( !$own && !empty( $page->extendToSubpages ) && !empty( $page->hidden ) ) {
                return 'its parent page [' . $uid . '] is hidden including its subpages';
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
        return $this->pages->get( (int) $this->pid( $t3Page ) );
    }


    /**
     * Returns the site root page of the TYPO3 page or the page on the top level if there's no site root.
     */
    public function root( object $t3Page ): object
    {
        $page = $t3Page;
        $seen = [];

        $last = $page;

        while( (int) ( $page->pid ?? 0 ) > 0 && empty( $page->is_siteroot ) )
        {
            $page = $this->next( $page, $seen );
            $last = in_array( (int) ( $page->doktype ?? 1 ), self::TRANSPARENT, true ) ? $last : $page;
        }

        // folders on the top level aren't imported, their subpages are the roots of the page trees
        return !empty( $page->is_siteroot ) || !in_array( (int) ( $page->doktype ?? 1 ), self::TRANSPARENT, true ) ? $page : $last;
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
