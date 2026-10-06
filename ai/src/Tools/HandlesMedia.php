<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
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
     * @param string $label Label used in the error message, e.g. "Image" or "Mask"
     * @return Image Prisma image
     * @throws \Aimeos\Cms\Exception If the file is missing or not an image
     */
    protected function image( string $id, string $label = 'Image' ) : Image
    {
        return Ai::files( [$id], Image::class )[0]
            ?? throw new \Aimeos\Cms\Exception( "$label file not found or not an image." );
    }


    /**
     * Ingests and stores a base64 encoded image as a new draft media file.
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

            if( $description ) {
                $file->description = $description;
            }

            // Store the file and generate previews outside the transaction to
            // keep slow disk and image work off the database connection.
            $file->ingest( $upload );

            return Presenter::item( Resource::addFile( $file, $user ) );
        } );
    }


    /**
     * Stores a base64 encoded image as a new draft version of an existing file.
     *
     * @param string $id UUID of the file to update
     * @param string $base64 Base64 encoded image data of the edited image
     * @param string $latestId Version ID the caller last retrieved (conflict detection)
     * @param Authenticatable|null $user Authenticated user updating the file
     * @return array<string, mixed> The updated file as array
     */
    protected function update( string $id, string $base64, string $latestId, ?Authenticatable $user ) : array
    {
        return $this->upload( $base64, 'image', function( UploadedFile $upload ) use ( $id, $latestId, $user ) {

            $file = Resource::saveFile( $id, [], $user, $latestId, $upload );
            return Presenter::saved( Presenter::file( $file ), $file );
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

        return Utils::upload( $binary, $name . '.' . $ext, $mime, $callback );
    }
}
