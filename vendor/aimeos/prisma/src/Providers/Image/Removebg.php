<?php

namespace Aimeos\Prisma\Providers\Image;

use Aimeos\Prisma\Contracts\Image\Isolate;
use Aimeos\Prisma\Contracts\Image\Relocate;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Files\Image;
use Aimeos\Prisma\Providers\Base;
use Aimeos\Prisma\Responses\FileResponse;
use Psr\Http\Message\ResponseInterface;


class Removebg extends Base implements Isolate, Relocate
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'X-API-Key', $config['api_key'] );
        $this->baseUrl( 'https://api.remove.bg' );
    }


    public function isolate( Image $image, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'add_shadow', 'bg_color', 'channels', 'crop', 'crop_margin', 'format', 'position', 'roi',
            'scale', 'semitransparency', 'shadow_opacity', 'shadow_type', 'size', 'type', 'type_level'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );

        $request = $this->request( $allowed, ['image_file' => $image] );
        $response = $this->client()->post( 'v1.0/removebg', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    public function relocate( Image $image, Image $bgimage, array $options = [] ) : FileResponse
    {
        $allowed = $this->allowed( $options, [
            'add_shadow', 'channels', 'crop', 'crop_margin', 'format', 'position', 'roi',
            'scale', 'semitransparency', 'shadow_opacity', 'shadow_type', 'size', 'type', 'type_level'
        ] );
        $allowed = $this->sanitize( $allowed, $this->options() );

        $request = $this->request( $allowed, ['image_file' => $image, 'bg_image_file' => $bgimage] );
        $response = $this->client()->post( 'v1.0/removebg', ['multipart' => $request] );

        return $this->toFileResponse( $response );
    }


    /**
     * Returns the allowed options and their possible values.
     *
     * @return array<string, array<string>> List of allowed options and their possible values
     */
    protected function options() : array
    {
        return [
            'add_shadow' => ['true', 'false'],
            'format' => ['png', 'jpg', 'webp', 'auto'],
            'semitransparency' => ['true', 'false'],
            'shadow_type' => ['3D', 'car', 'drop', 'auto'],
            'size' => ['preview', 'full', '50MP', 'auto'],
            'type' => ['car', 'product', 'person', 'animal', 'graphic', 'transportation', 'auto'],
            'type_level' => ['none', '1', '2', 'latest'],
        ];
    }


    protected function toFileResponse( ResponseInterface $response ) : FileResponse
    {
        $this->validate( $response );

        $meta = [];
        $mimeType = $response->getHeaderLine( 'Content-Type' );

        foreach( $response->getHeaders() as $name => $value ) {
            $meta[$name] = str_starts_with( $name, 'X-' ) ? current( $value ) : null;
        }

        return FileResponse::fromBinary( $response->getBody(), $mimeType )
            ->withMeta( array_filter( $meta ) )
            ->withUsage(
                (float) $response->getHeaderLine( 'X-Credits-Charged' )
            );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() === 200 ) {
            return;
        }

        $errors = json_decode( $response->getBody()->getContents() )?->errors ?: [];
        $errors = join( ', ', array_column( $errors, 'title' ) );

        switch( $response->getStatusCode() )
        {
            case 400: throw new \Aimeos\Prisma\Exceptions\BadRequestException( $errors );
            case 402: throw new \Aimeos\Prisma\Exceptions\PaymentRequiredException( $errors );
            case 403: throw new \Aimeos\Prisma\Exceptions\UnauthorizedException( $errors );
            case 429: throw new \Aimeos\Prisma\Exceptions\RateLimitException( $errors );
            default: throw new \Aimeos\Prisma\Exceptions\PrismaException( $errors );
        }
    }
}
