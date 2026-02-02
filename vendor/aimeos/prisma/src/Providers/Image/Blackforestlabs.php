<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Inpaint;
use Aimeos\Prisma\Contracts\Image\Uncrop;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Psr\Http\Message\ResponseInterface;


class Blackforestlabs extends Base implements Imagine, Inpaint, Uncrop
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'x-key', $config['api_key'] );
        $this->header( 'Content-Type', 'application/json' );
        $this->baseUrl( $config['url'] ?? 'https://api.bfl.ai' );
    }


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $model = (string) $this->modelName( 'flux-2-pro' );
        $names = match( $model ) {
            'flux-2-pro' => ['seed', 'width', 'height', 'safety_tolerance', 'output_format', 'webhook_url', 'webhook_secret'],
            'flux-2-flex' => ['prompt_upsampling', 'input_image_blob_path', 'seed', 'width', 'height', 'guidance', 'steps', 'safety_tolerance', 'output_format', 'webhook_url', 'webhook_secret'],
            'flux-kontext-pro', 'flux-kontext-max' => ['seed', 'aspect_ratio', 'output_format', 'webhook_url', 'webhook_secret', 'prompt_upsampling', 'safety_tolerance'],
            'flux-pro-1.1-ultra' => ['prompt_upsampling', 'seed', 'aspect_ratio', 'safety_tolerance', 'output_format', 'raw', 'image_prompt', 'image_prompt_strength', 'webhook_url', 'webhook_secret'],
            'flux-pro-1.1' => ['image_prompt', 'width', 'height', 'prompt_upsampling', 'seed', 'safety_tolerance', 'output_format', 'webhook_url', 'webhook_secret'],
            'flux-dev' => ['image_prompt', 'width', 'height', 'steps', 'prompt_upsampling', 'seed', 'guidance', 'safety_tolerance', 'output_format', 'webhook_url', 'webhook_secret'],
            default => array_keys( $options )
        };

        $allowed = $this->allowed( $options, $names );
        $files = [];

        if( $file = array_shift( $images ) )
        {
            if( str_starts_with( $model, 'flux-pro' ) || str_starts_with( $model, 'flux-dev' ) ){
                $files['input_prompt'] = $file->base64();
            } else {
                $files['input_image'] = $file->base64();

                foreach( $images as $index => $file ) {
                    $files['input_image_' . ( $index + 2 )] = $file->base64();
                }
            }
        }

        $request = ['prompt' => $prompt] + $allowed + ['output_format' => 'png'] + $files;
        $response = $this->client()->post( 'v1/' . $model, ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'flux-pro-1.0-fill' );
        $allowed = $this->allowed( $options, [
            'steps', 'prompt_upsampling', 'seed', 'guidance', 'output_format',
            'safety_tolerance', 'webhook_url', 'webhook_secret'
        ] ) + ['output_format' => 'png'];

        $request = ['image' => $image->base64(), 'mask' => $mask->base64(), 'prompt' => $prompt] + $allowed;
        $response = $this->client()->post( 'v1/' . $model, ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    public function uncrop( Image $image, int $top, int $right, int $bottom, int $left, array $options = [] ) : FileResponse
    {
        $model = $this->modelName( 'flux-pro-1.0-expand' );
        $data = [
            'image' => $image->base64(),
            'top' => min( $top, 2048 ),
            'right' => min( $right, 2048 ),
            'bottom' => min( $bottom, 2048 ),
            'left' => min( $left, 2048 ),
        ];
        $allowed = $this->allowed( $options, [
            'guidance', 'output_format', 'prompt', 'prompt_upsampling', 'safety_tolerance',
            'seed', 'steps', 'webhook_url', 'webhook_secret'
        ] );

        $request = $data + $allowed + ['output_format' => 'png'];
        $response = $this->client()->post( 'v1/' . $model, ['json' => $request] );

        return $this->toFileResponse( $response );
    }


    protected function closure( string $url ) : \Closure
    {
        $client = $this->client();

        return function() use ( $client, $url ) {

            $response = $client->get( $url );

            if( $response->getStatusCode() !== 200 ) {
                throw new PrismaException( $response->getReasonPhrase() );
            }

            if( !( $data = json_decode( $response->getBody()->getContents() ) ) ) {
                throw new PrismaException( 'Invalid response: ' . $response->getBody()->getContents() );
            }

            if( @$data->status !== 'Ready' ) {
                return null;
            }

            if( !@$data->sample ) {
                throw new PrismaException( 'Invalid response: ' . $response->getBody()->getContents() );
            }

            $response = $client->get( $data->sample );

            if( $response->getStatusCode() !== 200 ) {
                throw new PrismaException( $response->getReasonPhrase() );
            }

            return $response->getBody()->getContents();
        };
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        if( ( $json = json_decode( $response->getBody()->getContents() ) ) === null || !isset( $json->polling_url ) ) {
            throw new PrismaException( 'Invalid response' );
        }

        return FileResponse::fromAsync( $this->closure( $json->polling_url ), 2 )
            ->withUsage( $json->cost ?? 0 );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 200 ) {
            return;
        }

        $detail = json_decode( $response->getBody()->getContents() )?->detail;
        $error = is_array( $detail ) ? join( ', ', array_map( fn( $entry ) => $entry->msg,  $detail ) ) : $detail;
        $msg = $error ?? $response->getReasonPhrase();

        switch( $response->getStatusCode() )
        {
            case 400:
            case 422: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $msg );
            case 402: throw new \Aimeos\Prisma\Exceptions\PaymentRequiredException( $msg );
            case 429: throw new \Aimeos\Prisma\Exceptions\RateLimitException( $msg );
            default: throw new \Aimeos\Prisma\Exceptions\PrismaException( $msg );
        }
    }
}
