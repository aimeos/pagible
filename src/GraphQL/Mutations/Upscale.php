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


final class Upscale
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

        $provider = config( 'cms.ai.upscale' ) ?: 'clipdrop';
        $config = config( 'prism.providers.' . $provider, [] );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );

            $response = Prisma::image()
                ->using( $provider, $config )
                ->model( config( 'cms.ai.upscale-model' ) )
                ->withClientOptions( ['timeout' => 60, 'connect_timeout' => 10] )
                ->upscale( $file, $args['width'] ?? 2000, $args['height'] ?? 2000 );

            return $response->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
