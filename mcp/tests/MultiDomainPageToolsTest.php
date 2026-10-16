<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\PageView;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class MultiDomainPageToolsTest extends McpTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'app.url', 'http://mydomain.tld' );
        $app['config']->set( 'cms.multidomain', true );
    }


    protected function setUp(): void
    {
        parent::setUp();

        Access::using( fn() => ['member', 'staff'] );

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    public function testGetPageReturnsMultiDomainUrl() : void
    {
        $page = PageView::where( 'name', 'Home' )->firstOrFail();

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\GetPage::class, [
            'id' => $page->id,
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'url', 'http://mydomain.tld' )
            ->etc()
        );
    }


    public function testSavePageReturnsMultiDomainUrl() : void
    {
        $page = PageView::where( 'name', 'Home' )->firstOrFail();

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
            'id' => $page->id,
            'latest_id' => $page->latest_id,
            'name' => 'Updated Home',
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'url', 'http://mydomain.tld' )
            ->etc()
        );
    }


    public function testSearchPagesReturnsMultiDomainUrl() : void
    {
        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\SearchPages::class, [
            'term' => 'Home',
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'pages.0.url', 'http://mydomain.tld' )
            ->etc()
        );
    }


    public function testRestorePageReturnsMultiDomainUrl() : void
    {
        $page = PageView::where( 'name', 'Dev' )->firstOrFail();
        $page->variant->update( ['domain' => 'otherdomain.tld'] );
        $page->page->delete();

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\RestorePage::class, [
            'id' => $page->id,
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'url', 'http://otherdomain.tld/dev' )
            ->etc()
        );
    }


    public function testMovePageReturnsMultiDomainUrl() : void
    {
        $page = PageView::where( 'name', 'Dev' )->firstOrFail();
        $parent = PageView::where( 'name', 'Blog' )->firstOrFail();
        $page->variant->update( ['domain' => 'otherdomain.tld'] );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\MovePage::class, [
            'id' => $page->id,
            'parent_id' => $parent->id,
        ] )->assertOk()->assertStructuredContent( fn( $json ) => $json
            ->where( 'url', 'http://otherdomain.tld/dev' )
            ->etc()
        );
    }
}
