<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Orders the TYPO3 content elements by the columns of the backend layouts of the pages.
 */
class Typo3Layouts
{
    /** @var array<string, list<int>> Ordered columns by backend layout, for PageTSconfig layouts also by the last page defining PageTSconfig */
    protected array $columns = [];


    /**
     * Initializes the object.
     *
     * @param Collection<int, mixed> $pages TYPO3 pages keyed by UID
     * @param Collection<int|string, mixed> $layouts Database backend layouts keyed by UID
     */
    public function __construct( protected Collection $pages, protected Collection $layouts )
    {
    }


    /**
     * Orders records like the TYPO3 frontend: backend-layout row/column order,
     * followed by the record sorting value inside each content column.
     *
     * @param Collection<int, mixed> $records
     * @return Collection<int, mixed>
     */
    public function sort( Collection $records, object $t3Page ): Collection
    {
        if( ( $columns = $this->columns( $t3Page ) ) === [] ) {
            return $records->values();
        }

        $positions = array_flip( $columns );
        $fallback = count( $positions );

        return $records->sort( function( $left, $right ) use ( $positions, $fallback ): int {
            $leftPosition = $positions[(int) ( $left->colPos ?? 0 )] ?? $fallback;
            $rightPosition = $positions[(int) ( $right->colPos ?? 0 )] ?? $fallback;

            return [$leftPosition, (int) ( $left->sorting ?? 0 ), (int) ( $left->uid ?? 0 )]
                <=> [$rightPosition, (int) ( $right->sorting ?? 0 ), (int) ( $right->uid ?? 0 )];
        } )->values();
    }


    /**
     * Returns the ordered TYPO3 content columns for the page's backend layout.
     *
     * @return list<int>
     */
    protected function columns( object $t3Page ): array
    {
        $layout = $this->layout( $t3Page );
        // pages inherit the same PageTSconfig from the closest page defining one, so it's parsed only once for all of them
        $key = str_starts_with( $layout, 'pagets__' ) ? $layout . ':' . $this->tsPage( $t3Page ) : $layout;

        return $this->columns[$key] ??= $this->resolve( $t3Page );
    }


    /**
     * Returns the backend layout of the page, inherited from its parent pages if not set.
     */
    protected function layout( object $t3Page ): string
    {
        $layout = trim( (string) ( $t3Page->backend_layout ?? '' ) );

        if( $layout === '' )
        {
            $page = $t3Page;
            $seen = [];

            while( (int) ( $page->pid ?? 0 ) > 0 )
            {
                $uid = (int) ( $page->uid ?? 0 );

                if( isset( $seen[$uid] ) || !( $page = $this->pages->get( (int) $page->pid ) ) ) {
                    break;
                }

                $seen[$uid] = true;

                if( ( $layout = trim( (string) ( $page->backend_layout_next_level ?? '' ) ) ) !== '' ) {
                    break;
                }
            }
        }

        return $layout;
    }


    /**
     * Resolves the ordered TYPO3 content columns for the backend layout of the page.
     *
     * @return list<int>
     */
    protected function resolve( object $t3Page ): array
    {
        $layout = $this->layout( $t3Page );
        $config = match( true ) {
            str_starts_with( $layout, 'pagets__' ) => $this->pageTs( $t3Page, substr( $layout, strlen( 'pagets__' ) ) ),
            str_starts_with( $layout, 'db__' ) => (string) data_get( $this->layouts->get( (int) substr( $layout, strlen( 'db__' ) ) ), 'config', '' ),
            default => '',
        };

        preg_match_all( '/\bcolPos\s*=\s*(-?\d+)/i', $config, $matches );

        return array_values( array_unique( array_map( 'intval', $matches[1] ) ) );
    }


    /**
     * Returns the UID of the page or its closest parent page with PageTSconfig, 0 if there's none.
     */
    protected function tsPage( object $t3Page ): int
    {
        $seen = [];
        $page = $t3Page;

        while( $page && !isset( $seen[$uid = (int) ( $page->uid ?? 0 )] ) )
        {
            if( (string) ( $page->TSconfig ?? '' ) ) {
                return $uid;
            }

            $seen[$uid] = true;
            $pid = (int) ( $page->pid ?? 0 );
            $page = $pid > 0 ? $this->pages->get( $pid ) : null;
        }

        return 0;
    }


    /**
     * Returns one inherited PageTSconfig backend-layout block.
     */
    protected function pageTs( object $t3Page, string $name ): string
    {
        $pages = $seen = [];
        $page = $t3Page;

        while( $page )
        {
            $uid = (int) ( $page->uid ?? 0 );

            if( isset( $seen[$uid] ) ) {
                break;
            }

            $seen[$uid] = true;
            $pages[] = $page;
            $pid = (int) ( $page->pid ?? 0 );
            $page = $pid > 0 ? $this->pages->get( $pid ) : null;
        }

        $source = Collection::make( array_reverse( $pages ) )
            ->map( fn( $item ) => (string) ( $item->TSconfig ?? '' ) )
            ->filter()
            ->implode( "\n" );
        $pattern = '/(?:^|\R)\s*mod\.web_layout\.BackendLayouts\.' . preg_quote( $name, '/' ) . '\s*\{/m';

        if( !preg_match_all( $pattern, $source, $matches, PREG_OFFSET_CAPTURE ) || empty( $matches[0] ) ) {
            return '';
        }

        $match = end( $matches[0] );

        if( ( $start = strpos( $source, '{', (int) $match[1] ) ) === false ) {
            return '';
        }

        $depth = 0;
        $length = strlen( $source );

        for( $offset = $start; $offset < $length; $offset++ )
        {
            if( $source[$offset] === '{' ) {
                $depth++;
            } elseif( $source[$offset] === '}' && --$depth === 0 ) {
                return substr( $source, $start + 1, $offset - $start - 1 );
            }
        }

        return '';
    }
}
