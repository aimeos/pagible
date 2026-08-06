<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;


class AccessTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    public function testViewCapabilityTracksCatalogConfiguration(): void
    {
        $this->assertFalse( Permission::has( 'access:view' ) );

        Access::using( fn() => [] );
        $this->assertTrue( Permission::has( 'access:view' ) );

        Access::using( null );
        $this->assertFalse( Permission::has( 'access:view' ) );
    }


    public function testRegistersConfiguredCapabilities(): void
    {
        Access::using( fn() => [] );

        $this->assertTrue( Permission::has( 'access:view' ) );
        $this->assertFalse( Permission::has( 'access:add' ) );
        $this->assertFalse( Permission::has( 'access:delete' ) );

        Access::using(
            list: fn() => [],
            add: fn( string $value ) => null,
            delete: fn( array $values ) => null,
        );

        $this->assertTrue( Permission::has( 'access:add' ) );
        $this->assertTrue( Permission::has( 'access:delete' ) );
        $this->assertFalse( Permission::has( 'user:access' ) );

        Access::using(
            list: fn() => [],
            userAccess: fn( $user, ?array $values ) => [],
        );

        $this->assertTrue( Permission::has( 'user:access' ) );

        Access::using( null );

        $this->assertFalse( Permission::has( 'access:view' ) );
        $this->assertFalse( Permission::has( 'access:add' ) );
        $this->assertFalse( Permission::has( 'access:delete' ) );
        $this->assertFalse( Permission::has( 'user:access' ) );
    }


    public function testSetsDirectUserAccess(): void
    {
        $assigned = ['beta'];
        $user = new \App\Models\User();

        Access::using(
            list: fn() => ['alpha', 'beta'],
            userAccess: function( $user, ?array $values ) use ( &$assigned ) {
                if( $values !== null ) {
                    $assigned = $values;
                }
                return [...$assigned, 'unknown'];
            },
        );

        $access = app( Access::class );

        $this->assertSame( ['beta'], $access->assigned( $user ) );
        $this->assertSame( ['alpha', 'beta'], $access->set( $user, [' beta ', 'alpha', 'alpha'] ) );
        $this->assertSame( ['alpha'], $access->set( $user, ['alpha'] ) );
        $this->assertSame( ['alpha'], $access->set( $user, ['alpha'] ) );
    }


    public function testPersistedUserAccessChangesUseLockedFreshModel(): void
    {
        $changed = null;
        $user = \App\Models\User::create( [
            'name' => 'Frontend user',
            'email' => 'locked@example.com',
            'password' => 'secret',
            'cmsperms' => [],
        ] );

        Access::using(
            list: fn() => ['member'],
            userAccess: function( $callbackUser, ?array $values ) use ( &$changed ) {
                if( $values !== null ) {
                    $changed = $callbackUser;
                }

                return $values ?? [];
            },
        );

        $this->assertSame( ['member'], app( Access::class )->set( $user, ['member'] ) );
        $this->assertInstanceOf( \App\Models\User::class, $changed );
        $this->assertNotSame( $user, $changed );
        $this->assertSame( $user->getKey(), $changed->getKey() );
    }


    public function testRejectsForeignTenantUserAccessChanges(): void
    {
        $changed = false;
        $user = new \App\Models\User();
        Tenancy::$access = fn() => false;

        Access::using(
            list: fn() => ['member'],
            userAccess: function() use ( &$changed ) {
                $changed = true;
                return [];
            },
        );

        try {
            app( Access::class )->set( $user, ['member'] );
            $this->fail( 'Expected a foreign tenant user to be rejected.' );
        } catch( Exception $e ) {
            $this->assertSame(
                'Frontend access can only be changed for users in the current tenant.',
                $e->getMessage(),
            );
            $this->assertFalse( $changed );
        } finally {
            Tenancy::$access = null;
        }
    }


    public function testRechecksTenantAfterLockingUserForAccessChanges(): void
    {
        $checks = 0;
        $changed = false;
        $user = \App\Models\User::create( [
            'name' => 'Moved frontend user',
            'email' => 'moved@example.com',
            'password' => 'secret',
            'cmsperms' => [],
        ] );
        Tenancy::$access = function() use ( &$checks ) {
            return ++$checks === 1;
        };

        Access::using(
            list: fn() => ['member'],
            userAccess: function() use ( &$changed ) {
                $changed = true;
                return [];
            },
        );

        try {
            app( Access::class )->set( $user, ['member'] );
            $this->fail( 'Expected changed tenant membership to be rejected.' );
        } catch( Exception $e ) {
            $this->assertSame(
                'Frontend access can only be changed for users in the current tenant.',
                $e->getMessage(),
            );
            $this->assertSame( 2, $checks );
            $this->assertFalse( $changed );
        } finally {
            Tenancy::$access = null;
        }
    }


    public function testUserAccessChangesInvalidateAllUserInstances(): void
    {
        $assigned = ['member'];
        $grantCalls = 0;

        Access::using(
            list: fn() => ['member'],
            grants: function() use ( &$assigned, &$grantCalls ) {
                $grantCalls++;
                return $assigned;
            },
            userAccess: function( $user, ?array $values ) use ( &$assigned ) {
                if( $values !== null ) {
                    $assigned = $values;
                }
                return $assigned;
            },
        );

        $authenticated = new \App\Models\User();
        $authenticated->id = 42;
        $found = new \App\Models\User();
        $found->id = 42;
        $access = app( Access::class );

        $this->assertSame( ['member'], $access->allowed( $authenticated ) );
        $this->assertSame( [], $access->set( $found, [] ) );
        $this->assertSame( [], $access->allowed( $authenticated ) );
        $this->assertSame( 2, $grantCalls );
    }


    public function testRejectsUnknownUserAccessAssignments(): void
    {
        Access::using(
            list: fn() => ['member'],
            userAccess: fn( $user, ?array $values ) => [],
        );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown frontend access value "missing".' );

        app( Access::class )->set( new \App\Models\User(), ['missing'] );
    }


    public function testRejectsOversizedUserAccessChanges(): void
    {
        Access::using(
            list: fn() => [],
            userAccess: fn( $user, ?array $values ) => [],
        );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No more than 250 user access values may be changed at once.' );

        app( Access::class )->set( new \App\Models\User(), array_map(
            fn( int $idx ) => 'value-' . $idx,
            range( 1, 251 ),
        ) );
    }


    public function testAddsAndDeletesValues(): void
    {
        $values = ['beta'];

        Access::using(
            list: function() use ( &$values ) {
                return $values;
            },
            add: function( string $value ) use ( &$values ) {
                $values[] = $value;
            },
            delete: function( array $deleted ) use ( &$values ) {
                $values = array_values( array_diff( $values, $deleted ) );
            },
        );

        $access = app( Access::class );

        $this->assertSame( ['alpha', 'beta'], $access->add( ' alpha ' ) );
        $this->assertSame( ['beta'], $access->delete( ['alpha', 'missing', 'alpha'] ) );
        $this->assertSame( ['beta'], $access->delete( [] ) );
    }


    public function testRejectsDuplicateValues(): void
    {
        Access::using(
            list: fn() => ['member'],
            add: fn( string $value ) => null,
        );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Access value "member" already exists.' );

        app( Access::class )->add( ' member ' );
    }


    public function testRejectsInvalidValues(): void
    {
        Access::using(
            list: fn() => [],
            add: fn( string $value ) => null,
        );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Access values must be non-empty strings.' );

        app( Access::class )->add( ' ' );
    }


    public function testRejectsLongValues(): void
    {
        Access::using(
            list: fn() => [],
            add: fn( string $value ) => null,
        );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Access values may not be longer than 100 characters.' );

        app( Access::class )->add( str_repeat( 'x', 101 ) );
    }


    public function testNormalizesValues(): void
    {
        $this->assertSame( ['alpha', 'beta'], Access::normalize( [' beta ', 'alpha', 'alpha'] ) );
    }


    public function testRejectsNonStringValues(): void
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Access values must be non-empty strings.' );

        Access::normalize( [null] );
    }


    public function testRejectsUnsupportedChanges(): void
    {
        Access::using( fn() => [] );

        try {
            app( Access::class )->add( 'member' );
            $this->fail( 'Read-only catalogs must reject additions.' );
        } catch( Exception $e ) {
            $this->assertSame( 'Adding access values is not available.', $e->getMessage() );
        }

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Deleting access values is not available.' );

        app( Access::class )->delete( ['member'] );
    }


    public function testCatalogChangesAreTenantScoped(): void
    {
        $values = ['test' => ['member'], 'other' => ['guest']];

        Access::using(
            list: function() use ( &$values ) {
                return $values[Tenancy::value()] ?? [];
            },
            add: function( string $value ) use ( &$values ) {
                $values[Tenancy::value()][] = $value;
            },
        );

        $access = app( Access::class );
        $this->assertSame( ['admin', 'member'], $access->add( 'admin' ) );
        $this->assertSame( $access, app( Access::class ) );

        Tenancy::set( 'other' );
        $other = app( Access::class );

        $this->assertSame( $access, $other );
        $this->assertSame( ['guest'], $other->list() );
    }


    public function testAccessWildcardsRefreshWithCapabilities(): void
    {
        $user = new \App\Models\User( ['cmsperms' => ['access:*']] );

        $this->assertFalse( Permission::can( 'access:view', $user ) );

        Access::using( fn() => [] );
        $this->assertTrue( Permission::can( 'access:view', $user ) );

        Access::using(
            list: fn() => [],
            add: fn( string $value ) => null,
        );
        $this->assertTrue( Permission::can( 'access:add', $user ) );

        Access::using( fn() => [] );
        $this->assertFalse( Permission::can( 'access:add', $user ) );
    }


    public function testAllowedValuesAreRequestScoped(): void
    {
        Access::using( fn() => ['member'] );
        $user = new \App\Models\User();
        $user->id = 42;
        $allow = true;
        $calls = 0;

        Gate::define( 'member', function() use ( &$allow, &$calls ) {
            $calls++;
            return $allow;
        } );

        $this->assertSame( ['member'], app( Access::class )->allowed( $user ) );
        $allow = false;
        $this->assertSame( ['member'], app( Access::class )->allowed( $user ) );

        app()->forgetScopedInstances();

        $this->assertSame( [], app( Access::class )->allowed( $user ) );
        $this->assertSame( 2, $calls );
    }


    public function testEffectiveGrantResolverAvoidsGateEvaluation(): void
    {
        $catalogCalls = $gateCalls = $grantCalls = 0;

        Access::using(
            list: function() use ( &$catalogCalls ) {
                $catalogCalls++;
                return ['alpha', 'beta', 'catalog-only'];
            },
            grants: function() use ( &$grantCalls ) {
                $grantCalls++;
                return [' beta ', 'alpha', 'alpha', 'unknown'];
            },
        );
        Gate::define( 'alpha', function() use ( &$gateCalls ) {
            $gateCalls++;
            return false;
        } );
        $user = new \App\Models\User();
        $user->id = 42;
        $access = app( Access::class );

        $this->assertSame( ['alpha', 'beta'], $access->allowed( $user ) );
        $this->assertSame( ['beta'], $access->allowed( $user, ['beta', 'missing', 'beta'] ) );
        $this->assertSame( 1, $grantCalls );
        $this->assertSame( 1, $catalogCalls );
        $this->assertSame( 0, $gateCalls );
    }


    public function testMembershipAndSearchUseCatalog(): void
    {
        $catalogCalls = 0;

        Access::using(
            list: function() use ( &$catalogCalls ) {
                $catalogCalls++;
                return ['alpha', 'member'];
            },
        );
        Gate::define( 'member', fn() => true );
        $user = new \App\Models\User();
        $user->id = 42;
        $access = app( Access::class );

        $this->assertTrue( $access->has( 'member' ) );
        $this->assertFalse( $access->has( 'missing' ) );
        $this->assertSame( ['member'], $access->search( 'm', 1 ) );
        $this->assertSame( ['member'], $access->allowed( $user, ['member', 'missing'] ) );
        $this->assertSame( 1, $catalogCalls );
    }


    public function testCatalogReturnsAllValues(): void
    {
        $yielded = 0;

        Access::using( function() use ( &$yielded ) {
            foreach( range( 1, 1000 ) as $idx )
            {
                $yielded++;
                yield 'access-' . $idx;
            }
        } );

        $values = app( Access::class )->list();

        $this->assertCount( 1000, $values );
        $this->assertContains( 'access-1000', $values );
        $this->assertSame( 1000, $yielded );
    }


    public function testAdditionExtendsLargeCatalog(): void
    {
        $values = array_map( fn( int $idx ) => 'access-' . $idx, range( 1, 250 ) );

        Access::using(
            list: function() use ( &$values ) {
                return $values;
            },
            add: function( string $value ) use ( &$values ) {
                $values[] = $value;
            },
        );

        $result = app( Access::class )->add( 'access-251' );

        $this->assertCount( 251, $result );
        $this->assertContains( 'access-251', $result );
    }


    public function testExtendedAccessIsAddedToEffectiveGrants(): void
    {
        Access::using(
            list: fn() => ['frontend.once', 'frontend.pro', 'member'],
            grants: fn() => ['member'],
        );
        Access::extend( fn() => ['frontend.once', 'frontend.pro', 'frontend.unknown'] );
        $user = new \App\Models\User();

        $this->assertSame(
            ['frontend.once', 'frontend.pro', 'member'],
            app( Access::class )->allowed( $user ),
        );
        $this->assertSame(
            ['frontend.pro'],
            app( Access::class )->allowed( $user, ['frontend.pro', 'frontend.expired'] ),
        );
    }


    public function testExtendedAccessIsAddedToGateGrants(): void
    {
        Access::using( fn() => ['frontend.member', 'member'] );
        Access::extend( fn() => ['frontend.member'] );
        Gate::define( 'member', fn() => false );
        $user = new \App\Models\User();

        $this->assertSame( ['frontend.member'], app( Access::class )->allowed( $user ) );
    }


    public function testGrantResolverFallsBackOnceWhenPermissionsCannotBeEnumerated(): void
    {
        $catalogCalls = $gateCalls = $grantCalls = 0;

        Access::using(
            list: function() use ( &$catalogCalls ) {
                $catalogCalls++;
                return ['member'];
            },
            grants: function() use ( &$grantCalls ) {
                $grantCalls++;
                return null;
            },
        );
        Gate::define( 'member', function() use ( &$gateCalls ) {
            $gateCalls++;
            return true;
        } );
        $user = new \App\Models\User();
        $user->id = 42;
        $access = app( Access::class );

        $this->assertSame( ['member'], $access->allowed( $user ) );
        $this->assertSame( ['member'], $access->allowed( $user ) );
        $this->assertSame( 1, $grantCalls );
        $this->assertSame( 1, $catalogCalls );
        $this->assertSame( 1, $gateCalls );
    }


    public function testGrantResolverIsRefreshedForEachTenant(): void
    {
        $calls = 0;

        Access::using(
            list: fn() => [Tenancy::value() . '.member'],
            grants: function() use ( &$calls ) {
                $calls++;
                return [Tenancy::value() . '.member'];
            },
        );
        $user = new \App\Models\User();
        $user->id = 42;
        $access = app( Access::class );

        $this->assertSame( ['test.member'], $access->allowed( $user ) );
        $this->assertSame( ['test.member'], $access->allowed( $user ) );

        Tenancy::set( 'other' );

        $this->assertSame( ['other.member'], $access->allowed( $user ) );
        $this->assertSame( 2, $calls );
    }


    public function testPageAccessScopeUsesBoundedEffectiveGrants(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::forceCreate( [
            'page_id' => $page->id,
            'tenant_id' => 'test',
            'value' => 'member',
            'editor' => 'test@example.com',
        ] );
        $catalogCalls = 0;

        Access::using(
            list: function() use ( &$catalogCalls ) {
                $catalogCalls++;
                return ['member'];
            },
            grants: fn() => ['member'],
        );
        $user = new \App\Models\User();
        $user->id = 42;

        $this->assertNotNull( Page::query()->access( $user )->find( $page->id ) );
        $this->assertSame( 1, $catalogCalls );
    }


    public function testListReturnsNormalizedValues(): void
    {
        Access::using( fn() => [' beta ', 'alpha', 'alpha'] );

        $this->assertSame( ['alpha', 'beta'], app( Access::class )->list() );
    }


    public function testListMemoizesNormalizedValuesPerRequest(): void
    {
        $calls = 0;
        Access::using( function() use ( &$calls ) {
            $calls++;
            return ['member'];
        } );

        $this->assertSame( ['member'], app( Access::class )->list() );
        $this->assertSame( ['member'], app( Access::class )->list() );
        $this->assertSame( 1, $calls );

        app()->forgetScopedInstances();

        $this->assertSame( ['member'], app( Access::class )->list() );
        $this->assertSame( 2, $calls );
    }


    public function testUsingInvalidatesListedValues(): void
    {
        Access::using( fn() => ['alpha'] );
        $this->assertSame( ['alpha'], app( Access::class )->list() );

        Access::using( fn() => ['beta'] );
        $this->assertSame( ['beta'], app( Access::class )->list() );
    }


    public function testGateAbilitiesAreNotCatalogValues(): void
    {
        Access::using( null );
        Gate::define( 'page:view', fn() => true );
        $user = new \App\Models\User();
        $user->id = 42;

        $this->assertSame( [], app( Access::class )->allowed( $user ) );
    }


    public function testAvailableValuesAreTenantScoped(): void
    {
        Access::using( fn() => Tenancy::value() === 'other' ? ['foreign'] : ['member'] );

        $foreignCalls = 0;
        Gate::define( 'member', fn() => true );
        Gate::define( 'foreign', function() use ( &$foreignCalls ) {
            $foreignCalls++;
            return true;
        } );

        $user = new \App\Models\User();
        $user->id = 42;

        $this->assertSame( ['member'], app( Access::class )->allowed( $user ) );
        $this->assertSame( 0, $foreignCalls );

        Tenancy::set( 'other' );

        $this->assertSame( ['foreign'], app( Access::class )->allowed( $user ) );
        $this->assertSame( 1, $foreignCalls );
    }


    public function testSpatieAdapterPreparesUsersOncePerTenantScope(): void
    {
        if( !class_exists( 'Spatie\\Permission\\PermissionRegistrar', false ) ) {
            class_alias( SpatieRegistrarFake::class, 'Spatie\\Permission\\PermissionRegistrar' );
        }

        $this->createAccessTable();

        try
        {
            $registrar = new SpatieRegistrarFake();
            app()->instance( 'Spatie\\Permission\\PermissionRegistrar', $registrar );
            config( ['permission.models.permission' => AccessWriteModel::class] );
            AccessWriteModel::query()->create( ['name' => 'member', 'guard_name' => 'web'] );

            Access::spatie();
            $access = app( Access::class );

            $this->assertContains( 'member', $access->list() );
            $this->assertSame( 'test', $registrar->tenant );

            $user = new SpatieUserFake();
            $user->setRelation( 'roles', collect( ['stale'] ) );
            $user->setRelation( 'permissions', collect( ['stale'] ) );

            Gate::define( 'member', function() use ( $user ) {
                $this->assertFalse( $user->relationLoaded( 'roles' ) );
                $this->assertFalse( $user->relationLoaded( 'permissions' ) );
                return true;
            } );

            $this->assertSame( ['member'], $access->allowed( $user, ['member'] ) );
            $this->assertSame( 1, $registrar->calls );

            $this->assertSame( [], $access->assigned( $user ) );
            $this->assertSame( ['member'], $access->set( $user, ['member'] ) );
            $this->assertSame( [], $access->set( $user, [] ) );

            $user->setRelation( 'roles', collect() );
            $user->setRelation( 'permissions', collect() );

            $this->assertSame( ['member'], $access->allowed( $user, ['member'] ) );
            $this->assertFalse( $user->relationLoaded( 'roles' ) );
            $this->assertFalse( $user->relationLoaded( 'permissions' ) );

            Tenancy::set( 'other' );

            $this->assertSame( $access, app( Access::class ) );
            $this->assertSame( ['member'], $access->allowed( $user, ['member'] ) );
            $this->assertFalse( $user->relationLoaded( 'roles' ) );
            $this->assertFalse( $user->relationLoaded( 'permissions' ) );
            $this->assertSame( 'other', $registrar->tenant );
            $this->assertSame( 2, $registrar->calls );
        }
        finally {
            $this->dropAccessTable();
        }
    }


    public function testSpatieAdapterReturnsCompleteCatalog(): void
    {
        if( !class_exists( 'Spatie\\Permission\\PermissionRegistrar', false ) ) {
            class_alias( SpatieRegistrarFake::class, 'Spatie\\Permission\\PermissionRegistrar' );
        }

        $this->createAccessTable();

        try
        {
            $rows = array_map( fn( int $idx ) => [
                'name' => 'access-' . $idx,
                'guard_name' => 'web',
            ], range( 1, 300 ) );

            DB::connection( config( 'cms.db', 'sqlite' ) )
                ->table( 'test_access_permissions' )
                ->insert( $rows );

            app()->instance( 'Spatie\\Permission\\PermissionRegistrar', new SpatieRegistrarFake() );
            config( [
                'auth.defaults.guard' => 'web',
                'permission.models.permission' => AccessWriteModel::class,
            ] );
            Access::spatie();

            $this->assertCount( 300, app( Access::class )->list() );
        }
        finally {
            $this->dropAccessTable();
        }
    }


    public function testBouncerAdapterActivatesTenantOncePerScope(): void
    {
        if( !class_exists( 'Silber\\Bouncer\\Bouncer', false ) ) {
            class_alias( BouncerFake::class, 'Silber\\Bouncer\\Bouncer' );
        }

        $this->createAccessTable();

        try
        {
            BouncerFake::$model = AccessWriteModel::class;
            $bouncer = new BouncerFake();
            app()->instance( 'Silber\\Bouncer\\Bouncer', $bouncer );
            AccessWriteModel::query()->create( ['name' => 'member'] );

            Access::bouncer();
            $access = app( Access::class );

            $this->assertContains( 'member', $access->list() );
            $this->assertSame( 'test', $bouncer->scope->tenant );

            Gate::define( 'member', fn() => true );
            $user = new BouncerUserFake();
            $this->assertSame( ['member'], $access->allowed( $user, ['member'] ) );
            $this->assertSame( [], $access->assigned( $user ) );
            $this->assertSame( ['member'], $access->set( $user, ['member'] ) );
            $this->assertSame( [], $access->set( $user, [] ) );
            $this->assertSame( 1, $bouncer->scope->calls );
            $this->assertSame( 2, $bouncer->userRefreshes );
        }
        finally
        {
            BouncerFake::$model = AccessPackageModel::class;
            $this->dropAccessTable();
        }
    }


    public function testLaratrustAdapterRegistersTenantAwareGates(): void
    {
        if( !class_exists( 'Laratrust\\Helper', false ) ) {
            class_alias( LaratrustHelperFake::class, 'Laratrust\\Helper' );
        }

        config( [
            'laratrust.models.permission' => AccessPackageModel::class,
            'laratrust.teams.enabled' => true,
        ] );
        $value = Page::query()->value( 'name' );
        $user = new LaratrustUserFake();

        Access::laratrust();

        $this->assertIsString( $value );
        $this->assertSame( [$value], app( Access::class )->allowed( $user, [$value] ) );
        $this->assertContains( $value, app( Access::class )->list() );
        $this->assertSame( [[$value, 'test']], $user->checks );
        $this->assertSame( [], app( Access::class )->assigned( $user ) );
        $this->assertSame( [$value], app( Access::class )->set( $user, [$value] ) );
        $this->assertSame( [], app( Access::class )->set( $user, [] ) );
        $this->assertSame( ['test', 'test'], $user->teams );
        $this->assertSame( 2, $user->flushes );
    }


    public function testPackageAdaptersAddAndDeleteCatalogValues(): void
    {
        $this->createAccessTable();

        try
        {
            if( !class_exists( 'Silber\\Bouncer\\Bouncer', false ) ) {
                class_alias( BouncerFake::class, 'Silber\\Bouncer\\Bouncer' );
            }

            BouncerFake::$model = AccessWriteModel::class;
            $bouncer = new BouncerFake();
            app()->instance( 'Silber\\Bouncer\\Bouncer', $bouncer );
            Access::bouncer();
            $access = app( Access::class );

            AccessWriteModel::query()->create( ['name' => 'model.edit', 'entity_type' => Page::class] );
            $this->assertSame( [], $access->delete( ['model.edit'] ) );
            $this->assertTrue( AccessWriteModel::query()->where( 'name', 'model.edit' )->exists() );
            $this->assertSame( ['bouncer.member'], $access->add( 'bouncer.member' ) );
            $this->assertSame( [], $access->delete( ['bouncer.member'] ) );
            $this->assertSame( $access, app( Access::class ) );
            $this->assertSame( 1, $bouncer->scope->calls );
            $this->assertSame( 2, $bouncer->refreshes );

            AccessWriteModel::query()->delete();

            config( [
                'laratrust.models.permission' => AccessWriteModel::class,
                'laratrust.permissions_as_gates' => true,
            ] );
            Access::laratrust();

            $this->assertSame( ['laratrust.member'], app( Access::class )->add( 'laratrust.member' ) );
            $this->assertSame( [], app( Access::class )->delete( ['laratrust.member'] ) );

            if( !class_exists( 'Spatie\\Permission\\PermissionRegistrar', false ) ) {
                class_alias( SpatieRegistrarFake::class, 'Spatie\\Permission\\PermissionRegistrar' );
            }

            $registrar = new SpatieRegistrarFake();
            app()->instance( 'Spatie\\Permission\\PermissionRegistrar', $registrar );
            config( [
                'auth.defaults.guard' => 'web',
                'permission.models.permission' => AccessWriteModel::class,
            ] );
            AccessWriteModel::$finds = 0;
            AccessWriteModel::query()->create( ['name' => 'api.member', 'guard_name' => 'api'] );
            Access::spatie();

            $this->assertSame( [], app( Access::class )->delete( ['api.member'] ) );
            $this->assertTrue( AccessWriteModel::query()->where( 'name', 'api.member' )->exists() );
            $this->assertSame( ['spatie.member'], app( Access::class )->add( 'spatie.member' ) );
            $this->assertSame( 'web', AccessWriteModel::query()->where( 'name', 'spatie.member' )->value( 'guard_name' ) );
            $this->assertSame( 1, AccessWriteModel::$finds );
            $this->assertSame( [], app( Access::class )->delete( ['spatie.member'] ) );
            $this->assertTrue( AccessWriteModel::query()->where( 'name', 'api.member' )->exists() );
            $this->assertSame( 1, $registrar->calls );
        }
        finally
        {
            BouncerFake::$model = AccessPackageModel::class;
            $this->dropAccessTable();
        }
    }


    public function testAllowedValuesDoNotQueryResourceRules(): void
    {
        Access::using( fn() => ['member'] );
        Gate::define( 'member', fn() => true );
        $user = new \App\Models\User();
        $user->id = 42;
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame( ['member'], app( Access::class )->allowed( $user ) );
        $this->assertCount( 0, DB::getQueryLog() );
    }


    private function createAccessTable(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->create( 'test_access_permissions', function( $table ) {
            $table->id();
            $table->string( 'name', 100 );
            $table->string( 'guard_name' )->nullable();
            $table->string( 'entity_type' )->nullable();
        } );
    }


    private function dropAccessTable(): void
    {
        Schema::connection( config( 'cms.db', 'sqlite' ) )->dropIfExists( 'test_access_permissions' );
    }
}


class AccessPackageModel extends \Illuminate\Database\Eloquent\Model
{
    protected $table = 'cms_pages';
    public $timestamps = false;


    public function getConnectionName(): string
    {
        return config( 'cms.db', 'sqlite' );
    }
}


class AccessWriteModel extends \Illuminate\Database\Eloquent\Model
{
    public static int $finds = 0;
    protected $guarded = [];
    protected $table = 'test_access_permissions';
    public $timestamps = false;


    public function getConnectionName(): string
    {
        return config( 'cms.db', 'sqlite' );
    }


    public static function findOrCreate( string $name, ?string $guard = null ): self
    {
        self::$finds++;
        return self::query()->firstOrCreate( ['name' => $name, 'guard_name' => $guard] );
    }
}


class SpatieRegistrarFake
{
    public ?string $tenant = null;
    public int $calls = 0;
    public function setPermissionsTeamId( string $tenant ): void
    {
        $this->tenant = $tenant;
        $this->calls++;
    }
}


class ProviderUserFake extends \App\Models\User
{
    /** @var array<int, string> */
    public array $direct = [];
}


class SpatieUserFake extends ProviderUserFake
{
    public function getDirectPermissions(): \Illuminate\Support\Collection
    {
        return collect( $this->direct )->map( fn( string $value ) => (object) ['name' => $value] );
    }


    public function syncPermissions( array $values ): self
    {
        $this->direct = $values;
        $this->unsetRelation( 'permissions' );
        return $this;
    }
}


class BouncerFake
{
    /** @var class-string<\Illuminate\Database\Eloquent\Model> */
    public static string $model = AccessPackageModel::class;
    public BouncerScopeFake $scope;
    public int $refreshes = 0;
    public int $userRefreshes = 0;


    public function __construct()
    {
        $this->scope = new BouncerScopeFake();
    }


    public function ability(): \Illuminate\Database\Eloquent\Model
    {
        return new self::$model();
    }


    public function allow( BouncerUserFake $user ): BouncerConductorFake
    {
        return new BouncerConductorFake( $user, true );
    }


    public function disallow( BouncerUserFake $user ): BouncerConductorFake
    {
        return new BouncerConductorFake( $user, false );
    }


    public function scope(): BouncerScopeFake
    {
        return $this->scope;
    }


    public function refresh(): void
    {
        $this->refreshes++;
    }


    public function refreshFor( BouncerUserFake $user ): void
    {
        $this->userRefreshes++;
    }
}


class BouncerConductorFake
{
    public function __construct( private BouncerUserFake $user, private bool $assign )
    {
    }


    public function to( array|string $values ): void
    {
        $values = (array) $values;
        $this->user->direct = $this->assign
            ? array_values( array_unique( [...$this->user->direct, ...$values] ) )
            : array_values( array_diff( $this->user->direct, $values ) );
    }
}


class BouncerUserFake extends ProviderUserFake
{
    public function abilities(): ProviderRelationFake
    {
        return new ProviderRelationFake( $this );
    }
}


class BouncerScopeFake
{
    public ?string $tenant = null;
    public int $calls = 0;


    public function to( string $tenant ): self
    {
        $this->tenant = $tenant;
        $this->calls++;
        return $this;
    }
}


class LaratrustUserFake extends ProviderUserFake
{
    /** @var array<int, array{string, ?string}> */
    public array $checks = [];
    /** @var array<int, string|null> */
    public array $teams = [];
    public int $flushes = 0;


    public function permissions(): ProviderRelationFake
    {
        return new ProviderRelationFake( $this );
    }


    public function givePermissions( array $values, ?string $team = null ): self
    {
        $this->teams[] = $team;

        foreach( $values as $value ) {
            $this->direct = array_values( array_unique( [...$this->direct, $value] ) );
            $this->flushCache();
        }

        return $this;
    }


    public function removePermissions( array $values, ?string $team = null ): self
    {
        $this->teams[] = $team;

        foreach( $values as $value ) {
            $this->direct = array_values( array_diff( $this->direct, [$value] ) );
            $this->flushCache();
        }

        return $this;
    }


    public function flushCache(): void
    {
        $this->flushes++;
    }


    public function isAbleTo( string $value, ?string $team = null ): bool
    {
        $this->checks[] = [$value, $team];
        return true;
    }
}


class ProviderRelationFake
{
    public function __construct( private ProviderUserFake $user )
    {
    }


    public function get( array $columns = ['*'] ): \Illuminate\Support\Collection
    {
        return collect( $this->user->direct )->map( fn( string $value ) => (object) ['name' => $value] );
    }


    public function getRelated(): AccessWriteModel
    {
        return new AccessWriteModel();
    }


    public function whereNull( string $column ): self
    {
        return $this;
    }


    public function wherePivot( string $column, mixed $value ): self
    {
        return $this;
    }
}


class LaratrustHelperFake
{
    public static function getIdFor( mixed $team, string $type ): mixed
    {
        return $team;
    }
}
