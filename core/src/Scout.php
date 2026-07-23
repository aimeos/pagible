<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Jobs\IndexModels;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Laravel\Scout\ModelObserver;
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

        if( $builder->query === '' && $builder->queryCallback ) {
            call_user_func( $builder->queryCallback, $query );
        }

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


    /**
     * Reindexes models by ID in bounded native Scout batches.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs
     * @param Collection<int, Models\Base>|null $loaded Already loaded current models
     */
    public static function index( string $model, array $ids, ?Collection $loaded = null ) : void
    {
        $instance = new $model();
        $models = [];

        foreach( $loaded ?? [] as $item ) {
            if( $item instanceof $model && $item->id !== null ) {
                $models[$item->id] = $item;
            }
        }

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            if( config( 'scout.queue' ) ) {
                dispatch( ( new IndexModels( $model, $chunk, Tenancy::value() ) )
                    ->onQueue( $instance->syncWithSearchUsingQueue() )
                    ->onConnection( $instance->syncWithSearchUsing() ) );
            } elseif( count( $items = array_intersect_key( $models, array_flip( $chunk ) ) ) === count( $chunk ) ) {
                $loaded = $instance->newCollection( array_values( $items ) );
                $loaded->loadMissing( $model::makeAllSearchableQuery()->getEagerLoads() );
                $instance->syncMakeSearchable( $loaded );
            } else {
                self::sync( $model, $chunk );
            }
        }
    }


    /**
     * Executes the callback without automatic Scout model synchronization.
     *
     * Already muted model classes remain muted when nested calls return.
     *
     * @template T
     * @param array<class-string<Models\Base>> $models Model classes to mute
     * @param \Closure(): T $callback Callback to execute
     * @return T Callback return value
     */
    public static function mute( array $models, \Closure $callback ) : mixed
    {
        $instances = [];

        foreach( array_unique( $models ) as $model ) {
            $instance = new $model();

            if( !ModelObserver::syncingDisabledFor( $instance ) ) {
                $instance::disableSearchSyncing();
                $instances[] = $instance;
            }
        }

        try {
            return $callback();
        } finally {
            foreach( $instances as $instance ) {
                $instance::enableSearchSyncing();
            }
        }
    }


    /**
     * Reindexes models immediately after loading their searchable relations.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs
     */
    public static function sync( string $model, array $ids ) : void
    {
        $instance = new $model();

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk ) {
            $items = $instance::makeAllSearchableQuery()
                ->withoutGlobalScope( SoftDeletingScope::class )
                ->whereKey( $chunk )
                ->get();

            $instance->syncMakeSearchable( $items );
        }
    }


    /**
     * Removes models from Scout by ID in bounded native batches.
     *
     * @param class-string<Models\Base> $model Model class
     * @param array<string> $ids Model IDs
     */
    public static function unindex( string $model, array $ids ) : void
    {
        $instance = new $model();
        $key = $instance->getScoutKeyName();

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            $items = $instance->newCollection( array_map(
                fn( $id ) => $instance->newInstance()->forceFill( [$key => $id] ),
                $chunk,
            ) );
            $instance->queueRemoveFromSearch( $items );
        }
    }
}
