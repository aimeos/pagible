<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Contracts\Broadcasting\Factory as BroadcastFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;


class ChannelAuthTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        // register the channels at boot and keep a broadcaster whose callbacks we can inspect
        $app['config']->set( 'cms.broadcast', true );
        $app['config']->set( 'broadcasting.default', 'null' );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => Permission::all(),
        ]);
        $this->user->tenant_id = 'test';
    }


    protected function tearDown(): void
    {
        Tenancy::$access = null;

        parent::tearDown();
    }


    public function testTypeChannelAllowsSameTenantOnly() : void
    {
        $auth = $this->channels()['cms.{tenant}.page'];

        $this->assertTrue( (bool) $auth( $this->user, 'test' ) );
        $this->assertFalse( (bool) $auth( $this->user, 'other' ) );
    }


    public function testTypeChannelDeniedWithoutViewPermission() : void
    {
        $user = new \App\Models\User( ['email' => 'x@testbench', 'cmsperms' => []] );
        $user->tenant_id = 'test'; // same tenant, so only the missing permission denies
        $auth = $this->channels()['cms.{tenant}.page'];

        $this->assertFalse( (bool) $auth( $user, 'test' ) );
    }


    public function testUnscopedChannelDeniedWhenTenantActive() : void
    {
        $auth = $this->channels()['cms.page'];

        $this->assertFalse( (bool) $auth( $this->user ) ); // Tenancy::value() === 'test'
    }


    public function testUnscopedChannelAllowedWhenNoTenant() : void
    {
        Tenancy::$callback = null; // genuine single-tenant deployment (tenancy not configured)
        $this->app->instance( Tenancy::class, new Tenancy( '' ) );

        $auth = $this->channels()['cms.page'];

        $this->assertTrue( (bool) $auth( $this->user ) );
    }


    public function testUnscopedChannelDeniedWhenTenancyConfigured() : void
    {
        // multi-tenant deployment (Tenancy::$callback set) whose auth route resolved no tenant -
        // the tenant-less channel must stay closed rather than open to every tenant
        $this->app->instance( Tenancy::class, new Tenancy( '' ) );

        $auth = $this->channels()['cms.page'];

        $this->assertFalse( (bool) $auth( $this->user ) ); // Tenancy::value() === '' but $callback !== null
    }


    public function testAccessHookCanDenyTenant() : void
    {
        Tenancy::$access = fn( $user, string $tenant ) => false;

        $auth = $this->channels()['cms.{tenant}.page'];

        $this->assertFalse( (bool) $auth( $this->user, 'test' ) ); // same tenant + view, but hook denies
    }


    /**
     * @return array<string, \Closure>
     */
    protected function channels() : array
    {
        $broadcaster = app( BroadcastFactory::class )->connection();
        $channels = ( new \ReflectionProperty( $broadcaster, 'channels' ) )->getValue( $broadcaster );

        return is_array( $channels ) ? $channels : [];
    }
}
