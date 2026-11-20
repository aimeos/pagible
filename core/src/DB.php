<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;


/**
 * Database helpers for transactions, column qualification and bulk inserts and updates.
 */
class DB
{
    /** @var int Maximum number of values in one IN list to stay below the parameter limits of the databases */
    public const IN_SIZE = 500;

    /** @var int Maximum number of bound parameters per statement, SQL Server allows up to 2100 */
    public const PARAMS = 2000;

    /**
     * Per-model structural columns that always live on the model table.
     *
     * @var array<string, list<string>>
     */
    private const MODEL_COLUMNS = [
        'cms_page_view' => [...Page::FIELDS, 'latest_id'],
        'cms_files' => ['id', 'tenant_id', 'latest_id', 'name', 'mime', 'path'],
        'cms_elements' => ['id', 'tenant_id', 'latest_id', 'type', 'name'],
    ];

    /**
     * Version data fields available as generated columns in MySQL/MariaDB/SQL Server.
     *
     * @var list<string>
     */
    private const GENERATED_COLUMNS = ['type', 'path', 'domain', 'theme', 'status', 'cache', 'name', 'tag', 'to'];


    /**
     * Returns the database connection of the CMS.
     */
    public static function connection() : Connection
    {
        return \Illuminate\Support\Facades\DB::connection( self::name() );
    }


    /**
     * Returns the name of the database connection used by the CMS.
     */
    public static function name() : string
    {
        return config( 'cms.db', 'sqlite' );
    }


    /**
     * Executes a callback within a database transaction using the CMS connection.
     *
     * @template T
     * @param \Closure(): T $callback The callback to execute within the transaction
     * @return T The return value of the callback
     */
    public static function transaction( \Closure $callback ) : mixed
    {
        return self::connection()->transaction( $callback, 3 );
    }


    /**
     * Executes a callback within a cache-locked database transaction for tree safety.
     *
     * @template T
     * @param \Closure(): T $callback The callback to execute within the locked transaction
     * @param int $lifetime Minimum lock lifetime in seconds for long running callbacks
     * @return T The return value of the callback
     */
    public static function lockedTransaction( \Closure $callback, int $lifetime = 0 ) : mixed
    {
        $wait = max( 1, (int) config( 'cms.lock', 30 ) );

        return Cache::lock( 'cms_pages_' . Tenancy::value(), max( $wait, $lifetime ) )
            ->block( $wait, fn() => self::transaction( $callback ) );
    }


    /**
     * Qualify an unqualified field name to the correct SQL column.
     *
     * In draft mode ($isDraft=true), routes version-level fields to cms_versions.
     * In content mode ($isDraft=false), routes all fields to the model table.
     * For MySQL/MariaDB/SQL Server, uses virtual/computed column names instead of JSON paths if available.
     *
     * @param string $field Unqualified field name
     * @param string $table Model table name (e.g., cms_page_view)
     * @param bool $isDraft Whether draft mode is active
     * @param string $driver Database driver name
     * @return string|null Qualified column name, or null to skip
     */
    public static function qualify( string $field, string $table, bool $isDraft, string $driver ) : ?string
    {
        $modelCols = self::MODEL_COLUMNS[$table] ?? ['id', 'tenant_id'];

        return match( true ) {
            $field === 'byversions_count' => $field,
            in_array( $field, ['lang', 'editor'] ) => ( $isDraft ? 'cms_versions.' : $table . '.' ) . $field,
            $field === 'published' => $isDraft ? 'cms_versions.published' : null,
            $field === 'scheduled' => $isDraft ? 'cms_versions.publish_at' : null,
            in_array( $field, $modelCols ) => $table . '.' . $field,
            $isDraft && in_array( $field, self::GENERATED_COLUMNS ) && in_array( $driver, ['mysql', 'mariadb', 'sqlsrv'] ) => 'cms_versions.data_' . $field,
            $isDraft => 'cms_versions.data->' . $field,
            default => $table . '.' . $field,
        };
    }


    /**
     * Adds the condition matching any of the values of the field.
     *
     * Fields stored differently than their values, like "scheduled" which is stored as
     * publish date of the versions, use their own conditions. NULL in the values matches
     * NULL in the column too.
     *
     * @param \Illuminate\Contracts\Database\Query\Builder $query Query builder to add the condition to
     * @param string $field Unqualified field name
     * @param string $column Qualified column of the field, see qualify()
     * @param array<mixed> $values Values to match
     * @param bool $not TRUE to match all other values
     */
    public static function whereField( \Illuminate\Contracts\Database\Query\Builder $query, string $field, string $column, array $values, bool $not = false ) : void
    {
        match( $field ) {
            'scheduled' => self::whereScheduled( $query, $column, $values, $not ),
            default => self::whereValues( $query, $column, $values, $not ),
        };
    }


    /**
     * Adds a case sensitive condition matching all values of the column starting with the prefix.
     *
     * LIKE is case insensitive in SQLite and can't use the index, GLOB is case sensitive like
     * the other databases. SQL Server also treats "[" and "]" as wildcards in LIKE, escaping
     * them is harmless for the other databases.
     *
     * @param \Illuminate\Contracts\Database\Query\Builder $query Query builder to add the condition to
     * @param literal-string $column Column name
     * @param string $prefix Literal prefix of the values
     */
    public static function wherePrefix( \Illuminate\Contracts\Database\Query\Builder $query, string $column, string $prefix ) : void
    {
        if( self::connection()->getDriverName() === 'sqlite' ) {
            $query->whereRaw( $column . ' glob ?', [strtr( $prefix, ['*' => '[*]', '?' => '[?]', '[' => '[[]'] ) . '*'] );
            return;
        }

        $query->whereRaw( $column . " like ? escape '='", [strtr( $prefix, ['=' => '==', '%' => '=%', '_' => '=_', '[' => '=[', ']' => '=]'] ) . '%'] );
    }


    /**
     * Returns the number of rows per statement which keeps the bound parameters below the limits of the databases.
     *
     * @param int $params Number of parameters per row
     * @return int<1, max> Number of rows
     */
    public static function batchSize( int $params ) : int
    {
        return max( 1, intdiv( self::PARAMS, max( 1, $params ) ) );
    }


    /**
     * Inserts the records in chunks which stay below the parameter limits of the databases.
     *
     * @param Builder $query Query builder for the table
     * @param array<int, array<string, mixed>> $records Records to insert
     */
    public static function insertRows( Builder $query, array $records ) : void
    {
        $groups = [];

        // bulk inserts require the same columns in each record
        foreach( $records as $record )
        {
            ksort( $record );
            $groups[implode( ',', array_keys( $record ) )][] = $record;
        }

        foreach( $groups as $list )
        {
            foreach( array_chunk( $list, self::batchSize( count( $list[0] ) ) ) as $chunk ) {
                ( clone $query )->insert( $chunk );
            }
        }
    }


    /**
     * Updates different values of the rows in CASE batches which stay below the parameter limits of the databases.
     *
     * @param string $table Table name
     * @param array<int, array<string, mixed>> $rows Rows with the ID and the new values of the columns
     * @param array<string> $columns Columns to update
     */
    public static function updateRows( string $table, array $rows, array $columns ) : void
    {
        if( !$rows || !$columns ) {
            return;
        }

        $db = self::connection();
        $grammar = $db->getQueryGrammar();
        $id = $grammar->wrap( 'id' );
        $size = self::batchSize( count( $columns ) * 2 + 1 );

        foreach( array_chunk( $rows, $size ) as $chunk )
        {
            $bindings = [];
            $sets = [];

            foreach( $columns as $column )
            {
                $name = $grammar->wrap( $column );
                $case = 'CASE ' . $id;

                foreach( $chunk as $row ) {
                    $case .= ' WHEN ? THEN ?';
                    $bindings[] = $row['id'];
                    $bindings[] = $row[$column];
                }

                $sets[] = $name . ' = ' . $case . ' ELSE ' . $name . ' END';
            }

            $bindings = [...$bindings, ...array_column( $chunk, 'id' )];
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '?' ) );
            $sql = 'UPDATE ' . $grammar->wrapTable( $table )
                . ' SET ' . implode( ', ', $sets )
                . ' WHERE ' . $id . ' IN (' . $placeholders . ')';

            $db->update( $sql, $bindings );
        }
    }


    /**
     * Adds the condition for the "scheduled" field which is stored as publish date of the versions.
     *
     * Versions are scheduled if they have a publish date, NULL values count as not scheduled.
     *
     * @param \Illuminate\Contracts\Database\Query\Builder $query Query builder to add the condition to
     * @param string $column Qualified publish date column
     * @param array<mixed> $values Scheduled states to match
     * @param bool $not TRUE to match all other states
     */
    private static function whereScheduled( \Illuminate\Contracts\Database\Query\Builder $query, string $column, array $values, bool $not ) : void
    {
        $states = array_unique( array_map( fn( $value ) => (bool) $value, $values ) );

        if( count( $states ) !== 1 )
        {
            // both states match all versions, an empty list none, negated vice versa
            if( ( $states === [] ) !== $not ) {
                $query->whereRaw( '1 = 0' );
            }

            return;
        }

        reset( $states ) !== $not ? $query->whereNotNull( $column ) : $query->whereNull( $column );
    }


    /**
     * Adds the condition matching any of the values in the column, including NULL values.
     *
     * Single values are compared directly, so JSON columns compare boolean values as JSON booleans.
     *
     * @param \Illuminate\Contracts\Database\Query\Builder $query Query builder to add the condition to
     * @param string $column Qualified column name
     * @param array<mixed> $values Values to match
     * @param bool $not TRUE to match all other values
     */
    private static function whereValues( \Illuminate\Contracts\Database\Query\Builder $query, string $column, array $values, bool $not ) : void
    {
        $list = array_values( array_filter( $values, fn( $value ) => $value !== null ) );
        $null = count( $list ) !== count( $values );

        if( $list === [] && $null ) {
            $not ? $query->whereNotNull( $column ) : $query->whereNull( $column );
            return;
        }

        if( $not )
        {
            count( $list ) === 1 ? $query->where( $column, '!=', $list[0] ) : $query->whereNotIn( $column, $list );
            $null && $query->whereNotNull( $column );
            return;
        }

        if( $null )
        {
            $query->where( fn( $q ) => $q->whereIn( $column, $list )->orWhereNull( $column ) );
            return;
        }

        count( $list ) === 1 ? $query->where( $column, '=', $list[0] ) : $query->whereIn( $column, $list );
    }
}
