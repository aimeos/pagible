<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class GeminiTest extends TestCase
{
    use MakesPrismaRequests;


    public function testDescribe() : void
    {
        $response = $this->prisma( 'image', 'gemini', ['api_key' => 'test'] )
            ->response( json_encode( [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => 'an image description'
                        ]]
                    ]
                ]]
            ] ) )
            ->ensure( 'describe' )
            ->describe( Image::fromBinary( 'PNG', 'image/png' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent', (string) $request->getUri() );
        } );

        $this->assertEquals( 'an image description', $response->text() );
    }


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'gemini', ['api_key' => 'test'] )
            ->response( json_encode( [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode( 'PNG' )
                            ]
                        ], [
                            'text' => 'PNG image'
                        ]]
                    ]
                ]]
            ] ) )
            ->ensure( 'imagine' )
            ->imagine( 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'test', $request->getHeaderLine( 'x-goog-api-key' ) );
            $this->assertEquals( 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3-pro-image-preview:generateContent', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testRepaint() : void
    {
        $file = $this->prisma( 'image', 'gemini', ['api_key' => 'test'] )
            ->response( json_encode( [
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'inlineData' => [
                                'mimeType' => 'image/png',
                                'data' => base64_encode( 'PNG' )
                            ]
                        ]]
                    ]
                ]]
            ] ) )
            ->ensure( 'repaint' )
            ->repaint( Image::fromBinary( 'PNG', 'image/png' ), 'prompt' );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }
}
