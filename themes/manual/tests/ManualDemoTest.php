<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\ManualDemo;
use Illuminate\Foundation\Testing\RefreshDatabase;


class ManualDemoTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function setUp() : void
    {
        parent::setUp();

        require_once dirname( __DIR__ ) . '/database/seeders/ManualDemo.php';

        ( new ManualDemo( 'manual', 'manual' ) )->seed();
        Tenancy::$callback = fn() => 'manual';
        app()->forgetInstance( Tenancy::class );
    }


    public function testCallouts() : void
    {
        $response = $this->get( '/installation' );

        $response->assertOk();
        $response->assertSee( '<blockquote class="callout note"><p class="callout-title">Note</p>', false );
        $response->assertSee( '<blockquote class="callout tip"><p class="callout-title">Tip</p>', false );
        $response->assertDontSee( '[!NOTE]' );

        $this->get( '/auditable' )->assertSee( 'class="callout caution"', false );
        $this->get( '/configuration' )->assertSee( 'class="callout warning"', false );
    }


    public function testChangelog() : void
    {
        $response = $this->get( '/changelog' );

        $response->assertOk();
        $response->assertSee( 'timeline', false );
        $response->assertSee( 'v2.4.0 · Oct 6, 2026' );
        $response->assertSee( 'Batches and exports' );
    }


    public function testDemo() : void
    {
        $docs = Page::where( 'path', 'docs' )->firstOrFail();

        $this->assertSame( 'manual', Page::where( 'tag', 'root' )->firstOrFail()->theme );
        $this->assertSame( 'docs', $docs->type );
        $this->assertCount( 2, Page::where( 'parent_id', $docs->id )->get() );
    }


    public function testDocs() : void
    {
        $response = $this->get( '/quickstart' );

        $response->assertOk();
        $response->assertSee( 'type-docs', false );
        $response->assertSee( '"@type": "TechArticle"', false );
        $response->assertSee( '<nav class="sidebar"', false );
        $response->assertSee( 'class="section"', false );
        $response->assertSee( 'aria-current="page"', false );
        $response->assertSee( 'On this page' );
        $response->assertSee( 'class="pager"', false );
        $response->assertSee( 'Previous' );
        $response->assertSee( 'Next' );
        $response->assertSee( 'Installation' );
        $response->assertSee( 'Configuration' );
        $response->assertSee( 'class="code-copy"', false );
        $response->assertSee( 'data-copied="Copied"', false );
        $response->assertSee( 'Last updated' );
    }


    public function testHome() : void
    {
        $response = $this->get( '/' );

        $response->assertOk();
        $response->assertSee( 'theme-manual', false );
        $response->assertSee( '"@type": "SoftwareSourceCode"', false );
        $response->assertSee( '"programmingLanguage": "PHP"', false );
        $response->assertSee( '"codeRepository": "https://github.com/waypoint-php/waypoint"', false );
        $response->assertSee( '<span class="version">v2.4</span>', false );
        $response->assertSee( 'class="repository"', false );
        $response->assertSee( 'Search docs' );
        $response->assertSee( 'License: MIT' );
        $response->assertSee( 'Everything an audit log needs' );
        $response->assertDontSee( '<nav class="sidebar"', false );
    }


    public function testPages() : void
    {
        $paths = ['/docs', '/getting-started', '/installation', '/quickstart', '/configuration', '/guides', '/custom-events',
            '/pruning', '/testing', '/api', '/auditable', '/activity', '/facade', '/commands', '/changelog'];

        foreach( $paths as $path ) {
            $this->get( $path )->assertOk();
        }

        $this->get( '/auditable' )->assertSee( 'withoutAuditing(callable $callback)' );
        $this->get( '/commands' )->assertSee( 'waypoint:prune' );
        $this->get( '/quickstart' )->assertSee( 'class="language-php"', false );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\ManualServiceProvider',
        ] );
    }
}
