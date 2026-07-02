<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\CmsGraphql;


class CmsGraphqlPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [CmsGraphql::class];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof CmsGraphql ) {
            return;
        }

        $this->latency( 'cms_graphql', [
            'action' => $event->action,
            'domain' => $event->domain,
            'tenant' => $event->tenant,
            'success' => $event->success,
        ], $event->durationMs );
    }
}
