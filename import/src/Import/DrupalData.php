<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Reads the configuration, entities, field values and files of the Drupal database and caches them.
 *
 * Field values of the nodes in import order and of the referenced media and paragraphs are fetched
 * in batches to avoid one query per entity.
 */
class DrupalData
{
    /** Maximum number of cached entity and file records by table */
    protected const CACHE_SIZE = 10000;



    /** @var array<string, array{type: string, settings?: array<string, mixed>}> Field storages of Drupal's standard profile used without config table */
    protected array $storages = [
        'node.body' => ['type' => 'text_with_summary'],
        'node.field_image' => ['type' => 'image'],
        'node.field_tags' => ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term']],
        'block_content.body' => ['type' => 'text_with_summary'],
    ];
    /** @var array<string, array<string, mixed>>|null Drupal configuration keyed by name */
    protected ?array $config = null;
    /** @var array<string, array<int, string>> Field names by entity type and bundle */
    protected array $fields = [];
    /** @var array<string, bool> Existence of the Drupal tables and columns */
    protected array $tables = [];
    /** @var array<string, array<int|string, Collection<int, \stdClass>>> Field rows by table and entity or revision ID */
    protected array $fieldRows = [];
    /** @var array<int|string, \stdClass|null> file_managed records by fid */
    protected array $fileRows = [];
    /** @var array<int, string> URIs of the file_managed records fetched since fetchedUris() was called last */
    protected array $fetchedUris = [];
    /** @var array<string, array<int|string, Collection<int, \stdClass>>> Entity records of all languages by table and column and by ID or UUID */
    protected array $entities = [];
    /** @var array<string, array<int|string, bool>> IDs of the entities whose fields are fetched together by entity type */
    protected array $batch = [];
    /** @var array<string, array<int, int|string>> IDs of the entities whose fields are fetched together in the order they were added by entity type */
    protected array $batchList = [];
    /** @var array<string, int> Number of IDs in the batch list which are already fetched by field table including the removed ones */
    protected array $batchPos = [];
    /** @var array<string, int> Number of IDs removed from the start of the batch list by entity type */
    protected array $batchBase = [];
    /** @var array<string, array<string, bool>> Field tables whose values are fetched in batches by entity type */
    protected array $batchTables = [];
    /** @var array<string, array<int|string, bool>> IDs of the entities whose referenced entities and files aren't fetched yet by field table */
    protected array $pending = [];
    /** @var array<string, array{0: string, 1: string}> Field name and referenced entity type by field table */
    protected array $targets = [];
    /** @var array<string, bool> Field tables of the paragraphs */
    protected array $paragraphTables = [];
    /** @var array<string, bool> Node field tables, TRUE for revision tables */
    protected array $nodeTables = [];
    /** @var array<int, bool> IDs of the imported nodes whose field values aren't fetched in batches any more */
    protected array $rendered = [];
    /** @var array<int, int> Import order of the nodes as positions by node ID */
    protected array $order = [];
    /** @var array<int, int> Node IDs in import order */
    protected array $sequence = [];
    /** @var array<int|string, string> Content types of the imported nodes by node ID */
    protected array $bundles = [];
    /** @var (\Closure(int): bool)|null Returns TRUE for the node IDs which aren't fetched in batches */
    protected ?\Closure $skip = null;
    /** @var array<int, int> Revision IDs of the drafts which are rendered by node ID */
    protected array $revisions = [];
    /** @var array<int, int> Revision IDs of the drafts in import order */
    protected array $draftSequence = [];
    /** @var array<int, int> Node IDs of the drafts by revision ID */
    protected array $draftNodes = [];
    /** @var array<int, int> Positions of the drafts in the import order by revision ID */
    protected array $draftOrder = [];
    /** @var array<string, array<int, array{int, int|string}>> Import positions and revision IDs of the kept drafts by revision table, last one is due first */
    protected array $keptDrafts = [];


    /**
     * Initializes the object.
     *
     * @param DrupalReader $reader Reader for the Drupal database
     * @param string $domain Machine name of the imported Domain Access domain whose configuration overrides are used or empty string for none
     */
    public function __construct( protected DrupalReader $reader, protected string $domain = '' )
    {
    }


    /**
     * Executes the function while the fields of the node are read from its draft revision.
     *
     * @template T
     * @param int $nid Node ID
     * @param int|null $vid Revision ID of the draft or NULL for the current revision
     * @param \Closure(): T $fn Function to execute
     * @return T Result of the function
     */
    public function revision( int $nid, ?int $vid, \Closure $fn ): mixed
    {
        if( $vid !== null ) {
            $this->revisions[$nid] = $vid;
        }

        try {
            return $fn();
        } finally {
            unset( $this->revisions[$nid] );
        }
    }


    /**
     * Returns the revision ID of the draft of the node which is rendered.
     *
     * @param int $nid Node ID
     * @return int|null Revision ID or NULL if the current revision is rendered
     */
    public function vid( int $nid ): ?int
    {
        return $this->revisions[$nid] ?? null;
    }


    /**
     * Returns the field rows of the node or of its draft revision if the draft is rendered.
     *
     * @param string $field Field name, e.g. "body"
     * @param int $nid Node ID
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to return the rows of another language if none exist for the language
     * @return Collection<int, \stdClass>
     */
    public function nodeValues( string $field, int $nid, string $langcode, bool $fallback = false ): Collection
    {
        $vid = $this->vid( $nid );

        return $this->fieldValues( 'node', $field, $vid ?? $nid, $langcode, $fallback, revision: $vid !== null );
    }


    /**
     * Sets the import order of the nodes whose field values are fetched together.
     *
     * @param array<int, int> $sequence Node IDs in import order
     * @param (\Closure(int): bool)|null $skip Returns TRUE for the node IDs which aren't rendered
     * @param array<int|string, string> $bundles Content types of the nodes by node ID to fetch only the field values of nodes having the field
     */
    public function sequence( array $sequence, ?\Closure $skip = null, array $bundles = [] ): void
    {
        $this->sequence = array_values( $sequence );
        $this->order = array_flip( $this->sequence );
        $this->keptDrafts = [];
        $this->bundles = $bundles;
        $this->skip = $skip;
    }


    /**
     * Sets the draft revisions of the nodes whose field values are fetched together.
     *
     * Must be called after sequence() because the drafts are fetched in the import order of their nodes.
     *
     * @param array<int, array<int, int>> $vids Revision IDs of the drafts by node ID
     */
    public function drafts( array $vids ): void
    {
        $this->draftNodes = [];

        foreach( $this->sequence as $nid )
        {
            foreach( $vids[$nid] ?? [] as $vid ) {
                $this->draftNodes[$vid] = $nid;
            }
        }

        $this->draftSequence = array_keys( $this->draftNodes );
        $this->draftOrder = array_flip( $this->draftSequence );
        $this->keptDrafts = [];
    }


    /**
     * Remembers the revisions of the entities to fetch their field values together.
     *
     * @param string $entityType Entity type ID, e.g. "block_content"
     * @param array<int, int> $vids Revision IDs
     */
    public function prefetchRevisions( string $entityType, array $vids ): void
    {
        $this->addBatch( "{$entityType}:revision", array_values( array_unique( array_filter( $vids ) ) ) );
    }


    /**
     * Returns the Drupal configuration of the given name.
     *
     * The overrides of the Domain Config module for the imported Domain Access domain are applied
     * after the translations, e.g. the front page or the theme of the domain.
     *
     * @param string $name Configuration name, e.g. "media.type.image"
     * @param string $langcode Drupal language code to use the translated configuration or empty string for none
     * @return array<string, mixed>|null Configuration or NULL if not available
     */
    public function config( string $name, string $langcode = '' ): ?array
    {
        if( ( $config = $this->configs()[$name] ?? null ) === null ) {
            return null;
        }

        $domain = $this->domain;
        $names = [
            $langcode !== '' ? "language.{$langcode}:{$name}" : '',
            $domain !== '' ? "domain.config.{$domain}.{$name}" : '',
            $domain !== '' && $langcode !== '' ? "domain.config.{$domain}.{$langcode}.{$name}" : '',
        ];

        foreach( array_filter( $names ) as $key )
        {
            if( ( $override = $this->configs()[$key] ?? null ) !== null ) {
                $config = array_replace_recursive( $config, $override );
            }
        }

        return $config;
    }


    /**
     * Returns the Drupal configuration from the config table keyed by name.
     *
     * Only the configuration used by the importer is loaded. Translations are keyed
     * by collection and name, e.g. "language.de:field.field.node.page.field_date".
     *
     * @return array<string, array<string, mixed>>
     */
    public function configs(): array
    {
        return $this->config ??= $this->reader->configs();
    }


    /**
     * Returns the fields of the entity bundle in the order of the default view display.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $bundle Bundle name, e.g. "article"
     * @return array<int, string> Field names
     */
    public function displayFields( string $entityType, string $bundle ): array
    {
        if( $display = $this->config( "core.entity_view_display.{$entityType}.{$bundle}.default" ) )
        {
            $content = (array) ( $display['content'] ?? [] );
            uasort( $content, fn( $a, $b ) => ( ( (array) $a )['weight'] ?? 0 ) <=> ( ( (array) $b )['weight'] ?? 0 ) );

            return array_values( array_filter( array_map( 'strval', array_keys( $content ) ), fn( $field ) => $this->storage( $entityType, $field ) !== null ) );
        }

        if( $fields = $this->entityFields( $entityType, $bundle ) ) {
            return $fields;
        }

        return match( $entityType ) {
            'node' => ['field_image', 'body'],
            'block_content' => ['body'],
            default => [],
        };
    }


    /**
     * Returns the configurable fields of the entity bundle.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $bundle Bundle name, e.g. "article"
     * @return array<int, string> Field names
     */
    public function entityFields( string $entityType, string $bundle ): array
    {
        $prefix = "field.field.{$entityType}.{$bundle}.";

        return $this->fields[$prefix] ??= array_values( array_map(
            fn( $name ) => substr( $name, strlen( $prefix ) ),
            array_filter( array_keys( $this->configs() ), fn( $name ) => str_starts_with( $name, $prefix ) )
        ) );
    }


    /**
     * Returns the record of an entity in the given language, its default language or any language.
     *
     * @param string $table Base or data table of the entity type
     * @param string $column ID or UUID column
     * @param int|string $value ID or UUID of the entity
     * @param string $langcode Drupal language code for data tables with translations
     */
    public function entity( string $table, string $column, int|string $value, string $langcode = '' ): ?\stdClass
    {
        $this->prefetch( $table, $column, [$value] );
        $rows = $this->entities["{$table}:{$column}"][$value] ?? Collection::make();

        return $rows->first( fn( $row ) => ( $row->langcode ?? '' ) === $langcode )
            ?? $rows->first( fn( $row ) => !empty( $row->default_langcode ) )
            ?? $rows->first();
    }


    /**
     * Fetches the records of the entities in all languages which aren't fetched yet.
     *
     * @param string $table Base or data table of the entity type
     * @param string $column ID or UUID column
     * @param array<int, int|string> $values IDs or UUIDs of the entities
     */
    public function prefetch( string $table, string $column, array $values ): void
    {
        $key = "{$table}:{$column}";
        $values = array_values( array_unique( array_filter( $values, fn( $value ) => !empty( $value ) && !isset( $this->entities[$key][$value] ) ) ) );

        if( !$values ) {
            return;
        }

        $rows = $this->hasTable( $table )
            ? Database::chunked( $values, fn( $chunk ) => $this->reader->entities( $table, $column, $chunk ) )->groupBy( $column )
            : Collection::make();

        foreach( $values as $value ) {
            $this->entities[$key][$value] = Collection::make( $rows->get( $value ) ?? [] );
        }

        $this->entities[$key] = $this->limit( $this->entities[$key], count( $values ) );
    }


    /**
     * Removes the oldest records from the cache if it contains too many records.
     *
     * Removed records are fetched again if they are required later.
     *
     * @template T
     * @param array<int|string, T> $cache Cached records in the order they were added
     * @param int $added Number of records added last which are kept
     * @return array<int|string, T> Remaining records
     */
    protected function limit( array $cache, int $added ): array
    {
        return Database::limit( $cache, static::CACHE_SIZE, $added );
    }


    /**
     * Fetches the entities and files referenced by the field rows fetched since the last call for the field table.
     *
     * Media and paragraph entities are also remembered to fetch their fields together.
     *
     * @param string $table Field table, e.g. "node__field_image"
     * @param string $field Field name, e.g. "field_image"
     * @param string $target Referenced entity type, e.g. "media", or "file" for image and file fields
     */
    public function prefetchTargets( string $table, string $field, string $target ): void
    {
        $this->targets[$table] = [$field, $target];

        if( empty( $this->pending[$table] ) ) {
            return;
        }

        // only the rows fetched since the last call, the targets of the others are already fetched
        $ids = Collection::make( $this->fetchedRows( $table ) )->collapse()->pluck( $field . '_target_id' )
            ->map( fn( $id ) => (int) $id )->filter()->unique()->values()->all();

        // the names of the media and the status of the blocks are in the data tables
        if( $target === 'media' ) {
            $this->prefetch( 'media_field_data', 'mid', $ids );
        } elseif( $target === 'block_content' ) {
            $this->prefetch( 'block_content_field_data', 'id', $ids );
        }

        if( in_array( $target, ['media', 'paragraph'], true ) ) {
            $this->addBatch( $target, $ids );
        }

        match( $target ) {
            'file' => $this->prefetchFiles( $ids ),
            'media' => $this->prefetch( 'media', 'mid', $ids ),
            'node' => $this->prefetch( 'node_field_data', 'nid', $ids ),
            'paragraph' => $this->prefetch( 'paragraphs_item_field_data', 'id', $ids ),
            'block_content' => $this->prefetch( 'block_content', 'id', $ids ),
            'taxonomy_term' => $this->prefetch( 'taxonomy_term_field_data', 'tid', $ids ),
            default => null,
        };
    }


    /**
     * Returns the field rows fetched from the field table since the last call for the table.
     *
     * @param string $table Field table, e.g. "node__body"
     * @return array<int|string, Collection<int, \stdClass>> Field rows by entity or revision ID
     */
    public function fetchedRows( string $table ): array
    {
        $rows = array_intersect_key( $this->fieldRows[$table] ?? [], $this->pending[$table] ?? [] );
        unset( $this->pending[$table] );

        return $rows;
    }


    /**
     * Fetches the media entities embedded into texts and remembers them to fetch their fields together.
     *
     * @param array<int, string> $uuids UUIDs of the media entities
     * @param array<int, int> $mids IDs of the media entities
     */
    public function prefetchMedia( array $uuids, array $mids = [] ): void
    {
        $this->prefetch( 'media', 'uuid', $uuids );
        $this->prefetch( 'media', 'mid', $mids );

        foreach( $uuids as $uuid )
        {
            foreach( $this->entities['media:uuid'][$uuid] ?? [] as $row ) {
                $mids[] = (int) $row->mid;
            }
        }

        $mids = array_values( array_unique( array_filter( $mids ) ) );
        $this->prefetch( 'media_field_data', 'mid', $mids );
        $this->addBatch( 'media', $mids );
    }


    /**
     * Fetches the reusable content blocks placed into layouts and remembers them to fetch their fields together.
     *
     * @param array<int, string> $uuids UUIDs of the content blocks
     */
    public function prefetchBlocks( array $uuids ): void
    {
        $this->prefetch( 'block_content', 'uuid', $uuids );
        $ids = [];

        foreach( $uuids as $uuid )
        {
            foreach( $this->entities['block_content:uuid'][$uuid] ?? [] as $row ) {
                $ids[] = (int) $row->id;
            }
        }

        $this->addBatch( 'block_content', array_values( array_unique( array_filter( $ids ) ) ) );
    }


    /**
     * Adds the entities to the ones whose field values are fetched together.
     *
     * @param string $entityType Entity type ID, e.g. "media"
     * @param array<int, int> $ids Entity IDs
     */
    protected function addBatch( string $entityType, array $ids ): void
    {
        foreach( $ids as $id )
        {
            if( !isset( $this->batch[$entityType][$id] ) ) {
                $this->batch[$entityType][$id] = true;
                $this->batchList[$entityType][] = $id;
            }
        }
    }


    /**
     * Fetches the nodes and files embedded into texts and the files linked in texts.
     *
     * @param array<int, string> $nodeUuids UUIDs of the embedded nodes
     * @param array<int, int> $nids IDs of the embedded nodes
     * @param array<int, string> $fileUuids UUIDs of the embedded files
     * @param array<int, int> $fids IDs of the embedded files
     * @param array<int, string> $uris URIs of the linked files, e.g. "public://doc.pdf"
     */
    public function prefetchEmbeds( array $nodeUuids, array $nids, array $fileUuids, array $fids, array $uris ): void
    {
        $this->prefetch( 'node', 'uuid', $nodeUuids );
        $this->prefetch( 'node', 'nid', $nids );

        foreach( $nodeUuids as $uuid )
        {
            foreach( $this->entities['node:uuid'][$uuid] ?? [] as $row ) {
                $nids[] = (int) $row->nid;
            }
        }

        // the titles and status of the embedded nodes are in the data table
        $this->prefetch( 'node_field_data', 'nid', $nids );
        $this->prefetch( 'file_managed', 'uuid', $fileUuids );
        $this->prefetch( 'file_managed', 'fid', $fids );
        $this->prefetch( 'file_managed', 'uri', $uris );

        // the permanent file records are fetched together instead of one by one while importing
        $files = [];

        foreach( ['uuid' => $fileUuids, 'fid' => $fids, 'uri' => $uris] as $column => $values )
        {
            foreach( $values as $value )
            {
                foreach( $this->entities["file_managed:{$column}"][$value] ?? [] as $row ) {
                    $files[] = (int) $row->fid;
                }
            }
        }

        $this->prefetchFiles( array_values( array_unique( $files ) ) );
    }


    /**
     * Fetches the file_managed records of the files which aren't fetched yet.
     *
     * @param array<int, int> $fids File IDs
     */
    public function prefetchFiles( array $fids ): void
    {
        $fids = array_values( array_filter( $fids, fn( $fid ) => !array_key_exists( $fid, $this->fileRows ) ) );

        if( !$fids ) {
            return;
        }

        $rows = $this->reader->files( $fids )->keyBy( 'fid' );

        foreach( $fids as $fid )
        {
            if( $row = $rows->get( $fid ) ) {
                $this->fetchedUris[] = (string) ( $row->uri ?? '' );
            }

            $this->fileRows[$fid] = $row;
        }

        $this->fileRows = $this->limit( $this->fileRows, count( $fids ) );
        // the URIs of files which are never imported aren't kept forever
        $this->fetchedUris = array_values( $this->limit( $this->fetchedUris, static::CACHE_SIZE ) );
    }


    /**
     * Returns the URIs of the file_managed records fetched since the last call, so their files can be looked up at once.
     *
     * @return array<int, string> Drupal file URIs, e.g. "public://image.png"
     */
    public function fetchedUris(): array
    {
        $uris = $this->fetchedUris;
        $this->fetchedUris = [];

        return $uris;
    }


    /**
     * Returns the field rows of the entity or revision in the given language ordered by delta.
     *
     * Rows of untranslatable fields exist for each language, "und" and "zxx" rows are used for all languages.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * @param int|string $id Entity ID or revision ID if $revision is TRUE
     * @param string $langcode Drupal language code
     * @param bool $fallback TRUE to return the rows of another language if none exist for the language
     * @param bool $revision TRUE to fetch the rows of the given revision ID
     * @return Collection<int, \stdClass>
     */
    public function fieldValues( string $entityType, string $field, int|string $id, string $langcode, bool $fallback = false, bool $revision = false ): Collection
    {
        $table = $this->fieldTable( $entityType, $field, $revision );
        $column = $revision ? 'revision_id' : 'entity_id';

        if( !isset( $this->fieldRows[$table][$id] ) )
        {
            if( !$this->hasTable( $table ) ) {
                return Collection::make();
            }

            // fields of all nodes and of the referenced media and paragraphs are fetched at once to avoid one query per entity
            $ids = match( true ) {
                $revision && $entityType === 'node' => $this->draftBatch( $table, $field, (int) $id ),
                $revision && isset( $this->batch["{$entityType}:revision"][$id] ) => $this->batched( "{$entityType}:revision", $table, $id ),
                $revision => [$id],
                $entityType === 'node' => $this->nodeBatch( $table, $field, (int) $id ),
                isset( $this->batch[$entityType][$id] ) => $this->batched( $entityType, $table, $id ),
                default => [$id],
            };
            $ids = array_values( array_filter( $ids, fn( $key ) => $key === $id || !isset( $this->fieldRows[$table][$key] ) ) );
            $rows = Database::chunked( $ids, fn( $chunk ) => $this->reader->fieldRows( $entityType, $field, $chunk, $column ) )->groupBy( $column );

            foreach( $ids as $key ) {
                $this->fieldRows[$table][$key] = Collection::make( $rows->get( $key ) ?? [] );
                $this->pending[$table][$key] = true;
            }

            if( $entityType === 'node' ) {
                $this->nodeTables[$table] = $revision;
                unset( $this->keptDrafts[$table] );
            } elseif( $entityType === 'paragraph' ) {
                $this->paragraphTables[$table] = true;
            } else {
                // values of media, blocks and terms aren't removed with the node because they are shared
                $this->fieldRows[$table] = $this->limit( $this->fieldRows[$table], count( $ids ) );
            }
        }

        $list = $this->fieldRows[$table][$id] ?? Collection::make();
        $rows = $list->where( 'langcode', $langcode );

        if( $rows->isEmpty() ) {
            $rows = $list->whereIn( 'langcode', ['und', 'zxx'] );
        }

        if( $rows->isEmpty() && $fallback && ( $first = $list->first() ) ) {
            $rows = $list->where( 'langcode', $first->langcode ?? '' );
        }

        return $rows->values();
    }


    /**
     * Returns the IDs of the entities whose field values are fetched together with the given entity.
     *
     * Only the IDs added to the batch since the last fetch from the field table are returned
     * because the field values of the others are already fetched.
     *
     * @param string $entityType Entity type ID, e.g. "paragraph"
     * @param string $table Field table, e.g. "paragraph__field_text"
     * @param int|string $id ID of the entity whose field values are required
     * @return array<int, int|string> Entity IDs
     */
    protected function batched( string $entityType, string $table, int|string $id ): array
    {
        $list = $this->batchList[$entityType] ?? [];
        $base = $this->batchBase[$entityType] ?? 0;
        $ids = array_slice( $list, max( ( $this->batchPos[$table] ?? $base ) - $base, 0 ) );
        $ids = array_values( array_unique( [$id, ...array_filter( $ids, fn( $key ) => isset( $this->batch[$entityType][$key] ) )] ) );

        $this->batchPos[$table] = $base + count( $list );
        $this->batchTables[$entityType][$table] = true;
        $this->trimBatch( $entityType );

        return $ids;
    }


    /**
     * Removes the IDs from the batch list whose values are fetched from all field tables of the entity type.
     *
     * Field tables used later fetch the values of the removed entities one by one if required.
     * Field tables which are rarely used skip the IDs more than CACHE_SIZE behind the end of the
     * list, so the list doesn't keep all IDs.
     *
     * @param string $entityType Entity type ID, e.g. "media"
     */
    protected function trimBatch( string $entityType ): void
    {
        $base = $this->batchBase[$entityType] ?? 0;
        $start = $base + count( $this->batchList[$entityType] ?? [] ) - static::CACHE_SIZE;

        foreach( array_keys( $this->batchTables[$entityType] ?? [] ) as $table ) {
            $this->batchPos[$table] = max( $this->batchPos[$table] ?? $base, $start );
        }

        $fetched = min( array_map( fn( $table ) => $this->batchPos[$table] ?? $base, array_keys( $this->batchTables[$entityType] ?? [] ) ) ?: [$base] ) - $base;

        // removed in larger blocks because the remaining IDs are moved
        if( $fetched < 500 ) {
            return;
        }

        foreach( array_splice( $this->batchList[$entityType], 0, $fetched ) as $key ) {
            unset( $this->batch[$entityType][$key] );
        }

        $this->batchBase[$entityType] = $base + $fetched;
    }


    /**
     * Returns the IDs of the nodes whose field values are fetched together with the given node.
     *
     * The next nodes in the import order are fetched which aren't rendered, fetched yet or skipped,
     * e.g. unchanged nodes with the --since option because they aren't rendered. Nodes whose content type has the field
     * are fetched together and nodes whose content type doesn't have the field according to the configuration are
     * fetched together too. Nodes which are already rendered, e.g. embedded into other nodes, are fetched alone.
     *
     * @param string $table Field table, e.g. "node__body"
     * @param string $field Field name, e.g. "body"
     * @param int $nid ID of the node whose field values are required
     * @return array<int, int> Node IDs
     */
    protected function nodeBatch( string $table, string $field, int $nid ): array
    {
        $ids = [$nid];
        $pos = $this->order[$nid] ?? null;

        if( $pos === null || isset( $this->rendered[$nid] ) ) {
            return $ids;
        }

        $lacks = $this->lacksField( $nid, $field );

        for( $idx = $pos + 1, $len = count( $this->sequence ); $idx < $len && count( $ids ) < 500; $idx++ )
        {
            $id = $this->sequence[$idx];

            if( !isset( $this->rendered[$id] ) && !isset( $this->fieldRows[$table][$id] ) && ( $this->skip === null || !( $this->skip )( $id ) )
                && $this->lacksField( $id, $field ) === $lacks
            ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }


    /**
     * Returns the revision IDs of the drafts whose field values are fetched together with the given draft.
     *
     * Like nodeBatch(), the drafts of the next nodes in the import order are fetched which aren't
     * rendered, fetched yet or skipped and whose content type has the field or not like the given one.
     *
     * @param string $table Field revision table, e.g. "node_revision__body"
     * @param string $field Field name, e.g. "body"
     * @param int $vid Revision ID of the draft whose field values are required
     * @return array<int, int> Revision IDs
     */
    protected function draftBatch( string $table, string $field, int $vid ): array
    {
        $ids = [$vid];

        if( ( $pos = $this->draftOrder[$vid] ?? null ) === null ) {
            return $ids;
        }

        $lacks = $this->lacksField( $this->draftNodes[$vid], $field );

        for( $idx = $pos + 1, $len = count( $this->draftSequence ); $idx < $len && count( $ids ) < 500; $idx++ )
        {
            $id = $this->draftSequence[$idx];
            $nid = $this->draftNodes[$id];

            if( !isset( $this->rendered[$nid] ) && !isset( $this->fieldRows[$table][$id] ) && ( $this->skip === null || !( $this->skip )( $nid ) )
                && $this->lacksField( $nid, $field ) === $lacks
            ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }


    /**
     * Tests if the content type of the node doesn't have the configurable field according to the configuration.
     *
     * @param int $nid Node ID
     * @param string $field Field name, e.g. "body"
     */
    protected function lacksField( int $nid, string $field ): bool
    {
        if( !isset( $this->bundles[$nid] ) || !( $fields = $this->entityFields( 'node', $this->bundles[$nid] ) ) ) {
            return false;
        }

        return !in_array( $field, $fields, true ) && isset( $this->configs()["field.storage.node.{$field}"] );
    }


    /**
     * Removes the field values of the imported node to limit the memory usage.
     *
     * The values are fetched again if they are required later.
     */
    public function forget( int $nid ): void
    {
        $this->rendered[$nid] = true;

        $pos = $this->order[$nid] ?? null;

        foreach( $this->nodeTables as $table => $revision )
        {
            if( $revision )
            {
                $rows = $this->dueDrafts( $table, $pos );

                $this->release( $table, $rows );
                $this->fieldRows[$table] = array_diff_key( $this->fieldRows[$table] ?? [], $rows );
                $this->pending[$table] = array_diff_key( $this->pending[$table] ?? [], $rows );

                if( $pos !== null && !isset( $this->keptDrafts[$table] ) )
                {
                    // the kept drafts are ordered once, so the next nodes don't check all of them again
                    $kept = [];

                    foreach( array_keys( $this->fieldRows[$table] ) as $vid ) {
                        $kept[] = [$this->order[$this->draftNodes[$vid] ?? 0] ?? -1, $vid];
                    }

                    rsort( $kept );
                    $this->keptDrafts[$table] = $kept;
                }
            }
            else
            {
                $this->release( $table, [$this->fieldRows[$table][$nid] ?? []] );
                unset( $this->fieldRows[$table][$nid], $this->pending[$table][$nid] );
            }
        }
    }


    /**
     * Returns the field rows of the revisions which aren't required after importing the node at the given position.
     *
     * Drafts of the next nodes fetched together with the ones of this node are kept.
     *
     * @param string $table Field revision table
     * @param int|null $pos Import position of the node or NULL if it isn't part of the import order
     * @return array<int|string, Collection<int, \stdClass>> Field rows by revision ID
     */
    protected function dueDrafts( string $table, ?int $pos ): array
    {
        if( $pos === null || !isset( $this->keptDrafts[$table] ) )
        {
            unset( $this->keptDrafts[$table] );

            return array_filter( $this->fieldRows[$table] ?? [], fn( $vid ) => $pos === null || !isset( $this->draftNodes[$vid] )
                || ( $this->order[$this->draftNodes[$vid]] ?? -1 ) <= $pos, ARRAY_FILTER_USE_KEY );
        }

        $rows = [];

        while( ( $last = end( $this->keptDrafts[$table] ) ) && $last[0] <= $pos )
        {
            array_pop( $this->keptDrafts[$table] );
            $rows[$last[1]] = $this->fieldRows[$table][$last[1]] ?? Collection::make();
        }

        return $rows;
    }


    /**
     * Removes the field values and records of the paragraphs referenced by the field rows including nested paragraphs.
     *
     * Paragraphs belong to the entity referencing them, so they aren't used by other nodes.
     *
     * @param string $table Field table of the rows
     * @param iterable<int|string, iterable<int, \stdClass>> $rows Field rows grouped by entity ID
     */
    protected function release( string $table, iterable $rows ): void
    {
        [$field, $target] = $this->targets[$table] ?? ['', ''];

        if( $target !== 'paragraph' ) {
            return;
        }

        foreach( $rows as $list )
        {
            foreach( $list as $row )
            {
                if( !( $id = (int) ( $row->{$field . '_target_id'} ?? 0 ) ) ) {
                    continue;
                }

                unset( $this->batch['paragraph'][$id], $this->entities['paragraphs_item_field_data:id'][$id] );

                foreach( array_keys( $this->paragraphTables ) as $ptable )
                {
                    if( isset( $this->fieldRows[$ptable][$id] ) )
                    {
                        $children = $this->fieldRows[$ptable][$id];
                        unset( $this->fieldRows[$ptable][$id], $this->pending[$ptable][$id] );
                        $this->release( $ptable, [$children] );
                    }
                }
            }
        }
    }


    /**
     * Returns the name of the dedicated table of a configurable Drupal field.
     *
     * Like DefaultTableMapping::generateFieldTableName(), names longer than 48 characters
     * use the first 10 characters of the SHA-256 hash of the field storage UUID instead.
     *
     * @param string $entityType Entity type ID, e.g. "media"
     * @param string $field Field name, e.g. "field_media_image"
     * @param bool $revision TRUE for the revision table
     */
    public function fieldTable( string $entityType, string $field, bool $revision = false ): string
    {
        return DrupalReader::table( $entityType, $field, $revision, (string) ( $this->config( "field.storage.{$entityType}.{$field}" )['uuid'] ?? '' ) );
    }


    /**
     * Returns the file_managed record of the file ID.
     */
    public function fileRow( int $fid ): ?\stdClass
    {
        $this->prefetchFiles( [$fid] );
        return $this->fileRows[$fid] ?? null;
    }


    /**
     * Returns the type and settings of the field storage.
     *
     * @param string $entityType Entity type ID, e.g. "node"
     * @param string $field Field name, e.g. "body"
     * Without configuration, the fields of Drupal's standard profile are used.
     *
     * @return array{type: string, settings?: array<string, mixed>}|null Field storage or NULL if not a configurable field
     */
    public function storage( string $entityType, string $field ): ?array
    {
        if( $config = $this->config( "field.storage.{$entityType}.{$field}" ) ) {
            return ['type' => (string) ( $config['type'] ?? '' ), 'settings' => (array) ( $config['settings'] ?? [] )];
        }

        return $this->storages["{$entityType}.{$field}"] ?? null;
    }


    /**
     * Returns the field storages of the Drupal configuration or of Drupal's standard profile without configuration.
     *
     * @return array<string, array{type: string, settings?: array<string, mixed>}> Field storages by entity type and field name, e.g. "node.body"
     */
    public function fieldStorages(): array
    {
        $storages = $this->storages;

        foreach( $this->configs() as $name => $config )
        {
            if( preg_match( '/^field\.storage\.([^.]+\.[^.]+)$/', $name, $m ) ) {
                $storages[$m[1]] = ['type' => (string) ( $config['type'] ?? '' ), 'settings' => (array) ( $config['settings'] ?? [] )];
            }
        }

        return $storages;
    }


    /**
     * Tests if the Drupal database contains the table.
     */
    public function hasTable( string $table ): bool
    {
        return $this->tables[$table] ??= $this->reader->has( $table );
    }


    /**
     * Tests if the table of the Drupal database contains the column.
     */
    public function hasColumn( string $table, string $column ): bool
    {
        return $this->tables["{$table}.{$column}"] ??= $this->hasTable( $table ) && $this->reader->hasColumn( $table, $column );
    }
}
