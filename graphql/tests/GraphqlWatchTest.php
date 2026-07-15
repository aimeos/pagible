<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\Observed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlWatchTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
        $app['config']->set( 'cms.watch.channel', 'cms' );
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

        RateLimiter::clear( 'cms-login:127.0.0.1|editor@testbench' );

        $this->user = \App\Models\User::create([
            'name' => 'Test',
            'email' => 'editor@testbench',
            'password' => Hash::make('secret'),
            'cmsperms' => ['page:view']
        ]);
    }


    public function testQueryDispatchesObserved() : void
    {
        Event::fake( [Observed::class] );

        $this->graphQL( '
            query { users { data { id } } }
        ' );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'graphql'
            && $e->action === 'users'
            && $e->dimensions['success'] === true
            && $e->durationMs >= 0.0
        );
    }


    public function testAuthMutationDispatchesObserved() : void
    {
        Event::fake( [Observed::class] );

        $this->graphQL( '
            mutation { cmsLogin(email: "editor@testbench", password: "secret") { id } }
        ' );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'graphql'
            && $e->action === 'cmsLogin'
            && $e->dimensions['success'] === true
        );
    }


    public function testFailedGraphqlDispatchesUnsuccessfulObserved() : void
    {
        Event::fake( [Authed::class, Observed::class] );

        $this->graphQL( '
            mutation { cmsLogin(email: "editor@testbench", password: "wrong") { id } }
        ' );

        Event::assertDispatched( Observed::class, fn( Observed $e ) =>
            $e->source === 'graphql'
            && $e->action === 'cmsLogin'
            && $e->dimensions['success'] === false
        );
    }


    public function testLoginDispatchesAuthed() : void
    {
        Event::fake( [Authed::class] );

        $this->graphQL( '
            mutation { cmsLogin(email: "editor@testbench", password: "secret") { id } }
        ' );

        Event::assertDispatched( Authed::class, fn( Authed $e ) =>
            $e->action === 'login' && $e->email === 'editor@testbench'
        );
    }


    public function testFailedLoginDispatchesAuthed() : void
    {
        Event::fake( [Authed::class] );

        $this->graphQL( '
            mutation { cmsLogin(email: "editor@testbench", password: "wrong") { id } }
        ' );

        Event::assertDispatched( Authed::class, fn( Authed $e ) => $e->action === 'login-fail' );
    }


    public function testLogoutDispatchesAuthed() : void
    {
        Event::fake( [Authed::class] );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation { cmsLogout { id } }
        ' );

        Event::assertDispatched( Authed::class, fn( Authed $e ) =>
            $e->action === 'logout' && $e->email === 'editor@testbench'
        );
    }


    public function testUserSaveDispatchesAuthed() : void
    {
        Event::fake( [Authed::class] );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation ($settings: JSON!) { cmsUser(settings: $settings) { settings } }
        ', ['settings' => json_encode( ['page' => []] )] );

        Event::assertDispatched( Authed::class, fn( Authed $e ) => $e->action === 'user-save' );
    }


    protected function editor() : \App\Models\User
    {
        if( !$this->user instanceof \App\Models\User ) {
            throw new \RuntimeException( 'Test user is not initialized.' );
        }

        return $this->user;
    }
}
