<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Database\Connection;


trait BackupTrait
{
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
}
