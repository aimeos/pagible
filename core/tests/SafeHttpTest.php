<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\SafeHttp;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Group;


class SafeHttpTest extends CoreTestAbstract
{
    public function testOptionsRejectsNonHttp()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        SafeHttpTestProxy::options( 'ftp://example.com/file' );
    }


    #[Group('network')]
    public function testOptionsPinsResolvedIp()
    {
        $opts = SafeHttpTestProxy::options( 'https://example.com/image.png' );

        $this->assertTrue( $opts['verify'] );
        $this->assertFalse( $opts['allow_redirects'] );
        $this->assertStringStartsWith( 'example.com:443:', $opts['curl'][CURLOPT_RESOLVE][0] );
    }


    public function testOptionsBracketsIpv6()
    {
        $opts = SafeHttpIpv6TestProxy::options( 'https://example.com/image.png' );

        $this->assertEquals( ['example.com:443:[2001:db8::1]'], $opts['curl'][CURLOPT_RESOLVE] );
    }


    public function testHttpStreamConnectError()
    {
        $port = $this->server();
        $client = new \GuzzleHttp\Client( ['handler' => new \Aimeos\Cms\HttpStream] );

        $this->expectException( \GuzzleHttp\Exception\ConnectException::class );
        $client->get( 'http://pin.invalid:' . $port . '/', ['stream' => true, 'connect_timeout' => 1] );
    }


    public function testRequestStreamsBody()
    {
        config( ['cms.allow-internal' => true] );
        $port = $this->server();

        $response = SafeHttp::request( 'http://127.0.0.1:' . $port . '/large', ['stream' => true] );
        $body = $response->toPsrResponse()->getBody();

        $this->assertInstanceOf( \GuzzleHttp\Psr7\PumpStream::class, $body );
        $this->assertEquals( 200, $response->status() );
        $this->assertEquals( 'image/png', $response->header( 'Content-Type' ) );
        $this->assertEquals( 1024, strlen( $body->read( 1024 ) ) );
        $this->assertFalse( $body->eof() );
        $this->assertEquals( 3 * 1048576 - 1024, strlen( $body->getContents() ) );
        $this->assertTrue( $body->eof() );
    }


    public function testRequestStreamsHead()
    {
        config( ['cms.allow-internal' => true] );
        $port = $this->server();

        $response = SafeHttp::request( 'http://127.0.0.1:' . $port . '/large', ['stream' => true], [], 'HEAD' );

        $this->assertEquals( 200, $response->status() );
        $this->assertEquals( 'image/png', $response->header( 'Content-Type' ) );
        $this->assertEquals( '', $response->body() );
    }


    public function testRequestBlocksRedirectToPrivateAddress()
    {
        Http::fake( [
            '8.8.8.8/*' => Http::response( '', 302, ['Location' => 'http://127.0.0.1/private'] ),
            '*' => Http::response( 'secret', 200 ),
        ] );

        try {
            SafeHttp::request( 'http://8.8.8.8/start' );
            $this->fail( 'Expected the private redirect to be blocked' );
        } catch( \Aimeos\Cms\Exception $e ) {
            $this->assertStringContainsString( 'does not resolve to an allowed address', $e->getMessage() );
        }

        Http::assertSentCount( 1 );
    }


    public function testResolveAllowsPrivateIpByDefault()
    {
        config( ['cms.allow-internal' => true] );

        // Literal IPs are validated directly without a DNS lookup
        $this->assertEquals( '127.0.0.1', SafeHttp::resolve( '127.0.0.1' ) );
        $this->assertEquals( '10.0.0.1', SafeHttp::resolve( '10.0.0.1' ) );
        $this->assertEquals( '8.8.8.8', SafeHttp::resolve( '8.8.8.8' ) );

        config( ['cms.allow-internal' => false] );
    }


    public function testResolveBlocksPrivateIpWhenDisabled()
    {
        $this->assertNull( SafeHttp::resolve( '127.0.0.1' ) );
        $this->assertNull( SafeHttp::resolve( '10.0.0.1' ) );

        // Public IPs remain allowed
        $this->assertEquals( '8.8.8.8', SafeHttp::resolve( '8.8.8.8' ) );
    }


    /**
     * Starts a local HTTP server which returns the Host header or 3MB of data for "/large".
     *
     * @return int Port of the server, stopped after the test
     */
    protected function server() : int
    {
        $socket = stream_socket_server( 'tcp://127.0.0.1:0' );
        $port = (int) substr( (string) stream_socket_get_name( $socket, false ), 10 );
        fclose( $socket );

        $file = ( $base = (string) tempnam( sys_get_temp_dir(), 'cms' ) ) . '.php';
        unlink( $base );

        file_put_contents( $file, '<?php header( "Content-Type: image/png" );'
            . 'echo $_SERVER["REQUEST_URI"] === "/large" ? str_repeat( "x", 3 * 1048576 ) : $_SERVER["HTTP_HOST"];' );

        $proc = proc_open( [PHP_BINARY, '-S', '127.0.0.1:' . $port, $file], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes );

        $this->beforeApplicationDestroyed( function() use ( $proc, $file ) {
            proc_terminate( $proc );
            proc_close( $proc );
            unlink( $file );
        } );

        for( $i = 0; $i < 50 && !( $conn = @fsockopen( '127.0.0.1', $port ) ); $i++ ) {
            usleep( 100000 );
        }

        $this->assertNotFalse( $conn, 'Local HTTP server did not start' );
        fclose( $conn );

        return $port;
    }
}


class SafeHttpTestProxy extends SafeHttp
{
    /**
     * Exposes the protected SSRF options helper for focused tests.
     *
     * @param string $url HTTP URL to validate and pin
     * @return array<string, mixed> Guzzle request options
     */
    public static function options( string $url ) : array
    {
        return parent::options( $url );
    }
}


class SafeHttpIpv6TestProxy extends SafeHttpTestProxy
{
    /**
     * Resolves every host to a fixed IPv6 address.
     *
     * @param string $host Host name
     * @return string|null IPv6 address
     */
    public static function resolve( string $host ) : ?string
    {
        return '2001:db8::1';
    }
}
