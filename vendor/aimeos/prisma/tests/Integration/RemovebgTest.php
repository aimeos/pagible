<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class RemovebgTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['REMOVEBG_API_KEY'] ) ) {
            $this->markTestSkipped( 'REMOVEBG_API_KEY is not defined in the environment' );
        }
    }


    public function testIsolate() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'removebg', ['api_key' => $_ENV['REMOVEBG_API_KEY']])
            ->ensure( 'isolate' )
            ->isolate( $image );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/removebg_isolate.png', $response->binary() );
    }


    public function testRelocate() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/product.png' );
        $bgimage = Image::fromLocalPath( __DIR__ . '/assets/photo.jpg' );

        $response = Prisma::image()
            ->using( 'removebg', ['api_key' => $_ENV['REMOVEBG_API_KEY']])
            ->ensure( 'relocate' )
            ->relocate( $image, $bgimage );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/removebg_relocate.png', $response->binary() );
    }
}
