<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class ClipdropTest extends TestCase
{
    use MakesPrismaRequests;


    public function testBackground() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'background' )
            ->background( Image::fromBinary( 'PNG', 'image/png' ), 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'test', $request->getHeaderLine( 'x-api-key' ) );
            $this->assertEquals( 'https://clipdrop-api.co/replace-background/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testDetext() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'detext' )
            ->detext( Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/remove-text/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testErase() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'erase' )
            ->erase( Image::fromBinary( 'PNG', 'image/png' ), Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/cleanup/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testImagine() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'imagine' )
            ->imagine( 'prompt' );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/text-to-image/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testIsolate() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'isolate' )
            ->isolate( Image::fromBinary( 'PNG', 'image/png' ) );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/remove-background/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testUncrop() : void
    {
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'uncrop' )
            ->uncrop( Image::fromBinary( 'PNG', 'image/png' ), 100, 0, 0, 0 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/uncrop/v1', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }


    public function testUpscale() : void
    {
        $base64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC';
        $file = $this->prisma( 'image', 'clipdrop', ['api_key' => 'test'] )
            ->response( 'PNG', ['Content-Type' => 'image/png', 'x-credits-consumed' => 1, 'x-remaining-credits' => 99] )
            ->ensure( 'upscale' )
            ->upscale( Image::fromBase64( $base64, 'image/png' ), 2 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://clipdrop-api.co/image-upscaling/v1/upscale', (string) $request->getUri() );
        } );

        $this->assertEquals( 'PNG', $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
        $this->assertEquals( ['used' => 1, 'x-remaining-credits' => 99], $file->usage() );
    }
}
