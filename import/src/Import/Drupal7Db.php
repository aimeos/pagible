<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Str;


/**
 * Accesses the tables, variables, locales and records of a Drupal 7 database.
 */
class Drupal7Db
{
    /** Drupal language code of the default language */
    protected ?string $langcode = null;
    /** @var array<string, mixed> Drupal 7 variables by name */
    protected array $variables = [];
    /** @var array<string, bool> Existing Drupal 7 tables and columns */
    protected array $tables = [];
    /** @var array<string, bool> Text groups which have strings in the locales_source table */
    protected array $groups = [];


    /**
     * @param Connection $db Drupal 7 database connection
     */
    public function __construct( protected Connection $db )
    {
    }


    /**
     * Tests if the table of the Drupal 7 database exists and contains the column.
     */
    public function column( string $table, string $column ): bool
    {
        return $this->tables["{$table}.{$column}"] ??= $this->exists( $table ) && $this->db->getSchemaBuilder()->hasColumn( $table, $column );
    }


    /**
     * Tests if the Drupal 7 database contains the table.
     */
    public function exists( string $table ): bool
    {
        return $this->tables[$table] ??= $this->db->getSchemaBuilder()->hasTable( $table );
    }


    /**
     * Returns the language code of the default language.
     */
    public function langcode(): string
    {
        if( $this->langcode === null )
        {
            $default = $this->variable( 'language_default' );
            $this->langcode = is_object( $default ) ? (string) ( ( (array) $default )['language'] ?? 'en' ) : 'en';
        }

        return $this->langcode;
    }


    /**
     * Returns the translations of the strings of the i18n_string module by context and language.
     *
     * @param string $group Text group, e.g. "menu"
     * @param array<int, string> $contexts Contexts of the strings, e.g. "item:5:title"
     * @return array<string, array<string, string>> Translations by context and language code
     */
    public function locales( string $group, array $contexts ): array
    {
        if( !$contexts || !$this->exists( 'locales_source' ) || !$this->exists( 'locales_target' ) ) {
            return [];
        }

        // the contexts are only indexed in the table of the i18n_string module
        $i18n = $this->exists( 'i18n_string' );

        // without i18n_string, the unindexed locales_source table is only searched if it contains strings of the text group (checked once)
        if( !$i18n && !( $this->groups[$group] ??= $this->db->table( 'locales_source' )->where( 'textgroup', $group )->exists() ) ) {
            return [];
        }

        $table = $i18n ? 'i18n_string as s' : 'locales_source as s';
        $rows = Database::chunked( $contexts, fn( $chunk ) => $this->db->table( $table )->join( 'locales_target as t', 't.lid', '=', 's.lid' )
            ->where( 's.textgroup', $group )->whereIn( 's.context', $chunk )->orderBy( 't.language' )->get( ['s.context', 't.language', 't.translation'] ) );
        $list = [];

        foreach( $rows as $row )
        {
            if( trim( (string) $row->translation ) !== '' ) {
                $list[(string) $row->context][(string) $row->language] = (string) $row->translation;
            }
        }

        return $list;
    }


    /**
     * Returns the values as record like the ones of the database.
     *
     * @param array<string, mixed> $data Values by column name
     */
    public function record( array $data ): \stdClass
    {
        return (object) $data;
    }


    /**
     * Returns the Drupal 8 role ID of a Drupal 7 role.
     */
    public function role( int $rid, string $name ): string
    {
        return match( $rid ) {
            1 => 'anonymous',
            2 => 'authenticated',
            default => Str::slug( $name, '_' ) ?: 'role_' . $rid,
        };
    }


    /**
     * Returns the value of a Drupal 7 variable.
     */
    public function variable( string $name ): mixed
    {
        if( !array_key_exists( $name, $this->variables ) )
        {
            $value = $this->exists( 'variable' ) ? $this->db->table( 'variable' )->where( 'name', $name )->value( 'value' ) : null;
            $this->variables[$name] = Database::unserialize( $value );
        }

        return $this->variables[$name];
    }
}
