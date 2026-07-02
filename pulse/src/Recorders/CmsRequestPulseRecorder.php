<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsRequest;


class CmsRequestPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsRequest::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof CmsRequest ) {
            return;
        }

        // 200s are keyed by page path (leading "/" so home's empty path becomes "/"
        // instead of being dropped by Recorder::key()'s empty filter). Other statuses
        // collapse into one "*" bucket per code so 404 scans can't inflate cardinality.
        // Domain is kept only for 200s: the Host header is attacker-controlled, so keying
        // non-200s by host would let spoofed values mint unbounded rows, while a 200 needs
        // a real page for that host. Empty on single-domain sites, then filtered out.
        $this->latency( 'cms_request', [
            'action' => 'theme:view',
            'path' => $event->status === 200 ? '/' . $event->path : '*',
            'domain' => $event->status === 200 ? $event->domain : '',
            'status' => $event->status,
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
