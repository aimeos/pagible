<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Erase;
use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Inpaint;
use Aimeos\Prisma\Contracts\Image\Isolate;
use Aimeos\Prisma\Contracts\Image\Uncrop;
use Aimeos\Prisma\Contracts\Image\Upscale;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Psr\Http\Message\ResponseInterface;


class Stabilityai extends Base
    implements Erase, Imagine, Inpaint, Isolate, Uncrop, Upscale
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'authorization', 'Bearer ' . $config['api_key'] );
        $this->baseUrl( $config['url'] ?? 'https://api.stability.ai' );
    }


    public function erase( Image $image, Image $mask, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['grow_mask', 'seed', 'output_format'] );
        $allowed = $this->sanitize( $allowed, ['output_format' => ['png', 'jpeg', 'webp']] );

        $request = $this->request( $allowed, ['image' => $image, 'mask' => $mask] );
        $response = $this->client()->post( 'v2beta/stable-image/edit/erase', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['aspect_ratio', 'negative_prompt', 'output_format', 'seed', 'strength', 'style_preset'] );
        $allowed = $this->sanitize( $allowed, [
            'aspect_ratio' => ['16:9', '1:1', '21:9', '2:3', '3:2', '4:5', '5:4', '9:16', '9:21'],
            'output_format' => ['png', 'jpeg', 'webp'],
        ] );

        $files = !empty( $images ) ? ['image' => current( $images )] : [];
        $model = $this->modelName( 'ultra' );

        if( !empty( $files ) ) {
            $allowed += ['strength' => '0.85'];
        }

        if( str_starts_with( (string) $model, 'sd3' ) )
        {
            $allowed = $this->allowed( $options, ['cfg_scale', 'negative_prompt', 'output_format', 'seed', 'strength', 'style_preset'] );
            $allowed['model'] = $model;
            $model = 'sd3';

            if( !empty( $files ) ) {
                $allowed['mode'] = 'image-to-image';
            }
        }

        $request = $this->request( ['prompt' => $prompt] + $allowed, $files );
        $response = $this->client()->post( 'v2beta/stable-image/generate/' . $model, ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['negative_prompt', 'seed', 'output_format', 'style_preset'] );
        $allowed = $this->sanitize( $allowed, ['output_format' => ['png', 'jpeg', 'webp']] );

        $request = $this->request( ['prompt' => $prompt] + $allowed, ['image' => $image, 'mask' => $mask] );
        $response = $this->client()->post( 'v2beta/stable-image/edit/inpaint', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function isolate( Image $image, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, ['output_format'] );
        $allowed = $this->sanitize( $allowed, ['output_format' => ['png', 'jpeg', 'webp']] );

        $request = $this->request( $allowed, ['image' => $image] );
        $response = $this->client()->post( 'v2beta/stable-image/edit/remove-background', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function uncrop( Image $image, int $top, int $right, int $bottom, int $left, array $options = [] ) : FileResponse
    {
        $data = [
            'up' => min( $top, 2000 ),
            'down' => min( $bottom, 2000 ),
            'left' => min( $left, 2000 ),
            'right' => min( $right, 2000 ),
        ];

        $allowed = $this->allowed( $options, ['creativity', 'output_format', 'prompt', 'seed', 'style_preset'] );
        $allowed = $this->sanitize( $allowed, ['output_format' => ['png', 'jpeg', 'webp']] );

        $request = $this->request( $data + $allowed, ['image' => $image] );
        $response = $this->client()->post( 'v2beta/stable-image/edit/outpaint', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function upscale( Image $image, int $factor, array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'conservative' );
        $allowed = $this->allowed( $options, match( $model ) {
            'conservative' => ['creativity', 'negative_prompt', 'output_format', 'seed'],
            'creative' => ['creativity', 'negative_prompt', 'output_format', 'seed', 'style_preset'],
            default => ['output_format'],
        } );
        $allowed = $this->sanitize( $allowed, ['output_format' => ['png', 'jpeg', 'webp']] );

        if( $model !== 'fast' && !isset( $allowed['prompt'] ) ) {
            $allowed['prompt'] = ' ';
        }

        $request = $this->request( $allowed, ['image' => $image] );
        $response = $this->client()->post( 'v2beta/stable-image/upscale/' . $model, ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $mimeType = $response->getHeaderLine( 'Content-Type' );
        return FileResponse::fromBinary( $response->getBody(), $mimeType );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 200 ) {
            return;
        }

        $errors = join( ', ', json_decode( $response->getBody()->getContents() )?->errors );

        switch( $response->getStatusCode() )
        {
            case 400:
            case 413: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $errors );
            case 403: throw new \Aimeos\Prisma\Exceptions\ForbiddenException( $errors );
            case 429: throw new \Aimeos\Prisma\Exceptions\RateLimitException( $errors );
            default: throw new \Aimeos\Prisma\Exceptions\PrismaException( $errors );
        }
    }
}
