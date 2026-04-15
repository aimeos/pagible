<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Laravel\Scout\Builder;


/**
 * Scout search builder support.
 */
class Scout
{
    /**
     * Per-model structural columns that always live on the model table.
     *
     * @var array<string, list<string>>
     */
    public const MODEL_COLUMNS = [
        'cms_pages' => ['id', 'parent_id', '_lft', '_rgt', 'tenant_id'],
        'cms_elements' => ['id', 'tenant_id', 'type', 'name'],
        'cms_files' => ['id', 'tenant_id', 'name', 'mime', 'path'],
    ];

    /**
     * Builder fields handled out-of-band; never translated to SQL columns.
     */
    public const SKIP_FIELDS = ['latest', '__soft_deleted', 'tenant_id'];


    /**
     * Apply draft-mode filters for the collection engine via callback.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string> $fields The fields passed to searchFields(); only 'draft' triggers this path
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function collection( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, array $fields ) : Builder
    {
        $isDraft = in_array( 'draft', $fields );
        static::apply( $query, $builder, $isDraft );

        return $builder;
    }


    /**
     * Apply Scout builder where/whereIn/whereNotIn filters and order qualification
     * to an Eloquent query, joining cms_versions when any referenced column lives
     * on the version table.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     */
    public static function apply( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, bool $isDraft ) : void
    {
        $table = $query->getModel()->getTable();
        $driver = $query->getModel()->getConnection()->getDriverName();
        $joined = false;

        $join = function() use ( $query, $table, &$joined ) {
            if( $joined ) {
                return;
            }
            $query->select( "{$table}.*" )
                ->join( 'cms_versions', "{$table}.latest_id", '=', 'cms_versions.id' )
                ->where( 'cms_versions.tenant_id', Tenancy::value() );
            $joined = true;
        };

        foreach( $builder->wheres as $key => $where )
        {
            $field = is_array( $where ) ? ( $where['field'] ?? $key ) : $key;

            if( in_array( $field, self::SKIP_FIELDS ) ) {
                continue;
            }

            if( !( $col = static::qualify( $field, $table, $isDraft, $driver ) ) ) {
                continue;
            }

            if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                $join();
            }

            $value = is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;
            $operator = is_array( $where ) ? ( $where['operator'] ?? '=' ) : '=';

            if( is_null( $value ) ) {
                $operator === '=' ? $query->whereNull( $col ) : $query->whereNotNull( $col );
            } else {
                $query->where( $col, $operator, $value );
            }
        }

        foreach( $builder->whereIns as $field => $values ) {
            if( $col = static::qualify( $field, $table, $isDraft, $driver ) ) {
                if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                    $join();
                }
                $query->whereIn( $col, $values );
            }
        }

        foreach( $builder->whereNotIns as $field => $values ) {
            if( $col = static::qualify( $field, $table, $isDraft, $driver ) ) {
                if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                    $join();
                }
                $query->whereNotIn( $col, $values );
            }
        }

        foreach( $builder->orders as &$order )
        {
            $col = static::qualify( $order['column'], $table, $isDraft, $driver ) ?? $table . '.' . $order['column'];

            if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                $join();
            }

            $order['column'] = $col;
        }
    }


    /**
     * Qualify an unqualified field name to the correct SQL column.
     *
     * In draft mode ($isDraft=true), routes version-level fields to cms_versions.
     * In content mode ($isDraft=false), routes all fields to the model table.
     * For MySQL/MariaDB/SQL Server, uses virtual/computed column names instead of JSON paths.
     *
     * @param string $field Unqualified field name
     * @param string $table Model table name (e.g., cms_pages)
     * @param bool $isDraft Whether draft mode is active (default: true)
     * @param string $driver Database driver name (default: '')
     * @return string|null Qualified column name, or null to skip
     */
    public static function qualify( string $field, string $table, bool $isDraft = true, string $driver = '' ) : ?string
    {
        $modelCols = self::MODEL_COLUMNS[$table] ?? ['id', 'tenant_id'];

        return match( true ) {
            $field === 'byversions_count' => $field,
            in_array( $field, ['lang', 'editor'] ) => ( $isDraft ? 'cms_versions.' : $table . '.' ) . $field,
            $field === 'published' => $isDraft ? 'cms_versions.published' : null,
            in_array( $field, $modelCols ) => $table . '.' . $field,
            $isDraft && in_array( $driver, ['mysql', 'mariadb', 'sqlsrv'] ) => 'cms_versions.data_' . $field,
            $isDraft => 'cms_versions.data->' . $field,
            default => $table . '.' . $field,
        };
    }
}
