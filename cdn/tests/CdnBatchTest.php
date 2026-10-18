<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\CdnBatch;
use Aimeos\Cms\Jobs\FlushCdn;
use Aimeos\Cms\Jobs\PurgeCdn;
use Illuminate\Cache\ArrayStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;


class CdnBatchTest extends CdnTestCase
{
    public function testPurgeDelay() : void
    {
        config( ['cms.cdn.delay' => 10, 'cms.cdn.clients' => [
            'cloudflare' => ['driver' => 'cloudflare', 'servers' => ['https://api.cloudflare.com'], 'options' => ['authentication_token' => 'secret', 'zone_identifier' => 'zone1'], 'delay' => 30],
            'varnish' => ['driver' => 'varnish', 'servers' => ['10.0.0.1']],
        ]] );
        Queue::fake();

        // URLs changed during the delay are collected and purged together
        Cdn::purge( ['https://example.com/a'] );
        Cdn::purge( ['https://example.com/b', 'https://example.com/a'] );

        Queue::assertPushed( FlushCdn::class, 1 );
        Queue::assertPushed( FlushCdn::class, fn( FlushCdn $job ) => $job->delay === 10 && $job->tenant === '' );
        Queue::assertNotPushed( PurgeCdn::class );

        $this->travel( 10 )->seconds();
        ( new FlushCdn() )->handle();

        // The waiting time is subtracted from the longer delays of the clients
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'cloudflare'
            && $job->delay === 20 && $job->urls === ['https://example.com/a', 'https://example.com/b']
        );
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->client === 'varnish' && $job->delay === null );
        $this->assertNull( Cache::get( CdnBatch::KEY ) );
    }


    public function testPurgeDelayLost() : void
    {
        config( ['cms.cdn.delay' => 10] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );
        $this->travel( 10 + CdnBatch::GRACE + 1 )->seconds();
        Cdn::purge( ['https://example.com/b'] );

        // A lost flush job is replaced, so the collected URLs are purged anyway
        Queue::assertPushed( FlushCdn::class, 2 );
        $this->assertSame( ['https://example.com/a', 'https://example.com/b'], Cache::get( CdnBatch::KEY )['urls'] );
    }


    public function testPurgeDelayOverflow() : void
    {
        config( ['cms.cdn.delay' => 10, 'cms.cdn.limit' => 0] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );
        Cdn::purge( array_map( fn( $idx ) => 'https://example.com/' . $idx, range( 1, CdnBatch::MAX ) ) );

        // Too many URLs are purged at once including the collected ones instead of keeping them in the cache
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->delay === 10 && $job->urls[0] === 'https://example.com/a' );
        Queue::assertPushed( PurgeCdn::class, (int) ceil( ( CdnBatch::MAX + 1 ) / Cdn::CHUNK ) );
        $this->assertNull( Cache::get( CdnBatch::KEY ) );
    }


    public function testPurgeDelayCacheFailed() : void
    {
        Cache::extend( 'reject', fn() => Cache::repository( new class extends ArrayStore {
            public function put( $key, $value, $seconds ) { return false; }
        } ) );
        config( ['cms.cdn.delay' => 10, 'cache.stores.reject' => ['driver' => 'reject'], 'cache.default' => 'reject'] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        // URLs the cache store rejects are purged at once
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->delay === 10 && $job->urls === ['https://example.com/a'] );
        Queue::assertNotPushed( FlushCdn::class );
    }


    public function testFlushRetry() : void
    {
        config( ['cms.cdn.delay' => 10, 'cms.cdn.limit' => 1, 'cms.cdn.clients.cloudflare.driver' => 'unknown'] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a', 'https://example.com/b'] );

        try {
            ( new FlushCdn() )->handle();
            $this->fail( 'Queueing the purges must fail' );
        } catch( \InvalidArgumentException ) {
        }

        // URLs collected meanwhile are kept separately and the moved URLs are queued again by the retry
        Cdn::purge( ['https://example.com/c'] );
        config( ['cms.cdn.clients.cloudflare.driver' => 'cloudflare', 'cms.cdn.limit' => 0] );
        ( new FlushCdn() )->handle();

        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) =>
            $job->urls === ['https://example.com/a', 'https://example.com/b', 'https://example.com/c']
        );
        $this->assertNull( Cache::get( CdnBatch::KEY ) );
        $this->assertNull( Cache::get( CdnBatch::KEY . ':flush' ) );
    }


    public function testPurgeDelayNullCache() : void
    {
        config( ['cms.cdn.delay' => 10, 'cache.default' => 'null'] );
        Queue::fake();

        Cdn::purge( ['https://example.com/a'] );

        // The null cache store can't keep the URLs, so they are purged on their own
        Queue::assertPushed( PurgeCdn::class, fn( PurgeCdn $job ) => $job->delay === 10 );
        Queue::assertNotPushed( FlushCdn::class );
    }
}
