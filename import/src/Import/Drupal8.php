<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\DB;


/**
 * Reads a Drupal 8+ database.
 */
class Drupal8 extends DrupalReader
{
    /** @var int Maximum number of UUIDs searched by the database because each one adds a LIKE condition */
    protected const LIKE_SIZE = 50;
    /** @var int Maximum number of entities whose texts are read at once when searching for UUIDs */
    protected const TEXT_SIZE = 100;

    /** @var array<string, array<string, mixed>>|null Configuration by name */
    protected ?array $configs = null;
    /** Node columns used by the import */
    protected const NODE_COLUMNS = ['nid', 'vid', 'type', 'langcode', 'title', 'status', 'created', 'changed', 'default_langcode', 'publish_on', 'unpublish_on'];

    /** @var array<string, bool> Existing tables and columns */
    protected array $tables = [];
    /** @var array<int, string>|null Existing node columns used by the import */
    protected ?array $nodeColumns = null;


    /**
     * Fetches the URL aliases of the system paths in all languages grouped by path.
     *
     * The aliases of the Domain Path module for the domain are used first.
     *
     * @param array<int, string> $paths System paths, e.g. "/taxonomy/term/5"
     * @param string $domain ID of the Domain Access domain whose aliases are used first
     * @return Collection<int|string, mixed>
     */
    public function aliases( array $paths, string $domain = '' ): Collection
    {
        $aliases = $this->siteAliases( $paths );

        if( $domain === '' || !$this->has( 'domain_path' ) ) {
            return $aliases;
        }

        $column = $this->hasColumn( 'domain_path', 'language' ) ? 'language' : 'langcode';
        $rows = Database::chunked( $paths, fn( $chunk ) => $this->db->table( 'domain_path' )
            ->where( 'domain_id', $domain )
            ->whereIn( 'source', $chunk )
            ->where( 'alias', '!=', '' )
            ->orderBy( 'id', 'desc' )
            ->get( ['source as path', 'alias', $column . ' as langcode'] )
        )->groupBy( 'path' );

        foreach( $rows as $path => $list ) {
            $aliases->put( $path, $list->concat( Collection::wrap( $aliases->get( $path ) ) ) );
        }

        return $aliases;
    }


    /**
     * Fetches the descriptions of the reusable custom blocks in all languages.
     *
     * Inline blocks of Layout Builder aren't reusable and are rendered into the content of their pages.
     *
     * @return Collection<int, \stdClass> Rows with "id", "langcode", "info" and "default_langcode"
     */
    public function blockInfos(): Collection
    {
        if( !$this->has( 'block_content_field_data' ) ) {
            return Collection::make();
        }

        return $this->db->table( 'block_content_field_data' )
            ->when( $this->hasColumn( 'block_content_field_data', 'reusable' ), fn( $query ) => $query->where( 'reusable', 1 ) )
            ->get( ['id', 'langcode', 'info', 'default_langcode'] );
    }


    /**
     * Fetches the book outlines.
     *
     * @return Collection<int, \stdClass> Rows with "nid", "bid", "pid" and "weight"
     */
    public function books(): Collection
    {
        return $this->db->table( 'book' )->get( ['nid', 'bid', 'pid', 'weight'] );
    }


    /**
     * Fetches the last modification timestamps of the nodes including their translations and revisions.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, int> Timestamps by node ID
     */
    public function changed( array $nids ): array
    {
        $changed = [];

        foreach( ['node_field_data', 'node_field_revision'] as $table )
        {
            if( !$this->hasColumn( $table, 'changed' ) ) {
                continue;
            }

            $rows = Database::chunked( $nids, fn( $ids ) => $this->db->table( $table )->whereIn( 'nid', $ids )
                ->groupBy( 'nid' )->select( 'nid', DB::raw( 'MAX(changed) AS changed' ) )->get() );

            foreach( $rows as $row ) {
                $changed[(int) $row->nid] = max( $changed[(int) $row->nid] ?? 0, (int) $row->changed );
            }
        }

        return $changed;
    }


    /**
     * Returns the UUIDs of the custom blocks changed since the time.
     *
     * @param int $since Unix timestamp
     * @return array<int, string> Block UUIDs
     */
    public function changedBlocks( int $since ): array
    {
        if( !$this->hasColumn( 'block_content_field_data', 'changed' ) || !$this->has( 'block_content' ) ) {
            return [];
        }

        $ids = $this->db->table( 'block_content_field_data' )->where( 'changed', '>=', $since )->distinct()->pluck( 'id' )->all();

        return Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'block_content' )->whereIn( 'id', $chunk )->select( 'uuid' )->get() )
            ->pluck( 'uuid' )->map( strval( ... ) )->all();
    }


    /**
     * Fetches the links of the menu changed since the time.
     *
     * @param string $menu Menu name, e.g. "main"
     * @param int $since Unix timestamp
     * @return Collection<int, \stdClass>
     */
    public function changedLinks( string $menu, int $since ): Collection
    {
        if( !$this->hasColumn( 'menu_link_content_data', 'changed' ) ) {
            return Collection::make();
        }

        return $this->db->table( 'menu_link_content_data' )->where( 'menu_name', $menu )->where( 'changed', '>=', $since )->get();
    }


    /**
     * Returns the Drupal configuration used by the importer from the config table keyed by name.
     *
     * Translations are keyed by collection and name, e.g. "language.de:field.field.node.page.field_date".
     *
     * @return array<string, array<string, mixed>>
     */
    public function configs(): array
    {
        if( $this->configs !== null ) {
            return $this->configs;
        }

        $configs = [];

        if( !$this->has( 'config' ) ) {
            return $this->configs = $configs;
        }

        $rows = $this->db->table( 'config' )
            ->where( fn( $query ) => $query->where( 'collection', '' )->orWhere( 'collection', 'like', 'language.%' ) )
            ->where( fn( $query ) => $query->where( 'name', 'like', 'field.%' )
                ->orWhere( 'name', 'like', 'core.entity_view_display.%' )
                ->orWhere( 'name', 'like', 'filter.format.%' )
                ->orWhere( 'name', 'language.negotiation' )
                ->orWhere( 'name', 'system.site' )
                ->orWhere( 'name', 'system.date' )
                ->orWhere( 'name', 'system.theme' )
                ->orWhere( 'name', 'like', 'block.block.%' )
                ->orWhere( 'name', 'like', 'views.view.%' )
                ->orWhere( 'name', 'like', 'user.role.%' )
                ->orWhere( 'name', 'nodeaccess.settings' )
                ->orWhere( 'name', 'permissions_by_term.settings' )
                ->orWhere( 'name', 'like', 'metatag.metatag_defaults.%' )
                ->orWhere( 'name', 'like', 'media.type.%' )
                ->orWhere( 'name', 'like', 'domain.record.%' )
                ->orWhere( 'name', 'like', 'domain.config.%' ) )
            ->get();

        foreach( $rows as $row )
        {
            if( is_array( $data = Database::unserialize( $row->data ) ) ) {
                $configs[( $row->collection ?? '' ) !== '' ? $row->collection . ':' . $row->name : (string) $row->name] = $data;
            }
        }

        return $this->configs = $configs;
    }


    /**
     * Returns the group IDs of the roles of the Content Access module by role ID.
     *
     * @return array<int|string, mixed>
     */
    public function contentAccessGids(): array
    {
        $state = $this->has( 'key_value' ) ? $this->db->table( 'key_value' )
            ->where( 'collection', 'state' )->where( 'name', 'content_access_roles_gids' )->value( 'value' ) : null;

        return Database::unserializeArray( $state );
    }


    /**
     * Returns the name of the menu used for the page hierarchy if no other menu is configured.
     */
    public function defaultMenu(): string
    {
        return 'main';
    }


    /**
     * Fetches the revisions which are newer than the current revisions of the nodes ordered by revision ID.
     *
     * @param array<int, int> $nids Node IDs
     * @return Collection<int, \stdClass> Rows with "nid", "langcode", "vid" and "title"
     */
    public function drafts( array $nids ): Collection
    {
        return Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'node_field_revision as r' )
            ->join( 'node_field_data as d', fn( $join ) => $join->on( 'd.nid', '=', 'r.nid' )->on( 'd.langcode', '=', 'r.langcode' ) )
            ->whereIn( 'r.nid', $chunk )
            ->whereColumn( 'r.vid', '>', 'd.vid' )
            ->when( $this->hasColumn( 'node_field_revision', 'revision_translation_affected' ), fn( $query ) => $query->where( 'r.revision_translation_affected', 1 ) )
            ->orderBy( 'r.vid' )
            ->get( ['r.nid', 'r.langcode', 'r.vid', 'r.title'] )
        );
    }


    /**
     * Fetches the records of the entities in all languages.
     *
     * @param string $table Base or data table of the entity type, e.g. "node_field_data"
     * @param string $column ID or UUID column
     * @param array<int, int|string> $values IDs or UUIDs of the entities
     * @return Collection<int, \stdClass>
     */
    public function entities( string $table, string $column, array $values ): Collection
    {
        // only the used columns of the terms are fetched because their descriptions can be large
        $columns = $table === 'taxonomy_term_field_data'
            ? ['tid', 'langcode', 'name', 'default_langcode', ...( $this->hasColumn( $table, 'status' ) ? ['status'] : [] )]
            : ['*'];

        return $this->db->table( $table )->whereIn( $column, $values )->get( $columns );
    }


    /**
     * Fetches the field rows of the entities ordered by delta.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * @param array<int, int|string> $ids Entity or revision IDs
     * @param string $column "entity_id" or "revision_id"
     * @return Collection<int, \stdClass>
     */
    public function fieldRows( string $entityType, string $field, array $ids, string $column = 'entity_id' ): Collection
    {
        $uuid = (string) ( $this->configs()["field.storage.{$entityType}.{$field}"]['uuid'] ?? '' );

        return $this->db->table( self::table( $entityType, $field, $column === 'revision_id', $uuid ) )
            ->where( 'deleted', 0 )
            ->whereIn( $column, $ids )
            ->orderBy( 'delta', 'asc' )
            ->get();
    }


    /**
     * Fetches the view grants of the nodes by node ID.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, array<int, \stdClass>>|false Grants by node ID or FALSE if no node access module is used
     */
    public function grants( array $nids ): array|false
    {
        if( !$this->restricted() ) {
            return false;
        }

        $grants = [];
        $rows = Database::chunked( $nids, fn( $ids ) => $this->db->table( 'node_access' )
            ->whereIn( 'nid', $ids )->where( 'grant_view', '>', 0 )->get( ['nid', 'langcode', 'gid', 'realm'] ) );

        foreach( $rows as $row ) {
            $grants[(int) $row->nid][] = $row;
        }

        return $grants;
    }


    /**
     * Tests if the database contains the table.
     */
    public function has( string $table ): bool
    {
        return $this->tables[$table] ??= $this->db->getSchemaBuilder()->hasTable( $table );
    }


    /**
     * Tests if the table exists and contains the column.
     */
    public function hasColumn( string $table, string $column ): bool
    {
        return $this->tables["{$table}.{$column}"] ??= $this->has( $table ) && $this->db->getSchemaBuilder()->hasColumn( $table, $column );
    }


    /**
     * Fetches the enabled links of the menu ordered by weight and title.
     *
     * @param string $name Menu name, e.g. "main"
     * @return Collection<int, \stdClass>
     */
    public function menu( string $name ): Collection
    {
        if( !$this->has( 'menu_link_content_data' ) ) {
            return Collection::make();
        }

        return $this->db->table( 'menu_link_content_data' )
            ->where( 'menu_name', $name )
            ->where( 'enabled', 1 )
            ->orderBy( 'weight', 'asc' )
            ->orderBy( 'title', 'asc' )
            ->get();
    }


    /**
     * Fetches the nodes of the content types in the default language ordered by creation time.
     *
     * @param array<int, string> $types Content types, e.g. "page"
     * @param bool $unpublished TRUE to fetch unpublished nodes too
     * @param string $domain ID of the Domain Access domain whose nodes are fetched
     * @return Collection<int, \stdClass>
     */
    public function nodes( array $types, bool $unpublished, string $domain = '' ): Collection
    {
        return $this->db->table( 'node_field_data' )
            ->where( 'default_langcode', 1 )
            ->when( !$unpublished, fn( $query ) => $this->published( $query ) )
            ->whereIn( 'type', $types )
            ->when( $domain !== '', fn( $query ) => $this->domainNodes( $query, $domain ) )
            ->orderBy( 'created', 'asc' )
            ->get( $this->nodeColumns() );
    }


    /**
     * Fetches the enabled redirects of the Redirect module.
     *
     * The redirects are read in chunks, so all of them aren't kept in memory at once.
     *
     * @return iterable<int, \stdClass> Rows with "redirect_source__path", "redirect_redirect__uri" and "language"
     */
    public function redirects(): iterable
    {
        return $this->db->table( 'redirect' )
            ->when( $this->hasColumn( 'redirect', 'enabled' ), fn( $query ) => $query->where( 'enabled', 1 ) )
            ->select( ['rid', 'language', 'redirect_source__path', 'redirect_redirect__uri'] )->lazyById( 1000, 'rid' );
    }


    /**
     * Returns the IDs of the nodes which reference terms, media or custom blocks changed since the time.
     *
     * Paragraphs which reference them are followed up to the nodes they belong to.
     *
     * @param int $since Unix timestamp
     * @param array<string, array{0: string, 1: string, 2: string}> $fields Entity type, field name and "block_content", "media" or "text" by field table
     * @return array<int, int> Node IDs
     */
    public function referencing( int $since, array $fields ): array
    {
        // the IDs are kept as keys because a changed term or block can be referenced by many nodes
        $ids = ['node' => [], 'paragraph' => []];

        if( $this->hasColumn( 'taxonomy_term_field_data', 'changed' ) && $this->has( 'taxonomy_index' ) )
        {
            $tids = $this->db->table( 'taxonomy_term_field_data' )->where( 'changed', '>=', $since )->distinct()->pluck( 'tid' )->all();
            $ids['node'] = $this->distinctIds( $ids['node'], $tids, fn( $chunk ) => $this->db->table( 'taxonomy_index' )->whereIn( 'tid', $chunk ), 'nid' );
        }

        $mids = $this->hasColumn( 'media_field_data', 'changed' )
            ? $this->db->table( 'media_field_data' )->where( 'changed', '>=', $since )->distinct()->pluck( 'mid' )->all()
            : [];
        $uuids = $mids && $this->has( 'media' )
            ? Database::chunked( $mids, fn( $chunk ) => $this->db->table( 'media' )->whereIn( 'mid', $chunk )->select( 'uuid' )->get() )->pluck( 'uuid' )->map( strval( ... ) )->all()
            : [];

        // custom blocks referenced by fields are shared elements updated when a referencing node is imported
        $bids = $this->hasColumn( 'block_content_field_data', 'changed' )
            ? $this->db->table( 'block_content_field_data' )->where( 'changed', '>=', $since )->distinct()->pluck( 'id' )->all()
            : [];

        foreach( $mids || $bids ? $fields : [] as $table => [$type, $field, $target] )
        {
            if( !isset( $ids[$type] ) || !$this->has( $table ) ) {
                continue;
            }

            if( $target === 'block_content' || $target === 'media' ) {
                $ids[$type] = $this->distinctIds( $ids[$type], $target === 'media' ? $mids : $bids,
                    fn( $chunk ) => $this->db->table( $table )->whereIn( "{$field}_target_id", $chunk ), 'entity_id' );
                continue;
            }

            foreach( $this->embedding( $table, $field, $uuids ) as $id ) {
                $ids[$type][(int) $id] = true;
            }
        }

        // paragraphs can be nested in other paragraphs
        $pids = array_keys( $ids['paragraph'] );

        for( $level = 0; $pids && $level < 10 && $this->has( 'paragraphs_item_field_data' ); $level++ )
        {
            $rows = Database::chunked( $pids, fn( $chunk ) => $this->db->table( 'paragraphs_item_field_data' )->whereIn( 'id', $chunk )
                ->select( 'parent_id', 'parent_type' )->distinct()->get() );
            $pids = [];

            foreach( $rows as $row )
            {
                if( $row->parent_type === 'node' ) {
                    $ids['node'][(int) $row->parent_id] = true;
                } elseif( $row->parent_type === 'paragraph' ) {
                    $pids[] = $row->parent_id;
                }
            }
        }

        unset( $ids['node'][0] );

        return array_keys( $ids['node'] );
    }


    /**
     * Adds the distinct IDs selected for each chunk of the values to the IDs.
     *
     * @param array<int, bool> $ids IDs as keys
     * @param array<int, int|string> $values Values the query is restricted to
     * @param \Closure(array<int, int|string>): Builder $query Returns the query for a chunk of the values
     * @param string $column Column of the IDs
     * @return array<int, bool> IDs as keys including the selected ones
     */
    protected function distinctIds( array $ids, array $values, \Closure $query, string $column ): array
    {
        foreach( array_chunk( array_values( array_unique( $values ) ), \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            foreach( $query( $chunk )->distinct()->pluck( $column ) as $id ) {
                $ids[(int) $id] = true;
            }
        }

        return $ids;
    }


    /**
     * Fetches the IDs of the published terms of the vocabularies ordered by ID.
     *
     * @param array<int, string> $vocabularies Vocabulary machine names, e.g. "tags"
     * @return array<int, int> Term IDs
     */
    public function termIds( array $vocabularies ): array
    {
        return $this->db->table( 'taxonomy_term_field_data' )->where( 'default_langcode', 1 )->whereIn( 'vid', $vocabularies )
            ->when( $this->hasColumn( 'taxonomy_term_field_data', 'status' ), fn( $query ) => $query->where( 'status', 1 ) )
            ->orderBy( 'tid' )->pluck( 'tid' )->map( fn( $tid ) => (int) $tid )->all();
    }


    /**
     * Streams the nodes tagged with the terms, sticky and newest first.
     *
     * The rows are streamed instead of fetched at once because terms can tag lots of nodes. If the rows
     * are limited, each term is fetched on its own so not more rows than the limit are transferred per term.
     *
     * @param array<int, int> $tids Term IDs
     * @param int|null $limit Maximum number of rows fetched per term or NULL for all
     * @return LazyCollection<int, \stdClass> Rows with "tid" and "nid"
     */
    public function termIndex( array $tids, ?int $limit = null ): LazyCollection
    {
        return LazyCollection::make( function() use ( $tids, $limit ) {
            $tids = array_values( array_unique( $tids ) );

            foreach( $limit !== null ? array_chunk( $tids, 1 ) : array_chunk( $tids, \Aimeos\Cms\DB::IN_SIZE ) as $ids )
            {
                foreach( $this->db->table( 'taxonomy_index' )->whereIn( 'tid', $ids )->orderByDesc( 'sticky' )->orderByDesc( 'created' )
                    ->orderBy( 'nid' )->when( $limit, fn( $query, $limit ) => $query->limit( $limit ) )->select( ['tid', 'nid'] )->cursor() as $row ) {
                    yield $row;
                }
            }
        } );
    }


    /**
     * Returns the role IDs of the Permissions by Term module for each term of the nodes.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, array<string, array<int, array<int, string>>>> Lists of role IDs by node ID and language code, "" if the permissions aren't translated
     */
    public function termRoles( array $nids ): array
    {
        if( !$this->has( 'permissions_by_term_role' ) || !$this->has( 'taxonomy_index' ) ) {
            return [];
        }

        $lang = $this->hasColumn( 'permissions_by_term_role', 'langcode' );
        $rows = Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'permissions_by_term_role as p' )
            ->join( 'taxonomy_index as t', 't.tid', '=', 'p.tid' )->whereIn( 't.nid', $chunk )
            ->get( array_merge( ['t.nid', 'p.tid', 'p.rid'], $lang ? ['p.langcode'] : [] ) ) );
        $map = [];

        foreach( $rows as $row ) {
            $map[(int) $row->nid][$lang ? (string) $row->langcode : ''][(int) $row->tid][] = (string) $row->rid;
        }

        return array_map( fn( $langs ) => array_map( 'array_values', $langs ), $map );
    }


    /**
     * Fetches the terms in the default language ordered by ID.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    public function terms( array $tids ): Collection
    {
        return Database::chunked( $tids, fn( $ids ) => $this->db->table( 'taxonomy_term_field_data' )
            ->where( 'default_langcode', 1 )->whereIn( 'tid', $ids )->orderBy( 'tid' )->get() );
    }


    /**
     * Fetches the published translations of the terms ordered by language.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    public function termTranslations( array $tids ): Collection
    {
        return Database::chunked( $tids, fn( $ids ) => $this->db->table( 'taxonomy_term_field_data' )
            ->where( 'default_langcode', 0 )->whereIn( 'tid', $ids )
            ->when( $this->hasColumn( 'taxonomy_term_field_data', 'status' ), fn( $query ) => $query->where( 'status', 1 ) )
            ->orderBy( 'langcode' )->get() );
    }


    /**
     * Returns the tracker which detects the changes by the modification dates.
     *
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    public function tracker( string $menu, \Closure $nodeId ): DrupalTracker
    {
        return new Drupal8Tracker( $this, $menu, $nodeId );
    }


    /**
     * Fetches the translations of the nodes ordered by language.
     *
     * @param array<int, int> $nids Node IDs
     * @param bool $unpublished TRUE to fetch unpublished translations too
     * @return Collection<int, \stdClass>
     */
    public function translations( array $nids, bool $unpublished ): Collection
    {
        return Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'node_field_data' )
            ->where( 'default_langcode', 0 )
            ->when( !$unpublished, fn( $query ) => $this->published( $query ) )
            ->whereIn( 'nid', $chunk )
            ->orderBy( 'langcode', 'asc' )
            ->get( $this->nodeColumns() )
        );
    }


    /**
     * Restricts the query to the nodes of the Domain Access domain or of all domains.
     *
     * @param Builder $query Query of the node_field_data table
     * @param string $domain ID of the Domain Access domain
     */
    protected function domainNodes( Builder $query, string $domain ): Builder
    {
        $all = $this->has( 'node__field_domain_all_affiliates' );

        return $query->where( fn( $query ) => $query
            ->whereIn( 'nid', $this->db->table( 'node__field_domain_access' )->where( 'deleted', 0 )
                ->where( 'field_domain_access_target_id', $domain )->select( 'entity_id' ) )
            ->when( $all, fn( $query ) => $query->orWhereIn( 'nid', $this->db->table( 'node__field_domain_all_affiliates' )->where( 'deleted', 0 )
                ->where( 'field_domain_all_affiliates_value', 1 )->select( 'entity_id' ) ) ) );
    }


    /**
     * Returns the IDs of the entities whose texts contain one of the UUIDs.
     *
     * A few UUIDs are searched by the database. For more, the texts are read once and searched
     * here because each search for a few UUIDs scans the whole table again. The texts are read for
     * TEXT_SIZE entities at a time because the database client may buffer the whole result.
     *
     * @param string $table Field table, e.g. "node__body"
     * @param string $field Field name, e.g. "body"
     * @param array<int, string> $uuids UUIDs of the embedded entities
     * @return array<int, int> Entity IDs
     */
    protected function embedding( string $table, string $field, array $uuids ): array
    {
        if( count( $uuids ) <= self::LIKE_SIZE )
        {
            return $uuids ? $this->db->table( $table )->where( function( $query ) use ( $uuids, $field ) {
                foreach( $uuids as $uuid ) {
                    $query->orWhere( "{$field}_value", 'like', "%{$uuid}%" );
                }
            } )->pluck( 'entity_id' )->all() : [];
        }

        // the words in the texts are looked up instead of testing each text for all UUIDs
        $set = array_flip( array_map( strtolower( ... ), $uuids ) );
        $ids = [];

        $deleted = $this->hasColumn( $table, 'deleted' );
        $last = null;

        do
        {
            // the next entities are fetched first so the texts of an entity aren't split between two chunks
            $eids = $this->db->table( $table )
                ->when( $deleted, fn( $query ) => $query->where( 'deleted', 0 ) )
                ->when( $last !== null, fn( $query ) => $query->where( 'entity_id', '>', $last ) )
                ->orderBy( 'entity_id' )->distinct()->limit( static::TEXT_SIZE )->pluck( 'entity_id' )->all();

            $found = [];

            foreach( $eids ? $this->db->table( $table )->whereIn( 'entity_id', $eids )
                ->when( $deleted, fn( $query ) => $query->where( 'deleted', 0 ) )
                ->select( 'entity_id', "{$field}_value" )->cursor() : [] as $row )
            {
                // entities with several texts are only searched until one contains a UUID
                if( isset( $found[$row->entity_id] ) ) {
                    continue;
                }

                preg_match_all( '/[\w-]+/', (string) $row->{$field . '_value'}, $matches );

                foreach( $matches[0] as $uuid )
                {
                    if( isset( $set[strtolower( $uuid )] ) ) {
                        $found[$row->entity_id] = true;
                        $ids[] = $row->entity_id;
                        break;
                    }
                }
            }

            $last = end( $eids );
        }
        while( count( $eids ) === static::TEXT_SIZE );

        return $ids;
    }


    /**
     * Returns the existing columns of the node_field_data table used by the import.
     *
     * @return array<int, string> Column names
     */
    protected function nodeColumns(): array
    {
        return $this->nodeColumns ??= array_values( array_intersect( self::NODE_COLUMNS, $this->db->getSchemaBuilder()->getColumnListing( 'node_field_data' ) ) );
    }


    /**
     * Restricts the query to the published nodes and the ones scheduled for publishing.
     *
     * @param Builder $query Query of the node_field_data table
     */
    protected function published( Builder $query ): Builder
    {
        $scheduled = $this->hasColumn( 'node_field_data', 'publish_on' );

        return $query->where( fn( $query ) => $query->where( 'status', 1 )
            ->when( $scheduled, fn( $query ) => $query->orWhere( 'publish_on', '>', time() ) ) );
    }


    /**
     * Fetches the URL aliases of the system paths in all languages grouped by path.
     *
     * @param array<int, string> $paths System paths, e.g. "/taxonomy/term/5"
     * @return Collection<int|string, mixed>
     */
    protected function siteAliases( array $paths ): Collection
    {
        if( $this->has( 'path_alias' ) ) {
            return Database::chunked( $paths, fn( $chunk ) => $this->db->table( 'path_alias' )
                ->where( 'status', 1 )
                ->whereIn( 'path', $chunk )
                ->orderBy( 'id', 'desc' )
                ->get( ['path', 'alias', 'langcode'] )
            )->groupBy( 'path' );
        }

        // Drupal 8.0-8.7 stores the aliases in url_alias with leading slashes
        if( $this->has( 'url_alias' ) ) {
            return Database::chunked( $paths, fn( $chunk ) => $this->db->table( 'url_alias' )
                ->whereIn( 'source', $chunk )
                ->orderBy( 'pid', 'desc' )
                ->get( ['source as path', 'alias', 'langcode'] )
            )->groupBy( 'path' );
        }

        return Collection::make();
    }
}
