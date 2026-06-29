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


final class Uncrop
{
    use Watch;


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

        $provider = config( 'cms.ai.uncrop.provider' );
        $config = config( 'cms.ai.uncrop', [] );
        $model = config( 'cms.ai.uncrop.model' );
        $start = hrtime( true );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), $upload->getClientMimeType() );

            $base64 = Prisma::image()
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'uncrop' )
                ->uncrop( $file, $args['top'] ?? 0, $args['right'] ?? 0, $args['bottom'] ?? 0, $args['left'] ?? 0 ) // @phpstan-ignore-line method.notFound
                ->base64();

            $this->generated( 'uncrop', $provider, $model, $start );

            return $base64;
        }
        catch( PrismaException $e )
        {
            $this->generated( 'uncrop', $provider, $model, $start, false, $e->getMessage() );

            Log::error( 'AI service error', ['mutation' => 'Uncrop', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}
