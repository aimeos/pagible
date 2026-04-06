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
     * Apply draft-mode filters for the collection engine via callback.
     *
     * Joins cms_versions and qualifies all where/whereIn/order columns.
     * Called from the searchFields('draft') macro when using the collection engine.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param array<string> $fields The fields passed to searchFields(), used to detect 'draft' and skip if not present
     * @return \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function collection( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, array $fields ) : Builder
    {
        if( !in_array( 'draft', $fields ) ) {
            return $builder;
        }

        $table = $query->getModel()->getTable();
        $driver = $query->getModel()->getConnection()->getDriverName();

        static::whereVersionExists( $query, $builder, $table, $driver );

        foreach( $builder->orders as &$order )
        {
            $order['column'] = static::qualify( $order['column'], $table, true, $driver ) ?? $table . '.' . $order['column'];
        }
        unset( $order );

        return $builder;
    }


    /**
     * Add a WHERE EXISTS subquery against cms_versions with version-level filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param string $table
     * @param string $driver
     */
    public static function whereVersionExists( $query, Builder $builder, string $table, string $driver ) : void
    {
        $sub = $query->getModel()->getConnection()->query()
            ->selectRaw( '1' )
            ->from( 'cms_versions' )
            ->whereColumn( 'cms_versions.id', '=', "{$table}.latest_id" )
            ->where( 'cms_versions.tenant_id', Tenancy::value() )
            ->limit( 1 );

        static::applyFilters( $query, $builder, $table, true, $driver, $sub );
        $query->whereExists( $sub );
    }


    /**
     * Apply Scout builder where/whereIn/whereNotIn filters, routing version-level
     * columns to the EXISTS subquery when provided.
     *
     * @param \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>|\Illuminate\Database\Query\Builder $query
     * @param \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model> $builder
     * @param string $table
     * @param bool $isDraft
     * @param string $driver
     * @param \Illuminate\Database\Query\Builder|null $versionQuery
     */
    public static function applyFilters( $query, Builder $builder, string $table, bool $isDraft, string $driver, ?\Illuminate\Database\Query\Builder $versionQuery = null ) : void
    {
        foreach( $builder->wheres as $key => $where )
        {
            $field = $where['field'] ?? $key;

            if( in_array( $field, ['latest', '__soft_deleted', 'tenant_id'] ) ) {
                continue;
            }

            if( $col = static::qualify( $field, $table, $isDraft, $driver ) )
            {
                $target = $versionQuery && str_starts_with( $col, 'cms_versions.' ) ? $versionQuery : $query;
                $value = is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;
                $operator = $where['operator'] ?? '=';

                if( is_null( $value ) ) {
                    $operator === '=' ? $target->whereNull( $col ) : $target->whereNotNull( $col );
                } else {
                    $target->where( $col, $operator, $value );
                }
            }
        }

        foreach( $builder->whereIns as $key => $values ) {
            if( $col = static::qualify( $key, $table, $isDraft, $driver ) ) {
                $target = $versionQuery && str_starts_with( $col, 'cms_versions.' ) ? $versionQuery : $query;
                $target->whereIn( $col, $values );
            }
        }

        foreach( $builder->whereNotIns as $key => $values ) {
            if( $col = static::qualify( $key, $table, $isDraft, $driver ) ) {
                $target = $versionQuery && str_starts_with( $col, 'cms_versions.' ) ? $versionQuery : $query;
                $target->whereNotIn( $col, $values );
            }
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
            in_array( $field, ['lang', 'editor'] ) => ( $isDraft ? 'cms_versions.' : $table . '.' ) . $field,
            $field === 'published' => $isDraft ? 'cms_versions.published' : null,
            in_array( $field, $modelCols ) => $table . '.' . $field,
            $isDraft && in_array( $driver, ['mysql', 'mariadb', 'sqlsrv'] ) => 'cms_versions.data_' . $field,
            $isDraft => 'cms_versions.data->' . $field,
            default => $table . '.' . $field,
        };
    }
}
