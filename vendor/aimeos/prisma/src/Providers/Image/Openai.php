<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Describe;
use Aimeos\Prisma\Contracts\Image\Imagine;
use Aimeos\Prisma\Contracts\Image\Inpaint;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Openai as Base;
use Aimeos\Prisma\Responses\FileResponse;
use Aimeos\Prisma\Responses\TextResponse;
use Psr\Http\Message\ResponseInterface;


class Openai extends Base implements Describe, Imagine, Inpaint
{
    public function describe( Image $image, ?string $lang = null, array $options = [] ) : TextResponse
    {
        $response = $this->client()->post( 'v1/responses', ['json' => [
            'model' => $this->modelName( 'gpt-5' ),
            'input' => [[
                'role' => 'user',
                'content' => [[
                    'type' => 'input_text',
                    'text' => 'Summarize the content of the file in a few words in plain text format in the language of ISO code "' . ($lang ?? 'en') . '".'
                ], [
                    'type' => 'input_image',
                    'image_url' => $image->url() ?? sprintf( 'data:%s;base64,%s', $image->mimeType(), $image->base64() )
                ]]
            ]]
        ]] );

        $this->validate( $response );

        $result = json_decode( $response->getBody()->getContents(), true ) ?? [];
        $text = null;

        foreach( $result['output'] ?? [] as $data )
        {
            foreach( $data['content'] ?? [] as $content )
            {
                if( $text = $content['text'] ?? null ) {
                    break 2;
                }
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


    public function imagine( string $prompt, array $images = [], array $options = [] ) : FileResponse
    {
        $response = $this->client()->post( 'v1/images/generations', ['json' => $this->params( $prompt, $options )] );

        return $this->toFileResponse( $response );
    }


    public function inpaint( Image $image, Image $mask, string $prompt, array $options = [] ) : FileResponse
    {
        $params = $this->params( $prompt, $options, 'dall-e-2' );
        $request = $this->request( $params, ['image' => $image, 'mask' => $this->mask( $mask )] );
        $response = $this->client()->post( 'v1/images/edits', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    protected function mask( Image $image ) : Image
    {
        if( ( $mask = imagecreatefromstring( (string) $image->binary() ) ) === false ) {
            throw new PrismaException( 'Invalid image/mask data' );
        }

        if( ( $stream = fopen( 'php://memory', 'r+' ) ) === false ) {
            throw new PrismaException( 'Unable to create image stream' );
        }

        imagesavealpha( $mask, true );
        imagealphablending( $mask, false );

        $width = imagesx( $mask );
        $height = imagesy( $mask );

        if( ( $transparent = imagecolorallocatealpha( $mask, 255, 255, 255, 127 ) ) === false ) {
            throw new PrismaException( 'Unable to allocate transparent color' );
        }

        for( $y = 0; $y < $height; $y++ )
        {
            for( $x = 0; $x < $width; $x++ )
            {
                if( ( imagecolorat( $mask, $x, $y ) & 0xFF ) === 0xFF ) { // white pixel
                    imagesetpixel( $mask, $x, $y, $transparent );
                }
            }
        }

        imagepng( $mask, $stream );
        rewind( $stream );

        $png = stream_get_contents( $stream );

        imagedestroy( $mask );
        fclose( $stream );

        return Image::fromBinary( $png, 'image/png' );
    }


    /**
     * Builds the parameters for the image requests.
     *
     * @param string $prompt Prompt describing the image
     * @param array<string, mixed> $options Provider specific options
     * @param string|null $model Optional model name
     * @return array<string, mixed>
     */
    protected function params( string $prompt, array $options, ?string $model = null ) : array
    {
        $model = $this->modelName( $model ?? 'dall-e-3' );
        $data = ['model' => $model, 'prompt' => $prompt, 'response_format' => 'b64_json'];

        $names = match( $model ) {
            'gpt-image-1' => ['background', 'moderation', 'output_compression', 'output_format'],
            'dall-e-3' => ['style'],
            default => [],
        };

        $allowed = $this->allowed( $options, $names + ['quality', 'size', 'user'] );
        $allowed = $this->sanitize( $allowed, [
            'background' => match( $model ) {
                'gpt-image-1' => ['transparent', 'opaque', 'auto'],
                default => [],
            },
            'moderation' => match( $model ) {
                'gpt-image-1' => ['low', 'auto'],
                default => [],
            },
            'output_format' => match( $model ) {
                'gpt-image-1' => ['png', 'jpeg', 'webp'],
                default => [],
            },
            'quality' => match( $model ) {
                'gpt-image-1' => ['low', 'medium', 'high', 'auto'],
                'dall-e-3' => ['standard', 'hd'],
                'dall-e-2' => ['standard'],
                default => [],
            },
            'size' => match( $model ) {
                'gpt-image-1' => ['1536x1024', '1024x1536', '1024x1024', 'auto'],
                'dall-e-3' => ['1792x1024', '1024x1792', '1024x1024', 'auto'],
                'dall-e-2' => ['1024x1024', '512x512', '256x256', 'auto'],
                default => ['auto'],
            },
            'style' => match( $model ) {
                'dall-e-3' => ['vivid', 'natural'],
                default => [],
            },
        ] );

        return $data + $allowed;
    }


    /**
     * Converts the Guzzle response into a file response.
     *
     * @param ResponseInterface $response Guzzle HTTP response
     * @return FileResponse File based response
     */
    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $result = json_decode( $response->getBody()->getContents(), true ) ?? [];

        if( !isset( $result['data'][0]['b64_json'] ) ) {
            throw new \Aimeos\Prisma\Exceptions\PrismaException( 'No image data found in response' );
        }

        $meta = $result;
        unset( $meta['data'], $meta['usage'] );

        return FileResponse::fromBase64( $result['data'][0]['b64_json'] )
            ->withUsage(
                $result['usage']['total_tokens'] ?? null,
                $result['usage'] ?? [],
            )
            ->withMeta( $meta );
    }
}
