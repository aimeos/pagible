<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads a Drupal database and returns the records and configuration in the format of Drupal 8+.
 *
 * The import commands only use the readers, so the same import code works for all Drupal versions.
 * Features which only exist in some Drupal versions return no records by default.
 */
abstract class DrupalReader
{
    /** Columns of the file_managed records used by the import */
    protected const FILE_COLUMNS = ['fid', 'uri', 'filename', 'filemime'];


    /**
     * Initializes the reader.
     *
     * @param Connection $db Connection to the Drupal database
     * @param \Closure(string): void $warn Shows a warning
     */
    public function __construct( protected Connection $db, protected \Closure $warn )
    {
    }


    /**
     * Returns the name of the Drupal 8 field table.
     *
     * Drupal uses a hash of the field storage UUID if the table name would be longer than 48 characters.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * @param bool $revision TRUE for the revision table
     * @param string $uuid UUID of the field storage configuration
     */
    public static function table( string $entityType, string $field, bool $revision = false, string $uuid = '' ): string
    {
        $table = $entityType . ( $revision ? '_revision__' : '__' ) . $field;

        if( strlen( $table ) <= 48 || $uuid === '' ) {
            return $table;
        }

        return $entityType . ( $revision ? '_r__' : '__' ) . substr( hash( 'sha256', $uuid ), 0, 10 );
    }


    /**
     * Fetches the URL aliases of the system paths in all languages grouped by path.
     *
     * @param array<int, string> $paths System paths, e.g. "/taxonomy/term/5"
     * @param string $domain ID of the Domain Access domain whose aliases are used first
     * @return Collection<int|string, mixed>
     */
    abstract public function aliases( array $paths, string $domain = '' ): Collection;


    /**
     * Fetches the descriptions of the custom blocks in all languages.
     *
     * @return Collection<int, \stdClass> Rows with "id", "langcode", "info" and "default_langcode"
     */
    abstract public function blockInfos(): Collection;


    /**
     * Fetches the book outlines.
     *
     * @return Collection<int, \stdClass> Rows with "nid", "bid", "pid" and "weight"
     */
    abstract public function books(): Collection;


    /**
     * Fetches the last modification timestamps of the nodes including their translations.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, int> Timestamps by node ID
     */
    abstract public function changed( array $nids ): array;


    /**
     * Returns the Drupal configuration used by the importer keyed by name.
     *
     * Translations are keyed by collection and name, e.g. "language.de:field.field.node.page.field_date".
     *
     * @return array<string, array<string, mixed>>
     */
    abstract public function configs(): array;


    /**
     * Returns the group IDs of the roles of the Content Access module by role ID.
     *
     * @return array<int|string, mixed>
     */
    abstract public function contentAccessGids(): array;


    /**
     * Returns the name of the menu used for the page hierarchy if no other menu is configured.
     */
    abstract public function defaultMenu(): string;


    /**
     * Fetches the revisions which are newer than the current revisions of the nodes ordered by revision ID.
     *
     * @param array<int, int> $nids Node IDs
     * @return Collection<int, \stdClass> Rows with "nid", "langcode", "vid" and "title"
     */
    abstract public function drafts( array $nids ): Collection;


    /**
     * Fetches the records of the entities in all languages.
     *
     * @param string $table Base or data table of the entity type, e.g. "node_field_data"
     * @param string $column ID or UUID column
     * @param array<int, int|string> $values IDs or UUIDs of the entities
     * @return Collection<int, \stdClass>
     */
    abstract public function entities( string $table, string $column, array $values ): Collection;


    /**
     * Fetches the field rows of the entities ordered by delta.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * @param array<int, int|string> $ids Entity or revision IDs
     * @param string $column "entity_id" or "revision_id"
     * @return Collection<int, \stdClass>
     */
    abstract public function fieldRows( string $entityType, string $field, array $ids, string $column = 'entity_id' ): Collection;


    /**
     * Fetches the permanent files.
     *
     * @param array<int, int|string> $fids File IDs
     * @return Collection<int, \stdClass>
     */
    public function files( array $fids ): Collection
    {
        return Database::chunked( $fids, fn( $chunk ) => $this->db->table( 'file_managed' )->where( 'status', 1 )->whereIn( 'fid', $chunk )->get( self::FILE_COLUMNS ) );
    }


    /**
     * Tests if a node access module restricts which nodes visitors can view.
     *
     * Without the node_access table or with the default grant for all nodes, every node is public.
     */
    protected function restricted(): bool
    {
        return $this->has( 'node_access' )
            && !$this->db->table( 'node_access' )->where( 'nid', 0 )->where( 'realm', 'all' )->where( 'grant_view', '>', 0 )->exists();
    }


    /**
     * Fetches the view grants of the nodes by node ID.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, array<int, \stdClass>>|false Grants by node ID or FALSE if no node access module is used
     */
    abstract public function grants( array $nids ): array|false;


    /**
     * Tests if the database provides the Drupal 8 table.
     */
    abstract public function has( string $table ): bool;


    /**
     * Tests if the Drupal 8 table provided by the database contains the column.
     */
    abstract public function hasColumn( string $table, string $column ): bool;


    /**
     * Fetches the enabled links of the menu.
     *
     * @param string $name Menu name, e.g. "main"
     * @return Collection<int, \stdClass>
     */
    abstract public function menu( string $name ): Collection;


    /**
     * Fetches the nodes of the content types in the default language ordered by creation time.
     *
     * @param array<int, string> $types Content types, e.g. "page"
     * @param bool $unpublished TRUE to fetch unpublished nodes too
     * @param string $domain ID of the Domain Access domain whose nodes are fetched
     * @return Collection<int, \stdClass>
     */
    abstract public function nodes( array $types, bool $unpublished, string $domain = '' ): Collection;


    /**
     * Fetches the redirects of the Redirect module.
     *
     * @return iterable<int, \stdClass>
     */
    abstract public function redirects(): iterable;


    /**
     * Fetches the IDs of the terms of the vocabularies ordered by ID.
     *
     * @param array<int, string> $vocabularies Vocabulary machine names, e.g. "tags"
     * @return array<int, int> Term IDs
     */
    abstract public function termIds( array $vocabularies ): array;


    /**
     * Streams the nodes tagged with the terms, sticky and newest first.
     *
     * @param array<int, int> $tids Term IDs
     * @param int|null $limit Maximum number of rows fetched per term or NULL for all
     * @return LazyCollection<int, \stdClass> Rows with "tid" and "nid"
     */
    abstract public function termIndex( array $tids, ?int $limit = null ): LazyCollection;


    /**
     * Returns the role IDs of the Permissions by Term module for each term of the nodes.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, array<string, array<int, array<int, string>>>> Lists of role IDs by node ID and language code, "" if the permissions aren't translated
     */
    public function termRoles( array $nids ): array
    {
        return [];
    }


    /**
     * Fetches the terms in the default language ordered by ID.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    abstract public function terms( array $tids ): Collection;


    /**
     * Fetches the translations of the terms ordered by language.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    abstract public function termTranslations( array $tids ): Collection;


    /**
     * Fetches the translations of the nodes ordered by language.
     *
     * @param array<int, int> $nids Node IDs
     * @param bool $unpublished TRUE to fetch unpublished translations too
     * @return Collection<int, \stdClass>
     */
    abstract public function translations( array $nids, bool $unpublished ): Collection;


    /**
     * Returns the tracker which detects the nodes and custom blocks changed since the last import.
     *
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    abstract public function tracker( string $menu, \Closure $nodeId ): DrupalTracker;
}
