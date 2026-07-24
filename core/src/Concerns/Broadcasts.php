<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event as Events;


/**
 * Lets a model broadcast its own changes over websockets, one event class per action.
 *
 * @phpstan-require-extends \Aimeos\Cms\Models\Base
 */
trait Broadcasts
{
    /**
     * Broadcasts a content change for this model after the current transaction commits.
     *
     * The action names the event class in Aimeos\Cms\Events ('saved' -> Saved), broadcast on the
     * per-type list/tree channel and consumed by both the list/tree views and any open detail
     * view of the changed item. toOthers() excludes the originating browser tab via its
     * X-Socket-ID header, while changes from other clients (MCP, API, another tab, a scheduled
     * job) carry no socket id and still reach the open editor.
     *
     * @param string $action Past-tense action: added, saved, published, restored, dropped, moved, purged
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @throws \InvalidArgumentException If $action has no matching event class
     */
    public function announce( string $action, Authenticatable|string|null $editor = null ) : void
    {
        $class = 'Aimeos\\Cms\\Events\\' . ucfirst( $action );

        if( !is_subclass_of( $class, Event::class ) ) {
            throw new \InvalidArgumentException( "Unknown broadcast action: {$action}" );
        }

        $broadcast = (bool) config( 'cms.broadcast' );

        // In-process listeners (audit logging) subscribe to the per-action events;
        // only do work when broadcasting is on or something listens. This
        // also avoids the per-item latest lazy load (e.g. on purge) when nothing is enabled.
        if( !$broadcast && !Events::hasListeners( $class ) ) {
            return;
        }

        if( !( $version = $this->latest ) ) {
            return;
        }

        static::send( new $class( ...$this->eventFields( $version, $editor, $action ) ), $broadcast );
    }


    /**
     * Broadcasts a single bulk-edit event for several updated items of one type.
     *
     * A no-op when broadcasting is disabled or nothing was saved.
     *
     * @param string $type Content type: 'page', 'element' or 'file'
     * @param list<string> $ids Ids of the saved items
     * @param array<string, string> $latest Saved item id => its new latest version id
     * @param array<string, mixed> $data Shared fields applied to every saved item
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @param string $action Audit action name
     */
    public static function announceBulk( string $type, array $ids, array $latest, array $data,
        Authenticatable|string|null $editor = null, string $action = 'bulk' ) : void
    {
        if( empty( $ids ) ) {
            return;
        }

        $broadcast = (bool) config( 'cms.broadcast' );

        if( !$broadcast && !Events::hasListeners( Bulk::class ) ) {
            return;
        }

        static::send( new Bulk(
            contentType: $type,
            ids: $ids,
            latest: $latest,
            data: $data,
            editor: is_string( $editor ) ? $editor : Utils::editor( $editor ),
            tenant: Tenancy::value(),
            source: Utils::source(),
            action: $action,
        ), $broadcast );
    }


    /**
     * Coalesces notifications while preserving single-item event names.
     *
     * @param Collection<int, \Aimeos\Cms\Models\Base> $items Changed items
     * @param string $action Past-tense lifecycle action
     * @param string $editor Editor name
     * @param array<string, mixed> $data Shared changed fields
     * @param bool $bulk TRUE to use the bulk event for a single item too
     */
    public static function announceMany( Collection $items, string $action, string $editor,
        array $data = [], bool $bulk = false ) : void
    {
        if( !( $first = $items->first() ) ) {
            return;
        }

        if( $items->count() === 1 && !$bulk ) {
            $first->announce( $action, $editor );
            return;
        }

        foreach( $items->chunk( 50 ) as $chunk ) {
            /** @var list<string> $ids */
            $ids = array_values( $chunk->pluck( 'id' )->all() );
            /** @var array<string, string> $latest */
            $latest = $chunk->pluck( 'latest_id', 'id' )->all();

            static::announceBulk(
                strtolower( class_basename( $first ) ),
                $ids,
                $latest,
                $data,
                $editor,
                $action,
            );
        }
    }


    /**
     * Returns the event data needed to patch lists or enrich audit entries.
     *
     * Lifecycle changes only alter list metadata. Page routes remain in the
     * payload because the audit listener records them.
     *
     * @return array<string, mixed>
     */
    protected function eventData( Version $version, string $action ) : array
    {
        if( !in_array( $action, ['dropped', 'purged', 'restored'], true ) ) {
            return (array) $version->data;
        }

        if( !$this instanceof Page ) {
            return [];
        }

        return [
            'path' => (string) ( $version->data->path ?? $this->path ),
            'domain' => (string) ( $version->data->domain ?? $this->domain ),
        ];
    }


    /**
     * Extracts the shared event fields from the model and version, keyed by the event constructor
     * parameter names so they can be spread into any event.
     *
     * @param Version $version Latest version of the model
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @param string $action Past-tense action
     * @return array{contentType: string, id: string, latest_id: string, editor: string, data: array<string, mixed>, published: bool, deleted_at: string|null, publish_at: string|null, updated_at: string|null, tenant: string, source: string}
     */
    protected function eventFields( Version $version, Authenticatable|string|null $editor,
        string $action ) : array
    {
        $id = $this->id;
        $latestId = $version->id;

        if( $id === null || $latestId === null ) {
            throw new \LogicException( 'Cannot announce unsaved CMS models.' );
        }

        return [
            'contentType' => strtolower( class_basename( $this ) ),
            'id' => $id,
            'latest_id' => $latestId,
            'editor' => is_string( $editor ) ? $editor : Utils::editor( $editor ),
            'data' => $this->eventData( $version, $action ),
            'published' => (bool) $version->published,
            'deleted_at' => $this->deleted_at ? (string) $this->deleted_at : null,
            'publish_at' => $version->publish_at,
            'updated_at' => $version->created_at ? (string) $version->created_at : null,
            'tenant' => Tenancy::value(),
            'source' => Utils::source(),
        ];
    }


    /**
     * Dispatches the event after the current transaction commits (immediately when there is none),
     * so a rolled-back change is never broadcast or logged.
     *
     * When broadcasting, broadcast() routes through the event dispatcher (so in-process listeners
     * run too) and toOthers() excludes the originating browser tab; otherwise the event is only
     * dispatched to in-process listeners, with broadcastWhen() false so it is never broadcast.
     *
     * @param Event|Bulk $event Event to dispatch, already built by the caller
     * @param bool $broadcast Whether websocket broadcasting is enabled
     */
    protected static function send( Event|Bulk $event, bool $broadcast ) : void
    {
        if( !$broadcast ) {
            DB::afterCommit( fn() => event( $event ) );
            return;
        }

        $event->broadcasting = true;

        DB::afterCommit( function() use ( $event ) {
            try {
                broadcast( $event )->toOthers();
            } catch( \Exception $e ) {
                report( $e );
            }
        } );
    }
}
