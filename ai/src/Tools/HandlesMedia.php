<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\File;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;


/**
 * Shared helpers for the AI media MCP tools.
 *
 * Loads stored media files as Prisma file objects, persists generated images
 * as new media files and stores edited images as new versions of the source file.
 */
trait HandlesMedia
{
    /**
     * Loads a stored image file and returns it as a Prisma image object.
     *
     * @param string $id UUID of the image file
     * @return Image|null Prisma image or NULL if the file is missing or not an image
     */
    protected function image( string $id ) : ?Image
    {
        /** @var File|null $file */
        $file = File::select( 'id', 'path', 'mime' )->find( $id );

        return $file ? $this->toImage( $file ) : null;
    }


    /**
     * Loads multiple stored image files as Prisma image objects.
     *
     * Files that don't exist or aren't images are silently skipped.
     *
     * @param array<int, string> $ids UUIDs of the image files
     * @return array<int, Image> List of Prisma images
     */
    protected function images( array $ids ) : array
    {
        if( empty( $ids ) ) {
            return [];
        }

        return File::whereIn( 'id', $ids )->select( 'id', 'path', 'mime' )->get()
            ->map( fn( File $file ) => $this->toImage( $file ) )
            ->filter()->values()->all();
    }


    /**
     * Stores a base64 encoded image as a new draft media file.
     *
     * @param string $base64 Base64 encoded image data
     * @param string $name Display name for the new file (without extension)
     * @param string|null $lang ISO language code, e.g. "en"
     * @param array<string, string>|null $description Multilingual alt text, e.g. ['en' => 'A sunset']
     * @param Authenticatable|null $user Authenticated user creating the file
     * @return array<string, mixed> The created file as array
     */
    protected function store( string $base64, string $name, ?string $lang, ?array $description, ?Authenticatable $user ) : array
    {
        return $this->upload( $base64, $name, function( UploadedFile $upload ) use ( $lang, $description, $user ) {

            $file = new File();
            $file->lang = $lang;
            $file->mime = $upload->getClientMimeType();
            $file->name = $upload->getClientOriginalName();

            if( $description ) {
                $file->description = $description;
            }

            // Store the file and generate previews outside the transaction to
            // keep slow disk and image work off the database connection.
            try {
                $file->addFile( $upload );
                $file->addPreviews( $upload );
            } catch( \Throwable $t ) {
                $file->removePreviews()->removeFile();
                throw $t;
            }

            if( !Utils::isValidMimetype( (string) $file->mime ) )
            {
                $file->removePreviews()->removeFile();
                return ['error' => sprintf( 'File type "%s" is not allowed.', $file->mime )];
            }

            $file = Resource::addFile( $file, $user );

            return [
                'id' => $file->id,
                'name' => $file->name,
                'mime' => $file->mime,
                'lang' => $file->lang,
                'path' => $file->path,
                'previews' => $file->previews,
                'description' => $file->description,
            ];
        } );
    }


    /**
     * Builds a Prisma image object from a stored file model.
     *
     * @param File $file File model with at least path and mime loaded
     * @return Image|null Prisma image or NULL if the file isn't an image
     */
    protected function toImage( File $file ) : ?Image
    {
        if( !str_starts_with( (string) $file->mime, 'image/' ) ) {
            return null;
        }

        if( str_starts_with( (string) $file->path, 'http' ) ) {
            return Image::fromUrl( (string) $file->path, $file->mime );
        }

        return Image::fromStoragePath( (string) $file->path, config( 'cms.disk', 'public' ), $file->mime );
    }


    /**
     * Stores a base64 encoded image as a new draft version of an existing file.
     *
     * @param string $id UUID of the file to update
     * @param string $base64 Base64 encoded image data of the edited image
     * @param string|null $latestId Version ID the caller last retrieved (conflict detection)
     * @param Authenticatable|null $user Authenticated user updating the file
     * @return array<string, mixed> The updated file as array
     */
    protected function update( string $id, string $base64, ?string $latestId, ?Authenticatable $user ) : array
    {
        return $this->upload( $base64, 'image', function( UploadedFile $upload ) use ( $id, $latestId, $user ) {

            $file = Resource::saveFile( $id, [], $user, $latestId, $upload );
            $data = (array) ( $file->latest->data ?? [] );

            return [
                'id' => $file->id,
                'name' => $data['name'] ?? $file->name,
                'mime' => $data['mime'] ?? $file->mime,
                'lang' => $data['lang'] ?? $file->lang,
                'path' => $data['path'] ?? $file->path,
                'previews' => $data['previews'] ?? $file->previews,
                'description' => $data['description'] ?? $file->description,
                'changed' => $file->changed,
            ];
        } );
    }


    /**
     * Decodes a base64 image into a temporary upload and passes it to the callback.
     *
     * The temporary file is removed again after the callback returns.
     *
     * @param string $base64 Base64 encoded image data
     * @param string $name Base name for the upload (without extension)
     * @param \Closure(UploadedFile): mixed $callback Receives the temporary upload
     * @return mixed Return value of the callback
     */
    protected function upload( string $base64, string $name, \Closure $callback ) : mixed
    {
        if( ( $binary = base64_decode( $base64, true ) ) === false ) {
            throw new \Aimeos\Cms\Exception( 'The AI service returned an invalid image.' );
        }

        $info = @getimagesizefromstring( $binary );
        $mime = $info['mime'] ?? 'image/png';
        $ext = match( $mime ) {
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'png',
        };

        $path = (string) tempnam( sys_get_temp_dir(), 'cms_ai_' );
        file_put_contents( $path, $binary );
        unset( $binary );

        try {
            return $callback( new UploadedFile( $path, $name . '.' . $ext, $mime, null, true ) );
        } finally {
            @unlink( $path );
        }
    }
}
