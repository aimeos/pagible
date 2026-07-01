<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Contacted;


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
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
