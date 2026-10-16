<?php

/**
 * @license MIT, https://opensource.org/license/mit
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
use LaravelJsonApi\Eloquent\Fields\ArrayList;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\ID;
use Aimeos\Cms\JsonApi\V1\CmsSchema;
use Aimeos\Cms\JsonApi\V1\Filters\WhereLang;
use Aimeos\Cms\JsonApi\V1\Filters\WhereVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Support\Facades\Auth;


class PageSchema extends CmsSchema
{
    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = PageView::class;


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
            Number::make( 'has' )->readOnly(),
            Number::make( 'cache' )->readOnly(),
            DateTime::make( 'createdAt' )->readOnly(),
            DateTime::make( 'updatedAt' )->readOnly(),
            ...array_map( fn( $name ) => ArrayHash::make( $name )->readOnly()->extractUsing( function( $model, $column, $items ) use ( $name ) {
                $items = $this->aux( $model, $name, $items );

                return $name === 'content' ? $this->resolveContent( $model, $items ) : $this->resolveFiles( $model, $items );
            } ), ['meta', 'config', 'content'] ),
            ArrayList::make( 'variants' )->readOnly()->extractUsing(
                fn( $model ) => $model->variants->map( fn( $variant ) => [
                    'lang' => $variant->lang,
                    'path' => $variant->path,
                    'domain' => $variant->domain,
                    'url' => Utils::pageUrl( $variant->path, $variant->domain ),
                ] )->values()->all()
            ),
            HasOne::make( 'parent' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn( $relation ) => $relation->withoutLinks()
            ),
            ...array_map( fn( $name ) => HasMany::make( $name )->type( 'navs' )->readOnly()->serializeUsing(
                static fn( $relation ) => $relation->withoutLinks()
            ), ['ancestors', 'children', 'menu', 'subtree'] ),
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
            ...array_map( fn( $name ) => Where::make( $name )->deserializeUsing(
                fn( $value ) => (string) $value
            ), ['domain', 'tag'] ),
            WhereVariant::make( 'path' )->deserializeUsing( fn( $value ) => (string) $value ),
            // must be the last variant filter because it selects the variants of the language
            WhereLang::make( 'lang' )->deserializeUsing( fn( $value ) => (string) $value ),
            WhereIdIn::make( $this ),
        ];
    }


    /**
     * Build an index query for this resource.
     *
     * @param Request|null $request
     * @param Builder<\Aimeos\Cms\Models\PageView> $query
     * @return Builder<\Aimeos\Cms\Models\PageView>
     */
    public function indexQuery( ?Request $request, Builder $query ): Builder
    {
        $fields = $request?->input( 'fields.pages' );
        $needsRelations = !$fields
            || str_contains( $fields, 'content' )
            || str_contains( $fields, 'meta' )
            || str_contains( $fields, 'config' );

        if( $needsRelations ) {
            $with = PageView::contentRelations();

            if( Permission::can( 'page:view', Auth::user() ) ) {
                $with['latest'] = fn( $q ) => $q->select( 'id', 'tenant_id', 'versionable_id', 'aux' );
                $with += PageView::contentRelations( 'latest.' );
            }

            $query = $query->with( $with );
        }

        if( !$fields || str_contains( $fields, 'variants' ) ) {
            $query->with( 'variants' );
        }

        $query = $query->orderBy( NestedSet::LFT );

        if( $request?->get( 'filter' ) ) {
            return $query;
        }

        return $query->where( (new PageView())->qualifyColumn( 'parent_id' ), null );
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
     * @param PageView $model The page model with loaded relations
     * @param mixed $items The content items to resolve
     * @return array<int, mixed> The resolved content items
     */
    protected function resolveContent( PageView $model, mixed $items ) : array
    {
        $version = $this->version( $model );
        $elements = null;
        $allFiles = null;

        foreach( (array) $items as $item )
        {
            $elements ??= $version ? $version->elements : $model->elements;

            if( $item->type === 'reference' && ($refid = $item->refid ?? null) && $element = $elements[$refid] ?? null )
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
