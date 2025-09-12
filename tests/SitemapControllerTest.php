<?php

namespace Tests;


class SitemapControllerTest extends TestAbstract
{
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

        $this->assertStringContainsString('<loc>http://localhost/hidden</loc>', $content);
        $this->assertStringContainsString('<loc>http://localhost/disabled-child</loc>', $content);
        $this->assertStringNotContainsString('<loc>http://localhost/disabled</loc>', $content);
    }
}
