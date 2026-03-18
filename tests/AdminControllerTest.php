<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Controllers\AdminController;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Psr7\Response as Psr7Response;


class AdminControllerTest extends TestAbstract
{
    protected ?\App\Models\User $user = null;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = \App\Models\User::create( [
            'name' => 'Admin',
            'email' => 'admin@testbench',
            'password' => 'secret',
            'cmseditor' => PHP_INT_MAX,
        ] );
    }


    public function testIndex()
    {
        // Create the manifest file so the view can render
        $manifestDir = public_path( 'vendor/cms/admin/.vite' );
        $manifestPath = $manifestDir . '/manifest.json';

        if( !is_dir( $manifestDir ) ) {
            mkdir( $manifestDir, 0755, true );
        }

        file_put_contents( $manifestPath, json_encode( ['index.html' => ['file' => 'app.js', 'css' => []]] ) );

        try {
            $response = $this->actingAs( $this->user )->get( route( 'cms.admin' ) );

            $response->assertStatus( 200 );

            $csp = $response->headers->get( 'Content-Security-Policy' );
            $this->assertNotNull( $csp );
            $this->assertStringContainsString( "base-uri 'self'", $csp );
            $this->assertStringContainsString( "default-src 'self'", $csp );
            $this->assertStringContainsString( "script-src 'self'", $csp );
            $this->assertStringContainsString( "style-src 'self'", $csp );
            $this->assertStringContainsString( 'nonce-', $csp );
        } finally {
            @unlink( $manifestPath );
            @rmdir( $manifestDir );
            @rmdir( dirname( $manifestDir ) );
        }
    }


    public function testProxyOptions()
    {
        $response = $this->actingAs( $this->user )->options( route( 'cms.proxy' ) );

        $response->assertStatus( 204 );
        $this->assertEquals( '*', $response->headers->get( 'Access-Control-Allow-Origin' ) );
        $this->assertStringContainsString( 'GET', $response->headers->get( 'Access-Control-Allow-Methods' ) );
        $this->assertStringContainsString( 'OPTIONS', $response->headers->get( 'Access-Control-Allow-Methods' ) );
    }


    public function testProxyUnsupportedMethod()
    {
        $response = $this->actingAs( $this->user )->post( route( 'cms.proxy' ) );

        $response->assertStatus( 405 );
    }


    public function testProxyInvalidUrl()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['url' => 'not-a-url'] ) );

        $response->assertStatus( 400 );
    }


    public function testProxyMissingUrl()
    {
        Http::fake( fn() => throw new \Illuminate\Http\Client\ConnectionException( 'Connection failed' ) );

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy' ) );

        // Empty URL passes isValidUrl check (returns true for empty), then fetch fails
        $response->assertStatus( 504 );
    }


    public function testProxyConnectionException()
    {
        Http::fake( fn() => throw new \Illuminate\Http\Client\ConnectionException( 'Connection timed out' ) );

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 504 );
    }


    public function testProxyGetRequest()
    {
        $body = 'fake-media-content';

        Http::fake( [
            'example.com/*' => Http::response( $body, 200, [
                'Content-Type' => 'video/mp4',
                'Content-Length' => strlen( $body ),
            ] ),
        ] );

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 200 );
        $this->assertEquals( 'video/mp4', $response->headers->get( 'Content-Type' ) );
        $this->assertEquals( '*', $response->headers->get( 'Access-Control-Allow-Origin' ) );
        $this->assertEquals( 'bytes', $response->headers->get( 'Accept-Ranges' ) );
    }


    public function testBuildHeadersNoRange()
    {
        $controller = new AdminController();
        $psr = new Psr7Response( 200, ['Content-Type' => 'video/mp4', 'Content-Length' => '5000'] );
        $clientResponse = new ClientResponse( $psr );

        $method = new \ReflectionMethod( $controller, 'buildHeaders' );
        $method->setAccessible( true );

        $headers = $method->invoke( $controller, $clientResponse, null );

        $this->assertEquals( 5000, $headers['Content-Length'] );
        $this->assertEquals( 'video/mp4', $headers['Content-Type'] );
        $this->assertArrayNotHasKey( 'Content-Range', $headers );
    }


    public function testBuildHeadersExceedsMax()
    {
        config()->set( 'cms.proxy.max-length', 1 ); // 1 MB

        $controller = new AdminController();
        $rawLength = 2 * 1024 * 1024; // 2 MB
        $psr = new Psr7Response( 200, ['Content-Type' => 'video/mp4', 'Content-Length' => (string) $rawLength] );
        $clientResponse = new ClientResponse( $psr );

        $method = new \ReflectionMethod( $controller, 'buildHeaders' );
        $method->setAccessible( true );

        $headers = $method->invoke( $controller, $clientResponse, null );

        $maxBytes = 1024 * 1024;
        $this->assertEquals( $maxBytes, $headers['Content-Length'] );
        $this->assertArrayHasKey( 'Content-Range', $headers );
        $this->assertEquals( "bytes 0-" . ( $maxBytes - 1 ) . "/$rawLength", $headers['Content-Range'] );
    }


    public function testBuildHeadersWithRange()
    {
        $controller = new AdminController();
        $rawLength = 10000;
        $psr = new Psr7Response( 206, ['Content-Type' => 'video/mp4', 'Content-Length' => (string) $rawLength] );
        $clientResponse = new ClientResponse( $psr );

        $method = new \ReflectionMethod( $controller, 'buildHeaders' );
        $method->setAccessible( true );

        $headers = $method->invoke( $controller, $clientResponse, 'bytes=0-999' );

        $this->assertEquals( 1000, $headers['Content-Length'] );
        $this->assertEquals( "bytes 0-999/$rawLength", $headers['Content-Range'] );
    }


    public function testBuildHeadersWithOpenRange()
    {
        config()->set( 'cms.proxy.max-length', 1 ); // 1 MB

        $controller = new AdminController();
        $rawLength = 5 * 1024 * 1024;
        $psr = new Psr7Response( 206, ['Content-Type' => 'video/mp4', 'Content-Length' => (string) $rawLength] );
        $clientResponse = new ClientResponse( $psr );

        $method = new \ReflectionMethod( $controller, 'buildHeaders' );
        $method->setAccessible( true );

        $maxBytes = 1024 * 1024;
        $headers = $method->invoke( $controller, $clientResponse, 'bytes=100-' );

        $expectedEnd = 100 + $maxBytes - 1;
        $this->assertEquals( $maxBytes, $headers['Content-Length'] );
        $this->assertEquals( "bytes 100-$expectedEnd/$rawLength", $headers['Content-Range'] );
    }
}
