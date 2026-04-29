<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
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

        foreach( $tableNames as $table ) {
            $columns[$table] = $schema->getColumnListing( $table );
        }

        return $columns;
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
