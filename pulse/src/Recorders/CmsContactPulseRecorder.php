<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Watch;


class CmsContactPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Contacted::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Contacted ) {
            return;
        }

        $this->latency( 'cms_contact', [
            'action' => 'theme:contact',
            'email' => Watch::mask( $event->email ),
            'ip' => Watch::mask( $event->ip ),
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
