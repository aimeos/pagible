<?php

namespace Tests\Providers\Audio;

use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class MistralTest extends TestCase
{
    use MakesPrismaRequests;


    public function testDescribe() : void
    {
        $prisma = $this->prisma( 'audio', 'mistral', ['api_key' => 'test'] );
        $prisma->response( json_encode( [
            'text' => 'an audio description'
        ] ) );
        $response = $prisma->response( json_encode( [
            'choices' => [[
                'message' => [
                    'content' => 'an audio description'
                ]
            ]]
        ] ) )
            ->ensure( 'describe' )
            ->describe( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.mistral.ai/v1/audio/transcriptions', (string) $request->getUri() );
        } );

        $this->assertEquals( 'an audio description', $response->text() );
    }


    public function testTranscribe() : void
    {
        $response = $this->prisma( 'audio', 'mistral', ['api_key' => 'test'] )
            ->response( '{
                "model": "voxtral-mini-2507",
                "text": "A test file",
                "language": "en",
                "segments": [],
                "usage": {
                    "prompt_audio_seconds": 203,
                    "prompt_tokens": 4,
                    "total_tokens": 3264,
                    "completion_tokens": 635
                }
                }' )
            ->ensure( 'transcribe' )
            ->transcribe( Audio::fromBinary( 'MP3', 'audio/mpeg' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'authorization' ) );
            $this->assertEquals( 'https://api.mistral.ai/v1/audio/transcriptions', (string) $request->getUri() );
        } );

        $this->assertEquals( "A test file", $response->text() );
    }
}
