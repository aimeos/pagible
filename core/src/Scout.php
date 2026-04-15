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

            if( !( $col = DB::qualify( $field, $table, $isDraft, $driver ) ) ) {
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
            if( $col = DB::qualify( $field, $table, $isDraft, $driver ) ) {
                if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                    $join();
                }
                $query->whereIn( $col, $values );
            }
        }

        foreach( $builder->whereNotIns as $field => $values ) {
            if( $col = DB::qualify( $field, $table, $isDraft, $driver ) ) {
                if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                    $join();
                }
                $query->whereNotIn( $col, $values );
            }
        }

        foreach( $builder->orders as &$order )
        {
            $col = DB::qualify( $order['column'], $table, $isDraft, $driver ) ?? $table . '.' . $order['column'];

            if( $isDraft && str_starts_with( $col, 'cms_versions.' ) ) {
                $join();
            }

            $order['column'] = $col;
        }
    }


}
