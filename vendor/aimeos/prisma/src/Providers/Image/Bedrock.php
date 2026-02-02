<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Inpaint;
use Aimeos\Prisma\Contracts\Image\Isolate;
use Aimeos\Prisma\Contracts\Image\Vectorize;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\VectorResponse;
use Psr\Http\Message\ResponseInterface;


class Bedrock extends Base implements Imagine, Inpaint, Isolate, Vectorize
{
    private string $baseUrl;


    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->baseUrl = 'https://bedrock-runtime.us-east-1.amazonaws.com';

        $this->header( 'Content-Type', 'application/json' );
        $this->header( 'Authorization', 'Bearer ' . $config['api_key'] );
        $this->baseUrl( $config['url'] ?? $this->baseUrl );
    }


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'amazon.titan-image-generator-v2:0' );
        $allowed = $this->allowed( $options, ['quality', 'height', 'width', 'cfgScale', 'seed'] );

        $request = [
            'taskType' => 'TEXT_IMAGE',
            'textToImageParams' => [
                'text' => $prompt,
                ...$this->allowed( $options, ['controlMode', 'controlStrength', 'negativeText'] ) + ['controlStrength' => 0.25]
            ],
            'imageGenerationConfig' => [
                'numberOfImages' => 1,
            ] + $allowed
        ];

        if( $image = current( $images ) ) {
            $request['textToImageParams']['conditionImage'] = $image->base64();
        }

        $response = $this->client()->post( $this->baseUrl . '/model/' . $model . '/invoke', ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'amazon.titan-image-generator-v2:0' );
        $allowed = $this->allowed( $options, ['quality', 'height', 'width', 'cfgScale'] );

        $request = [
            'taskType' => 'INPAINTING',
            'inPaintingParams' => [
                'image' => $image->base64(),
                'maskImage' => $this->invert( $mask )->base64(),
                'text' => $prompt,
                ...$this->allowed( $options, ['negativeText'] )
            ],
            'imageGenerationConfig' => [
                'numberOfImages' => 1,
            ] + $allowed
        ];
        $response = $this->client()->post( $this->baseUrl . '/model/' . $model . '/invoke', ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function isolate( Image $image, array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'amazon.titan-image-generator-v2:0' );

        $request = [
            'taskType' => 'BACKGROUND_REMOVAL',
            'backgroundRemovalParams' => [
                'image' => $image->base64(),
            ],
        ];

        $response = $this->client()->post( $this->baseUrl . '/model/' . $model . '/invoke', ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function vectorize( array $images, ?int $size = null, array $options = [] ) : VectorResponse
    {
        $promises = $vectors = [];
        $model = $this->modelName( 'amazon.titan-embed-image-v1' );

        foreach( $images as $index => $image )
        {
            $promises[$index] = $this->client()->postAsync( 'model/' . $model . '/invoke', [
                'json' => [
                    "inputImage" => $image->base64(),
                    "embeddingConfig" => [
                        "outputEmbeddingLength" => $size ?? 1024
                    ]
                ],
            ] );
        }

        foreach( $promises as $index => $promise )
        {
            $response = $promise->wait();
            $this->validate( $response );

            $vectors[$index] = json_decode( $response->getBody()->getContents(), false )?->embedding;
        }

        return VectorResponse::fromVectors( $vectors );
    }


    protected function invert( Image $image ) : Image
    {
        if( ( $img = imagecreatefromstring( (string) $image->binary() ) ) === false ) {
            throw new PrismaException( "Invalid image/mask data" );
        }

        if( ( $stream = fopen( 'php://memory', 'r+' ) ) === false ) {
            throw new PrismaException( "Unable to create image stream" );
        }

        imagefilter( $img, IMG_FILTER_NEGATE );

        imagepng( $img, $stream );
        rewind( $stream );

        $png = stream_get_contents( $stream );

        imagedestroy( $img );
        fclose( $stream );

        return Image::fromBinary( $png, 'image/png' );
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $data = json_decode( $response->getBody()->getContents(), true );

        if( !isset( $data['images'][0] ) ) {
            throw new \Aimeos\Prisma\Exceptions\PrismaException( 'No image data found in response' );
        }

        return FileResponse::fromBase64( $data['images'][0], 'image/png' );
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
            case 413: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $error );
            case 401: throw new \Aimeos\Prisma\Exceptions\UnauthorizedException( $error );
            case 402: throw new \Aimeos\Prisma\Exceptions\PaymentRequiredException( $error );
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
