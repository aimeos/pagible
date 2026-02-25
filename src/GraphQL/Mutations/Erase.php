<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\UploadedFile;
use GraphQL\Error\Error;


final class Erase
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'image:erase', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $upload = $args['file'];
        $filemask = $args['mask'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        if( !$filemask instanceof UploadedFile || !$filemask->isValid() ) {
            throw new Error( 'Invalid mask upload' );
        }

        $provider = config( 'cms.ai.erase.provider' );
        $config = config( 'cms.ai.erase', [] );
        $model = config( 'cms.ai.erase.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );
            $mask = Image::fromBinary( $filemask->getContent(), $filemask->getClientMimeType() );

            return Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'erase' )
                ->erase( $file, $mask, $config )
                ->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
