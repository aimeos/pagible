<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\GraphQL\Resolvers;

use Aimeos\Cms\Access;


/**
 * Resolves frontend access catalog operations.
 */
final class AccessResolver
{
    /**
     * Adds an access value.
     *
     * @param array{value: string} $args
     * @return array<int, string>
     */
    public function add( mixed $root, array $args ) : array
    {
        return app( Access::class )->add( (string) ( $args['value'] ?? '' ) );
    }


    /**
     * Returns the complete catalog or a bounded search result.
     *
     * @param array<string, mixed> $args
     * @return array<int, string>
     */
    public function catalog( mixed $root, array $args ) : array
    {
        $access = app( Access::class );

        if( !array_key_exists( 'term', $args ) && !array_key_exists( 'first', $args ) ) {
            return $access->list();
        }

        return $access->search( (string) ( $args['term'] ?? null ), (int) ( $args['first'] ?? 50 ) );
    }


    /**
     * Deletes access values.
     *
     * @param array{values: array<int, mixed>} $args
     * @return array<int, string>
     */
    public function delete( mixed $root, array $args ) : array
    {
        return app( Access::class )->delete( $args['values'] ?? [] );
    }
}
