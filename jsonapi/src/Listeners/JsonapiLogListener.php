<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Queried;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for read-only JSON:API requests.
 *
 * Active only when "cms.watch.channel" is set; a listener failure never breaks the request.
 * Entries are sampled by "cms.watch.sample".
 */
class JsonapiLogListener
{
    public function handle( Queried $event ) : void
    {
        if( Watch::sampled() ) {
            Watch::emit( 'cms.jsonapi', $this->fields( $event ) );
        }
    }


    /**
     * @return array<string, mixed>
     */
    protected function fields( Queried $event ) : array
    {
        return [
            'action' => $event->action,
            'duration_ms' => round( $event->durationMs, 1 ),
            'domain' => $event->domain,
            'includes' => $event->includes,
            'tenant_id' => $event->tenant,
        ];
    }
}
