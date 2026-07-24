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


final class Repaint
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

        $provider = config( 'cms.ai.repaint.provider' );
        $config = config( 'cms.ai.repaint', [] );
        $model = config( 'cms.ai.repaint.model' );

        try
        {
            $file = Image::fromBinary( $upload->getContent(), (string) $upload->getMimeType() );

            return Prisma::image()->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'repaint' )
                ->repaint( $file, $args['prompt'], $config ) // @phpstan-ignore-line method.notFound
                ->base64();
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Repaint', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
