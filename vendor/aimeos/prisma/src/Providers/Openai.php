<?php

namespace Aimeos\Prisma\Providers;

use Aimeos\Prisma\Exceptions\PrismaException;
use Psr\Http\Message\ResponseInterface;


class Openai extends Base
{
    public function __construct( array $config )
    {
        if( !isset( $config['api_key'] ) ) {
            throw new PrismaException( sprintf( 'No API key' ) );
        }

        $this->header( 'OpenAI-Organization', $config['organization'] ?? null );
        $this->header( 'OpenAI-Project', $config['project'] ?? null );
        $this->header( 'authorization', 'Bearer ' . $config['api_key'] );
        $this->baseUrl( $config['url'] ?? 'https://api.openai.com' );
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
