<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Keywords;
use Aimeos\KeywordsBridge\Facades\Keywords as Bridge;
use Aimeos\KeywordsBridge\Keyword;


class KeywordsTest extends CmsTestAbstract
{
    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'keywords-bridge.default', 'test' );
    }


    public function testNoDriver() : void
    {
        config( ['keywords-bridge.default' => 'none'] );
        Bridge::shouldReceive( 'related' )->never();

        $this->expectException( \Aimeos\Cms\Exception::class );
        Keywords::related( 'laravel cms' );
    }


    public function testRelatedComplete() : void
    {
        Bridge::shouldReceive( 'related' )->once()->with( 'laravel cms', null, 10 )
            ->andReturn( [new Keyword( 'laravel headless cms', 320 )] );

        Keywords::related( 'laravel cms', null, 10 );

        // the cached list already contains all related keywords
        $this->assertCount( 1, Keywords::related( 'laravel cms', null, 50 ) );
    }


    public function testRelatedFilter() : void
    {
        Bridge::shouldReceive( 'related' )->once()->andReturn( [
            new Keyword( 'laravel headless cms', 320 ),
            new Keyword( str_repeat( 'laravel cms ', 7 ), 10 ),
            new Keyword( "laravel\u{200B}cms", 10 ),
            new Keyword( "laravel\x00cms", 10 ),
        ] );

        $this->assertEquals( [['keyword' => 'laravel headless cms', 'volume' => 320]], Keywords::related( 'laravel cms' ) );
    }


    public function testRelatedLimit() : void
    {
        Bridge::shouldReceive( 'related' )->once()->with( 'laravel cms', null, 2 )
            ->andReturn( [new Keyword( 'laravel headless cms', 320 ), new Keyword( 'laravel blog', 210 )] );
        Bridge::shouldReceive( 'related' )->once()->with( 'laravel cms', null, 3 )
            ->andReturn( [new Keyword( 'laravel headless cms', 320 ), new Keyword( 'laravel blog', 210 ), new Keyword( 'filament cms', 90 )] );

        Keywords::related( 'laravel cms', null, 2 );

        // smaller limits use the cached list, larger ones fetch it again
        $this->assertEquals( [['keyword' => 'laravel headless cms', 'volume' => 320]], Keywords::related( 'laravel cms', null, 1 ) );
        $this->assertCount( 3, Keywords::related( 'laravel cms', null, 3 ) );
    }


    public function testRelatedVolume() : void
    {
        Bridge::shouldReceive( 'related' )->once()->andReturn( [new Keyword( 'laravel headless cms', 320 )] );
        Bridge::shouldReceive( 'volume' )->once()->with( ['laravel cms'], 'us' )->andReturn( [new Keyword( 'laravel cms', 1900 )] );

        Keywords::related( 'laravel cms', 'us' );

        $this->assertEquals( [
            ['keyword' => 'laravel headless cms', 'volume' => 320],
            ['keyword' => 'laravel cms', 'volume' => 1900],
        ], Keywords::volume( ['laravel headless cms', 'laravel cms'], 'us' ) );
    }


    public function testVolumeCached() : void
    {
        Bridge::shouldReceive( 'volume' )->once()->with( ['laravel cms', 'headless cms'], null )
            ->andReturn( [new Keyword( 'laravel cms', 1900 )] );
        Bridge::shouldReceive( 'volume' )->once()->with( ['pagible'], null )
            ->andReturn( [new Keyword( 'pagible', 10 )] );

        Keywords::volume( ['laravel cms', 'headless cms'] );

        $this->assertEquals( [
            ['keyword' => 'headless cms', 'volume' => null],
            ['keyword' => 'pagible', 'volume' => 10],
            ['keyword' => 'laravel cms', 'volume' => 1900],
        ], Keywords::volume( ['headless cms', 'pagible', 'laravel cms'] ) );
    }
}
