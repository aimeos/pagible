<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools\Concerns;

use Aimeos\Cms\Uploads;


/**
 * Inline base64 file uploads for the file tools.
 */
trait Upload
{
    /**
     * Passes the base64 encoded content from the validated input as upload to the callback.
     *
     * The content is decoded into a temporary upload which is removed afterwards.
     *
     * @param array<string, mixed> $input Validated input with optional "content" and "name"
     * @param \Closure $fn Callback receiving the UploadedFile or NULL if no content is given
     * @return mixed Result of the callback
     * @throws \Aimeos\Cms\Exceptions\InvalidInputException If the content isn't valid base64
     * @throws \Throwable Exceptions thrown by the callback
     */
    protected function upload( array $input, \Closure $fn ) : mixed
    {
        if( !isset( $input['content'] ) ) {
            return $fn( null );
        }

        $data = base64_decode( (string) preg_replace( '/^data:[^,]*;base64,/', '', $input['content'], 1 ), true );

        if( !$data ) {
            throw new \Aimeos\Cms\Exceptions\InvalidInputException( 'Invalid file content, must be base64 encoded' );
        }

        return Uploads::upload( $data, basename( $input['name'] ?? '' ) ?: 'file', null, $fn );
    }
}
