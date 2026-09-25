<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Jobs\PurgeCdn;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\UnsupportedProxyOperationException;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Http\Client\HttpAsyncClient;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;


class CdnTest extends CdnTestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];


    public function testClients() : void
    {
        config( ['cms.cdn.clients' => [
            'fastly' => ['driver' => 'fastly', 'token' => 'a', 'service' => 's'],
            'other' => null,
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );

        $this->assertSame( ['fastly', 'varnish'], array_keys( Cdn::clients() ) );
    }


    public function testPurgeLimitHosts() : void
    {
        config( ['cms.cdn.limit' => 1, 'cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1', 'hosts' => ['example.com']],
        ]] );
        Queue::fake();

        // URLs of other hosts aren't purged by the client and don't count
        Cdn::purge( ['https://example.com/a', 'https://other.com/b'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === ['https://example.com/a'] );
    }


    public function testPurgeDelay() : void
    {
        config( ['cms.cdn.delay' => 10, 'cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1', 'delay' => 30],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'cloudflare' && $job->delay === 30 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'varnish' && $job->delay === 10 );
    }


    public function testCloudflare() : void
    {
        $config = ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1'];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 200 ) ) );

        $invalidator->invalidatePath( 'https://example.com/a' )->invalidatePath( 'https://example.com/b' )->flush();

        $this->assertCount( 1, $this->history );
        $request = $this->history[0]['request'];
        $this->assertSame( 'POST', $request->getMethod() );
        $this->assertSame( 'https://api.cloudflare.com/client/v4/zones/zone1/purge_cache', (string) $request->getUri() );
        $this->assertSame( 'Bearer secret', $request->getHeaderLine( 'Authorization' ) );
        $this->assertSame( '{"files":["https://example.com/a","https://example.com/b"]}', (string) $request->getBody() );
    }


    public function testFastly() : void
    {
        $config = ['driver' => 'fastly', 'token' => 'secret', 'service' => 'svc'];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 200 ) ) );

        $invalidator->invalidatePath( 'https://example.com/a' )->flush();

        $request = $this->history[0]['request'];
        $this->assertSame( 'PURGE', $request->getMethod() );
        $this->assertSame( 'https://api.fastly.com/a', (string) $request->getUri() );
        $this->assertSame( 'example.com', $request->getHeaderLine( 'Host' ) );
        $this->assertSame( 'secret', $request->getHeaderLine( 'Fastly-Key' ) );
        $this->assertSame( '1', $request->getHeaderLine( 'Fastly-Soft-Purge' ) );
    }


    public function testVarnish() : void
    {
        $config = ['driver' => 'varnish', 'servers' => ['10.0.0.1:6081', '10.0.0.2:6081']];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 200 ), new Response( 200 ) ) );

        $invalidator->invalidatePath( 'https://example.com/a' )->flush();

        $this->assertCount( 2, $this->history );
        $request = $this->history[1]['request'];
        $this->assertSame( 'PURGE', $request->getMethod() );
        $this->assertSame( 'http://10.0.0.2:6081/a', (string) $request->getUri() );
        $this->assertSame( 'example.com', $request->getHeaderLine( 'Host' ) );
    }


    public function testError() : void
    {
        $config = ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1'];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 403 ) ) );

        $this->expectException( ExceptionCollection::class );
        $invalidator->invalidatePath( 'https://example.com/a' )->flush();
    }


    public function testClearCloudflare() : void
    {
        $config = ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1'];

        Cdn::invalidator( $config, $this->http( new Response( 200 ) ) )->clearCache()->flush();

        $this->assertSame( '{"purge_everything":true}', (string) $this->history[0]['request']->getBody() );
    }


    public function testClearVarnish() : void
    {
        $config = ['driver' => 'varnish', 'servers' => ['10.0.0.1']];

        $this->expectException( UnsupportedProxyOperationException::class );
        Cdn::invalidator( $config, $this->http() )->clearCache();
    }


    public function testJobClear() : void
    {
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ) ) );

        ( new PurgeCdn( 'cloudflare', [Cdn::ALL] ) )->handle();

        $this->assertCount( 1, $this->history );
        $this->assertSame( '{"purge_everything":true}', (string) $this->history[0]['request']->getBody() );
    }


    public function testJobRemovedClient() : void
    {
        config( ['cms.cdn.clients' => []] );

        ( new PurgeCdn( 'cloudflare', ['https://example.com/a'] ) )->handle();

        $this->assertSame( [10, 60, 300, 900], ( new PurgeCdn( 'cloudflare', [] ) )->backoff() );
    }


    private function http( Response ...$responses ) : HttpAsyncClient
    {
        $stack = HandlerStack::create( new MockHandler( $responses ) );
        $stack->push( Middleware::history( $this->history ) );

        return new PluginClient( new Client( new GuzzleClient( ['handler' => $stack, 'http_errors' => false] ) ), [new ErrorPlugin()] );
    }
}
