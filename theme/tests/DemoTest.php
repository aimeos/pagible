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


    public function testSeedTaste(): void
    {
        require_once dirname( __DIR__, 2 ) . '/themes/taste/database/seeders/TasteDemo.php';

        app()->register( \Aimeos\Cms\TasteServiceProvider::class );

        ( new \Database\Seeders\TasteDemo( 'taste', 'taste' ) )->seed();

        Tenancy::$callback = fn() => 'taste';
        app()->forgetInstance( Tenancy::class );

        $home = Page::where( 'tag', 'root' )->firstOrFail();
        $visit = Page::where( 'path', 'visit' )->firstOrFail();
        $restaurant = $home->config->{'taste::restaurant'}->data;
        $hero = collect( (array) $home->content )->first( fn( $item ) => ( $item->type ?? null ) === 'hero' );
        $pricing = collect( (array) $home->content )->first( fn( $item ) => ( $item->type ?? null ) === 'pricing' );
        $map = collect( (array) $visit->content )->first( fn( $item ) => ( $item->type ?? null ) === 'map' );

        $this->assertSame( 'en', $home->latest->data->lang );
        $this->assertSame( 'Kastanienallee 48', $restaurant->{'street-address'} );
        $this->assertSame( 'Japanese, Ramen', $restaurant->cuisine );
        $this->assertSame( '/menu', $restaurant->menu );
        $this->assertSame( '€€', $restaurant->{'price-range'} );
        $this->assertCount( 11, (array) $restaurant->hours );
        $this->assertSame( 'Walk in with 1–3 guests · Tue–Sun from 12:00', $hero->data->subtitle );
        $this->assertSame( '/visit#table-request', $hero->data->{'url-alternative'} );
        $this->assertIsObject( $map );
        $this->assertSame( 52.538456, $map->data->location->latitude );
        $this->assertSame( 13.409564, $map->data->location->longitude );
        $this->assertSame( 16, $map->data->location->zoom );
        $this->assertSame( [
            'House bowl · gluten-free option',
            'Gluten-free option',
            'Vegan · gluten-free option',
        ], array_map( fn( $item ) => $item->badge ?? null, (array) $pricing->data->items ) );

        $images = File::where( 'mime', 'image/jpeg' )->get();

        $this->assertCount( 10, $images );

        foreach( $images as $image ) {
            $this->assertNotSame( '', $image->description->en ?? '', $image->name );
            $this->assertNotSame( '', $image->description->de ?? '', $image->name );
        }

        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( '"@type": "Restaurant"', false );
        $response->assertSee( '"streetAddress": "Kastanienallee 48"', false );
        $response->assertSee( '"servesCuisine": ["Japanese","Ramen"]', false );
        $response->assertSee( '"openingHoursSpecification": [{"@type":"OpeningHoursSpecification"', false );
        $response->assertSee( '"hasMenu": "' . url( '/menu' ) . '"', false );
        $response->assertSee( '"priceRange": "€€"', false );

        $response = $this->get( '/visit' );

        $response->assertOk();
        $response->assertSee( 'https://www.openstreetmap.org/export/embed.html?', false );
        $response->assertSee( 'marker=52.538456%2C13.409564', false );
        $response->assertSee( '© OpenStreetMap contributors', false );
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
