<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Events\QueryFilter;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Permission;
use Database\Seeders\CmsSeeder;
use Illuminate\Support\Facades\Event;


class AccessTest extends TestAbstract
{
    public function testRegisterAddsAction()
    {
        $before = Permission::all();
        Permission::register( 'test:custom' );
        $after = Permission::all();

        $this->assertNotContains( 'test:custom', $before );
        $this->assertContains( 'test:custom', $after );
    }


    public function testRegisterSkipsDuplicate()
    {
        $before = count( Permission::all() );
        Permission::register( 'page:view' );
        $after = count( Permission::all() );

        $this->assertEquals( $before, $after );
    }


    public function testRegisterMultiple()
    {
        Permission::register( ['test:one', 'test:two'] );
        $all = Permission::all();

        $this->assertContains( 'test:one', $all );
        $this->assertContains( 'test:two', $all );
    }


    public function testPageRolesAttribute()
    {
        $page = new Page();

        $this->assertNull( $page->roles );
    }


    public function testPageRolesCast()
    {
        $this->seed( CmsSeeder::class );
        $page = Page::where( 'tag', 'root' )->firstOrFail();

        $page->roles = ['admin', 'premium'];
        $page->save();

        $fresh = Page::find( $page->id );
        $this->assertEquals( ['admin', 'premium'], $fresh->roles );
    }


    public function testPageRolesFillable()
    {
        $page = new Page();
        $page->fill( ['roles' => ['editor']] );

        $this->assertEquals( ['editor'], $page->roles );
    }


    public function testPageRolesNullFillable()
    {
        $page = new Page();
        $page->fill( ['roles' => null] );

        $this->assertNull( $page->roles );
    }


    public function testPublishSyncsRoles()
    {
        $this->seed( CmsSeeder::class );
        $page = Page::where( 'tag', 'root' )->firstOrFail();

        $version = $page->versions()->first();
        $data = (array) $version->data;
        $data['roles'] = ['admin', 'premium'];
        $version->data = (object) $data;
        $version->save();

        $page->publish( $version );

        $fresh = Page::find( $page->id );
        $this->assertEquals( ['admin', 'premium'], $fresh->roles );
    }


    public function testPublishSyncsRolesNull()
    {
        $this->seed( CmsSeeder::class );
        $page = Page::where( 'tag', 'root' )->firstOrFail();

        $page->roles = ['old'];
        $page->save();

        $version = $page->versions()->first();
        $data = (array) $version->data;
        $data['roles'] = null;
        $version->data = (object) $data;
        $version->save();

        $page->publish( $version );

        $fresh = Page::find( $page->id );
        $this->assertNull( $fresh->roles );
    }


    public function testWithoutGlobalScopeNoOp()
    {
        $this->seed( CmsSeeder::class );

        $withScope = Page::count();
        $withoutScope = Page::withoutGlobalScope( 'access' )->count();

        $this->assertEquals( $withScope, $withoutScope );
    }


    public function testQueryFilterEventFired()
    {
        Event::fake( [QueryFilter::class] );

        $query = new \Aimeos\Cms\GraphQL\Query();
        $query->pages( null, ['filter' => [], 'first' => 10, 'page' => 1] );

        Event::assertDispatched( QueryFilter::class );
    }


    public function testQueryFilterEventElements()
    {
        Event::fake( [QueryFilter::class] );

        $query = new \Aimeos\Cms\GraphQL\Query();
        $query->elements( null, ['filter' => [], 'first' => 10, 'page' => 1] );

        Event::assertDispatched( QueryFilter::class );
    }


    public function testQueryFilterEventFiles()
    {
        Event::fake( [QueryFilter::class] );

        $query = new \Aimeos\Cms\GraphQL\Query();
        $query->files( null, ['filter' => [], 'first' => 10, 'page' => 1] );

        Event::assertDispatched( QueryFilter::class );
    }
}
