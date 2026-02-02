<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class BedrockTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['BEDROCK_API_KEY'] ) ) {
            $this->markTestSkipped( 'BEDROCK_API_KEY is not defined in the environment' );
        }
    }


    public function testImagine() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'bedrock', ['api_key' => $_ENV['BEDROCK_API_KEY']])
            ->ensure( 'imagine' )
            ->imagine( 'a cartoon dog', [$image] );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/bedrock_imagine.png', $response->binary() );
    }


    public function testInpaint() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $mask = Image::fromLocalPath( __DIR__ . '/assets/mask.png' );

        $response = Prisma::image()
            ->using( 'bedrock', ['api_key' => $_ENV['BEDROCK_API_KEY']])
            ->ensure( 'inpaint' )
            ->inpaint( $image, $mask, 'add eye glasses' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/bedrock_inpaint.png', $response->binary() );
    }


    public function testIsolate() : void
    {
        $image = Image::fromLocalPath( __DIR__ . '/assets/cat.png' );
        $response = Prisma::image()
            ->using( 'bedrock', ['api_key' => $_ENV['BEDROCK_API_KEY']])
            ->ensure( 'isolate' )
            ->isolate( $image );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/bedrock_isolate.png', $response->binary() );
    }


    public function testVectorize() : void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC';
        $image = Image::fromBase64( $base64, 'image/png' );
        $response = Prisma::image()
            ->using( 'bedrock', ['api_key' => $_ENV['BEDROCK_API_KEY']])
            ->ensure( 'vectorize' )
            ->vectorize( [$image] );

        $this->assertCount( 1, $response->vectors() );
        $this->assertCount( 1024, $response->vectors()[0] );
    }
}
