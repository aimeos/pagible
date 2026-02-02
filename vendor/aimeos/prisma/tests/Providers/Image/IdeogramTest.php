<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class IdeogramTest extends TestCase
{
    use MakesPrismaRequests;


    public function testBackground() : void
    {
        $file = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "created": "2000-01-23 04:56:07+00:00",
                "data": [{
                    "prompt": "A photo of a cat",
                    "resolution": "1280x800",
                    "is_image_safe": true,
                    "seed": 12345,
                    "url": "https://placehold.co/10x10.png",
                    "style_type": "GENERAL"
                }]
            }' )
            ->ensure( 'background' )
            ->background( Image::fromBinary( 'PNG', 'image/png' ), 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'test', $request->getHeaderLine( 'Api-Key' ) );
            $this->assertEquals( 'https://api.ideogram.ai/v1/ideogram-v3/replace-background', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://placehold.co/10x10.png', $file->url() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( 'A photo of a cat', $file->description() );
        $this->assertEquals( [
            "prompt" => "A photo of a cat",
            "resolution" => "1280x800",
            "is_image_safe" => true,
            "seed" => 12345,
            "url" => "https://placehold.co/10x10.png",
            "style_type" => "GENERAL",
            "created" => "2000-01-23 04:56:07+00:00"
        ], $file->meta() );
    }


    public function testDescribe() : void
    {
        $response = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "descriptions": [{
                    "text": "an image description"
                }]
            }' )
            ->ensure( 'describe' )
            ->describe( Image::fromBinary( 'PNG', 'image/png' ), 'en' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.ideogram.ai/describe', (string) $request->getUri() );
        } );

        $this->assertEquals( 'an image description', $response->text() );
    }


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "data": [{
                    "url": "https://placehold.co/10x10.png"
                }]
            }' )
            ->ensure( 'imagine' )
            ->imagine( 'prompt', [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.ideogram.ai/v1/ideogram-v3/generate', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://placehold.co/10x10.png', $file->url() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testInpaint() : void
    {
        $file = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "data": [{
                    "url": "https://placehold.co/10x10.png"
                }]
            }' )
            ->ensure( 'inpaint' )
            ->inpaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBinary( 'PNG', 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.ideogram.ai/v1/ideogram-v3/edit', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://placehold.co/10x10.png', $file->url() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testRepaint() : void
    {
        $file = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "data": [{
                    "url": "https://placehold.co/10x10.png"
                }]
            }' )
            ->ensure( 'repaint' )
            ->repaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.ideogram.ai/v1/ideogram-v3/remix', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://placehold.co/10x10.png', $file->url() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testUpscale() : void
    {
        $file = $this->prisma( 'image', 'ideogram', ['api_key' => 'test'] )
            ->response( '{
                "data": [{
                    "url": "https://placehold.co/10x10.png"
                }]
            }' )
            ->ensure( 'upscale' )
            ->upscale( Image::fromBinary( 'PNG', 'image/png' ), 2 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.ideogram.ai/upscale', (string) $request->getUri() );
        } );

        $this->assertEquals( 'https://placehold.co/10x10.png', $file->url() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }
}
