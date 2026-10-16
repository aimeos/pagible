<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\ContentType;
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
 * The broadcast payload uses the model/database column names so the browser can apply them directly.
 *
 * @phpstan-type Projection array{version_id: string, path?: string, domain?: string, lang?: string}
 */
abstract class Event implements Loggable, ShouldBroadcastNow
{
    use Broadcasting;


    /**
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param string $id Content UUID
     * @param string $latestId New version UUID (model's latest_id column)
     * @param string $editor Editor name
     * @param array<string, mixed> $data Version data
     * @param bool $published Whether the latest version is published (list draft state)
     * @param string|null $deletedAt Soft-delete timestamp or null (list trash state)
     * @param string|null $publishAt Scheduled publish timestamp or null (list scheduled state)
     * @param string|null $updatedAt Latest version timestamp (list modified date)
     * @param string $tenant Tenant ID the change belongs to; scopes the channel, not in the payload
     * @param string $source Originating interface: 'graphql', 'mcp' or 'cli'; not in the payload
     * @param array{}|Projection $projection Published projection; not in the broadcast payload
     */
    public function __construct(
        public readonly string $contentType,
        public readonly string $id,
        public readonly string $latestId,
        public readonly string $editor,
        public readonly array $data,
        public readonly bool $published = false,
        public readonly ?string $deletedAt = null,
        public readonly ?string $publishAt = null,
        public readonly ?string $updatedAt = null,
        public readonly string $tenant = '',
        public readonly string $source = '',
        public readonly array $projection = [],
    ) {}


    /**
     * Returns the type of the changed content.
     */
    public function type() : ContentType
    {
        return ContentType::from( $this->contentType );
    }


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
            'latest_id' => $this->latestId,
            'editor' => $this->editor,
            'data' => $this->data,
            'published' => $this->published,
            'deleted_at' => $this->deletedAt,
            'publish_at' => $this->publishAt,
            'updated_at' => $this->updatedAt,
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
        ] + ( $this->type() === ContentType::Page ? [
            'path' => $data['path'] ?? null,
            'domain' => $data['domain'] ?? null,
        ] : [] )];
    }


    /**
     * Returns the changed item.
     *
     * @return list<Change> Changed item
     */
    public function changes() : array
    {
        $action = strtolower( class_basename( $this ) );
        $projection = $this instanceof Published ? $this->projection : [];
        $data = $projection ?: $this->data;
        $page = $this->type() === ContentType::Page;

        return [new Change(
            contentType: $this->contentType,
            action: $action,
            id: $this->id,
            versionId: $projection['version_id'] ?? $this->latestId,
            published: $action === 'published' && ( $this->published || $projection !== [] ),
            // lifecycle events of whole pages have no language, only those of single page variants
            lang: $page && !( $this instanceof Dropped || $this instanceof Restored || $this instanceof Purged ) && is_string( $data['lang'] ?? null ) ? $data['lang'] : null,
            path: $page && is_string( $data['path'] ?? null ) ? $data['path'] : null,
            domain: $page && is_string( $data['domain'] ?? null ) ? $data['domain'] : null,
            editor: $this->editor,
            tenant: $this->tenant,
            source: $this->source,
        )];
    }
}
