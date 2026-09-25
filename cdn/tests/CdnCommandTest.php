<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Http\Adapter\Guzzle7\Client;
use Http\Client\Common\Plugin\ErrorPlugin;
use Http\Client\Common\PluginClient;
use Psr\Http\Message\RequestInterface;


class CdnCommandTest extends CdnTestCase
{
    /** @var array<int, array{request: RequestInterface}> */
    private array $history = [];


    public function testPurge() : void
    {
        $this->http( new Response( 200 ) );

        $this->artisan( 'cms:cdn:purge', ['urls' => ['https://example.com/a', 'b']] )
            ->expectsOutput( 'cloudflare: purged 2 URL(s)' )
            ->assertSuccessful();

        $this->assertSame( '{"files":["https://example.com/a","https://example.com/b"]}', (string) $this->history[0]['request']->getBody() );
    }


    public function testPurgeHosts() : void
    {
        config( ['cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1', 'hosts' => ['example.com']],
        ]] );
        $this->http( new Response( 200 ) );

        $this->artisan( 'cms:cdn:purge', ['urls' => ['https://example.com/a', 'https://other.com/b']] )
            ->expectsOutput( 'cloudflare: purged 1 URL(s)' )
            ->assertSuccessful();

        $this->assertSame( '{"files":["https://example.com/a"]}', (string) $this->history[0]['request']->getBody() );
    }


    public function testPurgeOrder() : void
    {
        config( ['cms.cdn.clients' => [
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
            'cloudflare' => ['driver' => 'cloudflare', 'token' => 'secret', 'zone' => 'zone1'],
        ]] );
        $this->http( new Response( 200 ), new Response( 200 ) );

        $this->artisan( 'cms:cdn:purge', ['urls' => ['https://example.com/a']] )->assertSuccessful();

        $this->assertSame( ['PURGE', 'POST'], array_map( fn( $entry ) => $entry['request']->getMethod(), $this->history ) );
    }


    public function testPurgeAll() : void
    {
        $this->http( new Response( 200 ) );

        $this->artisan( 'cms:cdn:purge', ['--all' => true] )->expectsOutput( 'cloudflare: purged all content' )->assertSuccessful();

        $this->assertSame( '{"purge_everything":true}', (string) $this->history[0]['request']->getBody() );
    }


    public function testPurgeClient() : void
    {
        $this->http();

        $this->artisan( 'cms:cdn:purge', ['urls' => ['a'], '--client' => ['other']] )->assertSuccessful();

        $this->assertSame( [], $this->history );
    }


    public function testPurgeError() : void
    {
        $this->http( new Response( 403 ) );

        $this->artisan( 'cms:cdn:purge', ['urls' => ['a']] )
            ->expectsOutputToContain( 'cloudflare: 403' )
            ->assertFailed();
    }


    public function testPurgeWithoutUrls() : void
    {
        $this->artisan( 'cms:cdn:purge' )->expectsOutput( 'Pass the URLs to purge or use --all' )->assertFailed();
    }


    private function http( Response ...$responses ) : void
    {
        $stack = HandlerStack::create( new MockHandler( $responses ) );
        $stack->push( Middleware::history( $this->history ) );

        $this->app->instance( 'cms.cdn.http', new PluginClient(
            new Client( new GuzzleClient( ['handler' => $stack, 'http_errors' => false] ) ), [new ErrorPlugin()]
        ) );
    }
}
