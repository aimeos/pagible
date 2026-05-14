<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;


class PageControllerTest extends ThemeTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User();
        $this->user->name = 'Test';
        $this->user->email = 'test@example.com';
        $this->user->cmsperms = ['admin'];
    }


    public function testLatestFindsChangedPath()
    {
        Tenancy::$callback = fn() => 'demo';

        $page = Page::where( 'tag', 'blog' )->firstOrFail();

        // Save with a new path (mimics admin panel which always sends domain)
        Resource::savePage(
            $page->id,
            ['path' => 'new-blog-path', 'domain' => $page->domain ?? ''],
            $this->user,
        );

        // Now try to access the page via the new path (as an editor would)
        $response = $this->actingAs( $this->user )->get( '/new-blog-path' );
        $response->assertStatus( 200 );
    }


    public function testLatestFindsChangedPathNoDomain()
    {
        Tenancy::$callback = fn() => 'demo';

        $page = Page::where( 'tag', 'article' )->firstOrFail();

        // Save with a new path (no domain in input — e.g. MCP tool)
        Resource::savePage(
            $page->id,
            ['path' => 'changed-article-path'],
            $this->user,
        );

        // Try to access via new path
        $response = $this->actingAs( $this->user )->get( '/changed-article-path' );
        $response->assertStatus( 200 );
    }


    public function testLatestFindsChangedPathWithDomainPage()
    {
        Tenancy::$callback = fn() => 'demo';

        // Home page has domain='mydomain.tld' in the seeder
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $this->assertEquals( 'mydomain.tld', $page->domain );

        // Admin panel save sends the page's domain
        Resource::savePage(
            $page->id,
            ['path' => 'new-home', 'domain' => $page->domain],
            $this->user,
        );

        // Without multidomain config, the route has no {domain} parameter,
        // so $domain defaults to '' in the controller
        $response = $this->actingAs( $this->user )->get( '/new-home' );
        $this->assertNotEquals( 404, $response->status() );
    }


    public function testLatestFindsExistingVersionWithoutDomain()
    {
        Tenancy::$callback = fn() => 'demo';

        // Create a page with a version that has no domain in data (legacy/importer case)
        $page = Page::forceCreate([
            'lang' => 'en',
            'name' => 'Test',
            'title' => 'Test Page',
            'path' => 'test-page',
            'status' => 1,
            'editor' => 'test',
        ]);

        $version = $page->versions()->forceCreate([
            'data' => ['name' => 'Test', 'path' => 'test-page', 'status' => 1],
            'aux' => [],
            'published' => true,
            'editor' => 'test',
        ]);
        $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();

        // Now save with a new path (no domain in input)
        Resource::savePage(
            $page->id,
            ['path' => 'new-test-page'],
            $this->user,
        );

        // Verify the version data now includes domain
        $page->refresh();
        $latest = Version::find( $page->latest_id );
        $this->assertArrayHasKey( 'domain', (array) $latest->data );

        // Try to access via new path
        $response = $this->actingAs( $this->user )->get( '/new-test-page' );
        $response->assertStatus( 200 );
    }
}
