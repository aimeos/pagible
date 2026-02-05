<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Utils;
use GraphQL\Error\Error;


final class SaveFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        if( !Permission::can( 'file:save', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $file = File::withTrashed()->findOrFail( $args['id'] );

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args, $file ) {

            $editor = Auth::user()?->name ?? request()->ip();
            $previews = $file->latest?->data?->previews ?? $file->previews;
            $path = $file->latest?->data?->path ?? $file->path;

            $copy = clone $file;
            $copy->fill( array_replace( (array) $file->latest?->data ?? [], (array) $args['input'] ?? [] ) );
            $copy->previews = $args['input']['previews'] ?? $previews;
            $copy->path = $args['input']['path'] ?? $path;
            $copy->editor = $editor;

            $upload = $args['file'] ?? null;

            if( $upload instanceof UploadedFile && $upload->isValid() ) {
                $copy->addFile( $upload );
            }

            if( $copy->path !== $path ) {
                $copy->mime = Utils::mimetype( $copy->path );
            }

            try
            {
                $preview = $args['preview'] ?? null;

                if( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) ) {
                    $copy->addPreviews( $preview );
                } elseif( $upload instanceof UploadedFile && $upload->isValid() && str_starts_with( $upload->getClientMimeType(), 'image/' ) ) {
                    $copy->addPreviews( $upload );
                } elseif( $copy->path !== $path && str_starts_with( $copy->path, 'http' ) ) {
                    $copy->addPreviews( $copy->path );
                } elseif( $preview === false ) {
                    $copy->previews = [];
                }
            }
            catch( \Throwable $t )
            {
                $copy->removePreviews();
                throw $t;
            }

            $copy->versions()->create( [
                'lang' => $copy->lang,
                'editor' => $editor,
                'data' => $copy->toArray(),
            ] );
        }, 3 );

        DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $file ) {
            $file->removeVersions();
        }, 3 );

        return $file;
    }
}
