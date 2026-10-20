<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Jobs\PurgeCdn;
use Aimeos\Cms\Tenancy;
use FOS\HttpCache\Exception\ExceptionCollection;
use FOS\HttpCache\Exception\UnsupportedProxyOperationException;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Queue;


class CdnTest extends CdnTestCase
{
    public function testAbsolute() : void
    {
        $this->assertSame( 'http://other.test/a', Cdn::absolute( 'http://other.test/a' ) );
        $this->assertSame( 'https://example.com/a', Cdn::absolute( '/a' ) );
        $this->assertSame( 'https://example.com/go?to=https://x.test', Cdn::absolute( 'go?to=https://x.test' ) );
    }


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


    public function testPurgeClearTenancy() : void
    {
        config( ['cms.cdn.limit' => 1] );
        Queue::fake();
        Tenancy::valueUsing( fn() => 't1' );

        try
        {
            // Removing all content would remove the content of the other tenants too
            Cdn::purge( ['https://example.com/a', 'https://example.com/b'] );
            Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => count( $job->urls ) === 2 && $job->tenant === 't1' );

            config( ['cms.cdn.clients.cloudflare.clear' => true] );
            Cdn::purge( ['https://example.com/c', 'https://example.com/d'] );
            Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === [Cdn::ALL] );
        }
        finally
        {
            Tenancy::valueUsing( null );
        }
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


    public function testServers() : void
    {
        $this->assertSame( ['10.0.0.1', '10.0.0.2'], Cdn::servers( ['servers' => [' 10.0.0.1', '10.0.0.1 ', '', '10.0.0.2']] ) );
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
        $this->http( new Response( 200 ) );
        $invalidator = Cdn::invalidator( $config );

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
        $this->http( new Response( 200 ) );
        $invalidator = Cdn::invalidator( $config );

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
        $this->http( new Response( 200 ), new Response( 200 ) );
        $invalidator = Cdn::invalidator( $config );

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
        $this->http( new Response( 200 ) );
        $invalidator = Cdn::invalidator( $config );

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
        $this->http();
        Cdn::invalidator( ['driver' => $driver, 'servers' => ['10.0.0.1']] );
    }


    public function testError() : void
    {
        $config = ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']];
        $this->http( new Response( 403 ) );
        $invalidator = Cdn::invalidator( $config );

        $this->expectException( ExceptionCollection::class );
        $invalidator->invalidatePath( 'https://example.com/a' )->flush();
    }


    public function testClearCloudflare() : void
    {
        $config = ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1']];

        $this->http( new Response( 200 ) );
        Cdn::invalidator( $config )->clearCache()->flush();

        $this->assertSame( '{"purge_everything":true}', (string) $this->history[0]['request']->getBody() );
    }


    public function testClearVarnish() : void
    {
        $config = ['driver' => 'varnish', 'servers' => ['10.0.0.1']];

        $this->expectException( UnsupportedProxyOperationException::class );
        $this->http();
        Cdn::invalidator( $config )->clearCache();
    }
}
