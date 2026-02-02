<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class VoyageaiTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['VOYAGEAI_API_KEY'] ) ) {
            $this->markTestSkipped( 'VOYAGEAI_API_KEY is not defined in the environment' );
        }
    }


    public function testVectorize() : void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC';
        $image = Image::fromBase64( $base64, 'image/png' );
        $response = Prisma::image()
            ->using( 'voyageai', ['api_key' => $_ENV['VOYAGEAI_API_KEY']])
            ->ensure( 'vectorize' )
            ->vectorize( [$image] );

        $this->assertCount( 1, $response->vectors() );
        $this->assertCount( 1024, $response->vectors()[0] );
    }
}
