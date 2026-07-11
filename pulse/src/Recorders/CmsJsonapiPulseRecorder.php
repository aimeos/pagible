<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsJsonapi;
use Aimeos\Cms\Watch;


class CmsJsonapiPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsJsonapi::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof CmsJsonapi || !Watch::sampled() ) {
            return;
        }

        $this->latency( 'cms_jsonapi', [
            'action' => $event->action,
            'domain' => $event->domain,
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
