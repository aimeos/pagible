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
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param list<string> $ids Ids of the saved items
     * @param array<string, string> $latest Saved item id => its new latest version id
     * @param array<string, mixed> $data Shared fields applied to every saved item
     * @param string $editor Editor name
     * @param string $tenant Tenant id; scopes the channel, not the payload
     */
    public function __construct(
        public readonly string $contentType,
        public readonly array $ids,
        public readonly array $latest,
        public readonly array $data,
        public readonly string $editor = '',
        public readonly string $tenant = '',
    ) {}


    public function broadcastAs() : string
    {
        return $this->contentType . '.bulk';
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
