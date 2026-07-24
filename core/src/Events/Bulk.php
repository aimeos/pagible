<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;


/**
 * Several items of one type were updated in a single bulk edit.
 *
 * Coalesces what would be one "saved" event per item into a single '{type}.bulk' message carrying
 * the saved ids, their new version ids and the shared fields applied to all of them.
 */
class Bulk implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Whether this instance should be sent to the websocket broadcaster.
     *
     * Lets the model dispatch the event to in-process listeners via event() without
     * broadcasting it, while the explicit broadcast()->toOthers() path sets it to true.
     */
    public bool $broadcasting = false;


    /**
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param list<string> $ids Ids of the saved items
     * @param array<string, string> $latest Saved item id => its new latest version id
     * @param array<string, mixed> $data Shared fields applied to every saved item
     * @param string $editor Editor name
     * @param string $tenant Tenant id; scopes the channel, not the payload
     * @param string $source Originating interface: 'graphql', 'mcp' or 'cli'; not in the payload
     * @param string $action Audit action name; not in the broadcast payload
     */
    public function __construct(
        public readonly string $contentType,
        public readonly array $ids,
        public readonly array $latest,
        public readonly array $data,
        public readonly string $editor = '',
        public readonly string $tenant = '',
        public readonly string $source = '',
        public readonly string $action = 'bulk',
    ) {}


    public function broadcastAs() : string
    {
        return $this->contentType . '.' . ( $this->action === 'purged' ? 'purged' : 'bulk' );
    }


    /**
     * Only broadcast when dispatched through the broadcast path, not when dispatched to
     * in-process listeners via event().
     */
    public function broadcastWhen() : bool
    {
        return $this->broadcasting;
    }


    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn() : array
    {
        return [new PrivateChannel( Channel::type( $this->tenant, $this->contentType ) )];
    }


    /**
     * @return array<string, mixed>
     */
    public function broadcastWith() : array
    {
        return [
            'contentType' => $this->contentType,
            'ids' => $this->ids,
            'latest' => $this->latest,
            'data' => $this->data,
            'editor' => $this->editor,
        ];
    }
}
