<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class StabilityaiTest extends TestCase
{
    use MakesPrismaRequests;


    public function testErase() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'erase' )
            ->erase(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBinary( 'PNG', 'image/png' )
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'Bearer test', $request->getHeaderLine( 'authorization' ) );
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/edit/erase', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'imagine' )
            ->imagine( 'prompt', [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/generate/ultra', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testInpaint() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'inpaint' )
            ->inpaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBinary( 'PNG', 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/edit/inpaint', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testIsolate() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'isolate' )
            ->isolate( Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/edit/remove-background', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testUncrop() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'uncrop' )
            ->uncrop( Image::fromBinary( 'PNG', 'image/png' ), 100, 0, 0, 0 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/edit/outpaint', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testUpscale() : void
    {
        $file = $this->prisma( 'image', 'stabilityai', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png'] )
            ->ensure( 'upscale' )
            ->upscale( Image::fromBinary( 'PNG', 'image/png' ), 2 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.stability.ai/v2beta/stable-image/upscale/conservative', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }
}
