<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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
        }

        if( isset( $args['input']['path'] ) )
        {
            $mime = Utils::mimetype( $args['input']['path'] );

            if( !Utils::isValidMimetype( $mime ) ) {
                $msg = 'File type "%s" not allowed, permitted types: %s';
                throw new Error( sprintf( $msg, $mime, implode( ', ', config( 'cms.graphql.mimetypes', [] ) ) ) );
            }
        }

        $file = Resource::saveFile(
            $args['id'],
            $args['input'] ?? [],
            Auth::user(),
            $args['latestId'] ?? null,
            $upload instanceof UploadedFile && $upload->isValid() ? $upload : null,
            $args['preview'] ?? null,
        );

        Resource::broadcast( $file, Auth::user() );

        return $file;
    }
}
