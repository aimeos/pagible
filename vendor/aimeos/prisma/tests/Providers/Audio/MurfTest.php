<?php

namespace Tests\Providers\Audio;

use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class MurfTest extends TestCase
{
    use MakesPrismaRequests;


    public function testRevoice() : void
    {
        $response = $this->prisma( 'audio', 'murf', ['api_key' => 'test'] )
            ->response( '{
                "audio_file": "https://murf.ai/link/to/audio/file",
                "audio_length_in_seconds": 8.75,
                "remaining_character_count": 992150
            }' )
            ->ensure( 'revoice' )
            ->revoice( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'en-US-terrell' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.murf.ai/v1/voice-changer/convert', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://murf.ai/link/to/audio/file', $response->url() );
    }


    public function testSpeak() : void
    {
        $response = $this->prisma( 'audio', 'murf', ['api_key' => 'test'] )
            ->response( '{
                "audioFile": "https://murf.ai/link/to/audio/file",
                "audioLengthInSeconds": 1.1,
                "remainingCharacterCount": 1,
                "wordDurations": [{
                    "endMs": 1,
                    "startMs": 1,
                    "word": "string",
                    "pitchScaleMaximum": 1.1,
                    "pitchScaleMinimum": 1.1,
                    "sourceWordIndex": 1
                }],
                "encodedAudio": "string",
                "warning": "string",
                "consumedCharacterCount": 1
            }' )
            ->ensure( 'speak' )
            ->speak( 'This is a test.', 'test' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.murf.ai/v1/speech/generate', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://murf.ai/link/to/audio/file', $response->url() );
    }
}
