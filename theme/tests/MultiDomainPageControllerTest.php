<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

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


    public function testDottedDomainServesRootPage() : void
    {
        $this->get( 'https://mydomain.tld/' )
            ->assertOk()
            ->assertSee( 'Welcome to Laravel CMS' );

        $this->get( 'https://mydomain.tld/?source=test' )
            ->assertOk()
            ->assertSee( 'Welcome to Laravel CMS' );

        $this->get( 'https://mydomain.tld/sitemap-1.xml' )
            ->assertOk();
    }
}
