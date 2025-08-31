<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\Exceptions\PrismException;
use Prism\Prism\ValueObjects\Media\Audio;
use Illuminate\Http\UploadedFile;
use Aimeos\Cms\Models\File;
use GraphQL\Error\Error;


final class Transcribe
{
    /**
     * @param null $rootValue
     * @param array<string, mixed> $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        if( !( ( $upload = $args['file'] ?? null ) && $upload instanceof UploadedFile && $upload->isValid() ) ) {
            throw new Error( 'No file uploaded' );
        }

        if( !str_starts_with( $upload->getMimeType(), 'audio/' ) ) {
            throw new Error( 'Only audio files' );
        }

        $provider = config( 'cms.ai.audio' ) ?: 'openai';
        $model = config( 'cms.ai.audio-model' ) ?: 'whisper-1';

        try
        {
            $prism = Prism::audio()->using( $provider, $model )
                ->withMaxTokens( config( 'cms.ai.maxtoken', 32768 ) )
                ->withClientOptions( [
                    'timeout' => 60,
                    'connect_timeout' => 10,
                ] );

            $file = Audio::fromBase64( base64_encode( $upload->getContent() ), $upload->getMimeType() );

            $response = $prism->withInput( $file )
                ->withProviderOptions( [
                'response_format' => 'verbose_json',
            ] )->asText();

            return $this->webvtt( $response->additionalContent['segments'] ?? [] );
        }
        catch( PrismException $e )
        {
            throw new Error( $e->getMessage() );
        }
    }


    protected function time( float $seconds ) : string
    {
        $hours = floor( $seconds / 3600 );
        $minutes = floor( ( $seconds % 3600 ) / 60 );
        $secs = floor( $seconds % 60 );
        $millis = ( $seconds - floor( $seconds ) ) * 1000;

        return sprintf( "%02d:%02d:%02d.%03d", $hours, $minutes, $secs, $millis );
    }


    protected function webvtt( array $segments ) : string
    {
        $lines = ['WEBVTT', ''];

        foreach( $segments as $segment )
        {
            $lines[] = $this->time( $segment['start'] ) . ' --> ' . $this->time( $segment['end'] );
            $lines[] = trim( $segment['text'] );
            $lines[] = "";
        }

        return implode( "\n", $lines );
    }
}
