<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\PageCache;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Cache\Repository;
use Illuminate\Cache\RedisStore;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class PageCacheTest extends ThemeTestAbstract
{
    public function testClearsOnlyRequestedTenantRoutes(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $cache = Cache::store( 'array' );
        $routeKey = new \ReflectionMethod( PageCache::class, 'routeKey' );
        $targetKey = $routeKey->invoke( null, 'test', 'example.com', 'target' );
        $secondKey = $routeKey->invoke( null, 'test', 'example.com', 'second' );
        $otherDomainKey = $routeKey->invoke( null, 'test', 'other.example', 'target' );
        $otherTenantKey = $routeKey->invoke( null, 'other', 'example.com', 'target' );

        foreach( [$targetKey, $secondKey, $otherDomainKey, $otherTenantKey] as $key ) {
            $cache->put( $key, $key );
        }

        PageInvalidated::dispatch( 'example.com', ['target', 'second'] );

        $this->assertNull( $cache->get( $targetKey ) );
        $this->assertNull( $cache->get( $secondKey ) );
        $this->assertSame( $otherDomainKey, $cache->get( $otherDomainKey ) );
        $this->assertSame( $otherTenantKey, $cache->get( $otherTenantKey ) );
    }


    public function testDeletesDatabaseRoutesInOneBatch(): void
    {
        Schema::create( 'cms_page_cache_test', function( Blueprint $table ) {
            $table->string( 'key' )->primary();
            $table->mediumText( 'value' );
            $table->integer( 'expiration' );
        } );
        $store = new DatabaseStore(
            DB::connection(),
            'cms_page_cache_test',
            'prefix:',
        );

        Cache::extend( 'cms-database-test', fn() => Cache::repository( $store ) );
        config( [
            'cache.stores.cms-database-test' => [
                'driver' => 'cms-database-test',
                'table' => 'cms_page_cache_test',
            ],
            'cms.theme.cache' => 'cms-database-test',
        ] );
        $cache = Cache::store( 'cms-database-test' );
        $method = new \ReflectionMethod( PageCache::class, 'routeKey' );
        $old = $method->invoke( null, 'test', 'example.com', 'old' );
        $new = $method->invoke( null, 'test', 'example.com', 'new' );
        $keep = $method->invoke( null, 'test', 'example.com', 'keep' );

        $cache->put( $old, 'old', 60 );
        $cache->put( $new, 'new', 60 );
        $cache->put( $keep, 'keep', 60 );
        DB::flushQueryLog();
        DB::enableQueryLog();

        PageCache::invalidate( 'example.com', ['old', 'new'], 'test' );

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount( 1, array_filter(
            $queries,
            fn( array $query ) => str_starts_with( strtolower( $query['query'] ), 'delete ' ),
        ) );
        $this->assertNull( $cache->get( $old ) );
        $this->assertNull( $cache->get( $new ) );
        $this->assertSame( 'keep', $cache->get( $keep ) );
    }


    public function testDeletesRedisRoutesInOneTenantBatch(): void
    {
        $deleted = [];
        $pipeline = \Mockery::mock( \Redis::class );
        $pipeline->shouldReceive( 'unlink' )
            ->twice()
            ->andReturnUsing( function( string ...$keys ) use ( &$deleted ) {
                $deleted = [...$deleted, ...$keys];
                return count( $keys );
            } );
        $pipeline->shouldReceive( 'exec' )->once()->andReturn( [] );
        $client = \Mockery::mock( \Redis::class );
        $client->shouldReceive( 'pipeline' )->once()->andReturn( $pipeline );
        $connection = new PhpRedisConnection( $client );
        $redis = \Mockery::mock( RedisFactory::class );
        $redis->shouldReceive( 'connection' )->once()->andReturn( $connection );
        $store = new RedisStore( $redis, 'prefix:' );

        Cache::extend( 'cms-redis-test', fn() => Cache::repository( $store ) );
        config( [
            'cache.stores.cms-redis-test' => ['driver' => 'cms-redis-test'],
            'cms.theme.cache' => 'cms-redis-test',
        ] );

        PageCache::invalidate( 'example.com', ['old', 'new'], 'test' );

        $this->assertCount( 2, $deleted );
        $this->assertStringStartsWith( 'prefix:{', $deleted[0] );
        $this->assertStringContainsString( '}:2:', $deleted[1] );
    }


    public function testFallbackStoreDeletesRoutesInBoundedChunks(): void
    {
        $repository = new class( new ArrayStore() ) extends Repository {
            /** @var list<list<string>> */
            public array $chunks = [];


            public function deleteMultiple( $keys ): bool
            {
                $keys = array_values( is_array( $keys ) ? $keys : iterator_to_array( $keys ) );
                $this->chunks[] = $keys;

                return parent::deleteMultiple( $keys );
            }
        };

        Cache::extend( 'cms-array-chunks', fn() => $repository );
        config( [
            'cache.stores.cms-array-chunks' => ['driver' => 'cms-array-chunks'],
            'cms.theme.cache' => 'cms-array-chunks',
        ] );
        $paths = array_map( fn( int $idx ) => 'page-' . $idx, range( 1, 501 ) );

        PageCache::invalidate( 'example.com', $paths, 'test' );

        $this->assertSame( [500, 1], array_map( 'count', $repository->chunks ) );
    }
}
