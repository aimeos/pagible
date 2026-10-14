<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns;

use Aimeos\Cms\Models\Version;


/**
 * Removes stale versions of CMS models, keeping the configured number of versions.
 *
 * @phpstan-require-extends \Aimeos\Cms\Models\Base
 */
trait PrunesVersions
{
    /**
     * Removes old versions for several models using one ranked version stream.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Version owner IDs (variant IDs for pages)
     */
    public static function pruneVersions( string $tenant, array $ids ) : void
    {
        $ids = array_values( array_unique( $ids ) );

        if( !$ids ) {
            return;
        }

        $num = max( 0, (int) config( 'cms.versions', 10 ) );
        $stale = new \SplTempFileObject( 1024 * 1024 );
        $stale->setFlags( \SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY );
        $stale->setCsvControl( ',', '"', '' );
        $keep = [];
        $found = false;

        foreach( static::versionRanks( $tenant, $ids ) as [$id, $rank] )
        {
            if( $rank > $num ) {
                $stale->fputcsv( [$id], ',', '"', '' );
                $found = true;
            } else {
                $keep[] = $id;
            }
        }

        if( !$found ) {
            return;
        }

        $prune = static::pruner( $tenant, $ids, $keep );
        $size = static::PRUNE_SIZE;
        $stale->rewind();
        $chunk = [];

        foreach( $stale as $row )
        {
            if( $row ) {
                $chunk[] = $row[0];
            }

            if( count( $chunk ) === $size ) {
                $prune( $chunk );
                $chunk = [];
            }
        }

        if( $chunk ) {
            $prune( $chunk );
        }
    }


    /**
     * Removes old versions of the model, keeping the configured number.
     */
    public function removeVersions() : void
    {
        if( ( $id = $this->getVersionKey() ) !== null ) {
            static::pruneVersions( $this->tenant_id, [$id] );
        }
    }


    /**
     * Returns the callback removing a chunk of stale versions of the given models.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     * @param array<string> $keep IDs of the versions which are kept
     * @return \Closure(array<string>): void
     */
    protected static function pruner( string $tenant, array $ids, array $keep ) : \Closure
    {
        return function( array $chunk ) use ( $tenant ) {
            Version::withoutTenancy()->where( 'tenant_id', $tenant )->whereIn( 'id', $chunk )->forceDelete();
        };
    }


    /**
     * Returns version IDs ranked newest first for each model.
     *
     * @param string $tenant Tenant ID
     * @param array<string> $ids Model IDs
     * @return \Generator<int, array{string, int}>
     */
    protected static function versionRanks( string $tenant, array $ids ) : \Generator
    {
        $owner = null;
        $rank = 0;

        foreach( Version::withoutTenancy()->select( 'id', 'versionable_id' )
            ->where( 'tenant_id', $tenant )
            ->where( 'versionable_type', static::versionType() )
            ->whereIn( 'versionable_id', $ids )
            ->orderBy( 'versionable_id' )->orderByDesc( 'created_at' )->orderByDesc( 'id' )
            ->cursor() as $version
        ) {
            $key = $version->versionable_id;

            if( $key !== $owner ) {
                $owner = $key;
                $rank = 0;
            }

            yield [(string) $version->id, ++$rank];
        }
    }
}
