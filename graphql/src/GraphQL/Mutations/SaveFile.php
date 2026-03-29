<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


final class SaveFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        return DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $args ) {

            /** @var File $orig */
            $orig = File::withTrashed()->with( 'latest' )->findOrFail( $args['id'] );
            $previews = $orig->latest?->data->previews ?? $orig->previews;
            $path = $orig->latest?->data->path ?? $orig->path;
            $editor = Auth::user()->email ?? request()->ip();

            $file = clone $orig;
            $file->fill( array_replace( (array) $orig->latest?->data, (array) $args['input'] ) );
            $file->previews = $args['input']['previews'] ?? $previews;
            $file->path = $args['input']['path'] ?? $path;
            $file->editor = $editor;

            $upload = $args['file'] ?? null;

            if( $upload instanceof UploadedFile && $upload->isValid() )
            {
                if( !Utils::isValidUpload( $upload ) ) {
                    $msg = 'File size of %s MB exceeds the maximum of %s MB';
                    throw new Error( sprintf( $msg, round( $upload->getSize() / 1024 / 1024, 3 ), config( 'cms.graphql.filesize', 50 ) ) );
                }

                if( !Utils::isValidMimetype( (string) $upload->getMimeType() ) ) {
                    $msg = 'File type "%s" not allowed, permitted types: %s';
                    throw new Error( sprintf( $msg, $upload->getMimeType(), implode( ', ', config( 'cms.graphql.mimetypes', [] ) ) ) );
                }

                $file->addFile( $upload );
            }

            if( $file->path !== $path )
            {
                $file->mime = Utils::mimetype( $file->path );

                if( !Utils::isValidMimetype( $file->mime ) ) {
                    $msg = 'File type "%s" not allowed, permitted types: %s';
                    throw new Error( sprintf( $msg, $file->mime, implode( ', ', config( 'cms.graphql.mimetypes', [] ) ) ) );
                }
            }

            try
            {
                $preview = $args['preview'] ?? null;

                if( $preview instanceof UploadedFile && $preview->isValid() && str_starts_with( $preview->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $preview );
                } elseif( $upload instanceof UploadedFile && $upload->isValid() && str_starts_with( $upload->getClientMimeType(), 'image/' ) ) {
                    $file->addPreviews( $upload );
                } elseif( $file->path !== $path && str_starts_with( $file->path, 'http' ) && Utils::isValidUrl( $file->path ) ) {
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

            $versionId = ( new Version )->newUniqueId();
            $version = $file->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => $file->lang,
                'editor' => $editor,
                'data' => $file->toArray(),
            ] );

            $orig->forceFill( ['latest_id' => $version->id] )->save();
            $file->removeVersions();

            return $orig->setRelation( 'latest', $version );
        }, 3 );
    }
}
