<?php

namespace Aimeos\Prisma\Providers\Audio;

use Aimeos\Prisma\Contracts\Audio\Revoice;
use Aimeos\Prisma\Contracts\Audio\Speak;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Psr\Http\Message\ResponseInterface;


class Murf extends Base implements Revoice, Speak
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'api-key', $config['api_key'] );
        $this->baseUrl( $config['url'] ?? 'https://api.murf.ai' );
    }


    public function revoice( Audio $audio, string $voice, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['channel_type', 'format', 'pitch', 'rate', 'sample_rate'] );

        $request = $this->request( ['voice_id' => $voice] + $allowed + ['format' => 'mp3'], ['file' => $audio] );
        $response = $this->client()->post( '/v1/voice-changer/convert', ['multipart' => $request] );

        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents() );

        return FileResponse::fromUrl( $data?->audio_file );
    }


    public function speak( string $text, ?string $voice = null, array $options = [] ) : FileResponse
    {
        $selected = $voice ?: 'en-US-natalie';
        $model = $this->modelName( 'GEN2' );

        $allowed = $this->allowed( $options, [
            'audioDuration', 'channelType', 'format', 'multiNativeLocale',
            'pitch', 'pronunciationDictionary', 'rate', 'sampleRate',
            'style', 'variation', 'wordDurationsAsOriginalText'
        ] );

        $request = ['voiceId' => $selected, 'text' => $text, 'modelVersion' => $model] + $allowed + ['format' => 'mp3'];
        $response = $this->client()->post( '/v1/speech/generate', ['json' => $request] );

        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents() );

        return FileResponse::fromUrl( $data?->audioFile );
    }
}
