<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class CohereTest extends TestCase
{
    use MakesPrismaRequests;


    public function testVectorize() : void
    {
        $response = $this->prisma( 'image', 'cohere', ['api_key' => 'test'] )
            ->response( '{
                "id": "da6e531f-54c6-4a73-bf92-f60566d8d753",
                "embeddings": {
                    "float": [
                        [0.1, 0.2, 0.3]
                    ]
                },
                "meta": {
                    "api_version": {
                        "version": "2",
                        "is_experimental": true
                    },
                    "billed_units": {
                        "images": 1
                    },
                    "warnings": [
                        "You are using an experimental version"
                    ]
                }
            }' )
            ->ensure( 'vectorize' )
            ->vectorize( [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'Authorization' ) );
            $this->assertEquals( 'https://api.cohere.ai/v2/embed', (string) $request->getUri() );
        } );

        $this->assertEquals( [[0.1, 0.2, 0.3]], $response->vectors() );
    }
}
