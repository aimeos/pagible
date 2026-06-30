<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Watch;


class CmsContactPulseRecorder extends Recorder
{
    public string $listen = Contacted::class;


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Contacted || !Watch::sampled() ) {
            return;
        }

        $this->entry( 'cms_contact', [
            'action' => 'theme:contact',
            'email' => Watch::mask( $event->email ),
            'ip' => Watch::mask( $event->ip ),
            'tenant' => $event->tenant,
        ], $this->ms( $event->durationMs ), ['count', 'avg', 'max'] );
    }
}
