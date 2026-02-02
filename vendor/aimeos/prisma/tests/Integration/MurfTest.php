<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;


class MurfTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['MURFAI_API_KEY'] ) ) {
            $this->markTestSkipped( 'MURFAI_API_KEY is not defined in the environment' );
        }
    }


    public function testRevoice() : void
    {
        $response = Prisma::audio()
            ->using( 'murf', ['api_key' => $_ENV['MURFAI_API_KEY']])
            ->ensure( 'revoice' )
            ->revoice( Audio::fromLocalPath( __DIR__ . '/assets/hello.mp3' ), 'en-US-terrell' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/murf_revoice.mp3', $response->binary() );
    }


    public function testSpeak() : void
    {
        $response = Prisma::audio()
            ->using( 'murf', ['api_key' => $_ENV['MURFAI_API_KEY']])
            ->ensure( 'speak' )
            ->speak( 'This is a test.' );

        $this->assertNotNull( $response->url() );

        file_put_contents( __DIR__ . '/results/murf_speak.mp3', $response->binary() );
    }
}
