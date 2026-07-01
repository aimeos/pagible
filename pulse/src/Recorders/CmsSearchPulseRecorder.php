<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Watch;


class CmsSearchPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Searched::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Searched || !Watch::sampled() ) {
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
