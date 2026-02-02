<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Background;
use Aimeos\Prisma\Contracts\Image\Detext;
use Aimeos\Prisma\Contracts\Image\Erase;
use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Isolate;
use Aimeos\Prisma\Contracts\Image\Uncrop;
use Aimeos\Prisma\Contracts\Image\Upscale;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Psr\Http\Message\ResponseInterface;


class Clipdrop extends Base
    implements Background, Detext, Erase, Imagine, Isolate, Uncrop, Upscale
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'x-api-key', (string) $config['api_key'] );
        $this->baseUrl( 'https://clipdrop-api.co' );
    }


    public function background( Image $image, string $prompt, array $options = [] ) : FileResponse
    {
        $request = $this->request( ['prompt' => $prompt], ['image_file' => $image] );
        $response = $this->client()->post( 'replace-background/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function detext( Image $image, array $options = [] ) : FileResponse
    {
        $request = $this->request( $options, ['image_file' => $image] );
        $response = $this->client()->post( 'remove-text/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function erase( Image $image, Image $mask, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['mode'] );
        $request = $this->request( $allowed, ['image_file' => $image, 'mask_file' => $mask] );
        $response = $this->client()->post( 'cleanup/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $request = $this->request( ['prompt' => $prompt] );
        $response = $this->client()->post( 'text-to-image/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function isolate( Image $image, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['transparency_handling'] );
        $request = $this->request( $allowed, ['image_file' => $image] );
        $response = $this->client()->post( 'remove-background/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function uncrop( Image $image, int $top, int $right, int $bottom, int $left, array $options = [] ) : FileResponse
    {
        $data = [
            'extend_up' => min( $top, 2048 ),
            'extend_down' => min( $bottom, 2048 ),
            'extend_left' => min( $left, 2048 ),
            'extend_right' => min( $right, 2048 ),
        ];

        $allowed = $this->allowed( $options, ['seed'] );
        $request = $this->request( $data + $allowed, ['image_file' => $image] );
        $response = $this->client()->post( 'uncrop/v1', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function upscale( Image $image, int $factor, array $options = [] ) : FileResponse
    {
        if( ( $size = getimagesizefromstring( (string) $image->binary() ) ) === false ) {
            throw new PrismaException( 'Unable to get image size' );
        }

        $data = [
            'target_width' => min( $size[0] * $factor, 4096 ),
            'target_height' => min( $size[1] * $factor, 4096 ),
        ];

        $request = $this->request( $data, ['image_file' => $image] );
        $response = $this->client()->post( 'image-upscaling/v1/upscale', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        if( $response->getStatusCode() === 406 ) {
            throw new \Aimeos\Prisma\Exceptions\BadRequestException( $response->getReasonPhrase() );
        }

        $this->validate( $response );
        $mimeType = $response->getHeaderLine( 'Content-Type' );

        return FileResponse::fromBinary( $response->getBody(), $mimeType )->withUsage(
            (float) $response->getHeaderLine( 'x-credits-consumed' ), [
                'x-remaining-credits' => $response->getHeaderLine( 'x-remaining-credits' )
            ]
        );
    }
}
