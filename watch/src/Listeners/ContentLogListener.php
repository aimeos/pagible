<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Utils;


/**
 * Writes a structured JSON line to the CMS log channel for every content change.
 *
 * Subscribes to the per-action content events (Added, Saved, Published, Dropped, Restored,
 * Purged, Moved) and the coalesced Bulk event. Active only when "cms.watch.channel" is set;
 * a listener failure never breaks the originating operation.
 */
class ContentLogListener
{
    use WritesLog;


    /**
     * Logs a single-item content change (add/save/publish/drop/restore/purge/move).
     */
    public function handle( Event $event ) : void
    {
        $this->write( $event->contentType, $event->source, [
            'action' => strtolower( class_basename( $event ) ),
            'ids' => [$event->id],
            'editor' => $event->editor,
            'published' => $event->published,
            'tenant_id' => $event->tenant,
        ] + $this->extra( $event ) );
    }


    /**
     * Logs a bulk content change applied to several items at once.
     */
    public function handleBulk( Bulk $event ) : void
    {
        $this->write( $event->contentType, $event->source, [
            'action' => 'bulk',
            'ids' => array_values( $event->ids ),
            'editor' => $event->editor,
            'tenant_id' => $event->tenant,
        ] );
    }


    /**
     * Adds the page path and domain to the context for page events.
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


    /**
     * Writes the structured entry to the CMS log channel, dropping null/empty values.
     *
     * @param string $type Content type ('page', 'element' or 'file')
     * @param string $source Originating interface captured on the event
     * @param array<string, mixed> $context Entry fields
     */
    protected function write( string $type, string $source, array $context ) : void
    {
        $context = [
            'request_id' => Utils::requestId(),
            'type' => $type,
            'source' => $source,
        ] + $context;

        $this->emit( 'cms.' . $type, array_filter( $context, fn( $value ) => $value !== null && $value !== '' ) );
    }
}
