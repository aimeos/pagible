<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Recorders\CmsAiPulseRecorder;
use Aimeos\Cms\Recorders\CmsAuthPulseRecorder;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsContentPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;


class PulseProviderTest extends PulseTestCase
{
    public function testRegistersCmsPulseRecorders() : void
    {
        $method = new \ReflectionMethod( PulseServiceProvider::class, 'pulse' );
        $method->invoke( new PulseServiceProvider( $this->application() ), dirname( __DIR__ ) );

        $this->assertSame( [
            CmsContentPulseRecorder::class => true,
            CmsAuthPulseRecorder::class => true,
            CmsAiPulseRecorder::class => true,
            CmsSearchPulseRecorder::class => true,
            CmsContactPulseRecorder::class => true,
            CmsJsonapiPulseRecorder::class => true,
        ], $this->pulse->recorders );
    }


    public function testSkipsCmsPulseRecordersWhenPulseIsDisabled() : void
    {
        config( ['pulse.enabled' => false] );

        $method = new \ReflectionMethod( PulseServiceProvider::class, 'pulse' );
        $method->invoke( new PulseServiceProvider( $this->application() ), dirname( __DIR__ ) );

        $this->assertSame( [], $this->pulse->recorders );
    }
}
}
