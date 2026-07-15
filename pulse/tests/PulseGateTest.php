<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use App\Models\User;
use Illuminate\Support\Facades\Gate;


class PulseGateTest extends PulseTestCase
{
    public function testPulsePermissionIsRegistered() : void
    {
        $this->assertContains( 'pulse:view', Permission::all() );
    }


    public function testPulseDefaultGateAllowsPulseViewPermission() : void
    {
        Gate::define( 'viewPulse', $this->pulseDefaultGate() );

        $this->gate();

        $user = new User( ['cmsperms' => ['pulse:view']] );
        $user->forceFill( ['tenant_id' => ''] );

        $this->assertTrue( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    public function testPulseDefaultGateRejectsOtherCmsPermissions() : void
    {
        Gate::define( 'viewPulse', $this->pulseDefaultGate() );

        $this->gate();

        $user = new User( ['cmsperms' => ['page:view']] );

        $this->assertFalse( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    public function testDefaultPulseGateRequiresCurrentTenantAccess() : void
    {
        $this->application()->instance( Tenancy::class, new Tenancy( 'tenant-b' ) );
        Gate::define( 'viewPulse', $this->pulseDefaultGate() );

        $this->gate();

        $allowed = new User( ['cmsperms' => ['pulse:view']] );
        $allowed->forceFill( ['tenant_id' => 'tenant-b'] );

        $denied = new User( ['cmsperms' => ['pulse:view']] );
        $denied->forceFill( ['tenant_id' => 'tenant-a'] );

        $this->assertTrue( Gate::forUser( $allowed )->allows( 'viewPulse' ) );
        $this->assertFalse( Gate::forUser( $denied )->allows( 'viewPulse' ) );
    }


    public function testDefaultPulseGateDeniesTenantlessRouteWhenTenancyIsConfigured() : void
    {
        Tenancy::$callback = fn() => '';
        $this->application()->instance( Tenancy::class, new Tenancy( '' ) );
        Gate::define( 'viewPulse', $this->pulseDefaultGate() );

        $this->gate();

        $user = new User( ['cmsperms' => ['pulse:view']] );
        $user->forceFill( ['tenant_id' => ''] );

        $this->assertFalse( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    public function testAppDefinedPulseGateIsPreserved() : void
    {
        Gate::define( 'viewPulse', fn( $user ) => false );

        $this->gate();

        $user = new User( ['cmsperms' => ['pulse:view']] );

        $this->assertFalse( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    protected function gate() : void
    {
        $method = new \ReflectionMethod( PulseServiceProvider::class, 'gate' );
        $method->invoke( new PulseServiceProvider( $this->application() ) );
    }


    private function pulseDefaultGate() : \Closure
    {
        return \Closure::bind(
            fn( $user ) => false,
            new \Laravel\Pulse\PulseServiceProvider( $this->application() ),
            \Laravel\Pulse\PulseServiceProvider::class
        ) ?? throw new \RuntimeException( 'Unable to bind Pulse gate' );
    }
}
}
