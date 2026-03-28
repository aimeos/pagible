<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Controllers\AdminController;
use Illuminate\Http\Client\Response as ClientResponse;
use GuzzleHttp\Psr7\Response as Psr7Response;


class AdminHeadersTest extends AdminTestAbstract
{
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
        config()->set( 'cms.admin.proxy.max-length', 1 ); // 1 MB

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
        config()->set( 'cms.admin.proxy.max-length', 1 ); // 1 MB

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
