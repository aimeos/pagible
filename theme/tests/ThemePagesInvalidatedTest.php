<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\PageCache;
use Illuminate\Support\Facades\Cache;


class ThemePagesInvalidatedTest extends ThemeTestAbstract
{
    public function testDeletesOnlyAffectedRouteEntries(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $cache = Cache::store( 'array' );
        $method = new \ReflectionMethod( PageCache::class, 'key' );
        $old = $method->invoke( null, 'old', 'example.com' );
        $new = $method->invoke( null, 'new', 'example.com' );
        $keep = $method->invoke( null, 'keep', 'example.com' );

        $cache->put( $old, 'old' );
        $cache->put( $new, 'new' );
        $cache->put( $keep, 'keep' );

        PagesInvalidated::dispatch( [
            ['domain' => 'example.com', 'path' => 'old'],
            ['domain' => 'example.com', 'path' => 'new'],
        ] );

        $this->assertNull( $cache->get( $old ) );
        $this->assertNull( $cache->get( $new ) );
        $this->assertSame( 'keep', $cache->get( $keep ) );
    }
}
