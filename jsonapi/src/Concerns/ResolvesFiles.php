<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


trait ResolvesFiles
{
    /**
     * Resolves file references and actions for a list of items.
     *
     * @param Page $model The page model with loaded files relation
     * @param object|array<int|string,object>|null $items The items to resolve files for
     * @param \Illuminate\Support\Collection<int, \Aimeos\Cms\Models\File>|null $lookup Optional file lookup collection
     * @return object|array<int|string,object>|null The items with resolved file references
     */
    protected function resolveFiles( Page $model, object|array|null $items, ?\Illuminate\Support\Collection $lookup = null ) : object|array|null
    {
        $version = $model->relationLoaded( 'latest' ) ? $model->latest : null;
        $filesById = null;
        $lang = $model->lang;
        $lang2 = substr( $lang, 0, 2 );
        $schemas = Schema::schemas( section: 'content' );

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

            $field = $schemas[$item->type ?? '']['fields']['action'] ?? [];
            $action = ( $field['type'] ?? null ) === 'hidden' ? ( $field['value'] ?? null ) : null;

            if( $action ) {
                $item->data->action = app()->call( $action, ['page' => $model, 'item' => $item] );
            } elseif( isset( $item->data->action ) ) {
                unset( $item->data->action );
            }
        }

        return $items;
    }
}
