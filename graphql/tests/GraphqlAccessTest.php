<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlAccessTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use RefreshesSchemaCache;

    /** @var array<int, string> */
    private array $values = ['beta', 'alpha'];

    /** @var array<string, array<int, string>> */
    private array $assignments = [];


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        Access::using(
            list: fn() => $this->values,
            add: function( string $value ) {
                $this->values[] = $value;
            },
            delete: function( array $values ) {
                $this->values = array_values( array_diff( $this->values, $values ) );
            },
            userAccess: function( Authenticatable $user, ?array $values ) {
                $email = (string) data_get( $user, 'email' );

                if( $values !== null ) {
                    $this->assignments[$email] = $values;
                }

                return $this->assignments[$email] ?? [];
            },
        );

        $this->user = \App\Models\User::create( [
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => Permission::all(),
        ] );
    }


    public function testReturnsSortedAccessValues(): void
    {
        $this->user->cmsperms = ['access:view'];

        $this->actingAs( $this->user )->graphQL( '{ access }' )
            ->assertExactJson( ['data' => ['access' => ['alpha', 'beta']]] );
    }


    public function testReturnsCompleteAccessCatalogWithoutSearchArguments(): void
    {
        $this->values = array_map( fn( int $idx ) => sprintf( 'access-%03d', $idx ), range( 1, 60 ) );

        $response = $this->actingAs( $this->user )->graphQL( '{ access }' );

        $response->assertGraphQLErrorFree();
        $this->assertSame( $this->values, $response->json( 'data.access' ) );
    }


    public function testSearchesAccessValuesInBoundedCatalog(): void
    {
        $this->actingAs( $this->user )->graphQL( '
            query($term: String!, $first: Int!) {
                access(term: $term, first: $first)
            }
        ', ['term' => 'be', 'first' => 1] )
            ->assertExactJson( ['data' => ['access' => ['beta']]] );
    }


    public function testSearchesAccessValuesWithNormalizedEmptyTerm(): void
    {
        $this->actingAs( $this->user )->graphQL( '
            query($term: String) {
                access(term: $term)
            }
        ', ['term' => ''] )
            ->assertExactJson( ['data' => ['access' => ['alpha', 'beta']]] );
    }


    public function testReturnsAccessValuesForPageAccessEditors(): void
    {
        $this->user->cmsperms = ['page:access'];

        $this->actingAs( $this->user )->graphQL( '{ access }' )
            ->assertExactJson( ['data' => ['access' => ['alpha', 'beta']]] );
    }


    public function testAddsAccessValue(): void
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation($value: String!) {
                addAccess(value: $value)
            }
        ', ['value' => ' member '] )->assertExactJson( [
            'data' => ['addAccess' => ['alpha', 'beta', 'member']],
        ] );
    }


    public function testDeletesAccessValues(): void
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation($values: [String!]!) {
                deleteAccess(values: $values)
            }
        ', ['values' => ['beta']] )->assertExactJson( [
            'data' => ['deleteAccess' => ['alpha']],
        ] );
    }


    public function testFindsCombinedUserAssignmentsByExactEmail(): void
    {
        $member = $this->frontend( 'member@example.com', ['viewer', '!page:save'] );
        $this->assignments['member@example.com'] = ['beta'];
        $this->user->cmsperms = ['user:access', 'user:permission'];

        $this->expectsDatabaseQueryCount( 1 );

        $this->actingAs( $this->user )->graphQL( '
            query($email: String!) {
                cmsUser(email: $email) { id email access permissions }
            }
        ', ['email' => ' MEMBER@example.com '] )->assertExactJson( [
            'data' => ['cmsUser' => [
                'id' => (string) $member->getKey(),
                'email' => 'member@example.com',
                'access' => ['beta'],
                'permissions' => ['viewer', '!page:save'],
            ]],
        ] );
    }


    public function testUserAssignmentFieldsHaveIndependentPermissions(): void
    {
        $this->frontend( 'member@example.com', ['viewer'] );
        $this->assignments['member@example.com'] = ['beta'];
        $this->user->cmsperms = ['user:access'];

        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "member@example.com") { email access } }
        ' )->assertExactJson( ['data' => ['cmsUser' => [
            'email' => 'member@example.com',
            'access' => ['beta'],
        ]]] );

        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "member@example.com") { permissions } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        \Aimeos\Cms\Permission::set( $this->user, ['user:permission'] );
        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "member@example.com") { email permissions } }
        ' )->assertExactJson( ['data' => ['cmsUser' => [
            'email' => 'member@example.com',
            'permissions' => ['viewer'],
        ]]] );

        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "member@example.com") { access } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testReturnsNullForMissingOrForeignTenantUser(): void
    {
        $foreign = $this->frontend( 'foreign@example.com' );
        $this->user->cmsperms = ['user:access', 'user:permission'];
        Tenancy::$access = fn( ?Authenticatable $user, string $tenant ) =>
            data_get( $user, 'email' ) !== 'foreign@example.com';

        try
        {
            $this->actingAs( $this->user )->graphQL( '
                query($email: String!) {
                    cmsUser(email: $email) { email access permissions }
                }
            ', ['email' => 'missing@example.com'] )
                ->assertExactJson( ['data' => ['cmsUser' => null]] );

            $this->actingAs( $this->user )->graphQL( '
                query($email: String!) {
                    cmsUser(email: $email) { email access permissions }
                }
            ', ['email' => 'foreign@example.com'] )
                ->assertExactJson( ['data' => ['cmsUser' => null]] );

            $this->actingAs( $this->user )->graphQL( '
                mutation($id: ID!) {
                    setUserAccess(id: $id, access: ["alpha"])
                }
            ', ['id' => (string) $foreign->getKey()] )
                ->assertGraphQLErrorMessage( 'User not found' );

            $this->actingAs( $this->user )->graphQL( '
                mutation($id: ID!) {
                    setUserPermissions(id: $id, permissions: ["viewer"])
                }
            ', ['id' => (string) $foreign->getKey()] )
                ->assertGraphQLErrorMessage( 'User not found' );

            $this->assertArrayNotHasKey( 'foreign@example.com', $this->assignments );
        }
        finally {
            Tenancy::$access = null;
        }
    }


    public function testReturnsPermissionCatalog(): void
    {
        $this->user->cmsperms = ['user:permission'];

        $response = $this->actingAs( $this->user )->graphQL( '
            query { permissions { roles permissions } }
        ' )->assertGraphQLErrorFree();

        $this->assertContains( 'viewer', $response->json( 'data.permissions.roles' ) );
        $this->assertContains( 'page:view', $response->json( 'data.permissions.permissions' ) );
        $this->assertContains( 'user:permission', $response->json( 'data.permissions.permissions' ) );
    }


    public function testSetsUserAccessById(): void
    {
        $member = $this->frontend();
        $this->user->cmsperms = ['user:access'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $access: [String!]!) {
                setUserAccess(id: $id, access: $access)
            }
        ', ['id' => (string) $member->getKey(), 'access' => [' beta ', 'alpha']] )
            ->assertExactJson( ['data' => ['setUserAccess' => ['alpha', 'beta']]] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $access: [String!]!) {
                setUserAccess(id: $id, access: $access)
            }
        ', ['id' => (string) $member->getKey(), 'access' => ['alpha']] )
            ->assertExactJson( ['data' => ['setUserAccess' => ['alpha']]] );
    }


    public function testUserAccessWriteKeepsStableTargetAfterEmailChanges(): void
    {
        $member = $this->frontend();
        $member->forceFill( ['email' => 'renamed@example.com'] )->save();
        $this->frontend( 'member@example.com' );
        $this->user->cmsperms = ['user:access'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) {
                setUserAccess(id: $id, access: ["alpha"])
            }
        ', ['id' => (string) $member->getKey()] )
            ->assertExactJson( ['data' => ['setUserAccess' => ['alpha']]] );

        $this->assertSame( ['alpha'], $this->assignments['renamed@example.com'] );
        $this->assertArrayNotHasKey( 'member@example.com', $this->assignments );
    }


    public function testSetsUserPermissionsById(): void
    {
        $member = $this->frontend( 'member@example.com', ['viewer'] );
        $this->user->cmsperms = ['user:permission'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $permissions: [String!]!) {
                setUserPermissions(id: $id, permissions: $permissions)
            }
        ', [
            'id' => (string) $member->getKey(),
            'permissions' => [' page:view ', 'editor', '!page:save'],
        ] )->assertExactJson( ['data' => ['setUserPermissions' => [
            '!page:save', 'editor', 'page:view',
        ]]] );

        $this->assertSame(
            ['!page:save', 'editor', 'page:view'],
            \App\Models\User::where( 'email', 'member@example.com' )->firstOrFail()->cmsperms,
        );
    }


    public function testRepeatedPermissionUpdatesReturnTheLatestAssignments(): void
    {
        $member = $this->frontend( 'member@example.com' );
        $this->user->cmsperms = ['user:permission'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) {
                first: setUserPermissions(id: $id, permissions: ["viewer"])
                second: setUserPermissions(id: $id, permissions: ["page:view"])
            }
        ', ['id' => (string) $member->getKey()] )->assertExactJson( ['data' => [
            'first' => ['viewer'],
            'second' => ['page:view'],
        ]] );

        $this->assertSame(
            ['page:view'],
            \App\Models\User::where( 'email', 'member@example.com' )->firstOrFail()->cmsperms,
        );
    }


    public function testPermissionManagerCanDelegateAdminRole(): void
    {
        $member = $this->frontend();
        $this->user->cmsperms = ['user:permission'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) {
                setUserPermissions(id: $id, permissions: ["admin"])
            }
        ', ['id' => (string) $member->getKey()] )
            ->assertExactJson( ['data' => ['setUserPermissions' => ['admin']]] );

        $this->assertSame( ['admin'], data_get( $member->fresh(), 'cmsperms' ) );
    }


    public function testBatchedSelfRevocationIsFullyRejected(): void
    {
        // A batched request that first revokes own permissions and then relies on
        // them being gone must fail entirely: self-changes are always rejected.
        $this->user->forceFill( ['cmsperms' => ['user:permission']] )->save();

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) {
                revoke: setUserPermissions(id: $id, permissions: [])
                escalate: setUserPermissions(id: $id, permissions: ["viewer"])
            }
        ', ['id' => (string) $this->user->getKey()] );

        $response->assertGraphQLErrorMessage( 'You cannot change your own permissions' );
        $this->assertSame( ['user:permission'], $this->user->fresh()->cmsperms );
    }


    public function testSelfAssignmentRejectsEscalation(): void
    {
        $this->user->forceFill( ['cmsperms' => ['user:permission', 'page:view']] )->save();

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $permissions: [String!]!) {
                setUserPermissions(id: $id, permissions: $permissions)
            }
        ', [
            'id' => (string) $this->user->getKey(),
            'permissions' => ['admin'],
        ] )->assertGraphQLErrorMessage( 'You cannot change your own permissions' );

        $this->assertEqualsCanonicalizing( ['page:view', 'user:permission'], \App\Models\User::findOrFail( $this->user->getKey() )->cmsperms );
    }


    public function testSelfAssignmentRejectsShedding(): void
    {
        // Even shedding own permissions is rejected: a user removing their own
        // user:permission could lock the last privileged account out.
        $this->user->forceFill( ['cmsperms' => ['user:permission', 'page:view']] )->save();

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $permissions: [String!]!) {
                setUserPermissions(id: $id, permissions: $permissions)
            }
        ', [
            'id' => (string) $this->user->getKey(),
            'permissions' => ['page:view'],
        ] )->assertGraphQLErrorMessage( 'You cannot change your own permissions' );

        $this->assertEqualsCanonicalizing( ['page:view', 'user:permission'], \App\Models\User::findOrFail( $this->user->getKey() )->cmsperms );
    }


    public function testUserPermissionCanAssignUnheldPermissionsToOtherUsers(): void
    {
        $member = $this->frontend( 'member@example.com', ['page:view'] );
        $this->user->forceFill( ['cmsperms' => ['user:permission', 'page:view']] )->save();

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!, $permissions: [String!]!) {
                setUserPermissions(id: $id, permissions: $permissions)
            }
        ', [
            'id' => (string) $member->getKey(),
            'permissions' => ['admin'],
        ] )->assertExactJson( ['data' => ['setUserPermissions' => ['admin']]] );

        $this->assertSame( ['admin'], $member->fresh()->cmsperms );
    }


    public function testCreatesUserWithRandomPasswordAndNoAssignments(): void
    {
        $this->user->cmsperms = ['user:create'];

        $this->assertTrue( Permission::has( 'user:create' ) );

        $this->actingAs( $this->user )->graphQL( '
            mutation($email: String!) { createUser(email: $email) { id email } }
        ', ['email' => ' NEW@example.com '] )
            ->assertJsonPath( 'data.createUser.email', 'new@example.com' )
            ->assertJsonStructure( ['data' => ['createUser' => ['id', 'email']]] );

        $user = \App\Models\User::where( 'email', 'new@example.com' )->firstOrFail();

        $this->assertSame( 'new@example.com', $user->name );
        $this->assertSame( [], $user->cmsperms );
        $this->assertFalse( Hash::needsRehash( $user->password ) );
        $this->assertFalse( Hash::check( 'secret', $user->password ) );
        $this->assertTrue( Tenancy::allows( $user, Tenancy::value() ) );
        $this->assertArrayNotHasKey( 'new@example.com', $this->assignments );
    }


    public function testReturnsCreatedUserAssignmentsWhenAllowed(): void
    {
        $this->user->cmsperms = ['user:create', 'user:access', 'user:permission'];

        $this->actingAs( $this->user )->graphQL( '
            mutation {
                createUser(email: "assigned@example.com") { id email access permissions }
            }
        ' )->assertJsonPath( 'data.createUser.email', 'assigned@example.com' )
            ->assertJsonPath( 'data.createUser.access', [] )
            ->assertJsonPath( 'data.createUser.permissions', [] )
            ->assertJsonStructure( ['data' => ['createUser' => ['id']]] );
    }


    public function testRejectsExistingUserCreation(): void
    {
        $this->frontend();
        $this->user->cmsperms = ['user:create'];

        $this->actingAs( $this->user )->graphQL( '
            mutation { createUser(email: "member@example.com") { email } }
        ' )->assertGraphQLErrorMessage( 'User already exists' );

        $this->assertArrayNotHasKey( 'member@example.com', $this->assignments );
    }


    public function testRejectsConcurrentUserCreation(): void
    {
        $this->user->cmsperms = ['user:create'];
        $inserted = false;

        \App\Models\User::creating( function( $user ) use ( &$inserted ) {
            if( $inserted || $user->email !== 'race@example.com' ) {
                return;
            }

            $inserted = true;
            $user->getConnection()->table( $user->getTable() )->insert( [
                'name' => 'Concurrent user',
                'email' => 'race@example.com',
                'password' => 'secret',
                'cmsperms' => '[]',
            ] );
        } );

        $this->actingAs( $this->user )->graphQL( '
            mutation { createUser(email: "race@example.com") { email } }
        ' )->assertGraphQLErrorMessage( 'User already exists' );

        $this->assertDatabaseMissing( 'users', ['email' => 'race@example.com'] );
        $this->assertArrayNotHasKey( 'race@example.com', $this->assignments );
    }


    public function testUserOperationsRequireIndependentPermissions(): void
    {
        $member = $this->frontend();
        $this->user->cmsperms = ['access:view'];

        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "member@example.com") { email } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) { setUserAccess(id: $id, access: ["alpha"]) }
        ', ['id' => (string) $member->getKey()] )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $this->actingAs( $this->user )->graphQL( '
            query { permissions { roles } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) { setUserPermissions(id: $id, permissions: ["viewer"]) }
        ', ['id' => (string) $member->getKey()] )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $this->actingAs( $this->user )->graphQL( '
            mutation { createUser(email: "new@example.com") { email } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testCmsUserDataDoesNotExposeUnrelatedUserFields(): void
    {
        $this->user->cmsperms = ['user:access'];

        $this->actingAs( $this->user )->graphQL( '
            query { cmsUser(email: "editor@testbench") { name } }
        ' )->assertGraphQLErrorMessage( 'Cannot query field "name" on type "CmsUserData".' );
    }


    public function testRequiresCatalogOrPageAccessPermission(): void
    {
        $this->user->cmsperms = ['access:add', 'access:delete'];

        $this->actingAs( $this->user )->graphQL( '{ access }' )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testRequiresAddPermission(): void
    {
        $this->user->cmsperms = ['access:view'];

        $this->actingAs( $this->user )->graphQL( '
            mutation { addAccess(value: "member") }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testRequiresDeletePermission(): void
    {
        $this->user->cmsperms = ['access:view'];

        $this->actingAs( $this->user )->graphQL( '
            mutation { deleteAccess(values: ["alpha"]) }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testReadOnlyCatalogDoesNotExposeChangeCapabilities(): void
    {
        Access::using( fn() => $this->values );
        $this->user->cmsperms = ['access:view', 'access:add', 'access:delete'];

        $this->assertNotContains( 'access:add', Permission::all() );
        $this->assertNotContains( 'access:delete', Permission::all() );
        $this->assertNotContains( 'user:access', Permission::all() );

        $this->actingAs( $this->user )->graphQL( '
            mutation { addAccess(value: "member") }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    /**
     * Creates a frontend user fixture.
     *
     * @param array<int, string> $permissions
     */
    private function frontend( string $email = 'member@example.com', array $permissions = [] ) : \App\Models\User
    {
        return \App\Models\User::create( [
            'name' => 'Frontend user',
            'email' => $email,
            'password' => 'secret',
            'cmsperms' => $permissions,
        ] );
    }
}
