<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;


/**
 * Routes the CMS jobs to the configured queue connection and name.
 */
trait RoutesQueue
{
    /**
     * Uses the queue connection and name from the CMS configuration.
     */
    protected function routeQueue() : void
    {
        $this->onConnection( config( 'cms.queue.connection' ) ?: null )->onQueue( config( 'cms.queue.name' ) ?: null );
    }
}
