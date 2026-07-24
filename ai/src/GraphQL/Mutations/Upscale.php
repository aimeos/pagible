<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Upscale
{
    use ObservesPrisma;
    use ValidatesInputs;


    /**
     * @param  null  $rootValue
     * @param  array<string, mixed>  $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        $upload = $this->upload( $args['file'], 'image' );

        $provider = config( 'cms.ai.upscale.provider' );
        $config = config( 'cms.ai.upscale', [] );
        $model = config( 'cms.ai.upscale.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), (string) $upload->getMimeType() );

            return Prisma::image()->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'upscale' )
                ->upscale( $file, $args['factor'], $config ) // @phpstan-ignore-line method.notFound
                ->base64();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Upscale', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
