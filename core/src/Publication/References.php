<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Publication;

use Aimeos\Cms\DB;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;


/**
 * Collects the file and element references of published versions and synchronizes the active pivots.
 *
 * @internal
 */
final class References
{
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
        $elementOwners = [];
        $fileOwners = [];
        $owners = [];

        foreach( $versions as $version )
        {
            /** @var class-string<Base> $type */
            $type = $version->versionable_type;

            if( !( $pivots = self::pivots( $type ) ) ) {
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

            if( isset( $pivots['files'] ) && self::flag( $version, 'target_files' ) !== false ) {
                $fileOwners[$versionId] = $owners[$versionId];
            }

            if( isset( $pivots['elements'] ) && self::flag( $version, 'target_elements' ) !== false ) {
                $elementOwners[$versionId] = $owners[$versionId];
            }
        }

        $this->loadPivot( 'cms_version_file', 'file_id', $fileOwners, 'files' );
        $this->loadPivot( 'cms_version_element', 'element_id', $elementOwners, 'elements' );

        return $owners;
    }


    /**
     * Returns the file and element relations of the model whose pivots are synchronized on publishing.
     *
     * @param Model|class-string<Model> $model Model or model class name
     * @return array<'files'|'elements', BelongsToMany<Model, Model>>
     */
    public static function pivots( Model|string $model ) : array
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
     * Flattens one active reference type for the given owners.
     *
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     * @param 'files'|'elements' $type
     * @return array<string>
     */
    public function related( array $owners, string $type ) : array
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
     * @param array<string, array{type: class-string<Base>, id: string}> $owners
     * @param 'files'|'elements' $target
     */
    private function loadPivot( string $table, string $related, array $owners, string $target ) : void
    {
        $db = DB::connection();

        foreach( array_chunk( array_keys( $owners ), Base::CHUNK ) as $ids )
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
                foreach( array_keys( $refs ) as $ref ) {
                    $rows[] = [$owner => $id, $related => $ref];
                }
            }

            DB::insertRows( $db->table( $table ), $rows );
        }
    }
}
