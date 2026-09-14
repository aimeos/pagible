<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;


class SitemapControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;

    public function testIndex()
    {
        $controller = new \Aimeos\Cms\Controllers\SitemapController();

        ob_start(); // Capture output from stream callback
        $response = $controller->index();
        $response->getCallback()(); // execute the streaming closure
        $content = ob_get_clean();

        // Assertions
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/xml', $response->headers->get('Content-Type'));

        // Basic XML structure
        $this->assertStringStartsWith('<?xml', $content);
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('</urlset>', $content);

        $this->assertStringContainsString('<loc><![CDATA[http://localhost/hidden]]></loc>', $content);
        $this->assertStringContainsString('<loc><![CDATA[http://localhost/disabled-child]]></loc>', $content);
        $this->assertStringNotContainsString('http://localhost/disabled]]>', $content);
    }


    public function testIndexExcludesNoindex()
    {
        \Aimeos\Cms\Models\Page::where( 'path', 'hidden' )->firstOrFail()
            ->forceFill( ['meta' => ['robots' => [
                'type' => 'robots',
                'data' => ['index' => 'noindex'],
                'files' => [],
            ]]] )
            ->saveQuietly();

        $controller = new \Aimeos\Cms\Controllers\SitemapController();

        ob_start();
        $response = $controller->index();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringNotContainsString( 'http://localhost/hidden]]>', $content );
        $this->assertStringContainsString( '<loc><![CDATA[http://localhost/disabled-child]]></loc>', $content );
    }


    public function testNews()
    {
        config( ['app.name' => 'CMS & News', 'app.locale' => 'de_DE'] );

        Page::where( 'tag', 'article' )->firstOrFail()->forceFill( [
            'created_at' => now()->subHour(),
            'lang' => '',
            'path' => 'current & news',
            'tag' => '',
            'title' => 'Current & News',
            'type' => 'news',
        ] )->saveQuietly();
        Page::where( 'path', 'hidden' )->firstOrFail()->forceFill( [
            'created_at' => now()->subHours( 2 ),
            'lang' => 'en_US',
            'title' => 'Hidden News',
            'type' => 'news',
        ] )->saveQuietly();
        Page::where( 'path', 'dev' )->firstOrFail()->forceFill( [
            'created_at' => now()->subDays( 3 ),
            'type' => 'news',
        ] )->saveQuietly();
        Page::where( 'path', 'disabled' )->firstOrFail()->forceFill( [
            'created_at' => now()->subHour(),
            'type' => 'news',
        ] )->saveQuietly();

        $response = $this->get( '/sitemap-news.xml' );
        $content = $response->streamedContent();

        $response->assertOk();
        $response->assertHeader( 'Content-Type', 'application/xml' );
        $this->assertStringContainsString( 'xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $content );
        $this->assertStringContainsString( '<loc><![CDATA[http://localhost/current%20%26%20news]]></loc>', $content );
        $this->assertStringContainsString( '<news:name>CMS &amp; News</news:name>', $content );
        $this->assertStringContainsString( '<news:language>de</news:language>', $content );
        $this->assertStringContainsString( '<news:language>en</news:language>', $content );
        $this->assertStringContainsString( '<news:title>Current &amp; News</news:title>', $content );
        $this->assertStringContainsString( '<loc><![CDATA[http://localhost/hidden]]></loc>', $content );
        $this->assertStringNotContainsString( 'http://localhost/dev]]>', $content );
        $this->assertStringNotContainsString( 'http://localhost/disabled]]>', $content );
        $this->assertLessThan( strpos( $content, 'http://localhost/hidden]]>' ), strpos( $content, 'http://localhost/current%20%26%20news]]>' ) );
    }


    public function testNoncanonicalOriginIsNotSharedCacheable(): void
    {
        config( ['app.url' => 'https://shop.example', 'cms.multidomain' => false] );

        $response = $this->withServerVariables( [
            'HTTPS' => 'on',
            'SERVER_PORT' => 443,
            'HTTP_HOST' => 'evil.example',
        ] )->get( '/sitemap.xml' );

        $response->assertOk();
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'private' ) );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'no-store' ) );
        $this->assertFalse( $response->headers->hasCacheControlDirective( 'public' ) );
        $this->assertFalse( $response->headers->has( 'Expires' ) );
    }


    public function testSitemapRoutesDoNotStartWebSessions(): void
    {
        foreach( ['cms.sitemap', 'cms.sitemap.chunk', 'cms.sitemap.news'] as $name )
        {
            $route = app( 'router' )->getRoutes()->getByName( $name );
            $this->assertNotNull( $route );
            $middleware = $route->gatherMiddleware();

            $this->assertNotContains( 'web', $middleware );
            $this->assertContains( 'throttle:cms-sitemap', $middleware );
        }
    }


    public function testIndexExcludesRestrictedPages()
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        Access::using( fn() => [] );
        PageAccess::set( [$page->id], [] );

        $controller = new \Aimeos\Cms\Controllers\SitemapController();

        ob_start();
        $response = $controller->index();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringNotContainsString( 'http://localhost/hidden]]>', $content );
        $this->assertStringContainsString( '<loc><![CDATA[http://localhost/disabled-child]]></loc>', $content );
    }


    public function testEditorCannotExposeUnpublishedPagesInPublicSitemap()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        $user->tenant_id = 'test';
        $this->actingAs( $user );

        $controller = new \Aimeos\Cms\Controllers\SitemapController();

        ob_start();
        $response = $controller->index();
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertStringNotContainsString( 'http://localhost/disabled]]>', $content );
        $this->assertStringContainsString( 'public', (string) $response->headers->get( 'Cache-Control' ) );
    }


    public function testIndexAsSitemapIndex()
    {
        $controller = new SitemapControllerLowThreshold();

        $response = $controller->index();
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<sitemapindex', $content);
        $this->assertStringContainsString('</sitemapindex>', $content);
        $this->assertStringContainsString('<loc>http://localhost/sitemap-1.xml</loc>', $content);
        $this->assertStringNotContainsString('<urlset', $content);
    }


    public function testChunk()
    {
        $controller = new SitemapControllerLowThreshold();

        ob_start();
        $response = $controller->chunk(1);
        $response->getCallback()();
        $content = ob_get_clean();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<urlset', $content);
        $this->assertStringContainsString('</urlset>', $content);
        $this->assertStringContainsString('<loc><![CDATA[http://localhost/', $content);
    }


    public function testChunkOutOfRange()
    {
        $controller = new SitemapControllerLowThreshold();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->chunk(999);
    }
}


class SitemapControllerLowThreshold extends \Aimeos\Cms\Controllers\SitemapController
{
    protected const URLS_PER_SITEMAP = 2;
}
