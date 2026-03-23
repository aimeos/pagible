<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Permission;


class PermissionTest extends TestAbstract
{
    protected function tearDown(): void
    {
        Permission::canUsing( null );
        Permission::addUsing( null );
        Permission::removeUsing( null );

        parent::tearDown();
    }


    public function testAll()
    {
        $actions = Permission::all();

        $this->assertIsArray( $actions );
        $this->assertContains( 'page:view', $actions );
        $this->assertContains( 'file:add', $actions );
        $this->assertContains( 'image:imagine', $actions );
        $this->assertGreaterThan( 10, count( $actions ) );
    }


    public function testCanNullUser()
    {
        $this->assertFalse( Permission::can( 'page:view', null ) );
        $this->assertFalse( Permission::can( '*', null ) );
    }


    public function testCanNoPermissions()
    {
        $user = new \App\Models\User();

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( '*', $user ) );
    }


    public function testCanWithPermission()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testCanWildcard()
    {
        $user = new \App\Models\User();
        $this->assertFalse( Permission::can( '*', $user ) );

        Permission::add( 'page:view', $user );
        $this->assertTrue( Permission::can( '*', $user ) );
    }


    public function testCanUnknownAction()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view', 'page:save']] );

        $this->assertFalse( Permission::can( 'unknown:action', $user ) );
    }


    public function testAdd()
    {
        $user = new \App\Models\User();

        Permission::add( 'page:view', $user );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testAddMultiple()
    {
        $user = new \App\Models\User();

        Permission::add( ['page:view', 'page:save', 'file:add'], $user );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( 'file:add', $user ) );
        $this->assertFalse( Permission::can( 'page:drop', $user ) );
    }


    public function testAddDuplicate()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        Permission::add( 'page:view', $user );

        $this->assertEquals( ['page:view'], $user->cmsperms );
    }


    public function testDel()
    {
        $user = new \App\Models\User();

        Permission::add( ['page:view', 'page:save'], $user );
        Permission::remove( 'page:view', $user );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
    }


    public function testDelMultiple()
    {
        $user = new \App\Models\User();

        Permission::add( ['page:view', 'page:save', 'file:add'], $user );
        Permission::remove( ['page:view', 'file:add'], $user );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( 'file:add', $user ) );
    }


    public function testGet()
    {
        $user = new \App\Models\User();

        Permission::add( 'page:view', $user );

        $perms = Permission::get( $user );

        $this->assertIsArray( $perms );
        $this->assertArrayHasKey( 'page:view', $perms );
        $this->assertTrue( $perms['page:view'] );
        $this->assertFalse( $perms['page:save'] );
        $this->assertCount( count( Permission::all() ), $perms );
    }


    public function testGetNullUser()
    {
        $perms = Permission::get( null );

        $this->assertIsArray( $perms );
        $this->assertFalse( $perms['page:view'] );
    }


    public function testRegister()
    {
        Permission::register( 'custom:action' );

        $this->assertContains( 'custom:action', Permission::all() );

        $user = new \App\Models\User();
        Permission::add( 'custom:action', $user );

        $this->assertTrue( Permission::can( 'custom:action', $user ) );
    }


    public function testRegisterMultiple()
    {
        Permission::register( ['custom:one', 'custom:two'] );

        $this->assertContains( 'custom:one', Permission::all() );
        $this->assertContains( 'custom:two', Permission::all() );
    }


    public function testRegisterDuplicate()
    {
        $countBefore = count( Permission::all() );

        Permission::register( 'page:view' );

        $this->assertCount( $countBefore, Permission::all() );
    }


    public function testCanUsing()
    {
        Permission::canUsing( fn( $action, $user ) => $action === 'page:view' );

        $user = new \App\Models\User();

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testAddUsing()
    {
        $called = false;

        Permission::addUsing( function( $action, $user ) use ( &$called ) {
            $called = true;
            return $user;
        } );

        $user = new \App\Models\User();
        Permission::add( 'page:view', $user );

        $this->assertTrue( $called );
        $this->assertFalse( Permission::can( 'page:view', $user ) );
    }


    public function testDelUsing()
    {
        $called = false;

        Permission::removeUsing( function( $action, $user ) use ( &$called ) {
            $called = true;
            return $user;
        } );

        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        Permission::remove( 'page:view', $user );

        $this->assertTrue( $called );
        $this->assertTrue( Permission::can( 'page:view', $user ) );
    }
}
