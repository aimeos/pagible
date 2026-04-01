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
     */
    public static function collection( \Illuminate\Database\Eloquent\Builder $query, Builder $builder, array $fields ) : Builder
    {
        if( !in_array( 'draft', $fields ) ) {
            return $builder;
        }

        $table = $query->getModel()->getTable();
        $query->select( "{$table}.*" )
            ->join( 'cms_versions', "{$table}.latest_id", '=', 'cms_versions.id' );

        foreach( $builder->wheres as $where )
        {
            if( $where['field'] === '__soft_deleted' ) {
                continue;
            }

            if( $col = static::qualify( $where['field'], $table ) )
            {
                if( is_null( $where['value'] ) ) {
                    $where['operator'] === '=' ? $query->whereNull( $col ) : $query->whereNotNull( $col );
                } else {
                    $query->where( $col, $where['operator'], $where['value'] );
                }
            }
        }

        foreach( $builder->whereIns as $field => $values )
        {
            if( $col = static::qualify( $field, $table ) ) {
                $query->whereIn( $col, $values );
            }
        }

        foreach( $builder->whereNotIns as $field => $values )
        {
            if( $col = static::qualify( $field, $table ) ) {
                $query->whereNotIn( $col, $values );
            }
        }

        foreach( $builder->orders as $order )
        {
            $col = static::qualify( $order['column'], $table ) ?? $table . '.' . $order['column'];
            $query->orderBy( $col, $order['direction'] );
        }

        $builder->orders = [];

        return $builder;
    }


    /**
     * Qualify an unqualified field name to the correct SQL column.
     *
     * In draft mode ($isDraft=true), routes version-level fields to cms_versions.
     * In content mode ($isDraft=false), routes all fields to the model table.
     *
     * @param string $field Unqualified field name
     * @param string $table Model table name (e.g., cms_pages)
     * @param bool $isDraft Whether draft mode is active (default: true)
     * @return string|null Qualified column name, or null to skip
     */
    public static function qualify( string $field, string $table, bool $isDraft = true ) : ?string
    {
        $modelCols = self::MODEL_COLUMNS[$table] ?? ['id', 'tenant_id'];

        return match( true ) {
            in_array( $field, ['lang', 'editor'] ) => ( $isDraft ? 'cms_versions.' : $table . '.' ) . $field,
            $field === 'published' => $isDraft ? 'cms_versions.published' : null,
            in_array( $field, $modelCols ) => $table . '.' . $field,
            $isDraft => 'cms_versions.data->' . $field,
            default => $table . '.' . $field,
        };
    }
}
