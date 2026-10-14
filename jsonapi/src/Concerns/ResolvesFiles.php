<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;


trait ResolvesFiles
{
    /**
     * Returns the value from the latest version if loaded, otherwise the passed value.
     *
     * @param PageView $model The page model
     * @param string $name Name of the version aux value, e.g. "config"
     * @param mixed $items Value of the page model
     * @return mixed Value from the latest version or the passed value
     */
    protected function aux( PageView $model, string $name, mixed $items ) : mixed
    {
        return ( $version = $this->version( $model ) ) ? $version->aux->{$name} : $items;
    }


    /**
     * Resolves file references and actions for a list of items.
     *
     * @param PageView $model The page model with loaded files relation
     * @param object|array<int|string,object>|null $items The items to resolve files for
     * @param \Illuminate\Support\Collection<int, \Aimeos\Cms\Models\File>|null $lookup Optional file lookup collection
     * @return object|array<int|string,object>|null The items with resolved file references
     */
    protected function resolveFiles( PageView $model, object|array|null $items, ?\Illuminate\Support\Collection $lookup = null ) : object|array|null
    {
        $version = $this->version( $model );
        $filesById = null;
        $lang = $model->lang;
        $lang2 = substr( $lang, 0, 2 );

        foreach( (array) $items as $item )
        {
            if( !empty( $item->files ) )
            {
                $resolved = [];
                $filesById ??= $lookup ?? ( $version ? $version->files : $model->files );

                foreach( (array) $item->files as $id )
                {
                    if( $file = $filesById[$id] ?? null )
                    {
                        $file = clone $file;
                        $file->description = $file->description->{$lang} ?? $file->description->{$lang2} ?? null;
                        $file->transcription = $file->transcription->{$lang} ?? $file->transcription->{$lang2} ?? null;

                        if( $file->disk === 'private' )
                        {
                            $file->previews = collect( (array) $file->previews )
                                ->map( fn( $path, $variant ) => FileResponse::url(
                                    $model,
                                    (string) $file->id,
                                    $variant,
                                ) )
                                ->all();
                            $file->path = FileResponse::url( $model, (string) $file->id );
                        }

                        $resolved[$id] = $file;
                    }
                }
                $item->files = $resolved ?: null;
            }

            if( empty( $item->files ) ) {
                unset( $item->files );
            }

            if( $action = Schema::action( $item->type ?? '' ) ) {
                $item->data->action = app()->call( $action, ['page' => $model, 'item' => $item] );
            } elseif( isset( $item->data->action ) ) {
                unset( $item->data->action );
            }
        }

        return $items;
    }


    /**
     * Returns the latest version of the page if it has been loaded.
     *
     * @param PageView $model The page model
     * @return Version|null Latest version or NULL if not loaded
     */
    protected function version( PageView $model ) : ?Version
    {
        return $model->relationLoaded( 'latest' ) ? $model->latest : null;
    }
}
