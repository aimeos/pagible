<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\ProxyToken;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class ProxyTokenTest extends AdminTestAbstract
{
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


    public function testExpired()
    {
        config()->set( 'cms.admin.proxy.ttl', -1 );

        $user = $this->user( 1 );
        $token = app( ProxyToken::class )->make( $user );

        $this->assertFalse( app( ProxyToken::class )->valid( $token, $user ) );
    }


    public function testGraphql()
    {
        $user = $this->user( 1 );

        $response = $this->actingAs( $user )->graphQL( '{ me { token } }' );
        $token = $response->json( 'data.me.token' );

        $this->assertIsString( $token );
        $this->assertTrue( app( ProxyToken::class )->valid( $token, $user ) );
    }


    public function testInvalid()
    {
        $this->assertFalse( app( ProxyToken::class )->valid( 'invalid', $this->user( 1 ) ) );
    }


    public function testValid()
    {
        $user = $this->user( 1 );
        $token = app( ProxyToken::class )->make( $user );

        $this->assertTrue( app( ProxyToken::class )->valid( $token, $user ) );
    }


    public function testWrongUser()
    {
        $token = app( ProxyToken::class )->make( $this->user( 1 ) );

        $this->assertFalse( app( ProxyToken::class )->valid( $token, $this->user( 2 ) ) );
    }


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'lighthouse.schema_path', dirname( __DIR__, 2 ) . '/graphql/tests/default-schema.graphql' );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->bootRefreshesSchemaCache();
    }


    protected function user( int $id ) : \App\Models\User
    {
        return ( new \App\Models\User( [
            'name' => 'Admin',
            'email' => $id . '@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] ) )->forceFill( ['id' => $id] );
    }
}
