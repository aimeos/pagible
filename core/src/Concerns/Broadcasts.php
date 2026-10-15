<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\ContentAction;
use Aimeos\Cms\ContentType;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\VariantAction;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Watch;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB;
use Illuminate\Support\Facades\Event as Events;
use Illuminate\Support\Testing\Fakes\EventFake;
use Aimeos\Cms\Models\Base;


/**
 * Lets a model broadcast its own changes over websockets, one event class per action.
 *
 * @phpstan-require-extends \Aimeos\Cms\Models\Base
 * @phpstan-import-type Projection from Event
 */
trait Broadcasts
{
    /**
     * Broadcasts a content change for this model after the current transaction commits.
     *
     * The action names the event class in Aimeos\Cms\Events (ContentAction::Saved -> Saved), broadcast on the
     * per-type list/tree channel and consumed by both the list/tree views and any open detail
     * view of the changed item. toOthers() excludes the originating browser tab via its
     * X-Socket-ID header, while changes from other clients (MCP, API, another tab, a scheduled
     * job) carry no socket id and still reach the open editor.
     *
     * @param ContentAction|LifecycleAction $action Content change or lifecycle action
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @param array{}|Projection $projection Published projection
     */
    public function announce( ContentAction|LifecycleAction $action, Authenticatable|string|null $editor = null,
        array $projection = [] ) : void
    {
        $class = $action->event();

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

        $id = $this->id;
        $latestId = $version->id;

        if( $id === null || $latestId === null ) {
            throw new \LogicException( 'Cannot announce unsaved CMS models.' );
        }

        // Lifecycle changes only alter list metadata, page routes remain because the audit listener records them
        $data = match( true ) {
            $action instanceof ContentAction => (array) $version->data,
            $this instanceof PageView => $this->route( $version ),
            default => [],
        };

        static::send( new $class(
            contentType: static::PERM,
            id: $id,
            latestId: $latestId,
            editor: is_string( $editor ) ? $editor : Utils::editor( $editor ),
            data: $data,
            published: (bool) $version->published,
            deletedAt: $this->deleted_at ? (string) $this->deleted_at : null,
            publishAt: $version->publish_at,
            updatedAt: $version->created_at ? (string) $version->created_at : null,
            tenant: Tenancy::value(),
            source: Watch::source(),
            projection: $projection,
        ) );
    }


    /**
     * Broadcasts a single bulk-edit event for several updated items of one type.
     *
     * A no-op when broadcasting is disabled or nothing was saved.
     *
     * @param ContentType $type Type of the saved items
     * @param list<string> $ids Ids of the saved items
     * @param array<string, string> $latest Saved item id => its new latest version id
     * @param array<string, mixed> $data Shared fields applied to every saved item
     * @param Authenticatable|string|null $editor Authenticated user or editor name
     * @param ContentAction|LifecycleAction|VariantAction $action Action applied to the items
     * @param array<string, string> $projected Item id => actually projected version id
     * @param array<string, string> $langs Item id => language of the page variant
     */
    public static function announceBulk( ContentType $type, array $ids, array $latest, array $data = [],
        Authenticatable|string|null $editor = null, ContentAction|LifecycleAction|VariantAction $action = ContentAction::Saved,
        array $projected = [], array $langs = [] ) : void
    {
        if( empty( $ids ) || !static::announces( Bulk::class ) ) {
            return;
        }

        static::send( new Bulk(
            contentType: $type->value,
            ids: $ids,
            latest: $latest,
            data: $data,
            editor: is_string( $editor ) ? $editor : Utils::editor( $editor ),
            tenant: Tenancy::value(),
            source: Watch::source(),
            action: $action->value,
            projected: $projected,
            langs: $langs,
        ) );
    }


    /**
     * Coalesces notifications while preserving single-item event names.
     *
     * @template T of Base
     * @param Collection<int, T> $items Changed items
     * @param ContentAction|LifecycleAction $action Content change or lifecycle action
     * @param string $editor Editor name
     * @param array<string, mixed> $data Shared changed fields
     * @param bool $bulk TRUE to use the bulk event for a single item too
     * @param array<string, Projection> $projected Published projections by version key
     */
    public static function announceMany( Collection $items, ContentAction|LifecycleAction $action, string $editor,
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
        $groups = $items->groupBy( fn( $item ) => (string) $item->eventLang() );

        foreach( $groups as $group )
        {
            foreach( $group->chunk( Base::CHUNK ) as $chunk )
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
                    if( $action instanceof ContentAction && ( $lang = $item->eventLang() ) !== null ) {
                        $langs[$id] = $lang;
                    }
                }

                static::announceBulk( ContentType::from( $first::PERM ), $ids, $latest, $data, $editor, $action, projected: $versions, langs: $langs );
            }
        }
    }


    /**
     * Returns whether an event needs to be built for broadcasting, an in-process listener or Event::fake().
     *
     * @param class-string<Event|Bulk> $event
     */
    public static function announces( string $event ) : bool
    {
        return (bool) config( 'cms.broadcast' ) || Events::hasListeners( $event )
            || Events::getFacadeRoot() instanceof EventFake;
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
            DB::connection()->afterCommit( fn() => event( $event ) );
            return;
        }

        $event->broadcasting = true;

        DB::connection()->afterCommit( function() use ( $event ) {
            try {
                broadcast( $event )->toOthers();
            } catch( \Exception $e ) {
                report( $e );
            }
        } );
    }
}
