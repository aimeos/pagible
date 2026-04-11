<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Tenancy;
use Database\Seeders\CmsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;


class TenancyTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Tenancy::$callback = fn() => 'test';
        app()->forgetScopedInstances();

        Permission::canUsing( null );

        parent::tearDown();
    }


    public function testTenancyScopeApply()
    {
        $this->seed( CmsSeeder::class );

        $pages = Page::all();

        $this->assertGreaterThan( 0, $pages->count() );

        foreach( $pages as $page ) {
            $this->assertEquals( 'test', $page->tenant_id );
        }
    }


    public function testCrossTenantIsolation()
    {
        $this->seed( CmsSeeder::class );

        $countBefore = Page::withoutTenancy()->count();
        $this->assertGreaterThan( 0, $countBefore );

        Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        $pages = Page::all();
        $this->assertEquals( 0, $pages->count() );
    }


    public function testWithoutTenancyMacro()
    {
        $this->seed( CmsSeeder::class );

        Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        $pages = Page::withoutTenancy()->get();
        $this->assertGreaterThan( 0, $pages->count() );
    }


    public function testTenancyAutoSetsOnCreate()
    {
        $page = Page::forceCreate( [
            'name' => 'Tenant Test Page',
            'title' => 'Tenant Test',
            'path' => 'tenant-test',
            'tag' => 'page',
            'to' => '',
            'domain' => '',
            'lang' => 'en',
            'type' => '',
            'theme' => '',
            'cache' => 0,
            'status' => 1,
            'editor' => 'test',
            'meta' => [],
            'config' => [],
            'content' => [],
        ] );

        $this->assertEquals( 'test', $page->tenant_id );
    }


    public function testStatusScopeWithoutPermission()
    {
        Auth::shouldReceive( 'user' )->andReturn( null );

        $this->createPage( 'Draft', 'draft', 0 );
        $this->createPage( 'Published', 'published', 1 );
        $this->createPage( 'Review', 'review', 2 );

        $pages = Page::withGlobalScope( 'status', new Status )->get();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertNotContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
        $this->assertContains( 2, $statuses );
    }


    public function testStatusScopeWithPermission()
    {
        $user = new \App\Models\User( [
            'name' => 'Editor',
            'email' => 'editor@tenancy-test',
            'password' => 'secret',
            'cmsperms' => ['page:view'],
        ] );

        Auth::shouldReceive( 'user' )->andReturn( $user );

        $this->createPage( 'Draft', 'draft2', 0 );
        $this->createPage( 'Published', 'published2', 1 );
        $this->createPage( 'Review', 'review2', 2 );

        $pages = Page::withGlobalScope( 'status', new Status )->get();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
        $this->assertContains( 2, $statuses );
    }


    public function testStatusScopeWithoutMacro()
    {
        Auth::shouldReceive( 'user' )->andReturn( null );

        $this->createPage( 'Draft', 'draft3', 0 );
        $this->createPage( 'Published', 'published3', 1 );

        // Without the status scope, all pages are visible regardless of status
        $pages = Page::all();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
    }


    protected function createPage( string $name, string $path, int $status ): Page
    {
        return Page::forceCreate( [
            'name' => $name,
            'title' => $name,
            'path' => $path,
            'tag' => 'page',
            'to' => '',
            'domain' => '',
            'lang' => 'en',
            'type' => '',
            'theme' => '',
            'cache' => 0,
            'status' => $status,
            'editor' => 'test',
            'meta' => [],
            'config' => [],
            'content' => [],
        ] );
    }


}
