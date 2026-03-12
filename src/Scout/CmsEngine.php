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
     * @param  array<string, mixed>  $options
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
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Aimeos\Cms\Models\Element|\Aimeos\Cms\Models\File|\Aimeos\Cms\Models\Page>  $models
     * @return void
     */
    public function delete( $models )
    {
        $tenant = \Aimeos\Cms\Tenancy::value();

        foreach( $models->groupBy( fn( $m ) => get_class( $m ) ) as $type => $group )
        {
            $db = $group->first()?->getConnection();

            if( !$db ) {
                continue;
            }

            $db->table( 'cms_index' )
                ->whereIn( 'indexable_id', $group->map( fn( $m ) => $m->getScoutKey() )->all() )
                ->where( 'indexable_type', $type )
                ->where( 'tenant_id', $tenant )
                ->delete();
        }
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
            ->where( 'indexable_type', get_class( $model ) )
            ->where( 'tenant_id', \Aimeos\Cms\Tenancy::value() )
            ->delete();
    }


    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Support\LazyCollection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function lazyMap( Builder $builder, $results, $model )
    {
        return new LazyCollection( $results['results']?->all() );
    }


    /**
     * Map the given results to instances of the given model.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  mixed  $results
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @return \Illuminate\Database\Eloquent\Collection<int, \Illuminate\Database\Eloquent\Model>
     */
    public function map( Builder $builder, $results, $model )
    {
        return $results['results'];
    }


    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param  mixed  $results
     * @return \Illuminate\Support\Collection<int, mixed>
     */
    public function mapIds( $results )
    {
        /** @var list<mixed> $keys */
        $keys = $results['results']?->modelKeys() ?? [];
        return new \Illuminate\Support\Collection( $keys );
    }


    /**
     * Paginate the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  int  $perPage
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>
     */
    public function paginate( Builder $builder, $perPage, $page )
    {
        return $this->paginateUsingDatabase( $builder, $perPage, 'page', $page );
    }


    /**
     * Paginate the given search on the engine using simple pagination.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \Illuminate\Database\Eloquent\Model>
     */
    public function paginateUsingDatabase( Builder $builder, $perPage, $pageName, $page )
    {
        $query = $this->buildSearchQuery( $builder );
        /** @var array<int, string> $columns */
        $columns = $query->getQuery()->columns ?: ['*'];
        return $query->paginate( $perPage, $columns, $pageName, $page );
    }


    /**
     * Perform the given search on the engine.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return mixed
     */
    public function search( Builder $builder )
    {
        $models = $this->buildSearchQuery( $builder )->get();

        return [
            'results' => $models,
            'total' => $models->count(),
        ];
    }


    /**
     * Paginate the given query into a simple paginator.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  int  $perPage
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\Paginator<int, \Illuminate\Database\Eloquent\Model>
     */
    public function simplePaginateUsingDatabase( Builder $builder, $perPage, $pageName, $page )
    {
        $query = $this->buildSearchQuery( $builder );
        /** @var array<int, string> $columns */
        $columns = $query->getQuery()->columns ?: ['*'];
        return $query->simplePaginate( $perPage, $columns, $pageName, $page );
    }


    /**
     * Update the given model in the index.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \Aimeos\Cms\Models\Element|\Aimeos\Cms\Models\File|\Aimeos\Cms\Models\Page>  $models
     * @return void
     */
    public function update( $models )
    {
        $tenant = \Aimeos\Cms\Tenancy::value();

        foreach( $models->groupBy( fn( $m ) => get_class( $m ) ) as $type => $group )
        {
            $db = $group->first()?->getConnection();

            if( !$db ) {
                continue;
            }

            $db->table( 'cms_index' )
                ->whereIn( 'indexable_id', $group->map( fn( $m ) => $m->getScoutKey() )->all() )
                ->where( 'indexable_type', $type )
                ->where( 'tenant_id', $tenant )
                ->delete();

            $rows = [];

            foreach( $group as $model )
            {
                if( !$model->shouldBeSearchable() ) {
                    continue;
                }

                $common = ['indexable_id' => $model->getScoutKey(), 'indexable_type' => $type, 'tenant_id' => $tenant];

                foreach( $model->toSearchableArray() as $row ) {
                    $rows[] = $row + $common;
                }
            }

            if( !empty( $rows ) ) {
                $db->table( 'cms_index' )->insert( $rows );
            }
        }
    }


    /**
     * Build the search query for the given Scout builder.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
     */
    protected function buildSearchQuery( Builder $builder )
    {
        return $this->initializeSearchQuery( $builder )
            ->when( !is_null( $builder->callback ), function( $query ) use ( $builder ) {
                /** @var callable $cb */
                $cb = $builder->callback;
                call_user_func( $cb, $query, $builder, $builder->query );
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
                /** @var callable $cb */
                $cb = $builder->queryCallback;
                call_user_func( $cb, $query );
            })
            ->when( !empty( $builder->orders ), function ( $query ) use ( $builder ) {
                $query->reorder();

                foreach( $builder->orders as $order ) {
                    $query->orderBy( $order['column'], $order['direction'] );
                }
            })
            ->when( !is_null( $builder->limit ), function ( $query ) use ( $builder ) {
                $query->limit( (int) $builder->limit );
            });
    }


    /**
     * Initialize the search query by joining with the cms_index table.
     *
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @return \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>
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

        match( $driver)  {
            'mysql', 'mariadb' => $this->searchMySQL( $sub, $builder->query ),
            'pgsql' => $this->searchPostgreSQL( $sub, $builder->query ),
            'sqlsrv' => $this->searchSQLServer( $sub, $builder->query ),
            'sqlite' => $this->searchSQLite( $sub, $builder->query ),
            default => $this->searchLike( $sub, $builder->query ),
        };

        $sub->where( 'indexable_type', get_class( $builder->model ) );

        $query->joinSub( $sub, 'index', function( $join ) use ( $modelTable ) {
            $join->on( 'index.indexable_id', '=', "{$modelTable}.id" );
        });

        return $query;
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

        $sub->select( 'indexable_id', 'latest' );

        foreach( $words as $word ) {
            $sub->where( 'content', 'like', '%' . $word . '%' );
        }
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
        $select = 'indexable_id, latest, MATCH(content) AGAINST(? IN BOOLEAN MODE) AS relevance';

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
        $select = "indexable_id, latest, ts_rank(to_tsvector('simple', coalesce(content, '')), to_tsquery('simple', ?)) AS relevance";

        $sub->selectRaw( $select, [$terms] )
            ->whereRaw( "to_tsvector('simple', coalesce(content, '')) @@ to_tsquery('simple', ?)", [$terms] );
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

        $terms = implode( ' AND ', array_map( fn( $w ) => '"' . $w . '"*', $words ) );

        $sub->selectRaw( 'indexable_id, latest, -rank AS relevance' )
            ->whereRaw( 'cms_index MATCH ?', [$terms] );
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

        $sub->selectRaw( 'cms_index.indexable_id, cms_index.latest, ct.[RANK] AS relevance' )
            ->join( $sub->raw( 'CONTAINSTABLE(cms_index, content, ?) AS ct' ), $sub->raw( 'cms_index.id' ), '=', $sub->raw( 'ct.[KEY]' ) )
            ->addBinding( [$terms], 'join' );
    }


    /**
     * Split search string into sanitized words.
     *
     * @param  string  $search
     * @param  string|null  $regex Characters to strip
     * @return list<string>
     */
    protected function words( string $search, ?string $regex = null ): array
    {
        $words = preg_split( '/\s+/', trim( $search ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];

        if( $regex ) {
            $words = array_map( fn( $w ) => (string) preg_replace( $regex, '', $w ), $words );
            $words = array_filter( $words, fn( $w ) => $w !== '' );
        }

        return array_values( $words );
    }
}
