<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class VertexaiTest extends TestCase
{
    use MakesPrismaRequests;


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'vertexai', ['access_token' => 'test', 'project_id' => '123'] )
            ->response( json_encode( [
                'predictions' => [[
                    'mimeType' => 'image/png',
                    'bytesBase64Encoded' => base64_encode( 'PNG' )
                ]]
            ] ) )
            ->ensure( 'imagine' )
            ->imagine( 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://aiplatform.googleapis.com/v1/projects/123/locations/global/publishers/google/models/imagen-4.0-generate-001:predict', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testInpaint() : void
    {
        $file = $this->prisma( 'image', 'vertexai', ['access_token' => 'test', 'project_id' => '123'] )
            ->response( json_encode( [
                'predictions' => [[
                    'mimeType' => 'image/png',
                    'bytesBase64Encoded' => base64_encode( 'PNG' )
                ]]
            ] ) )
            ->ensure( 'inpaint' )
            ->inpaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBinary( 'PNG', 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://aiplatform.googleapis.com/v1/projects/123/locations/global/publishers/google/models/imagen-3.0-capability-001:predict', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testUpscale() : void
    {
        $file = $this->prisma( 'image', 'vertexai', ['access_token' => 'test', 'project_id' => '123'] )
            ->response( json_encode( [
                'predictions' => [[
                    'mimeType' => 'image/png',
                    'bytesBase64Encoded' => base64_encode( 'PNG' )
                ]]
            ] ) )
            ->ensure( 'upscale' )
            ->upscale( Image::fromBinary( 'PNG', 'image/png' ), 5 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://aiplatform.googleapis.com/v1/projects/123/locations/global/publishers/google/models/imagen-4.0-upscale-preview:predict', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testVectorize() : void
    {
        $response = $this->prisma( 'image', 'vertexai', ['access_token' => 'test', 'project_id' => '123'] )
            ->response( json_encode( [
                'predictions' => [[
                    'imageEmbedding' => [0.1, 0.2, 0.3]
                ]]
            ] ) )
            ->ensure( 'vectorize' )
            ->vectorize( [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://aiplatform.googleapis.com/v1/projects/123/locations/global/publishers/google/models/multimodalembedding@001:predict', (string) $request->getUri() );
        } );

        $this->assertEquals( [[0.1, 0.2, 0.3]], $response->vectors() );
    }
}
