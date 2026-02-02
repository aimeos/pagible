<?php

namespace Tests\Providers\Image;

use Aimeos\Prisma\Files\Image;
use PHPUnit\Framework\TestCase;
use Tests\MakesPrismaRequests;


class BlackforestlabsTest extends TestCase
{
    use MakesPrismaRequests;


    public function testImagine() : void
    {
        $prisma = $this->prisma( 'image', 'blackforestlabs', ['api_key' => 'test'] );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Processing",
            "polling_url": "https://localhost/poll"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Processing"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Ready",
            "sample": "https://localhost/test.png"
        }' );

        $png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC' );
        $file = $prisma->response( $png, ['Content-Type' => 'image/png'] )
            ->ensure( 'imagine' )
            ->imagine( 'prompt', [Image::fromBinary( 'PNG', 'image/png' )] );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'POST', $request->getMethod() );
            $this->assertEquals( 'test', $request->getHeaderLine( 'x-key' ) );
            $this->assertEquals( 'https://api.bfl.ai/v1/flux-2-pro', (string) $request->getUri() );
        } );

        $this->assertFalse( $file->ready() );
        $this->assertEquals( $png, $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testInpaint() : void
    {
        $prisma = $this->prisma( 'image', 'blackforestlabs', ['api_key' => 'test'] );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "processing",
            "polling_url": "https://localhost/poll"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Processing"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Ready",
            "sample": "https://localhost/test.png"
        }' );

        $png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC' );
        $file = $prisma->response( $png, ['Content-Type' => 'image/png'] )
            ->ensure( 'inpaint' )
            ->inpaint(
                Image::fromBinary( 'PNG', 'image/png' ),
                Image::fromBinary( 'PNG', 'image/png' ),
                'prompt'
            );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.bfl.ai/v1/flux-pro-1.0-fill', (string) $request->getUri() );
        } );

        $this->assertFalse( $file->ready() );
        $this->assertEquals( $png, $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }


    public function testUncrop() : void
    {
        $prisma = $this->prisma( 'image', 'blackforestlabs', ['api_key' => 'test'] );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Processing",
            "polling_url": "https://localhost/poll"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Processing"
        }' );
        $prisma->response( '{
            "id": "image_1234567890",
            "status": "Ready",
            "sample": "https://localhost/test.png"
        }' );

        $png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQI12NgYGAAAAAEAAEnNCcKAAAAAElFTkSuQmCC' );
        $file = $prisma->response( $png, ['Content-Type' => 'image/png'] )
            ->ensure( 'uncrop' )
            ->uncrop( Image::fromBinary( 'PNG', 'image/png' ), 100, 0, 0, 0 );

        $this->assertPrismaRequest( function( $request, $options ) {
            $this->assertEquals( 'https://api.bfl.ai/v1/flux-pro-1.0-expand', (string) $request->getUri() );
        } );

        $this->assertFalse( $file->ready() );
        $this->assertEquals( $png, $file->binary() );
        $this->assertEquals( 'image/png', $file->mimeType() );
    }
}
