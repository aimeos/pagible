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
        $settings = ['page' => ['filter' => ['view' => 'list']]];

        $this->user->update( ['cmsdata' => json_encode( $settings )] );

        $response = $this->actingAs( $this->user )->graphQL( "{
            me {
                settings
            }
        }" );

        $this->assertEquals( $settings, json_decode( $response->json( 'data.me.settings' ), true ) );
    }


    public function testMeCmsdataNull()
    {
        $this->actingAs( $this->user )->graphQL( "{
            me {
                settings
            }
        }" )->assertJson( [
            'data' => [
                'me' => [
                    'settings' => null,
                ],
            ]
        ] );
    }


    public function testUser()
    {
        $settings = ['page' => ['filter' => ['view' => 'list'], 'sort' => ['column' => 'ID', 'order' => 'DESC']]];

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation ($settings: JSON!) {
                cmsUser(settings: $settings) {
                    settings
                }
            }
        ', ['settings' => json_encode( $settings )] );

        $this->assertEquals( $settings, json_decode( $response->json( 'data.cmsUser.settings' ), true ) );

        $this->assertEquals( $settings, json_decode( $this->user->fresh()->cmsdata, true ) );
    }


    public function testUserOverwrite()
    {
        $first = ['page' => ['filter' => ['view' => 'list']]];
        $second = ['file' => ['sort' => ['column' => 'NAME', 'order' => 'ASC']]];

        $this->actingAs( $this->user )->graphQL( '
            mutation ($settings: JSON!) {
                cmsUser(settings: $settings) {
                    settings
                }
            }
        ', ['settings' => json_encode( $first )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation ($settings: JSON!) {
                cmsUser(settings: $settings) {
                    settings
                }
            }
        ', ['settings' => json_encode( $second )] );

        $this->assertEquals( $second, json_decode( $this->user->fresh()->cmsdata, true ) );
    }


    public function testUserGuest()
    {
        $this->graphQL( '
            mutation ($settings: JSON!) {
                cmsUser(settings: $settings) {
                    settings
                }
            }
        ', ['settings' => json_encode( ['page' => []] )] )->assertGraphQLErrorMessage( 'Unauthenticated.' );
    }


    public function testUserTooLarge()
    {
        $settings = ['data' => str_repeat( 'x', 65536 )];

        $this->actingAs( $this->user )->graphQL( '
            mutation ($settings: JSON!) {
                cmsUser(settings: $settings) {
                    settings
                }
            }
        ', ['settings' => json_encode( $settings )] )->assertGraphQLErrorMessage( 'User data too large (64 KB), maximum is 64 KB' );
    }
}
