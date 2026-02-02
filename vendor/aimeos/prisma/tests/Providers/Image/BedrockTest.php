<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class BedrockTest extends TestCase
{
    use MakesPrismaRequests;


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'bedrock', ['api_key' => 'test'] )
            ->response( '{
                "images": [
                    "' . base64_encode( 'PNG' ) . '"
                ],
                "error": "only on failure"
            }' )
            ->ensure( 'imagine' )
            ->imagine( 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'Authorization' ) );
            $this->assertEquals( 'https://bedrock-runtime.us-east-1.amazonaws.com/model/amazon.titan-image-generator-v2:0/invoke', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testInpaint() : void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC';
        $file = $this->prisma( 'image', 'bedrock', ['api_key' => 'test'] )
            ->response( '{
                "images": [
                    "' . base64_encode( 'PNG' ) . '"
                ],
                "error": "only on failure"
            }' )
            ->ensure( 'inpaint' )
            ->inpaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBase64( $base64, 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://bedrock-runtime.us-east-1.amazonaws.com/model/amazon.titan-image-generator-v2:0/invoke', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testIsolate() : void
    {
        $file = $this->prisma( 'image', 'bedrock', ['api_key' => 'test'] )
            ->response( '{
                "images": [
                    "' . base64_encode( 'PNG' ) . '"
                ],
                "error": "only on failure"
            }' )
            ->ensure( 'isolate' )
            ->isolate( Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://bedrock-runtime.us-east-1.amazonaws.com/model/amazon.titan-image-generator-v2:0/invoke', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testVectorize() : void
    {
        $response = $this->prisma( 'image', 'bedrock', ['api_key' => 'test'] )
            ->response( json_encode( [
                'embedding' => [0.1, 0.2, 0.3],
                'metadata' => []
            ] ) )
            ->ensure( 'vectorize' )
            ->vectorize( [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://bedrock-runtime.us-east-1.amazonaws.com/model/amazon.titan-embed-image-v1/invoke', (string) $request->getUri() );
        } );

        $this->assertEquals( [[0.1, 0.2, 0.3]], $response->vectors() );
    }
}
