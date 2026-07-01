<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Generated;


class CmsAiPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Generated::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Generated ) {
            return;
        }

        $key = [
            'mutation' => $this->prefixed( 'ai', $event->mutation ),
            'provider' => $event->provider,
            'model' => $event->model,
            'tenant' => $event->tenant,
            'success' => $event->success,
        ];

        $this->latency( 'cms_ai', $key, $event->durationMs );
    }
}
