<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Watch;


class CmsAuthPulseRecorder extends Recorder
{
    public string $listen = Authed::class;


    public function record( mixed $event ) : void
    {
        if( !$event instanceof Authed ) {
            return;
        }

        $this->entry( 'cms_auth', [
            'action' => $this->prefixed( 'graphql', $event->action ),
            'email' => Watch::mask( $event->email ),
            'ip' => Watch::mask( $event->ip ),
            'user_agent' => Watch::mask( $event->userAgent ),
            'tenant' => $event->tenant,
        ] );
    }
}
