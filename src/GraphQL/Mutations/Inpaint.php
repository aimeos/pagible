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


final class Inpaint
{
    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        $upload = $args['file'];
        $filemask = $args['mask'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        if( $filemask && !( $filemask instanceof UploadedFile && $filemask->isValid() ) ) {
            throw new Error( 'Invalid mask upload' );
        }

        $provider = config( 'cms.ai.inpaint' ) ?: 'stabilityai';
        $config = config( 'prism.providers.' . $provider, [] );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );
            $mask = $filemask ? Image::fromBinary( $filemask->getContent(), $filemask->getClientMimeType() ) : null;

            $response = Prisma::image()
                ->using( $provider, $config )
                ->model( config( 'cms.ai.inpaint-model' ) )
                ->withClientOptions( ['timeout' => 60, 'connect_timeout' => 10] )
                ->ensure( 'inpaint' )
                ->inpaint( $file, $args['prompt'], $mask );

            return $response->base64();
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, null, $e->getTrace() );
        }
    }
}
