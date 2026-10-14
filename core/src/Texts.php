<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Collects the texts of page items for translation and search and writes the translations back.
 */
class Texts
{
    /** @var list<string> Field types containing texts for translation and search */
    public const TYPES = ['string', 'text', 'plaintext', 'markdown'];

    /** @var array<int, string> Hash keys of the collected texts */
    private array $keys = [];

    /** @var string Pattern matching the URLs of inline links and link reference definitions in Markdown */
    private const LINKS = '/(\]\(\s*<?|^ {0,3}\[[^\]\n]+\]:[ \t]*<?)([^\s()<>]+)/m';

    /** @var array<int, mixed> References to the collected texts */
    private array $refs = [];

    /** @var array<int, int> Indexes of the collected texts containing Markdown */
    private array $markdown = [];

    /** @var array<int, mixed> References to the collected URLs */
    private array $urls = [];


    /**
     * Returns the searchable text values from content elements.
     *
     * @param iterable<array<string, mixed>|object> $items Content elements
     * @return array<int, string> Searchable text values
     */
    public static function searchable( iterable $items ) : array
    {
        $schemas = Schema::schemas( section: 'content' );
        $result = [];

        foreach( $items as $item )
        {
            $item = (object) $item;
            $fields = (array) ( $schemas[$item->type ?? '']['fields'] ?? [] );

            foreach( (array) ( $item->data ?? [] ) as $name => $value )
            {
                if( is_string( $value ) && isset( $fields[$name] )
                    && ( $fields[$name]['searchable'] ?? true )
                    && in_array( $fields[$name]['type'], self::TYPES, true )
                ) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }


    /**
     * Adds a text of an array to translate.
     *
     * @param string $key Hash key of the item the text belongs to
     * @param array<string, mixed> $data Array containing the text
     * @param string $name Key of the text in the array
     */
    public function add( string $key, array &$data, string $name ) : void
    {
        if( is_string( $data[$name] ?? null ) && trim( $data[$name] ) !== '' ) {
            $this->push( $key, $data[$name] );
        }
    }


    /**
     * Adds an URL to rewrite.
     *
     * @param mixed $url Reference to the URL
     */
    public function url( mixed &$url ) : void
    {
        if( is_string( $url ) && trim( $url ) !== '' ) {
            $this->urls[] = &$url;
        }
    }


    /**
     * Rewrites the collected URLs and the links in the collected Markdown texts.
     *
     * @param \Closure(array<int, string>): array<string, string> $rewrite Gets the URLs and returns the new URLs by old URL
     */
    public function links( \Closure $rewrite ) : void
    {
        $urls = array_map( fn( $url ) => trim( (string) $url ), $this->urls );

        foreach( $this->markdown as $idx )
        {
            if( preg_match_all( self::LINKS, (string) $this->refs[$idx], $matches ) ) {
                array_push( $urls, ...$matches[2] );
            }
        }

        if( empty( $urls ) || empty( $map = $rewrite( array_values( array_unique( $urls ) ) ) ) ) {
            return;
        }

        foreach( $this->urls as $idx => $url ) {
            $this->urls[$idx] = $map[trim( (string) $url )] ?? $url;
        }

        foreach( $this->markdown as $idx ) {
            $this->refs[$idx] = preg_replace_callback( self::LINKS, fn( $m ) => $m[1] . ( $map[$m[2]] ?? $m[2] ), (string) $this->refs[$idx] );
        }
    }


    /**
     * Returns the hash keys of the items with texts.
     *
     * @return array<int, string> Unique hash keys
     */
    public function keys() : array
    {
        return array_values( array_unique( $this->keys ) );
    }


    /**
     * Translates the collected texts and writes the translations back.
     *
     * @param (callable(array<int, string>, string, ?string, string): array<int, string>)|null $translate Translate callback
     * @param string $to Target language code
     * @param string|null $from Source language code
     * @param string $context Context for the translation
     * @return bool TRUE if the texts were translated, FALSE if no callback was given
     */
    public function translate( ?callable $translate, string $to, ?string $from = null, string $context = '' ) : bool
    {
        if( !$translate ) {
            return false;
        }

        if( empty( $this->refs ) ) {
            return true;
        }

        $result = array_values( $translate( array_map( fn( $ref ) => (string) $ref, $this->refs ), $to, $from, $context ) );

        if( count( $result ) !== count( $this->refs ) ) {
            throw new Exception( sprintf( 'Expected %1$d translated texts, got %2$d', count( $this->refs ), count( $result ) ) );
        }

        // translations are hardly longer than their source, so excessive AI output is cut off
        foreach( $result as $idx => $text ) {
            $this->refs[$idx] = mb_substr( (string) $text, 0, 100 + 4 * mb_strlen( (string) $this->refs[$idx] ) );
        }

        return true;
    }


    /**
     * Adds the texts of the data according to the schema of its type.
     *
     * Follows items nested in items and skips types and fields with "translate": false.
     * URL fields are collected to rewrite the links to other pages.
     *
     * @param string $key Hash key of the item the texts belong to
     * @param mixed $data Data object of the item
     * @param array<string, mixed> $schema Schema of the item type with its fields
     */
    public function walk( string $key, mixed $data, array $schema ) : void
    {
        if( ( $schema['translate'] ?? true ) !== false && is_object( $data ) ) {
            $this->fields( $key, $data, (array) ( $schema['fields'] ?? [] ) );
        }
    }


    /**
     * Adds the texts of the fields.
     *
     * @param string $key Hash key of the item the texts belong to
     * @param object $data Data object with the field values
     * @param array<string, mixed> $fields Field definitions by name
     */
    protected function fields( string $key, object $data, array $fields ) : void
    {
        foreach( $fields as $name => $field )
        {
            if( !isset( $data->{$name} ) || !is_array( $field ) ) {
                continue;
            }

            $type = $field['type'] ?? '';

            if( $type === 'url' ) {
                $this->url( $data->{$name} );
            } elseif( ( $field['translate'] ?? true ) !== false ) {
                $this->field( $key, $type, $data->{$name}, $field );
            }
        }
    }


    /**
     * Collects the translatable texts of one field value.
     *
     * @param string $key Key of the item the field belongs to
     * @param string $type Field type from the schema
     * @param mixed $value Field value, texts are collected by reference
     * @param array<string, mixed> $field Field schema
     */
    private function field( string $key, string $type, mixed &$value, array $field ) : void
    {
        if( in_array( $type, self::TYPES, true ) && is_string( $value ) && trim( $value ) !== '' )
        {
            if( in_array( $type, ['text', 'markdown'], true ) ) {
                $this->markdown[] = count( $this->refs );
            }

            $this->push( $key, $value );
        }
        elseif( $type === 'table' && is_array( $value ) )
        {
            foreach( array_keys( $value ) as $row ) {
                $this->cells( $key, $value[$row] );
            }
        }
        elseif( $type === 'items' && is_array( $value ) )
        {
            foreach( $value as $item )
            {
                if( is_object( $item ) ) {
                    $this->fields( $key, $item, (array) ( $field['item'] ?? [] ) );
                }
            }
        }
    }


    /**
     * Collects the non-empty texts of one table row.
     *
     * @param string $key Key of the item the table belongs to
     * @param mixed $cells Table row, texts are collected by reference
     */
    private function cells( string $key, mixed &$cells ) : void
    {
        if( !is_array( $cells ) ) {
            return;
        }

        foreach( $cells as $col => $cell )
        {
            if( is_string( $cell ) && trim( $cell ) !== '' ) {
                $this->push( $key, $cells[$col] );
            }
        }
    }


    /**
     * Adds a reference to a text.
     *
     * @param string $key Hash key of the item the text belongs to
     * @param mixed $text Reference to the text
     */
    private function push( string $key, mixed &$text ) : void
    {
        $this->keys[] = $key;
        $this->refs[] = &$text;
    }
}
