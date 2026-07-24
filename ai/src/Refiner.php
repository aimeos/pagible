<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Assembles AI refine responses into stored content elements.
 */
class Refiner
{
    /**
     * Merges an AI refine response into the existing content elements.
     *
     * Preserves existing elements by ID (minting one for new elements), restores the
     * group from the existing element or the page type's first section, and derives the
     * attached file IDs from the file references in the element data.
     *
     * @param array<mixed> $content Existing content elements
     * @param array<mixed> $response AI response with updated content elements
     * @param string|null $type Page type whose sections provide the default group
     * @return array<mixed> Updated content elements
     */
    public static function merge( array $content, array $response, ?string $type = null ) : array
    {
        $result = [];
        $map = collect( $content )->keyBy( 'id' );
        $default = Schema::section( $type );

        foreach( $response as $item )
        {
            if( !is_array( $item ) ) {
                continue;
            }

            $entry = (array) $map->pull( $item['id'] ?? null, [] );
            $entry['type'] = $item['type'] ?? ( $entry['type'] ?? 'text' );

            if( empty( $entry['id'] ) ) {
                $entry['id'] = Utils::uid();
            }

            $entry['group'] = $entry['group'] ?? $item['group'] ?? $default;

            if( $entry['type'] === 'reference' )
            {
                $refid = $item['refid'] ?? ( $entry['refid'] ?? null );

                if( $refid !== null ) {
                    $entry['refid'] = $refid;
                }

                unset( $entry['data'] );
            }
            else
            {
                $itemData = array_filter( (array) ( $item['data'] ?? [] ), fn( $v ) => $v !== null );
                $data = array_merge( (array) ( $entry['data'] ?? [] ), $itemData );
                $entry['data'] = (array) Validation::defaults( $entry['type'], $data );

                $ids = Validation::files( $entry['data'] );

                if( $ids ) {
                    $entry['files'] = $ids;
                } else {
                    unset( $entry['files'] );
                }
            }

            $result[] = $entry;
        }

        return $result;
    }
}
