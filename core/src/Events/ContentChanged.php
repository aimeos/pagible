<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;


class ContentChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * Properties use the model/database column names so consumers can apply them
     * directly without mapping between event and column names.
     *
     * @param string $contentType Content type: 'page', 'element', 'file'
     * @param string $id Content UUID
     * @param string $latest_id New version UUID (model's latest_id column)
     * @param string $editor Editor name
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed>|null $aux Version aux: content/meta/config (pages only)
     * @param bool $published Whether the latest version is published (list draft state)
     * @param string|null $deleted_at Soft-delete timestamp or null (list trash state)
     * @param string|null $publish_at Scheduled publish timestamp or null (list scheduled state)
     * @param string|null $updated_at Latest version timestamp (list modified date)
     * @param string $action What happened: 'saved' (in-place edit), 'added', 'removed' or 'moved'
     * @param bool $detail Per-item detail variant: targets the per-item channel and carries $aux
     */
    public function __construct(
        public string $contentType,
        public string $id,
        public string $latest_id,
        public string $editor,
        public array $data,
        public ?array $aux = null,
        public bool $published = false,
        public ?string $deleted_at = null,
        public ?string $publish_at = null,
        public ?string $updated_at = null,
        public string $action = 'saved',
        public bool $detail = false,
    ) {}


    public function broadcastAs() : string
    {
        return 'content.changed';
    }


    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn() : array
    {
        // The detail variant notifies the open detail view on the per-item channel and is
        // the only one carrying the heavy content/meta/config aux. The list variant goes to
        // the per-type channel for the list/tree views, which patch node metadata only.
        return $this->detail
            ? [new PrivateChannel( "cms.{$this->contentType}.{$this->id}" )]
            : [new PrivateChannel( "cms.{$this->contentType}" )];
    }


    /**
     * @return array<string, mixed>
     */
    public function broadcastWith() : array
    {
        $payload = [
            'contentType' => $this->contentType,
            'id' => $this->id,
            'latest_id' => $this->latest_id,
            'editor' => $this->editor,
            'data' => $this->data,
            'published' => $this->published,
            'deleted_at' => $this->deleted_at,
            'publish_at' => $this->publish_at,
            'updated_at' => $this->updated_at,
            'action' => $this->action,
        ];

        // Only the per-item detail channel carries content/meta/config; the list/tree
        // channel never reads aux, so omitting it keeps that broadcast small.
        if( $this->detail ) {
            $payload['aux'] = $this->aux;
        }

        return $payload;
    }
}
