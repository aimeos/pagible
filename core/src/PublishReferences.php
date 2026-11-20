<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;


/**
 * Collects the file and element references of published versions and synchronizes the active pivots.
 *
 * @internal
 */
final class PublishReferences
{
    /**
     * Version attributes indicating whether active and target pivot rows exist.
     */
    public const FLAGS = ['active_files', 'active_elements', 'target_files', 'target_elements'];

    /**
     * @var array<string, array{
     *     files: array<string>,
     *     elements: array<string>,
     *     active_files: bool|null,
     *     active_elements: bool|null
     * }>
     */
    private array $refs = [];

    private int $work = 0;


    /**
     * Returns the IDs of the active elements referenced by the version.
     *
     * @return array<string>
     */
    public function elements( string $versionId ) : array
    {
        return $this->refs[$versionId]['elements'] ?? [];
    }


    /**
     * Removes unavailable related IDs from active references.
     *
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     * @param 'files'|'elements' $type
     * @param array<string, mixed> $existing Existing-ID map
     */
    public function filter( array $owners, string $type, array $existing ) : void
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
     * Removes the references of the owners.
     *
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     */
    public function forget( array $owners ) : void
    {
        foreach( array_keys( $owners ) as $versionId ) {
            unset( $this->refs[$versionId] );
        }
    }


    /**
     * Loads scalar references for a bounded collection of versions.
     *
     * @param Collection<int, Version> $versions
     * @return array<string, array{type: class-string<Base>, id: string}> Owners by version ID
     */
    public function load( Collection $versions ) : array
    {
        $elementIds = [];
        $fileIds = [];
        $owners = [];
        $pivots = [];

        foreach( $versions as $version )
        {
            /** @var class-string<Base> $type */
            $type = $version->versionable_type;

            if( !( $pivots[$type] ??= self::pivots( $type ) ) ) {
                continue;
            }

            /** @var string $id */
            $id = $version->versionable_id;
            $versionId = (string) $version->id;

            $owners[$versionId] = ['type' => $type, 'id' => $id];

            $this->refs[$versionId] = [
                'files' => [],
                'elements' => [],
                'active_files' => self::flag( $version, 'active_files' ),
                'active_elements' => self::flag( $version, 'active_elements' ),
            ];

            if( isset( $pivots[$type]['files'] ) && self::flag( $version, 'target_files' ) !== false ) {
                $fileIds[] = $versionId;
            }

            if( isset( $pivots[$type]['elements'] ) && self::flag( $version, 'target_elements' ) !== false ) {
                $elementIds[] = $versionId;
            }
        }

        $this->loadPivot( 'cms_version_file', 'file_id', $fileIds, 'files' );
        $this->loadPivot( 'cms_version_element', 'element_id', $elementIds, 'elements' );

        return $owners;
    }


    /**
     * Returns the subqueries projecting the presence of active and target pivot rows.
     *
     * The aliases are the FLAGS prefixed by "pub_" and must be moved to the version
     * attributes without the prefix before the versions are passed to load().
     *
     * @param Base $instance Model whose pivot rows are checked
     * @param bool $compact TRUE to project the target pivots only
     * @param string $versionKey Qualified column of the latest version ID
     * @return array<string, Builder> Subqueries by alias
     */
    public static function projections( Base $instance, bool $compact, string $versionKey ) : array
    {
        $db = DB::connection();
        $first = fn( string $from, string $col, string $ref ) => $db->table( $from )->select( $col )->whereColumn( $col, $ref )->limit( 1 );
        $version = new Version();
        $selects = [];

        foreach( self::pivots( $instance ) as $name => $relation )
        {
            if( !$compact ) {
                $selects["pub_active_{$name}"] = $first( $relation->getTable(), $relation->getForeignPivotKeyName(), $relation->getQualifiedParentKeyName() );
            }

            $target = $version->{$name}();
            $selects["pub_target_{$name}"] = $first( $target->getTable(), $target->getForeignPivotKeyName(), $versionKey );
        }

        return $selects;
    }


    /**
     * Flattens one active reference type for the given owners.
     *
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     * @param 'files'|'elements' $type
     * @return array<string>
     */
    public function related( array $owners, string $type ) : array
    {
        return array_merge( ...array_map( fn( $versionId ) => $this->refs[$versionId][$type], array_keys( $owners ) ) );
    }


    /**
     * Removes all collected references.
     */
    public function reset() : void
    {
        $this->refs = [];
    }


    /**
     * Synchronizes the active file and element pivots of the owners.
     *
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     */
    public function sync( array $owners ) : void
    {
        $groups = [];

        foreach( $owners as $versionId => $owner ) {
            $groups[$owner['type']][$owner['id']] = $this->refs[$versionId];
        }

        foreach( $groups as $type => $refs )
        {
            foreach( self::pivots( $type ) as $name => $relation ) {
                $this->syncPivot( $relation->getTable(), $relation->getForeignPivotKeyName(), $relation->getRelatedPivotKeyName(), $refs, $name );
            }
        }
    }


    /**
     * Returns whether a portable pivot-presence projection is known to contain rows.
     */
    private static function flag( Version $version, string $name ) : ?bool
    {
        if( !array_key_exists( $name, $version->getAttributes() ) ) {
            return null;
        }

        return $version->getAttribute( $name ) !== null;
    }


    /**
     * Loads version pivot IDs into the active reference maps.
     *
     * @param array<string> $versionIds
     * @param 'files'|'elements' $target
     */
    private function loadPivot( string $table, string $related, array $versionIds, string $target ) : void
    {
        $db = DB::connection();

        foreach( array_chunk( $versionIds, Base::CHUNK ) as $ids )
        {
            foreach( $db->table( $table )->select( 'version_id', $related )->whereIn( 'version_id', $ids )->cursor() as $row )
            {
                /** @var string $versionId */
                $versionId = $row->version_id;
                /** @var string $relatedId */
                $relatedId = $row->{$related};

                PageView::checkBulk( ++$this->work );
                $this->refs[$versionId][$target][] = $relatedId;
            }
        }
    }


    /**
     * Returns the file and element relations of the model whose pivots are synchronized on publishing.
     *
     * @param Model|class-string<Model> $model Model or model class name
     * @return array<'files'|'elements', BelongsToMany<Model, Model>>
     */
    private static function pivots( Model|string $model ) : array
    {
        $model = is_string( $model ) ? new $model() : $model;
        $result = [];

        foreach( ['files', 'elements'] as $name )
        {
            if( method_exists( $model, $name ) && ( $relation = $model->{$name}() ) instanceof BelongsToMany ) {
                $result[$name] = $relation;
            }
        }

        return $result;
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
        $db = DB::connection();
        $active = "active_{$key}";
        $groups = array_filter(
            $groups,
            fn( $sets ) => $sets[$key] || $sets[$active] !== false,
        );

        foreach( array_chunk( $groups, Base::CHUNK, true ) as $chunk )
        {
            // owners without references only need their pivots removed
            if( !array_filter( $chunk, fn( $sets ) => $sets[$key] !== [] ) )
            {
                $db->table( $table )->whereIn( $owner, array_keys( $chunk ) )->delete();
                continue;
            }

            $current = array_fill_keys( array_keys( $chunk ), [] );
            // owners known to have no pivots yet, e.g. never published ones, are only inserted
            $known = array_keys( array_filter( $chunk, fn( $sets ) => $sets[$active] !== false ) );

            foreach( $known ? $db->table( $table )->select( $owner, $related )->whereIn( $owner, $known )->cursor() : [] as $row )
            {
                /** @var string $ownerId */
                $ownerId = $row->{$owner};
                /** @var string $relatedId */
                $relatedId = $row->{$related};

                $current[$ownerId][$relatedId] = true;
            }

            $replace = $rows = [];

            foreach( $chunk as $id => $sets )
            {
                $target = array_fill_keys( $sets[$key], true );

                if( count( $target ) !== count( $current[$id] ) || array_diff_key( $target, $current[$id] ) )
                {
                    $replace[] = $id;

                    foreach( array_keys( $target ) as $ref ) {
                        $rows[] = [$owner => $id, $related => $ref];
                    }
                }
            }

            if( !$replace ) {
                continue;
            }

            // only owners with existing pivots need them removed
            if( $stale = array_values( array_filter( $replace, fn( $id ) => $current[$id] !== [] ) ) ) {
                $db->table( $table )->whereIn( $owner, $stale )->delete();
            }

            DB::insertRows( $db->table( $table ), $rows );
        }
    }
}
