<?php

namespace Tests\Providers\Audio;

use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class DeepgramTest extends TestCase
{
    use MakesPrismaRequests;


    public function testSpeak() : void
    {
        $response = $this->prisma( 'audio', 'deepgram', ['api_key' => 'test'] )
            ->response( 'MP3' )
            ->ensure( 'speak' )
            ->speak( 'This is a test.', 'test' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.deepgram.com/v1/speak?model=test', (string) $request->getUri() );
        } );

        $this->assertEquals( 'MP3', $response->binary() );
    }


    public function testTranscribe() : void
    {
        $response = $this->prisma( 'audio', 'deepgram', ['api_key' => 'test'] )
            ->response( '{
                "results": {
                    "channels": [{
                        "alternatives": [{
                            "transcript": "Hello",
                            "confidence": 1.1,
                            "paragraphs": {
                                "transcript": "Hello",
                                "paragraphs": [{
                                    "sentences": [{
                                        "text": "Hello",
                                        "start": 1.1,
                                        "end": 1.1
                                    }],
                                    "speaker": 1.1,
                                    "num_words": 1.1,
                                    "start": 1.1,
                                    "end": 1.1
                                }]
                            }
                        }]
                    }]
                }
            }' )
            ->ensure( 'transcribe' )
            ->transcribe( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.deepgram.com/v1/listen?model=nova-3&language=en', (string) $request->getUri() );
        } );

        $this->assertEquals( 'Hello', $response->text() );
    }
}
