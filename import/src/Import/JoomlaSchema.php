<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;


/**
 * Returns the prefixed names of the Joomla tables and which tables and columns exist.
 *
 * The tables and columns are read once because older Joomla versions lack some of them.
 */
class JoomlaSchema
{
    /** Prefix of the Joomla database tables without "#__" */
    protected string $tablePrefix;
    /** @var array<string, bool> If the tables exist by table name without prefix */
    protected array $tables = [];
    /** @var array<string, array<int, string>> Existing columns by table name without prefix */
    protected array $columns = [];


    /**
     * @param Connection $db Connection to the Joomla database
     * @param string $prefix Joomla database table prefix, "#__" is removed
     */
    public function __construct( protected Connection $db, string $prefix )
    {
        $this->tablePrefix = str_replace( '#__', '', $prefix );
    }


    /**
     * Returns the existing columns of the table from the given ones, the columns of each table are read once.
     *
     * @param string $table Table name without prefix
     * @param array<int, string> $columns Column names
     * @return array<int, string> Existing column names
     */
    public function existingColumns( string $table, array $columns ): array
    {
        $this->columns[$table] ??= $this->db->getSchemaBuilder()->getColumnListing( $this->table( $table ) );
        return array_values( array_intersect( $columns, $this->columns[$table] ) );
    }


    /**
     * Tests if the column exists in the table, the columns of each table are read once.
     *
     * @param string $table Table name without prefix
     * @param string $column Column name
     */
    public function hasColumn( string $table, string $column ): bool
    {
        return $this->existingColumns( $table, [$column] ) !== [];
    }


    /**
     * Returns the prefixed name of the table if it exists in the Joomla database.
     *
     * @param string $table Table name without prefix
     * @return string|null Prefixed table name or NULL if the table doesn't exist
     */
    public function tableIfExists( string $table ): ?string
    {
        $prefixed = $this->table( $table );
        $this->tables[$table] ??= $this->db->getSchemaBuilder()->hasTable( $prefixed );

        return $this->tables[$table] ? $prefixed : null;
    }


    /**
     * Replaces the "#__" placeholders for the table prefix in the SQL statement.
     *
     * Like Joomla, placeholders within quoted string literals are kept.
     */
    public function sql( string $sql ): string
    {
        $prefix = $this->db->getTablePrefix() . $this->tablePrefix;
        // single and double quoted literals including escaped and doubled quotes are matched as a whole and returned unchanged
        $pattern = '/\'(?:[^\'\\\\]|\\\\.|\'\')*\'|"(?:[^"\\\\]|\\\\.|"")*"|#__/s';

        return (string) preg_replace_callback( $pattern, fn( $match ) => $match[0] === '#__' ? $prefix : $match[0], $sql );
    }


    /**
     * Returns the prefixed table name.
     */
    public function table( string $name ): string
    {
        return $this->tablePrefix . $name;
    }
}
