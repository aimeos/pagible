<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Controllers\AdminController;
use Aimeos\Cms\ProxyToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;


class AdminControllerTest extends AdminTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected ?\App\Models\User $user = null;
    protected string $proxyToken = '';


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User( [
            'name' => 'Admin',
            'email' => 'admin@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] );

        $this->proxyToken = app( ProxyToken::class )->make( $this->user );
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


    public function testProxyRateLimiter()
    {
        $this->assertNotNull( RateLimiter::limiter( 'cms-proxy' ) );
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
        $response = $this->actingAs( $this->user )->post( route( 'cms.proxy', ['token' => $this->proxyToken] ) );

        $response->assertStatus( 405 );
    }


    public function testProxyInvalidUrl()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => $this->proxyToken, 'url' => 'not-a-url'] ) );

        $response->assertStatus( 400 );
    }


    public function testProxyMissingUrl()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => $this->proxyToken] ) );

        $response->assertStatus( 400 );
    }


    public function testProxyConnectionException()
    {
        Http::fake( fn() => throw new \Illuminate\Http\Client\ConnectionException( 'Connection timed out' ) );

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => $this->proxyToken, 'url' => 'https://example.com/video.mp4'] ) );

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

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => $this->proxyToken, 'url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 200 );
        $this->assertEquals( 'video/mp4', $response->headers->get( 'Content-Type' ) );
        $this->assertEquals( '*', $response->headers->get( 'Access-Control-Allow-Origin' ) );
        $this->assertEquals( 'bytes', $response->headers->get( 'Accept-Ranges' ) );
    }


    public function testProxyInvalidToken()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => 'invalid', 'url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 403 );
    }


    public function testProxyMissingToken()
    {
        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 403 );
    }


    public function testProxyExpiredToken()
    {
        config()->set( 'cms.admin.proxy.ttl', -1 );
        $token = app( ProxyToken::class )->make( $this->user );

        $response = $this->actingAs( $this->user )->get( route( 'cms.proxy', ['token' => $token, 'url' => 'https://example.com/video.mp4'] ) );

        $response->assertStatus( 403 );
    }


}
