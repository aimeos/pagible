<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\JsonApi\V1\Navs;

use Aimeos\Cms\JsonApi\V1\CmsSchema;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            Str::make( 'lang' )->readOnly(),
            Str::make( 'path' )->readOnly(),
            Str::make( 'name' )->readOnly(),
            Str::make( 'title' )->readOnly(),
            Str::make( 'to' )->readOnly(),
            Str::make( 'domain' )->readOnly(),
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


    /**
     * Build an index query for this resource.
     *
     * @param Request|null $request
     * @param Builder<\Aimeos\Cms\Models\Nav> $query
     * @return Builder<\Aimeos\Cms\Models\Nav>
     */
    public function indexQuery( ?Request $request, Builder $query ): Builder
    {
        if( Permission::can( 'page:view', Auth::user() ) ) {
            $query = $query->with( [
                'files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
                'latest' => fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'aux' ),
                'latest.files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
            ] );
        }

        return $query;
    }
}
