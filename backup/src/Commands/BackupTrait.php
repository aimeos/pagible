<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;


trait BackupTrait
{
    /**
     * Returns the sorted backup files of the tenant on the disk.
     *
     * @param Filesystem $storage Storage disk
     * @param string $tenant Tenant ID
     * @return Collection<int, string> Backup file paths
     */
    protected function backups( Filesystem $storage, string $tenant ): Collection
    {
        $prefix = 'pagible-' . $tenant . '-';

        /** @var Collection<int, string> */
        return collect( $storage->files() )
            ->filter( fn( string $f ) => str_starts_with( basename( $f ), $prefix ) && str_ends_with( $f, '.zip' ) )
            ->sort()
            ->values();
    }


    /**
     * Returns column listings for the given tables.
     *
     * @param Connection $db Database connection
     * @param array<int, string> $tableNames Table names
     * @return array<string, list<string>> Table name => column names
     */
    protected function classify( Connection $db, array $tableNames ): array
    {
        $schema = $db->getSchemaBuilder();

        /** @var array<string, list<string>> */
        $columns = [];

        foreach( $tableNames as $table )
        {
            $columns[$table] = array_values( array_map(
                fn( array $col ) => $col['name'],
                array_filter( $schema->getColumns( $table ), fn( array $col ) => empty( $col['generation'] ) )
            ) );
        }

        return $columns;
    }


    /**
     * Returns the stored path and preview paths of an archived File row.
     *
     * @param array<string, mixed> $row File record
     * @return list<mixed> Path and preview paths
     */
    protected static function filePaths( array $row ): array
    {
        $previews = json_decode( (string) ( $row['previews'] ?? '{}' ), true );
        return [$row['path'] ?? null, ...array_values( is_array( $previews ) ? $previews : [] )];
    }


    /**
     * Returns the authenticated manifest signature.
     *
     * Backups are tied to the application key. Restoring them in another
     * installation requires configuring the same APP_KEY.
     *
     * @param array<string, mixed> $manifest Unsigned or signed manifest data
     */
    protected function sign( array $manifest ): string
    {
        $key = (string) config( 'app.key' );

        if( $key === '' ) {
            throw new \RuntimeException( 'An application key is required for authenticated backups' );
        }

        unset( $manifest['signature'] );

        return hash_hmac( 'sha256', json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ), $key );
    }


    /**
     * Returns a writable directory path for temporary files.
     *
     * @return string Directory path
     */
    protected function tempdir(): string
    {
        $dir = storage_path( 'app' );
        return is_writable( $dir ) ? $dir : sys_get_temp_dir();
    }


    /**
     * Returns the stored path and preview paths of an archived File version row.
     *
     * @param array<string, mixed> $row Version record
     * @return list<mixed> Path and preview paths
     */
    protected static function versionPaths( array $row ): array
    {
        $data = json_decode( (string) ( $row['data'] ?? '{}' ), true );
        $data = is_array( $data ) ? $data : [];

        return [$data['path'] ?? null, ...array_values( is_array( $data['previews'] ?? null ) ? $data['previews'] : [] )];
    }
}
