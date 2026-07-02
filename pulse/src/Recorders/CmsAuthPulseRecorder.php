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

        // Authentication happens through the GraphQL API, so it shares the GraphQL
        // bucket and is distinguished from content operations by the "auth:" prefix.
        $this->entry( 'cms_graphql', [
            'action' => $this->prefixed( 'auth', $event->action ),
            'tenant' => $event->tenant,
        ] );
    }
}
