<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Http\UploadedFile;
use GraphQL\Error\Error;


final class Isolate
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        $upload = $args['file'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        $provider = config( 'cms.ai.isolate.provider' );
        $config = config( 'cms.ai.isolate', [] );
        $model = config( 'cms.ai.isolate.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );

            return Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'isolate' )
                ->isolate( $file, $config )
                ->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, null, $e->getTrace() );
        }
    }
}
