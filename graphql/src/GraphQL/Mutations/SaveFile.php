<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use GraphQL\Error\Error;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;


final class SaveFile
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ) : File
    {
        $upload = $args['file'] ?? null;

        if( $upload !== null ) {
            $this->validateUpload( $upload );
        }

        if( isset( $args['preview'] ) && $args['preview'] !== false ) {
            $this->validateUpload( $args['preview'], true );
        }

        return Resource::saveFile(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['latestId'] ?? null,
            $upload instanceof UploadedFile && $upload->isValid() ? $upload : null,
            $args['preview'] ?? null,
        );
    }


    /**
     * Validates a primary or preview upload before storage or image decoding.
     */
    protected function validateUpload( mixed $value, bool $preview = false ) : void
    {
        $label = $preview ? 'Preview' : 'File';

        if( !$value instanceof UploadedFile || !$value->isValid() ) {
            throw new Error( sprintf( 'Invalid %s upload', strtolower( $label ) ) );
        }

        if( !Utils::isValidUpload( $value ) ) {
            throw new Error( sprintf( '%s size of %s MB exceeds the maximum of %s MB',
                $label, round( $value->getSize() / 1024 / 1024, 3 ), config( 'cms.upload.filesize', 50 ) ) );
        }

        $mime = (string) $value->getMimeType();

        if( ( $preview && !str_starts_with( $mime, 'image/' ) ) || !Utils::isValidMimetype( $mime ) ) {
            throw new Error( sprintf( '%s type "%s" not allowed, permitted types: %s',
                $label, $mime, implode( ', ', config( 'cms.upload.mimetypes', [] ) ) ) );
        }
    }
}
