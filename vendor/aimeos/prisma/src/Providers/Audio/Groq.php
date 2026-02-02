<?php

namespace Aimeos\Prisma\Providers\Audio;

use Aimeos\Prisma\Contracts\Audio\Describe;
use Aimeos\Prisma\Contracts\Audio\Speak;
use Aimeos\Prisma\Contracts\Audio\Transcribe;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Providers\Groq as Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;


class Groq extends Base implements Describe, Speak, Transcribe
{
    public function describe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $text = $this->transcribe( $audio, $lang, $options )->text();
        $cmd = 'Summarize the text in a few words in plain text format in the language of ISO code "' . ( $lang ?? 'en' ) . '":';

        $request = [
            'model' => $this->modelName( 'openai/gpt-oss-120b' ),
            'messages' => [
                ['role' => 'user', 'content' => $cmd . "\n" . $text]
            ]
        ];
        $response = $this->client()->post( 'openai/v1/chat/completions', ['json' => $request] );
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
        $selected = $voice ?: 'austin';
        $model = $this->modelName( 'canopylabs/orpheus-v1-english' );
        $allowed = $this->allowed( $options, ['response_format', 'sample_rate', 'speed'] );
        $allowed += ['response_format' => 'wav'];

        $request = ['model' => $model, 'input' => $text, 'voice' => $selected] + $allowed;
        $response = $this->client()->post( 'openai/v1/audio/speech', ['json' => $request] );

        $this->validate( $response );

        $mimetype = $response->getHeaderLine( 'Content-Type' ) ?: 'audio/wav';
        return FileResponse::fromBinary( $response->getBody()->getContents(), $mimetype );
    }


    public function transcribe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $model = $this->modelName( 'whisper-large-v3' );
        $allowed = $this->allowed( $options, [
            'prompt', 'response_format', 'temperature', 'timestamp_granularities'
        ] ) + ['response_format' => 'verbose_json'];

        if( $lang ) {
            $allowed['language'] = $lang;
        }

        $request = $this->request( ['model' => $model] + $allowed, ['file' => $audio] );
        $response = $this->client()->post( 'openai/v1/audio/transcriptions', ['multipart' => $request] );

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
