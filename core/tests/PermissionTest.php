<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;


class PermissionTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Permission::canUsing( null );

        parent::tearDown();
    }


    public function testAll()
    {
        $actions = Permission::all();

        $this->assertIsArray( $actions );
        $this->assertContains( 'page:view', $actions );
        $this->assertContains( 'page:access', $actions );
        $this->assertContains( 'file:add', $actions );
        $this->assertContains( 'file:relocate', $actions );
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


    public function testCanReflectsRawAttributeChangesWithinOneRequest()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );

        // Direct attribute writes (outside of set()) must not serve stale resolutions.
        $user->cmsperms = ['page:view', 'page:save'];

        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( 'page:view', $user ) );
    }


    public function testCanReflectsRawAttributeRemovalWithinOneRequest()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view', 'page:save']] );

        $this->assertTrue( Permission::can( 'page:save', $user ) );

        $user->cmsperms = ['page:view'];

        $this->assertFalse( Permission::can( 'page:save', $user ) );
        $this->assertTrue( Permission::can( 'page:view', $user ) );
    }


    public function testCanRequiresCurrentTenant()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        $user->tenant_id = 'test';

        $this->assertTrue( Permission::can( 'page:view', $user ) );

        $user->tenant_id = 'other';

        $this->assertFalse( Permission::can( 'page:view', $user ) );
    }


    public function testCanRejectsUnresolvedConfiguredTenant(): void
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );
        $user->tenant_id = '';
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( '' ) );

        $this->assertFalse( Permission::can( 'page:view', $user ) );
    }


    public function testCanWithoutTenantConfiguration(): void
    {
        $user = new GenericUser( ['id' => 1, 'cmsperms' => ['page:view']] );
        Tenancy::$callback = null;
        Tenancy::set( '' );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
    }


    public function testCanWildcard()
    {
        $user = $this->user( 'can-wildcard@example.com' );
        $this->assertFalse( Permission::can( '*', $user ) );

        Permission::set( $user, ['page:view'] );
        $this->assertTrue( Permission::can( '*', $user ) );
    }


    public function testCanUnknownAction()
    {
        $user = new \App\Models\User( ['cmsperms' => ['page:view', 'page:save', 'unknown:action']] );

        $this->assertFalse( Permission::can( 'unknown:action', $user ) );
    }





    public function testAssignedReturnsRawValues(): void
    {
        $user = new \App\Models\User( ['cmsperms' => ['viewer', '!page:save', 'page:view']] );

        $this->assertSame( ['viewer', '!page:save', 'page:view'], Permission::assigned( $user ) );
    }




    public function testGet()
    {
        $user = $this->user( 'get@example.com' );

        Permission::set( $user, ['page:view'] );

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

        $this->assertTrue( Permission::has( 'custom:action' ) );

        $user = $this->user( 'register@example.com' );
        Permission::set( $user, ['custom:action'] );

        $this->assertTrue( Permission::can( 'custom:action', $user ) );
    }


    public function testHas(): void
    {
        $this->assertTrue( Permission::has( 'page:view' ) );
        $this->assertFalse( Permission::has( 'unknown:action' ) );
    }


    public function testUnregister(): void
    {
        Permission::register( 'custom:remove' );
        $this->assertTrue( Permission::has( 'custom:remove' ) );

        Permission::unregister( 'custom:remove' );

        $this->assertFalse( Permission::has( 'custom:remove' ) );
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
        Permission::register( 'custom:action' );
        Permission::canUsing( fn( $action, $user ) => $action === 'custom:action' );

        $user = new \App\Models\User();

        $this->assertTrue( Permission::can( 'custom:action', $user ) );
        $this->assertFalse( Permission::can( 'page:save', $user ) );
    }


    public function testCanUsingCannotBypassCurrentTenant()
    {
        Permission::canUsing( fn() => true );

        $user = new \App\Models\User();
        $user->tenant_id = 'other';

        $this->assertFalse( Permission::can( 'page:view', $user ) );
    }


    public function testCanUsingCannotAuthorizeUnknownAction()
    {
        Permission::canUsing( fn() => true );

        $user = new \App\Models\User();

        $this->assertFalse( Permission::can( 'unknown:action', $user ) );
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
        $this->assertTrue( Permission::can( 'page:access', $user ) );
        $this->assertTrue( Permission::can( 'file:view', $user ) );
        $this->assertTrue( Permission::can( 'file:describe', $user ) );
        $this->assertTrue( Permission::can( 'file:relocate', $user ) );
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
        $this->assertTrue( Permission::can( 'page:config', $user ) );
    }


    public function testCanWithAdminRole()
    {
        $user = new \App\Models\User( ['cmsperms' => ['admin']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'image:imagine', $user ) );
        $this->assertTrue( Permission::can( 'text:write', $user ) );
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


    public function testSetPersistsNormalizedAssignments(): void
    {
        $user = $this->user( 'permission-set@example.com', ['viewer'] );

        $this->assertSame(
            ['!page:save', 'editor', 'page:view'],
            Permission::set( $user, [' page:view ', 'editor', '!page:save', 'editor'] ),
        );
        $this->assertSame(
            ['!page:save', 'editor', 'page:view'],
            $user->fresh()->cmsperms,
        );
    }


    public function testSetAcceptsSupportedWildcardsAndDenies(): void
    {
        $user = $this->user( 'permission-wildcard@example.com' );

        $this->assertSame(
            ['!*:publish', '*', 'page:*'],
            Permission::set( $user, ['page:*', '!*:publish', '*'] ),
        );
    }


    public function testSetDoesNotInvalidateAssignmentsForOtherUserInstances(): void
    {
        $user = $this->user( 'permission-cache@example.com', ['page:view'] );
        $other = \App\Models\User::findOrFail( $user->getKey() );

        $this->assertTrue( Permission::can( 'page:view', $other ) );
        $this->assertSame( [], Permission::set( $user, [] ) );
        $this->assertSame( ['page:view'], Permission::assigned( $other ) );
        $this->assertTrue( Permission::can( 'page:view', $other ) );
        $this->assertSame( [], Permission::assigned( $other->fresh() ) );
        $this->assertFalse( Permission::can( 'page:view', $other->fresh() ) );
    }


    public function testSetReturnsLatestAssignmentsAfterRepeatedUpdates(): void
    {
        $user = $this->user( 'permission-repeat@example.com' );

        $this->assertSame( ['viewer'], Permission::set( $user, ['viewer'] ) );
        $this->assertSame( ['page:view'], Permission::set( $user, ['page:view'] ) );
        $this->assertSame( ['page:view'], $user->fresh()->cmsperms );
    }


    public function testSetPreservesExistingLegacyAssignments(): void
    {
        $user = $this->user( 'permission-legacy@example.com', ['legacy:permission'] );

        $this->assertSame(
            ['legacy:permission', 'page:view'],
            Permission::set( $user, ['legacy:permission', 'page:view'] ),
        );
    }


    public function testSetRejectsForeignTenantUsers(): void
    {
        $user = new \App\Models\User( ['cmsperms' => []] );
        $user->exists = true;
        $user->id = 42;
        $user->tenant_id = 'other';

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'CMS permissions can only be changed for users in the current tenant.' );

        Permission::set( $user, ['page:view'] );
    }


    public function testSetRejectsUnknownAssignments(): void
    {
        $user = $this->user( 'permission-invalid@example.com' );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Unknown CMS role or permission "missing:permission".' );

        Permission::set( $user, ['missing:permission'] );
    }


    public function testSetRejectsTooManyAssignments(): void
    {
        $permissions = array_map( fn( int $idx ) => 'custom:' . $idx, range( 1, 251 ) );
        $user = $this->user( 'permission-limit@example.com' );
        Permission::register( $permissions );

        try {
            Permission::set( $user, $permissions );
            $this->fail( 'Oversized CMS permission assignments must be rejected.' );
        } catch( \Aimeos\Cms\Exception $e ) {
            $this->assertSame(
                'No more than 250 CMS permissions may be assigned at once.',
                $e->getMessage(),
            );
        } finally {
            Permission::unregister( $permissions );
        }
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
        // editor = ['publisher', '!*:publish', '!*:purge', '!page:access'] in test config
        $user = new \App\Models\User( ['cmsperms' => ['editor']] );

        $this->assertTrue( Permission::can( 'page:view', $user ) );
        $this->assertTrue( Permission::can( 'page:save', $user ) );
        $this->assertFalse( Permission::can( 'page:access', $user ) );
        $this->assertFalse( Permission::can( 'page:publish', $user ) );
        $this->assertFalse( Permission::can( 'page:purge', $user ) );
        $this->assertFalse( Permission::can( 'element:publish', $user ) );
        $this->assertFalse( Permission::can( 'element:purge', $user ) );
        $this->assertTrue( Permission::can( 'element:view', $user ) );
    }


    /** @param array<int, string> $permissions */
    private function user( string $email, array $permissions = [] ) : \App\Models\User
    {
        return \App\Models\User::create( [
            'name' => 'Editor',
            'email' => $email,
            'password' => 'secret',
            'cmsperms' => $permissions,
        ] );
    }
}
