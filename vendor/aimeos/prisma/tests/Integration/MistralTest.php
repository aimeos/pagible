<?php

namespace Tests\Integration;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Files\Audio;
use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;


class MistralTest extends TestCase
{
    protected function setUp() : void
    {
        \Dotenv\Dotenv::createImmutable( dirname( __DIR__, 2 ) )->load();

        if( empty( $_ENV['MISTRAL_API_KEY'] ) ) {
            $this->markTestSkipped( 'MISTRAL_API_KEY is not defined in the environment' );
        }
    }


    public function testDescribeAudio() : void
    {
        $audio = Audio::fromLocalPath( __DIR__ . '/assets/hello.mp3' );
        $response = Prisma::audio()
            ->using( 'mistral', ['api_key' => $_ENV['MISTRAL_API_KEY']])
            ->ensure( 'describe' )
            ->describe( $audio );

        $this->assertStringContainsStringIgnoringCase( 'greeting', $response->text() );
    }


    public function testTranscribe() : void
    {
        $audio = Audio::fromLocalPath( __DIR__ . '/assets/hello.mp3' );
        $response = Prisma::audio()
            ->using( 'mistral', ['api_key' => $_ENV['MISTRAL_API_KEY']])
            ->ensure( 'transcribe' )
            ->transcribe( $audio );

        $this->assertStringContainsString( 'Hello', $response->text() );
    }


    public function testRecognize() : void
    {
        $audio = Image::fromLocalPath( __DIR__ . '/assets/text.png' );
        $response = Prisma::audio()
            ->using( 'mistral', ['api_key' => $_ENV['MISTRAL_API_KEY']])
            ->ensure( 'recognize' )
            ->recognize( $audio );

        $this->assertEquals( 'This is text', $response->text() );
    }
}
