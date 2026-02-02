<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class GroqTest extends TestCase
{
    use MakesPrismaRequests;


    public function testDescribe() : void
    {
        $response = $this->prisma( 'image', 'openai', ['api_key' => 'test'] )
            ->response( '{
                "id": "resp_abc123",
                "status": "completed",
                "model": "meta-llama/llama-4-scout-17b-16e-instruct",
                "output": [{
                    "type": "message",
                    "role": "assistant",
                    "content": [{
                        "type": "output_text",
                        "text": "an image description"
                    }]
                }],
                "usage": {
                    "total_tokens": 154
                }
            }' )
            ->ensure( 'describe' )
            ->describe( Image::fromBinary( 'PNG', 'image/png' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.openai.com/v1/responses', (string) $request->getUri() );
        } );

        $this->assertEquals( 'an image description', $response->text() );
    }
}
