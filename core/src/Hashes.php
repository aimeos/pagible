<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;


/**
 * Computes the sync hashes of page variants
 *
 * Each key has a "<kind>:" prefix and each value is the crc32b hash of the
 * canonical JSON of the item, so variants can be compared item by item.
 */
class Hashes
{
    /** @var list<string> Page fields tracked for translations */
    public const PAGE_FIELDS = ['title', 'name', 'type', 'theme', 'tag', 'cache'];


    /**
     * Returns the hashes of a page variant.
     *
     * @param array<string, mixed>|object $data Page fields like title, name, type, theme, tag and cache
     * @param iterable<mixed>|object|null $content List of content elements
     * @param iterable<mixed>|object|null $meta Meta entries by type
     * @param iterable<mixed>|object|null $config Config entries by key
     * @return array<string, string> Hashes by key
     */
    public static function page( array|object $data, iterable|object|null $content = null,
        iterable|object|null $meta = null, iterable|object|null $config = null ) : array
    {
        $data = (array) $data;
        $hashes = [];
        $order = [];

        foreach( self::items( $content ) as $item )
        {
            $item = (array) $item;

            if( !isset( $item['id'] ) || !is_scalar( $item['id'] ) ) {
                continue;
            }

            $id = (string) $item['id'];
            $order[] = [$id, $item['group'] ?? null];

            unset( $item['id'], $item['group'] );
            $hashes['el:' . $id] = self::hash( $item );
        }

        $hashes['page:order'] = self::hash( $order );

        foreach( self::items( $meta ) as $key => $item ) {
            $hashes['meta:' . $key] = self::hash( $item );
        }

        foreach( self::PAGE_FIELDS as $field ) {
            $hashes['page:' . $field] = self::hash( $data[$field] ?? null );
        }

        foreach( self::items( $config ) as $key => $item ) {
            $hashes['config:' . $key] = self::hash( $item );
        }

        return $hashes;
    }


    /**
     * Returns the hashes of the published variant of the page.
     *
     * @param Page $page Page with the variant
     * @return array<string, string> Hashes by key
     */
    public static function published( Page $page ) : array
    {
        return self::page( $page->only( self::PAGE_FIELDS ), $page->content, $page->meta, $page->config );
    }


    /**
     * Returns the crc32b hash of the canonical JSON of the value.
     *
     * @param mixed $value Value to hash
     * @return string Eight hex characters
     */
    public static function hash( mixed $value ) : string
    {
        $json = json_encode( self::canonical( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
        return hash( 'crc32b', $json );
    }


    /**
     * Tests if the hashes of a variant differ from the hashes of the source.
     *
     * @param array<string, string> $source Hashes of the source variant
     * @param array<string, string> $variant Hashes of the other variant
     * @return bool TRUE if the variant isn't up to date
     */
    public static function stale( array $source, array $variant ) : bool
    {
        foreach( $source as $key => $hash )
        {
            if( ( $variant[$key] ?? null ) !== $hash ) {
                return true;
            }
        }

        // elements removed in the source
        foreach( array_keys( $variant ) as $key )
        {
            if( str_starts_with( (string) $key, 'el:' ) && !isset( $source[$key] ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Sorts the keys of objects at every level.
     *
     * @param mixed $value Value to normalize
     * @return mixed Normalized value
     */
    protected static function canonical( mixed $value ) : mixed
    {
        if( $value instanceof \JsonSerializable ) {
            $value = $value->jsonSerialize();
        }

        if( is_object( $value ) ) {
            $value = (array) $value;

            if( empty( $value ) ) {
                return new \stdClass();
            }
        } elseif( !is_array( $value ) || array_is_list( $value ) ) {
            return is_array( $value ) ? array_map( [self::class, 'canonical'], $value ) : $value;
        }

        ksort( $value, SORT_STRING );
        return (object) array_map( [self::class, 'canonical'], $value );
    }


    /**
     * Returns the entries of a list or map.
     *
     * @param iterable<mixed>|object|null $items List or map
     * @return iterable<mixed> Entries
     */
    protected static function items( iterable|object|null $items ) : iterable
    {
        if( $items === null ) {
            return [];
        }

        return is_iterable( $items ) ? $items : (array) $items;
    }
}
