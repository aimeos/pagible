<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


/**
 * Holds bounded shared state while publishing related CMS models.
 */
final class Publication
{
    /** @var array<string, Element> */
    private array $elements = [];

    /** @var array<class-string<Base>, array<string, Base>> */
    private array $models = [];

    /**
     * @var array<string, array{
     *     files: array<string>,
     *     elements: array<string>,
     *     active_files: bool|null,
     *     active_elements: bool|null
     * }>
     */
    private array $refs = [];

    /** @var array<int, array{domain: string, path: string}> */
    private array $routes = [];

    /** @var array<string, Version> */
    private array $versions = [];

    private int $work = 0;


    /**
     * Applies a prepared version and records its publication effects.
     */
    public function apply( Base $model, Version $version ) : void
    {
        if( $version->published ) {
            return;
        }

        $route = $model instanceof Page ? [
            'domain' => $model->domain,
            'path' => $model->path,
        ] : null;

        $model->stage( $version );
        $model->save();

        if( !$version->published ) {
            $version->published = true;
            $version->save();
        }

        $this->track( $model, $version );

        if( $route ) {
            $this->routes[] = $route;
            $this->routes[] = [
                'domain' => $model->domain,
                'path' => $model->path,
            ];
        }
    }


    /**
     * Dispatches accumulated route invalidations and search updates.
     */
    public function flush() : void
    {
        if( $this->routes ) {
            PagesInvalidated::dispatch( $this->routes );
        }

        foreach( $this->models as $model => $items ) {
            Scout::index( $model, array_keys( $items ), collect( array_values( $items ) ) );
        }

        $this->elements = [];
        $this->models = [];
        $this->routes = [];
    }


    /**
     * Merges successfully committed publication effects.
     */
    public function merge( self $publication ) : void
    {
        foreach( $publication->models as $model => $items ) {
            $this->models[$model] = ( $this->models[$model] ?? [] ) + $items;
        }

        array_push( $this->routes, ...$publication->routes );
    }


    /**
     * Publishes one model using a self-contained publication context.
     */
    public function one( Base $model, Version $version ) : void
    {
        if( $version->published ) {
            return;
        }

        Scout::mute( Version::TYPES, function() use ( $model, $version ) {
            $this->prepare( collect( [$version] ) );
            $this->apply( $model, $version );
        } );

        $this->flush();
    }


    /**
     * Prepares and publishes dependencies for a bounded root-version chunk.
     *
     * @param Collection<int, Version> $versions
     */
    public function prepare( Collection $versions, ?Authenticatable $user = null ) : void
    {
        $this->elements = [];
        $this->refs = [];

        $owners = $this->references( $versions );

        $this->filter( $owners, 'files', $this->publishFiles( $this->related( $owners, 'files' ), $user ) );
        $this->filter( $owners, 'elements', $this->publishElements( $this->related( $owners, 'elements' ), $user ) );
        $this->sync( $owners );
    }


    /**
     * Publishes or schedules the latest versions of the given models.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @param Authenticatable|null $user Authenticated user for editor tracking
     * @param string|null $at Publication date or null to publish immediately
     * @return Collection<int, Base>
     */
    public static function publish( string $model, array $ids, ?Authenticatable $user = null, ?string $at = null ) : Collection
    {
        Validation::publishAt( $at );

        $action = match( $model ) {
            Element::class => 'element:publish',
            File::class => 'file:publish',
            Page::class => 'page:publish',
            default => throw new \InvalidArgumentException( 'Invalid CMS model: ' . $model ),
        };

        if( $user && !Permission::can( $action, $user ) ) {
            throw new Exception( 'Insufficient permissions' );
        }

        $ids = array_values( array_unique( $ids ) );
        $model::checkBulk( count( $ids ) );
        $editor = Utils::editor( $user );
        $publication = new self();

        $publish = fn() => Utils::transaction(
            function() use ( $at, $editor, $ids, $model, $publication, $user ) {

                /** @var Collection<int, Base> $items */
                $items = collect();
                /** @var Collection<int, Base> $pending */
                $pending = collect();

                foreach( array_chunk( $ids, 50 ) as $chunk )
                {
                    $loaded = self::items( $model, $chunk, (bool) $at );
                    $unpublished = $loaded->filter(
                        fn( Base $item ) => $item->latest && !$item->latest->published,
                    )->values();

                    if( !$unpublished->isEmpty() )
                    {
                        if( !$at ) {
                            $publication->prepare( $unpublished->pluck( 'latest' )->values(), $user );
                            $publication->applyAll( $unpublished->map( function( Base $item ) {
                                if( !( $version = $item->latest ) ) {
                                    throw new \LogicException( 'Unpublished model has no latest version.' );
                                }

                                return [$item, $version];
                            } )->all() );
                            $publication->publishVersions();
                        } else {
                            if( $user ) {
                                $publication->authorize( $unpublished->pluck( 'latest' )->values(), $user );
                            }

                            $publication->schedule( $unpublished, $at, $editor );
                        }
                    }

                    foreach( $loaded as $item )
                    {
                        $items->push( $item );
                    }

                    foreach( $unpublished as $item ) {
                        $pending->push( $item );
                    }
                }

                return [$items, $pending];
            },
        );

        [$items, $pending] = $at ? $publish() : Scout::mute( Version::TYPES, $publish );

        if( !$at ) {
            $publication->flush();
        }

        Base::announceMany( $pending, 'published', $editor, [
            'published' => !$at,
            'publish_at' => $at,
        ] );

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

        foreach( $items as [$model, $version] )
        {
            $id = $model->id;
            $versionId = $version->id;

            if( $id === null || $versionId === null ) {
                throw new \LogicException( 'Prepared publication contains an unsaved model.' );
            }

            $route = $model instanceof Page ? [
                'domain' => $model->domain,
                'path' => $model->path,
            ] : null;

            $model->stage( $version );
            $columns = array_keys( $model->getDirty() );
            $updated = $model->getUpdatedAtColumn();

            if( $columns && $model->usesTimestamps() && $updated !== null )
            {
                $model->setUpdatedAt( $model->freshTimestamp() );
                $columns[] = $updated;
            }

            $columns = array_values( array_unique( $columns ) );
            sort( $columns, SORT_STRING );

            if( $columns )
            {
                $attributes = $model->getAttributes();
                $key = $model::class . '|' . implode( '|', $columns );
                $row = ['id' => $id];

                foreach( $columns as $column ) {
                    $row[$column] = $attributes[$column] ?? null;
                }

                $groups[$key]['table'] = $model->getTable();
                $groups[$key]['columns'] = $columns;
                $groups[$key]['rows'][] = $row;
            }

            if( !$version->published ) {
                $version->published = true;
                $this->versions[$versionId] = $version;
            }

            $this->track( $model, $version );

            if( $route ) {
                $this->routes[] = $route;
                $this->routes[] = [
                    'domain' => $model->domain,
                    'path' => $model->path,
                ];
            }
        }

        foreach( $groups as $group ) {
            self::updateRows( $group['table'], $group['rows'], $group['columns'] );
        }

        foreach( $items as [$model, $_] ) {
            $model->syncChanges()->syncOriginal();
        }
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

        $this->refs = [];
        $owners = $this->references( $versions );
        $elements = $this->related( $owners, 'elements' );
        $files = $this->related( $owners, 'files' );

        if( ( $elements && ( !$canElements || !$canFiles ) )
            || ( $files && !$canFiles ) ) {
            throw new Exception( 'Insufficient permissions' );
        }
    }


    /**
     * Removes unavailable related IDs from active references.
     *
     * @param array<string, array{type: string, id: string}> $owners
     * @param 'files'|'elements' $type
     * @param array<string, bool|Element> $existing Existing-ID map
     */
    private function filter( array $owners, string $type, array $existing ) : void
    {
        foreach( $owners as $versionId => $_ )
        {
            $this->refs[$versionId][$type] = array_values( array_filter(
                $this->refs[$versionId][$type],
                fn( $id ) => isset( $existing[$id] ),
            ) );
        }
    }


    /**
     * Returns whether a portable pivot-presence projection is known to contain rows.
     */
    private function flag( Version $version, string $name ) : ?bool
    {
        if( !array_key_exists( $name, $version->getAttributes() ) ) {
            return null;
        }

        return $version->getAttribute( $name ) !== null;
    }


    /**
     * Loads models and their latest versions in one portable joined query.
     *
     * @param class-string<Base> $model
     * @param array<string> $ids
     * @return Collection<int, Base>
     */
    private static function items( string $model, array $ids, bool $compact = false ) : Collection
    {
        $instance = new $model();
        $table = $instance->getTable();
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $columns = [
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

        $query = $instance->newQuery()
            ->select( $compact ? ["{$table}.id", "{$table}.latest_id"] : ["{$table}.*"] )
            ->leftJoin( 'cms_versions AS cms_latest', function( $join ) use ( $table ) {
                $join->on( "{$table}.latest_id", '=', 'cms_latest.id' )
                    ->where( 'cms_latest.tenant_id', Tenancy::value() );
            } )
            ->whereIn( "{$table}.id", $ids );

        foreach( $columns as $column ) {
            $query->addSelect( "cms_latest.{$column} AS pub_{$column}" );
        }

        if( !$compact && $model === Page::class )
        {
            $query->addSelect( [
                'pub_active_files' => $db->table( 'cms_page_file' )
                    ->select( 'page_id' )
                    ->whereColumn( 'page_id', "{$table}.id" )
                    ->limit( 1 ),
                'pub_active_elements' => $db->table( 'cms_page_element' )
                    ->select( 'page_id' )
                    ->whereColumn( 'page_id', "{$table}.id" )
                    ->limit( 1 ),
            ] );
        }
        elseif( !$compact && $model === Element::class )
        {
            $query->addSelect( [
                'pub_active_files' => $db->table( 'cms_element_file' )
                    ->select( 'element_id' )
                    ->whereColumn( 'element_id', "{$table}.id" )
                    ->limit( 1 ),
            ] );
        }

        if( $model !== File::class )
        {
            $query->addSelect( [
                'pub_target_files' => $db->table( 'cms_version_file' )
                    ->select( 'version_id' )
                    ->whereColumn( 'version_id', 'cms_latest.id' )
                    ->limit( 1 ),
            ] );
        }

        if( $model === Page::class )
        {
            $query->addSelect( [
                'pub_target_elements' => $db->table( 'cms_version_element' )
                    ->select( 'version_id' )
                    ->whereColumn( 'version_id', 'cms_latest.id' )
                    ->limit( 1 ),
            ] );
        }

        $items = $query->get();

        foreach( $items as $item )
        {
            $attributes = [];

            foreach( $columns as $column )
            {
                $attributes[$column] = $item->getAttribute( "pub_{$column}" );
                $item->offsetUnset( "pub_{$column}" );
            }

            if( $attributes['id'] === null )
            {
                $item->setRelation( 'latest', null );
                continue;
            }

            /** @var Version $version */
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

            $item->setRelation( 'latest', $version );
        }

        return $items;
    }


    /**
     * Loads version pivot IDs into the active reference maps.
     *
     * @param array<string, array{type: string, id: string}> $owners
     * @param 'files'|'elements' $target
     */
    private function load( string $table, string $related, array $owners, string $target ) : void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        foreach( array_chunk( array_keys( $owners ), 50 ) as $ids )
        {
            foreach( $db->table( $table )->select( 'version_id', $related )->whereIn( 'version_id', $ids )->cursor() as $row )
            {
                /** @var string $versionId */
                $versionId = $row->version_id;
                /** @var string $relatedId */
                $relatedId = $row->{$related};

                Page::checkBulk( ++$this->work );
                $this->refs[$versionId][$target][] = $relatedId;
            }
        }
    }


    /**
     * Publishes referenced elements and their file dependencies in bounded chunks.
     *
     * @param array<string> $ids
     * @return array<string, Element> Existing elements by ID
     */
    private function publishElements( array $ids, ?Authenticatable $user = null ) : array
    {
        $existing = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            $loaded = self::items( Element::class, $chunk );
            $items = [];

            foreach( $loaded as $element )
            {
                if( !$element instanceof Element ) {
                    throw new \LogicException( 'Invalid CMS element result.' );
                }

                if( ( $id = $element->id ) === null ) {
                    throw new \LogicException( 'Stored CMS element has no ID.' );
                }

                $existing[$id] = $element;
                $this->elements[$id] = $element;

                if( ( $version = $element->latest ) && !$version->published ) {
                    $items[] = [$element, $version];
                }
            }

            if( $items && $user && !Permission::can( 'element:publish', $user ) ) {
                throw new Exception( 'Insufficient permissions' );
            }

            $owners = $this->references( collect( array_column( $items, 1 ) ) );
            $files = $this->publishFiles( $this->related( $owners, 'files' ), $user );
            $this->filter( $owners, 'files', $files );
            $this->sync( $owners );
            $this->applyAll( $items );

            foreach( array_keys( $owners ) as $versionId ) {
                unset( $this->refs[$versionId] );
            }
        }

        return $existing;
    }


    /**
     * Publishes referenced files in bounded, deduplicated chunks.
     *
     * @param array<string> $ids
     * @return array<string, bool> Existing-ID map
     */
    private function publishFiles( array $ids, ?Authenticatable $user = null ) : array
    {
        $existing = [];

        foreach( array_chunk( array_values( array_unique( $ids ) ), 50 ) as $chunk )
        {
            $items = [];
            $loaded = self::items( File::class, $chunk );

            foreach( $loaded as $file )
            {
                if( ( $id = $file->id ) === null ) {
                    throw new \LogicException( 'Stored CMS file has no ID.' );
                }

                $existing[$id] = true;

                if( $file->latest && !$file->latest->published ) {
                    $items[] = [$file, $file->latest];
                }
            }

            if( $items && $user && !Permission::can( 'file:publish', $user ) ) {
                throw new Exception( 'Insufficient permissions' );
            }

            $this->applyAll( $items );
        }

        return $existing;
    }


    /**
     * Marks all versions prepared in the current root chunk as published.
     */
    private function publishVersions() : void
    {
        foreach( array_chunk( array_keys( $this->versions ), 500 ) as $ids ) {
            Version::whereIn( 'id', $ids )->update( ['published' => true] );
        }

        foreach( $this->versions as $version ) {
            $version->syncChanges()->syncOriginal();
        }

        $this->versions = [];
    }


    /**
     * Loads scalar references for a bounded collection of versions.
     *
     * @param Collection<int, Version> $versions
     * @return array<string, array{type: string, id: string}>
     */
    private function references( Collection $versions ) : array
    {
        $elementOwners = [];
        $fileOwners = [];
        $owners = [];

        foreach( $versions as $version )
        {
            /** @var class-string<Base> $type */
            $type = $version->versionable_type;

            if( $type === File::class ) {
                continue;
            }

            /** @var string $id */
            $id = $version->versionable_id;
            $versionId = $version->id;

            if( $versionId === null ) {
                throw new \LogicException( 'Stored CMS version has no ID.' );
            }

            $owners[$versionId] = ['type' => $type, 'id' => $id];

            $this->refs[$versionId] = [
                'files' => [],
                'elements' => [],
                'active_files' => $this->flag( $version, 'active_files' ),
                'active_elements' => $this->flag( $version, 'active_elements' ),
            ];

            if( $this->flag( $version, 'target_files' ) !== false ) {
                $fileOwners[$versionId] = $owners[$versionId];
            }

            if( $type === Page::class && $this->flag( $version, 'target_elements' ) !== false ) {
                $elementOwners[$versionId] = $owners[$versionId];
            }
        }

        $this->load( 'cms_version_file', 'file_id', $fileOwners, 'files' );

        $this->load( 'cms_version_element', 'element_id', $elementOwners, 'elements' );

        return $owners;
    }


    /**
     * Flattens one active reference type for the given owners.
     *
     * @param array<string, array{type: string, id: string}> $owners
     * @param 'files'|'elements' $type
     * @return array<string>
     */
    private function related( array $owners, string $type ) : array
    {
        $result = [];

        foreach( $owners as $versionId => $_ )
        {
            foreach( $this->refs[$versionId][$type] as $id ) {
                $result[] = $id;
            }
        }

        return $result;
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

        self::updateRows( ( new Version() )->getTable(), $rows, ['data', 'editor', 'publish_at'] );

        foreach( $versions as $version ) {
            $version->syncChanges()->syncOriginal();
        }
    }


    /**
     * Synchronizes active page and element pivots.
     *
     * @param array<string, array{type: string, id: string}> $owners
     */
    private function sync( array $owners ) : void
    {
        $pages = [];
        $elements = [];

        foreach( $owners as $versionId => $owner )
        {
            if( $owner['type'] === Page::class ) {
                $pages[$owner['id']] = $this->refs[$versionId];
            } else {
                $elements[$owner['id']] = $this->refs[$versionId];
            }
        }

        $this->syncPivot( 'cms_page_file', 'page_id', 'file_id', $pages, 'files' );
        $this->syncPivot( 'cms_page_element', 'page_id', 'element_id', $pages, 'elements' );
        $this->syncPivot( 'cms_element_file', 'element_id', 'file_id', $elements, 'files' );
    }


    /**
     * Replaces pivots only for owners whose target references changed.
     *
     * @param array<string, array{
     *     files: array<string>,
     *     elements: array<string>,
     *     active_files: bool|null,
     *     active_elements: bool|null
     * }> $groups
     * @param 'files'|'elements' $key
     */
    private function syncPivot( string $table, string $owner, string $related, array $groups, string $key ) : void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $active = "active_{$key}";
        $groups = array_filter(
            $groups,
            fn( $sets ) => $sets[$key] || ( $sets[$active] ?? null ) !== false,
        );

        foreach( array_chunk( $groups, 50, true ) as $chunk )
        {
            $current = array_fill_keys( array_keys( $chunk ), [] );

            foreach( $db->table( $table )->select( $owner, $related )->whereIn( $owner, array_keys( $chunk ) )->cursor() as $row )
            {
                /** @var string $ownerId */
                $ownerId = $row->{$owner};
                /** @var string $relatedId */
                $relatedId = $row->{$related};

                $current[$ownerId][$relatedId] = true;
            }

            $replace = [];

            foreach( $chunk as $id => $sets )
            {
                $target = array_fill_keys( $sets[$key], true );

                $existing = $current[$id];

                if( count( $target ) !== count( $existing ) || array_diff_key( $target, $existing ) ) {
                    $replace[$id] = $target;
                }
            }

            if( !$replace ) {
                continue;
            }

            $db->table( $table )->whereIn( $owner, array_keys( $replace ) )->delete();
            $rows = [];

            foreach( $replace as $id => $refs )
            {
                foreach( array_keys( $refs ) as $ref )
                {
                    $rows[] = [$owner => $id, $related => $ref];

                    if( count( $rows ) === 500 )
                    {
                        $db->table( $table )->insert( $rows );
                        $rows = [];
                    }
                }
            }

            if( $rows ) {
                $db->table( $table )->insert( $rows );
            }
        }
    }


    /**
     * Retains a changed model and fills relations already resolved during publication.
     */
    private function track( Base $model, Version $version ) : void
    {
        $id = $model->id;

        if( $id === null ) {
            throw new \LogicException( 'Published CMS model has no ID.' );
        }

        if( $model instanceof Page )
        {
            if( ( $versionId = $version->id ) === null ) {
                throw new \LogicException( 'Published CMS page version has no ID.' );
            }

            $elements = [];

            foreach( $this->refs[$versionId]['elements'] ?? [] as $elementId ) {
                if( isset( $this->elements[$elementId] ) ) {
                    $elements[] = $this->elements[$elementId];
                }
            }

            $items = ( new Element() )->newCollection( $elements );
            $model->setRelation( 'elements', $items );
            $version->setRelation( 'elements', $items );
        }

        $this->models[$model::class][$id] = $model;
    }


    /**
     * Updates different row values in parameter-safe CASE batches.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string> $columns
     */
    private static function updateRows( string $table, array $rows, array $columns ) : void
    {
        if( !$rows || !$columns ) {
            return;
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $grammar = $db->getQueryGrammar();
        $id = $grammar->wrap( 'id' );
        $size = max( 1, intdiv( 2000, count( $columns ) * 2 + 1 ) );

        foreach( array_chunk( $rows, $size ) as $chunk )
        {
            $bindings = [];
            $sets = [];

            foreach( $columns as $column )
            {
                $name = $grammar->wrap( $column );
                $case = 'CASE ' . $id;

                foreach( $chunk as $row ) {
                    $case .= ' WHEN ? THEN ?';
                    $bindings[] = $row['id'];
                    $bindings[] = $row[$column];
                }

                $sets[] = $name . ' = ' . $case . ' ELSE ' . $name . ' END';
            }

            $bindings = [...$bindings, ...array_column( $chunk, 'id' )];
            $placeholders = implode( ', ', array_fill( 0, count( $chunk ), '?' ) );
            $sql = 'UPDATE ' . $grammar->wrapTable( $table )
                . ' SET ' . implode( ', ', $sets )
                . ' WHERE ' . $id . ' IN (' . $placeholders . ')';

            $db->update( $sql, $bindings );
        }
    }
}
