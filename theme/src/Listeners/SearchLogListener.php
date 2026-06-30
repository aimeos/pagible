<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Searched;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for frontend searches.
 *
 * Active only when "cms.watch.channel" is set; a listener failure never breaks the request.
 * Entries are sampled by "cms.watch.sample".
 */
class SearchLogListener
{
    /**
     * Logs the search as a structured entry.
     */
    public function handle( Searched $event ) : void
    {
        if( Watch::sampled() ) {
            Watch::emit( 'cms.search', $this->context( $event ) );
        }
    }


    /**
     * Builds the structured log context.
     *
     * @return array<string, mixed>
     */
    protected function context( Searched $event ) : array
    {
        return [
            'query' => $event->query,
            'results' => $event->results,
            'page' => $event->page,
            'duration_ms' => round( $event->durationMs, 1 ),
            'domain' => $event->domain,
            'lang' => $event->lang,
            'tenant_id' => $event->tenant,
        ];
    }
}
