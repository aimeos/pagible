<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Describe;
use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Repaint;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Gemini as Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Psr\Http\Message\ResponseInterface;


class Gemini extends Base implements Describe, Imagine, Repaint
{
    public function describe( Image $image, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $model = $this->modelName( 'gemini-3-pro-image-preview' );
        $request = [
            'contents' => [[
                'parts' => [[
                    'inlineData' => [
                        'data' => $image->base64(),
                        'mimeType' => $image->mimeType()
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


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'gemini-3-pro-image-preview' );
        $allowed = $this->allowed( $options, ['aspectRatio'] );

        $system = $this->systemPrompt() ? [
            'systemInstruction' => [
                'parts' => [[
                    'text' => $this->systemPrompt()
                ]]
            ]] : [];

        $config = !empty( $allowed ) ? [
            'imageConfig' => $allowed
        ] : [];

        $request = $system + [
            'contents' => [[
                'parts' => [
                    ...array_map( fn( Image $img ) => [
                        'inlineData' => [
                            'data' => $img->base64(),
                            'mimeType' => $img->mimeType()
                        ],
                    ], $images ),
                    ['text' => $prompt]
                ]
            ]],
            'generationConfig' => [
                'responseModalities' => $options['responseModalities'] ?? ['TEXT', 'IMAGE'],
                ...$config
            ]
        ];

        $response = $this->client()->post( 'v1beta/models/' . $model . ':generateContent', ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function repaint( Image $image, string $prompt, array $options = [] ) : FileResponse
    {
        return $this->imagine( $prompt, [$image], $options );
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $data = current( $data['candidates'] ?? [] ) ?: null;
        $base64 = $mimeType = $description = null;

        foreach( $data['content']['parts'] ?? [] as $part )
        {
            if( isset( $part['inlineData']['data'] ) ) {
                $base64 = $part['inlineData']['data'];
                $mimeType = $part['inlineData']['mimeType'] ?? null;
            } elseif( isset( $part['text'] ) ) {
                $description = $part['text'];
            }
        }

        if( !$base64 ) {
            throw new \Aimeos\Prisma\Exceptions\PrismaException( 'No image data found in response' );
        }

        return FileResponse::fromBase64( $base64, $mimeType )
            ->withMeta( $data['metadata'] ?? [] )
            ->withDescription( $description );
    }
}
