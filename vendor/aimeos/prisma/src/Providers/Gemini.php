<?php

namespace Aimeos\Prisma\Providers;

use Aimeos\Prisma\Exceptions\PrismaException;
use Psr\Http\Message\ResponseInterface;


class Gemini extends Base
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'x-goog-api-key', (string) $config['api_key'] );
        $this->baseUrl( 'https://generativelanguage.googleapis.com' );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( ( $status = $response->getStatusCode() ) !== 200 )
        {
            $error = json_decode( $response->getBody()->getContents() )?->error?->message ?: $response->getReasonPhrase();
            $this->throw( match( $status ) {
                403 => 401, // unauthorized, not forbidden content
                default   => $status
            }, $error );
        }
    }
}
