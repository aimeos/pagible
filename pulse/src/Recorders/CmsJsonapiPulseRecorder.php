<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Watch;


class CmsJsonapiPulseRecorder extends Recorder
{
    public string $listen = Queried::class;


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Queried || !Watch::sampled() ) {
            return;
        }

        $this->entry( 'cms_jsonapi', [
            'action' => $event->action,
            'domain' => $event->domain,
            'includes' => $event->includes,
            'tenant' => $event->tenant,
        ], $this->ms( $event->durationMs ), ['count', 'avg', 'max'] );
    }
}
