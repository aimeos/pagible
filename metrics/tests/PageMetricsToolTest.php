<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\AnalyticsBridge\Facades\Analytics;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Tools\GetPageMetrics;


class PageMetricsToolTest extends MetricsTestAbstract
{
    protected function setUp() : void
    {
        parent::setUp();

        $this->user = new \App\Models\User( ['cmsperms' => ['page:metrics']] );
    }


    public function testRegistered() : void
    {
        $this->assertContains( GetPageMetrics::class, CmsServer::tools() );
    }


    public function testNoPermission() : void
    {
        CmsServer::actingAs( new \App\Models\User() )->tool( GetPageMetrics::class, ['url' => 'https://example.com/blog'] )
            ->assertHasErrors();
    }


    public function testGetPageMetrics()
    {
        Analytics::shouldReceive('driver->stats')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn([
                'views' => [['key' => '2026-03-01', 'value' => 100]],
                'conversions' => [['key' => '2026-03-01', 'value' => 5]],
            ]);

        Analytics::shouldReceive('search')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn(['impressions' => 50, 'clicks' => 10]);

        Analytics::shouldReceive('queries')
            ->once()
            ->with('https://example.com/blog', 30)
            ->andReturn([['key' => 'test query', 'clicks' => 5]]);

        Analytics::shouldReceive('pagespeed')
            ->once()
            ->with('https://example.com/blog')
            ->andReturn([['key' => 'time_to_first_byte', 'value' => 250]]);

        $response = CmsServer::actingAs($this->user)->tool( GetPageMetrics::class, [
            'url' => 'https://example.com/blog',
        ] );

        $response->assertOk()->assertSee( ['views', 'pagespeed'] )->assertDontSee( 'conversions' );
    }
}
