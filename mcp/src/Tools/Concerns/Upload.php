<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Tools\Concerns;

use Illuminate\Http\UploadedFile;


/**
 * Inline base64 file uploads for the file tools.
 */
trait Upload
{
    /**
     * Passes the file source from the validated input to the callback.
     *
     * Base64 encoded content is decoded into a temporary upload which is removed afterwards.
     *
     * @param array<string, mixed> $input Validated input with optional "content", "url" and "name"
     * @param \Closure $fn Callback receiving the UploadedFile, URL or NULL if no source is given
     * @return mixed Result of the callback
     * @throws \Aimeos\Cms\InvalidException If the content isn't valid base64
     * @throws \Throwable Exceptions thrown by the callback
     */
    protected function source( array $input, \Closure $fn ) : mixed
    {
        if( !isset( $input['content'] ) ) {
            return $fn( $input['url'] ?? null );
        }

        $data = base64_decode( (string) preg_replace( '/^data:[^,]*;base64,/', '', $input['content'], 1 ), true );

        if( !$data ) {
            throw new \Aimeos\Cms\InvalidException( 'Invalid file content, must be base64 encoded' );
        }

        if( ( $path = tempnam( sys_get_temp_dir(), 'cms' ) ) === false || file_put_contents( $path, $data ) === false ) {
            throw new \Aimeos\Cms\Exception( 'Unable to create temporary file' );
        }

        unset( $data );

        try {
            return $fn( new UploadedFile( $path, basename( $input['name'] ?? '' ) ?: 'file', null, null, true ) );
        } finally {
            @unlink( $path );
        }
    }
}
