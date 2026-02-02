<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Recognize;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Mistral as Base;
use Aimeos\Prisma\Responses\TextResponse;
use Psr\Http\Message\ResponseInterface;


class Mistral extends Base implements Recognize
{
    public function recognize( Image $image, array $options = [] ) : TextResponse
    {
        $model = $this->modelName( 'mistral-ocr-latest' );
        $allowed = $this->allowed( $options, [
            'bbox_annotation_format', 'document_annotation_format', 'id',
            'image_limit', 'image_min_size', 'include_image_base64', 'pages'
        ] );

        $request = [
            'document' => [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $image->url() ?: sprintf( 'data:%s;base64,%s', $image->mimeType(), $image->base64() ),
                ],
            ],
            'model' => $model,
        ] + $allowed;

        $response = $this->client()->post( 'v1/ocr', ['json' => $request] );

        return $this->toTextResponse( $response );
    }


    protected function toTextResponse( ResponseInterface $response ) : TextResponse
    {
        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $texts = array_map( fn( $item ) => $item['markdown'] ?? null, $data['pages'] ?? [] );

        return TextResponse::fromText( implode( "\n\n", $texts ) )
            ->withUsage( 0, $data['usage_info'] ?? [] );
    }
}
