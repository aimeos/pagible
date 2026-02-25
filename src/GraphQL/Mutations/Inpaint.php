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


final class Inpaint
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'image:inpaint', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $upload = $args['file'];
        $upmask = $args['mask'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        if( !$upmask instanceof UploadedFile || !$upmask->isValid() ) {
            throw new Error( 'Invalid mask upload' );
        }

        $provider = config( 'cms.ai.inpaint.provider' );
        $config = config( 'cms.ai.inpaint', [] );
        $model = config( 'cms.ai.inpaint.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );
            $mask = Image::fromBinary( $upmask->getContent(), $upmask->getClientMimeType() );

            return Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'inpaint' )
                ->inpaint( $file, $mask, $args['prompt'], $config )
                ->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
