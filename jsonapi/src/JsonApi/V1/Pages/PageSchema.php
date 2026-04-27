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
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Nav;
use Aimeos\Nestedset\NestedSet;


class PageSchema extends Schema
{
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
                return $this->resolveFiles( $model, $items );
            } ),
            ArrayHash::make( 'config' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                return $this->resolveFiles( $model, $items );
            } ),
            ArrayHash::make( 'content' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                return $this->resolveContent( $model, $items );
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
            $query = $query->with( [
                'files' => fn( $q ) => $q->select( 'cms_files.id', 'name', 'mime', 'path', 'previews', 'description', 'transcription' ),
                'elements' => fn( $q ) => $q->select( 'cms_elements.id', 'type', 'data' ),
                'elements.files' => fn( $q ) => $q->select( 'cms_files.id', 'name', 'mime', 'path', 'previews', 'description', 'transcription' ),
            ] );
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
        $allFiles = null;

        foreach( (array) $items as $item )
        {
            if( $item->type === 'reference' && $element = @$model->elements[@$item->refid] )
            {
                $item->type = $element->type;
                $item->data = $element->data;

                if( !$element->files->isEmpty() ) {
                    $allFiles = ( $allFiles ?? $model->files )->merge( $element->files );
                    $item->files = $element->files->keys()->all();
                }
                unset( $item->refid );
            }

            unset( $item->group );
        }

        return array_values( (array) $this->resolveFiles( $model, $items, $allFiles ) );
    }


    /**
     * Resolves file references and actions for a list of items.
     *
     * @param Page $model The page model with loaded files relation
     * @param mixed $items The items to resolve files for
     * @param \Illuminate\Database\Eloquent\Collection<int, \Aimeos\Cms\Models\File>|null $lookup Optional file lookup collection
     * @return mixed The items with resolved file references
     */
    protected function resolveFiles( Page $model, mixed $items, ?\Illuminate\Support\Collection $lookup = null ) : mixed
    {
        $filesById = null;
        $lang = $model->lang;
        $lang2 = substr( $lang, 0, 2 );

        foreach( (array) $items as $item )
        {
            if( !empty( $item->files ) )
            {
                $resolved = [];
                $filesById ??= $lookup ?? $model->files;

                foreach( (array) $item->files as $id )
                {
                    if( $file = $filesById[$id] ?? null )
                    {
                        $file->description = $file->description->{$lang} ?? $file->description->{$lang2} ?? null;
                        $file->transcription = $file->transcription->{$lang} ?? $file->transcription->{$lang2} ?? null;
                        $resolved[$id] = $file;
                    }
                }
                $item->files = $resolved ?: null;
            }

            if( empty( $item->files ) ) {
                unset( $item->files );
            }

            if( !empty( $item->data->action ) ) {
                $item->data->action = app()->call( $item->data->action, ['model' => $model, 'item' => $item] );
            }
        }

        return $items;
    }
}
