<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Concerns;

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
