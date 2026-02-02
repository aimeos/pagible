<?php

namespace Aimeos\Prisma\Providers\Audio;

use Aimeos\Prisma\Contracts\Audio\Describe;
use Aimeos\Prisma\Contracts\Audio\Speak;
use Aimeos\Prisma\Contracts\Audio\Transcribe;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Providers\Openai as Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;


class Openai extends Base implements Describe, Speak, Transcribe
{
    public function describe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $text = $this->transcribe( $audio, $lang, $options )->text();
        $cmd = 'Summarize the text in a few words in plain text format in the language of ISO code "' . ( $lang ?? 'en' ) . '":';

        $request = [
            'model' => $this->modelName( 'gpt-4o-mini' ),
            'messages' => [
                ['role' => 'user', 'content' => $cmd . "\n" . $text]
            ]
        ];
        $response = $this->client()->post( 'v1/chat/completions', ['json' => $request] );
        $data = json_decode($response->getBody(), true) ?: [];

        $meta = $data;
        unset( $meta['choices'], $meta['usage'] );

        return TextResponse::fromText( $data['choices'][0]['message']['content'] ?? '' )
            ->withUsage(
                $data['usage']['total_tokens'] ?? null,
                $data['usage'] ?? [],
            );
    }


    public function speak( string $text, ?string $voice = null, array $options = [] ) : FileResponse
    {
        $selected = $voice ?: 'alloy';
        $model = $this->modelName( 'gpt-4o-mini-tts' );
        $allowed = $this->allowed( $options, ['instructions', 'response_format', 'speed'] );

        $request = ['model' => $model, 'input' => $text, 'voice' => $selected] + $allowed;
        $response = $this->client()->post( 'v1/audio/speech', ['json' => $request] );

        $this->validate( $response );

        $mimetype = $response->getHeaderLine( 'Content-Type' ) ?: 'audio/mpeg';
        return FileResponse::fromBinary( $response->getBody()->getContents(), $mimetype );
    }


    public function transcribe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $model = $this->modelName( 'whisper-1' );
        $format = $model === 'whisper-1' ? 'verbose_json' : 'json';

        $allowed = $this->allowed( $options, [
            'chunking_strategy', 'include', 'known_speaker_names', 'known_speaker_references',
            'prompt', 'response_format', 'temperature', 'timestamp_granularities'
        ] ) + ['response_format' => $format];

        if( $lang ) {
            $allowed['language'] = $lang;
        }

        $request = $this->request( ['model' => $model] + $allowed, ['file' => $audio] );
        $response = $this->client()->post( 'v1/audio/transcriptions', ['multipart' => $request] );

        $this->validate( $response );

        if( !str_contains( $allowed['response_format'] ?? 'json', 'json' ) ) {
            return TextResponse::fromText( $response->getBody()->getContents() );
        }

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];

        return TextResponse::fromText( $data['text'] ?? null )
            ->withStructured( $data['segments'] ?? [] )
            ->withUsage(
                $data['usage']['total_tokens'] ?? null,
                $data['usage'] ?? [],
            );
    }
}
