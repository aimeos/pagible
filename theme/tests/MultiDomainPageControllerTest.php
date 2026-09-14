<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class MultiDomainPageControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'app.url', 'https://mydomain.tld' );
        $app['config']->set( 'cms.multidomain', true );
        $app['config']->set( 'cms.theme.cache', 'array' );
    }


    public function testDottedDomainServesRobotsTxt() : void
    {
        Page::where( 'tag', 'root' )->firstOrFail()->forceFill( ['config' => [
            'robots-txt' => [
                'type' => 'robots-txt',
                'data' => ['text' => "User-agent: *\nDisallow: /private"],
                'files' => [],
            ],
        ]] )->saveQuietly();

        $this->get( 'https://mydomain.tld/robots.txt' )
            ->assertOk()
            ->assertContent(
                "User-agent: *\nDisallow: /private\n\n"
                    . "Sitemap: https://mydomain.tld/sitemap.xml\n"
                    . "Sitemap: https://mydomain.tld/sitemap-news.xml\n"
            );

        $this->get( 'https://otherdomain.tld/robots.txt' )
            ->assertOk()
            ->assertContent(
                "Sitemap: https://otherdomain.tld/sitemap.xml\n"
                    . "Sitemap: https://otherdomain.tld/sitemap-news.xml\n"
            );
    }


    public function testDottedDomainServesRootPage() : void
    {
        $this->get( 'https://mydomain.tld/' )
            ->assertOk()
            ->assertSee( 'Welcome to Laravel CMS' )
            ->assertSee( '<link rel="canonical" href="https://mydomain.tld"', false )
            ->assertSee( 'action="https://mydomain.tld/cmsapi/search?q=_term_"', false );

        $this->get( 'https://mydomain.tld/?source=test' )
            ->assertOk()
            ->assertSee( 'Welcome to Laravel CMS' );

        $this->get( 'https://mydomain.tld/sitemap-1.xml' )
            ->assertOk();
    }
}
