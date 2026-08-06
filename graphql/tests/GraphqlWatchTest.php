<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Events\Observed;
use Aimeos\Cms\Events\UserChanged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;


class GraphqlWatchTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'cms.watch.channel', 'cms' );
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


    public function testSetUserDispatchesAuthed() : void
    {
        Event::fake( [Authed::class] );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation ($settings: JSON!) { setUser(settings: $settings) { id } }
        ', ['settings' => json_encode( ['page' => []] )] );

        Event::assertDispatched( Authed::class, fn( Authed $e ) => $e->action === 'user-save' );
    }


    public function testCreateUserDispatchesUserChanged() : void
    {
        Event::fake( [UserChanged::class] );
        $this->editor()->forceFill( ['cmsperms' => ['user:create']] );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation { createUser(email: "created@example.com") { id } }
        ' )->assertGraphQLErrorFree();

        Event::assertDispatched( UserChanged::class, fn( UserChanged $e ) =>
            $e->action === 'create'
            && $e->actorEmail === 'editor@testbench'
            && $e->targetEmail === 'created@example.com'
            && $e->targetId !== ''
            && $e->assignments === []
            && $e->tenant === 'test'
        );
    }


    public function testSetUserAccessDispatchesUserChanged() : void
    {
        Event::fake( [UserChanged::class] );
        $this->editor()->forceFill( ['cmsperms' => ['user:access']] );
        $target = \App\Models\User::create( [
            'name' => 'Frontend user',
            'email' => 'member@example.com',
            'password' => 'secret',
            'cmsperms' => [],
        ] );
        Access::using(
            list: fn() => ['member'],
            userAccess: fn( $user, ?array $values ) => $values ?? [],
        );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation($id: ID!) { setUserAccess(id: $id, access: ["member"]) }
        ', ['id' => (string) $target->getKey()] )->assertGraphQLErrorFree();

        Event::assertDispatched( UserChanged::class, fn( UserChanged $e ) =>
            $e->action === 'access'
            && $e->targetEmail === 'member@example.com'
            && $e->targetId === (string) $target->getKey()
            && $e->assignments === ['member']
        );
    }


    public function testSetUserPermissionsDispatchesUserChanged() : void
    {
        Event::fake( [\Aimeos\Cms\Events\PermissionChanged::class] );
        $this->editor()->forceFill( ['cmsperms' => ['user:permission']] );
        $target = \App\Models\User::create( [
            'name' => 'Frontend user',
            'email' => 'member@example.com',
            'password' => 'secret',
            'cmsperms' => [],
        ] );

        $this->actingAs( $this->editor() )->graphQL( '
            mutation($id: ID!) { setUserPermissions(id: $id, permissions: ["viewer"]) }
        ', ['id' => (string) $target->getKey()] )->assertGraphQLErrorFree();

        Event::assertDispatched( \Aimeos\Cms\Events\PermissionChanged::class,
            fn( \Aimeos\Cms\Events\PermissionChanged $e ) =>
                $e->targetEmail === 'member@example.com'
                && $e->targetId === (string) $target->getKey()
                && $e->assignments === ['viewer']
        );
    }


    protected function editor() : \App\Models\User
    {
        if( !$this->user instanceof \App\Models\User ) {
            throw new \RuntimeException( 'Test user is not initialized.' );
        }

        return $this->user;
    }
}
