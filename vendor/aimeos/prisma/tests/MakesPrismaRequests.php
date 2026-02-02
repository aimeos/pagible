<?php

namespace Tests;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Contracts\Provider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use PHPUnit\Framework\Assert;


trait MakesPrismaRequests
{
    /** @var array<int, array<string, mixed>> */
    protected array $prismaHistory = [];
    protected ?MockHandler $prismaHandler = null;
    protected ?Provider $prismaProvider = null;


    /**
     * Assert that a Prisma request matching the callback was sent.
     *
     * @param callable $callback Callback to validate the request and options
     * @param string $message Error message
     * @return void
     */
    protected function assertPrismaRequest( callable $callback, string $message = '' ) : void
    {
        foreach( $this->prismaHistory as $entry )
        {
            unset( $entry['options']['handler'] );
            if( $callback( $entry['request'], $entry['options'] ) !== false ) {
                return;
            }
        }

        Assert::fail( $message ?: 'No matching Prisma request was sent.' );
    }


    /**
     * Create a Prisma provider instance.
     *
     * @param string $type Prisma provider type
     * @param string $name Prisma provider name
     * @param array<string, mixed> $config Configuration options
     */
    protected function prisma( string $type, string $name, array $config = [] ) : static
    {
        $this->prismaHandler = new MockHandler();

        $stack = HandlerStack::create( $this->prismaHandler );
        $stack->push( Middleware::history( $this->prismaHistory ) );

        $this->prismaProvider = (new Prisma( $type ))
            ->using( $name, $config )
            ->withClientHandler( $stack );

        return $this;
    }


    /**
     * Add a fake response, optionally return the Provider for direct use.
     *
     * @param string|array<string, mixed> $body Response body
     * @param array<string, int|string> $headers Response headers
     * @param int $status HTTP status code
     * @param string $reason Reason phrase
     * @return Provider Provider instance if needed
     */
    protected function response( string|array $body = '', array $headers = [], int $status = 200, string $reason = '' ) : Provider
    {
        if( is_array( $body ) )
        {
            $body = json_encode( $body );
            $headers['Content-Type'] ??= 'application/json';
        }

        $this->prismaHandler->append( new Response( $status, $headers, $body, '1.1', $reason ) );

        return $this->prismaProvider;
    }


    /**
     * Get all recorded requests.
     *
     * @return array<RequestInterface> List of requests
     */
    protected function requests(): array
    {
        return array_map( fn( $h ) => $h['request'], $this->prismaHistory );
    }


    /**
     * Get the underlying provider instance.
     *
     * @return Provider Prisma provider instance
     */
    protected function provider() : Provider
    {
        return $this->prismaProvider;
    }
}
