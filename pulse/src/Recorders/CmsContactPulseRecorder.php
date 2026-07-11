<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsContact;


class CmsContactPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsContact::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof CmsContact ) {
            return;
        }

        $this->latency( 'cms_contact', [
            'action' => 'theme:contact',
            'ip' => $event->ip,
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
