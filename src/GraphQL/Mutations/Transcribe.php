<?php

namespace Aimeos\Cms\GraphQL\Mutations;

use Prism\Prism\Prism;
use Prism\Prism\ValueObjects\Media\Audio;
use Illuminate\Http\UploadedFile;
use Aimeos\Cms\GraphQL\Exception;
use Aimeos\Cms\Models\File;


final class Transcribe
{
    /**
     * @param null $rootValue
     * @param array<string, mixed> $args
     */
    public function __invoke( $rootValue, array $args ): string
    {
        $prism = Prism::audio()->using( config( 'cms.ai.audio', 'openai' ), config( 'cms.ai.audio-model', 'whisper-1' ) )
            ->withClientOptions( [
                'timeout' => 60,
                'connect_timeout' => 10,
            ] );

        if( !( ( $upload = $args['file'] ?? null ) && $upload instanceof UploadedFile && $upload->isValid() ) ) {
            throw new Exception( 'No file uploaded' );
        }

        if( !str_starts_with( $upload->getMimeType(), 'audio/' ) ) {
            throw new Exception( 'Only audio files' );
        }

        $file = Audio::fromBase64( base64_encode( $upload->getContent() ), $upload->getMimeType() );

        $response = $prism->withInput( $file )
            ->withProviderOptions([
                'response_format' => 'vtt',
            ])->asText();

        return $response->text;
    }
}
