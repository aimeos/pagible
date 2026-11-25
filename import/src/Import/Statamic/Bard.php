<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Statamic;


/**
 * Converts Statamic Bard ProseMirror nodes into Markdown and plain text.
 */
class Bard
{
    /** Regular expression matching the characters with a meaning in Markdown text */
    protected const ESCAPE_PATTERN = '/([\\\\`*_\[\]<>~|])/';

    /** HTML tags of the marks used if their Markdown syntax directly follows the closing syntax of other marks */
    protected const HTML_TAGS = ['bold' => ['<strong>', '</strong>'], 'italic' => ['<em>', '</em>'], 'strike' => ['<del>', '</del>']];


    /**
     * Initializes the converter.
     *
     * @param \Closure(string): ?string $link Returns the URL of the link target or NULL if it can't be linked
     */
    public function __construct( protected \Closure $link )
    {
    }


    /**
     * Converts a Bard ProseMirror node into Markdown text.
     *
     * @param array<mixed> $node Bard node
     */
    public function markdown( array $node ): string
    {
        return match( $node['type'] ?? '' ) {
            'text' => $this->inline( ['content' => [$node]] ),
            'paragraph' => $this->paragraph( $node ),
            'heading' => str_repeat( '#', self::level( $node['attrs']['level'] ?? 2 ) ) . ' ' . trim( $this->inline( $node ) ) . "\n\n",
            'bulletList' => $this->list( $node, fn( int $idx ) => '- ' ),
            'orderedList' => $this->list( $node, fn( int $idx ) => ( (int) ( $node['attrs']['start'] ?? $node['attrs']['order'] ?? 1 ) + $idx ) . '. ' ),
            'blockquote' => $this->blockquote( $node ),
            'codeBlock' => $this->code( $this->text( $node ) ),
            'hardBreak' => "  \n",
            // "***" instead of "---" which would turn the text above into a heading
            'horizontalRule' => "***\n\n",
            'table' => $this->pipes( $node ),
            default => $this->inline( $node ),
        };
    }


    /**
     * Tests if the Markdown text contains HTML tags added by the converter for marks without Markdown syntax.
     *
     * Tags in the text of the Bard nodes are escaped, so only the tags of the marks are found.
     *
     * @param string $markdown Markdown text
     */
    public static function hasHtml( string $markdown ): bool
    {
        return preg_match( '/(?<!\\\\)<(?:su[bp]>|u>|small>|strong>|em>|del>|a\s)/', $markdown ) === 1;
    }


    /**
     * Returns the heading level limited to the levels of HTML headings.
     *
     * @param mixed $level Heading level like 2 or "h2"
     * @return int Level from 1 to 6
     */
    public static function level( mixed $level ): int
    {
        // Select fields often store the levels like "h2"
        if( is_string( $level ) && preg_match( '/^h([1-6])$/i', trim( $level ), $match ) ) {
            $level = $match[1];
        }

        return min( max( is_numeric( $level ) ? (int) $level : 2, 1 ), 6 );
    }


    /**
     * Converts a Bard table node into the rows of a table element.
     *
     * The cells contain inline Markdown, cells spanning several columns or rows are followed by empty cells
     * so the other cells stay in their columns.
     *
     * @param array<mixed> $node Bard table node
     * @return array{header: string, rows: array<int, array<int, string>>}|null Header type like "row", "col" or "row+col" and rows of cells or NULL without rows
     */
    public function table( array $node ): ?array
    {
        $rows = [];
        $header = '';
        $col = true;
        /** @var array<int, array<int, true>> $spans Columns covered by cells of previous rows by row */
        $spans = [];

        foreach( (array) ( $node['content'] ?? [] ) as $row )
        {
            $cells = array_values( array_filter( (array) ( is_array( $row ) ? $row['content'] ?? [] : [] ), 'is_array' ) );

            if( $cells === [] ) {
                continue;
            }

            // the first row is the header if all of its cells are header cells
            if( $rows === [] && array_filter( $cells, fn( $cell ) => ( $cell['type'] ?? '' ) !== 'tableHeader' ) === [] ) {
                $header = 'row';
            }

            // the first column is the header if the first cells of all rows are header cells
            $col = $col && ( $cells[0]['type'] ?? '' ) === 'tableHeader';

            $idx = count( $rows );
            $covered = $spans[$idx] ?? [];
            $result = [];

            foreach( $cells as $cell )
            {
                while( isset( $covered[count( $result )] ) ) {
                    $result[] = '';
                }

                $pos = count( $result );
                $colspan = max( 1, min( 100, (int) ( $cell['attrs']['colspan'] ?? 1 ) ) );
                $rowspan = max( 1, min( 100, (int) ( $cell['attrs']['rowspan'] ?? 1 ) ) );

                $result[] = $this->cell( $cell );
                array_push( $result, ...array_fill( 0, $colspan - 1, '' ) );

                for( $r = 1; $r < $rowspan; $r++ )
                {
                    for( $c = $pos; $c < $pos + $colspan; $c++ ) {
                        $spans[$idx + $r][$c] = true;
                    }
                }
            }

            // columns covered by cells of previous rows after the last cell
            while( $covered !== [] && max( array_keys( $covered ) ) >= count( $result ) ) {
                $result[] = '';
            }

            unset( $spans[$idx] );
            $rows[] = $result;
        }

        if( $col && count( $rows ) > 1 ) {
            $header = $header === 'row' ? 'row+col' : 'col';
        }

        return $rows !== [] ? ['header' => $header, 'rows' => $rows] : null;
    }


    /**
     * Converts a Bard table node into a Markdown table for tables in quotes and lists.
     *
     * Markdown tables always have a header row, it's empty if the first row of the table isn't a header.
     *
     * @param array<mixed> $node Bard table node
     */
    protected function pipes( array $node ): string
    {
        if( ( $table = $this->table( $node ) ) === null ) {
            return '';
        }

        $rows = $table['rows'];
        $cols = max( [1, ...array_map( 'count', $rows )] );
        $header = str_starts_with( $table['header'], 'row' ) ? array_shift( $rows ) ?? [] : [];
        // pipes separate the cells also in code, so the pipes which aren't escaped yet are escaped
        $line = fn( array $cells ) => rtrim( '| ' . implode( ' | ', array_map( fn( $cell ) => preg_replace( '/(?<!\\\\)\|/', '\\\\|', $cell ), array_pad( $cells, $cols, '' ) ) ) . ' |' ) . "\n";

        return $line( $header ) . $line( array_fill( 0, $cols, '---' ) ) . implode( '', array_map( $line, $rows ) ) . "\n";
    }


    /**
     * Extracts the plain text of a Bard node.
     *
     * @param array<mixed> $node Bard node
     */
    public function text( array $node ): string
    {
        $text = '';

        foreach( (array) ( $node['content'] ?? [] ) as $child )
        {
            if( !is_array( $child ) ) {
                continue;
            }

            $text .= match( $child['type'] ?? '' ) {
                'text' => is_string( $child['text'] ?? null ) ? $child['text'] : '',
                'hardBreak' => "\n",
                'paragraph', 'heading' => $this->text( $child ) . ' ',
                default => $this->text( $child ),
            };
        }

        return $text;
    }


    /**
     * Converts a Bard blockquote node into Markdown.
     *
     * @param array<mixed> $node Bard blockquote node
     */
    protected function blockquote( array $node ): string
    {
        $lines = explode( "\n", trim( $this->inline( $node ) ) );

        return implode( "\n", array_map( fn( $line ) => rtrim( '> ' . $line ), $lines ) ) . "\n\n";
    }


    /**
     * Converts a Bard table cell into inline Markdown on a single line.
     *
     * Paragraphs and headings keep their marks and links, the text of other nodes like lists is escaped.
     *
     * @param array<mixed> $cell Bard table cell or header node
     */
    protected function cell( array $cell ): string
    {
        $parts = [];

        foreach( (array) ( $cell['content'] ?? [] ) as $child )
        {
            if( is_array( $child ) ) {
                $parts[] = trim( in_array( $child['type'] ?? '', ['paragraph', 'heading'], true )
                    ? $this->paragraph( ['content' => $child['content'] ?? []] )
                    : $this->escape( $this->text( $child ) ) );
            }
        }

        return trim( (string) preg_replace( '/\s+/', ' ', implode( ' ', array_filter( $parts, fn( $part ) => $part !== '' ) ) ) );
    }


    /**
     * Returns the escaped text of a text node.
     *
     * @param string $text Plain text
     */
    protected function escape( string $text ): string
    {
        return static::unlink( (string) preg_replace( self::ESCAPE_PATTERN, '\\\\$1', $text ) );
    }


    /**
     * Escapes the URLs, www. domains and e-mail addresses which GitHub flavored Markdown links automatically.
     *
     * Statamic doesn't link them in Bard texts and in Markdown fields without automatic_links but Pagible
     * renders Markdown with autolinks. The escaped characters are shown as they are.
     *
     * @param string $text Markdown text
     */
    public static function unlink( string $text ): string
    {
        return (string) preg_replace(
            ['/\b(https?|ftp):(?=\/\/)/i', '/\b(www)\./i', '/(?<=[\w.+-])@(?=[\w-]+\.)/'],
            ['$1\\\\:', '$1\\\\.', '\\\\@'],
            $text
        );
    }


    /**
     * Converts the code into a fenced Markdown code block.
     *
     * The fence is longer than the longest sequence of backticks in the code.
     *
     * @param string $code Code text
     */
    protected function code( string $code ): string
    {
        preg_match_all( '/`+/', $code, $matches );
        $fence = str_repeat( '`', max( [3, ...array_map( fn( $ticks ) => strlen( $ticks ) + 1, $matches[0] )] ) );

        return $fence . "\n" . $code . "\n" . $fence . "\n\n";
    }


    /**
     * Converts the child nodes of a Bard node into Markdown.
     *
     * Marks of adjacent text nodes stay open like "**bold *italic***" instead of being closed and opened again
     * and the marks used by the following text nodes the longest are opened first. Spaces at the start and
     * end of marked text are moved outside the marks because Markdown doesn't support them there.
     *
     * @param array<mixed> $node Bard node
     */
    protected function inline( array $node ): string
    {
        $children = array_values( array_filter( (array) ( $node['content'] ?? [] ), 'is_array' ) );
        $marks = array_map( fn( $child ) => ( $child['type'] ?? '' ) === 'text' ? $this->tags( $child ) : [], $children );
        /** @var array<int, array{string, string}> $open Opening and closing tags of the open marks by position */
        $open = [];
        $keys = [];
        $md = '';

        foreach( $children as $idx => $child )
        {
            if( ( $child['type'] ?? '' ) !== 'text' )
            {
                $md = $this->close( $md, $open, $keys, 0 );
                $md .= $this->markdown( $child );
                continue;
            }

            $text = $this->marks( $child );

            // marks of the previous text which aren't used by this text are closed
            $pos = 0;
            while( $pos < count( $keys ) && isset( $marks[$idx][$keys[$pos]] ) ) {
                $pos++;
            }
            $size = strlen( $md );
            $md = $this->close( $md, $open, $keys, $pos );
            // closing syntax like "**" directly followed by opening syntax like "*" would be one delimiter run
            $closed = strlen( $md ) > $size && !preg_match( '/\s$/', $md );

            if( trim( $text ) === '' ) {
                $md .= $text;
                continue;
            }

            $new = array_diff_key( $marks[$idx], array_flip( $keys ) );
            $length = [];

            foreach( array_keys( $new ) as $key )
            {
                for( $next = $idx; isset( $marks[$next][$key] ); $next++ );
                $length[$key] = $next - $idx;
            }

            arsort( $length );
            $ltrim = ltrim( $text );
            $md .= substr( $text, 0, strlen( $text ) - strlen( $ltrim ) );

            foreach( array_keys( $length ) as $key )
            {
                $tags = $closed && $ltrim === $text && preg_match( '/[*~]$/', $md ) && preg_match( '/^[*~]/', $new[$key][0] ) ? static::HTML_TAGS[$key] ?? $new[$key] : $new[$key];
                $md .= $tags[0];
                $open[] = $tags;
                $keys[] = $key;
            }

            $md .= $ltrim;
        }

        return $this->close( $md, $open, $keys, 0 );
    }


    /**
     * Converts a Bard list node into Markdown, nested lists are indented below their list items.
     *
     * @param array<mixed> $node Bard bullet or ordered list node
     * @param \Closure(int): string $marker Returns the marker of the list item at the position
     */
    protected function list( array $node, \Closure $marker ): string
    {
        $md = '';
        $idx = 0;

        foreach( (array) ( $node['content'] ?? [] ) as $item )
        {
            if( !is_array( $item ) ) {
                continue;
            }

            // the paragraphs and nested lists of the item are kept together in a tight list
            $parts = array_map( fn( $child ) => is_array( $child ) ? trim( $this->markdown( $child ) ) : '', (array) ( $item['content'] ?? [] ) );
            // tables need an empty line before, otherwise the text above becomes their header row
            $parts = array_map( fn( $part ) => str_starts_with( $part, '|' ) ? "\n" . $part : $part, array_filter( $parts, fn( $part ) => $part !== '' ) );
            $lines = explode( "\n", implode( "\n", $parts ) );
            $prefix = $marker( $idx++ );
            $indent = str_repeat( ' ', strlen( $prefix ) );

            $md .= $prefix . array_shift( $lines ) . "\n";

            foreach( $lines as $line ) {
                $md .= ( $line !== '' ? $indent . $line : '' ) . "\n";
            }
        }

        return $md . "\n";
    }


    /**
     * Closes the open marks from the position on and moves the spaces at the end of the text outside the marks.
     *
     * @param string $md Markdown text
     * @param array<int, array{string, string}> $open Opening and closing tags of the open marks, updated
     * @param array<int, string> $keys Keys of the open marks, updated
     * @param int $pos Position of the first mark which is closed
     * @return string Markdown text with the closed marks
     */
    protected function close( string $md, array &$open, array &$keys, int $pos ): string
    {
        if( $pos >= count( $open ) ) {
            return $md;
        }

        $rtrim = rtrim( $md, " \t" );
        $spaces = substr( $md, strlen( $rtrim ) );

        foreach( array_reverse( array_slice( $open, $pos ) ) as $tags ) {
            $rtrim .= $tags[1];
        }

        $open = array_slice( $open, 0, $pos );
        $keys = array_slice( $keys, 0, $pos );

        return $rtrim . $spaces;
    }


    /**
     * Returns the text of a text node, code marks are applied and the other text is escaped.
     *
     * @param array<mixed> $node Bard text node
     */
    protected function marks( array $node ): string
    {
        $text = is_string( $node['text'] ?? null ) ? $node['text'] : '';
        $marks = array_filter( (array) ( $node['marks'] ?? [] ), 'is_array' );

        // code isn't escaped because Markdown shows its text as it is, the backticks are longer than the ones in the code
        if( array_filter( $marks, fn( $mark ) => ( $mark['type'] ?? '' ) === 'code' ) ) {
            preg_match_all( '/`+/', $text, $matches );
            $ticks = str_repeat( '`', max( [1, ...array_map( fn( $run ) => strlen( $run ) + 1, $matches[0] )] ) );
            return $ticks . ( str_starts_with( $text, '`' ) || str_ends_with( $text, '`' ) ? " {$text} " : $text ) . $ticks;
        }

        return $this->escape( $text );
    }


    /**
     * Returns the opening and closing tags of the ProseMirror marks of a text node except code marks.
     *
     * Marks without Markdown syntax like underline, small, superscript and subscript become HTML tags.
     *
     * @param array<mixed> $node Bard text node
     * @return array<string, array{string, string}> Opening and closing tags by mark key
     */
    protected function tags( array $node ): array
    {
        $tags = [];

        foreach( array_filter( (array) ( $node['marks'] ?? [] ), 'is_array' ) as $mark )
        {
            $type = $mark['type'] ?? '';
            $pair = match( $type ) {
                'bold' => ['**', '**'],
                'italic' => ['*', '*'],
                'strike' => ['~~', '~~'],
                // Markdown has no syntax for these marks, the text is converted to HTML if it contains them
                'superscript' => ['<sup>', '</sup>'],
                'subscript' => ['<sub>', '</sub>'],
                'underline' => ['<u>', '</u>'],
                'small' => ['<small>', '</small>'],
                'link' => $this->link( $mark ),
                default => null,
            };

            if( $pair !== null && is_string( $type ) ) {
                // links to different targets are different marks
                $tags[$type . ( $type === 'link' ? json_encode( $pair ) : '' )] = $pair;
            }
        }

        return $tags;
    }


    /**
     * Returns the opening and closing tags of the link or NULL if the link target can't be linked.
     *
     * Links opened in a new window or with link types like "nofollow" are HTML links because Markdown has no
     * syntax for the link target and the link types.
     *
     * @param array<mixed> $mark Link mark with the target in "href", the window in "target", the title in "title" and the link types in "rel"
     * @return array{string, string}|null Opening and closing tags
     */
    protected function link( array $mark ): ?array
    {
        $href = $mark['attrs']['href'] ?? null;

        if( !is_string( $href ) || ( $url = ( $this->link )( $href ) ) === null ) {
            return null;
        }

        $title = is_string( $title = $mark['attrs']['title'] ?? null ) ? trim( (string) preg_replace( '/\s+/', ' ', $title ) ) : '';

        $rel = is_string( $rel = $mark['attrs']['rel'] ?? null ) ? trim( (string) preg_replace( '/\s+/', ' ', strtolower( $rel ) ) ) : '';
        $blank = ( $mark['attrs']['target'] ?? null ) === '_blank';

        if( $blank || $rel !== '' )
        {
            return ['<a href="' . e( $url ) . '"' . ( $blank ? ' target="_blank"' : '' ) . ( $rel !== '' ? ' rel="' . e( $rel ) . '"' : '' )
                . ( $title !== '' ? ' title="' . e( $title ) . '"' : '' ) . '>', '</a>'];
        }

        $title = $title !== '' ? ' "' . str_replace( ['\\', '"'], ['\\\\', '\\"'], $title ) . '"' : '';

        return ['[', '](<' . str_replace( ['<', '>', ' '], ['%3C', '%3E', '%20'], $url ) . '>' . $title . ')'];
    }


    /**
     * Converts a Bard paragraph node into Markdown.
     *
     * Characters at the start of the lines which would make the text a heading, list or quote or the line
     * before a heading underlined by "=" or "-" are escaped.
     *
     * @param array<mixed> $node Bard paragraph node
     */
    protected function paragraph( array $node ): string
    {
        $md = (string) preg_replace( ['/^(\s*)([#>+=-])/m', '/^(\s*\d+)([.)])/m'], ['$1\\\\$2', '$1\\\\$2'], $this->inline( $node ) );

        return $md . "\n\n";
    }
}
