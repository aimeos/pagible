<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Aimeos\Cms\ContentType;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;


/**
 * Several items of one type were changed by a single call.
 *
 * Coalesces what would be one event per item into a single '{type}.{action}' message, e.g.
 * 'page.saved' for bulk edits, carrying the changed ids, their new version ids and the shared
 * fields applied to all of them. In contrast to the single item events, the payload contains "ids".
 */
class Bulk implements Loggable, ShouldBroadcastNow
{
    use Broadcasting;


    /**
     * @param string $contentType Content type: 'page', 'element' or 'file'
     * @param list<string> $ids Ids of the saved items
     * @param array<string, string> $latest Saved item id => its new latest version id
     * @param array<string, mixed> $data Shared fields applied to every saved item
     * @param string $editor Editor name
     * @param string $tenant Tenant id; scopes the channel, not the payload
     * @param string $source Originating interface: 'graphql', 'mcp' or 'cli'; not in the payload
     * @param string $action Action name, e.g. 'saved' for bulk edits or 'published', 'dropped', etc.; not in the broadcast payload
     * @param array<string, string> $projected Actually projected version ids; not in the broadcast payload
     * @param array<string, string> $langs Saved page id => language of its page variant
     */
    public function __construct(
        public readonly string $contentType,
        public readonly array $ids,
        public readonly array $latest,
        public readonly array $data,
        public readonly string $editor = '',
        public readonly string $tenant = '',
        public readonly string $source = '',
        public readonly string $action = 'saved',
        public readonly array $projected = [],
        public readonly array $langs = [],
    ) {}


    /**
     * Returns the type of the changed content.
     */
    public function type() : ContentType
    {
        return ContentType::from( $this->contentType );
    }


    public function broadcastAs() : string
    {
        return $this->contentType . '.' . $this->action;
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
        ] + ( $this->langs ? ['langs' => $this->langs] : [] );
    }


    /**
     * @return array{message: string, fields: array<string, mixed>}
     */
    public function log() : array
    {
        return ['message' => 'cms.' . $this->contentType, 'fields' => [
            'type' => $this->contentType,
            'source' => $this->source,
            'action' => $this->action,
            'ids' => array_values( $this->ids ),
            'editor' => $this->editor,
            'tenant_id' => $this->tenant,
        ] + ( $this->langs ? ['langs' => array_values( array_unique( $this->langs ) )] : [] )];
    }


    /**
     * Returns the changed items.
     *
     * @return list<Change> Changed items
     */
    public function changes() : array
    {
        $published = $this->action === 'published' && ( $this->data['published'] ?? false ) === true;

        return array_map( fn( string $id ) => new Change(
            contentType: $this->contentType,
            action: $this->action,
            id: $id,
            versionId: $this->projected[$id] ?? $this->latest[$id] ?? '',
            published: $published,
            lang: $this->langs[$id] ?? null,
            editor: $this->editor,
            tenant: $this->tenant,
            source: $this->source,
        ), array_values( $this->ids ) );
    }
}
