<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\AnalyticsBridge\Facades\Analytics;


class ToolsTest extends TestAbstract
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->user = \App\Models\User::create([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmseditor' => 0x7fffffff
        ]);
    }


    public function testGetLocales()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetLocales::class );

        $response->assertOk()->assertStructuredContent( ['en', 'de'] );
    }


    public function testGoogleQueries()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        Analytics::shouldReceive( 'queries' )
            ->once()
            ->with( 'https://example.com/blog' )
            ->andReturn( [
                ['key' => 'laravel cms', 'impressions' => 100, 'clicks' => 10, 'ctr' => 0.1, 'position' => 5],
                ['key' => 'aimeos', 'impressions' => 50, 'clicks' => 5, 'ctr' => 0.1, 'position' => 3],
            ] );


        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GoogleQueries::class, [
            'domain' => 'example.com',
            'path' => '/blog',
        ] );

        $response->assertOk()->assertStructuredContent( [
            ['impressions' => 100, 'clicks' => 10, 'ctr' => 0.1, 'position' => 5, 'query' => 'laravel cms'],
            ['impressions' => 50, 'clicks' => 5, 'ctr' => 0.1, 'position' => 3, 'query' => 'aimeos'],
        ] );
    }


    public function testAddPage()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddPage::class, [
            'lang' => 'en',
            'name' => 'Test page',
            'title' => 'A Test Page',
            'summary' => 'This is a test page.',
            'content' => '# Hello World\nThis is a test page.',
        ] );

        $response->assertOk()->assertSee( [
            'en',
            'Test page',
            'A Test Page',
        ] );
    }


    public function testSearchPages()
    {
        $this->seed( \Database\Seeders\CmsSeeder::class );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'lang' => 'en',
            'term' => 'blog',
        ] );

        $response->assertOk()->assertSee( [
            'en',
            'blog',
            'Blog | Laravel CMS',
        ] );
    }
}
