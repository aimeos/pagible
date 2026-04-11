<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Controllers\AdminController;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AdminControllerTest extends AdminTestAbstract
{
    use RefreshDatabase;

    protected ?\App\Models\User $user = null;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User( [
            'name' => 'Admin',
            'email' => 'admin@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
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


}
