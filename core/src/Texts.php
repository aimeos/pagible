<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Collects the texts of page items for translation and writes the translations back.
 */
class Texts
{
    /** @var array<int, string> Hash keys of the collected texts */
    private array $keys = [];

    /** @var array<int, mixed> References to the collected texts */
    private array $refs = [];


    /**
     * Adds a text of an array to translate.
     *
     * @param string $key Hash key of the item the text belongs to
     * @param array<string, mixed> $data Array containing the text
     * @param string $name Key of the text in the array
     * @return self Same object for fluent calls
     */
    public function add( string $key, array &$data, string $name ) : self
    {
        if( is_string( $data[$name] ?? null ) && trim( $data[$name] ) !== '' ) {
            $this->push( $key, $data[$name] );
        }

        return $this;
    }


    /**
     * Returns the number of collected texts.
     *
     * @return int Number of texts
     */
    public function count() : int
    {
        return count( $this->refs );
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
     * Returns the collected texts.
     *
     * @return array<int, string> Texts in the order they were collected
     */
    public function texts() : array
    {
        return array_map( fn( $ref ) => (string) $ref, $this->refs );
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

        $result = array_values( $translate( $this->texts(), $to, $from, $context ) );

        if( count( $result ) !== count( $this->refs ) ) {
            throw new Exception( sprintf( 'Expected %1$d translated texts, got %2$d', count( $this->refs ), count( $result ) ) );
        }

        foreach( $result as $idx => $text ) {
            $this->refs[$idx] = (string) $text;
        }

        return true;
    }


    /**
     * Adds the texts of the data according to the schema of its type.
     *
     * Follows items nested in items and skips types and fields with "translate": false.
     *
     * @param string $key Hash key of the item the texts belong to
     * @param mixed $data Data object of the item
     * @param array<string, mixed> $schema Schema of the item type with its fields
     * @return self Same object for fluent calls
     */
    public function walk( string $key, mixed $data, array $schema ) : self
    {
        if( ( $schema['translate'] ?? true ) !== false && is_object( $data ) ) {
            $this->fields( $key, $data, (array) ( $schema['fields'] ?? [] ) );
        }

        return $this;
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
            if( !isset( $data->{$name} ) || !is_array( $field ) || ( $field['translate'] ?? true ) === false ) {
                continue;
            }

            $type = $field['type'] ?? '';

            if( in_array( $type, Sync::TEXT_TYPES, true ) && is_string( $data->{$name} ) && trim( $data->{$name} ) !== '' )
            {
                $this->push( $key, $data->{$name} );
            }
            elseif( $type === 'table' && is_array( $data->{$name} ) )
            {
                foreach( $data->{$name} as $row => $cells )
                {
                    foreach( is_array( $cells ) ? $cells : [] as $col => $cell )
                    {
                        if( is_string( $cell ) && trim( $cell ) !== '' ) {
                            $this->push( $key, $data->{$name}[$row][$col] );
                        }
                    }
                }
            }
            elseif( $type === 'items' && is_array( $data->{$name} ) )
            {
                foreach( $data->{$name} as $item )
                {
                    if( is_object( $item ) ) {
                        $this->fields( $key, $item, (array) ( $field['item'] ?? [] ) );
                    }
                }
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
