<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class RobotsControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testIndex() : void
    {
        $this->robots( "User-agent: *\r\nDisallow: /private\r\n" );

        $response = $this->get( '/robots.txt' );

        $response->assertOk();
        $response->assertHeader( 'Content-Type', 'text/plain; charset=utf-8' );
        $this->assertTrue( $response->headers->hasCacheControlDirective( 'public' ) );
        $this->assertSame( '300', $response->headers->getCacheControlDirective( 'max-age' ) );
        $this->assertSame(
            $this->expected( "User-agent: *\nDisallow: /private" ),
            $response->getContent(),
        );
    }


    public function testIndexDoesNotDuplicateConfiguredSitemaps() : void
    {
        $this->robots(
            "User-agent: *\n"
                . "Sitemap: http://localhost/site-map.xml\n"
                . "sitemap: http://localhost/site-map-news.xml\n"
                . "Sitemap: http://localhost/SITE-MAP.xml\n"
                . "Sitemap: https://other.example/sitemap.xml"
        );

        $response = $this->get( '/robots.txt' );
        $content = $response->getContent();

        $response->assertOk();
        $this->assertSame( 1, substr_count( $content, 'Sitemap: http://localhost/site-map.xml' ) );
        $this->assertSame( 1, substr_count( $content, 'http://localhost/site-map-news.xml' ) );
        $this->assertStringContainsString( 'Sitemap: http://localhost/SITE-MAP.xml', $content );
        $this->assertStringContainsString( 'Sitemap: https://other.example/sitemap.xml', $content );
    }


    public function testIndexIgnoresInvalidConfiguredText() : void
    {
        $this->robots( "User-agent: *\nDisallow: /private\0public" );

        $this->get( '/robots.txt' )
            ->assertOk()
            ->assertContent( $this->expected() );
    }


    public function testIndexIgnoresOversizedConfiguredText() : void
    {
        $this->robots( str_repeat( 'a', 500 * 1024 + 1 ) );

        $response = $this->get( '/robots.txt' );

        $response->assertOk();
        $response->assertContent( $this->expected() );
        $this->assertLessThanOrEqual( 500 * 1024, strlen( $response->getContent() ) );
    }


    public function testIndexIsAvailableWithEmptyConfig() : void
    {
        $this->robots( " \n" );

        $this->get( '/robots.txt' )
            ->assertOk()
            ->assertContent( $this->expected() );
    }


    public function testIndexIsAvailableWithoutConfig() : void
    {
        $this->get( '/robots.txt' )
            ->assertOk()
            ->assertContent( $this->expected() );
    }


    public function testIndexStripsUtf8Bom() : void
    {
        $this->robots( "\xEF\xBB\xBFUser-agent: *\nDisallow: /private" );

        $this->get( '/robots.txt' )
            ->assertOk()
            ->assertContent( $this->expected( "User-agent: *\nDisallow: /private" ) );
    }


    public function testIndexUsesEtagForConditionalRequests() : void
    {
        $response = $this->get( '/robots.txt' );
        $etag = $response->headers->get( 'ETag' );

        $response->assertOk();
        $this->assertNotNull( $etag );

        $this->withHeader( 'If-None-Match', $etag )
            ->get( '/robots.txt' )
            ->assertStatus( 304 )
            ->assertHeader( 'ETag', $etag )
            ->assertContent( '' );
    }


    public function testRouteDoesNotStartWebSession() : void
    {
        $route = app( 'router' )->getRoutes()->getByName( 'cms.robots' );

        $this->assertNotNull( $route );
        $this->assertNotContains( 'web', $route->gatherMiddleware() );
    }


    public function testUnpublishedDraftDoesNotLeakIntoPublicResponse() : void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $config = fn( string $text ) => ['robots-txt' => [
            'type' => 'robots-txt',
            'data' => ['text' => $text],
            'files' => [],
        ]];
        $user = new \App\Models\User( [
            'email' => 'test@example.com',
            'cmsperms' => ['admin'],
        ] );

        Resource::savePage( $root->id, ['config' => $config( 'Disallow: /published' )], $user );
        Publication::publish( Page::class, [$root->id], $user );
        Resource::savePage( $root->id, ['config' => $config( 'Disallow: /draft' )], $user );

        $root->refresh()->load( 'latest' );
        $this->assertSame( 'Disallow: /published', $root->config->{'robots-txt'}->data->text );
        $this->assertSame( 'Disallow: /draft', $root->latest->aux->config->{'robots-txt'}->data->text );

        $response = $this->get( '/robots.txt' );

        $response->assertOk();
        $response->assertSeeText( 'Disallow: /published' );
        $response->assertDontSeeText( 'Disallow: /draft' );
    }


    protected function defineEnvironment( $app ) : void
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.theme.sitemap', 'site-map' );
    }


    /**
     * Returns the expected response with generated sitemap locations.
     */
    protected function expected( ?string $text = null ) : string
    {
        return ( $text !== null ? rtrim( $text, "\n" ) . "\n\n" : '' )
            . "Sitemap: http://localhost/site-map.xml\n"
            . "Sitemap: http://localhost/site-map-news.xml\n";
    }


    /**
     * Stores published root-page robots.txt information.
     */
    protected function robots( string $text ) : void
    {
        Page::where( 'tag', 'root' )->firstOrFail()->forceFill( ['config' => [
            'robots-txt' => ['type' => 'robots-txt', 'data' => ['text' => $text], 'files' => []],
        ]] )->saveQuietly();
    }
}
