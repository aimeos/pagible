<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Jobs;

use Aimeos\Cms\Events\Change;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;


/**
 * Passes the changed items of one event to a queued Change::listen() listener class.
 */
class HandleChanges implements ShouldQueue
{
    use Dispatchable;
    use Queueable;
    use RoutesQueue;


    /**
     * @param class-string $listener Listener class with a handle(Change $change) method
     * @param list<Change> $changes Changed items of one event
     */
    public function __construct( public string $listener, public array $changes )
    {
        $this->routeQueue();

        // Listener classes can choose their own queue like queued Laravel event listeners
        $vars = get_class_vars( $listener );
        $this->connection = $vars['connection'] ?? $this->connection;
        $this->queue = $vars['queue'] ?? $this->queue;
    }


    public function handle() : void
    {
        $listener = app( $this->listener );

        foreach( $this->changes as $change ) {
            $listener->handle( $change );
        }
    }
}
