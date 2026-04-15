<?php

namespace Aimeos\Cms\Scout;

use Aimeos\Cms\DB;
use Aimeos\Cms\Scout as ScoutHelper;
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
        foreach( $models->groupBy( fn( $m ) => get_class( $m ) ) as $type => $group )
        {
            $this->indexQuery( $group, $type )?->delete();
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
     * Paginate the given search on the engine using database pagination.
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
        return $query->paginate( $perPage, $this->paginateColumns( $query ), $pageName, $page );
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
        return $query->simplePaginate( $perPage, $this->paginateColumns( $query ), $pageName, $page );
    }


    /**
     * Extract the string columns currently selected on the underlying query,
     * defaulting to ['*'] when no columns are set.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return list<string>
     */
    protected function paginateColumns( $query ) : array
    {
        return array_values( array_filter( $query->getQuery()->columns ?: [], 'is_string' ) ) ?: ['*'];
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

            $db->transaction( function() use ( $db, $group, $type, $tenant ) {
                $this->indexQuery( $group, $type )?->delete();

                $rows = [];

                foreach( $group as $model )
                {
                    if( !$model->shouldBeSearchable() ) {
                        continue;
                    }

                    $array = $model->toSearchableArray();
                    $common = ['indexable_id' => $model->getScoutKey(), 'indexable_type' => $type, 'tenant_id' => $tenant];

                    if( !empty( $array['draft'] ) ) {
                        $rows[] = ['latest' => true, 'content' => $array['draft']] + $common;
                    }

                    if( !empty( $array['content'] ) ) {
                        $rows[] = ['latest' => false, 'content' => $array['content']] + $common;
                    }
                }

                if( !empty( $rows ) ) {
                    $db->table( 'cms_index' )->insert( $rows );
                }
            } );
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
        $modelTable = $builder->model->getTable();
        $isDraft = false;
        $query = $builder->model->newQuery();

        // Pre-pass: detect draft mode and apply trashed scope side effects
        foreach( $builder->wheres as $key => $where )
        {
            $field = $where['field'] ?? $key;
            $value = is_array( $where ) && array_key_exists( 'value', $where ) ? $where['value'] : $where;

            if( $field === 'latest' && $value == true ) {
                $isDraft = true;
            }

            if( $field === '__soft_deleted' ) {
                $scope = \Illuminate\Database\Eloquent\SoftDeletingScope::class;

                if( $value === null ) {
                    $query->withoutGlobalScope( $scope );
                } elseif( $value == 1 ) {
                    $query->withoutGlobalScope( $scope )
                        ->whereNotNull( $builder->model->qualifyColumn( 'deleted_at' ) );
                }
            }
        }

        // Join cms_index for full-text search
        if( !empty( $builder->query ) ) {
            $this->joinSearchIndex( $query, $builder, $modelTable );
        }

        // Apply Scout builder constraints to the Eloquent query
        if( $builder->callback ) {
            call_user_func( $builder->callback, $query, $builder, $builder->query );
        } else {
            ScoutHelper::apply( $query, $builder, $isDraft );
        }

        if( !is_null( $builder->queryCallback ) ) {
            call_user_func( $builder->queryCallback, $query );
        }

        if( !empty( $builder->orders ) ) {
            $query->reorder();
            $driver = $builder->model->getConnection()->getDriverName();

            foreach( $builder->orders as $order ) {
                // In non-callback mode Scout::apply() already qualified the column.
                $col = $builder->callback
                    ? ( DB::qualify( $order['column'], $modelTable, $isDraft, $driver ) ?? $order['column'] )
                    : $order['column'];
                $query->orderBy( $col, $order['direction'] );
            }
        }

        if( !is_null( $builder->limit ) ) {
            $query->limit( (int) $builder->limit );
        }

        return $query;
    }


    /**
     * Build a query scoped to the cms_index rows for the given model group.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     * @param  \Illuminate\Support\Collection<int, T>  $group
     * @param  string  $type Model class name
     * @return \Illuminate\Database\Query\Builder|null
     */
    protected function indexQuery( $group, string $type )
    {
        $db = $group->first()?->getConnection();

        if( !$db ) {
            return null;
        }

        return $db->table( 'cms_index' )
            ->whereIn( 'indexable_id', $group->map( fn( $m ) => $m->getKey() )->all() )
            ->where( 'indexable_type', $type )
            ->where( 'tenant_id', \Aimeos\Cms\Tenancy::value() );
    }


    /**
     * Join the cms_index table for full-text search with relevance scoring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  \Laravel\Scout\Builder<\Illuminate\Database\Eloquent\Model>  $builder
     * @param  string  $modelTable
     */
    protected function joinSearchIndex( $query, Builder $builder, string $modelTable ) : void
    {
        $driver = $builder->model->getConnection()->getDriverName();
        $terms = mb_strtolower( $builder->query );

        if( !$query->getQuery()->columns ) {
            $query->select( "{$modelTable}.*" );
        }

        $query->join( 'cms_index', 'cms_index.indexable_id', '=', "{$modelTable}.id" )
            ->where( 'cms_index.indexable_type', get_class( $builder->model ) )
            ->where( 'cms_index.tenant_id', \Aimeos\Cms\Tenancy::value() );

        foreach( $builder->wheres as $key => $where )
        {
            if( ( $where['field'] ?? $key ) == 'latest' ) {
                $query->where( 'cms_index.latest', $where['operator'] ?? '=', $where['value'] ?? $where );
            }
        }

        match( $driver ) {
            'mysql', 'mariadb' => $this->searchMySQL( $query->getQuery(), $terms ),
            'pgsql' => $this->searchPostgreSQL( $query->getQuery(), $terms ),
            'sqlsrv' => $this->searchSQLServer( $query->getQuery(), $terms ),
            'sqlite' => $this->searchSQLite( $query->getQuery(), $terms ),
            default => $this->searchLike( $query->getQuery(), $terms ),
        };
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

        foreach( $words as $word ) {
            $sub->where( 'cms_index.content', 'like', '%' . $word . '%' );
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

        $boost = "IF(LOCATE(?, LEFT(cms_index.content, 500)) > 0, 1.5, 0.5)";

        $sub->whereRaw( 'MATCH(cms_index.content) AGAINST(? IN BOOLEAN MODE)', [$terms] )
            ->orderByRaw( "MATCH(cms_index.content) AGAINST(? IN BOOLEAN MODE) * {$boost} DESC", [$terms, $words[0]] );
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

        $boost = "CASE WHEN POSITION(? IN LEFT(cms_index.content, 500)) > 0 THEN 1.5 ELSE 0.5 END";

        $sub->whereRaw( "cms_index.content_vector @@ to_tsquery('simple', ?)", [$terms] )
            ->orderByRaw( "ts_rank(cms_index.content_vector, to_tsquery('simple', ?)) * {$boost} DESC", [$terms, $words[0]] );
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

        $boost = "CASE WHEN INSTR(SUBSTR(cms_index.content, 1, 500), ?) > 0 THEN 1.5 ELSE 0.5 END";

        $sub->whereRaw( 'cms_index MATCH ?', [$terms] )
            ->orderByRaw( "-cms_index.rank * {$boost} DESC", [$words[0]] );
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

        $boost = "CASE WHEN CHARINDEX(?, LEFT(cms_index.content, 500)) > 0 THEN 1.5 ELSE 0.5 END";

        $sub->join( $sub->raw( 'CONTAINSTABLE(cms_index, content, ?) AS ct' ), $sub->raw( 'cms_index.id' ), '=', $sub->raw( 'ct.[KEY]' ) )
            ->addBinding( [$terms], 'join' )
            ->orderByRaw( "ct.[RANK] * {$boost} DESC", [$words[0]] );
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
