<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsMcp;


class CmsMcpPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsMcp::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof CmsMcp ) {
            return;
        }

        $this->latency( 'cms_mcp', [
            'action' => $event->action,
            'domain' => $event->domain,
            'tenant' => $event->tenant,
            'success' => $event->success,
        ], $event->durationMs );
    }
}
