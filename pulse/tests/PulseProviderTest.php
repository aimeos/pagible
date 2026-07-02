<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */
namespace Tests {

use Aimeos\Cms\PulseServiceProvider;
use Aimeos\Cms\Recorders\CmsContactPulseRecorder;
use Aimeos\Cms\Recorders\CmsGraphqlPulseRecorder;
use Aimeos\Cms\Recorders\CmsJsonapiPulseRecorder;
use Aimeos\Cms\Recorders\CmsMcpPulseRecorder;
use Aimeos\Cms\Recorders\CmsRequestPulseRecorder;
use Aimeos\Cms\Recorders\CmsSearchPulseRecorder;


class PulseProviderTest extends PulseTestCase
{
    public function testRegistersCmsPulseRecorders() : void
    {
        $method = new \ReflectionMethod( PulseServiceProvider::class, 'pulse' );
        $method->invoke( new PulseServiceProvider( $this->application() ), dirname( __DIR__ ) );

        $this->assertSame( [
            CmsGraphqlPulseRecorder::class => true,
            CmsMcpPulseRecorder::class => true,
            CmsSearchPulseRecorder::class => true,
            CmsContactPulseRecorder::class => true,
            CmsJsonapiPulseRecorder::class => true,
            CmsRequestPulseRecorder::class => true,
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
