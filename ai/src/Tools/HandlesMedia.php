<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Uploads;
use Aimeos\Prisma\Files\Image;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;


/**
 * Shared helpers for the AI media MCP tools.
 *
 * Loads stored media files as Prisma file objects, decodes generated images
 * into temporary uploads and stores edited images as new versions of the source file.
 */
trait HandlesMedia
{
    /**
     * Validates the request, edits the image and stores it as new version of the file.
     *
     * @param Request $request Tool request with the "file" and "latest_id" parameters
     * @param array<string, string> $rules Validation rules of the additional parameters
     * @param array<string, string> $messages Validation messages incl. the one for "file.required"
     * @param \Closure(Image, array<string, mixed>): string $edit Receives the image and the validated parameters, returns the base64 encoded result
     * @return ResponseFactory The updated file
     */
    protected function edit( Request $request, array $rules, array $messages, \Closure $edit ) : ResponseFactory
    {
        $v = $request->validate( ['file' => 'required|string|max:36'] + $rules + ['latest_id' => 'required|string|max:36'], $messages + [
            'latest_id.required' => 'You must pass the latest_id returned by get-file, add-file, or a previous save-file so concurrent edits are detected.',
        ] );

        $base64 = $edit( $this->image( $v['file'], $request->user() ), $v );

        $result = $this->upload( $base64, 'image', function( UploadedFile $upload ) use ( $v, $request ) {

            $file = Files::save( $v['file'], [], $request->user(), $v['latest_id'], $upload );
            return Presenter::saved( Presenter::file( $file ), $file );
        } );

        return Response::structured( $result );
    }


    /**
     * Returns the input schema of the "latest_id" parameter.
     *
     * @return \Illuminate\JsonSchema\Types\Type
     */
    protected function latest( JsonSchema $schema ) : \Illuminate\JsonSchema\Types\Type
    {
        return $schema->string()
            ->description( 'Required. The latest_id value returned by get-file, add-file, or your previous save-file for this file. Ensures edits made by another editor in the meantime are merged instead of overwritten.' )
            ->required();
    }


    /**
     * Loads a stored image file and returns it as a Prisma image object.
     *
     * @param string $id UUID of the image file
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user User loading the file
     * @param string $label Label used in the error message, e.g. "Image" or "Mask"
     * @return Image Prisma image
     * @throws \Aimeos\Cms\Exception If the file is missing or not an image
     */
    protected function image( string $id, ?\Illuminate\Contracts\Auth\Authenticatable $user, string $label = 'Image' ) : Image
    {
        return Ai::files( [$id], $user, Image::class )[0]
            ?? throw new \Aimeos\Cms\Exception( "$label file not found or not an image." );
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

        return Uploads::upload( $binary, $name . '.' . $ext, $mime, $callback );
    }
}
