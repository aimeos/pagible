<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;


class SitemapControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    public function testIndex()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

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


    public function testIndexAsSitemapIndex()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $controller = new SitemapControllerLowThreshold();

        $response = $controller->index();
        $content = $response->getContent();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('<sitemapindex', $content);
        $this->assertStringContainsString('</sitemapindex>', $content);
        $this->assertStringContainsString('<loc><![CDATA[http://localhost/sitemap-1.xml]]></loc>', $content);
        $this->assertStringNotContainsString('<urlset', $content);
    }


    public function testChunk()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

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
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $controller = new SitemapControllerLowThreshold();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        $controller->chunk(999);
    }
}


class SitemapControllerLowThreshold extends \Aimeos\Cms\Controllers\SitemapController
{
    protected const URLS_PER_SITEMAP = 2;
}
