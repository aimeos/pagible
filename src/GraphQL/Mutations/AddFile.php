<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;


final class AddFile
{
    /**
     * @param  null  $rootValue
     * @param  array  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        if( empty( $args['input']['path'] ) && empty( $args['file'] ) ) {
            throw new Error( 'Either input "path" or "file" argument must be provided' );
        }

        $editor = Auth::user()?->name ?? request()->ip();

        $file = new File();
        $file->fill( $args['input'] ?? [] );
        $file->editor = $editor;

        if( isset( $args['file'] ) ) {
            $this->addUpload( $file, $args );
        } else {
            $this->addUrl( $file, $args );
        }

        $file->save();

        $file->versions()->create( [
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

        return $file;
    }


    /**
     * Adds the uploaded file to the file model.
     *
     * @param  File $file File model instance
     * @param  array $args Arguments containing the file upload
     * @return File The updated file model instance
     */
    protected function addUpload( File $file, array $args ) : File
    {
        $upload = $args['file'] ?? null;

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        $file->addFile( $upload );
        $file->mime = Utils::mimetype( $file->path );
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
     * @param  array $args Arguments containing the URL
     * @return File The updated file model instance
     */
    protected function addUrl( File $file, array $args ) : File
    {
        $url = $args['input']['path'] ?? '';

        if( !str_starts_with( $url, 'http' ) ) {
            throw new Error( 'Invalid URL' );
        }

        $file->path = $url;
        $file->mime = Utils::mimetype( $url );
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
