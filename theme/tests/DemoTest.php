<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Commands\Demo as DemoCommand;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\PageCache;
use Aimeos\Cms\Schema;
use Aimeos\Cms\Tenancy;
use Database\Seeders\DefaultDemo;
use Illuminate\Http\Response;


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
        config( ['cache.default' => 'array', 'cms.theme.cache' => 'file'] );
        Tenancy::$callback = fn() => 'demo';
        app()->forgetInstance( Tenancy::class );
        PageCache::remember( fn() => ( new Response( 'cached page without logo' ) )
            ->header( 'Cache-Control', 'public' )
            ->setExpires( now()->addMinutes( 5 ) ),
            '',
        );

        ( new DefaultDemo( '', 'demo' ) )->seed();

        Tenancy::$callback = fn() => 'demo';

        $home = Page::where( 'tag', 'root' )->firstOrFail();

        $this->assertSame( '', $home->theme );
        $this->assertSame( 'demo', $home->tenant_id );
        $this->assertNotNull( $home->latest_id );
        $this->assertTrue( collect( (array) $home->content )->contains( fn( $item ) => ( $item->type ?? null ) === 'testimonial' ) );
        $logoId = $home->config->logo->data->file->id ?? null;
        $this->assertIsString( $logoId );
        $this->assertTrue( $home->files->has( $logoId ) );
        $this->assertNull( PageCache::response( '' ) );
        $response = $this->get( '/' );
        $response->assertSee( 'meridian-works-logo.svg', false );
        $response->assertSee( 'class="login"', false );
        $response->assertSee( 'href="' . route( 'login' ) . '"', false );
        $this->assertGreaterThan( 0, Page::where( 'path', 'blog' )->count() );
        $this->assertGreaterThan( 0, Page::where( 'type', 'docs' )->count() );
        $pricing = collect( (array) $home->content )->first( fn( $item ) => ( $item->type ?? null ) === 'pricing' );
        $this->assertIsObject( $pricing );
        $this->assertSame(
            ['2–3 weeks', '6–10 weeks', 'Retained'],
            array_map( fn( $item ) => $item->prices[0]->label ?? null, (array) $pricing->data->items ),
        );

        foreach( (array) $pricing->data->items as $item )
        {
            foreach( (array) ( $item->prices ?? [] ) as $price ) {
                $this->assertFalse( isset( $price->amount ) && !is_int( $price->amount ) && !is_float( $price->amount ) );
            }
        }

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
        $this->artisan( 'cms:demo', ['--theme' => 'luxury', '--tenant' => 'showcase'] )->assertExitCode( 0 );

        Tenancy::$callback = fn() => 'showcase';

        $this->assertSame( 'luxury', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    public function testCommandAll(): void
    {
        Schema::register( dirname( __DIR__, 2 ) . '/themes/luxury', 'luxury' );

        $this->artisan( 'cms:demo', ['--all' => true] )->assertExitCode( 0 );

        Tenancy::$callback = fn() => 'luxury';

        $this->assertSame( 'luxury', Page::where( 'tag', 'root' )->firstOrFail()->theme );
    }


    /**
     * @param class-string<\Illuminate\Support\ServiceProvider> $provider
     * @param class-string<\Database\Seeders\AbstractDemo> $seeder
     */
    private function assertThemeImages( string $theme, string $provider, string $seeder, int $heroFiles,
        string $image, ?int $slides = null
    ) : void {
        $class = basename( str_replace( '\\', '/', $seeder ) );

        require_once dirname( __DIR__, 2 ) . "/themes/{$theme}/database/seeders/{$class}.php";

        app()->register( $provider );

        ( new $seeder( $theme, $theme ) )->seed();

        Tenancy::$callback = fn() => $theme;
        app()->forgetInstance( Tenancy::class );

        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $hero = collect( (array) $home->content )
            ->first( fn( $item ) => ( $item->type ?? null ) === 'hero' );

        $this->assertIsObject( $hero );
        $this->assertCount( $heroFiles, (array) ( $hero->data->files ?? [] ) );

        Page::with( ['files', 'latest.files'] )->get()->each( function( Page $page ) {
            $this->assertEqualsCanonicalizing( $page->latest->files->keys()->all(), $page->files->keys()->all(), $page->path );
        } );

        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( $image, false );

        if( $slides !== null ) {
            $this->assertSame( $slides, substr_count( (string) $response->getContent(), 'class="hero-slide"' ) );
        }
    }
}
