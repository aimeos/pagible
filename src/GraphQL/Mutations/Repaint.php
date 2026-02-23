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


final class Repaint
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !Permission::can( 'image:repaint', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

        $upload = $args['file'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        $provider = config( 'cms.ai.repaint.provider' );
        $config = config( 'cms.ai.repaint', [] );
        $model = config( 'cms.ai.repaint.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );

            return Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'repaint' )
                ->repaint( $file, $args['prompt'], $config )
                ->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, null, $e->getTrace() );
        }
    }
}
