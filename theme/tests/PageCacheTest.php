<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\PageCache;
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
}
