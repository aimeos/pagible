<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\Cache;


class ThemePagesInvalidatedTest extends ThemeTestAbstract
{
    public function testDeletesOnlyAffectedRouteEntries(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $cache = Cache::store( 'array' );
        $old = Page::key( 'old', 'example.com' );
        $new = Page::key( 'new', 'example.com' );
        $keep = Page::key( 'keep', 'example.com' );

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
