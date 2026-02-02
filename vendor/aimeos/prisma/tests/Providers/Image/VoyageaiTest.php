<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class VoyageaiTest extends TestCase
{
    use MakesPrismaRequests;


    public function testVectorize() : void
    {
        $response = $this->prisma( 'image', 'voyageai', ['api_key' => 'test'] )
            ->response( '{
                "object": "list",
                "data": [
                    {
                        "object": "embedding",
                        "embedding": [0.1, 0.2, 0.3],
                        "index": 0
                    }
                ],
                "model": "voyage-multimodal-3",
                "usage": {
                    "text_tokens": 5,
                    "image_pixels": 2000000,
                    "total_tokens": 3576
                }
            }' )
            ->ensure( 'vectorize' )
            ->vectorize( [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'Authorization' ) );
            $this->assertEquals( 'https://api.voyageai.com/v1/multimodalembeddings', (string) $request->getUri() );
        } );

        $this->assertEquals( [[0.1, 0.2, 0.3]], $response->vectors() );
    }
}
