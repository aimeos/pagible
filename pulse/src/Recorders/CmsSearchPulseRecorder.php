<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsSearch;
use Aimeos\Cms\Watch;


class CmsSearchPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsSearch::class];


    public function record( mixed $event ) : void
    {
        // Sampled here, not at dispatch, so the audit log still records every
        // search; only the Pulse metric is thinned.
        if( !$event instanceof CmsSearch || !Watch::sampled() ) {
            return;
        }

        $this->latency( 'cms_search', [
            'action' => 'theme:search',
            'domain' => $event->domain,
            'lang' => $event->lang,
            'tenant' => $event->tenant,
        ], $event->durationMs );
    }
}
