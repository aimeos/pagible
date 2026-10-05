<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;


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
abstract class Event implements Loggable, ShouldBroadcastNow
{
    use Broadcasting;


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
     * @param string $source Originating interface: 'graphql', 'mcp' or 'cli'; not in the payload
     * @param array{}|array{version_id: string, path?: string, domain?: string} $projection Published projection; not in the broadcast payload
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
        public readonly string $source = '',
        public readonly array $projection = [],
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


    /**
     * Returns the watch log entry including the page route for page events.
     *
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        $projected = $this instanceof Published && $this->projection !== [];
        $data = $projected ? $this->projection : $this->data;

        return ['message' => 'cms.' . $this->contentType, 'fields' => [
            'type' => $this->contentType,
            'source' => $this->source,
            'action' => strtolower( class_basename( $this ) ),
            'ids' => [$this->id],
            'editor' => $this->editor,
            'published' => $projected || $this->published,
            'tenant_id' => $this->tenant,
        ] + ( $this->contentType === 'page' ? [
            'path' => $data['path'] ?? null,
            'domain' => $data['domain'] ?? null,
        ] : [] )];
    }
}
