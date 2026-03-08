<?php

namespace Aimeos\Cms\Scout;

use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Laravel\Scout\Contracts\PaginatesEloquentModelsUsingDatabase;


class CmsEngine extends Engine implements PaginatesEloquentModelsUsingDatabase
{
    /**
     * Create a search index.
     *
     * @param  string  $name
     * @param  array  $options
     * @return mixed
     */
    public function createIndex( $name, array $options = [] )
    {
    }

    /**
     * Delete a search index.
     *
     * @param  string  $name
     * @return mixed
     */
    public function deleteIndex( $name )
    {
    }


    /**
     * Remove the given model from the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function delete( $models )
    {
        $models->first()->getConnection()->table( 'cms_index' )
            ->whereIn( 'page_id', $models->map->getScoutKey()->all() )
            ->delete();
    }


    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param  mixed  $results
     * @return int
     */
    public function getTotalCount( $results )
    {
        return $results['total'];
    }


    /**
     * Flush all of the model's records from the engine.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return void
     */
    public function flush( $model )
    {
        $model->getConnection()->table( 'cms_index' )
            ->where( 'tenant_id', \Aimeos\Cms\Tenancy::value() )
            ->delete();
    }


    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection
     */
    public function lazyMap( Builder $builder, $results, $model )
    {
        return new LazyCollection( $results['results']?->all() );
    }


    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function map( Builder $builder, $results, $model )
    {
        return $results['results'];
    }


    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection
     */
    public function mapIds( $results )
    {
        return collect( $results['results']?->modelKeys() );
    }


    /**
     * Paginate the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function paginate( Builder $builder, $perPage, $page )
    {
        return $this->paginateUsingDatabase( $builder, $perPage, 'page', $page );
    }


    /**
     * Paginate the given search on the engine using simple pagination.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function paginateUsingDatabase( Builder $builder, $perPage, $pageName, $page )
    {
        return $this->buildSearchQuery( $builder )->paginate( $perPage, ['*'], $pageName, $page );
    }


    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return mixed
     */
    public function search( Builder $builder )
    {
        $models = $this->buildSearchQuery($builder)->get();

        return [
            'results' => $models,
            'total' => $models->count(),
        ];
    }


    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator
     */
    public function simplePaginateUsingDatabase( Builder $builder, $perPage, $pageName, $page )
    {
        return $this->buildSearchQuery( $builder )->simplePaginate( $perPage, ['*'], $pageName, $page );
    }


    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $models
     * @return void
     */
    public function update( $models )
    {
        $tenant = \Aimeos\Cms\Tenancy::value();
        $db = $models->first()->getConnection();

        $db->table( 'cms_index' )
            ->whereIn( 'page_id', $models->map->getScoutKey()->all() )
            ->delete();

        $rows = [];

        foreach( $models as $model )
        {
            if( !$model->shouldBeSearchable() ) {
                continue;
            }

            $common = ['page_id' => $model->getScoutKey(), 'tenant_id' => $tenant];

            foreach( $model->toSearchableArray() as $row ) {
                $rows[] = $row + $common;
            }
        }

        if( !empty( $rows ) ) {
            $db->table( 'cms_index' )->insert( $rows );
        }
    }


    /**
     * Build the search query for the given Scout builder.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function buildSearchQuery( Builder $builder )
    {
        return $this->initializeSearchQuery( $builder )
            ->when( !is_null( $builder->callback ), function( $query ) use ( $builder ) {
                call_user_func( $builder->callback, $query, $builder, $builder->query );
            })
            ->when( !$builder->callback && !empty( $builder->wheres ), function ( $query ) use ( $builder ) {
                foreach( $builder->wheres as $field => $value ) {
                    $query->where( $field, '=', $value );
                }
            })
            ->when( !$builder->callback && !empty( $builder->whereIns ), function ( $query ) use ( $builder ) {
                foreach( $builder->whereIns as $key => $values ) {
                    $query->whereIn( $key, $values );
                }
            })
            ->when( !$builder->callback && !empty( $builder->whereNotIns ), function ( $query ) use ( $builder ) {
                foreach( $builder->whereNotIns as $key => $values ) {
                    $query->whereNotIn( $key, $values );
                }
            })
            ->when( !is_null( $builder->queryCallback ), function( $query ) use ( $builder ) {
                call_user_func( $builder->queryCallback, $query );
            })
            ->when( !empty( $builder->orders ), function ( $query ) use ( $builder ) {
                $query->reorder();

                foreach( $builder->orders as $order ) {
                    $query->orderBy( $order['column'], $order['direction'] );
                }
            });
    }


    /**
     * Initialize the search query by joining with the cms_index table.
     *
     * @param  \Laravel\Scout\Builder  $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function initializeSearchQuery( Builder $builder )
    {
        $query = $builder->model->newQuery();

        if (empty($builder->query)) {
            return $query;
        }

        $driver = $builder->model->getConnection()->getDriverName();
        $modelTable = $builder->model->getTable();

        $sub = $builder->model->getConnection()->table( 'cms_index' );

        match ($driver) {
            'mysql', 'mariadb' => $this->searchMySQL( $sub, $builder->query ),
            'pgsql' => $this->searchPostgreSQL( $sub, $builder->query ),
            'sqlsrv' => $this->searchSQLServer( $sub, $builder->query ),
            'sqlite' => $this->searchSQLite( $sub, $builder->query ),
            default => $this->searchLike( $sub, $builder->query ),
        };

        $query->joinSub( $sub, 'index', function( $join ) use ( $modelTable ) {
            $join->on( 'index.page_id', '=', "{$modelTable}.id" );
        });

        return $query;
    }


    /**
     * Fulltext prefix search for MySQL and MariaDB using MATCH AGAINST in boolean mode.
     *
     * @param  \Illuminate\Database\Query\Builder  $sub
     * @param  string  $search
     * @return void
     */
    protected function searchMySQL( $sub, string $search )
    {
        if( !( $words = $this->words( $search, '/[+\-><()~*"@]/' ) ) ) {
            return;
        }

        $terms = implode( ' ', array_map( fn( $w ) => '+' . $w . '*', $words ) );
        $select = 'page_id, MATCH(content) AGAINST(? IN BOOLEAN MODE) AS relevance';

        $sub->selectRaw( $select, [$terms] )
            ->whereRaw( 'MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$terms] );
    }


    /**
     * Fulltext prefix search for PostgreSQL using tsvector/tsquery.
     *
     * @param  \Illuminate\Database\Query\Builder  $sub
     * @param  string  $search
     * @return void
     */
    protected function searchPostgreSQL( $sub, string $search )
    {
        if( !( $words = $this->words( $search, '/[&|!():\'\\\\ ]/' ) ) ) {
            return;
        }

        $terms = implode( ' & ', array_map( fn( $w ) => $w . ':*', $words ) );
        $select = "page_id, ts_rank(to_tsvector('simple', coalesce(content, '')), to_tsquery('simple', ?)) AS relevance";

        $sub->selectRaw( $select, [$terms] )
            ->whereRaw( "to_tsvector('simple', coalesce(content, '')) @@ to_tsquery('simple', ?)", [$terms] );
    }


    /**
     * Fulltext prefix search for SQL Server using CONTAINS.
     *
     * @param  \Illuminate\Database\Query\Builder  $sub
     * @param  string  $search
     * @return void
     */
    protected function searchSQLServer( $sub, string $search )
    {
        if( !( $words = $this->words( $search, '/["\'()]/' ) ) ) {
            return;
        }

        $terms = implode( ' AND ', array_map( fn( $w ) => '"' . $w . '*"', $words ) );
        $select = 'page_id, (SELECT [RANK] FROM CONTAINSTABLE(cms_index, content, ?) WHERE [KEY] = cms_index.id) AS relevance';

        $sub->selectRaw( $select, [$terms] )
            ->whereRaw( 'CONTAINS(content, ?)', [$terms] );
    }


    /**
     * Fulltext prefix search for SQLite using FTS5.
     *
     * @param  \Illuminate\Database\Query\Builder  $sub
     * @param  string  $search
     * @return void
     */
    protected function searchSQLite( $sub, string $search )
    {
        if( !( $words = $this->words( $search, '/["\'\-\+\*\(\)\{\}\[\]\^~:]/' ) ) ) {
            return;
        }

        $terms = implode( ' AND ', array_map( fn( $w ) => '"' . $w . '" *', $words ) );

        $sub->selectRaw( 'page_id, -rank AS relevance' )
            ->whereRaw( 'cms_index MATCH ?', [$terms] );
    }


    /**
     * LIKE-based search fallback for other databases.
     *
     * @param  \Illuminate\Database\Query\Builder  $sub
     * @param  string  $search
     * @return void
     */
    protected function searchLike( $sub, string $search )
    {
        if( !( $words = $this->words( $search ) ) ) {
            return;
        }

        $sub->select( 'page_id' );

        foreach( $words as $word ) {
            $sub->where( 'content', 'like', '%' . $word . '%' );
        }
    }


    /**
     * Split search string into sanitized words.
     *
     * @param  string  $search
     * @param  string|null  $regex Characters to strip
     * @return array
     */
    protected function words( string $search, ?string $regex = null ): array
    {
        $words = preg_split( '/\s+/', trim( $search ), -1, PREG_SPLIT_NO_EMPTY );

        if( $regex ) {
            $words = array_map( fn( $w ) => preg_replace( $regex, '', $w ), $words );
            $words = array_filter( $words, fn( $w ) => $w !== '' );
        }

        return array_values( $words );
    }
}
