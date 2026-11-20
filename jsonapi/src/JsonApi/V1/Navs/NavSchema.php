<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Navs;

use Aimeos\Cms\JsonApi\V1\CmsSchema;
use Aimeos\Cms\Models\Nav;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\ID;


class NavSchema extends CmsSchema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Nav::class;

    /**
     * The resource type as it appears in URIs.
     *
     * @var string|null
     */
    protected ?string $uriType = 'pages';


    /**
     * Get the resource fields.
     *
     * @return array<int, mixed>
     */
    public function fields(): array
    {
        return [
            ID::make()->uuid(),
            Str::make( 'parent_id' )->readOnly(),
            ...array_map( fn( $name ) => Str::make( $name )->readOnly()->extractUsing(
                fn( $model, $column ) => $model->latestValue( $column, true )
            ), ['lang', 'path', 'name', 'title', 'to', 'domain'] ),
            Number::make( 'has' )->readOnly(),
            DateTime::make( 'createdAt' )->readOnly(),
            DateTime::make( 'updatedAt' )->readOnly(),
            ArrayHash::make( 'config' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                return $this->resolveFiles( $model, $this->aux( $model, 'config', $items ) );
            } ),
            HasMany::make( 'children' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
        ];
    }
}
