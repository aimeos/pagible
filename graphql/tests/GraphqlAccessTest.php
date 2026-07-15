<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Permission;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlAccessTest extends GraphqlTestAbstract
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    /** @var array<int, string> */
    private array $values = ['beta', 'alpha'];


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
        );

        $this->user = new \App\Models\User( [
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => Permission::all(),
        ] );
    }


    public function testReturnsSortedAccessValues(): void
    {
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


    public function testRequiresViewPermission(): void
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

        $this->actingAs( $this->user )->graphQL( '
            mutation { addAccess(value: "member") }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }
}
