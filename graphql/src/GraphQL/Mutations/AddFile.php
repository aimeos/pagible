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


final class AddFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        if( empty( $args['input']['path'] ) && empty( $args['file'] ) ) {
            throw new Error( 'Either input "path" or "file" argument must be provided' );
        }

        return Utils::transaction( function() use ( $args ) {

            $editor = Utils::editor( Auth::user() );
            $versionId = ( new Version )->newUniqueId();

            $file = new File();
            $file->fill( $args['input'] ?? [] );
            $file->editor = $editor;

            if( isset( $args['file'] ) ) {
                $this->addUpload( $file, $args );
            } else {
                $this->addUrl( $file, $args );
            }

            $file->latest_id = $versionId;
            $file->save();

            $version = $file->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => $args['input']['lang'] ?? null,
                'editor' => $editor,
                'data' => [
                    'lang' => $file->lang,
                    'name' => $file->name,
                    'mime' => $file->mime,
                    'path' => $file->path,
                    'previews' => $file->previews,
                    'description' => $file->description,
                    'transcription' => $file->transcription,
                ],
            ] );

            return $file->setRelation( 'latest', $version );
        } );
    }


    /**
     * Adds the uploaded file to the file model.
     *
     * @param  File $file File model instance
     * @param  array<string, mixed> $args Arguments containing the file upload
     * @return File The updated file model instance
     */
    protected function addUpload( File $file, array $args ) : File
    {
        $upload = $args['file'] ?? null;

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        if( !Utils::isValidUpload( $upload ) ) {
            $msg = 'File size of %s MB exceeds the maximum of %s MB';
            throw new Error( sprintf( $msg, round( $upload->getSize() / 1024 / 1024, 3 ), config( 'cms.graphql.filesize', 50 ) ) );
        }

        if( !Utils::isValidMimetype( (string) $upload->getMimeType() ) ) {
            $msg = 'File type "%s" not allowed, permitted types: %s';
            throw new Error( sprintf( $msg, $upload->getMimeType(), implode( ', ', config( 'cms.graphql.mimetypes', [] ) ) ) );
        }

        $file->addFile( $upload );
        $file->mime = Utils::mimetype( (string) $file->path );
        $file->name = $file->name ?: pathinfo( $upload->getClientOriginalName(), PATHINFO_BASENAME );

        try
        {
            if( isset( $args['preview'] ) || str_starts_with( $upload->getClientMimeType(), 'image/' ) ) {
                $file->addPreviews( $args['preview'] ?? $upload );
            }
        }
        catch( \Throwable $t )
        {
            $file->removePreviews()->removeFile();
            throw $t;
        }

        return $file;
    }


    /**
     * Adds a file from a URL to the file model.
     *
     * @param  File $file File model instance
     * @param  array<string, mixed> $args Arguments containing the URL
     * @return File The updated file model instance
     */
    protected function addUrl( File $file, array $args ) : File
    {
        $url = $args['input']['path'] ?? '';

        if( !str_starts_with( $url, 'http' ) || !Utils::isValidUrl( $url ) ) {
            $msg = 'Invalid URL "%s"';
            throw new Error( sprintf( $msg, $url ) );
        }

        $file->path = $url;
        $file->mime = Utils::mimetype( $url );

        if( !Utils::isValidMimetype( $file->mime ) ) {
            $msg = 'File type "%s" not allowed, permitted types: %s';
            throw new Error( sprintf( $msg, $file->mime, implode( ', ', config( 'cms.graphql.mimetypes', [] ) ) ) );
        }
        $file->name = $file->name ?: substr( $url, 0, 255 );

        try
        {
            if( isset( $args['preview'] ) || str_starts_with( $file->mime, 'image/' ) ) {
                $file->addPreviews( $args['preview'] ?? $url );
            }
        }
        catch( \Throwable $t )
        {
            $file->removePreviews();
            throw $t;
        }

        return $file;
    }
}
