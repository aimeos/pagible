<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class StabilityaiTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['STABILITYAI_API_KEY'] ) ) {
            $this->markTestSkipped( 'STABILITYAI_API_KEY is not defined in the environment' );
        }
    }


    public function testErase() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/photo.jpg' );
        $mask = Image::fromLocalPath( __DIR__ . '/assets/mask-erase.png' );

        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'erase' )
            ->erase( $image, $mask );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_erase.png', $response->binary() );
    }


    public function testImagine() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'imagine' )
            ->imagine( 'a cartoon dog', [$image] );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_imagine.png', $response->binary() );
    }


    public function testInpaint() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $mask = Image::fromLocalPath( __DIR__ . '/assets/mask.png' );

        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'inpaint' )
            ->inpaint( $image, $mask, 'add eye glasses' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_inpaint.png', $response->binary() );
    }


    public function testIsolate() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'isolate' )
            ->isolate( $image );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_isolate.png', $response->binary() );
    }


    public function testUncrop() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/photo.jpg' );
        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'uncrop' )
            ->uncrop( $image, 0, 200, 0, 0 );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_uncrop.png', $response->binary() );
    }


    public function testUpscale() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'stabilityai', ['api_key' => $_ENV['STABILITYAI_API_KEY']])
            ->ensure( 'upscale' )
            ->upscale( $image, 2 );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/stabilityai_upscale.png', $response->binary() );
    }
}
