<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\JsonApi\V1\Pages;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use LaravelJsonApi\Eloquent\Contracts\Paginator;
use LaravelJsonApi\Eloquent\Pagination\PagePagination;
use LaravelJsonApi\Eloquent\Filters\Where;
use LaravelJsonApi\Eloquent\Filters\WhereIdIn;
use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\Relations\HasOne;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Schema;
use Aimeos\Cms\Concerns\ResolvesFiles;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Permission;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Support\Facades\Auth;


class PageSchema extends Schema
{
    use ResolvesFiles;
    /**
     * Default page value if no pagination was sent by the client.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $defaultPagination = ['number' => 1];

    /**
     * The maximum depth of include paths.
     *
     * @var int
     */
    protected int $maxDepth = 1;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = Page::class;


    /**
     * Schema constructor.
     *
     * @param \LaravelJsonApi\Contracts\Server\Server $server
     */
    public function __construct( \LaravelJsonApi\Contracts\Server\Server $server )
    {
        parent::__construct( $server );
        $this->maxDepth = config( 'cms.jsonapi.maxdepth', 1 );
    }


    /**
     * Determine if the resource is authorizable.
     *
     * @return bool
     */
    public function authorizable(): bool
    {
        return false;
    }


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
            Str::make( 'theme' )->readOnly(),
            Str::make( 'type' )->readOnly(),
            Str::make( 'to' )->readOnly(),
            Str::make( 'domain' )->readOnly(),
            Boolean::make( 'has' )->readOnly(),
            Number::make( 'cache' )->readOnly(),
            DateTime::make( 'createdAt' )->readOnly(),
            DateTime::make( 'updatedAt' )->readOnly(),
            ArrayHash::make( 'meta' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                $version = $model->relationLoaded( 'latest' ) ? $model->latest : null;
                return $this->resolveFiles( $model, $version ? $version->aux->meta : $items );
            } ),
            ArrayHash::make( 'config' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                $version = $model->relationLoaded( 'latest' ) ? $model->latest : null;
                return $this->resolveFiles( $model, $version ? $version->aux->config : $items );
            } ),
            ArrayHash::make( 'content' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                $version = $model->relationLoaded( 'latest' ) ? $model->latest : null;
                return $this->resolveContent( $model, $version ? $version->aux->content : $items );
            } ),
            HasOne::make( 'parent' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
            HasMany::make( 'ancestors' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
            HasMany::make( 'children' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
            HasMany::make( 'menu' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
            HasMany::make( 'subtree' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
        ];
    }


    /**
     * Get the resource filters.
     *
     * @return array<int, mixed>
     */
    public function filters(): array
    {
        return [
            Where::make( 'domain' )->deserializeUsing(
                fn($value) => (string) $value
            ),
            Where::make( 'path' )->deserializeUsing(
                fn($value) => (string) $value
            ),
            Where::make( 'tag' )->deserializeUsing(
                fn($value) => (string) $value
            ),
            Where::make( 'lang' )->deserializeUsing(
                fn($value) => (string) $value
            ),
            WhereIdIn::make( $this ),
        ];
    }


    /**
     * Build an index query for this resource.
     *
     * @param Request|null $request
     * @param Builder<\Aimeos\Cms\Models\Page> $query
     * @return Builder<\Aimeos\Cms\Models\Page>
     */
    public function indexQuery( ?Request $request, Builder $query ): Builder
    {
        $fields = $request?->input( 'fields.pages' );
        $needsRelations = !$fields
            || str_contains( $fields, 'content' )
            || str_contains( $fields, 'meta' )
            || str_contains( $fields, 'config' );

        if( $needsRelations ) {
            $with = [
                'files' => fn( $q ) => $q->select( File::SELECT_COLS ),
                'elements' => fn( $q ) => $q->select( Element::SELECT_COLS ),
                'elements.files' => fn( $q ) => $q->select( File::SELECT_COLS ),
            ];

            if( Permission::can( 'page:view', Auth::user() ) ) {
                $with['latest'] = fn( $q ) => $q->select( 'id', 'versionable_id', 'aux' );
                $with['latest.files'] = fn( $q ) => $q->select( File::SELECT_COLS );
                $with['latest.elements'] = fn( $q ) => $q->select( Element::SELECT_COLS );
                $with['latest.elements.files'] = fn( $q ) => $q->select( File::SELECT_COLS );
            }

            $query = $query->with( $with );
        }

        $query = $query->orderBy( NestedSet::LFT );

        if( $request && ( $filter = $request->get( 'filter' ) ) ) {
            return $query;
        }

        return $query->where( (new Page())->qualifyColumn( 'parent_id' ), null );
    }


    /**
     * Get the resource paginator.
     *
     * @return Paginator|null
     */
    public function pagination(): ?Paginator
    {
        return PagePagination::make();
    }


    /**
     * Resolves element references, file references and actions for content items.
     *
     * @param Page $model The page model with loaded relations
     * @param mixed $items The content items to resolve
     * @return array<int, mixed> The resolved content items
     */
    protected function resolveContent( Page $model, mixed $items ) : array
    {
        $version = $model->relationLoaded( 'latest' ) ? $model->latest : null;
        $elements = null;
        $allFiles = null;

        foreach( (array) $items as $item )
        {
            $elements ??= $version ? $version->elements : $model->elements;

            if( $item->type === 'reference' && $element = @$elements[@$item->refid] )
            {
                $item->type = $element->type;
                $item->data = $element->data;

                if( !$element->files->isEmpty() ) {
                    $allFiles = ( $allFiles ?? ( $version ? $version->files : $model->files ) )->merge( $element->files );
                    $item->files = $element->files->keys()->all();
                }
                unset( $item->refid );
            }

            unset( $item->group );
        }

        return array_values( (array) $this->resolveFiles( $model, $items, $allFiles ) );
    }


}
