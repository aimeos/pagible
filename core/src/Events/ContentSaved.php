<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;


class ContentSaved implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param string $contentType Content type: 'page', 'element', 'file'
     * @param string $contentId Content UUID
     * @param string $versionId New version UUID
     * @param string $editor Editor display name
     * @param array<string, mixed> $data Version data
     * @param array<string, mixed>|null $aux Version aux: content/meta/config (pages only)
     */
    public function __construct(
        public string $contentType,
        public string $contentId,
        public string $versionId,
        public string $editor,
        public array $data,
        public ?array $aux = null,
    ) {}


    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn() : array
    {
        return [new PrivateChannel( "cms.{$this->contentType}.{$this->contentId}" )];
    }


    public function broadcastAs() : string
    {
        return 'content.saved';
    }
}
