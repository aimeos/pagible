<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms;


/**
 * Database column qualification helpers.
 */
class DB
{
    /**
     * Per-model structural columns that always live on the model table.
     *
     * @var array<string, list<string>>
     */
    public const MODEL_COLUMNS = [
        'cms_pages' => ['id', 'parent_id', '_lft', '_rgt', 'tenant_id'],
        'cms_elements' => ['id', 'tenant_id', 'type', 'name'],
        'cms_files' => ['id', 'tenant_id', 'name', 'mime', 'path'],
    ];


    /**
     * Qualify an unqualified field name to the correct SQL column.
     *
     * In draft mode ($isDraft=true), routes version-level fields to cms_versions.
     * In content mode ($isDraft=false), routes all fields to the model table.
     * For MySQL/MariaDB/SQL Server, uses virtual/computed column names instead of JSON paths.
     *
     * @param string $field Unqualified field name
     * @param string $table Model table name (e.g., cms_pages)
     * @param bool $isDraft Whether draft mode is active (default: true)
     * @param string $driver Database driver name (default: '')
     * @return string|null Qualified column name, or null to skip
     */
    public static function qualify( string $field, string $table, bool $isDraft = true, string $driver = '' ) : ?string
    {
        $modelCols = self::MODEL_COLUMNS[$table] ?? ['id', 'tenant_id'];

        return match( true ) {
            $field === 'byversions_count' => $field,
            in_array( $field, ['lang', 'editor'] ) => ( $isDraft ? 'cms_versions.' : $table . '.' ) . $field,
            $field === 'published' => $isDraft ? 'cms_versions.published' : null,
            in_array( $field, $modelCols ) => $table . '.' . $field,
            $isDraft && in_array( $driver, ['mysql', 'mariadb', 'sqlsrv'] ) => 'cms_versions.data_' . $field,
            $isDraft => 'cms_versions.data->' . $field,
            default => $table . '.' . $field,
        };
    }
}
