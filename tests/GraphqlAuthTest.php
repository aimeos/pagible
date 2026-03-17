<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlAuthTest extends TestAbstract
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


	protected function defineEnvironment( $app )
	{
        parent::defineEnvironment( $app );

		$app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
		$app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
		$app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
    }


	protected function getPackageProviders( $app )
	{
		return array_merge( parent::getPackageProviders( $app ), [
			'Nuwave\Lighthouse\LighthouseServiceProvider'
		] );
	}


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();

        $this->user = \App\Models\User::create([
            'name' => 'Test',
            'email' => 'editor@testbench',
            'password' => \Illuminate\Support\Facades\Hash::make('secret'),
            'cmseditor' => 1
        ]);
    }


    public function testLogin()
    {
        $user = \App\Models\User::where('email', 'editor@testbench')->firstOrFail();

        $expected = collect($user->getAttributes())->only(['id', 'email', 'name'])->all();

        $this->expectsDatabaseQueryCount( 1 );

        $response = $this->graphQL( "
            mutation {
                cmsLogin(email: \"editor@testbench\", password: \"secret\") {
                    id
                    email
                    name
                }
            }
        " )->assertJson( [
            'data' => [
                'cmsLogin' => $expected,
            ]
        ] );
    }


    public function testLogout()
    {
        $user = \App\Models\User::where('email', 'editor@testbench')->firstOrFail();

        $expected = collect($user->getAttributes())->only(['id', 'email', 'name'])->all();

        $this->expectsDatabaseQueryCount( 0 );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                cmsLogout {
                    id
                    email
                    name
                }
            }
        " )->assertJson( [
            'data' => [
                'cmsLogout' => $expected,
            ]
        ] );
    }


    public function testMe()
    {
        $user = \App\Models\User::where('email', 'editor@testbench')->firstOrFail();

        $expected = collect($user->getAttributes())->only(['id', 'email', 'name'])->all();

        $this->expectsDatabaseQueryCount( 0 );

        $response = $this->actingAs( $this->user )->graphQL( "{
            me {
                id
                email
                name
            }
        }" )->assertJson( [
            'data' => [
                'me' => $expected,
            ]
        ] );
    }


    public function testMeCmsdata()
    {
        $cmsdata = ['page' => ['filter' => ['view' => 'list']]];

        $this->user->update( ['cmsdata' => json_encode( $cmsdata )] );

        $response = $this->actingAs( $this->user )->graphQL( "{
            me {
                cmsdata
            }
        }" );

        $this->assertEquals( $cmsdata, json_decode( $response->json( 'data.me.cmsdata' ), true ) );
    }


    public function testMeCmsdataNull()
    {
        $this->actingAs( $this->user )->graphQL( "{
            me {
                cmsdata
            }
        }" )->assertJson( [
            'data' => [
                'me' => [
                    'cmsdata' => null,
                ],
            ]
        ] );
    }


    public function testUser()
    {
        $cmsdata = ['page' => ['filter' => ['view' => 'list'], 'sort' => ['column' => 'ID', 'order' => 'DESC']]];

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation ($cmsdata: JSON!) {
                cmsUser(cmsdata: $cmsdata) {
                    cmsdata
                }
            }
        ', ['cmsdata' => json_encode( $cmsdata )] );

        $this->assertEquals( $cmsdata, json_decode( $response->json( 'data.cmsUser.cmsdata' ), true ) );

        $this->assertDatabaseHas( 'users', [
            'id' => $this->user->id,
            'cmsdata' => json_encode( $cmsdata ),
        ] );
    }


    public function testUserOverwrite()
    {
        $first = ['page' => ['filter' => ['view' => 'list']]];
        $second = ['file' => ['sort' => ['column' => 'NAME', 'order' => 'ASC']]];

        $this->actingAs( $this->user )->graphQL( '
            mutation ($cmsdata: JSON!) {
                cmsUser(cmsdata: $cmsdata) {
                    cmsdata
                }
            }
        ', ['cmsdata' => json_encode( $first )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation ($cmsdata: JSON!) {
                cmsUser(cmsdata: $cmsdata) {
                    cmsdata
                }
            }
        ', ['cmsdata' => json_encode( $second )] );

        $this->assertDatabaseHas( 'users', [
            'id' => $this->user->id,
            'cmsdata' => json_encode( $second ),
        ] );
    }


    public function testUserGuest()
    {
        $this->graphQL( '
            mutation ($cmsdata: JSON!) {
                cmsUser(cmsdata: $cmsdata) {
                    cmsdata
                }
            }
        ', ['cmsdata' => json_encode( ['page' => []] )] )->assertGraphQLErrorMessage( 'Unauthenticated.' );
    }


    public function testUserTooLarge()
    {
        $cmsdata = ['data' => str_repeat( 'x', 65536 )];

        $this->actingAs( $this->user )->graphQL( '
            mutation ($cmsdata: JSON!) {
                cmsUser(cmsdata: $cmsdata) {
                    cmsdata
                }
            }
        ', ['cmsdata' => json_encode( $cmsdata )] )->assertGraphQLErrorMessage( 'User data too large' );
    }
}
