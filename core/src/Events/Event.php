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
 * Base for the per-operation content events broadcast to the per-type channel.
 *
 * One concrete subclass exists per operation: Added, Saved, Published, Restored, Dropped,
 * Moved and Purged. Every event carries metadata only (no content/meta/config), keeping the
 * broadcast small; an open detail view reloads its item when it sees the matching 'saved'.
 *
 * The websocket name is '{type}.{action}', where {action} is the lower-cased class name
 * (Saved -> 'page.saved', Moved -> 'element.moved'), so the browser subscribes per name and
 * decides whether to patch the row or reload - there is no separate action field in the payload.
 *
 * Properties use the model/database column names so consumers can apply them directly.
 */
abstract class Event implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param string $id Content UUID
     * @param string $latest_id New version UUID (model's latest_id column)
     * @param string $editor Editor name
     * @param array<string, mixed> $data Version data
     * @param bool $published Whether the latest version is published (list draft state)
     * @param string|null $deleted_at Soft-delete timestamp or null (list trash state)
     * @param string|null $publish_at Scheduled publish timestamp or null (list scheduled state)
     * @param string|null $updated_at Latest version timestamp (list modified date)
     * @param string $tenant Tenant ID the change belongs to; scopes the channel, not in the payload
     */
    public function __construct(
        public readonly string $contentType,
        public readonly string $id,
        public readonly string $latest_id,
        public readonly string $editor,
        public readonly array $data,
        public readonly bool $published = false,
        public readonly ?string $deleted_at = null,
        public readonly ?string $publish_at = null,
        public readonly ?string $updated_at = null,
        public readonly string $tenant = '',
    ) {}


    /**
     * Websocket event name the browser subscribes to: the content type plus the lower-cased
     * class name, e.g. 'page.saved' or 'element.moved'.
     */
    public function broadcastAs() : string
    {
        return $this->contentType . '.' . strtolower( class_basename( $this ) );
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
            'id' => $this->id,
            'latest_id' => $this->latest_id,
            'editor' => $this->editor,
            'data' => $this->data,
            'published' => $this->published,
            'deleted_at' => $this->deleted_at,
            'publish_at' => $this->publish_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
