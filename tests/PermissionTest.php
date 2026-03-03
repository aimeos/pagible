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
        Permission::$callback = null;
        Permission::$addCallback = null;
        Permission::$delCallback = null;

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
        $user = new \App\Models\User( ['cmseditor' => 0] );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( '*', $user ) );
    }


    public function testCanWithPermission()
    {
        $user = new \App\Models\User( ['cmseditor' => 0b00000001] ); // page:view only

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testCanWildcard()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );
        $this->assertFalse( Permission::can( '*', $user ) );

        $user->cmseditor = 0b00000001;
        $this->assertTrue( Permission::can( '*', $user ) );
    }


    public function testCanUnknownAction()
    {
        $user = new \App\Models\User( ['cmseditor' => 0xFFFFFFFF] );

        $this->assertFalse( Permission::can( 'unknown:action', $user ) );
    }


    public function testAdd()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( 'page:view', $user );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testAddMultiple()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( ['page:view', 'page:save', 'file:add'], $user );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( 'file:add', $user ) );
        $this->assertFalse( Permission::can( 'page:drop', $user ) );
    }


    public function testAddUnknownAction()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( 'unknown:action', $user );

        $this->assertEquals( 0, $user->cmseditor );
    }


    public function testDel()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( ['page:view', 'page:save'], $user );
        Permission::del( 'page:view', $user );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
    }


    public function testDelMultiple()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( ['page:view', 'page:save', 'file:add'], $user );
        Permission::del( ['page:view', 'file:add'], $user );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( 'file:add', $user ) );
    }


    public function testGet()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

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


    public function testHighBitPermissions()
    {
        $user = new \App\Models\User( ['cmseditor' => 0] );

        Permission::add( 'image:imagine', $user );

        $this->assertTrue( Permission::can( 'image:imagine', $user ) );
        $this->assertFalse( Permission::can( 'page:view', $user ) );
    }


    public function testCallback()
    {
        Permission::$callback = fn( $action, $user ) => $action === 'page:view';

        $user = new \App\Models\User( ['cmseditor' => 0] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testAddCallback()
    {
        $called = false;

        Permission::$addCallback = function( $action, $user ) use ( &$called ) {
            $called = true;
            return $user;
        };

        $user = new \App\Models\User( ['cmseditor' => 0] );
        Permission::add( 'page:view', $user );

        $this->assertTrue( $called );
        $this->assertFalse( Permission::can( 'page:view', $user ) ); // custom callback did not set bit
    }


    public function testDelCallback()
    {
        $called = false;

        Permission::$delCallback = function( $action, $user ) use ( &$called ) {
            $called = true;
            return $user;
        };

        $user = new \App\Models\User( ['cmseditor' => 0b00000001] ); // page:view set
        Permission::del( 'page:view', $user );

        $this->assertTrue( $called );
        $this->assertTrue( Permission::can( 'page:view', $user ) ); // custom callback did not clear bit
    }
}
