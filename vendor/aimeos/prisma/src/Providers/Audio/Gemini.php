<?php

namespace Aimeos\Prisma\Providers\Audio;

use Aimeos\Prisma\Contracts\Audio\Describe;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Providers\Gemini as Base;
use Aimeos\Prisma\Responses\TextResponse;


class Gemini extends Base implements Describe
{
    public function describe( Audio $audio, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $model = $this->modelName( 'gemini-2.5-flash' );
        $request = [
            'contents' => [[
                'parts' => [[
                    'inlineData' => [
                        'data' => $audio->base64(),
                        'mimeType' => $audio->mimeType()
                    ]],
                    ['text' => 'Summarize the content of the file in a few words in plain text format in the language of ISO code "' . ( $lang ?? 'en' ) . '".']
                ]
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT']
            ]
        ];
        $response = $this->client()->post( 'v1beta/models/' . $model . ':generateContent', ['json' => $request] );

        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $data = current( $data['candidates'] ?? [] ) ?: [];
        $part = current( $data['content']['parts'] ?? [] ) ?: [];

        return TextResponse::fromText( $part['text'] ?? null )
            ->withMeta( $data['metadata'] ?? [] );
    }
}
