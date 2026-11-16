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
    /** Empty date of MySQL databases, e.g. used by Joomla and WordPress */
    public const ZERO_DATE = '0000-00-00 00:00:00';

    /** Empty dates of MySQL databases with and without time, also with fractions of seconds like "0000-00-00 00:00:00.000" */
    public const EMPTY_DATES = [self::ZERO_DATE, '0000-00-00'];

    /** Length of the dates without fractions of seconds, e.g. "0000-00-00 00:00:00" */
    protected const DATE_LENGTH = 19;


    /**
     * Returns the first date which isn't empty, e.g. to fall back to the local date if the UTC one is missing.
     *
     * This is the only implementation used by all importers. Empty strings, values which aren't strings and
     * the empty dates are skipped, "0" isn't empty. The empty dates are compared without fractions of seconds.
     *
     * @param iterable<mixed> $dates Dates in the format "YYYY-MM-DD HH:MM:SS", values which aren't strings are empty
     * @param array<int, string> $empty Empty dates, by default the ones of MySQL with and without time
     * @return string|null First date or NULL if all are empty
     */
    public static function firstDate( iterable $dates, array $empty = self::EMPTY_DATES ): ?string
    {
        foreach( $dates as $date )
        {
            if( is_string( $date ) && $date !== '' && !in_array( substr( $date, 0, self::DATE_LENGTH ), $empty, true ) ) {
                return $date;
            }
        }

        return null;
    }


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
