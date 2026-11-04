<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads a Drupal 7 database and returns the records and configuration in the format of Drupal 8+.
 *
 * Nodes of the Content Translation module become translations of their source node, translations of the
 * Entity Translation module (with titles of the Title module) translations of the node. Field collections
 * and paragraphs become paragraphs, the custom blocks of the default theme block placements.
 */
class Drupal7 extends DrupalReader
{
    /** Maximum number of cached file records, texts, node titles and schedules */
    protected const CACHE_SIZE = 10000;
    /** Columns of the node table used for the node records */
    protected const NODE_COLUMNS = ['nid', 'vid', 'type', 'title', 'uid', 'status', 'promote', 'sticky', 'created', 'changed'];

    /** Number added to the IDs of the field collection items so they don't collide with the IDs of the paragraphs */
    protected ?int $offset = null;
    /** @var array<int, int>|null Target node ID by Drupal 7 node ID */
    protected ?array $nodes = null;
    /** @var array<int, string> Languages of the Drupal 7 nodes which aren't in the default language */
    protected array $langcodes = [];
    /** @var array<int, true> Drupal 7 node IDs in translation sets */
    protected array $sets = [];
    /** @var array<int, array<int, int>> Drupal 7 node IDs by target node ID, only for target nodes with translation nodes */
    protected array $sources = [];
    /** @var array<string, array{type: string, settings: array<int|string, mixed>}>|null Drupal 7 field types and settings by field name */
    protected ?array $fields = null;
    /** @var array<string, array{type: string, storage: array{type: string, settings: array<string, mixed>, columns: array<string, string>}, columns: array<string, string>, time: bool}> */
    protected array $infos = [];
    /** @var array<int, array<string, string>> Node titles of the Title module by Drupal 7 node ID and language */
    protected array $titles = [];
    /** @var array<int, \stdClass|null> Records of the Scheduler module by Drupal 7 node ID */
    protected array $schedules = [];
    /** @var array<int, int>|null Term ID of the term in the default language by ID of its translated terms of the i18n_taxonomy module */
    protected ?array $termMap = null;
    /** @var array<int, array<int, int>>|null IDs of the translated terms of the i18n_taxonomy module by ID of the term in the default language */
    protected ?array $termSources = null;
    /** @var array<string, Collection<int, \stdClass>> Menu links by menu name */
    protected array $menus = [];
    /** @var array<string, array<int, string>> Alternative texts and titles of the File Entity module by field name and file ID */
    protected array $fileTexts = [];
    /** @var array<int|string, string>|null Vocabulary machine names by vocabulary ID */
    protected ?array $vocabularies = null;
    /** @var array<string, array<int|string, mixed>>|null Settings of the field instances by entity type, bundle and field name joined by dots */
    protected ?array $instances = null;
    /** @var array<string, array<string, mixed>> Configuration in the format of Drupal 8 by name */
    protected array $configs = [];
    /** TRUE if the configuration has been built */
    protected bool $configured = false;
    /** @var array<int, \stdClass|null> file_managed records by file ID */
    protected array $files = [];
    /** Reader for the custom blocks and the blocks of the Bean module */
    protected ?Drupal7Blocks $blocks = null;
    /** Reader for the Panelizer displays and the custom content panes */
    protected ?Drupal7Panels $panels = null;
    /** Reader for the block placements of the block table and the Context module */
    protected ?Drupal7Placements $placements = null;
    /** Tables, variables, locales and records of the Drupal 7 database */
    protected Drupal7Db $d7;


    /**
     * Initializes the reader.
     *
     * @param Connection $db Drupal database connection
     * @param \Closure(string): void $warn Shows a warning
     */
    public function __construct( Connection $db, \Closure $warn )
    {
        parent::__construct( $db, $warn );
        $this->d7 = new Drupal7Db( $db );
    }


    /**
     * Tests if the database is a Drupal 7 database.
     */
    public static function is( Connection $conn ): bool
    {
        $schema = $conn->getSchemaBuilder();
        return $schema->hasTable( 'node' ) && $schema->hasTable( 'field_config_instance' );
    }


    /**
     * Fetches the URL aliases of the system paths in all languages grouped by path.
     *
     * The aliases of translation nodes become aliases of their source nodes.
     *
     * @param array<int, string> $paths System paths, e.g. "/node/1"
     * @param string $domain Unused because the Domain Path module isn't supported for Drupal 7
     * @return Collection<int|string, mixed>
     */
    public function aliases( array $paths, string $domain = '' ): Collection
    {
        if( !$this->d7->exists( 'url_alias' ) ) {
            return Collection::make();
        }

        $this->map();
        $sources = [];

        foreach( $paths as $path )
        {
            $path = trim( $path, '/' );

            if( preg_match( '/^node\/(\d+)$/', $path, $m ) )
            {
                foreach( $this->sources[(int) $m[1]] ?? [(int) $m[1]] as $nid ) {
                    $sources[] = 'node/' . $nid;
                }
            }
            elseif( preg_match( '/^taxonomy\/term\/(\d+)$/', $path, $m ) )
            {
                foreach( [(int) $m[1], ...$this->termSources()[(int) $m[1]] ?? []] as $tid ) {
                    $sources[] = 'taxonomy/term/' . $tid;
                }
            }
            else
            {
                $sources[] = $path;
            }
        }

        return Database::chunked( $sources, fn( $chunk ) => $this->db->table( 'url_alias' )->whereIn( 'source', $chunk )->orderBy( 'pid', 'desc' )->get() )
            ->map( fn( $row ) => $this->d7->record( [
                'id' => (int) $row->pid,
                'path' => '/' . $this->path( trim( (string) $row->source, '/' ) ),
                'alias' => '/' . trim( (string) $row->alias, '/' ),
                'langcode' => (string) ( $row->language ?? '' ) ?: 'und',
            ] ) )
            ->sortByDesc( 'id' )
            ->groupBy( 'path' );
    }


    /**
     * Returns the descriptions of the custom blocks.
     *
     * @return Collection<int, \stdClass> Rows with "id", "langcode", "info" and "default_langcode"
     */
    public function blockInfos(): Collection
    {
        return $this->blocks()->rows( 'id', [] );
    }


    /**
     * Returns the book outlines, the parent is the node of the parent menu link of the book page.
     *
     * @return Collection<int, \stdClass> Rows with "nid", "bid", "pid" and "weight"
     */
    public function books(): Collection
    {
        if( !$this->d7->exists( 'book' ) || !$this->d7->exists( 'menu_links' ) ) {
            return Collection::make();
        }

        $map = $this->map();

        return $this->db->table( 'book as b' )->join( 'menu_links as l', 'l.mlid', '=', 'b.mlid' )
            ->leftJoin( 'menu_links as p', 'p.mlid', '=', 'l.plid' )
            ->orderBy( 'b.nid' )
            ->get( ['b.nid', 'b.bid', 'l.weight', 'p.link_path as parent'] )
            ->filter( fn( $row ) => isset( $map[(int) $row->nid] ) )
            ->map( fn( $row ) => $this->d7->record( [
                'nid' => $this->nid( (int) $row->nid ),
                'bid' => $this->nid( (int) $row->bid ),
                'pid' => preg_match( '/^node\/(\d+)$/', (string) $row->parent, $m ) ? $this->nid( (int) $m[1] ) : 0,
                'weight' => (int) $row->weight,
            ] ) )
            ->values();
    }


    /**
     * Returns the last modification timestamps of the nodes including their translations.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, int> Timestamps by node ID
     */
    public function changed( array $nids ): array
    {
        $changed = [];

        foreach( Database::chunked( $this->ids( $nids ), fn( $chunk ) => $this->db->table( 'node' )->whereIn( 'nid', $chunk )->get( ['nid', 'changed'] ) ) as $row )
        {
            $nid = $this->nid( (int) $row->nid );
            $changed[$nid] = max( $changed[$nid] ?? 0, (int) $row->changed );
        }

        // newer revisions are imported as drafts, older ones aren't relevant
        if( $this->d7->exists( 'node_revision' ) )
        {
            $rows = Database::chunked( $this->ids( $nids ), fn( $chunk ) => $this->db->table( 'node_revision as r' )
                ->join( 'node as n', 'n.nid', '=', 'r.nid' )->whereIn( 'r.nid', $chunk )->whereColumn( 'r.vid', '>', 'n.vid' )
                ->groupBy( 'r.nid' )->select( 'r.nid', $this->db->raw( 'MAX(r.timestamp) AS changed' ) )->get() );

            foreach( $rows as $row )
            {
                $nid = $this->nid( (int) $row->nid );
                $changed[$nid] = max( $changed[$nid] ?? 0, (int) $row->changed );
            }
        }

        if( $this->d7->exists( 'entity_translation' ) )
        {
            $rows = Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'entity_translation' )
                ->where( 'entity_type', 'node' )->whereIn( 'entity_id', $chunk )->get( ['entity_id', 'changed'] ) );

            foreach( $rows as $row ) {
                $changed[(int) $row->entity_id] = max( $changed[(int) $row->entity_id] ?? 0, (int) $row->changed );
            }
        }

        return $changed;
    }


    /**
     * Returns the configuration used by the importer in the format of Drupal 8.
     *
     * The configuration is only built once.
     * @return array<string, array<string, mixed>> Configuration by name, e.g. "system.site"
     */
    public function configs(): array
    {
        if( $this->configured ) {
            return $this->configs;
        }

        $this->configs = [];

        $this->languages();
        $this->site();
        $this->fieldConfigs();
        $this->configs = $this->placements()->configs( $this->blocks()->configs( $this->panels()->configs( $this->configs ) ) );
        $this->metatagConfigs();
        $this->formats();
        $this->views();
        $this->roles();
        $this->domains();
        $this->configured = true;

        return $this->configs;
    }


    /**
     * Returns the grant IDs of the roles by role ID.
     *
     * The Content Access and Nodeaccess modules use the Drupal 7 role IDs as grant IDs.
     *
     * @return array<string, int> Grant IDs by role ID
     */
    public function contentAccessGids(): array
    {
        if( !$this->d7->exists( 'role' ) ) {
            return [];
        }

        return $this->db->table( 'role' )->get( ['rid', 'name'] )
            ->mapWithKeys( fn( $row ) => [$this->d7->role( (int) $row->rid, (string) $row->name ) => (int) $row->rid] )->all();
    }


    /**
     * Returns the name of the menu used for the page hierarchy if no other menu is configured.
     */
    public function defaultMenu(): string
    {
        return 'main-menu';
    }


    /**
     * Returns the newest revisions of the nodes which are newer than their current revisions.
     *
     * The current revision of the Workbench Moderation or State Flow module is used if available,
     * otherwise the newest revision, e.g. a pending revision of the Revisioning module.
     *
     * @param array<int, int> $nids Node IDs
     * @return Collection<int, \stdClass> Rows with "nid", "langcode", "vid" and "title" ordered by revision ID
     */
    public function drafts( array $nids ): Collection
    {
        if( !$this->d7->exists( 'node_revision' ) ) {
            return Collection::make();
        }

        $map = $this->map();
        $ids = $this->ids( $nids );
        $current = Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'node' )->whereIn( 'nid', $chunk )->get( ['nid', 'vid'] ) )->pluck( 'vid', 'nid' );

        $rows = match( true ) {
            $this->d7->exists( 'workbench_moderation_node_history' ) => Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'workbench_moderation_node_history' )
                ->whereIn( 'nid', $chunk )->where( 'current', 1 )->get( ['nid', 'vid'] ) ),
            $this->d7->exists( 'state_flow_states' ) => Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'state_flow_states' )
                ->where( 'entity_type', 'node' )->whereIn( 'entity_id', $chunk )->where( 'status', 1 )->get( ['entity_id as nid', 'revision_id as vid'] ) ),
            default => Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'node_revision' )->whereIn( 'nid', $chunk )
                ->groupBy( 'nid' )->select( 'nid', $this->db->raw( 'MAX(vid) AS vid' ) )->get() ),
        };

        $vids = $rows->filter( fn( $row ) => (int) $row->vid > (int) $current->get( $row->nid ) )->pluck( 'vid' )->map( fn( $id ) => (int) $id )->all();
        $titles = $this->d7->exists( 'field_revision_title_field' )
            ? Database::chunked( $vids, fn( $chunk ) => $this->db->table( 'field_revision_title_field' )->where( 'entity_type', 'node' )
                ->whereIn( 'revision_id', $chunk )->where( 'deleted', 0 )->where( 'delta', 0 )->get() )->groupBy( 'revision_id' )
            : Collection::make();

        return Database::chunked( $vids, fn( $chunk ) => $this->db->table( 'node_revision' )->whereIn( 'vid', $chunk )->get( ['nid', 'vid', 'title'] ) )
            ->filter( fn( $row ) => isset( $map[(int) $row->nid] ) )
            ->sortBy( 'vid' )
            ->map( function( $row ) use ( $titles ) {
                $node = $this->mapping( (int) $row->nid );
                $title = Collection::make( $titles->get( $row->vid ) ?? [] )
                    ->first( fn( $title ) => $this->titleLang( $node, (string) $title->language ) === $node['langcode'] && trim( (string) $title->title_field_value ) !== '' );

                return $this->d7->record( [
                    'nid' => $node['nid'],
                    'langcode' => $node['langcode'],
                    'vid' => (int) $row->vid,
                    'title' => $title ? (string) $title->title_field_value : (string) $row->title,
                ] );
            } )
            ->values();
    }


    /**
     * Returns the records of the entities in all languages in the format of the Drupal 8 tables.
     *
     * @param string $table Drupal 8 base or data table of the entity type, e.g. "node_field_data"
     * @param string $column ID or UUID column
     * @param array<int, int|string> $values IDs or UUIDs of the entities
     * @return Collection<int, \stdClass>
     */
    public function entities( string $table, string $column, array $values ): Collection
    {
        $ids = array_map( intval( ... ), $values );

        return match( $table ) {
            'node_field_data' => $this->nodeRows( $ids ),
            'paragraphs_item_field_data' => $this->paragraphs( $ids ),
            'block_content', 'block_content_field_data' => $this->blocks()->rows( $column, $values ),
            'taxonomy_term_field_data' => $this->termRows( $ids ),
            'file_managed' => $this->db->table( 'file_managed' )->whereIn( $column, $values )->get(),
            'users_field_data' => $this->d7->exists( 'users' )
                ? $this->db->table( 'users' )->whereIn( 'uid', $ids )->get( ['uid', 'name'] )
                    ->map( fn( $row ) => $this->d7->record( ['uid' => (int) $row->uid, 'name' => (string) $row->name, 'langcode' => 'und', 'default_langcode' => 1] ) )
                : Collection::make(),
            default => Collection::make(),
        };
    }


    /**
     * Returns the field rows of the entities in the format of Drupal 8 ordered by delta.
     *
     * @param string $entityType Drupal 8 entity type, i.e. "node", "paragraph" or "block_content"
     * Revisions of custom blocks are the custom content panes of the Panels module.
     *
     * @param string $field Field name, e.g. "body"
     * @param array<int, int|string> $ids Entity IDs or revision IDs
     * @param string $column "entity_id" or "revision_id"
     * @return Collection<int, \stdClass>
     */
    public function fieldRows( string $entityType, string $field, array $ids, string $column = 'entity_id' ): Collection
    {
        $ids = array_map( intval( ... ), $ids );
        $revision = $column === 'revision_id';

        if( $entityType === 'block_content' && $revision ) {
            return $field === 'body' ? $this->panels()->panes( $ids ) : Collection::make();
        }

        // bodies of custom blocks and fields of blocks of the Bean module
        $bodies = $entityType === 'block_content' && $field === 'body' ? $this->blocks()->bodies( $ids ) : Collection::make();

        if( $entityType === 'node' && $field === 'field_meta_tags' && !isset( $this->fields()[$field] ) ) {
            return $this->metatags( $ids, $revision );
        }

        if( $entityType === 'node' && $field === 'layout_builder__layout' ) {
            return $this->panelizer( $ids, $revision );
        }

        if( !( $info = $this->field( $field ) ) || $revision && !$this->d7->exists( 'field_revision_' . $field ) ) {
            return $bodies;
        }

        $query = $this->db->table( ( $revision ? 'field_revision_' : 'field_data_' ) . $field )->where( 'deleted', 0 );
        $offset = $this->offset();

        if( $entityType === 'node' ) {
            $query->where( 'entity_type', 'node' )->whereIn( $column, $revision ? $ids : $this->ids( $ids ) );
        } elseif( $entityType === 'paragraph' ) {
            $query->where( fn( $query ) => $query
                ->where( fn( $query ) => $query->where( 'entity_type', 'paragraphs_item' )->whereIn( 'entity_id', array_filter( $ids, fn( $id ) => $id <= $offset ) ) )
                ->orWhere( fn( $query ) => $query->where( 'entity_type', 'field_collection_item' )
                    ->whereIn( 'entity_id', array_map( fn( $id ) => $id - $offset, array_filter( $ids, fn( $id ) => $id > $offset ) ) ) ) );
        } elseif( $entityType === 'block_content' ) {
            $beans = $this->blocks()->offset();
            $query->where( 'entity_type', 'bean' )->whereIn( 'entity_id', array_map( fn( $id ) => $id - $beans, array_filter( $ids, fn( $id ) => $id > $beans ) ) );
        } else {
            return Collection::make();
        }

        $rows = $query->orderBy( 'entity_id' )->orderBy( 'language' )->orderBy( 'delta' )->get();

        // files of the images and media tags of all rows are fetched at once instead of one by one
        match( $info['type'] ) {
            'image', 'media' => $this->prefetchFiles( $rows->pluck( $field . '_fid' )->map( fn( $id ) => (int) $id )->all(), false ),
            'text', 'text_long', 'text_with_summary' => $this->prefetchFiles( $rows->flatMap( fn( $row ) => $this->tagFids(
                (string) ( $row->{$field . '_value'} ?? '' ) . (string) ( $row->{$field . '_summary'} ?? '' )
            ) )->all() ),
            default => null,
        };

        return $bodies->merge( $rows->map( fn( $row ) => $this->value( $row, $field, $info ) ) )->values();
    }


    /**
     * Returns the view grants of the nodes and their translation nodes by node ID.
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
        $rows = Database::chunked( $this->ids( $nids ), fn( $chunk ) => $this->db->table( 'node_access' )
            ->whereIn( 'nid', $chunk )->where( 'grant_view', '>', 0 )->get( ['nid', 'gid', 'realm'] ) );

        foreach( $rows as $row )
        {
            $node = $this->mapping( (int) $row->nid );
            // the Domain Access module grants access to all visitors of the domains
            $realm = in_array( (string) $row->realm, ['domain_id', 'domain_site'], true ) ? 'all' : (string) $row->realm;
            $grants[$node['nid']][] = $this->d7->record( ['nid' => $node['nid'], 'langcode' => $node['langcode'], 'gid' => (int) $row->gid, 'realm' => $realm] );
        }

        return $grants;
    }


    /**
     * Tests if the Drupal 8 table is available.
     *
     * @param string $table Drupal 8 table, e.g. "node__body"
     */
    public function has( string $table ): bool
    {
        return match( true ) {
            in_array( $table, ['node_field_data', 'config'], true ) => true,
            $table === 'node_field_revision' => $this->d7->exists( 'node_revision' ),
            $table === 'users_field_data' => $this->d7->exists( 'users' ),
            $table === 'node__field_domain_access' => $this->d7->exists( 'domain_access' ) && $this->d7->exists( 'domain' ),
            $table === 'block_content_revision__body' => $this->d7->exists( 'panels_pane' ),
            in_array( $table, ['node__layout_builder__layout', 'node_revision__layout_builder__layout'], true ) => $this->d7->exists( 'panelizer_entity' ) && $this->d7->exists( 'panels_pane' ),
            $table === 'paragraphs_item_field_data' => $this->d7->exists( 'paragraphs_item' ) || $this->d7->exists( 'field_collection_item' ),
            in_array( $table, ['block_content', 'block_content_field_data', 'block_content__body'], true ) => $this->d7->exists( 'block_custom' ) || $this->d7->exists( 'bean' ),
            $table === 'menu_link_content_data' => $this->d7->exists( 'menu_links' ),
            $table === 'path_alias' => $this->d7->exists( 'url_alias' ),
            $table === 'taxonomy_term_field_data' => $this->d7->exists( 'taxonomy_term_data' ),
            $table === 'book' => $this->d7->exists( 'book' ) && $this->d7->exists( 'menu_links' ),
            in_array( $table, ['file_managed', 'node_access', 'taxonomy_index', 'redirect'], true ) => $this->d7->exists( $table ),
            in_array( $table, ['node__field_meta_tags', 'node_revision__field_meta_tags'], true ) && !isset( $this->fields()['field_meta_tags'] ) => $this->d7->exists( 'metatag' ),
            (bool) preg_match( '/^(node|paragraph|block_content)__(\w+)$/', $table, $m ) => isset( $this->fields()[$m[2]] ) && $this->d7->exists( 'field_data_' . $m[2] ),
            (bool) preg_match( '/^node_revision__(\w+)$/', $table, $m ) => isset( $this->fields()[$m[1]] ) && $this->d7->exists( 'field_revision_' . $m[1] ),
            default => false,
        };
    }


    /**
     * Tests if the Drupal 8 table has the column.
     *
     * @param string $table Drupal 8 table, e.g. "node_field_data"
     * @param string $column Column name, e.g. "changed"
     */
    public function hasColumn( string $table, string $column ): bool
    {
        return in_array( "{$table}.{$column}", ['node_field_data.changed', 'node_field_data.vid', 'book.pid'], true );
    }


    /**
     * Returns the enabled links of the menu in the default language and their translations.
     *
     * Links in translation sets of the i18n_menu module become translations of the link in the default
     * language, localized link titles translations of their link. Other links of other languages are skipped.
     *
     * @param string $name Menu name, e.g. "main-menu"
     * @return Collection<int, \stdClass>
     */
    public function menu( string $name ): Collection
    {
        if( isset( $this->menus[$name] ) ) {
            return $this->menus[$name];
        }

        if( !$this->d7->exists( 'menu_links' ) ) {
            return $this->menus[$name] = Collection::make();
        }

        $rows = $this->db->table( 'menu_links' )->where( 'menu_name', $name )->where( 'hidden', 0 )
            ->orderBy( 'weight' )->orderBy( 'link_title' )->get();

        // links of the i18n_menu module can have a language
        $language = $this->d7->column( 'menu_links', 'language' );
        $default = $rows->filter( fn( $row ) => !$language || in_array( (string) $row->language, ['', 'und', $this->d7->langcode()], true ) );
        $sets = $this->d7->column( 'menu_links', 'i18n_tsid' ) ? $default->filter( fn( $row ) => (int) $row->i18n_tsid > 0 )->keyBy( 'i18n_tsid' ) : Collection::make();
        $other = $rows->diffKeys( $default );
        $translations = $other->filter( fn( $row ) => $sets->has( (int) ( $row->i18n_tsid ?? 0 ) ) );

        if( $count = $other->count() - $translations->count() ) {
            ( $this->warn )( "Skipped {$count} menu links of other languages than \"{$this->d7->langcode()}\" which aren't translations of links in \"{$this->d7->langcode()}\"" );
        }

        $list = $default->map( fn( $row ) => $this->menuLink( $row, $this->d7->langcode(), (string) $row->link_title ) )->values()->all();
        $titles = [];

        foreach( $translations as $row )
        {
            if( $link = $sets->get( (int) $row->i18n_tsid ) ) {
                $titles[(int) $link->mlid][(string) $row->language] ??= (string) $row->link_title;
            }
        }

        // link titles localized by the i18n_menu module
        foreach( $this->d7->locales( 'menu', $default->map( fn( $row ) => 'item:' . $row->mlid . ':title' )->values()->all() ) as $context => $langs )
        {
            foreach( $langs as $langcode => $title ) {
                $titles[(int) explode( ':', $context )[1]][$langcode] ??= $title;
            }
        }

        foreach( $default as $row )
        {
            foreach( $titles[(int) $row->mlid] ?? [] as $langcode => $title )
            {
                if( $langcode !== $this->d7->langcode() ) {
                    $list[] = $this->menuLink( $row, $langcode, $title );
                }
            }
        }

        return $this->menus[$name] = Collection::make( $list );
    }


    /**
     * Returns the nodes of the content types in their default language ordered by creation date.
     *
     * @param array<int, string> $types Content types
     * @param bool $unpublished TRUE to return unpublished nodes too
     * @param string $domain Machine name or ID of the Domain Access domain or empty string for the nodes of all domains
     * @return Collection<int, \stdClass>
     */
    public function nodes( array $types, bool $unpublished, string $domain = '' ): Collection
    {
        $map = $this->map();
        $rows = $this->db->table( 'node' )->whereIn( 'type', $types )
            ->when( $domain !== '', fn( $query ) => $this->domainNodes( $query, $domain ) )
            ->orderBy( 'created' )->orderBy( 'nid' )->select( self::NODE_COLUMNS )->cursor()
            ->filter( fn( $row ) => ( $map[(int) $row->nid] ?? null ) === (int) $row->nid );
        $list = $nids = [];

        // the node records are created chunk by chunk to keep only the titles and schedules of the last nodes
        foreach( $rows->chunk( \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
        {
            $ids = $chunk->map( fn( $row ) => (int) $row->nid )->values()->all();
            $this->preload( $ids );

            foreach( $chunk as $row )
            {
                $node = $this->node( $row, (int) $row->nid, $this->mapping( (int) $row->nid )['langcode'], true );

                if( $unpublished || $this->published( $node ) ) {
                    $list[] = $node;
                }
            }

            array_push( $nids, ...$ids );
        }

        $this->webforms( $nids );

        return Collection::make( $list );
    }


    /**
     * Warns about the forms of the Webform module attached to the nodes because they can't be imported.
     *
     * @param array<int, int> $nids Drupal 7 node IDs
     */
    protected function webforms( array $nids ): void
    {
        if( !$this->d7->exists( 'webform_component' ) ) {
            return;
        }

        $forms = Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'webform_component' )->whereIn( 'nid', $chunk )->distinct()->get( ['nid'] ) )
            ->pluck( 'nid' )->map( fn( $id ) => (int) $id )->sort()->values();

        if( $forms->isNotEmpty() ) {
            ( $this->warn )( "Skipped the forms of {$forms->count()} Webform nodes (e.g. node {$forms->first()}), only their texts are imported" );
        }
    }


    /**
     * Returns the redirects of the Redirect module.
     *
     * The redirects are read in chunks, so all of them aren't kept in memory at once.
     *
     * @return \Generator<int, \stdClass> Rows with "redirect_source__path", "redirect_redirect__uri" and "language"
     */
    public function redirects(): \Generator
    {
        if( !$this->d7->exists( 'redirect' ) ) {
            return;
        }

        $optional = array_filter( ['source_options', 'redirect_options', 'status'], fn( $column ) => $this->d7->column( 'redirect', $column ) );
        $rows = $this->db->table( 'redirect' )->select( ['rid', 'source', 'redirect', 'language', 'status_code', ...$optional] )->lazyById( 1000, 'rid' );
        $count = 0;

        foreach( $rows as $row )
        {
            if( isset( $row->status ) && !(int) $row->status ) {
                continue;
            }

            // Pagible pages only match the URL path
            if( !empty( Database::unserializeArray( $row->source_options ?? null )['query'] ) )
            {
                $count++;
                continue;
            }

            $options = Database::unserializeArray( $row->redirect_options ?? null );
            $query = (array) ( $options['query'] ?? [] );
            $fragment = (string) ( $options['fragment'] ?? '' );

            yield $this->d7->record( [
                'rid' => (int) $row->rid,
                'redirect_source__path' => trim( (string) $row->source, '/' ),
                'redirect_redirect__uri' => $this->link( (string) $row->redirect ) . ( $query ? '?' . http_build_query( $query ) : '' ) . ( $fragment !== '' ? '#' . $fragment : '' ),
                'language' => (string) $row->language ?: 'und',
                'status_code' => (int) $row->status_code ?: 301,
            ] );
        }

        if( $count ) {
            ( $this->warn )( "Skipped {$count} redirects with query strings in their source URL, only redirects of URL paths are supported" );
        }
    }


    /**
     * Returns the IDs of the terms of the vocabularies ordered by ID.
     *
     * @param array<int, string> $vocabularies Vocabulary machine names, e.g. "tags"
     * @return array<int, int> Term IDs
     */
    public function termIds( array $vocabularies ): array
    {
        if( !$this->d7->exists( 'taxonomy_term_data' ) ) {
            return [];
        }

        $vids = array_keys( array_intersect( $this->vocabularies(), $vocabularies ) );
        $map = $this->termMap();

        // translated terms of the i18n_taxonomy module are translations of the term in the default language
        return $this->db->table( 'taxonomy_term_data' )->whereIn( 'vid', $vids )->orderBy( 'tid' )->pluck( 'tid' )
            ->map( fn( $tid ) => (int) $tid )->reject( fn( $tid ) => isset( $map[$tid] ) )->values()->all();
    }


    /**
     * Streams the nodes tagged with the terms, sticky and newest first.
     *
     * The rows are streamed instead of fetched at once because terms can tag lots of nodes. If the rows
     * are limited, each term is fetched together with its translated terms on its own, so not more rows
     * than the limit are transferred per term.
     *
     * @param array<int, int> $tids Term IDs
     * @param int|null $limit Maximum number of rows fetched per term or NULL for all
     * @return LazyCollection<int, \stdClass> Rows with "tid" and "nid"
     */
    public function termIndex( array $tids, ?int $limit = null ): LazyCollection
    {
        if( !$this->d7->exists( 'taxonomy_index' ) ) {
            return LazyCollection::make();
        }

        // nodes can be tagged with the translated terms of the i18n_taxonomy module
        $map = $this->termMap();
        $tids = [...$tids, ...array_keys( array_intersect( $map, $tids ) )];

        return LazyCollection::make( function() use ( $tids, $map, $limit ) {
            $tids = array_values( array_unique( $tids ) );
            $groups = [];

            foreach( $tids as $tid ) {
                $groups[$map[$tid] ?? $tid][] = $tid;
            }

            foreach( $limit !== null ? $groups : array_chunk( $tids, \Aimeos\Cms\DB::IN_SIZE ) as $chunk )
            {
                foreach( $this->db->table( 'taxonomy_index' )->whereIn( 'tid', $chunk )->orderByDesc( 'sticky' )->orderByDesc( 'created' )
                    ->orderBy( 'nid' )->when( $limit, fn( $query, $limit ) => $query->limit( $limit ) )->select( ['tid', 'nid'] )->cursor() as $row ) {
                    yield $this->d7->record( ['tid' => $map[(int) $row->tid] ?? (int) $row->tid, 'nid' => $this->nid( (int) $row->nid )] );
                }
            }
        } );
    }


    /**
     * Returns the terms ordered by ID.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    public function terms( array $tids ): Collection
    {
        if( !$this->d7->exists( 'taxonomy_term_data' ) ) {
            return Collection::make();
        }

        return Database::chunked( $tids, fn( $chunk ) => $this->db->table( 'taxonomy_term_data' )->whereIn( 'tid', $chunk )->orderBy( 'tid' )->get() )
            ->map( fn( $row ) => $this->term( $row ) );
    }


    /**
     * Returns the translations of the terms ordered by language.
     *
     * Translations are the terms in translation sets of the i18n_taxonomy module and the localized
     * names and descriptions of the terms.
     *
     * @param array<int, int> $tids Term IDs
     * @return Collection<int, \stdClass>
     */
    public function termTranslations( array $tids ): Collection
    {
        return $this->termRows( $tids )->filter( fn( $term ) => !$term->default_langcode )->sortBy( 'langcode' )->values();
    }


    /**
     * Returns the tracker which treats all custom blocks as changed.
     *
     * @param string $menu Name of the menu used for the page hierarchy
     * @param \Closure(\stdClass): ?int $nodeId Returns the node ID of a menu link
     */
    public function tracker( string $menu, \Closure $nodeId ): DrupalTracker
    {
        return new Drupal7Tracker( $this, $menu, $nodeId );
    }


    /**
     * Returns the translations of the nodes ordered by language.
     *
     * @param array<int, int> $nids Node IDs
     * @param bool $unpublished TRUE to return unpublished translations too
     * @return Collection<int, \stdClass>
     */
    public function translations( array $nids, bool $unpublished ): Collection
    {
        $this->map();

        // only nodes with translation nodes or translations of the Entity Translation module are translated
        $translated = $this->d7->exists( 'entity_translation' )
            ? Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'entity_translation' )->where( 'entity_type', 'node' )->whereIn( 'entity_id', $chunk )
                ->whereNotIn( 'language', ['', 'und'] )->distinct()->get( ['entity_id'] ) )->pluck( 'entity_id' )->map( fn( $id ) => (int) $id )->flip()->all()
            : [];

        return $this->nodeRows( array_values( array_filter( $nids, fn( $nid ) => isset( $this->sources[$nid] ) || isset( $translated[$nid] ) ) ) )
            ->filter( fn( $node ) => !$node->default_langcode && ( $unpublished || $this->published( $node ) ) )
            ->sortBy( 'langcode' )
            ->values();
    }


    /**
     * Returns the reader for the custom blocks and the blocks of the Bean module.
     */
    protected function blocks(): Drupal7Blocks
    {
        return $this->blocks ??= new Drupal7Blocks( $this->db, $this->d7, fn( string $text ) => $this->text( $text ) );
    }


    /**
     * Returns the reader for the Panelizer displays and the custom content panes.
     */
    protected function panels(): Drupal7Panels
    {
        return $this->panels ??= new Drupal7Panels( $this->db, $this->d7, fn( string $text ) => $this->text( $text ), $this->warn );
    }


    /**
     * Returns the reader for the block placements of the block table and the Context module.
     */
    protected function placements(): Drupal7Placements
    {
        return $this->placements ??= new Drupal7Placements( $this->db, $this->d7, $this->warn );
    }


    /**
     * Returns a date of the Date module in the format of Drupal 8.
     *
     * @param mixed $value Date like "2024-05-01 10:00:00", "2024-05-01T10:00:00" or Unix timestamp
     * @param bool $time FALSE for dates without time
     */
    protected function date( mixed $value, string $type, bool $time ): ?string
    {
        if( $value === null || $value === '' ) {
            return null;
        }

        $value = $type === 'datestamp' ? gmdate( 'Y-m-d\TH:i:s', (int) $value ) : str_replace( ' ', 'T', (string) $value );

        return $time ? $value : substr( $value, 0, 10 );
    }


    /**
     * Adds the domains of the Domain Access module and their site settings of the Domain Conf module.
     *
     * The domains are available by their machine name and their ID.
     */
    protected function domains(): void
    {
        if( !$this->d7->exists( 'domain' ) ) {
            return;
        }

        $settings = $this->d7->exists( 'domain_conf' ) ? $this->db->table( 'domain_conf' )->pluck( 'settings', 'domain_id' ) : Collection::make();
        $machine = $this->d7->column( 'domain', 'machine_name' );

        foreach( $this->db->table( 'domain' )->get() as $row )
        {
            $site = Database::unserializeArray( $settings->get( $row->domain_id ) );
            $front = trim( (string) ( $site['site_frontpage'] ?? '' ), '/' );

            foreach( array_unique( array_filter( [$machine ? (string) $row->machine_name : '', (string) $row->domain_id] ) ) as $key )
            {
                $this->configs['domain.record.' . $key] = ['id' => $key, 'hostname' => (string) $row->subdomain, 'name' => (string) $row->sitename];

                if( $site = array_filter( ['name' => $site['site_name'] ?? null, 'slogan' => $site['site_slogan'] ?? null, 'page' => $front !== '' ? ['front' => '/' . $this->path( $front )] : null] ) ) {
                    $this->configs["domain.config.{$key}.system.site"] = $site;
                }
            }
        }
    }


    /**
     * Limits the query to the nodes assigned to the Domain Access domain or to all domains.
     *
     * @param Builder $query Query of the Drupal 7 node table
     * @param string $domain Machine name or ID of the domain
     */
    protected function domainNodes( Builder $query, string $domain ): Builder
    {
        if( !$this->d7->exists( 'domain_access' ) || !$this->d7->exists( 'domain' ) ) {
            return $query->whereIn( 'nid', [] );
        }

        $id = $this->db->table( 'domain' )
            ->where( fn( $query ) => $query->where( 'domain_id', ctype_digit( $domain ) ? (int) $domain : -1 )
                ->when( $this->d7->column( 'domain', 'machine_name' ), fn( $query ) => $query->orWhere( 'machine_name', $domain ) ) )
            ->value( 'domain_id' );

        // subquery instead of the node IDs to avoid too many query parameters
        return $query->whereIn( 'nid', $this->db->table( 'domain_access' )
            ->where( fn( $query ) => $query->where( fn( $query ) => $query->where( 'realm', 'domain_id' )->where( 'gid', (int) $id ) )
                ->orWhere( fn( $query ) => $query->where( 'realm', 'domain_site' )->where( 'gid', 0 ) ) )
            ->select( 'nid' ) );
    }


    /**
     * Returns the Drupal 7 type, the Drupal 8 storage and the column mapping of the field.
     *
     * @return array{type: string, storage: array{type: string, settings: array<string, mixed>, columns: array<string, string>}, columns: array<string, string>, time: bool}|null
     */
    protected function field( string $field ): ?array
    {
        if( isset( $this->infos[$field] ) ) {
            return $this->infos[$field];
        }

        if( !( $config = $this->fields()[$field] ?? null ) || !$this->d7->exists( 'field_data_' . $field ) ) {
            return null;
        }

        $storage = $this->storage( $config['type'], $config['settings'] );
        $prefix = $field . '_';
        $present = [];

        foreach( $this->db->getSchemaBuilder()->getColumnListing( 'field_data_' . $field ) as $column )
        {
            if( str_starts_with( $column, $prefix ) ) {
                $present[substr( $column, strlen( $prefix ) )] = substr( $column, strlen( $prefix ) );
            }
        }

        return $this->infos[$field] = [
            'type' => $config['type'],
            'storage' => $storage,
            // Drupal 7 column suffix => Drupal 8 column suffix
            'columns' => $storage['columns'] ? array_intersect_key( $storage['columns'], $present ) : $present,
            'time' => !empty( ( (array) ( $config['settings']['granularity'] ?? [] ) )['hour'] ),
        ];
    }


    /**
     * Adds the configuration of the fields of nodes, paragraphs, field collections and beans.
     */
    protected function fieldConfigs(): void
    {
        $entityTypes = ['node' => 'node', 'paragraphs_item' => 'paragraph', 'field_collection_item' => 'paragraph', 'bean' => 'block_content'];
        $instances = $this->db->table( 'field_config_instance' )->where( 'deleted', 0 )
            ->whereIn( 'entity_type', array_keys( $entityTypes ) )->where( 'field_name', '!=', 'title_field' )->get();
        $fields = $this->fields();
        $layouts = $this->layouts();
        $displays = $all = [];

        foreach( $instances as $instance )
        {
            if( !( $config = $fields[(string) $instance->field_name] ?? null ) ) {
                continue;
            }

            $entity = $entityTypes[(string) $instance->entity_type];
            $field = (string) $instance->field_name;
            $data = Database::unserializeArray( $instance->data );
            $storage = $this->storage( $config['type'], $config['settings'] );
            $display = (array) ( ( (array) ( $data['display'] ?? [] ) )['default'] ?? [] );
            $settings = (array) ( $data['settings'] ?? [] );

            if( $storage['type'] === 'boolean' ) {
                $allowed = (array) ( $storage['settings']['allowed_values'] ?? [] );
                $settings += ['on_label' => (string) ( $allowed[1] ?? 'On' ), 'off_label' => (string) ( $allowed[0] ?? 'Off' )];
            }

            $this->configs["field.storage.{$entity}.{$field}"] = ['type' => $storage['type'], 'settings' => $storage['settings']];
            $this->configs["field.field.{$entity}.{$instance->bundle}.{$field}"] = ['label' => (string) ( $data['label'] ?? $field ), 'settings' => $settings];

            if( ( $display['type'] ?? '' ) !== 'hidden' ) {
                $displays[$entity][(string) $instance->bundle][$field] = ['weight' => (int) ( $display['weight'] ?? 0 ), 'label' => (string) ( $display['label'] ?? 'above' )];
            }

            if( $mode = $layouts[$entity][(string) $instance->bundle]['mode'] ?? null )
            {
                $display = (array) ( ( (array) ( $data['display'] ?? [] ) )[$mode] ?? [] ) ?: $display;
                $all[$entity][(string) $instance->bundle][$field] = ['weight' => (int) ( $display['weight'] ?? 0 ), 'label' => (string) ( $display['label'] ?? 'above' )];
            }
        }

        foreach( $displays as $entity => $bundles )
        {
            foreach( $bundles as $bundle => $content ) {
                $this->configs["core.entity_view_display.{$entity}.{$bundle}.default"] = ['content' => $this->groups( $entity, $bundle, $content )];
            }
        }

        // the Display Suite layouts replace the field order of the display
        foreach( $layouts as $entity => $bundles )
        {
            foreach( $bundles as $bundle => $layout ) {
                $this->configs["core.entity_view_display.{$entity}.{$bundle}.default"] = ['content' => $this->layout( $entity, $bundle, $layout, $all[$entity][$bundle] ?? [] )];
            }
        }
    }


    /**
     * Returns the fields shown by the Display Suite layout in the order of its regions.
     *
     * Fields which are not in a region are hidden, the Display Suite fields like the title or
     * the links are skipped because they are no fields.
     *
     * @param string $entity Drupal 8 entity type, "node" or "paragraph"
     * @param string $bundle Bundle name, e.g. "article"
     * @param array{mode: string, regions: array<string, array<int, string>>} $layout View mode and the field names by region
     * @param array<string, array{weight: int, label: string}> $fields Fields of the bundle with the weights and labels of the view mode
     * @return array<string, array{weight: int, label: string}> Displayed fields with weights in the order of the layout
     */
    protected function layout( string $entity, string $bundle, array $layout, array $fields ): array
    {
        $groups = $this->groupData( $entity, $bundle, $layout['mode'] );
        $list = [];

        foreach( $layout['regions'] as $names ) {
            array_push( $list, ...$this->order( $names, $groups, $fields ) );
        }

        $content = [];

        foreach( array_values( array_unique( $list ) ) as $idx => $field ) {
            $content[$field] = ['weight' => $idx, 'label' => $fields[$field]['label']];
        }

        return $content;
    }


    /**
     * Returns the Display Suite layouts of the full or default view mode.
     *
     * @return array<string, array<string, array{mode: string, regions: array<string, array<int, string>>}>> Layouts by Drupal 8 entity type and bundle
     */
    protected function layouts(): array
    {
        if( !$this->d7->exists( 'ds_layout_settings' ) ) {
            return [];
        }

        $entityTypes = ['node' => 'node', 'paragraphs_item' => 'paragraph', 'field_collection_item' => 'paragraph', 'bean' => 'block_content'];
        // the regions in reading order, others between the content and the footer
        $order = array_flip( ['header', 'top', 'ds_header', 'left', 'first', 'sidebar_first', 'ds_content', 'content', 'middle', 'main',
            'right', 'second', 'sidebar_second', 'third', 'fourth', 'footer', 'bottom', 'ds_footer'] );
        $rank = fn( string $region ) => $order[$region] ?? $order['fourth'] + 0.5;
        $layouts = [];

        // the full view mode is used for the pages if it has its own layout
        $rows = $this->db->table( 'ds_layout_settings' )->whereIn( 'entity_type', array_keys( $entityTypes ) )
            ->whereIn( 'view_mode', ['default', 'full'] )->orderBy( 'view_mode' )->get();

        foreach( $rows as $row )
        {
            $regions = array_map( fn( $names ) => array_map( strval( ... ), array_values( (array) $names ) ), (array) ( Database::unserializeArray( $row->settings )['regions'] ?? [] ) );
            uksort( $regions, fn( $a, $b ) => $rank( (string) $a ) <=> $rank( (string) $b ) );

            $layouts[$entityTypes[(string) $row->entity_type]][(string) $row->bundle] = ['mode' => (string) $row->view_mode, 'regions' => $regions];
        }

        return $layouts;
    }


    /**
     * Returns the displayed fields in the order of the groups of the Field Group module.
     *
     * Fields in groups are shown at the position of their group like in Drupal.
     *
     * @param string $entity Drupal 8 entity type, "node" or "paragraph"
     * @param string $bundle Bundle name, e.g. "article"
     * @param array<string, array{weight: int, label: string}> $content Displayed fields by field name
     * @return array<string, array{weight: int, label: string}> Displayed fields with weights in the order of the groups
     */
    protected function groups( string $entity, string $bundle, array $content ): array
    {
        if( !( $groups = $this->groupData( $entity, $bundle, 'default' ) ) ) {
            return $content;
        }

        $grouped = array_merge( ...array_values( array_column( $groups, 'children' ) ) );
        $top = [...array_keys( array_filter( $groups, fn( $group ) => $group['parent'] === '' ) ), ...array_diff( array_keys( $content ), $grouped )];

        foreach( $this->order( $top, $groups, $content ) as $idx => $field ) {
            $content[$field] = ['weight' => $idx, 'label' => $content[$field]['label']];
        }

        return $content;
    }


    /**
     * Returns the groups of the Field Group module.
     *
     * @param string $entity Drupal 8 entity type, "node" or "paragraph"
     * @param string $bundle Bundle name, e.g. "article"
     * @param string $mode View mode, e.g. "default"
     * @return array<string, array{parent: string, weight: int, children: array<int, string>}> Groups by name
     */
    protected function groupData( string $entity, string $bundle, string $mode ): array
    {
        if( !$this->d7->exists( 'field_group' ) ) {
            return [];
        }

        $groups = [];
        $types = match( $entity ) {
            'node' => ['node'],
            'block_content' => ['bean'],
            default => ['paragraphs_item', 'field_collection_item'],
        };
        $rows = $this->db->table( 'field_group' )->whereIn( 'entity_type', $types )
            ->where( 'bundle', $bundle )->where( 'mode', $mode )->get();

        foreach( $rows as $row )
        {
            $data = Database::unserializeArray( $row->data );
            $groups[(string) $row->group_name] = [
                'parent' => (string) $row->parent_name,
                'weight' => (int) ( $data['weight'] ?? 0 ),
                'children' => array_map( strval( ... ), array_values( (array) ( $data['children'] ?? [] ) ) ),
            ];
        }

        return $groups;
    }


    /**
     * Returns the types and settings of the Drupal 7 fields by field name.
     *
     * @return array<string, array{type: string, settings: array<int|string, mixed>}>
     */
    protected function fields(): array
    {
        if( $this->fields !== null ) {
            return $this->fields;
        }

        $this->fields = [];

        foreach( $this->db->table( 'field_config' )->where( 'deleted', 0 )->get() as $row ) {
            $this->fields[(string) $row->field_name] = ['type' => (string) $row->type, 'settings' => (array) ( Database::unserializeArray( $row->data )['settings'] ?? [] )];
        }

        return $this->fields;
    }


    /**
     * Returns the alternative text or title of the image of the File Entity module.
     *
     * @param int $fid File ID
     * @param string $name "alt" or "title"
     */
    protected function fileText( int $fid, string $name ): string
    {
        $field = 'field_file_image_' . $name . '_text';

        if( !$this->d7->exists( 'field_data_' . $field ) ) {
            return '';
        }

        if( !isset( $this->fileTexts[$field][$fid] ) )
        {
            $this->fileTexts[$field][$fid] = (string) $this->db->table( 'field_data_' . $field )->where( 'entity_type', 'file' )
                ->where( 'entity_id', $fid )->where( 'deleted', 0 )->orderBy( 'delta' )->value( $field . '_value' );
            $this->fileTexts[$field] = Database::limit( $this->fileTexts[$field], static::CACHE_SIZE );
        }

        return $this->fileTexts[$field][$fid];
    }


    /**
     * Fetches the file records and the alternative texts and titles of the File Entity module not fetched yet.
     *
     * @param array<int, int> $fids File IDs
     * @param bool $records Fetch the file records too, not only the texts
     */
    protected function prefetchFiles( array $fids, bool $records = true ): void
    {
        $fids = array_values( array_unique( array_filter( $fids ) ) );

        if( $records && ( $missing = array_values( array_filter( $fids, fn( $fid ) => !array_key_exists( $fid, $this->files ) ) ) ) )
        {
            $rows = $this->d7->exists( 'file_managed' )
                ? Database::chunked( $missing, fn( $chunk ) => $this->db->table( 'file_managed' )->whereIn( 'fid', $chunk )->get( self::FILE_COLUMNS ) )->keyBy( fn( $row ) => (int) $row->fid )
                : Collection::make();

            foreach( $missing as $fid ) {
                $this->files[$fid] = $rows->get( $fid );
            }

            $this->files = Database::limit( $this->files, static::CACHE_SIZE, count( $missing ) );
        }

        foreach( ['alt', 'title'] as $name )
        {
            $field = 'field_file_image_' . $name . '_text';

            if( !$this->d7->exists( 'field_data_' . $field ) || !( $missing = array_values( array_filter( $fids, fn( $fid ) => !isset( $this->fileTexts[$field][$fid] ) ) ) ) ) {
                continue;
            }

            // the text of the lowest delta wins like in fileText()
            $texts = Database::chunked( $missing, fn( $chunk ) => $this->db->table( 'field_data_' . $field )->where( 'entity_type', 'file' )
                ->whereIn( 'entity_id', $chunk )->where( 'deleted', 0 )->orderByDesc( 'delta' )->get( ['entity_id', $field . '_value'] ) )
                ->mapWithKeys( fn( $row ) => [(int) $row->entity_id => (string) $row->{$field . '_value'}] );

            foreach( $missing as $fid ) {
                $this->fileTexts[$field][$fid] = (string) $texts->get( $fid, '' );
            }

            $this->fileTexts[$field] = Database::limit( $this->fileTexts[$field], static::CACHE_SIZE, count( $missing ) );
        }
    }


    /**
     * Returns the file IDs of the file tags of the Media module in the text.
     *
     * @param string $text Text like 'Photo: [[{"fid":"1","type":"media"}]]'
     * @return array<int, int> File IDs
     */
    protected function tagFids( string $text ): array
    {
        preg_match_all( '/\[\[(\{.+?\})\]\]/s', $text, $matches );

        return array_map( fn( $json ) => (int) ( ( (array) json_decode( $json, true ) )['fid'] ?? 0 ), $matches[1] );
    }


    /**
     * Returns the file record of the file ID.
     */
    protected function file( int $fid ): ?\stdClass
    {
        if( !array_key_exists( $fid, $this->files ) )
        {
            $this->files[$fid] = $this->d7->exists( 'file_managed' ) ? $this->db->table( 'file_managed' )->where( 'fid', $fid )->first( self::FILE_COLUMNS ) : null;
            $this->files = Database::limit( $this->files, static::CACHE_SIZE );
        }

        return $this->files[$fid];
    }


    /**
     * Adds the filters of the text formats.
     */
    protected function formats(): void
    {
        if( !$this->d7->exists( 'filter' ) ) {
            return;
        }

        foreach( $this->db->table( 'filter' )->get()->groupBy( 'format' ) as $format => $rows ) {
            $this->configs['filter.format.' . $format] = ['filters' => $rows->mapWithKeys( fn( $row ) => [(string) $row->name => [
                'status' => (int) $row->status,
                'settings' => Database::unserializeArray( $row->settings ?? null ),
            ]] )->all()];
        }
    }


    /**
     * Returns the Drupal 7 node IDs of the nodes and of their translation nodes.
     *
     * @param array<int, int> $nids Node IDs
     * @return array<int, int> Drupal 7 node IDs
     */
    protected function ids( array $nids ): array
    {
        $map = $this->map();
        $list = [];

        foreach( $nids as $nid )
        {
            if( isset( $this->sources[$nid] ) ) {
                array_push( $list, ...$this->sources[$nid] );
            } elseif( ( $map[$nid] ?? null ) === $nid ) {
                $list[] = $nid;
            }
        }

        return $list;
    }


    /**
     * Adds the language prefixes and domains of the Locale module.
     */
    protected function languages(): void
    {
        if( !$this->d7->exists( 'languages' ) ) {
            return;
        }

        $rows = $this->db->table( 'languages' )->where( 'enabled', 1 )->get();
        $providers = (array) ( $this->d7->variable( 'language_negotiation_language' ) ?? [] );
        $source = isset( $providers['locale-url'] ) ? ( (int) ( $this->d7->variable( 'locale_language_negotiation_url_part' ) ?? 0 ) ? 'domain' : 'path_prefix' ) : 'none';

        $this->configs['language.negotiation'] = ['url' => [
            'source' => $source,
            'prefixes' => $rows->mapWithKeys( fn( $row ) => [(string) $row->language => (string) $row->prefix] )->all(),
            'domains' => $rows->mapWithKeys( fn( $row ) => [(string) $row->language => (string) $row->domain] )->all(),
        ]];
    }


    /**
     * Returns the URI of a Drupal 7 link path like Drupal 8 stores it.
     *
     * @param string $path Link path, e.g. "node/1", "<front>", "about" or "https://example.com"
     */
    protected function link( string $path ): string
    {
        return match( true ) {
            $path === '<front>' => 'internal:/<front>',
            $path === '<nolink>' || $path === '<separator>' => 'route:<nolink>',
            (bool) preg_match( '/^[a-z][a-z0-9+.-]*:/i', $path ) => $path,
            (bool) preg_match( '/^node\/(\d+)$/', $path, $m ) => 'entity:node/' . $this->nid( (int) $m[1] ),
            default => 'internal:/' . $this->path( ltrim( $path, '/' ) ),
        };
    }


    /**
     * Returns the target node IDs of the Drupal 7 nodes.
     *
     * Translation nodes of the Content Translation module are mapped to their source node,
     * translation nodes in a language the source node already has are skipped. Only the
     * languages, translation sets and sources which differ from the defaults are kept.
     *
     * @return array<int, int> Target node ID by Drupal 7 node ID
     */
    protected function map(): array
    {
        if( $this->nodes !== null ) {
            return $this->nodes;
        }

        $nodes = $translations = $langs = $sources = [];
        $default = $this->d7->langcode();
        $tnid = $this->db->getSchemaBuilder()->hasColumn( 'node', 'tnid' );
        $rows = $this->db->table( 'node' )->orderBy( 'nid' )->select( array_merge( ['nid', 'language'], $tnid ? ['tnid'] : [] ) )->cursor();

        // source nodes first, so translation nodes with the same language as their source node are skipped
        foreach( $rows as $row )
        {
            $nid = (int) $row->nid;
            $source = (int) ( $row->tnid ?? 0 );
            $langcode = in_array( (string) $row->language, ['', 'und'], true ) ? $default : (string) $row->language;

            if( $source && $source !== $nid ) {
                $translations[$nid] = [$source, $langcode];
                continue;
            }

            $nodes[$nid] = $nid;
            $this->mapNode( $nid, $langcode, $source > 0 );
        }

        // translation nodes whose source node doesn't exist are source nodes
        $missing = array_filter( $translations, fn( $entry ) => !isset( $nodes[$entry[0]] ) && !isset( $translations[$entry[0]] ) );

        foreach( $missing as $nid => [, $langcode] )
        {
            $nodes[$nid] = $nid;
            $this->mapNode( $nid, $langcode, true );
            unset( $translations[$nid] );
        }

        foreach( $translations as $nid => [$target, $langcode] )
        {
            // only the languages and nodes of the translation sets are tracked
            $own = ( $nodes[$target] ?? null ) === $target;
            $langs[$target] ??= $own ? [$this->langcodes[$target] ?? $default => true] : [];

            if( isset( $langs[$target][$langcode] ) ) {
                ( $this->warn )( "Skipped Drupal translation node {$nid}, node {$target} already has a translation in \"{$langcode}\"" );
                continue;
            }

            $langs[$target][$langcode] = true;
            $sources[$target] ??= $own ? [$target] : [];
            $sources[$target][] = $nid;
            $nodes[$nid] = $target;
            $this->mapNode( $nid, $langcode, true );
        }

        $this->sources = array_filter( $sources, fn( $nids ) => count( $nids ) > 1 );
        return $this->nodes = $nodes;
    }


    /**
     * Returns the target node, language and translation set membership of the Drupal 7 node.
     *
     * @param int $nid Drupal 7 node ID
     * @return array{nid: int, langcode: string, set: bool} Target node ID, language and translation set flag
     */
    protected function mapping( int $nid ): array
    {
        return [
            'nid' => $this->map()[$nid] ?? $nid,
            'langcode' => $this->langcodes[$nid] ?? $this->d7->langcode(),
            'set' => isset( $this->sets[$nid] ),
        ];
    }


    /**
     * Keeps the language and the translation set membership of the Drupal 7 node if they differ from the defaults.
     *
     * @param int $nid Drupal 7 node ID
     * @param string $langcode Language of the node
     * @param bool $set TRUE if the node is in a translation set
     */
    protected function mapNode( int $nid, string $langcode, bool $set ): void
    {
        if( $langcode !== $this->d7->langcode() ) {
            $this->langcodes[$nid] = $langcode;
        }

        if( $set ) {
            $this->sets[$nid] = true;
        }
    }


    /**
     * Returns a menu link in the format of the menu_link_content_data table.
     *
     * @param \stdClass $row Record of the Drupal 7 menu_links table
     * @param string $langcode Language of the link
     * @param string $title Title in the language
     */
    protected function menuLink( \stdClass $row, string $langcode, string $title ): \stdClass
    {
        return $this->d7->record( [
            'id' => (int) $row->mlid,
            'uuid' => 'd7-' . $row->mlid,
            'menu_name' => (string) $row->menu_name,
            'title' => $title,
            'link__uri' => $this->link( (string) $row->link_path ),
            'parent' => (int) $row->plid ? 'menu_link_content:d7-' . $row->plid : null,
            'enabled' => 1,
            'weight' => (int) $row->weight,
            'langcode' => $langcode,
            'default_langcode' => (int) ( $langcode === $this->d7->langcode() ),
        ] );
    }


    /**
     * Adds the defaults of the Metatag module.
     */
    protected function metatagConfigs(): void
    {
        if( !$this->d7->exists( 'metatag_config' ) ) {
            return;
        }

        foreach( $this->db->table( 'metatag_config' )->get() as $row )
        {
            if( preg_match( '/^(global|node)(?::(\w+))?$/', (string) $row->instance, $m ) && !( $m[1] === 'global' && isset( $m[2] ) ) ) {
                $this->configs['metatag.metatag_defaults.' . $m[1] . ( isset( $m[2] ) ? '__' . $m[2] : '' )] = ['tags' => $this->tags( $row->config )];
            }
        }
    }


    /**
     * Returns the meta tags of the current revisions of the nodes or of the revisions as rows of the Metatag field.
     *
     * @param array<int, int> $ids Node IDs or revision IDs
     * @param bool $revision TRUE if the IDs are revision IDs
     * @return Collection<int, \stdClass>
     */
    protected function metatags( array $ids, bool $revision = false ): Collection
    {
        if( !$this->d7->exists( 'metatag' ) ) {
            return Collection::make();
        }

        $map = $this->map();
        $latest = [];
        // only the meta tags of the current revisions and the ones for all revisions (revision ID 0) are used
        $rows = Database::chunked( $revision ? $ids : $this->ids( $ids ), fn( $chunk ) => $this->db->table( 'metatag as m' )->where( 'm.entity_type', 'node' )
            ->whereIn( $revision ? 'm.revision_id' : 'm.entity_id', $chunk )
            ->when( !$revision, fn( $query ) => $query->join( 'node as n', 'n.nid', '=', 'm.entity_id' )
                ->where( fn( $query ) => $query->whereColumn( 'm.revision_id', 'n.vid' )->orWhere( 'm.revision_id', 0 ) ) )
            ->orderBy( 'm.entity_id' )->orderBy( 'm.revision_id' )->get( ['m.entity_id', 'm.revision_id', 'm.language', 'm.data'] ) );

        foreach( $rows as $row ) {
            $latest[(int) $row->entity_id . ':' . ( $revision ? $row->revision_id . ':' : '' ) . $row->language] = $row;
        }

        return Collection::make( array_values( $latest ) )->filter( fn( $row ) => isset( $map[(int) $row->entity_id] ) )->map( fn( $row ) => $this->d7->record( [
            'entity_id' => $map[(int) $row->entity_id],
            'revision_id' => (int) $row->revision_id,
            'bundle' => '',
            'deleted' => 0,
            'langcode' => $this->rowLang( $this->mapping( (int) $row->entity_id ), (string) $row->language ),
            'delta' => 0,
            'field_meta_tags_value' => serialize( $this->tags( $row->data ) ),
        ] ) );
    }


    /**
     * Returns the target node ID of the Drupal 7 node ID, the source node for translation nodes.
     */
    protected function nid( int $nid ): int
    {
        return $this->map()[$nid] ?? $nid;
    }


    /**
     * Returns a node record in the format of the node_field_data table.
     *
     * @param \stdClass $row Record of the Drupal 7 node table
     * @param int $nid Target node ID
     * @param string $langcode Language of the record
     * @param bool $default TRUE for the default language of the node
     */
    protected function node( \stdClass $row, int $nid, string $langcode, bool $default ): \stdClass
    {
        $schedule = $this->schedules[(int) $row->nid] ?? null;

        return $this->d7->record( [
            'nid' => $nid,
            'vid' => (int) $row->vid,
            'type' => (string) $row->type,
            'langcode' => $langcode,
            'title' => $this->titles[(int) $row->nid][$langcode] ?? (string) $row->title,
            'uid' => (int) $row->uid,
            'status' => (int) $row->status,
            'promote' => (int) $row->promote,
            'sticky' => (int) $row->sticky,
            'created' => (int) $row->created,
            'changed' => (int) $row->changed,
            'default_langcode' => (int) $default,
            'publish_on' => (int) ( $schedule->publish_on ?? 0 ) ?: null,
        ] );
    }


    /**
     * Returns the records of the nodes in all languages in the format of the node_field_data table.
     *
     * @param array<int, int> $nids Node IDs
     * @return Collection<int, \stdClass>
     */
    protected function nodeRows( array $nids ): Collection
    {
        $rows = Database::chunked( $this->ids( $nids ), fn( $chunk ) => $this->db->table( 'node' )->whereIn( 'nid', $chunk )->orderBy( 'nid' )->get( self::NODE_COLUMNS ) );
        $translations = $this->d7->exists( 'entity_translation' )
            ? Database::chunked( $nids, fn( $chunk ) => $this->db->table( 'entity_translation' )->where( 'entity_type', 'node' )->whereIn( 'entity_id', $chunk )->get() )->groupBy( 'entity_id' )
            : Collection::make();
        $list = [];

        $this->preload( $rows->pluck( 'nid' )->map( fn( $id ) => (int) $id )->all() );

        foreach( $rows as $row )
        {
            $node = $this->mapping( (int) $row->nid );
            $default = $node['nid'] === (int) $row->nid;
            $list[] = $data = $this->node( $row, $node['nid'], $node['langcode'], $default );

            // translations of the Entity Translation module share the node and its fields
            foreach( $default ? $translations->get( $row->nid ) ?? [] : [] as $translation )
            {
                $langcode = (string) $translation->language;

                if( in_array( $langcode, ['', 'und', $node['langcode']], true ) ) {
                    continue;
                }

                $list[] = (object) array_replace( (array) $data, [
                    'langcode' => $langcode,
                    'title' => $this->titles[(int) $row->nid][$langcode] ?? (string) $row->title,
                    'status' => (int) $translation->status,
                    'created' => (int) $translation->created ?: (int) $row->created,
                    'changed' => (int) $translation->changed ?: (int) $row->changed,
                    'default_langcode' => 0,
                ] );
            }
        }

        return Collection::make( $list );
    }


    /**
     * Returns the number added to the IDs of the field collection items.
     */
    protected function offset(): int
    {
        return $this->offset ??= $this->d7->exists( 'paragraphs_item' ) ? (int) $this->db->table( 'paragraphs_item' )->max( 'item_id' ) : 0;
    }


    /**
     * Returns the field names of the groups and fields in the order of their weights.
     *
     * @param array<int, string> $names Group and field names
     * @param array<string, array{parent: string, weight: int, children: array<int, string>}> $groups Groups by name
     * @param array<string, array{weight: int, label: string}> $content Displayed fields by field name
     * @param int $depth Nesting level of the groups
     * @return array<int, string> Field names
     */
    protected function order( array $names, array $groups, array $content, int $depth = 0 ): array
    {
        $list = [];
        usort( $names, fn( $a, $b ) => ( $groups[$a]['weight'] ?? $content[$a]['weight'] ?? 0 ) <=> ( $groups[$b]['weight'] ?? $content[$b]['weight'] ?? 0 ) );

        foreach( $names as $name )
        {
            if( isset( $groups[$name] ) && $depth < 10 ) {
                array_push( $list, ...$this->order( $groups[$name]['children'], $groups, $content, $depth + 1 ) );
            } elseif( isset( $content[$name] ) ) {
                $list[] = $name;
            }
        }

        return $list;
    }


    /**
     * Returns the Panelizer displays of the nodes as rows of the Layout Builder field.
     *
     * @param array<int, int> $ids Node IDs or revision IDs
     * @param bool $revision TRUE if the IDs are revision IDs
     * @return Collection<int, \stdClass>
     */
    protected function panelizer( array $ids, bool $revision ): Collection
    {
        if( !$this->d7->exists( 'panelizer_entity' ) || !$this->d7->exists( 'panels_pane' ) ) {
            return Collection::make();
        }

        $map = $this->map();
        $rows = Database::chunked( $revision ? $ids : $this->ids( $ids ), fn( $chunk ) => $this->db->table( 'panelizer_entity as p' )
            ->join( 'node as n', 'n.nid', '=', 'p.entity_id' )
            ->where( 'p.entity_type', 'node' )->where( 'p.did', '>', 0 )->whereIn( 'p.view_mode', $this->panels()->modes() )
            ->whereIn( $revision ? 'p.revision_id' : 'p.entity_id', $chunk )
            ->when( !$revision, fn( $query ) => $query->whereColumn( 'p.revision_id', 'n.vid' ) )
            ->get( ['p.entity_id', 'p.revision_id', 'p.view_mode', 'p.did', 'n.type'] ) );
        $list = [];

        $this->panels()->prefetch( $rows->map( fn( $row ) => (int) $row->did )->all() );

        foreach( $rows->sortBy( fn( $row ) => array_search( $row->view_mode, $this->panels()->modes(), true ) ) as $row )
        {
            $node = isset( $map[(int) $row->entity_id] ) ? $this->mapping( (int) $row->entity_id ) : null;

            $list[(int) $row->revision_id] ??= $node ? $this->d7->record( [
                'entity_id' => $node['nid'],
                'revision_id' => (int) $row->revision_id,
                'bundle' => (string) $row->type,
                'deleted' => 0,
                'langcode' => $node['langcode'],
                'delta' => 0,
                'layout_builder__layout_section' => serialize( $this->panels()->section( (int) $row->did, (string) $row->type ) ),
            ] ) : null;
        }

        return Collection::make( array_values( array_filter( $list ) ) );
    }


    /**
     * Returns the paragraphs and field collection items in the format of the paragraphs_item_field_data table.
     *
     * @param array<int, int> $ids Paragraph IDs, IDs of field collection items are shifted by the offset
     * @return Collection<int, \stdClass>
     */
    protected function paragraphs( array $ids ): Collection
    {
        $offset = $this->offset();
        $items = [
            'paragraphs_item' => ['bundle', 0, array_filter( $ids, fn( $id ) => $id <= $offset )],
            'field_collection_item' => ['field_name', $offset, array_map( fn( $id ) => $id - $offset, array_filter( $ids, fn( $id ) => $id > $offset ) )],
        ];
        $list = [];

        foreach( $items as $table => [$type, $add, $itemIds] )
        {
            if( !$itemIds || !$this->d7->exists( $table ) ) {
                continue;
            }

            foreach( $this->db->table( $table )->whereIn( 'item_id', $itemIds )->get() as $row )
            {
                $list[] = $this->d7->record( [
                    'id' => (int) $row->item_id + $add,
                    'type' => (string) $row->{$type},
                    'langcode' => 'und',
                    'status' => (int) empty( $row->archived ),
                    'default_langcode' => 1,
                ] );
            }
        }

        return Collection::make( $list );
    }


    /**
     * Returns the system path of a Drupal 7 path, translation nodes are replaced by their source nodes.
     *
     * @param string $path Path without leading slash, e.g. "node/2"
     * @return string Path without leading slash
     */
    protected function path( string $path ): string
    {
        $path = (string) preg_replace_callback( '/^node\/(\d+)(?=$|[\/?#])/', fn( $m ) => 'node/' . $this->nid( (int) $m[1] ), $path );
        return (string) preg_replace_callback( '/^taxonomy\/term\/(\d+)(?=$|[\/?#])/', fn( $m ) => 'taxonomy/term/' . ( $this->termMap()[(int) $m[1]] ?? $m[1] ), $path );
    }


    /**
     * Fetches the titles of the Title module and the records of the Scheduler module of the nodes not fetched yet.
     *
     * @param array<int, int> $ids Drupal 7 node IDs
     */
    protected function preload( array $ids ): void
    {
        if( !( $ids = array_values( array_filter( $ids, fn( $id ) => !array_key_exists( $id, $this->schedules ) ) ) ) ) {
            return;
        }

        foreach( $ids as $id ) {
            $this->titles[$id] = [];
            $this->schedules[$id] = null;
        }

        $this->schedules = Database::limit( $this->schedules, static::CACHE_SIZE, count( $ids ) );
        $this->titles = array_intersect_key( $this->titles, $this->schedules );

        if( $this->d7->exists( 'field_data_title_field' ) )
        {
            $map = $this->map();
            $rows = Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'field_data_title_field' )->where( 'entity_type', 'node' )
                ->whereIn( 'entity_id', $chunk )->where( 'deleted', 0 )->where( 'delta', 0 )->get() );

            foreach( $rows as $row )
            {
                if( isset( $map[(int) $row->entity_id] ) && trim( (string) $row->title_field_value ) !== '' ) {
                    $this->titles[(int) $row->entity_id][$this->titleLang( $this->mapping( (int) $row->entity_id ), (string) $row->language )] = (string) $row->title_field_value;
                }
            }
        }

        if( $this->d7->exists( 'scheduler' ) )
        {
            foreach( Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'scheduler' )->whereIn( 'nid', $chunk )->get() ) as $row ) {
                $this->schedules[(int) $row->nid] = $row;
            }
        }
    }


    /**
     * Tests if the node is published or will be published by the Scheduler module.
     */
    protected function published( \stdClass $node ): bool
    {
        return (bool) $node->status || (int) $node->publish_on > time();
    }


    /**
     * Adds the roles and the grant IDs of the Nodeaccess module.
     */
    protected function roles(): void
    {
        if( !$this->d7->exists( 'role' ) ) {
            return;
        }

        $roles = array_flip( $gids = $this->contentAccessGids() );

        $permissions = $this->d7->exists( 'role_permission' )
            ? $this->db->table( 'role_permission' )->get( ['rid', 'permission'] )->groupBy( 'rid' )->map( fn( $rows ) => $rows->pluck( 'permission' )->map( strval( ... ) )->all() )->all()
            : [];
        $admin = (int) ( $this->d7->variable( 'user_admin_role' ) ?? 0 );

        foreach( $roles as $rid => $role )
        {
            $this->configs['user.role.' . $role] = [
                'id' => $role,
                'is_admin' => $admin > 0 && $rid === $admin,
                'permissions' => $permissions[$rid] ?? [],
            ];
        }

        // the Content Access and Nodeaccess modules use the role IDs as grant IDs
        $this->configs['nodeaccess.settings'] = ['role_map' => $gids];
    }


    /**
     * Returns the language code of a field value.
     *
     * Values of nodes in translation sets of the Content Translation module without language belong to the node language.
     *
     * @param array{nid: int, langcode: string, set: bool} $node
     */
    protected function rowLang( array $node, string $langcode ): string
    {
        return $node['set'] && in_array( $langcode, ['', 'und'], true ) ? $node['langcode'] : ( $langcode ?: 'und' );
    }


    /**
     * Returns the language of the title of the Title module, language neutral titles belong to the node language.
     *
     * @param array{nid: int, langcode: string, set: bool} $node Target node ID, language and translation set flag
     * @param string $langcode Language of the title row
     */
    protected function titleLang( array $node, string $langcode ): string
    {
        return in_array( $langcode, ['', 'und'], true ) ? $node['langcode'] : $langcode;
    }


    /**
     * Adds the site name, front page, theme and time zone.
     */
    protected function site(): void
    {
        $front = trim( (string) ( $this->d7->variable( 'site_frontpage' ) ?? 'node' ), '/' );

        $this->configs['system.site'] = [
            'name' => (string) ( $this->d7->variable( 'site_name' ) ?? '' ),
            'slogan' => (string) ( $this->d7->variable( 'site_slogan' ) ?? '' ),
            'page' => ['front' => '/' . $this->path( $front )],
        ];
        $this->configs['system.theme'] = ['default' => (string) ( $this->d7->variable( 'theme_default' ) ?? 'bartik' )];
        $this->configs['system.date'] = ['timezone' => ['default' => (string) ( $this->d7->variable( 'date_default_timezone' ) ?? '' )]];
    }


    /**
     * Returns the field type, settings and column names of Drupal 8 for a Drupal 7 field type.
     *
     * @param string $type Drupal 7 field type, e.g. "taxonomy_term_reference"
     * @param array<int|string, mixed> $settings Drupal 7 field settings
     * @return array{type: string, settings: array<string, mixed>, columns: array<string, string>}
     */
    protected function storage( string $type, array $settings ): array
    {
        $allowed = ['allowed_values' => (array) ( $settings['allowed_values'] ?? [] )];
        $text = ['value' => 'value', 'summary' => 'summary', 'format' => 'format'];
        $dates = ['value' => 'value', 'value2' => 'end_value'];
        $target = (string) ( $settings['target_type'] ?? '' );

        [$type, $settings, $columns] = match( $type ) {
            'text', 'text_long', 'text_with_summary' => [$type, [], $text],
            'number_integer' => ['integer', [], ['value' => 'value']],
            'number_decimal' => ['decimal', [], ['value' => 'value']],
            'number_float' => ['float', [], ['value' => 'value']],
            'list_text' => ['list_string', $allowed, ['value' => 'value']],
            'list_integer', 'list_float' => [$type, $allowed, ['value' => 'value']],
            'list_boolean' => ['boolean', $allowed, ['value' => 'value']],
            'email' => ['email', [], ['email' => 'value']],
            'link_field' => ['link', [], ['url' => 'uri', 'title' => 'title', 'attributes' => 'options']],
            'image' => ['image', [], ['fid' => 'target_id', 'alt' => 'alt', 'title' => 'title']],
            'file' => ['file', ['display_field' => !empty( $settings['display_field'] )], ['fid' => 'target_id', 'display' => 'display', 'description' => 'description']],
            'media' => ['media', [], ['fid' => 'target_id', 'title' => 'title']],
            'taxonomy_term_reference' => ['entity_reference', ['target_type' => 'taxonomy_term'], ['tid' => 'target_id']],
            'entityreference' => ['entity_reference', ['target_type' => $target === 'bean' ? 'block_content' : $target], ['target_id' => 'target_id']],
            'node_reference' => ['entity_reference', ['target_type' => 'node'], ['nid' => 'target_id']],
            'user_reference' => ['entity_reference', ['target_type' => 'user'], ['uid' => 'target_id']],
            'paragraphs', 'field_collection' => ['entity_reference_revisions', ['target_type' => 'paragraph'], ['value' => 'target_id', 'revision_id' => 'target_revision_id']],
            'date', 'datetime', 'datestamp' => [empty( $settings['todate'] ) ? 'datetime' : 'daterange', [], $dates],
            'addressfield' => ['address', [], [
                'country' => 'country_code', 'administrative_area' => 'administrative_area', 'locality' => 'locality', 'postal_code' => 'postal_code',
                'thoroughfare' => 'address_line1', 'premise' => 'address_line2', 'organisation_name' => 'organization',
                'first_name' => 'given_name', 'last_name' => 'family_name', 'name_line' => 'name_line',
            ]],
            'video_embed_field' => ['video_embed_field', [], ['video_url' => 'value']],
            'youtube' => ['video_embed_field', [], ['input' => 'value', 'video_id' => 'video_id']],
            'phone' => ['telephone', [], ['value' => 'value']],
            'computed' => ['string_long', [], ['value' => 'value']],
            default => [$type, $settings, []],
        };

        return ['type' => $type, 'settings' => array_filter( $settings, fn( $value, $key ) => is_string( $key ), ARRAY_FILTER_USE_BOTH ), 'columns' => $columns];
    }


    /**
     * Returns the meta tags of the Metatag module in the format of Drupal 8.
     *
     * @param mixed $data Serialized meta tags like ['og:title' => ['value' => 'Title']]
     * @return array<string, string> Meta tags like ['og_title' => 'Title']
     */
    protected function tags( mixed $data ): array
    {
        $tags = [];

        foreach( Database::unserializeArray( $data ) as $name => $tag )
        {
            $value = ( (array) $tag )['value'] ?? '';
            // e.g. robots: ['noindex' => 'noindex', 'nofollow' => 0]
            $value = is_array( $value ) ? implode( ', ', array_filter( array_map( strval( ... ), array_filter( $value, 'is_scalar' ) ) ) ) : $value;
            $name = str_replace( ':', '_', (string) $name );
            $name = (string) preg_replace( ['/^canonical$/', '/^twitter_(?!cards_)/'], ['canonical_url', 'twitter_cards_'], $name );

            if( is_scalar( $value ) && trim( (string) $value ) !== '' ) {
                $tags[$name] = (string) $value;
            }
        }

        return $tags;
    }


    /**
     * Returns a term in the format of the taxonomy_term_field_data table.
     *
     * @param \stdClass $row Record of the Drupal 7 taxonomy_term_data table
     */
    protected function term( \stdClass $row ): \stdClass
    {
        return $this->d7->record( [
            'tid' => (int) $row->tid,
            'vid' => $this->vocabularies()[$row->vid] ?? (string) $row->vid,
            'langcode' => (string) ( $row->language ?? '' ) ?: 'und',
            'name' => (string) $row->name,
            'description__value' => $this->text( (string) $row->description ),
            'description__format' => (string) $row->format,
            'weight' => (int) $row->weight,
            'status' => 1,
            'default_langcode' => 1,
        ] );
    }


    /**
     * Returns the term ID of the term in the default language by the IDs of its translated terms.
     *
     * Translated terms are terms in other languages in the translation sets of the i18n_taxonomy module.
     *
     * @return array<int, int>
     */
    protected function termMap(): array
    {
        if( $this->termMap !== null ) {
            return $this->termMap;
        }

        $this->termMap = [];

        if( !$this->d7->column( 'taxonomy_term_data', 'i18n_tsid' ) || !$this->d7->column( 'taxonomy_term_data', 'language' ) ) {
            return $this->termMap;
        }

        $sets = $this->db->table( 'taxonomy_term_data' )->where( 'i18n_tsid', '>', 0 )->orderBy( 'tid' )->get( ['tid', 'language', 'i18n_tsid'] )->groupBy( 'i18n_tsid' );

        foreach( $sets as $rows )
        {
            if( !( $default = $rows->first( fn( $row ) => in_array( (string) $row->language, ['', 'und', $this->d7->langcode()], true ) ) ) ) {
                continue;
            }

            foreach( $rows as $row )
            {
                if( (int) $row->tid !== (int) $default->tid ) {
                    $this->termMap[(int) $row->tid] = (int) $default->tid;
                }
            }
        }

        return $this->termMap;
    }


    /**
     * Returns the terms and their translations in the format of the taxonomy_term_field_data table.
     *
     * @param array<int, int> $tids IDs of terms in the default language
     * @return Collection<int, \stdClass>
     */
    protected function termRows( array $tids ): Collection
    {
        if( !$this->d7->exists( 'taxonomy_term_data' ) ) {
            return Collection::make();
        }

        $map = $this->termMap();
        $translated = array_keys( array_intersect( $map, $tids ) );
        $rows = Database::chunked( [...$tids, ...$translated], fn( $chunk ) => $this->db->table( 'taxonomy_term_data' )->whereIn( 'tid', $chunk )->orderBy( 'tid' )->get() );
        $contexts = $rows->flatMap( fn( $row ) => ['term:' . $row->tid . ':name', 'term:' . $row->tid . ':description'] )->all();
        $locales = $this->d7->locales( 'taxonomy', $contexts );
        $list = [];

        foreach( $rows as $row )
        {
            $tid = (int) $row->tid;

            if( isset( $map[$tid] ) )
            {
                $list[] = $this->d7->record( ['tid' => $map[$tid], 'default_langcode' => 0] + (array) $this->term( $row ) );
                continue;
            }

            $list[] = $term = $this->term( $row );
            $names = $locales["term:{$tid}:name"] ?? [];
            $descriptions = $locales["term:{$tid}:description"] ?? [];

            // names and descriptions localized by the i18n_taxonomy module
            foreach( array_unique( [...array_keys( $names ), ...array_keys( $descriptions )] ) as $langcode )
            {
                $list[] = $this->d7->record( [
                    'langcode' => (string) $langcode,
                    'name' => $names[$langcode] ?? $term->name,
                    'description__value' => isset( $descriptions[$langcode] ) ? $this->text( $descriptions[$langcode] ) : $term->description__value,
                    'default_langcode' => 0,
                ] + (array) $term );
            }
        }

        return Collection::make( $list );
    }


    /**
     * Returns the IDs of the translated terms by the ID of the term in the default language.
     *
     * @return array<int, array<int, int>>
     */
    protected function termSources(): array
    {
        if( $this->termSources === null )
        {
            $this->termSources = [];

            foreach( $this->termMap() as $tid => $default ) {
                $this->termSources[$default][] = $tid;
            }
        }

        return $this->termSources;
    }


    /**
     * Replaces the file tags of the Media module in the text by images or links to the files.
     *
     * Links to translation nodes of the Content Translation module become links to their source nodes
     * and links to translated terms of the i18n Taxonomy module links to their source terms.
     *
     * @param string $text Text like 'Photo: [[{"fid":"1","type":"media","attributes":{"alt":"Photo"}}]]'
     */
    protected function text( string $text ): string
    {
        $text = (string) preg_replace_callback( '/(\shref=(["\'])[^"\']*?\/node\/)(\d+)(?=[\/?#]|\2)/i', fn( $m ) => $m[1] . $this->nid( (int) $m[3] ), $text );
        $text = (string) preg_replace_callback( '/(\shref=(["\'])[^"\']*?\/taxonomy\/term\/)(\d+)(?=[\/?#]|\2)/i', fn( $m ) => $m[1] . ( $this->termMap()[(int) $m[3]] ?? $m[3] ), $text );
        $text = $this->videoFilter( $text );
        $this->prefetchFiles( $this->tagFids( $text ) );

        return (string) preg_replace_callback( '/\[\[(\{.+?\})\]\]/s', function( $m ) {
            $data = json_decode( $m[1], true );

            if( !is_array( $data ) || !( $file = $this->file( (int) ( $data['fid'] ?? 0 ) ) ) ) {
                return '';
            }

            $uri = (string) $file->uri;
            $url = str_starts_with( $uri, 'private://' )
                ? '/system/files/' . substr( $uri, 10 )
                : '/' . trim( (string) ( $this->d7->variable( 'file_public_path' ) ?? 'sites/default/files' ), '/' ) . '/' . preg_replace( '/^public:\/\//', '', $uri );
            $attrs = (array) ( $data['attributes'] ?? [] );
            $fields = (array) ( $data['fields'] ?? [] );
            // texts of the tag, overridden in the editor or of the file entity
            $value = fn( string $name ) => trim( (string) ( $attrs[$name] ?? '' ) )
                ?: trim( (string) ( array_values( array_filter( $fields, fn( $key ) => str_starts_with( (string) $key, "field_file_image_{$name}_text[" ), ARRAY_FILTER_USE_KEY ) )[0] ?? '' ) )
                ?: $this->fileText( (int) $file->fid, $name );
            $alt = $value( 'alt' );
            $title = $value( 'title' );
            $align = $this->align( $attrs, $fields );

            // remote videos of the Media: YouTube, Media: Vimeo and Media: oEmbed modules, embedded by the importer
            if( preg_match( '#^(?:youtube|vimeo|oembed)://#i', $uri ) ) {
                return '<iframe src="' . e( $uri ) . '" title="' . e( $title ?: $alt ?: (string) $file->filename ) . '"></iframe>';
            }

            return str_starts_with( (string) $file->filemime, 'image/' )
                ? '<img src="' . e( $url ) . '" alt="' . e( $alt ) . '"' . ( $title !== '' ? ' title="' . e( $title ) . '"' : '' ) . ( $align !== '' ? ' data-align="' . $align . '"' : '' ) . '>'
                : '<a href="' . e( $url ) . '">' . e( $alt ?: (string) $file->filename ) . '</a>';
        }, $text );
    }


    /**
     * Returns the alignment of a file tag of the Media module.
     *
     * The alignment is set by the "alignment" field of Media 2.x, by CSS classes of the
     * WYSIWYG editor or the theme and by the float style of the tag.
     *
     * @param array<int|string, mixed> $attrs Attributes of the file tag
     * @param array<int|string, mixed> $fields Fields of the file tag
     * @return string "left", "right", "center" or empty string for no alignment
     */
    protected function align( array $attrs, array $fields ): string
    {
        $value = strtolower( (string) ( $fields['alignment'] ?? '' ) . ' ' . (string) ( $attrs['class'] ?? '' ) . ' ' . (string) ( $attrs['style'] ?? '' ) );

        return preg_match( '/(?:^|[\s-])(left|right|center)\b|float:\s*(left|right)/', $value, $m ) ? ( $m[1] ?: $m[2] ) : '';
    }


    /**
     * Replaces the tags of the Video Filter module by iframes for YouTube and Vimeo videos and links for other videos.
     *
     * @param string $text Text like 'Video: [video:https://youtu.be/ID width:640 height:360]'
     */
    protected function videoFilter( string $text ): string
    {
        return (string) preg_replace_callback( '/\[video:\s*([^\]\s]+)([^\]]*)\]/i', function( $m ) {
            $url = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
            $url = str_starts_with( $url, '//' ) ? 'https:' . $url : $url;

            if( !preg_match( '#^https?://#i', $url ) ) {
                return $m[0];
            }

            if( !preg_match( '#^https?://(?:[\w-]+\.)*(?:youtube\.com|youtu\.be|vimeo\.com)/#i', $url ) ) {
                return '<a href="' . e( $url ) . '">' . e( $url ) . '</a>';
            }

            preg_match_all( '/\b(width|height):\s*(\d+%?)/i', $m[2], $sizes, PREG_SET_ORDER );
            $attrs = implode( '', array_map( fn( $size ) => ' ' . strtolower( $size[1] ) . '="' . $size[2] . '"', $sizes ) );

            return '<iframe src="' . e( $url ) . '"' . $attrs . '></iframe>';
        }, $text );
    }


    /**
     * Returns the target and title attributes of a value of the Link module.
     *
     * The target is set for all links of the field instance or selected per link if the instance allows it,
     * the title is entered per link if the instance allows it or the same for all links.
     *
     * @param \stdClass $row Record of the Drupal 7 field_data_* table
     * @param string $field Field name
     * @param mixed $attributes Serialized attributes of the link
     * @return array<string, string> Attributes by name
     */
    protected function linkAttributes( \stdClass $row, string $field, mixed $attributes ): array
    {
        if( $this->instances === null )
        {
            $this->instances = [];

            foreach( $this->db->table( 'field_config_instance' )->where( 'deleted', 0 )->get() as $instance ) {
                $this->instances[$instance->entity_type . '.' . $instance->bundle . '.' . $instance->field_name] = (array) ( Database::unserializeArray( $instance->data )['settings'] ?? [] );
            }
        }

        $values = Database::unserializeArray( $attributes );
        $settings = (array) ( $this->instances[$row->entity_type . '.' . $row->bundle . '.' . $field]['attributes'] ?? $this->fields()[$field]['settings']['attributes'] ?? [] );
        $target = (string) ( $settings['target'] ?? 'default' );

        return array_filter( [
            'target' => $target === 'user' ? (string) ( $values['target'] ?? '' ) : ( $target !== 'default' ? $target : '' ),
            'title' => ( !empty( $settings['configurable_title'] ) ? trim( (string) ( $values['title'] ?? '' ) ) : '' ) ?: trim( (string) ( $settings['title'] ?? '' ) ),
        ] );
    }


    /**
     * Returns a field row in the format of Drupal 8.
     *
     * @param \stdClass $row Record of the Drupal 7 field_data_* table
     * @param string $field Field name
     * @param array{type: string, storage: array{type: string, settings: array<string, mixed>, columns: array<string, string>}, columns: array<string, string>, time: bool} $info Field information
     */
    protected function value( \stdClass $row, string $field, array $info ): \stdClass
    {
        $id = (int) $row->entity_id;
        $langcode = (string) $row->language ?: 'und';
        $prefix = $field . '_';

        if( $row->entity_type === 'node' && isset( $this->map()[$id] ) )
        {
            $node = $this->mapping( $id );
            $id = $node['nid'];
            $langcode = $this->rowLang( $node, $langcode );
        }
        elseif( $row->entity_type === 'field_collection_item' )
        {
            $id += $this->offset();
        }
        elseif( $row->entity_type === 'bean' )
        {
            $id += $this->blocks()->offset();
        }

        $data = [
            'entity_id' => $id,
            'revision_id' => (int) $row->revision_id,
            'bundle' => (string) $row->bundle,
            'deleted' => 0,
            'langcode' => $langcode,
            'delta' => (int) $row->delta,
        ];

        foreach( $info['columns'] as $from => $to ) {
            $data[$prefix . $to] = $row->{$prefix . $from} ?? null;
        }

        return (object) $this->convert( $row, $field, $info, $data );
    }


    /**
     * Converts the column values of the Drupal 7 field type to the ones of Drupal 8.
     *
     * @param \stdClass $row Record of the Drupal 7 field_data_* table
     * @param string $field Field name
     * @param array{type: string, storage: array{type: string, settings: array<string, mixed>, columns: array<string, string>}, columns: array<string, string>, time: bool} $info Field information
     * @param array<string, mixed> $data Field row in the format of Drupal 8
     * @return array<string, mixed> Converted field row
     */
    protected function convert( \stdClass $row, string $field, array $info, array $data ): array
    {
        $prefix = $field . '_';
        $type = $info['type'];
        $value = fn( string $name ) => $data[$prefix . $name] ?? null;

        switch( $type )
        {
            case 'text': case 'text_long': case 'text_with_summary':
                foreach( ['value', 'summary'] as $name ) {
                    isset( $data[$prefix . $name] ) && $data[$prefix . $name] = $this->text( (string) $data[$prefix . $name] );
                }
                break;
            case 'link_field':
                $data[$prefix . 'uri'] = $this->link( (string) $value( 'uri' ) );
                $data[$prefix . 'options'] = serialize( ['attributes' => $this->linkAttributes( $row, $field, $value( 'options' ) )] );
                break;
            case 'image':
                $data[$prefix . 'alt'] = (string) $value( 'alt' ) ?: $this->fileText( (int) $value( 'target_id' ), 'alt' );
                $data[$prefix . 'title'] = (string) $value( 'title' ) ?: $this->fileText( (int) $value( 'target_id' ), 'title' );
                break;
            case 'media':
                $data[$prefix . 'title'] = (string) $value( 'title' ) ?: $this->fileText( (int) $value( 'target_id' ), 'alt' ) ?: $this->fileText( (int) $value( 'target_id' ), 'title' );
                break;
            case 'taxonomy_term_reference':
                $data[$prefix . 'target_id'] = $this->termMap()[(int) $value( 'target_id' )] ?? $value( 'target_id' );
                break;
            case 'addressfield':
                $data[$prefix . 'given_name'] = (string) $value( 'given_name' ) . (string) $value( 'family_name' ) !== '' ? $value( 'given_name' ) : $value( 'name_line' );
                break;
            case 'node_reference':
            case 'entityreference':
                $data[$prefix . 'target_id'] = match( $info['storage']['settings']['target_type'] ?? '' ) {
                    'node' => $this->nid( (int) $value( 'target_id' ) ),
                    'taxonomy_term' => $this->termMap()[(int) $value( 'target_id' )] ?? $value( 'target_id' ),
                    'block_content' => (int) $value( 'target_id' ) + $this->blocks()->offset(),
                    default => $value( 'target_id' ),
                };
                break;
            case 'youtube':
                // the YouTube field stores the URL entered by the editor and the extracted video ID
                $data[$prefix . 'value'] = (string) $value( 'video_id' ) !== '' ? 'https://www.youtube.com/watch?v=' . $value( 'video_id' ) : $value( 'value' );
                break;
            case 'field_collection':
                $data[$prefix . 'target_id'] = (int) $value( 'target_id' ) + $this->offset();
                break;
            case 'date': case 'datetime': case 'datestamp':
                $data[$prefix . 'value'] = $this->date( $value( 'value' ), $type, $info['time'] );
                if( isset( $info['columns']['value2'] ) ) {
                    $data[$prefix . 'end_value'] = $this->date( $value( 'end_value' ), $type, $info['time'] );
                }
                break;
        }

        return $data;
    }


    /**
     * Adds the page displays of the Views stored in the database.
     */
    protected function views(): void
    {
        if( !$this->d7->exists( 'views_view' ) || !$this->d7->exists( 'views_display' ) ) {
            return;
        }

        $displays = $this->db->table( 'views_display' )->get()->groupBy( 'vid' );

        foreach( $this->db->table( 'views_view' )->get() as $view )
        {
            $this->configs['views.view.' . $view->name] = [
                'label' => (string) ( $view->human_name ?? '' ) ?: (string) $view->name,
                'display' => Collection::make( $displays->get( $view->vid ) ?? [] )->mapWithKeys( fn( $display ) => [(string) $display->id => [
                    'display_plugin' => (string) $display->display_plugin,
                    'display_options' => Database::unserializeArray( $display->display_options ),
                ]] )->all(),
            ];
        }
    }


    /**
     * Returns the vocabulary machine names by vocabulary ID.
     *
     * @return array<int|string, string>
     */
    protected function vocabularies(): array
    {
        return $this->vocabularies ??= $this->d7->exists( 'taxonomy_vocabulary' )
            ? $this->db->table( 'taxonomy_vocabulary' )->pluck( 'machine_name', 'vid' )->map( strval( ... ) )->all()
            : [];
    }
}
