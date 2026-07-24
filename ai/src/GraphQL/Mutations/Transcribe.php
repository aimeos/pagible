<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\Utils;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use GraphQL\Error\Error;


final class Transcribe
{
    use ObservesPrisma;
    use ValidatesInputs;


    /**
     * @param null $rootValue
     * @param array<string, mixed> $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ): array
    {
        $upload = $this->upload( $args['file'], 'audio' );

        $provider = config( 'cms.ai.transcribe.provider' );
        $config = config( 'cms.ai.transcribe', [] );
        $model = config( 'cms.ai.transcribe.model' );

        try
        {
            $file = Audio::fromBinary( $upload->getContent(), (string) $upload->getMimeType() );

            $data = Prisma::audio()->observe( $this->observer() )
                ->using( $provider, $config )
                ->model( $model )
                ->ensure( 'transcribe' )
                ->transcribe( $file, null, $config ) // @phpstan-ignore-line method.notFound
                ->structured();

            return array_map( fn( $entry ) => [
                'start' => Utils::formatSeconds( $entry['start'] ),
                'end' => Utils::formatSeconds( $entry['end'] ),
                'text' => $entry['text'],
            ], $data );
        }
        catch( PrismaException $e )
        {
            Log::error( 'AI service error', ['mutation' => 'Transcribe', 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString()] );
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }
}
