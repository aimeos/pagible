<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\PulseServiceProvider;
use App\Models\User;
use Illuminate\Support\Facades\Gate;


class PulseGateTest extends PulseTestCase
{
    public function testPulseDefaultGateIsOverriddenAfterBoot() : void
    {
        Gate::define( 'viewPulse', ( new \Laravel\Pulse\PulseServiceProvider )->gate() );

        $this->gate();

        $user = new User( ['cmsperms' => ['page:view']] );

        $this->assertTrue( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    public function testAppDefinedPulseGateIsPreserved() : void
    {
        Gate::define( 'viewPulse', fn( $user ) => false );

        $this->gate();

        $user = new User( ['cmsperms' => ['page:view']] );

        $this->assertFalse( Gate::forUser( $user )->allows( 'viewPulse' ) );
    }


    protected function gate() : void
    {
        $method = new \ReflectionMethod( PulseServiceProvider::class, 'gate' );
        $method->invoke( new PulseServiceProvider( $this->application() ) );
    }
}
}
