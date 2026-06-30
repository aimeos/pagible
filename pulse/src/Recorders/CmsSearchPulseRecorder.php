<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Watch;


class CmsSearchPulseRecorder extends Recorder
{
    public string $listen = Searched::class;


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Searched || !Watch::sampled() ) {
            return;
        }

        $this->entry( 'cms_search', [
            'action' => 'theme:search',
            'query' => $event->query,
            'results' => $event->results,
            'page' => $event->page,
            'domain' => $event->domain,
            'lang' => $event->lang,
            'tenant' => $event->tenant,
        ], $this->ms( $event->durationMs ), ['count', 'avg', 'max'] );
    }
}
