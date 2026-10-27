<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Resource;

use Aimeos\Cms\ContentAction;
use Aimeos\Cms\DB;
use Aimeos\Cms\Exceptions\InvalidInputException;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\LifecycleAction;
use Aimeos\Cms\Invalidation;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Merge;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource\Support\Bulk;
use Aimeos\Cms\Resource\Support\Refs;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Validation;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Aimeos\Cms\Resource\Support\Items;
use Aimeos\Cms\Resource\Support\Langs;
use Aimeos\Cms\Concerns\ChangeChunks;
use Aimeos\Cms\Publication;
use Illuminate\Support\Collection;


/**
 * Operations on shared content elements.
 *
 * Each method checks the permission of its action for the passed user, e.g. "element:save" for
 * save(), and throws a PermissionException if it's missing. Without user (NULL), the call is
 * anonymous and throws too unless it runs inside Permission::system(), e.g. in commands or jobs.
 *
 * Use it via the Aimeos\Cms\Facades\Elements facade, which resolves the instance from the container.
 * Applications can replace it by a subclass overriding the public or protected methods, e.g. with
 * `$this->app->singleton( \Aimeos\Cms\Resource\Elements::class, MyElements::class )` in their service provider.
 *
 * @phpstan-type ElementInput array{type?: string, name?: string|null, lang?: string|null, data?: array<string, mixed>|object|null, ...}
 */
class Elements
{
    use ChangeChunks;


    /**
     * Creates a new element with version and attached files.
     *
     * Files attached to the version are derived from the element's content data.
     *
     * @param ElementInput $input Element fields (type, name, lang, data)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Element
     * @throws InvalidInputException If the input is invalid
     */
    public function add( array $input, ?Authenticatable $user = null ) : Element
    {
        Permission::check( 'element:add', $user );
        Permission::checkLang( $input['lang'] ?? null, $user );

        Validation::limits( Element::class, $input );
        $input['data'] = Validation::element( $input['type'] ?? '', $input['data'] ?? [] );

        $editor = Utils::editor( $user );

        return DB::transaction( function() use ( $input, $editor, $user ) {

            $fileIds = Refs::element( $input, $user );

            $element = new Element();
            $element->setUniqueIds();
            $element->fill( $input );
            $element->editor = $editor;

            // Saves the element with its draft version so the draft (latest=true) row is indexed
            $element->draft( [
                'data' => $element->toArray(),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
            ], ['files' => $fileIds] );

            $element->files()->attach( $fileIds );
            $element->announce( ContentAction::Added, $editor );

            return $element;
        } );
    }


    /**
     * Updates an existing element with a new version.
     *
     * @param string $id Element UUID
     * @param ElementInput $input Changed fields (merged with latest version)
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $latestId Version ID the editor was working on (for conflict detection)
     * @return Element
     * @throws InvalidInputException If the input is invalid
     * @throws ModelNotFoundException If element not found
     */
    public function save( string $id, array $input, ?Authenticatable $user = null, ?string $latestId = null ) : Element
    {
        Permission::check( 'element:save', $user );

        $element = Element::withTrashed()->with( 'latest' )->findOrFail( $id );
        Langs::authorize( $element, $user, $input );

        $type = $input['type'] ?? $element->type;
        Validation::limits( Element::class, $input );

        if( isset( $input['data'] ) ) {
            $input['data'] = Validation::element( $type, $input['data'], isset( $input['type'] ) );
        } elseif( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        $editor = Utils::editor( $user );

        return DB::transaction( function() use ( $element, $input, $editor, $latestId, $user ) {

            $this->apply( $element, $input, $editor, $latestId, $user );
            $element->announce( ContentAction::Saved, $editor );
            PruneVersions::dispatchChunked( Element::class, [$element->id] );

            return $element;
        } );
    }


    /**
     * Applies the same input to several shared content elements at once.
     *
     * @param array<string> $ids Element IDs to update
     * @param ElementInput $input Fields applied to every element (e.g. ['lang' => 'de'])
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return BulkResult
     * @throws InvalidInputException If the input carries the element-specific "type" or "data"
     */
    public function bulk( array $ids, array $input, ?Authenticatable $user = null ) : BulkResult
    {
        Permission::check( 'element:save', $user );

        if( isset( $input['type'] ) || isset( $input['data'] ) ) {
            throw new InvalidInputException( 'Bulk edits cannot change the type or data of an element' );
        }

        Validation::limits( Element::class, $input );

        // an explicit NULL language makes the elements language-neutral
        Permission::checkLang( $input['lang'] ?? null, $user, array_key_exists( 'lang', $input ) );

        return Bulk::save( Element::class, $ids, $input, $user, function( string $id, array $refs, string $editor ) use ( $input, $user ) : ?Element {

            if( !( $element = Element::withTrashed()->with( 'latest' )->lockForUpdate()->find( $id ) ) ) {
                return null;
            }

            Langs::authorize( $element, $user, $input );
            return $this->apply( $element, $input, $editor, null, $user, $refs[$element->latest_id ?? ''] ?? null );
        } );
    }

    /**
     * Publishes or schedules the latest version of the element.
     *
     * @param string $id Element ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return Element Published element
     * @throws ModelNotFoundException If the element doesn't exist
     */
    public function publish( string $id, ?Authenticatable $user = null, ?string $at = null ) : Element
    {
        return Items::one( Element::class, $this->publishMany( [$id], $user, $at ), $id );
    }


    /**
     * Publishes or schedules the latest versions of the elements.
     *
     * @param array<string> $ids Element IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or NULL to publish immediately
     * @return Collection<int, Element> Published elements
     */
    public function publishMany( array $ids, ?Authenticatable $user = null, ?string $at = null ) : Collection
    {
        return Publication::publish( Element::class, $ids, $user, $at );
    }


    /**
     * Moves the element to the trash.
     *
     * @param string $id Element ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Element Trashed element
     * @throws ModelNotFoundException If the element doesn't exist
     */
    public function drop( string $id, ?Authenticatable $user = null ) : Element
    {
        return Items::one( Element::class, $this->dropMany( [$id], $user ), $id );
    }


    /**
     * Permanently deletes the element.
     *
     * @param string $id Element ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Element Deleted element
     * @throws ModelNotFoundException If the element doesn't exist
     */
    public function purge( string $id, ?Authenticatable $user = null ) : Element
    {
        return Items::one( Element::class, $this->purgeMany( [$id], $user ), $id );
    }


    /**
     * Restores the trashed element.
     *
     * @param string $id Element ID
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @return Element Restored element
     * @throws ModelNotFoundException If the element doesn't exist
     */
    public function restore( string $id, ?Authenticatable $user = null ) : Element
    {
        return Items::one( Element::class, $this->restoreMany( [$id], $user ), $id );
    }


    /**
     * Moves elements to the trash.
     *
     * @param array<string> $ids Element IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Element> Trashed elements
     */
    public function dropMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'element:drop', $user );

        $ids = Element::bulkIds( $ids );
        $editor = Utils::editor( $user );

        $items = Scout::mute( [Element::class], fn() => $this->change( Element::class, $ids, $fields, [], function( $items ) use ( $editor ) {
            // dispatched after commit
            Invalidation::refs( array_map( strval( ... ), $items->modelKeys() ) );
            Element::trashMany( $items, $editor );
        }, $user ) );

        Element::announceMany( $items, LifecycleAction::Dropped, $editor, ['deleted_at' => (string) $items->first()?->deleted_at] );

        // the CMS search ignores trashed items, other search engines keep them if soft deletes are enabled
        if( Scout::keepsTrashed() ) {
            Scout::index( Element::class, $this->ids( $items ) );
        } else {
            Scout::unindex( Element::class, $this->ids( $items ) );
        }

        return $items;
    }


    /**
     * Permanently deletes elements.
     *
     * @param array<string> $ids Element IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Element> Deleted elements
     */
    public function purgeMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'element:purge', $user );

        $ids = Element::bulkIds( $ids );
        $editor = Utils::editor( $user );

        $items = Scout::mute( [Element::class], fn() => $this->change( Element::class, $ids, $fields, [], function( $items ) {
            // the references are removed with the elements, pages using trashed elements were invalidated when they were trashed
            Invalidation::refs( array_map( strval( ... ), $items->reject( fn( Element $item ) => $item->trashed() )->modelKeys() ) );
            Element::forceDeleteMany( $items );
        }, $user ) );

        Element::announceMany( $items, LifecycleAction::Purged, $editor );
        Scout::unindex( Element::class, $this->ids( $items ) );

        return $items;
    }


    /**
     * Restores trashed elements.
     *
     * @param array<string> $ids Element IDs
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param array<string> $fields Requested response fields
     * @return Collection<int, Element> Restored elements
     */
    public function restoreMany( array $ids, ?Authenticatable $user = null, array $fields = [] ) : Collection
    {
        Permission::check( 'element:keep', $user );

        $ids = Element::bulkIds( $ids );
        $editor = Utils::editor( $user );

        // all columns are required to update the search index
        $items = Scout::mute( [Element::class], fn() => $this->change( Element::class, $ids, $fields, Element::SELECT_COLUMNS, function( $items ) use ( $editor ) {
            // dispatched after commit
            Invalidation::refs( array_map( strval( ... ), $items->modelKeys() ) );
            Element::restoreMany( $items, $editor );
        }, $user ) );

        Element::announceMany( $items, LifecycleAction::Restored, $editor, ['deleted_at' => null] );
        Scout::index( Element::class, $this->ids( $items ) );

        return $items;
    }


    /**
     * Creates a new version for an already loaded element from the given input.
     *
     * Shared by saveElement() (single) and bulkElement() (bulk). Must run inside a transaction.
     *
     * @param Element $element Element model with the "latest" relation loaded
     * @param array<string, mixed> $input Validated element input (merged with latest version)
     * @param string $editor Name of the editing user
     * @param string|null $latestId Version ID the editor was working on for conflict detection
     * @param Authenticatable|null $user Current user accepting new file references
     * @param array<string, array<string>>|null $refs Prefetched references of the latest version to copy
     * @return Element Updated element with the new version as "latest"
     */
    protected function apply( Element $element, array $input, string $editor,
        ?string $latestId, ?Authenticatable $user, ?array $refs = null ) : Element
    {
        [$data, $dd] = Merge::model( $element, $input, $latestId );

        // without new data, the file references of the previous version are copied
        $element->draft( [
            'data' => $data,
            'editor' => $editor,
            'lang' => $input['lang'] ?? $element->latest?->lang,
        ], array_key_exists( 'data', $input ) ? ['files' => Refs::element( $data, $user )] : $refs, $dd ? ['data' => $dd] : null );

        return $element;
    }


    /**
     * Returns the IDs of the changed items.
     *
     * @param Collection<int, covariant Base> $items Changed items
     * @return array<string> Item IDs
     */
    protected function ids( Collection $items ) : array
    {
        return $items->pluck( 'id' )->map( strval( ... ) )->all();
    }
}
