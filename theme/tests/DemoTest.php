<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\Demo as DemoCommand;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Tenancy;
use Database\Seeders\DefaultDemo;


class DemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;


    public function testResolvesByConvention(): void
    {
        require_once __DIR__ . '/ConventionDemo.php';

        $this->assertInstanceOf( \Database\Seeders\ConventionDemo::class, DemoCommand::make( 'convention', 'x' ) );
        $this->assertInstanceOf( DefaultDemo::class, DemoCommand::make( '', 'x' ) );
        $this->assertInstanceOf( DefaultDemo::class, DemoCommand::make( 'missing', 'x' ) );
    }


    public function testSeedDefault(): void
    {
        ( new DefaultDemo( '', 'demo' ) )->seed();

        Tenancy::$callback = fn() => 'demo';

        $home = Page::where( 'tag', 'root' )->firstOrFail();

        $this->assertSame( '', $home->theme );
        $this->assertSame( 'demo', $home->tenant_id );
        $this->assertNotNull( $home->latest_id );
        $this->assertTrue( collect( (array) $home->content )->contains( fn( $item ) => ( $item->type ?? null ) === 'testimonial' ) );
        $this->assertGreaterThan( 0, Page::where( 'path', 'blog' )->count() );
        $this->assertGreaterThan( 0, Page::where( 'type', 'docs' )->count() );

        foreach( Page::get() as $page )
        {
            $meta = (array) $page->meta;

            $this->assertArrayHasKey( 'meta-tags', $meta );
            $this->assertArrayHasKey( 'social-media', $meta );

            $description = $meta['meta-tags']->data->description ?? '';

            $this->assertNotSame( '', $description );
            $this->assertStringContainsString( $description, (string) $page );
            $this->assertStringContainsString( $description, (string) $page->latest );
        }
    }


    public function testSeedTheme(): void
    {
        ( new DefaultDemo( 'luxury', 'luxury' ) )->seed();

        Tenancy::$callback = fn() => 'luxury';

        $home = Page::where( 'tag', 'root' )->firstOrFail();

        $this->assertSame( 'luxury', $home->theme );
        $this->assertSame( 'luxury', $home->tenant_id );
    }


    public function testCommand(): void
    {
        $this->artisan( 'cms:demo', ['--theme' => 'paper', '--tenant' => 'showcase'] )->assertExitCode( 0 );

        Tenancy::$callback = fn() => 'showcase';

        $this->assertSame( 'paper', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testCommandAll(): void
    {
        Schema::register( dirname( __DIR__, 2 ) . '/themes/luxury', 'luxury' );

        $this->artisan( 'cms:demo', ['--all' => true] )->assertExitCode( 0 );

        Tenancy::$callback = fn() => 'luxury';

        $this->assertSame( 'luxury', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }
}
