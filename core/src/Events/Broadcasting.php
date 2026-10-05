<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Broadcasts content events on the per-type channel of their tenant.
 *
 * Using classes provide the "contentType" and "tenant" properties.
 */
trait Broadcasting
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Whether this instance should be sent to the websocket broadcaster.
     *
     * Lets the model dispatch the event to in-process listeners (audit logging, metrics)
     * via event() without broadcasting it, while the explicit broadcast()->toOthers()
     * path sets it to true. Kept out of the broadcast payload.
     */
    public bool $broadcasting = false;


    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn() : array
    {
        return [new PrivateChannel( Channel::type( $this->tenant, $this->contentType ) )];
    }


    /**
     * Only broadcast when dispatched through the broadcast path, not when dispatched to
     * in-process listeners via event().
     */
    public function broadcastWhen() : bool
    {
        return $this->broadcasting;
    }
}
