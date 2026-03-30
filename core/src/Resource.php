<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;


class Resource
{
    /**
     * Publishes or schedules items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @param string|null $at ISO 8601 datetime to schedule publication
     * @param array<string> $with Eager-load relations
     * @return Collection<int, Base>
     */
    public static function publish( string $model, array $ids, string $editor, ?string $at = null, array $with = ['latest'] ) : Collection
    {
        return Utils::transaction( function() use ( $model, $ids, $editor, $at, $with ) {

            $items = $model::with( $with )->whereIn( 'id', $ids )->get();

            foreach( $items as $item )
            {
                if( $latest = $item->latest )
                {
                    if( $at )
                    {
                        $latest->publish_at = $at;
                        $latest->editor = $editor;
                        $latest->save();
                    }
                    else
                    {
                        $item->publish( $latest );
                    }
                }
            }

            return $items;
        } );
    }


    /**
     * Soft-deletes items by ID.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @return Collection<int, Base>
     */
    public static function drop( string $model, array $ids, string $editor ) : Collection
    {
        return Utils::transaction( function() use ( $model, $ids, $editor ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

            foreach( $items as $item )
            {
                $item->editor = $editor;
                $item->delete();

                if( $item instanceof Page ) {
                    Cache::forget( Page::key( $item ) );
                }
            }

            return $items;
        } );
    }


    /**
     * Restores soft-deleted items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string $editor
     * @return Collection<int, Base>
     */
    public static function restore( string $model, array $ids, string $editor ) : Collection
    {
        $callback = function() use ( $model, $ids, $editor ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

            foreach( $items as $item )
            {
                $item->editor = $editor;
                $item->restore();
            }

            return $items;
        };

        return is_a( $model, Page::class, true )
            ? Utils::lockedTransaction( $callback )
            : Utils::transaction( $callback );
    }


    /**
     * Permanently deletes items by ID.
     *
     * Uses a cache-locked transaction for Page models to protect tree integrity.
     * Calls purge() on File models to clean up storage.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @return Collection<int, Base>
     */
    public static function purge( string $model, array $ids ) : Collection
    {
        $callback = function() use ( $model, $ids ) {

            $items = $model::withTrashed()->whereIn( 'id', $ids )->get();

            foreach( $items as $item )
            {
                if( $item instanceof File ) {
                    $item->purge();
                } else {
                    $item->forceDelete();
                }

                if( $item instanceof Page ) {
                    Cache::forget( Page::key( $item ) );
                }
            }

            return $items;
        };

        return is_a( $model, Page::class, true )
            ? Utils::lockedTransaction( $callback )
            : Utils::transaction( $callback );
    }


    /**
     * Creates a new page with version and attached relations.
     *
     * @param array<string, mixed> $input Page fields (content/meta/config go into version aux)
     * @param mixed $user Authenticated user for permission-based validation
     * @param string $editor Editor identifier for tracking
     * @param array<string> $files File IDs to attach
     * @param array<string> $elements Element IDs to attach
     * @param string|null $ref Sibling page ID to insert before
     * @param string|null $parent Parent page ID to append to
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addPage( array $input, mixed $user, string $editor,
        array $files = [], array $elements = [],
        ?string $ref = null, ?string $parent = null ) : Page
    {
        $input = Validation::page( $input, $user );

        return Utils::lockedTransaction( function() use ( $input, $editor, $files, $elements, $ref, $parent ) {

            $versionId = ( new Version )->newUniqueId();

            $page = new Page();
            $page->fill( $input );
            $page->tenant_id = Tenancy::value();
            $page->editor = $editor;

            self::position( $page, $ref, $parent );

            $page->latest_id = $versionId;
            $page->save();

            $page->files()->attach( $files );
            $page->elements()->attach( $elements );

            $data = array_diff_key( $input, array_flip( ['config', 'content', 'meta'] ) );

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
                'aux' => [
                    'meta' => $input['meta'] ?? new \stdClass(),
                    'config' => $input['config'] ?? new \stdClass(),
                    'content' => $input['content'] ?? [],
                ]
            ] );

            $version->elements()->attach( $elements );
            $version->files()->attach( $files );

            return $page->setRelation( 'latest', $version );
        } );
    }


    /**
     * Updates an existing page with a new version.
     *
     * @param string $id Page UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param mixed $user Authenticated user for permission-based validation
     * @param string $editor Editor identifier for tracking
     * @param array<string> $files File IDs to attach to version
     * @param array<string> $elements Element IDs to attach to version
     * @return Page
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If page not found
     */
    public static function savePage( string $id, array $input, mixed $user, string $editor,
        array $files = [], array $elements = [] ) : Page
    {
        $input = Validation::page( $input, $user );

        return Utils::transaction( function() use ( $id, $input, $editor, $files, $elements ) {

            /** @var Page $page */
            $page = Page::withTrashed()->with( 'latest' )->findOrFail( $id );
            $versionId = ( new Version )->newUniqueId();

            $data = array_diff_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            array_walk( $data, fn( &$v, $k ) => $v = !in_array( $k, ['related_id'] ) ? ( $v ?? '' ) : $v );
            $data = array_replace( (array) $page->latest?->data, $data );

            $aux = array_intersect_key( $input, array_flip( ['meta', 'config', 'content'] ) );
            $aux = array_replace( (array) $page->latest?->aux, $aux );

            $version = $page->versions()->forceCreate( [
                'id' => $versionId,
                'data' => $data,
                'editor' => $editor,
                'lang' => $input['lang'] ?? $page->latest?->lang,
                'aux' => $aux,
            ] );

            $version->elements()->attach( $elements );
            $version->files()->attach( $files );

            $page->forceFill( ['latest_id' => $version->id] )->save();

            $page->setRelation( 'latest', $version );
            return $page->removeVersions();
        } );
    }


    /**
     * Creates a new element with version and attached files.
     *
     * @param array<string, mixed> $input Element fields (type, name, lang, data)
     * @param string $editor Editor identifier for tracking
     * @param array<string> $files File IDs to attach
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     */
    public static function addElement( array $input, string $editor, array $files = [] ) : Element
    {
        Validation::element( $input['type'] ?? '' );

        if( isset( $input['data'] ) ) {
            Validation::html( $input['type'] ?? '', $input['data'] );
        }

        return Utils::transaction( function() use ( $input, $editor, $files ) {

            $versionId = ( new Version )->newUniqueId();

            $element = new Element();
            $element->fill( $input );
            $element->data = $input['data'] ?? [];
            $element->tenant_id = Tenancy::value();
            $element->latest_id = $versionId;
            $element->editor = $editor;
            $element->save();

            $element->files()->attach( $files );

            $data = $input;
            ksort( $data );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => is_null( $v ) ? (string) $v : $v, $data ),
                'lang' => $input['lang'] ?? null,
                'editor' => $editor,
            ] );

            $version->files()->attach( $files );

            return $element->setRelation( 'latest', $version );
        } );
    }


    /**
     * Updates an existing element with a new version.
     *
     * @param string $id Element UUID
     * @param array<string, mixed> $input Changed fields (merged with latest version)
     * @param string $editor Editor identifier for tracking
     * @param array<string> $files File IDs to attach to version
     * @return Element
     * @throws \InvalidArgumentException On validation failure
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If element not found
     */
    public static function saveElement( string $id, array $input, string $editor, array $files = [] ) : Element
    {
        /** @var Element $element */
        $element = Element::withTrashed()->with( 'latest' )->findOrFail( $id );
        $type = $input['type'] ?? $element->type ?? ( (array) ( $element->latest->data ?? [] ) )['type'] ?? '';

        if( isset( $input['type'] ) ) {
            Validation::element( $type );
        }

        if( isset( $input['data'] ) ) {
            Validation::html( $type, $input['data'] );
        }

        return Utils::transaction( function() use ( $element, $input, $editor, $files ) {

            $versionId = ( new Version )->newUniqueId();

            $data = array_replace( (array) ( $element->latest->data ?? [] ), $input );

            $version = $element->versions()->forceCreate( [
                'id' => $versionId,
                'data' => array_map( fn( $v ) => $v ?? '', $data ),
                'editor' => $editor,
                'lang' => $input['lang'] ?? $element->latest?->lang,
            ] );

            $version->files()->attach( $files );
            $element->forceFill( ['latest_id' => $version->id] )->save();

            $element->setRelation( 'latest', $version );
            return $element->removeVersions();
        } );
    }


    /**
     * Positions a page in the tree relative to a sibling or parent.
     *
     * @param Page $page The page to position
     * @param string|null $beforeId ID of sibling to insert before
     * @param string|null $parentId ID of parent to append to
     * @param bool $root Whether to make the page a root node when no ref/parent given
     */
    public static function position( Page $page, ?string $beforeId = null, ?string $parentId = null, bool $root = false ) : void
    {
        if( $beforeId ) {
            $ref = Page::withTrashed()->findOrFail( $beforeId );
            $page->beforeNode( $ref );
        } elseif( $parentId ) {
            $parent = Page::withTrashed()->findOrFail( $parentId );
            $page->appendToNode( $parent );
        } elseif( $root ) {
            $page->makeRoot();
        }
    }
}
