<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;


class ElevenlabsTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['ELEVENLABS_API_KEY'] ) ) {
            $this->markTestSkipped( 'ELEVENLABS_API_KEY is not defined in the environment' );
        }
    }


    public function testRevoice() : void
    {
        $response = Prisma::audio()
            ->using( 'elevenlabs', ['api_key' => $_ENV['ELEVENLABS_API_KEY']])
            ->ensure( 'revoice' )
            ->revoice( Audio::fromLocalPath( __DIR__ . '/assets/hello.mp3' ), 'JBFqnCBsd6RMkjVDRZzb' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/elevenlabs_revoice.mp3', $response->binary() );
    }


    public function testSpeak() : void
    {
        $response = Prisma::audio()
            ->using( 'elevenlabs', ['api_key' => $_ENV['ELEVENLABS_API_KEY']])
            ->ensure( 'speak' )
            ->speak( 'This is a test.', 'test' );

        $this->assertGreaterThan( 0, strlen( $response->binary() ) );

        file_put_contents( __DIR__ . '/results/elevenlabs_speak.mp3', $response->binary() );
    }


    public function testTranscribe() : void
    {
        $audio = Audio::fromLocalPath( __DIR__ . '/assets/hello.mp3' );
        $response = Prisma::audio()
            ->using( 'elevenlabs', ['api_key' => $_ENV['ELEVENLABS_API_KEY']])
            ->ensure( 'transcribe' )
            ->transcribe( $audio );

        $this->assertStringContainsStringIgnoringCase( 'Hello', $response->text() );
    }
}
