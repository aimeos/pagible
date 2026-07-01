<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Authed;


class CmsAuthPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [Authed::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Authed ) {
            return;
        }

        $action = $this->prefixed( 'graphql', $event->action );

        $this->entry( 'cms_auth', [
            'action' => $action,
            'tenant' => $event->tenant,
        ] );
    }
}
