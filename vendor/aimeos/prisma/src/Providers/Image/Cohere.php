<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Vectorize;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\VectorResponse;
use Psr\Http\Message\ResponseInterface;


class Cohere extends Base implements Vectorize
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'Content-Type', 'application/json' );
        $this->header( 'Authorization', 'Bearer ' . $config['api_key'] );
        $this->baseurl( $config['url'] ?? 'https://api.cohere.ai' );
    }


    public function vectorize( array $images, ?int $size = null, array $options = [] ) : VectorResponse
    {
        $allowed = $this->allowed( $options, ['embedding_types', 'max_tokens', 'truncate', 'priority'] );
        $request = [
            'model' => $this->modelName( 'embed-v4.0' ),
            'inputs' => [],
            'input_type' => 'image',
            'output_dimension' => $size ?: 1536,
            ...$allowed,
        ] + ['embedding_types' => ['float']];

        foreach( $images as $image )
        {
            $request['inputs'][] = [
                'content' => [[
                    'type' => 'image_url',
                    'image_url' => [
                        'url' => sprintf( 'data:%s;base64,%s', $image->mimeType(), $image->base64() )
                    ]
                ]]
            ];
        }

        $response = $this->client()->post( 'v2/embed', ['json' => $request] );

        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true ) ?? [];

        return VectorResponse::fromVectors( $data['embeddings']['float'] )
            ->withUsage( $data['meta']['billed_units']['images'] ?? 0, $data['meta']['billed_units'] ?? [] )
            ->withMeta( $data['meta'] ?? [] );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 200 ) {
            return;
        }

        $error = json_decode( $response->getBody()->getContents() )?->message ?: $response->getReasonPhrase();

        switch( $response->getStatusCode() )
        {
            case 400:
            case 409:
            case 413:
            case 422: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $error );
            case 401: throw new \Aimeos\Prisma\Exceptions\UnauthorizedException( $error );
            case 402: throw new \Aimeos\Prisma\Exceptions\PaymentRequiredException( $error );
            case 403:
            case 498: throw new \Aimeos\Prisma\Exceptions\ForbiddenException( $error );
            case 404: throw new \Aimeos\Prisma\Exceptions\NotFoundException( $error );
            case 429: throw new \Aimeos\Prisma\Exceptions\RateLimitException( $error );
            case 503:
            case 504: throw new \Aimeos\Prisma\Exceptions\OverloadedException( $error );
            default: throw new \Aimeos\Prisma\Exceptions\PrismaException( $error );
        }
    }
}
