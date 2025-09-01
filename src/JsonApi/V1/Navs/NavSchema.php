<?php

namespace Aimeos\Cms\JsonApi\V1\Navs;

use LaravelJsonApi\Eloquent\Fields\Relations\HasMany;
use LaravelJsonApi\Eloquent\Fields\ArrayHash;
use LaravelJsonApi\Eloquent\Fields\DateTime;
use LaravelJsonApi\Eloquent\Fields\Boolean;
use LaravelJsonApi\Eloquent\Fields\Number;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Fields\ID;
use LaravelJsonApi\Eloquent\Schema;
use Aimeos\Cms\Models\Nav;


class NavSchema extends Schema
{
    /**
     * Default page value if no pagination was sent by the client.
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
    public static string $model = Nav::class;

    /**
     * The resource type as it appears in URIs.
     *
     * @var string|null
     */
    protected ?string $uriType = 'pages';


    /**
     * Schema constructor.
     *
     * @param \LaravelJsonApi\Contracts\Server\Server $server
     */
    public function __construct( \LaravelJsonApi\Contracts\Server\Server $server )
    {
        parent::__construct( $server );
        $this->maxDepth = config( 'cms.jsonapi_maxdepth', 1 );
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
     * @return array
     */
    public function fields(): array
    {
        return [
            ID::make(),
            Str::make( 'parent_id' )->readOnly(),
            Str::make( 'lang' )->readOnly(),
            Str::make( 'path' )->readOnly(),
            Str::make( 'name' )->readOnly(),
            Str::make( 'title' )->readOnly(),
            Str::make( 'to' )->readOnly(),
            Str::make( 'domain' )->readOnly(),
            Boolean::make( 'has' )->readOnly(),
            DateTime::make( 'createdAt' )->readOnly(),
            DateTime::make( 'updatedAt' )->readOnly(),
            ArrayHash::make( 'config' )->readOnly()->extractUsing( function( $model, $column, $items ) {
                foreach( (array) $items as $item )
                {
                    if( !empty( $item->files ) )
                    {
                        $lang = $model->lang;
                        $lang2 = substr( $lang, 0, 2 );

                        $item->files = collect( $item->files )
                            ->map( fn( $id ) => $model->files[$id] ?? null )
                            ->filter()
                            ->pluck( null, 'id' )
                            ->each( function( $file ) use ( $lang, $lang2 ) {
                                $file->description = $file->description?->{$lang}
                                    ?? $file->description?->{$lang2}
                                    ?? null;

                                $file->transcription = $file->transcription?->{$lang}
                                    ?? $file->transcription?->{$lang2}
                                    ?? null;
                            } );
                    }
                    else
                    {
                        unset( $item->files );
                    }

                    if( !empty( $item->data?->action ) ) {
                        $item->data->action = app()->call( $item->data->action, ['model' => $model, 'item' => $item] );
                    }
                }
                return $items;
            } ),
            HasMany::make( 'children' )->type( 'navs' )->readOnly()->serializeUsing(
                static fn($relation) => $relation->withoutLinks()
            ),
        ];
    }
}
