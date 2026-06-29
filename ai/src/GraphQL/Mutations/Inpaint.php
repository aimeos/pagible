<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\Watch;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use GraphQL\Error\Error;


final class Inpaint
{
    use Watch;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
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
        $start = hrtime( true );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );
            $mask = Image::fromBinary( $upmask->getContent(), $upmask->getClientMimeType() );

            $base64 = Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'inpaint' )
                ->inpaint( $file, $mask, $args['prompt'], $config ) // @phpstan-ignore-line method.notFound
                ->base64();

            $this->generated( 'inpaint', $provider, $model, $start );

            return $base64;
        }
        catch( PrismaException $e )
        {
            $this->generated( 'inpaint', $provider, $model, $start, false, $e->getMessage() );

            Log::error( 'AI service error', ['mutation' => 'Inpaint', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}
