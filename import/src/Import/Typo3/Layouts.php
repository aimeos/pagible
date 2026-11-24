<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Illuminate\Support\Collection;


/**
 * Orders the TYPO3 content elements by the columns of the backend layouts of the pages.
 */
class Layouts
{
    /** @var array<string, list<int>> Ordered columns by backend layout, for PageTSconfig layouts also by the last page defining PageTSconfig */
    protected array $columns = [];


    /**
     * Initializes the object.
     *
     * @param Collection<int, mixed> $pages TYPO3 pages keyed by UID
     * @param Collection<int|string, mixed> $layouts Database backend layouts keyed by UID
     * @param (\Closure(string): ?string)|null $include Returns the content of the imported TSconfig file, e.g. "EXT:site/Configuration/page.tsconfig", or NULL if it can't be read
     * @param string $global PageTSconfig loaded for all pages, e.g. from the "Configuration/page.tsconfig" files of the extensions
     * @param (\Closure(object, int): void)|null $unused Called for the content records in columns which aren't part of the backend layout with the record and its column
     */
    public function __construct( protected Collection $pages, protected Collection $layouts, protected ?\Closure $include = null,
        protected string $global = '', protected ?\Closure $unused = null )
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
        // without backend layout, the main column is rendered first like by the default layout, then the others
        if( ( $columns = $this->columns( $t3Page ) ) === [] )
        {
            return $records->sortBy( fn( $record ) => [
                (int) ( $record->colPos ?? 0 ) !== 0 ? 1 : 0, (int) ( $record->colPos ?? 0 ), (int) ( $record->sorting ?? 0 ), (int) ( $record->uid ?? 0 )
            ] )->values();
        }

        $positions = array_flip( $columns );
        $fallback = count( $positions );

        // content in columns which aren't part of the backend layout isn't shown by TYPO3, content inside containers has the columns of the container
        $records = $records->filter( function( $record ) use ( $positions ) {
            $colPos = (int) ( $record->colPos ?? 0 );

            if( isset( $positions[$colPos] ) || $colPos === -1 || !empty( $record->tx_container_parent ) || !empty( $record->tx_gridelements_container ) ) {
                return true;
            }

            if( $this->unused ) {
                ( $this->unused )( $record, $colPos );
            }

            return false;
        } );

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
            // database layouts are stored by their UID, "db__" is the prefix used by older extensions
            ctype_digit( $layout ) => (string) data_get( $this->layouts->get( (int) $layout ), 'config', '' ),
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
            if( (string) ( $page->TSconfig ?? '' ) || (string) ( $page->tsconfig_includes ?? '' ) ) {
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

        // the files included by the pages are loaded before their own TSconfig
        $source = Collection::make( array_reverse( $pages ) )
            ->map( fn( $item ) => $this->pageIncludes( $item ) . "\n" . (string) ( $item->TSconfig ?? '' ) )
            ->filter( fn( $value ) => trim( $value ) !== '' )
            ->implode( "\n" );

        return $this->block( $this->includes( $this->global . "\n" . $source ), 'mod.web_layout.BackendLayouts.' . $name );
    }


    /**
     * Returns the TSconfig of the static files included by the page using its "tsconfig_includes" field.
     */
    protected function pageIncludes( object $t3Page ): string
    {
        $source = '';

        foreach( array_filter( array_map( 'trim', explode( ',', (string) ( $t3Page->tsconfig_includes ?? '' ) ) ) ) as $file ) {
            $source .= '@import \'' . $file . "'\n";
        }

        return $source;
    }


    /**
     * Returns the content of the last TypoScript block with the object path, e.g. "mod.web_layout.BackendLayouts.default".
     *
     * Supports the dotted ("mod.web_layout.BackendLayouts.default {") and the nested syntax
     * ("mod { web_layout { BackendLayouts { default {") as well as assignments to the full path.
     * Copies ("<") and removals (">") of the block are applied, conditions are ignored and their content is used.
     */
    protected function block( string $source, string $target, int $level = 0 ): string
    {
        $stack = $buffer = $assigned = [];
        $result = null;
        $depth = null;

        foreach( preg_split( '/\R/', $source ) ?: [] as $line )
        {
            $trimmed = trim( $line );

            if( $trimmed === '' || str_starts_with( $trimmed, '#' ) || str_starts_with( $trimmed, '//' ) || $depth === null && str_starts_with( $trimmed, '[' ) ) {
                continue;
            }

            // copies and removals of the block outside of it, e.g. "BackendLayouts.default < BackendLayouts.base"
            if( $depth === null && preg_match( '/^([\w.\-]+)\s*(<|>)\s*([\w.\-]*)$/', $trimmed, $match ) )
            {
                $prefix = implode( '.', array_filter( $stack, fn( $key ) => $key !== '{' ) );
                $path = ( $prefix !== '' ? $prefix . '.' : '' ) . $match[1];

                if( $match[2] === '>' && ( $path === $target || str_starts_with( $path, $target . '.' ) || str_starts_with( $target, $path . '.' ) ) )
                {
                    $result = null;
                    $assigned = [];
                }
                elseif( $match[2] === '<' && $path === $target && $match[3] !== '' && $level < 10 )
                {
                    // references starting with a dot are relative to the current block
                    $from = str_starts_with( $match[3], '.' ) ? $prefix . $match[3] : $match[3];
                    $result = $from !== $target ? $this->block( $source, $from, $level + 1 ) : $result;
                    $assigned = [];
                }

                continue;
            }

            if( preg_match( '/^([\w.\-]+)\s*\{$/', $trimmed, $match ) )
            {
                if( $depth !== null ) {
                    $buffer[] = $trimmed;
                }

                $stack[] = '{';
                array_push( $stack, ...explode( '.', $match[1] ) );

                if( $depth === null && implode( '.', array_filter( $stack, fn( $key ) => $key !== '{' ) ) === $target )
                {
                    $depth = count( $stack );
                    $buffer = [];
                }

                continue;
            }

            if( str_starts_with( $trimmed, '}' ) )
            {
                if( $depth !== null && count( $stack ) === $depth )
                {
                    $result = implode( "\n", $buffer );
                    $depth = null;
                } elseif( $depth !== null ) {
                    $buffer[] = $trimmed;
                }

                // the keys of the block are removed up to its opening brace
                while( ( $key = array_pop( $stack ) ) !== null && $key !== '{' );

                continue;
            }

            if( $depth !== null )
            {
                $buffer[] = $trimmed;
                continue;
            }

            $path = implode( '.', array_filter( [...array_filter( $stack, fn( $key ) => $key !== '{' ), $trimmed], fn( $value ) => $value !== '' ) );

            if( str_starts_with( $path, $target . '.' ) ) {
                $assigned[] = $path;
            }
        }

        return $result ?? implode( "\n", $assigned );
    }


    /**
     * Replaces the imports of TSconfig files by their content if the files can be read.
     *
     * Supports the "@import" statements and the legacy "<INCLUDE_TYPOSCRIPT:" tags.
     *
     * @param string $source TSconfig
     * @param int $depth Nesting level of the imports
     * @return string TSconfig including the imported files
     */
    protected function includes( string $source, int $depth = 0 ): string
    {
        if( !( $include = $this->include ) || $depth > 10 ) {
            return $source;
        }

        $pattern = '/^[ \t]*(?:@import\s+[\'"]([^\'"]+)[\'"]|<INCLUDE_TYPOSCRIPT:\s*source\s*=\s*"(?:FILE:)?([^"]+)"[^>]*>)[^\r\n]*$/mi';

        return (string) preg_replace_callback( $pattern, function( $match ) use ( $depth, $include ) {
            $content = $include( trim( $match[1] ?: ( $match[2] ?? '' ) ) );
            return $content !== null ? $this->includes( (string) $content, $depth + 1 ) : '';
        }, $source );
    }
}
