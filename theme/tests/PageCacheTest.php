<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\PageCache;
use Aimeos\Cms\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;


class PageCacheTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    public function testClearsOnlyRequestedTenantDomain(): void
    {
        config( ['cms.theme.cache' => 'array'] );
        $cache = Cache::store( 'array' );
        $routeKey = new \ReflectionMethod( PageCache::class, 'routeKey' );
        $target = $this->page( 'target', 'example.com' );
        $trashed = $this->page( 'trashed', 'example.com' );
        $otherDomain = $this->page( 'other-domain', 'other.example' );
        $otherTenant = Tenancy::run( 'other', fn() => $this->page( 'target', 'example.com' ) );
        $target->update( ['status' => 0] );
        $trashed->delete();

        $targetKey = $routeKey->invoke( null, 'test', $target->domain, $target->path );
        $trashedKey = $routeKey->invoke( null, 'test', $trashed->domain, $trashed->path );
        $otherDomainKey = $routeKey->invoke( null, 'test', $otherDomain->domain, $otherDomain->path );
        $otherTenantKey = $routeKey->invoke( null, 'other', $otherTenant->domain, $otherTenant->path );

        foreach( [$targetKey, $trashedKey, $otherDomainKey, $otherTenantKey] as $key ) {
            $cache->put( $key, $key );
        }

        request()->attributes->set( 'cms.jsonapi', true );
        PageInvalidated::dispatch( 'example.com' );

        $this->assertNull( $cache->get( $targetKey ) );
        $this->assertNull( $cache->get( $trashedKey ) );
        $this->assertSame( $otherDomainKey, $cache->get( $otherDomainKey ) );
        $this->assertSame( $otherTenantKey, $cache->get( $otherTenantKey ) );
    }


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

        PageInvalidated::dispatch( 'example.com', 'old' );

        $this->assertNull( $cache->get( $old ) );
        $this->assertSame( 'new', $cache->get( $new ) );
        $this->assertSame( 'keep', $cache->get( $keep ) );
    }


    private function page( string $path, string $domain ): Page
    {
        return Page::forceCreate( [
            'lang' => 'en',
            'name' => $path,
            'title' => $path,
            'path' => $path,
            'domain' => $domain,
            'status' => 1,
            'editor' => 'test',
        ] );
    }
}
