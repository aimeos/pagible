<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageQuery;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Publication\References;
use Aimeos\Cms\Resource\Pages;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Database\Query\Builder;


/**
 * Holds bounded shared state while publishing related CMS models.
 *
 * @phpstan-import-type Projection from Event
 */
final class Publication
{

    /**
     * Version columns loaded together with the models.
     */
    private const LATEST_COLUMNS = [
        'id',
        'tenant_id',
        'versionable_id',
        'versionable_type',
        'published',
        'publish_at',
        'lang',
        'data',
        'aux',
        'editor',
        'created_at',
    ];

    /** @var array<string, Element> */
    private array $elements = [];

    /** @var array<class-string<Base>, array<string, Base>> Models by version key, pages per variant */
    private array $models = [];

    /** @var array<class-string<Base>, array<string, Projection>> */
    private array $projected = [];

    private References $references;

    /** @var array<string, Version> */
    private array $versions = [];



    /**
     * Initializes the publication state.
     */
    public function __construct()
    {
        $this->references = new References();
    }


    /**
     * Applies a prepared version and records its publication effects.
     */
    public function apply( Base $model, Version $version ) : void
    {
        if( $version->published ) {
            return;
        }

        $moved = self::moved( $model );

        $model->stage( $version );
        $model instanceof PageView ? Pages::saveVariant( $model ) : $model->save();

        $version->published = true;
        $version->save();

        $this->track( $model, $version );

        if( $moved ) {
            $moved();
        }
    }


    /**
     * Dispatches accumulated search updates and publication events.
     */
    public function flush( ?string $editor = null ) : void
    {
        // Pages show the published content of shared elements and files
        Invalidation::refs(
            array_map( strval( ... ), array_keys( $this->models[Element::class] ?? [] ) ),
            array_map( strval( ... ), array_keys( $this->models[File::class] ?? [] ) ),
        );

        // translations whose source changed need an update
        Sync::markStale( Pages::views( $this->models[PageView::class] ?? [] ) );

        foreach( $this->models as $model => $items ) {
            // only the published variants of pages change
            Scout::index( $model, array_map( fn( Base $item ) => (string) $item->getScoutKey(), array_values( $items ) ), keys: true );
        }

        foreach( $this->models as $model => $items ) {
            Base::announceMany( collect( array_values( $items ) ), ContentAction::Published, $editor ?? '', [
                'published' => true,
                'publish_at' => null,
            ], projected: $this->projected[$model] ?? [] );
        }

        $this->elements = [];
        $this->models = [];
        $this->projected = [];
    }


    /**
     * Merges successfully committed publication effects.
     */
    public function merge( self $publication ) : void
    {
        foreach( $publication->models as $model => $items ) {
            $this->models[$model] = ( $this->models[$model] ?? [] ) + $items;
        }

        foreach( $publication->projected as $model => $items ) {
            $this->projected[$model] = ( $this->projected[$model] ?? [] ) + $items;
        }
    }


    /**
     * Publishes one model using a self-contained publication context.
     */
    public function one( Base $model, Version $version ) : void
    {
        if( !$version->exists
            || (string) $version->versionable_id !== (string) $model->getVersionKey()
            || (string) $version->versionable_type !== (string) $model->getMorphClass()
        ) {
            throw new \LogicException( 'CMS version does not belong to the model.' );
        }

        if( $version->published ) {
            return;
        }

        File::locked( fn() =>
            Scout::mute( Version::TYPES, function() use ( $model, $version ) {
                $this->prepare( collect( [$version] ) );
                $this->apply( $model, $version );
            } ),
        );

        $this->flush( (string) $version->editor );
    }


    /**
     * Prepares and publishes dependencies for a bounded root-version chunk.
     *
     * @param Collection<int, Version> $versions
     */
    public function prepare( Collection $versions, ?Authenticatable $user = null ) : void
    {
        $this->elements = [];

        $refs = $this->references;
        $refs->reset();
        $owners = $refs->load( $versions );

        $refs->filter( $owners, 'files', $this->publishFiles( $refs->related( $owners, 'files' ), $user ) );
        $refs->filter( $owners, 'elements', $this->publishElements( $refs->related( $owners, 'elements' ), $user ) );
        $refs->sync( $owners );
    }


    /**
     * Publishes or schedules the latest versions of the given models.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or null to publish immediately
     * @param string|null $lang Language of the page variants or null for the source variants
     * @return Collection<int, Base>
     */
    public static function publish( string $model, array $ids, ?Authenticatable $user = null, ?string $at = null,
        ?string $lang = null ) : Collection
    {
        Validation::publishAt( $at );

        if( !in_array( $model, Version::TYPES, true ) ) {
            throw new \InvalidArgumentException( 'Invalid CMS model: ' . $model );
        }

        Permission::check( $model::PERM . ':publish', $user );

        $ids = $model::bulkIds( $ids );
        $editor = Utils::editor( $user );
        $publication = new self();

        $publish = fn() => DB::transaction( function() use ( $at, $editor, $ids, $lang, $model, $publication, $user ) {
            $items = $pending = collect();

            foreach( array_chunk( $ids, Base::CHUNK ) as $chunk )
            {
                $loaded = self::items( $model, $chunk, (bool) $at, $lang );
                $pending = $pending->concat( $publication->publishItems( $loaded, $user, $at, $editor ) );
                $items = $items->concat( $loaded );
            }

            return [$items, $pending];
        } );

        [$items, $pending] = File::locked( fn() => $at ? $publish() : Scout::mute( Version::TYPES, $publish ) );

        if( !$at ) {
            $publication->flush( $editor );
        } else {
            Base::announceMany( $pending, ContentAction::Published, $editor, [
                'published' => false,
                'publish_at' => $at,
            ] );
        }

        return $items;
    }


    /**
     * Applies and persists a prepared model/version batch with set-based writes.
     *
     * @param array<int, array{Base, Version}> $items
     */
    private function applyAll( array $items ) : void
    {
        $groups = [];
        $sources = [];

        foreach( $items as [$model, $version] )
        {
            $moved = self::moved( $model );

            $model->stage( $version );
            $columns = array_keys( $model->getDirty() );

            // the source language follows the language of the source variant
            if( $model instanceof PageView && in_array( 'lang', $columns, true )
                && $model->getRawOriginal( 'lang' ) === $model->getRawOriginal( 'source' )
            ) {
                $sources[] = $model;
            }

            if( $update = self::dirtyRow( $model, $columns ) )
            {
                [$table, $cols, $row] = $update;
                $key = $model::class . '|' . implode( '|', $cols );

                $groups[$key]['table'] = $table;
                $groups[$key]['columns'] = $cols;
                $groups[$key]['rows'][] = $row;
            }

            $version->published = true;
            $this->versions[(string) $version->id] = $version;

            $this->track( $model, $version );

            if( $moved ) {
                $moved();
            }
        }

        foreach( $groups as $group ) {
            DB::updateRows( $group['table'], $group['rows'], $group['columns'] );
        }

        foreach( $sources as $page )
        {
            Page::withTrashed()->whereKey( $page->id )->toBase()->update( ['source' => $page->lang] );
            $page->setAttribute( 'source', $page->lang );
        }

        foreach( $items as [$model, $_] ) {
            $model->syncChanges()->syncOriginal();
        }
    }


    /**
     * Returns the invalidation of the previous URL used after the model has been published.
     *
     * @return \Closure|null Invalidates the URL of the page if it changed or NULL for other models
     */
    private static function moved( Base $model ) : ?\Closure
    {
        if( !$model instanceof PageView ) {
            return null;
        }

        $domain = (string) $model->domain;
        $path = (string) $model->path;

        return fn() => Invalidation::moved( $model, $domain, $path );
    }


    /**
     * Returns the update row for the dirty columns of a staged model.
     *
     * @param array<string> $columns Dirty columns
     * @return array{0: string, 1: array<string>, 2: array<string, mixed>}|null Table, columns and row or NULL if unchanged
     */
    private static function dirtyRow( Base $model, array $columns ) : ?array
    {
        $updated = $model->getUpdatedAtColumn();

        if( $columns && $model->usesTimestamps() && $updated !== null )
        {
            $model->setUpdatedAt( $model->freshTimestamp() );
            $columns[] = $updated;
        }

        $columns = array_values( array_unique( $columns ) );
        sort( $columns, SORT_STRING );

        if( !$columns ) {
            return null;
        }

        $attributes = $model->getAttributes();
        $row = ['id' => (string) $model->id];

        foreach( $columns as $column ) {
            $row[$column] = $attributes[$column] ?? null;
        }

        return $model->publishedRow( $row, $columns );
    }


    /**
     * Verifies publication rights for all dependencies without changing them.
     *
     * @param Collection<int, Version> $versions
     */
    private function authorize( Collection $versions, Authenticatable $user ) : void
    {
        $canElements = Permission::can( 'element:publish', $user );
        $canFiles = Permission::can( 'file:publish', $user );

        if( $canElements && $canFiles ) {
            return;
        }

        $this->references->reset();
        $owners = $this->references->load( $versions );
        $elements = $this->references->related( $owners, 'elements' );
        $files = $this->references->related( $owners, 'files' );

        if( ( $elements && ( !$canElements || !$canFiles ) )
            || ( $files && !$canFiles ) ) {
            throw new Exception( 'Insufficient permissions' );
        }
    }


    /**
     * Loads models and their latest versions in one portable joined query.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param string|null $lang Language of the page variants or null for the source variants
     * @return Collection<int, Base>
     */
    private static function items( string $model, array $ids, bool $compact = false, ?string $lang = null ) : Collection
    {
        $instance = new $model();
        $table = $instance->getTable();
        $query = $instance->newQuery();

        if( $lang !== null && $query instanceof PageQuery ) {
            $query->language( $lang );
        }

        $query->select( $compact ? ["{$table}.id", "{$table}.latest_id"] : ["{$table}.*"] )
            ->leftJoin( 'cms_versions AS cms_latest', function( $join ) use ( $table ) {
                $join->on( "{$table}.latest_id", '=', 'cms_latest.id' )
                    ->where( 'cms_latest.tenant_id', Tenancy::value() );
            } )
            ->whereIn( "{$table}.id", $ids );

        foreach( self::LATEST_COLUMNS as $column ) {
            $query->addSelect( "cms_latest.{$column} AS pub_{$column}" );
        }

        $query->addSelect( self::flags( $instance, $compact ) );

        $items = $query->get();

        foreach( $items as $item ) {
            $item->setRelation( 'latest', self::latest( $item ) );
        }

        return $items;
    }


    /**
     * Returns the subqueries projecting the presence of active and target pivot rows.
     *
     * @return array<string, Builder> Subqueries by alias
     */
    private static function flags( Base $instance, bool $compact ) : array
    {
        $db = DB::connection();
        $table = $instance->getTable();
        $first = fn( string $from, string $col, string $ref ) => $db->table( $from )->select( $col )->whereColumn( $col, $ref )->limit( 1 );
        $selects = [];

        if( !$compact && $instance instanceof PageView )
        {
            $selects['access_count'] = $db->table( 'cms_page_access' )
                ->selectRaw( 'count(*)' )
                ->whereColumn( 'page_id', "{$table}.id" )
                ->where( 'tenant_id', Tenancy::value() );
        }

        $version = new Version();

        foreach( References::pivots( $instance ) as $name => $relation )
        {
            if( !$compact ) {
                $selects["pub_active_{$name}"] = $first( $relation->getTable(), $relation->getForeignPivotKeyName(), $relation->getQualifiedParentKeyName() );
            }

            $target = $version->{$name}();
            $selects["pub_target_{$name}"] = $first( $target->getTable(), $target->getForeignPivotKeyName(), 'cms_latest.id' );
        }

        return $selects;
    }


    /**
     * Moves the joined latest version columns of the item into a version model.
     *
     * @return Version|null Latest version or NULL if the item has none
     */
    private static function latest( Base $item ) : ?Version
    {
        $attributes = [];

        foreach( self::LATEST_COLUMNS as $column )
        {
            $attributes[$column] = $item->getAttribute( "pub_{$column}" );
            $item->offsetUnset( "pub_{$column}" );
        }

        if( $attributes['id'] === null ) {
            return null;
        }

        $version = ( new Version() )->newFromBuilder( $attributes, $item->getConnectionName() );

        foreach( ['active_files', 'active_elements', 'target_files', 'target_elements'] as $name )
        {
            $attribute = "pub_{$name}";

            if( array_key_exists( $attribute, $item->getAttributes() ) )
            {
                $version->setAttribute( $name, $item->getAttribute( $attribute ) );
                $item->offsetUnset( $attribute );
            }
        }

        return $version;
    }


    /**
     * Publishes referenced elements and their file dependencies in bounded chunks.
     *
     * @param array<string> $ids
     * @return array<string, Element> Existing elements by ID
     */
    private function publishElements( array $ids, ?Authenticatable $user = null ) : array
    {
        $existing = $this->publishRelated( Element::class, $ids, $user, function( array $items ) use ( $user ) {

            $refs = $this->references;
            $owners = $refs->load( collect( array_column( $items, 1 ) ) );
            $refs->filter( $owners, 'files', $this->publishFiles( $refs->related( $owners, 'files' ), $user ) );
            $refs->sync( $owners );
            $this->applyAll( $items );
            $refs->forget( $owners );
        } );

        $this->elements = array_replace( $this->elements, $existing );
        return $existing;
    }


    /**
     * Publishes referenced files in bounded, deduplicated chunks.
     *
     * @param array<string> $ids
     * @return array<string, File> Existing files by ID
     */
    private function publishFiles( array $ids, ?Authenticatable $user = null ) : array
    {
        return $this->publishRelated( File::class, $ids, $user, fn( array $items ) => $this->applyAll( $items ) );
    }


    /**
     * Publishes the unpublished latest versions of the referenced items in bounded, deduplicated chunks.
     *
     * @template T of Element|File
     * @param class-string<T> $model Model class of the items
     * @param array<string> $ids
     * @param \Closure(array<int, array{T, Version}>) : void $apply Applies the unpublished items of a chunk
     * @return array<string, T> Existing items by ID
     */
    private function publishRelated( string $model, array $ids, ?Authenticatable $user, \Closure $apply ) : array
    {
        $existing = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), Base::CHUNK ) as $chunk )
        {
            $items = [];

            foreach( self::items( $model, $chunk )->whereInstanceOf( $model ) as $item )
            {
                $existing[(string) $item->id] = $item;

                if( ( $version = $item->latest ) && !$version->published ) {
                    $items[] = [$item, $version];
                }
            }

            Permission::check( $model::PERM . ':publish', $user, (bool) $items );
            $apply( $items );
        }

        return $existing;
    }


    /**
     * Publishes or schedules the unpublished latest versions of the loaded items.
     *
     * @param Collection<int, Base> $items Items with the "latest" relation loaded
     * @param Authenticatable|null $user Authenticated user or NULL for system calls
     * @param string|null $at Date/time to publish at or NULL to publish immediately
     * @param string $editor Name of the editing user
     * @return Collection<int, Base> Items whose latest versions were unpublished
     */
    private function publishItems( Collection $items, ?Authenticatable $user, ?string $at, string $editor ) : Collection
    {
        $unpublished = $items->filter( fn( Base $item ) => $item->latest && !$item->latest->published )->values();

        if( $unpublished->isEmpty() ) {
            return $unpublished;
        }

        $versions = $unpublished->pluck( 'latest' );

        if( $at )
        {
            if( $user ) {
                $this->authorize( $versions, $user );
            }

            $this->schedule( $unpublished, $at, $editor );
            return $unpublished;
        }

        $this->prepare( $versions, $user );
        $this->applyAll( $unpublished->map( fn( Base $item ) => [
            $item, $item->latest ?? throw new \LogicException( 'Unpublished model has no latest version.' )
        ] )->all() );
        $this->publishVersions();

        return $unpublished;
    }


    /**
     * Marks all versions prepared in the current root chunk as published.
     */
    private function publishVersions() : void
    {
        foreach( array_chunk( array_keys( $this->versions ), DB::IN_SIZE ) as $ids ) {
            Version::whereIn( 'id', $ids )->update( ['published' => true] );
        }

        foreach( $this->versions as $version ) {
            $version->syncChanges()->syncOriginal();
        }

        $this->versions = [];
    }


    /**
     * Schedules the loaded latest versions with one set-based update.
     *
     * @param Collection<int, covariant Base> $items
     */
    private function schedule( Collection $items, string $at, string $editor ) : void
    {
        $rows = [];
        $versions = [];

        foreach( $items as $item )
        {
            if( !( $version = $item->latest ) || $version->published ) {
                continue;
            }

            $data = $version->data;
            $data->scheduled = 1;
            $version->data = $data;
            $version->publish_at = $at;
            $version->editor = $editor;

            $attributes = $version->getAttributes();
            $rows[] = [
                'id' => $version->id,
                'data' => $attributes['data'],
                'editor' => $attributes['editor'],
                'publish_at' => $attributes['publish_at'],
            ];
            $versions[] = $version;
        }

        DB::updateRows( ( new Version() )->getTable(), $rows, ['data', 'editor', 'publish_at'] );

        foreach( $versions as $version ) {
            $version->syncChanges()->syncOriginal();
        }
    }


    /**
     * Retains a changed model and fills relations already resolved during publication.
     */
    private function track( Base $model, Version $version ) : void
    {
        // pages are published per variant, several variants of a page can be published together
        $id = (string) ( $model->getVersionKey() ?? $model->id );
        $versionId = (string) $version->id;
        $projection = ['version_id' => $versionId];

        if( $model instanceof PageView )
        {
            $projection += $model->route( $version ) + ['lang' => (string) ( $version->data->lang ?? $model->lang )];

            $elements = [];

            foreach( $this->references->elements( $versionId ) as $elementId ) {
                if( isset( $this->elements[$elementId] ) ) {
                    $elements[] = $this->elements[$elementId];
                }
            }

            $items = ( new Element() )->newCollection( $elements );
            $model->setRelation( 'elements', $items );
            $version->setRelation( 'elements', $items );
        }

        $this->models[$model::class][$id] = $model;
        $this->projected[$model::class][$id] = $projection;
    }
}
