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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Psr\Http\Message\RequestInterface;


class CdnTest extends CdnTestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];


    public function testClients() : void
    {
        config( ['cms.cdn.clients' => [
            'fastly' => ['driver' => 'fastly', 'servers' => ['https://api.fastly.com'], 'options' => ['authentication_token' => 'a', 'service_identifier' => 's']],
            'other' => null,
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );

        $this->assertSame( ['fastly', 'varnish'], array_keys( Cdn::clients() ) );
    }


    public function testPurgeLimitHosts() : void
    {
        config( ['cms.cdn.limit' => 1, 'cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'hosts' => ['example.com']],
        ]] );
        Queue::fake();

        // URLs of other hosts aren't purged by the client and don't count
        Cdn::purge( ['https://example.com/a', 'https://other.com/b'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === ['https://example.com/a'] );
    }


    public function testPurgeDelay() : void
    {
        config( ['cms.cdn.delay' => 10, 'cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'delay' => 30],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'cloudflare' && $job->delay === 30 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'varnish' && $job->delay === 10 );
    }


    public function testPurgeChain() : void
    {
        config( ['cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'after' => 'varnish'],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
            'fastly' => ['driver' => 'fastly', 'servers' => ['https://api.fastly.com'], 'options' => ['authentication_token' => 'a', 'service_identifier' => 's']],
        ]] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        // The outer cache is purged after the inner cache it fetches the content from, independent clients at once
        Queue::assertPushed( PurgeCdn::class, 2 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'varnish'
            && count( $job->chained ) === 1 && unserialize( $job->chained[0] )->client === 'cloudflare'
        );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'fastly' && $job->chained === [] );
    }


    public function testPurgeServers() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1', '10.0.0.2', '10.0.0.1']]]] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        Queue::assertPushed( PurgeCdn::class, 2 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->server === '10.0.0.1' );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->server === '10.0.0.2' );
    }


    public function testStacks() : void
    {
        $clients = [
            'cdn' => ['after' => 'proxy'],
            'proxy' => ['after' => 'varnish'],
            'varnish' => [],
            'other' => ['after' => 'missing'],
            'loop1' => ['after' => 'loop2'],
            'loop2' => ['after' => 'loop1'],
        ];

        $stacks = Cdn::stacks( $clients );

        $this->assertSame( ['varnish', 'other', 'proxy', 'cdn'], array_slice( array_keys( $stacks ), 0, 4 ) );
        $this->assertSame( ['varnish', 'varnish', 'varnish', 'other'], [$stacks['cdn'], $stacks['proxy'], $stacks['varnish'], $stacks['other']] );
        $this->assertNotSame( $stacks['loop1'], $stacks['loop2'] );
    }


    public function testCloudflare() : void
    {
        $config = ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']];
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
        $config = ['driver' => 'fastly', 'servers' => ['https://api.fastly.com'], 'options' => ['authentication_token' => 'secret', 'service_identifier' => 'svc']];
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


    public function testNginx() : void
    {
        $config = ['driver' => 'nginx', 'servers' => ['10.0.0.1:8080'], 'options' => ['purge_location' => '/purge']];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 200 ) ) );

        $invalidator->invalidatePath( 'https://example.com/a' )->flush();

        $request = $this->history[0]['request'];
        $this->assertSame( 'PURGE', $request->getMethod() );
        $this->assertSame( 'http://10.0.0.1:8080/purge/a', (string) $request->getUri() );
        $this->assertSame( 'example.com', $request->getHeaderLine( 'Host' ) );
    }


    /**
     * @return array<string, array{string}>
     */
    public static function unknownDrivers() : array
    {
        return [
            'empty' => [''],
            'missing' => ['cloudfront'],
            'no HTTP proxy' => ['noop'],
            'multiplexer' => ['multiplexerClient'],
            'namespace' => ['Invalidation\\PurgeCapable'],
            'other class' => ['\\Aimeos\\Cms\\Cdn'],
        ];
    }


    #[\PHPUnit\Framework\Attributes\DataProvider( 'unknownDrivers' )]
    public function testUnknownDriver( string $driver ) : void
    {
        $this->expectException( \InvalidArgumentException::class );
        Cdn::invalidator( ['driver' => $driver, 'servers' => ['10.0.0.1']], $this->http() );
    }


    public function testError() : void
    {
        $config = ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']];
        $invalidator = Cdn::invalidator( $config, $this->http( new Response( 403 ) ) );

        $this->expectException( ExceptionCollection::class );
        $invalidator->invalidatePath( 'https://example.com/a' )->flush();
    }


    public function testClearCloudflare() : void
    {
        $config = ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']];

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


    public function testJobConfigError() : void
    {
        config( ['cms.cdn.clients' => ['cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com']]]] );

        $queued = \Mockery::mock( \Illuminate\Contracts\Queue\Job::class );
        $queued->shouldReceive( 'fail' )->once()->with( \Mockery::type( \Throwable::class ) );

        $job = new PurgeCdn( 'cloudflare', ['https://example.com/a'] );
        $job->setJob( $queued );
        $job->handle();
    }


    public function testJobConfigErrorWithoutQueue() : void
    {
        config( ['cms.cdn.clients' => ['cloudflare' => ['driver' => 'cloudfront']]] );

        $this->expectException( \InvalidArgumentException::class );
        ( new PurgeCdn( 'cloudflare', ['https://example.com/a'] ) )->handle();
    }


    public function testJobQueries() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1'], 'queries' => true]]] );
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ), new Response( 200 ) ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a.b', 'https://example.com/c?page=2'] ) )->handle();

        $this->assertCount( 2, $this->history );
        $ban = $this->history[0]['request'];
        $this->assertSame( 'BAN', $ban->getMethod() );
        $this->assertSame( '^/a\.b(\?|$)', $ban->getHeaderLine( 'X-Url' ) );
        $this->assertSame( '^(example\.com)$', $ban->getHeaderLine( 'X-Host' ) );
        $this->assertSame( 'PURGE', $this->history[1]['request']->getMethod() );
        $this->assertSame( 'http://10.0.0.1/c?page=2', (string) $this->history[1]['request']->getUri() );
    }


    public function testJobQueriesUnsupported() : void
    {
        config( ['cms.cdn.clients' => ['nginx' => ['driver' => 'nginx', 'servers' => ['10.0.0.1'], 'queries' => true]]] );
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ) ) );

        ( new PurgeCdn( 'nginx', ['https://example.com/a'] ) )->handle();

        $this->assertSame( 'PURGE', $this->history[0]['request']->getMethod() );
    }


    public function testJobServer() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1', '10.0.0.2']]]] );
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ) ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a'], '10.0.0.2' ) )->handle();
        ( new PurgeCdn( 'varnish', ['https://example.com/a'], '10.0.0.3' ) )->handle();

        $this->assertCount( 1, $this->history );
        $this->assertSame( 'http://10.0.0.2/a', (string) $this->history[0]['request']->getUri() );
    }


    public function testJobRefresh() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1'], 'refresh' => true]]] );
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ) ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a'] ) )->handle();

        $this->assertCount( 1, $this->history );
        $this->assertSame( 'GET', $this->history[0]['request']->getMethod() );
        $this->assertSame( 'no-cache', $this->history[0]['request']->getHeaderLine( 'Cache-Control' ) );
    }


    public function testJobRefreshQueries() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1'], 'queries' => true, 'refresh' => true]]] );
        $this->app->instance( 'cms.cdn.http', $this->http( new Response( 200 ), new Response( 200 ) ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a'] ) )->handle();

        $this->assertSame( ['BAN', 'GET'], array_map( fn( $entry ) => $entry['request']->getMethod(), $this->history ) );
    }


    public function testJobFailed() : void
    {
        Log::spy();
        $job = new PurgeCdn( 'cloudflare', ['https://example.com/a'] );

        // Equal errors are logged only once
        $job->failed( new \RuntimeException( 'secret ' . uniqid() ) );
        $job->failed( new \RuntimeException( 'secret ' . uniqid() ) );

        Log::shouldHaveReceived( 'warning' )->once()->with( 'cms.cdn.purge_failed', \Mockery::on( fn( array $data ) =>
            $data['client'] === 'cloudflare' && $data['error'] === \RuntimeException::class && !str_contains( serialize( $data ), 'secret' )
        ) );
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
