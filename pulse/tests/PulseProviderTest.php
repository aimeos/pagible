<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\Pulse\Recorder;
use Laravel\Pulse\Pulse;


class PulseProviderTest extends PulseTestCase
{
    public function testRegistersRecorder() : void
    {
        $recorders = $this->application()->make( Pulse::class )->recorders();

        $this->assertTrue( config( 'pulse.recorders.' . Recorder::class ) );
        $this->assertTrue( $recorders->contains( fn( object $recorder ) => $recorder instanceof Recorder ) );
    }
}
}
