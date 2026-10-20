<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Jobs\PurgeCdn;
use FOS\HttpCache\Exception\ExceptionCollection;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;


class PurgeCdnTest extends CdnTestCase
{
    public function testJobClear() : void
    {
        $this->http( new Response( 200 ) );

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
        $this->http( new Response( 200 ), new Response( 200 ) );

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
        $this->http( new Response( 200 ) );

        ( new PurgeCdn( 'nginx', ['https://example.com/a'] ) )->handle();

        $this->assertSame( 'PURGE', $this->history[0]['request']->getMethod() );
    }


    public function testJobServer() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1', '10.0.0.2']]]] );
        $this->http( new Response( 200 ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a'], '10.0.0.2' ) )->handle();
        ( new PurgeCdn( 'varnish', ['https://example.com/a'], '10.0.0.3' ) )->handle();

        $this->assertCount( 1, $this->history );
        $this->assertSame( 'http://10.0.0.2/a', (string) $this->history[0]['request']->getUri() );
    }


    public function testJobRefresh() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1'], 'refresh' => true]]] );
        $this->http( new Response( 200 ) );

        ( new PurgeCdn( 'varnish', ['https://example.com/a'] ) )->handle();

        $this->assertCount( 1, $this->history );
        $this->assertSame( 'GET', $this->history[0]['request']->getMethod() );
        $this->assertSame( 'no-cache', $this->history[0]['request']->getHeaderLine( 'Cache-Control' ) );
    }


    public function testJobRefreshQueries() : void
    {
        config( ['cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1'], 'queries' => true, 'refresh' => true]]] );
        $this->http( new Response( 200 ), new Response( 200 ) );

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


    public function testJobMetrics() : void
    {
        $observed = [];
        Event::listen( Observed::class, function( Observed $event ) use ( &$observed ) { $observed[] = $event; } );
        $this->http( new Response( 200 ), new Response( 500 ) );

        ( new PurgeCdn( 'cloudflare', ['https://example.com/a'], null, 't1' ) )->handle();

        try {
            ( new PurgeCdn( 'cloudflare', [Cdn::ALL] ) )->handle();
        } catch( ExceptionCollection ) {
        }

        $this->assertCount( 2, $observed );
        $this->assertSame( ['cdn', 'cdn:purge', 't1', ['client' => 'cloudflare', 'success' => true]],
            [$observed[0]->source, $observed[0]->action, $observed[0]->tenant, $observed[0]->dimensions]
        );
        $this->assertSame( ['cdn:clear', ['client' => 'cloudflare', 'success' => false]], [$observed[1]->action, $observed[1]->dimensions] );
    }


    public function testJobRemovedClient() : void
    {
        config( ['cms.cdn.clients' => []] );

        ( new PurgeCdn( 'cloudflare', ['https://example.com/a'] ) )->handle();

        $this->assertSame( [10, 60, 300, 900], ( new PurgeCdn( 'cloudflare', [] ) )->backoff() );
    }
}
