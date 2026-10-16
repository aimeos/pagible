<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Query\Filter;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Request;
use Laravel\Scout\Builder;


/**
 * Base class of the tools searching items.
 */
abstract class SearchTool extends Tool
{
    /**
     * Model class of the items
     *
     * @var class-string<Base>
     */
    protected const MODEL = Base::class;

    /**
     * Columns selected for the items
     *
     * @var array<int, string>
     */
    protected const COLUMNS = [];

    /**
     * Key of the items in the response
     */
    protected const KEY = 'items';

    /**
     * Validation rules shared by all search tools
     *
     * @var array<string, string>
     */
    protected const FILTERS = [
        'trashed' => 'string|in:without,with,only',
        'publish' => 'string|in:PUBLISHED,DRAFT,SCHEDULED',
        'editor' => 'string|max:255',
    ];

    /**
     * Validation rules of the request parameters
     *
     * @var array<string, string>
     */
    protected const RULES = self::FILTERS;


    /**
     * Applies the model specific filters.
     *
     * @param Builder<Model> $search Scout search builder
     * @param array<string, mixed> $filter Validated filter values
     * @return Builder<Model> Filtered search builder
     */
    abstract protected function filter( Builder $search, array $filter ) : Builder;


    /**
     * Returns the data of the found item.
     *
     * @param Model $item Found item
     * @return array<string, mixed> Item data
     */
    abstract protected function present( Model $item ) : array;


    /**
     * Handle the tool request.
     */
    protected function run( Request $request ) : ResponseFactory
    {
        $v = $request->validate( static::RULES );

        $search = Filter::search( static::MODEL, $v['term'] ?? '' )
            ->query( fn( $q ) => $q->select( static::COLUMNS )
            ->with( ['latest' => fn( $q ) => $q->select( Version::SELECT_COLUMNS )] ) )
            ->take( 25 );

        $result = [];

        foreach( $this->filter( $search, $v )->get() as $item ) {
            $result[] = $this->present( $item );
        }

        return Response::structured( [static::KEY => $result] );
    }


    /**
     * Returns the input schema of the shared filters.
     *
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    protected function filters( JsonSchema $schema ) : array
    {
        return [
            'trashed' => $schema->string()
                ->description('Include trashed items: "without" (default), "with" (include deleted), or "only" (only deleted).'),
            'publish' => $schema->string()
                ->description('Filter by publish status: "PUBLISHED", "DRAFT", or "SCHEDULED".'),
            'editor' => $schema->string()
                ->description('Filter by editor name.'),
        ];
    }
}
