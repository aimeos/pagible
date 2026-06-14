<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;


class ContentChanged implements ShouldBroadcastNow
{
    use Dispatchable;

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
    ) {}


    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn() : array
    {
        // structural changes (added/removed/moved) only concern the list/tree views, not an open detail view
        if( $this->action !== 'saved' ) {
            return [new PrivateChannel( "cms.{$this->contentType}" )];
        }

        return [
            // per-item channel for the open detail view
            new PrivateChannel( "cms.{$this->contentType}.{$this->id}" ),
            // per-type channel for the list/tree views
            new PrivateChannel( "cms.{$this->contentType}" ),
        ];
    }


    public function broadcastAs() : string
    {
        return 'content.changed';
    }
}
