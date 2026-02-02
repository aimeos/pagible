<?php

namespace Tests\Providers\Audio;

use Aimeos\Prisma\Files\Audio;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class GeminiTest extends TestCase
{
    use MakesPrismaRequests;


    public function testDescribe() : void
    {
        $response = $this->prisma( 'audio', 'gemini', ['api_key' => 'test'] )
            ->response( json_encode( [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'an audio description'
                        ]]
                    ]
                ]]
            ] ) )
            ->ensure( 'describe' )
            ->describe( Audio::fromBinary( 'MP3', 'audio/mpeg' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', (string) $request->getUri() );
        } );

        $this->assertEquals( 'an audio description', $response->text() );
    }
}
