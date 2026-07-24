<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Utils;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;


/**
 * Validates AI GraphQL inputs that require runtime-aware policy checks.
 */
trait ValidatesInputs
{
    /**
     * Validates structured content size and nesting before sending it to an AI provider.
     */
    protected function content( mixed $content ) : mixed
    {
        $max = max( 1, (int) config( 'cms.ai.maxinput', 1024 * 1024 ) );
        $json = json_encode( $content );

        if( $json === false || strlen( $json ) > $max ) {
            throw new Error( sprintf( 'Content exceeds the maximum input size of %d bytes', $max ) );
        }

        $depth = max( 1, (int) config( 'cms.ai.maxdepth', 20 ) );
        $stack = [[$content, 1]];

        while( $entry = array_pop( $stack ) )
        {
            [$value, $level] = $entry;

            if( $level > $depth ) {
                throw new Error( sprintf( 'Content exceeds the maximum nesting depth of %d', $depth ) );
            }

            foreach( is_object( $value ) ? get_object_vars( $value ) : ( is_array( $value ) ? $value : [] ) as $child )
            {
                if( is_array( $child ) || is_object( $child ) ) {
                    $stack[] = [$child, $level + 1];
                }
            }
        }

        return $content;
    }


    /**
     * Validates an upload against the shared CMS policy and expected media family.
     */
    protected function upload( mixed $value, string $type, string $label = 'file' ) : UploadedFile
    {
        $name = ucfirst( $label );

        if( !$value instanceof UploadedFile || !$value->isValid() ) {
            throw new Error( sprintf( 'Invalid %s upload', $label ) );
        }

        if( !Utils::isValidUpload( $value ) ) {
            throw new Error( sprintf( '%s size exceeds the maximum of %s MB',
                $name, config( 'cms.upload.filesize', 50 ) ) );
        }

        $mime = (string) $value->getMimeType();

        if( !str_starts_with( $mime, $type . '/' ) || !Utils::isValidMimetype( $mime ) ) {
            throw new Error( sprintf( '%s type "%s" is not allowed', $name, $mime ) );
        }

        if( $type === 'image' ) {
            $this->pixels( $value, $name );
        }

        return $value;
    }


    /**
     * Rejects raster images whose decoded dimensions exceed the configured limit.
     */
    private function pixels( UploadedFile $upload, string $label ) : void
    {
        $path = $upload->getRealPath();
        $info = is_string( $path ) ? @getimagesize( $path ) : false;

        if( !$info ) {
            throw new Error( sprintf( 'Invalid %s image', strtolower( $label ) ) );
        }

        $max = max( 1, (int) config( 'cms.upload.maxpixels', 4096 * 4096 ) );
        $width = (int) $info[0];
        $height = (int) $info[1];

        if( $height < 1 || $width < 1 || $width > intdiv( $max, $height ) ) {
            throw new Error( sprintf( '%s image exceeds the maximum size of %d pixels', $label, $max ) );
        }
    }
}
