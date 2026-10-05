<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Controllers\AdminController;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Psr7\Response as Psr7Response;


class AdminHeadersTest extends AdminTestAbstract
{
    public function testBuildHeadersNoRange()
    {
        $headers = $this->headers( 200, ['Content-Type' => 'video/mp4', 'Content-Length' => '5000'] );

        $this->assertEquals( 5000, $headers['Content-Length'] );
        $this->assertEquals( 'video/mp4', $headers['Content-Type'] );
        $this->assertArrayNotHasKey( 'Content-Range', $headers );
    }


    public function testBuildHeadersExceedsMax()
    {
        $maxBytes = 1024 * 1024;
        $rawLength = 2 * 1024 * 1024;
        $headers = $this->headers( 200, ['Content-Type' => 'video/mp4', 'Content-Length' => (string) $rawLength], $maxBytes );

        $this->assertEquals( $maxBytes, $headers['Content-Length'] );
        $this->assertEquals( "bytes 0-" . ( $maxBytes - 1 ) . "/$rawLength", $headers['Content-Range'] );
    }


    public function testBuildHeadersWithRange()
    {
        $headers = $this->headers( 206, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => '1000',
            'Content-Range' => 'bytes 0-999/10000',
        ] );

        $this->assertEquals( 1000, $headers['Content-Length'] );
        $this->assertEquals( 'bytes 0-999/10000', $headers['Content-Range'] );
    }


    public function testBuildHeadersWithOpenRange()
    {
        $maxBytes = 1024 * 1024;
        $rawLength = 5 * 1024 * 1024;
        $headers = $this->headers( 206, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => (string) ( $rawLength - 100 ),
            'Content-Range' => 'bytes 100-' . ( $rawLength - 1 ) . "/$rawLength",
        ], $maxBytes );

        $expectedEnd = 100 + $maxBytes - 1;
        $this->assertEquals( $maxBytes, $headers['Content-Length'] );
        $this->assertEquals( "bytes 100-$expectedEnd/$rawLength", $headers['Content-Range'] );
    }


    public function testProxySmallOpenRange()
    {
        $response = $this->proxy( 206, [
            'Content-Type' => 'video/mp4',
            'Content-Length' => '1000',
            'Content-Range' => 'bytes 0-999/1000',
        ], 'bytes=0-' );

        $response->assertStatus( 206 );
        $this->assertEquals( '1000', $response->headers->get( 'Content-Length' ) );
        $this->assertEquals( 'bytes 0-999/1000', $response->headers->get( 'Content-Range' ) );
    }


    public function testProxyUpstreamIgnoresRange()
    {
        $response = $this->proxy( 200, ['Content-Type' => 'video/mp4', 'Content-Length' => '1000'], 'bytes=100-' );

        $response->assertStatus( 200 );
        $this->assertEquals( '1000', $response->headers->get( 'Content-Length' ) );
        $this->assertFalse( $response->headers->has( 'Content-Range' ) );
    }


    /**
     * Returns the proxy headers for the given upstream response.
     *
     * @param array<string, string> $headers Upstream headers
     * @return array<string, mixed>
     */
    protected function headers( int $status, array $headers, int $maxsize = 10485760 ) : array
    {
        $controller = new AdminController();
        $method = new \ReflectionMethod( $controller, 'buildHeaders' );

        return $method->invoke( $controller, new ClientResponse( new Psr7Response( $status, $headers ) ), $maxsize );
    }


    /**
     * Proxies a range request to a faked upstream returning a 1000 byte body.
     *
     * @param array<string, string> $headers Upstream headers
     */
    protected function proxy( int $status, array $headers, string $range ) : \Illuminate\Testing\TestResponse
    {
        config( ['cms.allow-internal' => true] );

        Http::fake( ['localhost/*' => Http::response( str_repeat( 'x', 1000 ), $status, $headers )] );

        $user = new \App\Models\User( ['name' => 'Admin', 'email' => 'admin@testbench', 'password' => 'secret'] );
        $token = app( \Aimeos\Cms\ProxyToken::class )->make( $user );

        return $this->actingAs( $user )->withHeaders( ['Range' => $range] )
            ->get( route( 'cms.proxy', ['token' => $token, 'url' => 'https://localhost/video.mp4'] ) );
    }
}
