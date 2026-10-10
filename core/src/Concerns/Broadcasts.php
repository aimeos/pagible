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
     * @param array{}|array{version_id: string, path?: string, domain?: string, lang?: string} $projection Published projection
     * @throws \InvalidArgumentException If $action has no matching event class
     */
    public function announce( string $action, Authenticatable|string|null $editor = null,
        array $projection = [] ) : void
    {
        $class = 'Aimeos\\Cms\\Events\\' . ucfirst( $action );

        if( !is_subclass_of( $class, Event::class ) ) {
            throw new \InvalidArgumentException( "Unknown broadcast action: {$action}" );
        }

        // In-process listeners (audit logging) subscribe to the per-action events;
        // only do work when broadcasting is on or something listens. This
        // also avoids the per-item latest lazy load (e.g. on purge) when nothing is enabled.
        if( !static::announces( $class ) ) {
            return;
        }

        if( $this->relationLoaded( 'latest' ) ) {
            $loaded = $this->getRelation( 'latest' );

            if( !$loaded instanceof Version || (string) $loaded->id !== (string) $this->latest_id ) {
                $this->unsetRelation( 'latest' );
            }
        }

        if( !( $version = $this->latest ) ) {
            return;
        }

        static::send( new $class(
            ...$this->eventFields( $version, $editor, $action, $projection )
        ) );
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
     * @param array<string, string> $projected Item id => actually projected version id
     * @param array<string, string> $langs Item id => language of the page variant
     */
    public static function announceBulk( string $type, array $ids, array $latest, array $data,
        Authenticatable|string|null $editor = null, string $action = 'bulk', array $projected = [], array $langs = [] ) : void
    {
        if( empty( $ids ) || !static::announces( Bulk::class ) ) {
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
            projected: $projected,
            langs: $langs,
        ) );
    }


    /**
     * Coalesces notifications while preserving single-item event names.
     *
     * @template T of \Aimeos\Cms\Models\Base
     * @param Collection<int, T> $items Changed items
     * @param string $action Past-tense lifecycle action
     * @param string $editor Editor name
     * @param array<string, mixed> $data Shared changed fields
     * @param bool $bulk TRUE to use the bulk event for a single item too
     * @param array<string, array{version_id: string, path?: string, domain?: string, lang?: string}> $projected Published projections by version key
     */
    public static function announceMany( Collection $items, string $action, string $editor,
        array $data = [], bool $bulk = false, array $projected = [] ) : void
    {
        if( !( $first = $items->first() ) ) {
            return;
        }

        if( $items->count() === 1 && !$bulk ) {
            $id = $first->id;
            $first->announce( $action, $editor, is_string( $id ) ? ( $projected[$first->getVersionKey() ?? $id] ?? [] ) : [] );
            return;
        }

        // page variants share the page ID, so each event contains the variants of one language only
        $groups = $first instanceof Page
            ? $items->groupBy( fn( $item ) => (string) $item->getAttribute( 'lang' ) )
            : collect( [$items] );

        foreach( $groups as $group )
        {
            foreach( $group->chunk( 50 ) as $chunk )
            {
                $ids = $latest = $versions = $langs = [];

                foreach( $chunk as $item )
                {
                    $id = (string) $item->id;
                    $ids[] = $id;
                    $latest[$id] = (string) $item->latest_id;

                    if( isset( $projected[$key = $item->getVersionKey() ?? $id] ) ) {
                        $versions[$id] = $projected[$key]['version_id'];
                    }

                    // lifecycle events of whole pages have no language, only those of single page variants
                    if( $item instanceof Page && !in_array( $action, ['dropped', 'restored', 'purged'], true ) ) {
                        $langs[$id] = (string) $item->lang;
                    }
                }

                static::announceBulk( strtolower( class_basename( $first ) ), $ids, $latest, $data, $editor, $action, $versions, $langs );
            }
        }
    }


    /**
     * Returns whether an event needs to be built for broadcasting or an in-process listener.
     *
     * @param class-string<Event|Bulk> $event
     */
    public static function announces( string $event ) : bool
    {
        return (bool) config( 'cms.broadcast' ) || Events::hasListeners( $event );
    }


    /**
     * Extracts the shared event fields from the model and version, keyed by the event constructor
     * parameter names so they can be spread into any event.
     *
     * @param Version $version Latest version of the model
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @param string $action Past-tense action
     * @param array{}|array{version_id: string, path?: string, domain?: string, lang?: string} $projection Published projection
     * @return array{contentType: string, id: string, latest_id: string, editor: string, data: array<string, mixed>, published: bool, deleted_at: string|null, publish_at: string|null, updated_at: string|null, tenant: string, source: string, projection: array{}|array{version_id: string, path?: string, domain?: string, lang?: string}}
     */
    protected function eventFields( Version $version, Authenticatable|string|null $editor,
        string $action, array $projection = [] ) : array
    {
        $id = $this->id;
        $latestId = $version->id;

        if( $id === null || $latestId === null ) {
            throw new \LogicException( 'Cannot announce unsaved CMS models.' );
        }

        // Lifecycle changes only alter list metadata, page routes remain because the audit listener records them
        $data = match( true ) {
            !in_array( $action, ['dropped', 'purged', 'restored'], true ) => (array) $version->data,
            $this instanceof Page => $this->route( $version ),
            default => [],
        };

        return [
            'contentType' => strtolower( class_basename( $this ) ),
            'id' => $id,
            'latest_id' => $latestId,
            'editor' => is_string( $editor ) ? $editor : Utils::editor( $editor ),
            'data' => $data,
            'published' => (bool) $version->published,
            'deleted_at' => $this->deleted_at ? (string) $this->deleted_at : null,
            'publish_at' => $version->publish_at,
            'updated_at' => $version->created_at ? (string) $version->created_at : null,
            'tenant' => Tenancy::value(),
            'source' => Utils::source(),
            'projection' => $projection,
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
     */
    protected static function send( Event|Bulk $event ) : void
    {
        if( !config( 'cms.broadcast' ) ) {
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
