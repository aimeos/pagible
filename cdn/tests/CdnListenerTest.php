<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\FilesRemoved;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Cdn;
use Aimeos\Cms\Jobs\PurgeCdn;
use Aimeos\Cms\Listeners\CdnListener;
use FOS\HttpCache\Exception\ExceptionCollection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;


class CdnListenerTest extends CdnTestCase
{
    public function testPages() : void
    {
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['', 'blog/post'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'cloudflare'
            && $job->urls === ['https://example.com/', 'https://example.com/blog/post']
        );
    }


    public function testPagesMultidomain() : void
    {
        config( ['cms.multidomain' => true] );
        Route::domain( '{domain}' )->where( ['domain' => '.+'] )->group( fn() => $this->route() );
        Queue::fake();

        PageInvalidated::dispatch( 'shop.test', ['a'] );
        PageInvalidated::dispatch( '', ['b'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === ['https://shop.test/a'] );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === ['https://example.com/b'] );
    }


    public function testPagesHosts() : void
    {
        config( ['cms.multidomain' => true, 'cms.cdn.clients' => [
            'one' => ['driver' => 'cloudflare', 'token' => 'a', 'zone' => 'z1', 'hosts' => ['one.test']],
            'two' => ['driver' => 'cloudflare', 'token' => 'b', 'zone' => 'z2', 'hosts' => ['two.test']],
        ]] );
        Route::domain( '{domain}' )->where( ['domain' => '.+'] )->group( fn() => $this->route() );
        Queue::fake();

        PageInvalidated::dispatch( 'one.test', ['a'] );

        Queue::assertPushed( PurgeCdn::class, 1 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'one' );
    }


    public function testPagesChunks() : void
    {
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', array_map( fn( $i ) => 'page-' . $i, range( 1, 300 ) ) );

        Queue::assertPushed( PurgeCdn::class, 2 );
    }


    public function testPagesLimit() : void
    {
        config( ['cms.cdn.limit' => 2] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a', 'b', 'c'] );

        Queue::assertPushed( PurgeCdn::class, 1 );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === [Cdn::ALL] );
    }


    public function testPagesLimitUnique() : void
    {
        config( ['cms.cdn.limit' => 2] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a', 'b', 'c'] );
        PageInvalidated::dispatch( '', ['d', 'e', 'f'] );

        Queue::assertPushed( PurgeCdn::class, 1 );
    }


    public function testPagesLimitVarnish() : void
    {
        config( ['cms.cdn.limit' => 2, 'cms.cdn.clients' => ['varnish' => ['driver' => 'varnish', 'servers' => ['127.0.0.1']]]] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a', 'b', 'c'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => count( $job->urls ) === 3 );
    }


    public function testPagesQueue() : void
    {
        config( ['cms.queue' => ['connection' => 'redis', 'name' => 'cdn']] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->connection === 'redis' && $job->queue === 'cdn' );
    }


    public function testPagesWithoutRoute() : void
    {
        Queue::fake();

        PageInvalidated::dispatch( '', ['a'] );

        Queue::assertNothingPushed();
    }


    public function testPagesWithoutClients() : void
    {
        config( ['cms.cdn.clients' => [
            'cloudflare' => null,
            'varnish' => null,
        ]] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a'] );

        Queue::assertNothingPushed();
    }


    public function testPagesUrl() : void
    {
        config( ['cms.cdn.url' => 'https://www.example.com/'] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === ['https://www.example.com/a'] );
    }


    public function testPagesDelay() : void
    {
        config( ['cms.cdn.delay' => 10] );
        $this->route();
        Queue::fake();

        PageInvalidated::dispatch( '', ['a', 'b'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) =>
            $job->delay === 10 && $job->urls === ['https://example.com/a', 'https://example.com/b']
        );
    }


    public function testPagesSyncAfterResponse() : void
    {
        config( ['queue.default' => 'sync'] );
        $history = [];
        $stack = \GuzzleHttp\HandlerStack::create( new \GuzzleHttp\Handler\MockHandler( [new \GuzzleHttp\Psr7\Response( 200 )] ) );
        $stack->push( \GuzzleHttp\Middleware::history( $history ) );
        $this->app->instance( 'cms.cdn.http', new \Http\Client\Common\PluginClient(
            new \Http\Adapter\Guzzle7\Client( new \GuzzleHttp\Client( ['handler' => $stack, 'http_errors' => false] ) ),
            [new \Http\Client\Common\Plugin\ErrorPlugin()]
        ) );
        $this->route();

        PageInvalidated::dispatch( '', ['a', 'b'] );

        $this->assertCount( 0, $history );

        $this->app->terminate();

        $this->assertCount( 1, $history );
        $this->assertSame( '{"files":["https://example.com/a","https://example.com/b"]}', (string) $history[0]['request']->getBody() );
    }


    public function testPagesSync() : void
    {
        config( ['queue.default' => 'sync', 'cms.cdn.delay' => 10, 'cms.cdn.timeout' => 1, 'cms.cdn.clients' => [
            'varnish' => ['driver' => 'varnish', 'servers' => ['127.0.0.1:1']],
        ]] );
        $this->route();
        Exceptions::fake();

        // Not delayed but purged after the response is sent
        PageInvalidated::dispatch( '', ['a'] );

        Exceptions::assertNothingReported();

        // Reported by the exception handler of the application after the response is sent
        $this->expectException( ExceptionCollection::class );
        $this->app->terminate();
    }


    public function testHeaders() : void
    {
        config( ['cms.cdn.maxage' => 3600, 'cms.cdn.stale' => 60] );
        $this->route( 'public, s-maxage=600, max-age=0, must-revalidate' );

        $header = $this->get( '/a' )->headers->get( 'Cache-Control' );

        $this->assertStringContainsString( 's-maxage=3600', (string) $header );
        $this->assertStringContainsString( 'stale-while-revalidate=60', (string) $header );
        $this->assertStringContainsString( 'stale-if-error=60', (string) $header );
        $this->assertStringContainsString( 'max-age=0', (string) $header );
        $this->assertStringNotContainsString( 'must-revalidate', (string) $header );
    }


    public function testHeadersExpires() : void
    {
        config( ['cms.cdn.maxage' => 3600] );
        Route::get( '{path?}', fn() => response( '' )->header( 'Cache-Control', 'public, s-maxage=600' )
            ->header( 'Expires', 'Thu, 01 Jan 2099 00:00:00 GMT' ) )->where( 'path', '.*' )->name( 'cms.page' );
        Route::getRoutes()->refreshNameLookups();

        $this->assertFalse( $this->get( '/a' )->headers->has( 'Expires' ) );
    }


    public function testHeadersPrivate() : void
    {
        config( ['cms.cdn.maxage' => 3600, 'cms.cdn.stale' => 60] );
        $this->route( 'no-store, private' );

        $this->assertSame( 'no-store, private', $this->get( '/a' )->headers->get( 'Cache-Control' ) );
    }


    public function testHeadersUnchanged() : void
    {
        $this->route( 'public, s-maxage=600, max-age=0, must-revalidate' );

        $this->assertSame( 'max-age=0, must-revalidate, public, s-maxage=600', $this->get( '/a' )->headers->get( 'Cache-Control' ) );
    }


    public function testFiles() : void
    {
        config( ['filesystems.disks.public.url' => 'https://cdn.example.com/storage'] );
        Queue::fake();

        FilesRemoved::dispatch( 'test', ['cms/test/1/a.jpg', 'cms/test/1/a-500.webp'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->urls === [
            'https://cdn.example.com/storage/cms/test/1/a.jpg',
            'https://cdn.example.com/storage/cms/test/1/a-500.webp',
        ] );
    }


    public function testFilesRelative() : void
    {
        Storage::fake( 'public' );
        Queue::fake();

        FilesRemoved::dispatch( 'test', ['cms/a.jpg'] );

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) =>
            str_starts_with( $job->urls[0], 'https://example.com/' ) && str_ends_with( $job->urls[0], '/cms/a.jpg' )
        );
    }


    protected function route( string $cache = 'no-cache' ) : void
    {
        Route::get( '{path?}', fn() => response( '' )->header( 'Cache-Control', $cache ) )->where( 'path', '.*' )->name( 'cms.page' );
        Route::getRoutes()->refreshNameLookups();
    }
}
