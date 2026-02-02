<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Describe;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Groq as Base;
use Aimeos\Prisma\Responses\TextResponse;


class Groq extends Base implements Describe
{
    public function describe( Image $image, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $response = $this->client()->post( 'openai/v1/chat/completions', ['json' => [
            'model' => $this->modelName( 'meta-llama/llama-4-scout-17b-16e-instruct' ),
            'messages' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Summarize the content of the file in a few words in plain text format in the language of ISO code "' . ($lang ?? 'en') . '".'
                ], [
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => $image->url() ?? sprintf( 'data:%s;base64,%s', $image->mimeType(), $image->base64() )
                    ]
                ]]
            ]]
        ]] );

        $this->validate( $response );

        $result = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $text = null;

        foreach( $result['choices'] ?? [] as $data )
        {
            if( $text = $data['message']['content'] ?? null ) {
                break;
            }
        }

        if( !$text ) {
            throw new \Aimeos\Prisma\Exceptions\PrismaException( 'No text found in response' );
        }

        $meta = $result;
        unset( $meta['output'], $meta['usage'] );

        return TextResponse::fromText( $text )
            ->withUsage(
                $result['usage']['total_tokens'] ?? null,
                $result['usage'] ?? [],
            )
            ->withMeta( $meta );
    }
}
