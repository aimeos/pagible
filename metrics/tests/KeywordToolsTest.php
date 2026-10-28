<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Tools\GetKeywordVolume;
use Aimeos\Cms\Tools\GetRelatedKeywords;
use Aimeos\KeywordsBridge\Facades\Keywords as Bridge;
use Aimeos\KeywordsBridge\Keyword;


class KeywordToolsTest extends MetricsTestAbstract
{
    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'keywords-bridge.default', 'test' );
    }


    protected function setUp() : void
    {
        parent::setUp();

        $this->user = new \App\Models\User( ['cmsperms' => ['page:metrics']] );
    }


    public function testNoDriver() : void
    {
        config( ['keywords-bridge.default' => 'none'] );
        Bridge::shouldReceive( 'volume' )->never();

        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, ['keywords' => ['laravel cms']] )
            ->assertSee( 'No keyword service configured' );
    }


    public function testVolume() : void
    {
        Bridge::shouldReceive( 'volume' )->once()->with( ['laravel cms', 'headless cms'], 'de' )
            ->andReturn( [new Keyword( 'laravel cms', 1900 )] );

        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, [
            'keywords' => [' Laravel  CMS', 'headless cms', 'laravel cms'],
            'country' => 'de',
        ] )->assertOk()->assertSee( ['"keyword":"laravel cms","volume":1900', '"keyword":"headless cms","volume":null'] );
    }


    public function testVolumeError() : void
    {
        Bridge::shouldReceive( 'volume' )->once()->andThrow( new \RuntimeException( 'https://api.example.com?key=secret' ) );

        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, ['keywords' => ['laravel cms']] )
            ->assertSee( 'Fetching the keyword data failed' )->assertDontSee( 'secret' );
    }


    public function testVolumeInvalid() : void
    {
        Bridge::shouldReceive( 'volume' )->never();

        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, ['keywords' => ['laravel cms'], 'country' => 'deu'] )
            ->assertHasErrors();
        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, ['keywords' => ['laravel cms'], 'country' => 'äß'] )
            ->assertHasErrors();
        CmsServer::actingAs( $this->user )->tool( GetKeywordVolume::class, ['keywords' => array_fill( 0, 101, 'cms' )] )
            ->assertHasErrors();
    }


    public function testVolumePermission() : void
    {
        Bridge::shouldReceive( 'volume' )->never();

        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        CmsServer::actingAs( $user )->tool( GetKeywordVolume::class, ['keywords' => ['laravel cms']] )
            ->assertSee( 'not found' );
    }


    public function testRelated() : void
    {
        Bridge::shouldReceive( 'related' )->once()->with( 'laravel cms', 'us', 10 )
            ->andReturn( [new Keyword( 'laravel headless cms', 320 )] );

        foreach( ['Laravel CMS', 'laravel cms'] as $keyword )
        {
            CmsServer::actingAs( $this->user )->tool( GetRelatedKeywords::class, [
                'keyword' => $keyword,
                'country' => 'us',
                'limit' => 10,
            ] )->assertOk()->assertSee( '"keyword":"laravel headless cms","volume":320' );
        }
    }


    public function testRelatedPermission() : void
    {
        Bridge::shouldReceive( 'related' )->never();

        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        CmsServer::actingAs( $user )->tool( GetRelatedKeywords::class, ['keyword' => 'laravel cms'] )
            ->assertSee( 'not found' );
    }
}
