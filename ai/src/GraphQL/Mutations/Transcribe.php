<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Utils;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use GraphQL\Error\Error;


final class Transcribe
{
    /**
     * @param null $rootValue
     * @param array<string, mixed> $args
     * @return array<int, mixed>
     */
    public function __invoke( $rootValue, array $args ): array
    {
        $upload = $args['file'];

        if( !$upload instanceof UploadedFile || !$upload->isValid() ) {
            throw new Error( 'Invalid file upload' );
        }

        $provider = config( 'cms.ai.transcribe.provider' );
        $config = config( 'cms.ai.transcribe', [] );
        $model = config( 'cms.ai.transcribe.model' );

        try
        {
            $file = Audio::fromBinary( $upload->getContent(), $upload->getClientMimeType() );

            $data = Prisma::audio()
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
            throw new Error( config( 'app.debug' ) ? $e->getMessage() : 'AI service error', null, null, null, null, $e );
        }
    }
}
