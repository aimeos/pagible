<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use GuzzleHttp\Psr7\Response;


class CdnCommandTest extends CdnTestCase
{
    public function testCheck() : void
    {
        $this->artisan( 'cms:cdn:check' )->expectsOutput( 'cloudflare: OK' )->assertSuccessful();
    }


    public function testCheckError() : void
    {
        config( ['cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'token' => 'secret', 'zone' => 'zone1'],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );

        $this->artisan( 'cms:cdn:check' )
            ->expectsOutputToContain( 'cloudflare: The required options "authentication_token", "zone_identifier" are missing' )
            ->expectsOutput( 'varnish: OK' )
            ->assertFailed();
    }


    public function testCheckQueries() : void
    {
        config( ['cms.cdn.clients' => ['nginx' => ['driver' => 'nginx', 'servers' => ['10.0.0.1'], 'queries' => true]]] );

        $this->artisan( 'cms:cdn:check' )
            ->expectsOutput( 'nginx: "queries" requires a driver supporting bans, only the URLs are purged' )
            ->assertSuccessful();
    }


    public function testCheckSend() : void
    {
        config( ['cms.cdn.clients.cloudflare.hosts' => ['www.example.com']] );
        $this->http( new Response( 200 ) );

        $this->artisan( 'cms:cdn:check', ['--send' => true] )->expectsOutput( 'cloudflare: OK' )->assertSuccessful();

        $this->assertCount( 1, $this->history );
        $this->assertSame( '{"files":["https://www.example.com/cms-cdn-check"]}', (string) $this->history[0]['request']->getBody() );
    }


    public function testCheckSendError() : void
    {
        $this->http( new Response( 403 ) );

        $this->artisan( 'cms:cdn:check', ['--send' => true] )->expectsOutputToContain( 'cloudflare: 403' )->assertFailed();
    }


    public function testCheckWarnings() : void
    {
        config( ['cms.cdn.clients' => ['cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'refresh' => true, 'after' => 'varnish']]] );

        $this->artisan( 'cms:cdn:check' )
            ->expectsOutput( 'cloudflare: "refresh" requires a driver supporting refreshes, the URLs are purged' )
            ->expectsOutput( 'cloudflare: "after" uses the unknown client "varnish", it is purged independently' )
            ->assertSuccessful();
    }


    public function testCheckWithoutClients() : void
    {
        config( ['cms.cdn.clients' => ['cloudflare' => null]] );

        $this->artisan( 'cms:cdn:check' )->expectsOutput( 'No CDN clients configured' )->assertSuccessful();
    }


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
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'hosts' => ['example.com']],
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
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'after' => 'varnish'],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
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
}
