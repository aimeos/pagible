<?php

namespace Aimeos\Prisma\Providers;

use Aimeos\Prisma\Contracts\Provider;
use Aimeos\Prisma\Exceptions\NotImplementedException;


class Fake extends Base
{
    /** @var array<int, mixed> */
    private array $responses = [];
    private ?Provider $provider = null;


    /**
     * Creates a new fake provider with the given responses.
     *
     * @param array<int, mixed> $responses
     */
    public function __construct( array $responses )
    {
        $this->responses = $responses;
    }


    /**
     * Handles calls to methods not implemented by the fake provider.
     *
     * @param string $method Method name
     * @param array<string, mixed> $arguments Method arguments
     * @return mixed
     * @throws NotImplementedException
     */
    public function __call( string $method, array $arguments ) : mixed
    {
        if( !$this->provider ) {
            throw new NotImplementedException( sprintf( 'No provider set for fake, cannot call "%1$s"', $method ) );
        }

        if( !method_exists( $this->provider, $method ) ) {
            throw new NotImplementedException( sprintf( 'Provider does not implement "%1$s"', $method ) );
        }

        if( empty( $this->responses ) ) {
            throw new NotImplementedException( sprintf( 'No fake response found for "%1$s"', $method ) );
        }

        return array_shift( $this->responses );
    }


    /**
     * Ensures that the given method is implemented by the provider.
     *
     * @param string $method Method name
     * @return static
     * @throws NotImplementedException If no provider is set
     */
    public function ensure( string $method ) : static
    {
        if( !$this->provider ) {
            throw new NotImplementedException( sprintf( 'No provider set for fake, cannot ensure "%1$s"', $method ) );
        }

        $this->provider->ensure( $method );
        return $this;
    }


    /**
     * Checks whether the given method is implemented by the provider.
     *
     * @param string $method Method name
     * @return bool TRUE if the method is implemented, FALSE otherwise
     */
    public function has( string $method ) : bool
    {
        if( !$this->provider ) {
            throw new NotImplementedException( sprintf( 'No provider set for fake, cannot test "%1$s"', $method ) );
        }

        return $this->provider->has( $method );
    }


    /**
     * Sets the provider to use for method checks.
     *
     * @param Provider $provider The provider to use
     * @return static
     */
    public function use( Provider $provider ) : static
    {
        $this->provider = $provider;
        return $this;
    }
}