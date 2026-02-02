<?php

namespace Aimeos\Prisma\Providers\Audio;

use Aimeos\Prisma\Contracts\Audio\Describe;
use Aimeos\Prisma\Contracts\Audio\Transcribe;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Providers\Mistral as Base;
use Aimeos\Prisma\Responses\TextResponse;
use Psr\Http\Message\ResponseInterface;


class Mistral extends Base implements Describe, Transcribe
{
    public function describe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $text = $this->transcribe( $audio, $lang, $options )->text();
        $cmd = 'Summarize the text in a few words in plain text format in the language of ISO code "' . ( $lang ?? 'en' ) . '":';

        $request = [
            'model' => $this->modelName( 'mistral-large-latest' ),
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


    public function transcribe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $allowed = $this->allowed( $options, ['temperature', 'timestamp_granularities'] );

        $files = [];
        $request = [
            'language' => $lang,
            'model' => $this->modelName( 'voxtral-mini-latest' ),
        ] + $allowed + ['timestamp_granularities' => 'segment'];

        if( $audio->url() ) {
            $request['file_url'] = $audio->url();
        } else {
            $files['file'] = $audio;
        }

        $request = $this->request( $request, $files );
        $response = $this->client()->post( 'v1/audio/transcriptions', ['multipart' => $request] );

        return $this->toTextResponse( $response );
    }


    protected function toTextResponse( ResponseInterface $response ) : TextResponse
    {
        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];

        return TextResponse::fromText( $data['text'] ?? '' )
            ->withUsage( $data['usage']['total_tokens'] ?? 0, $data['usage'] ?? [] )
            ->withStructured( $data['segments'] ?? [] );
    }
}
