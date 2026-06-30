<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for single-item content changes.
 *
 * Subscribes to the per-action content events (Added, Saved, Published, Dropped, Restored,
 * Purged, Moved). Active only when "cms.watch.channel" is set; a listener failure never breaks
 * the originating operation.
 */
class ContentListener
{
    public function handle( Event $event ) : void
    {
        Watch::emit( 'cms.' . $event->contentType, [
            'type' => $event->contentType,
            'source' => $event->source,
            'action' => strtolower( class_basename( $event ) ),
            'ids' => [$event->id],
            'editor' => $event->editor,
            'published' => $event->published,
            'tenant_id' => $event->tenant,
        ] + $this->extra( $event ) );
    }


    /**
     * Adds the page path and domain fields for page events.
     *
     * @return array<string, mixed>
     */
    protected function extra( Event $event ) : array
    {
        if( $event->contentType !== 'page' ) {
            return [];
        }

        return [
            'path' => $event->data['path'] ?? null,
            'domain' => $event->data['domain'] ?? null,
        ];
    }

}
