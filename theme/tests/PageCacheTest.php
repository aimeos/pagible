<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\PageCache;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;


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


    public function testReturnsGzipCachedPageWhenAccepted(): void
    {
        if( !function_exists( 'gzdecode' ) ) {
            $this->markTestSkipped( 'The zlib extension is not available.' );
        }

        $html = str_repeat( '<p>cached page</p>', 100 );
        $this->cache( 'compressed', $html );
        $key = ( new \ReflectionMethod( PageCache::class, 'key' ) )->invoke( null, 'compressed' );
        $entry = Cache::store( 'array' )->get( $key );

        $this->assertIsArray( $entry );
        $this->assertArrayHasKey( 'gzip', $entry );
        $this->assertArrayNotHasKey( 'html', $entry );

        foreach( ['gzip', 'br, *;q=0.5'] as $encoding )
        {
            request()->headers->set( 'Accept-Encoding', $encoding );
            $response = PageCache::response( 'compressed' );

            $this->assertNotNull( $response );
            $this->assertSame( 'gzip', $response->headers->get( 'Content-Encoding' ) );
            $this->assertSame( 'Accept-Encoding', $response->headers->get( 'Vary' ) );
            $this->assertSame( $entry['gzip'], $response->getContent() );
            $this->assertSame( $html, gzdecode( (string) $response->getContent() ) );
        }
    }


    public function testReturnsIdentityCachedPageWhenGzipIsRejected(): void
    {
        $html = str_repeat( '<p>cached page</p>', 100 );
        $this->cache( 'identity', $html );
        request()->headers->set( 'Accept-Encoding', 'gzip;q=0, br' );

        $response = PageCache::response( 'identity' );

        $this->assertNotNull( $response );
        $this->assertNull( $response->headers->get( 'Content-Encoding' ) );
        $this->assertSame( 'Accept-Encoding', $response->headers->get( 'Vary' ) );
        $this->assertSame( $html, $response->getContent() );
    }


    private function cache( string $path, string $html ): void
    {
        config( ['cms.theme.cache' => 'array'] );

        PageCache::remember( fn() => ( new Response( $html, 200 ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            $path,
        );
    }
}
