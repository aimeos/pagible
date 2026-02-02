<?php

namespace Aimeos\Prisma;

use Aimeos\Prisma\Contracts\Provider;
use Aimeos\Prisma\Exceptions\NotImplementedException;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Providers\Fake;


/**
 * Factory class for Prisma providers.
 */
class Prisma
{
    private static ?Fake $fake = null;
    private string $type;


    /**
     * Creates a new Prisma factory instance for the specified provider type.
     *
     * @param string $type Provider type
     */
    public function __construct( string $type )
    {
        $this->type = $type;
    }


    /**
     * Create a new provider by name.
     *
     * @param string|null $name Provider name in lower case
     * @param array<string, mixed> $config Configuration parameter for the provider
     * @return Provider Provider instance
     */
    public function using( ?string $name, array $config = [] ) : Provider
    {
        if( !$name ) {
            throw new PrismaException( 'No provider name given' );
        }

        $classname = '\\Aimeos\\Prisma\\Providers\\' . ucfirst( $this->type ) . '\\' . ucfirst( $name );

        if( !class_exists( $classname ) ) {
            throw new NotImplementedException( sprintf( 'Provider "%1$s" not found', $classname ) );
        }

        $provider = new $classname( $config );

        if( !( $provider instanceof Provider ) ) {
            throw new NotImplementedException( sprintf( 'Provider "%1$s" does not implement "%2$s"', $classname, Provider::class ) );
        }

        return self::$fake ? self::$fake->use( $provider ) : $provider;
    }


    /**
     * Sets a fake provider for testing purposes.
     *
     * @param array<int, \GuzzleHttp\Psr7\Response> $responses Responses to return for the fake provider
     * @return void
     */
    public static function fake( array $responses = [] ) : void
    {
        self::$fake = new Fake( $responses );
    }


    /**
     * Creates a new Prisma factory instance for audio processing.
     *
     * @return self
     */
    public static function audio() : self
    {
        return new self( 'audio' );
    }


    /**
     * Creates a new Prisma factory instance for image processing.
     *
     * @return self
     */
    public static function image() : self
    {
        return new self( 'image' );
    }


    /**
     * Tests if the specified provider supports the given method.
     *
     * @param string $type Provider type
     * @param string $provider Provider name
     * @param string $method Method name
     * @param array<string, mixed> $config Configuration parameter for the provider
     * @return bool TRUE if supported, FALSE if not
     */
    public static function supports( string $type, string $provider, string $method, array $config ) : bool
    {
        try {
            return ( new self( $type ) )->using( $provider, $config )->has( $method );
        } catch( PrismaException ) {
            return false;
        }
    }
}
