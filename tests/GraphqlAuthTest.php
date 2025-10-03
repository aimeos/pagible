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

        $attr = collect($user->getAttributes())->except(['cmseditor', 'password', 'secret', 'remember_token'])->all();
        $expected = [
            'id' => (string) $user->id,
            'created_at' => (string) $user->getAttribute( 'created_at' ),
            'updated_at' => (string) $user->getAttribute( 'updated_at' ),
        ] + $attr;

        $this->expectsDatabaseQueryCount( 1 );

        $response = $this->graphQL( "
            mutation {
                cmsLogin(email: \"editor@testbench\", password: \"secret\") {
                    id
                    email
                    name
                    email_verified_at
                    created_at
                    updated_at
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

        $attr = collect($user->getAttributes())->except(['cmseditor', 'password', 'secret', 'remember_token'])->all();
        $expected = [
            'id' => (string) $user->id,
            'created_at' => (string) $user->getAttribute( 'created_at' ),
            'updated_at' => (string) $user->getAttribute( 'updated_at' ),
        ] + $attr;

        $this->expectsDatabaseQueryCount( 0 );

        $response = $this->actingAs( $this->user )->graphQL( "
            mutation {
                cmsLogout {
                    id
                    email
                    name
                    email_verified_at
                    created_at
                    updated_at
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

        $attr = collect($user->getAttributes())->except(['cmseditor', 'password', 'secret', 'remember_token'])->all();
        $expected = [
            'id' => (string) $user->id,
            'created_at' => (string) $user->getAttribute( 'created_at' ),
            'updated_at' => (string) $user->getAttribute( 'updated_at' ),
        ] + $attr;

        $this->expectsDatabaseQueryCount( 0 );

        $response = $this->actingAs( $this->user )->graphQL( "{
            me {
                id
                email
                name
                email_verified_at
                created_at
                updated_at
            }
        }" )->assertJson( [
            'data' => [
                'me' => $expected,
            ]
        ] );
    }
}
