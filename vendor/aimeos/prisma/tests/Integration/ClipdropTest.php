<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class ClipdropTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['CLIPDROP_API_KEY'] ) ) {
            $this->markTestSkipped( 'CLIPDROP_API_KEY is not defined in the environment' );
        }
    }


    public function testBackground() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/room.jpg' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'background' )
            ->background( $image, 'beach at sunset' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_background.png', $response->binary() );
    }


    public function testDetext() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/detext.jpg' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'detext' )
            ->detext( $image );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_detext.png', $response->binary() );
    }


    public function testErase() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/photo.jpg' );
        $mask = Image::fromLocalPath( __DIR__ . '/assets/mask-erase.png' );

        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'erase' )
            ->erase( $image, $mask );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_erase.png', $response->binary() );
    }


    public function testImagine() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'imagine' )
            ->imagine( 'a cartoon dog', [$image] );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_imagine.png', $response->binary() );
    }


    public function testIsolate() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'isolate' )
            ->isolate( $image );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_isolate.png', $response->binary() );
    }


    public function testUncrop() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/photo.jpg' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'uncrop' )
            ->uncrop( $image, 0, 200, 0, 0 );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_uncrop.png', $response->binary() );
    }


    public function testUpscale() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'clipdrop', ['api_key' => $_ENV['CLIPDROP_API_KEY']])
            ->ensure( 'upscale' )
            ->upscale( $image, 2 );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/clipdrop_upscale.png', $response->binary() );
    }
}
