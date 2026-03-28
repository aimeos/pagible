<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Permission;


class PermissionTest extends CoreTestAbstract
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


    public function testCanWithRole()
    {
        $user = new \App\Models\User( ['cmsperms' => ['viewer']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
        $this->assertTrue( Permission::can( 'file:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( '*', $user ) );
    }


    public function testCanWithRoleWildcard()
    {
        $user = new \App\Models\User( ['cmsperms' => ['publisher']] );

        // publisher role has page:*, element:*, file:*
        $this->assertTrue( Permission::can( 'element:view', $user ) );
        $this->assertTrue( Permission::can( 'element:save', $user ) );
        $this->assertTrue( Permission::can( 'element:publish', $user ) );
        $this->assertTrue( Permission::can( 'file:view', $user ) );
        $this->assertTrue( Permission::can( 'file:describe', $user ) );
    }


    public function testCanWithRoleAndOverride()
    {
        $user = new \App\Models\User( ['cmsperms' => ['viewer', 'image:imagine']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'image:imagine', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testCanWithMultipleRoles()
    {
        $user = new \App\Models\User( ['cmsperms' => ['viewer', 'publisher']] );

        // viewer permissions
        $this->assertTrue( Permission::can( 'page:view', $user ) );
        // publisher permissions
        $this->assertTrue( Permission::can( 'page:publish', $user ) );
        $this->assertTrue( Permission::can( 'config:page', $user ) );
    }


    public function testCanWithAdminRole()
    {
        $user = new \App\Models\User( ['cmsperms' => ['admin']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'image:imagine', $user ) );
        $this->assertTrue( Permission::can( 'text:write', $user ) );
    }


    public function testAddRole()
    {
        $user = new \App\Models\User();

        Permission::add( 'editor', $user );

        $this->assertContains( 'editor', $user->cmsperms );
        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
    }


    public function testAddInvalidRole()
    {
        $user = new \App\Models\User();

        Permission::add( 'nonexistent', $user );

        $this->assertNotContains( 'nonexistent', $user->cmsperms );
    }


    public function testRemoveRole()
    {
        $user = new \App\Models\User( ['cmsperms' => ['editor', 'page:view']] );

        Permission::remove( 'editor', $user );

        $this->assertNotContains( 'editor', $user->cmsperms );
        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'element:view', $user ) );
    }


    public function testGetWithRole()
    {
        $user = new \App\Models\User( ['cmsperms' => ['viewer']] );

        $perms = Permission::get( $user );

        $this->assertTrue( $perms['page:view'] );
        $this->assertTrue( $perms['element:view'] );
        $this->assertTrue( $perms['file:view'] );
        $this->assertFalse( $perms['page:save'] );
    }


    public function testRoles()
    {
        $roles = Permission::roles();

        $this->assertContains( 'viewer', $roles );
        $this->assertContains( 'editor', $roles );
        $this->assertContains( 'publisher', $roles );
        $this->assertContains( 'admin', $roles );
    }


    public function testRole()
    {
        $perms = Permission::role( 'viewer' );

        $this->assertContains( 'page:view', $perms );
        $this->assertContains( 'element:view', $perms );
        $this->assertContains( 'file:view', $perms );
        $this->assertNotContains( 'page:save', $perms );
    }


    public function testRoleWithWildcard()
    {
        $perms = Permission::role( 'admin' );

        $this->assertContains( 'page:view', $perms );
        $this->assertContains( 'image:imagine', $perms );
        $this->assertCount( count( Permission::all() ), array_unique( $perms ) );
    }


    public function testRoleUnknown()
    {
        $perms = Permission::role( 'nonexistent' );

        $this->assertEmpty( $perms );
    }


    public function testCanWithDeny()
    {
        $user = new \App\Models\User( ['cmsperms' => ['editor', '!page:save']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
    }


    public function testCanWithDenyWildcard()
    {
        $user = new \App\Models\User( ['cmsperms' => ['publisher', '!image:*']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'image:imagine', $user ) );
        $this->assertFalse( Permission::can( 'image:upscale', $user ) );
    }


    public function testGetWithDeny()
    {
        $user = new \App\Models\User( ['cmsperms' => ['admin', '!page:purge', '!page:drop']] );

        $perms = Permission::get( $user );

        $this->assertTrue( $perms['page:view'] );
        $this->assertTrue( $perms['page:save'] );
        $this->assertFalse( $perms['page:purge'] );
        $this->assertFalse( $perms['page:drop'] );
    }


    public function testCanWithDenySuffixWildcard()
    {
        $user = new \App\Models\User( ['cmsperms' => ['publisher', '!*:publish'] ] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( 'page:publish', $user ) );
        $this->assertFalse( Permission::can( 'element:publish', $user ) );
        $this->assertFalse( Permission::can( 'file:publish', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
    }


    public function testCanWithRoleReferencingRole()
    {
        // editor = ['publisher', '!*:publish', '!*:purge'] in test config
        $user = new \App\Models\User( ['cmsperms' => ['editor']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( 'page:publish', $user ) );
        $this->assertFalse( Permission::can( 'page:purge', $user ) );
        $this->assertFalse( Permission::can( 'element:publish', $user ) );
        $this->assertFalse( Permission::can( 'element:purge', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
    }
}
