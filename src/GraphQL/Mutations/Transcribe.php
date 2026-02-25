<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\GraphQL\Mutations;

use Aimeos\Cms\Permission;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Support\Facades\Auth;
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
        if( !Permission::can( 'audio:transcribe', Auth::user() ) ) {
            throw new Error( 'Insufficient permissions' );
        }

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
                'start' => $this->time( $entry['start'] ),
                'end' => $this->time( $entry['end'] ),
                'text' => $entry['text'],
            ], $data );
        }
        catch( PrismaException $e )
        {
            throw new Error( $e->getMessage(), null, null, null, null, $e );
        }
    }


    /**
     * Formats the given time in seconds to a string in the format "HH:MM:SS.mmm"
     *
     * @param float $seconds Time in seconds
     * @return string Formatted time string
     */
    protected function time( float $seconds ) : string
    {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = floor( $seconds % 60 );
        $millis = ( $seconds - floor( $seconds ) ) * 1000;

        return sprintf( "%02d:%02d:%02d.%03d", $hours, $minutes, $secs, $millis );
    }
}
