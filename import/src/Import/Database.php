<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\DB as CmsDB;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


/**
 * Database helpers shared by the importers.
 */
class Database
{
    /**
     * Executes the query for chunks of the unique IDs and returns the rows of all chunks.
     *
     * @template TValue
     * @param array<int, int|string> $ids IDs passed to the query function
     * @param \Closure(array<int, int|string>): Collection<int, TValue> $fn Query function for a chunk of IDs
     * @return Collection<int, TValue> Rows of all chunks
     */
    public static function chunked( array $ids, \Closure $fn ): Collection
    {
        return Collection::make( array_chunk( array_values( array_unique( $ids ) ), CmsDB::IN_SIZE ) )->flatMap( fn( $chunk ) => $fn( $chunk )->all() );
    }


    /**
     * Removes the oldest records from the cache if it contains more records than the given size.
     *
     * Half of the records are kept, at least the ones added last.
     *
     * @template TKey of array-key
     * @template TValue
     * @param array<TKey, TValue> $cache Cached records in the order they were added
     * @param int $size Maximum number of cached records
     * @param int $added Number of records added last which are kept
     * @return array<TKey, TValue> Remaining records
     */
    public static function limit( array $cache, int $size, int $added = 1 ): array
    {
        if( count( $cache ) <= $size ) {
            return $cache;
        }

        return array_slice( $cache, -max( intdiv( $size, 2 ), $added ), null, true );
    }


    /**
     * Tests the source database connection.
     *
     * @param string $conn Database connection name
     * @return string|null Reason why the connection doesn't work or NULL if it works
     */
    public static function check( string $conn ) : ?string
    {
        try
        {
            DB::connection( $conn )->getPdo();
            return null;
        }
        catch( \Exception $e )
        {
            return $e->getMessage();
        }
    }


    /**
     * Returns the unserialized PHP value stored in the database.
     *
     * Objects aren't restored for security reasons.
     *
     * @param mixed $data Serialized PHP value
     * @return mixed Unserialized value or NULL if the data isn't a valid serialized value
     */
    public static function unserialize( mixed $data ): mixed
    {
        $value = is_string( $data ) ? @unserialize( $data, ['allowed_classes' => false] ) : null;
        return $value === false ? null : $value;
    }


    /**
     * Returns the unserialized array stored in the database.
     *
     * @param mixed $data Serialized PHP array
     * @return array<int|string, mixed> Unserialized array or an empty array if the data isn't a serialized array
     */
    public static function unserializeArray( mixed $data ): array
    {
        $value = self::unserialize( $data );
        return is_array( $value ) ? $value : [];
    }
}
