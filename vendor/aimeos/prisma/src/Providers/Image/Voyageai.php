<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Vectorize;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\VectorResponse;
use Psr\Http\Message\ResponseInterface;


class Voyageai extends Base implements Vectorize
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'Content-Type', 'application/json' );
        $this->header( 'Authorization', 'Bearer ' . $config['api_key'] );
        $this->baseurl( 'https://api.voyageai.com' );
    }


    public function vectorize( array $images, ?int $size = null, array $options = [] ) : VectorResponse
    {
        $allowed = $this->allowed( $options, ['output_encoding', 'truncation'] );
        $request = [
            'model' => $this->modelName( 'voyage-multimodal-3' ),
            'inputs' => [],
            ...$allowed
        ];

        foreach( $images as $image )
        {
            $request['inputs'][] = [
                'content' => [[
                    'type' => 'image_base64',
                    'image_base64' => sprintf( 'data:%s;base64,%s', $image->mimeType(), $image->base64() )
                ]]
            ];
        }

        $response = $this->client()->post( 'v1/multimodalembeddings', ['json' => $request] );

        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $vectors = array_map( fn( $item ) => $item['embedding'] ?? null, $data['data'] ?? [] );

        return VectorResponse::fromVectors( $vectors )
            ->withUsage( $data['usage']['total_tokens'] ?? 0, $data['usage'] ?? [] );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 200 ) {
            return;
        }

        $error = json_decode( $response->getBody()->getContents() )?->detail ?: $response->getReasonPhrase();

        switch( $response->getStatusCode() )
        {
            case 400: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $error );
            case 401: throw new \Aimeos\Prisma\Exceptions\UnauthorizedException( $error );
            case 403: throw new \Aimeos\Prisma\Exceptions\ForbiddenException( $error );
            case 404: throw new \Aimeos\Prisma\Exceptions\NotFoundException( $error );
            case 429: throw new \Aimeos\Prisma\Exceptions\RateLimitException( $error );
            case 502:
            case 503:
            case 504: throw new \Aimeos\Prisma\Exceptions\OverloadedException( $error );
            default: throw new \Aimeos\Prisma\Exceptions\PrismaException( $error );
        }
    }
}
