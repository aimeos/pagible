<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class MistralTest extends TestCase
{
    use MakesPrismaRequests;


    public function testRecognize() : void
    {
        $response = $this->prisma( 'image', 'mistral', ['api_key' => 'test'] )
            ->response( '{
                "pages": [{
                    "index": 1,
                    "markdown": "A test document\n",
                    "images": [],
                    "dimensions": {
                        "dpi": 200,
                        "height": 2200,
                        "width": 1700
                    }
                }, {
                    "index": 2,
                    "markdown": "![img-0.jpeg](img-0.jpeg)\nFigure 1: Illustration\n",
                    "images": [
                        {
                        "id": "img-0.jpeg",
                        "top_left_x": 292,
                        "top_left_y": 217,
                        "bottom_right_x": 1405,
                        "bottom_right_y": 649,
                        "image_base64": "..."
                        }
                    ],
                    "dimensions": {
                        "dpi": 200,
                        "height": 2200,
                        "width": 1700
                    }
                }],
                "model": "mistral-ocr-2503-completion",
                "usage_info": {
                    "pages_processed": 2,
                    "doc_size_bytes": null
                }
            }' )
            ->ensure( 'recognize' )
            ->recognize( Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'authorization' ) );
            $this->assertEquals( 'https://api.mistral.ai/v1/ocr', (string) $request->getUri() );
        } );

        $this->assertEquals( "A test document\n\n\n![img-0.jpeg](img-0.jpeg)\nFigure 1: Illustration", $response->text() );
    }
}
