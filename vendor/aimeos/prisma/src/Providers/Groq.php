<?php

namespace Aimeos\Prisma\Providers;

use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Providers\Base;
use Psr\Http\Message\ResponseInterface;


class Groq extends Base
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'authorization', 'Bearer ' . $config['api_key'] );
        $this->baseUrl( $config['url'] ?? 'https://api.groq.com' );
    }


    protected function validate( ResponseInterface $response ) : void
    {
        if( $response->getStatusCode() !== 200 )
        {
            $error = json_decode( $response->getBody()->getContents() )?->error?->message ?: $response->getReasonPhrase();
            $this->throw( $response->getStatusCode(), $error );
        }
    }
}
