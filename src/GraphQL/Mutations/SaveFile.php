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

        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            $editor = Auth::user()?->name ?? request()->ip();
            $orig = File::withTrashed()->findOrFail( $args['id'] );
            $previews = $orig->latest?->data?->previews ?? $orig->previews;
            $path = $orig->latest?->data?->path ?? $orig->path;

            $file = clone $orig;
            $file->fill( array_replace( (array) $orig->latest?->data ?? [], (array) $args['input'] ?? [] ) );
            $file->previews = $args['input']['previews'] ?? $previews;
            $file->path = $args['input']['path'] ?? $path;
            $file->editor = $editor;

            $upload = $args['file'] ?? null;

            if( $upload instanceof UploadedFile && $upload->isValid() ) {
                $file->addFile( $upload );
            }

            if( $file->path !== $path ) {
                $file->mime = Utils::mimetype( $file->path );
            }

            try
            {
                $preview = $args['preview'] ?? null;

                if( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $preview );
                } elseif( $upload instanceof UploadedFile && $upload->isValid() && str_starts_with( $upload->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $upload );
                } elseif( $file->path !== $path && str_starts_with( $file->path, 'http' ) ) {
                    $file->addPreviews( $file->path );
                } elseif( $preview === false ) {
                    $file->previews = [];
                }
            }
            catch( \Throwable $t )
            {
                $file->removePreviews();
                throw $t;
            }

            $file->versions()->create( [
                'lang' => $file->lang,
                'editor' => $editor,
                'data' => $file->toArray(),
            ] );

            $file->removeVersions();

            return $orig;
        }, 3 );
    }
}
