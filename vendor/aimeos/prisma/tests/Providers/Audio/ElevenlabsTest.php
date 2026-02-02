<?php

namespace Tests\Providers\Audio;

use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class ElevenlabsTest extends TestCase
{
    use MakesPrismaRequests;


    public function testRevoice() : void
    {
        $response = $this->prisma( 'audio', 'elevenlabs', ['api_key' => 'test'] )
            ->response( 'MP3' )
            ->ensure( 'revoice' )
            ->revoice( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'JBFqnCBsd6RMkjVDRZzb' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.elevenlabs.io/v1/speech-to-speech/JBFqnCBsd6RMkjVDRZzb', (string) $request->getUri() );
        } );

        $this->assertEquals( 'MP3', $response->binary() );
    }


    public function testSpeak() : void
    {
        $response = $this->prisma( 'audio', 'elevenlabs', ['api_key' => 'test'] )
            ->response( 'MP3' )
            ->ensure( 'speak' )
            ->speak( 'This is a test.', 'test' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.elevenlabs.io/v1/text-to-speech/test', (string) $request->getUri() );
        } );

        $this->assertEquals( 'MP3', $response->binary() );
    }


    public function testTranscribe() : void
    {
        $response = $this->prisma( 'audio', 'elevenlabs', ['api_key' => 'test'] )
            ->response( '{
                "language_code": "en",
                "language_probability": 0.98,
                "text": "Hello",
                "words": [{
                    "text": "Hello",
                    "start": 0,
                    "end": 0.5,
                    "type": "word",
                    "speaker_id": "speaker_1",
                    "logprob": -0.124
                }]
            }' )
            ->ensure( 'transcribe' )
            ->transcribe( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.elevenlabs.io/v1/speech-to-text', (string) $request->getUri() );
        } );

        $this->assertEquals( 'Hello', $response->text() );
    }
}
