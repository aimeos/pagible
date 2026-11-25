<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Statamic;

use Aimeos\Cms\Import\Database;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;


/**
 * Reads the entries, collection trees and asset metadata from the database of the Statamic Eloquent driver.
 *
 * @phpstan-type TextField array{type: string, breaks: bool, links: bool, escape: bool, smart: bool, antlers: bool, mode?: string}
 * @phpstan-type ArrayField array{labels: array<string, string>, headers: array{0: string, 1: string}}
 * @phpstan-type BlueprintFields array{containers: array<string, string>, texts: array<string, TextField>, grids: array<string, bool>, videos: array<string, bool>, links: array<string, bool>, across: array<string, bool>, lists: array<string, bool>, arrays: array<string, ArrayField>, images: array<int, string>}
 */
class Reader
{
    /** Columns of the entries fetched for the imported entries, the data is fetched when it's used */
    protected const ENTRY_COLUMNS = ['id', 'site', 'origin_id', 'slug', 'uri', 'published', 'collection', 'blueprint', 'date', 'created_at', 'updated_at'];

    /** Columns of the entries fetched for all entries up front, only with the modification time if --since is used */
    protected const INDEX_COLUMNS = ['id', 'collection', 'slug', 'uri'];

    /** Maximum number of cached assets_meta records */
    protected const CACHE_SIZE = 2000;

    /** Maximum number of origins of localized entries which are followed */
    protected const ORIGIN_DEPTH = 10;

    /** Columns of the entries table used by the Eloquent driver, other columns contain mapped data fields */
    protected const DRIVER_COLUMNS = ['id', 'site', 'origin_id', 'published', 'status', 'slug', 'uri', 'date', 'order', 'collection', 'blueprint', 'data', 'created_at', 'updated_at'];

    /** Key of the data of localized entries listing the fields which differ from their origin */
    protected const LOCALIZED_KEY = '__localized_fields';

    /** Maximum depth of fieldsets imported or referenced by other fieldsets which are resolved */
    protected const FIELDSET_DEPTH = 5;

    /** Field types whose text and settings are used by the content conversion */
    protected const TEXT_TYPES = ['markdown', 'textarea', 'text', 'bard', 'code'];

    /** @var array<int, string>|null Existing columns of the entries table */
    protected ?array $entryColumns = null;
    /** @var array<int, string>|null Existing columns of the assets_meta table, empty if the table doesn't exist */
    protected ?array $assetColumns = null;
    /** @var array<string, array{name: string, extension: string, mime: string, alt: string}|null> Assets fetched at once by "container::path", NULL if there's none */
    protected array $assets = [];
    /** @var array<string, array{settings: array<string, mixed>, updated: int|null}>|null Settings and modification times of the collections by handle */
    protected ?array $settings = null;
    /** @var array<string, array{blueprints: array<string, BlueprintFields>, default: string|null}> Settings of the fields by blueprint handle and the default blueprint by collection */
    protected array $blueprints = [];
    /** @var array<string, array<mixed>>|null Fieldset data by handle, empty if the fieldsets aren't stored in the database */
    protected ?array $fieldsets = null;
    /** @var array<string, array<string, mixed>> SEO Pro site defaults by site handle */
    protected array $seoDefaults = [];
    /** @var array<string, mixed>|null SEO Pro addon settings, empty if they aren't stored in the database */
    protected ?array $seoSettings = null;


    /**
     * @param Connection $db Connection to the Statamic database
     * @param string $site Statamic site handle
     * @param array<int, string> $collections Statamic collection handles
     */
    public function __construct( protected Connection $db, protected string $site, protected array $collections )
    {
    }


    /**
     * Returns the handles of the asset containers stored in the database by the Eloquent driver.
     *
     * @param Connection $db Connection to the Statamic database
     * @return array<int, string>|null Container handles or NULL if the asset containers aren't stored in the database
     */
    public static function containerHandles( Connection $db ): ?array
    {
        if( !$db->getSchemaBuilder()->hasTable( 'asset_containers' ) ) {
            return null;
        }

        return $db->table( 'asset_containers' )->pluck( 'handle' )->map( fn( $handle ) => (string) $handle )->filter()->values()->all();
    }


    /**
     * Returns the sites stored in the database by the Eloquent driver.
     *
     * @param Connection $db Connection to the Statamic database
     * @return array<string, array{lang: string, url: string}>|null Language and URL of the sites by handle in their order or NULL if the sites aren't stored in the database
     */
    public static function sites( Connection $db ): ?array
    {
        $schema = $db->getSchemaBuilder();

        if( !$schema->hasTable( 'sites' ) ) {
            return null;
        }

        $columns = $schema->getColumnListing( 'sites' );
        $query = $db->table( 'sites' );
        $sites = [];

        foreach( array_intersect( ['order', 'id'], $columns ) as $column ) {
            $query->orderBy( $column );
        }

        foreach( $query->get( array_values( array_intersect( ['handle', 'lang', 'locale', 'url'], $columns ) ) ) as $row )
        {
            // the language is derived from the locale like "de_DE" by Statamic if it's not set
            $lang = is_string( $row->lang ?? null ) && $row->lang !== '' ? $row->lang : strtolower( (string) preg_replace( '/[_-].*$/s', '', (string) ( $row->locale ?? '' ) ) );
            $sites[(string) $row->handle] = ['lang' => $lang, 'url' => is_string( $row->url ?? null ) ? $row->url : ''];
        }

        return $sites;
    }


    /**
     * Returns the name and extension of the asset in the container.
     *
     * @param string $container Asset container handle
     * @param string $path Path of the asset in the container
     * @return array{name: string, extension: string, mime: string, alt: string}|null Title, alt text or file name of the asset, its extension, MIME type and alt text or NULL if unknown
     */
    public function asset( string $container, string $path ): ?array
    {
        $key = $container . '::' . $path;

        if( !array_key_exists( $key, $this->assets ) ) {
            $this->prefetchAssets( [$key] );
        }

        return $this->assets[$key] ?? null;
    }


    /**
     * Fetches the assets_meta records of the assets at once.
     *
     * @param array<int, string> $keys Assets like "container::path"
     */
    public function prefetchAssets( array $keys ): void
    {
        $this->assetColumns ??= $this->exists( 'assets_meta' )
            ? array_values( array_intersect( $this->db->getSchemaBuilder()->getColumnListing( 'assets_meta' ), ['container', 'path', 'filename', 'extension', 'meta'] ) )
            : [];

        if( ( $keys = array_values( array_diff( array_unique( $keys ), array_keys( $this->assets ) ) ) ) === [] ) {
            return;
        }

        $found = array_fill_keys( $keys, null );

        if( in_array( 'path', $this->assetColumns, true ) )
        {
            $container = in_array( 'container', $this->assetColumns, true );
            $paths = array_values( array_unique( array_map( fn( $key ) => explode( '::', $key, 2 )[1] ?? '', $keys ) ) );
            $rows = Database::chunked( $paths, fn( $chunk ) => $this->db->table( 'assets_meta' )->whereIn( 'path', $chunk )->get( $this->assetColumns ) );

            foreach( $rows as $row )
            {
                // the first record of the path in the container is used, all containers match without container column
                foreach( $container ? [$row->container . '::' . $row->path] : $this->keys( $keys, (string) $row->path ) as $key )
                {
                    if( array_key_exists( $key, $found ) ) {
                        $found[$key] ??= $this->assetInfo( $row );
                    }
                }
            }
        }

        $this->assets = Database::limit( array_replace( $this->assets, $found ), static::CACHE_SIZE, count( $found ) );
    }


    /**
     * Returns the decoded data of the entry merged with the data of its origins, fetched from the database if the entry has no data.
     *
     * @return array<string, mixed>
     */
    public function data( \stdClass $entry ): array
    {
        if( !property_exists( $entry, 'data' ) ) {
            $entry = $this->withData( Collection::make( [$entry] ) )->first() ?? $entry;
        }

        return $this->decode( $entry->data ?? null );
    }


    /**
     * Fetches the published entries of the collections and site with the columns used by the import one by one.
     *
     * The data is fetched by data() or withData() when it's used because it contains the content of the entries.
     *
     * @return LazyCollection<int, \stdClass>
     */
    public function entries(): LazyCollection
    {
        return $this->published( self::ENTRY_COLUMNS )->cursor();
    }


    /**
     * Fetches the IDs, collections and URLs of the published entries one by one in import order.
     *
     * Only these columns are kept for all entries, the other columns are fetched by rows() when the entries are imported.
     *
     * @param bool $times TRUE to fetch the modification times too
     * @return LazyCollection<int, \stdClass>
     */
    public function ids( bool $times = false ): LazyCollection
    {
        return $this->published( $times ? array_merge( self::INDEX_COLUMNS, ['updated_at'] ) : self::INDEX_COLUMNS )->cursor();
    }


    /**
     * Returns the entry ID each collection is mounted on.
     *
     * @return array<string, string> Entry IDs by collection handle
     */
    public function mounts(): array
    {
        $mounts = [];

        foreach( $this->collections() as $handle => $collection )
        {
            if( is_string( $mount = $collection['settings']['mount'] ?? null ) && $mount !== '' ) {
                $mounts[$handle] = $mount;
            }
        }

        return $mounts;
    }


    /**
     * Tests if the first entry of the collection tree is the home page.
     *
     * @param string $collection Collection handle
     */
    public function expectsRoot( string $collection ): bool
    {
        return !empty( $this->settings( $collection )['structure']['root'] ?? false );
    }


    /**
     * Returns the asset containers of the asset fields in the blueprint of the entry.
     *
     * Fields in Replicator and Bard sets are available by "set.field" and by their handle, the first field of a
     * handle is used then.
     *
     * @return array<string, string> Container handles by field key
     */
    public function containers( object $entry ): array
    {
        return $this->blueprint( $entry )['containers'];
    }


    /**
     * Returns the handles of the Grid fields in the blueprint of the entry.
     *
     * @return array<string, bool> TRUE by field key like "set.field" or "field"
     */
    public function grids( object $entry ): array
    {
        return $this->blueprint( $entry )['grids'];
    }


    /**
     * Returns the handles of the Video fields in the blueprint of the entry.
     *
     * @return array<string, bool> TRUE by field key like "set.field" or "field"
     */
    public function videos( object $entry ): array
    {
        return $this->blueprint( $entry )['videos'];
    }


    /**
     * Returns the handles of the Link fields in the blueprint of the entry.
     *
     * @return array<string, bool> TRUE by field key like "set.field" or "field"
     */
    public function links( object $entry ): array
    {
        return $this->blueprint( $entry )['links'];
    }


    /**
     * Returns if the Link and Bard fields in the blueprint of the entry select entries across sites.
     *
     * @return array<string, bool> TRUE if entries are selected across sites, FALSE if not by field key like "set.field" or "field"
     */
    public function across( object $entry ): array
    {
        return $this->blueprint( $entry )['across'];
    }


    /**
     * Returns the handles of the List fields in the blueprint of the entry.
     *
     * @return array<string, bool> TRUE by field key like "set.field" or "field"
     */
    public function lists( object $entry ): array
    {
        return $this->blueprint( $entry )['lists'];
    }


    /**
     * Returns the Array fields in the blueprint of the entry with the labels of their keys.
     *
     * @return array<string, ArrayField> Labels by key and column headers of the arrays by field key like "set.field" or "field"
     */
    public function arrays( object $entry ): array
    {
        return $this->blueprint( $entry )['arrays'];
    }


    /**
     * Returns the handles of the asset fields with a single asset at the top level of the blueprint of the entry.
     *
     * @return array<int, string> Field handles in the order of the blueprint
     */
    public function images( object $entry ): array
    {
        return $this->blueprint( $entry )['images'];
    }


    /**
     * Returns the types and Markdown settings of the text fields in the blueprint of the entry.
     *
     * Markdown fields convert single line breaks to line breaks (automatic_line_breaks) and don't link URLs
     * (automatic_links), escape HTML (escape_markup) or use smart punctuation (smartypants) by default, Text
     * and Textarea fields contain plain text.
     *
     * @return array<string, TextField> Field type and settings by field key like "set.field" or "field"
     */
    public function texts( object $entry ): array
    {
        return $this->blueprint( $entry )['texts'];
    }


    /**
     * Returns the settings of the fields in the blueprint of the entry.
     *
     * Entries without blueprint or with an unknown one use the default blueprint of their collection like
     * Statamic, which is the first blueprint not hidden in the order of the blueprints.
     *
     * @return BlueprintFields
     */
    protected function blueprint( object $entry ): array
    {
        $collection = (string) ( $entry->collection ?? '' );

        if( !isset( $this->blueprints[$collection] ) )
        {
            $blueprints = $order = $visible = [];

            if( $this->exists( 'blueprints' ) )
            {
                $rows = $this->db->table( 'blueprints' )->where( 'namespace', 'collections.' . $collection )->orderBy( 'id' )->get( ['handle', 'data'] );

                foreach( $rows as $idx => $row )
                {
                    $data = $this->decode( $row->data );
                    $handle = (string) $row->handle;

                    $blueprints[$handle] = $this->blueprintFields( $data );
                    // blueprints without order are sorted after the ordered ones in the order they were created
                    $order[$handle] = [is_numeric( $data['order'] ?? null ) ? 0 : 1, is_numeric( $data['order'] ?? null ) ? (float) $data['order'] : 0, $idx];
                    $visible[$handle] = empty( $data['hide'] );
                }
            }

            uksort( $order, fn( $a, $b ) => $order[$a] <=> $order[$b] );
            $handles = array_keys( $order );
            $default = array_values( array_filter( $handles, fn( $handle ) => $visible[$handle] ) )[0] ?? $handles[0] ?? null;

            $this->blueprints[$collection] = ['blueprints' => $blueprints, 'default' => $default];
        }

        ['blueprints' => $blueprints, 'default' => $default] = $this->blueprints[$collection];

        // the blueprint is stored in the data of entries saved by older versions of the Eloquent driver
        $handle = $entry->blueprint ?? null;
        $handle = is_string( $handle ) && $handle !== '' ? $handle : ( $this->decode( $entry->data ?? null )['blueprint'] ?? null );

        return is_string( $handle ) && isset( $blueprints[$handle] ) ? $blueprints[$handle]
            : ( $default !== null ? $blueprints[$default] : ['containers' => [], 'texts' => [], 'grids' => [], 'videos' => [], 'links' => [], 'across' => [], 'lists' => [], 'arrays' => [], 'images' => []] );
    }


    /**
     * Tests if the blueprints of the collection are stored in the database.
     *
     * The Eloquent driver stores the blueprints in files by default, the field settings are unknown then.
     *
     * @param string $collection Collection handle
     */
    public function hasBlueprints( string $collection ): bool
    {
        $this->blueprint( (object) ['collection' => $collection, 'blueprint' => null, 'data' => null] );

        return $this->blueprints[$collection]['blueprints'] !== [];
    }


    /**
     * Tests if the settings of the collection are stored in the database.
     *
     * @param string $collection Collection handle
     */
    public function hasSettings( string $collection ): bool
    {
        return isset( $this->collections()[$collection] );
    }


    /**
     * Tests if the collection has a structure, i.e. its entries are ordered by a tree.
     *
     * @param string $collection Collection handle
     */
    public function isStructured( string $collection ): bool
    {
        return !empty( $this->settings( $collection )['structure'] ?? null );
    }


    /**
     * Returns the tree of the collection built from the parents stored in the data of the entries.
     *
     * The Eloquent driver stores the parent entry of structured entries in their data, which is used if the
     * tree of the collection isn't stored in the database. The entries are ordered by their order and date.
     *
     * @param string $collection Collection handle
     * @return array<int, array<string, mixed>> Tree like the stored trees, empty if no entry has a parent
     */
    public function parentTree( string $collection ): array
    {
        $parents = [];

        foreach( $this->published( ['id', 'data'] )->where( 'collection', $collection )->cursor() as $row )
        {
            $parent = $this->decode( $row->data )['parent'] ?? null;
            $parents[(string) $row->id] = is_string( $parent ) || is_int( $parent ) ? (string) $parent : null;
        }

        if( array_filter( $parents, fn( $parent ) => $parent !== null && array_key_exists( $parent, $parents ) ) === [] ) {
            return [];
        }

        $children = [];

        foreach( $parents as $id => $parent )
        {
            // entries whose parent isn't published or which are their own ancestors are top level entries
            for( $ancestor = $parent, $depth = 0; $ancestor !== null && $ancestor !== (string) $id && $depth < count( $parents ); $depth++ ) {
                $ancestor = $parents[$ancestor] ?? null;
            }

            $children[$parent !== null && array_key_exists( $parent, $parents ) && $ancestor === null ? $parent : ''][] = (string) $id;
        }

        $build = function( string $id ) use ( &$build, $children ): array {
            return array_filter( ['entry' => $id, 'children' => array_map( $build, $children[$id] ?? [] )] );
        };

        return array_map( $build, $children[''] ?? [] );
    }


    /**
     * Tests if the entries of the collection have URLs in the site.
     *
     * Statamic only generates URLs for collections with a route. If the settings of the collection aren't known,
     * the entries are assumed to have URLs.
     *
     * @param string $collection Collection handle
     * @param string|null $site Site handle or NULL for the imported site
     */
    public function routable( string $collection, ?string $site = null ): bool
    {
        $settings = $this->settings( $collection );
        $site ??= $this->site;

        if( !array_key_exists( 'routes', $settings ) ) {
            return true;
        }

        // the routes can be the same for all sites or differ per site
        $routes = $settings['routes'];
        $route = is_array( $routes ) ? $routes[$site] ?? null : $routes;

        return is_string( $route ) && trim( $route ) !== '';
    }


    /**
     * Fetches the IDs, origins, sites and URLs of the published localized entries in the sites one by one.
     *
     * @param array<int, string> $sites Site handles of the translations
     * @return LazyCollection<int, \stdClass>
     */
    public function localized( array $sites ): LazyCollection
    {
        if( $sites === [] || !in_array( 'origin_id', $this->columns( ['origin_id'] ), true ) ) {
            return LazyCollection::empty();
        }

        return $this->db->table( 'entries' )
            ->whereIn( 'site', $sites )
            ->whereNotNull( 'origin_id' )
            ->where( 'published', true )
            ->whereIn( 'collection', $this->collections )
            ->select( $this->columns( ['id', 'site', 'origin_id', 'collection', 'slug', 'uri'] ) )
            ->cursor();
    }


    /**
     * Fetches the origins of the localized entries in all sites.
     *
     * Localized entries can originate from localized entries of other sites, also of sites which aren't imported.
     *
     * @return array<string, string> IDs of the origin entries by ID of the localized entries
     */
    public function origins(): array
    {
        if( !in_array( 'origin_id', $this->columns( ['origin_id'] ), true ) ) {
            return [];
        }

        $origins = [];
        $rows = $this->db->table( 'entries' )->whereIn( 'collection', $this->collections )->whereNotNull( 'origin_id' )->select( ['id', 'origin_id'] )->cursor();

        foreach( $rows as $row ) {
            $origins[(string) $row->id] = (string) $row->origin_id;
        }

        return $origins;
    }


    /**
     * Fetches the published entries of the other sites which aren't localizations of entries in the site.
     *
     * The entries of all sites are used because localized entries can originate from localized entries of
     * sites which aren't imported.
     *
     * @param array<int, string> $sites Site handles of the translations
     * @return array<int, int|string> IDs of the entries which can't be imported as translations
     */
    public function unrelated( array $sites ): array
    {
        if( $sites === [] || !in_array( 'origin_id', $this->columns( ['origin_id'] ), true ) ) {
            return [];
        }

        $origins = $site = $published = [];
        $rows = $this->db->table( 'entries' )->whereIn( 'collection', $this->collections )
            ->select( ['id', 'site', 'origin_id', 'published'] )->cursor();

        foreach( $rows as $row )
        {
            $origins[(string) $row->id] = $row->origin_id !== null ? (string) $row->origin_id : null;
            $site[(string) $row->id] = (string) $row->site;
            $published[(string) $row->id] = (bool) $row->published;
        }

        // the entries of the site and all of their localizations share the topmost origin
        $root = function( string $id ) use ( $origins ): string {
            for( $depth = 0; ( $origin = $origins[$id] ?? null ) !== null && array_key_exists( $origin, $origins ) && $depth < static::ORIGIN_DEPTH; $depth++ ) {
                $id = $origin;
            }
            return $id;
        };

        $roots = [];

        foreach( $site as $id => $handle )
        {
            if( $handle === $this->site ) {
                $roots[$root( (string) $id )] = true;
            }
        }

        $ids = [];

        foreach( $site as $id => $handle )
        {
            if( $handle !== $this->site && in_array( $handle, $sites, true ) && $published[$id] && !isset( $roots[$root( (string) $id )] ) ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }


    /**
     * Fetches the entries imported next without their data.
     *
     * @param array<int|string> $ids IDs of the entries
     * @return array<int|string, \stdClass> Entries by ID
     */
    public function rows( array $ids ): array
    {
        $rows = [];
        $result = Database::chunked( $ids, fn( $chunk ) => $this->db->table( 'entries' )->whereIn( 'id', $chunk )->get( $this->columns( self::ENTRY_COLUMNS ) ) );

        foreach( $result as $row ) {
            $rows[$row->id] = $row;
        }

        return $rows;
    }


    /**
     * Fetches the published translations of the entries in the sites of the languages without their data.
     *
     * Translations are entries in other sites whose origin is the entry or one of its translations, also of
     * sites which aren't imported. If the entry is a localization itself, the translations of its origin are
     * returned too.
     *
     * @param Collection<array-key, \stdClass> $entries Entries of the imported site
     * @param array<int, string> $sites Site handles of the translations
     * @return array<string, array<string, \stdClass>> Translations by ID of the imported entry and site
     */
    public function translations( Collection $entries, array $sites ): array
    {
        if( $sites === [] || $entries->isEmpty() || !in_array( 'origin_id', $this->columns( ['origin_id'] ), true ) ) {
            return [];
        }

        // the entries and their translations are grouped by the topmost origin of the entries
        $roots = $entries->mapWithKeys( fn( $entry ) => [(string) $entry->id => (string) ( $entry->root ?? $entry->id )] )->all();
        $group = array_fill_keys( array_values( $roots ), null );
        $found = [];
        $next = array_keys( $group );

        for( $depth = 0; $next !== [] && $depth < static::ORIGIN_DEPTH; $depth++ )
        {
            // the localizations of all sites are fetched because translations can originate from sites which aren't imported
            $rows = Database::chunked( $next, fn( $chunk ) => $this->db->table( 'entries' )
                ->where( fn( $query ) => $query->whereIn( 'origin_id', $chunk )->orWhereIn( 'id', $chunk ) )
                ->get( $this->columns( self::ENTRY_COLUMNS ) ) );
            $next = [];

            foreach( $rows as $row )
            {
                $id = (string) $row->id;

                if( isset( $found[$id] ) ) {
                    continue;
                }

                // the topmost origin is the root of its own group
                $row->root = array_key_exists( $id, $group ) ? $id : ( $found[(string) $row->origin_id]->root ?? $roots[(string) $row->origin_id] ?? (string) $row->origin_id );
                $found[$id] = $row;
                $next[] = $id;
            }
        }

        $result = [];

        foreach( $roots as $id => $root )
        {
            foreach( $found as $row )
            {
                if( $row->root === $root && $row->published && in_array( (string) $row->site, $sites, true ) && !isset( $result[$id][(string) $row->site] ) ) {
                    $result[$id][(string) $row->site] = $row;
                }
            }
        }

        return $result;
    }


    /**
     * Returns copies of the entries with their data fetched, decoded and merged with the data of their origins at once.
     *
     * Localized entries only contain the values which differ from their origin entries, the others are inherited.
     * The values injected by the collection and the SEO Pro defaults are added too. The copies contain the ID of
     * the topmost origin as "root" property.
     *
     * @param Collection<array-key, \stdClass> $entries Entries without data
     * @return Collection<array-key, \stdClass>
     */
    public function withData( Collection $entries ): Collection
    {
        $origin = in_array( 'origin_id', $this->columns( ['origin_id'] ), true );
        // fields can be stored in their own columns by the "map_data_to_columns" setting of the Eloquent driver
        $mapped = array_values( array_diff( $this->columns( $this->entryColumns() ), self::DRIVER_COLUMNS ) );
        $columns = array_merge( $origin ? ['id', 'origin_id', 'data'] : ['id', 'data'], $mapped );
        $rows = [];
        $next = $entries->pluck( 'id' )->map( strval( ... ) )->unique()->values()->all();

        for( $depth = 0; $next !== [] && $depth <= static::ORIGIN_DEPTH; $depth++ )
        {
            $result = Database::chunked( $next, fn( $ids ) => $this->db->table( 'entries' )->whereIn( 'id', $ids )->get( $columns ) );
            $next = [];

            foreach( $result as $row )
            {
                $parent = $origin && $row->origin_id !== null ? (string) $row->origin_id : null;
                $rows[(string) $row->id] = ['origin' => $parent, 'data' => $this->rowData( $row, $mapped, $parent !== null )];

                if( $parent !== null && !isset( $rows[$parent] ) ) {
                    $next[] = $parent;
                }
            }

            $next = array_values( array_unique( array_diff( $next, array_keys( $rows ) ) ) );
        }

        return $entries->map( function( $entry ) use ( $rows ) {
            [$data, $root] = $this->merged( (string) $entry->id, $rows );
            $data = $this->defaults( $data, (string) ( $entry->collection ?? '' ), (string) ( $entry->site ?? $this->site ) );
            return (object) ( ['data' => $data, 'root' => $root] + (array) $entry );
        } );
    }


    /**
     * Returns the protection scheme of the entry, e.g. "password" or "logged_in".
     *
     * The protect value of the entry overrides the one its collection injects into all of its entries.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @return string Protection scheme or an empty string if everyone can see the entry
     */
    public function protection( object $entry, array $data ): string
    {
        $inject = (array) ( $this->settings( (string) ( $entry->collection ?? '' ) )['inject'] ?? [] );
        $scheme = $data['protect'] ?? $inject['protect'] ?? null;

        return is_string( $scheme ) ? $scheme : '';
    }


    /**
     * Returns how the date of the entry hides it now and after its date.
     *
     * Collections can hide their entries with a date in the future (scheduled) or in the past (expired). The "private"
     * date behavior hides the entries completely, the "unlisted" one only from listings. Entries with a date in the
     * future change their behavior at their date, e.g. events which expire then.
     *
     * @return array{behavior: string, reason: string, at: int|null, then: string|null}|null Current date behavior ("private", "unlisted" or "public"), reason why it's hidden, the time when the behavior changes and the behavior then or NULL if the date doesn't hide the entry
     */
    public function dateRestriction( object $entry ): ?array
    {
        $settings = $this->settings( (string) ( $entry->collection ?? '' ) );
        $time = is_string( $date = $entry->date ?? null ) ? strtotime( $date . ' UTC' ) : false;

        // the date of entries is only used by Statamic in dated collections
        if( $time === false || empty( $settings['dated'] ) ) {
            return null;
        }

        $future = $time > time();
        $hides = fn( mixed $behavior ) => in_array( $behavior, ['private', 'unlisted'], true ) ? $behavior : 'public';
        $behavior = $hides( $settings[$future ? 'future_date_behavior' : 'past_date_behavior'] ?? null );
        $then = $future ? $hides( $settings['past_date_behavior'] ?? null ) : null;

        if( $behavior === 'public' && ( $then ?? 'public' ) === 'public' ) {
            return null;
        }

        $reason = $future ? 'it is scheduled for ' . gmdate( 'Y-m-d H:i', $time ) . ' UTC' : 'it expired on ' . gmdate( 'Y-m-d H:i', $time ) . ' UTC';

        return ['behavior' => $behavior, 'reason' => $reason, 'at' => $future ? $time : null, 'then' => $then];
    }


    /**
     * Tests if the entry exists in any site, also as draft.
     *
     * @param string $id Entry ID
     */
    public function hasEntry( string $id ): bool
    {
        return $this->db->table( 'entries' )->where( 'id', $id )->exists();
    }


    /**
     * Tests if the SEO Pro settings like its site defaults are stored in the database.
     *
     * Older SEO Pro versions store them in the "content/seo.yaml" file instead.
     */
    public function hasSeoSettings(): bool
    {
        return $this->seoSettings() !== [];
    }


    /**
     * Returns the SEO Pro section and site defaults which SEO Pro uses if a field referenced by the entry is empty.
     *
     * @param object $entry Statamic entry
     * @return array<int, array<string, mixed>> Section and site defaults without inherited values
     */
    public function seoFallbacks( object $entry ): array
    {
        $inject = $this->decode( $this->settings( (string) ( $entry->collection ?? '' ) )['inject'] ?? [] );

        return [
            is_array( $inject['seo'] ?? null ) ? $this->filled( $inject['seo'] ) : [],
            $this->filled( $this->seoDefaults( (string) ( $entry->site ?? $this->site ) ) ),
        ];
    }


    /**
     * Returns the IDs of the published entries of a collection which isn't structured in the sort order of Statamic.
     *
     * Statamic sorts the entries by the "sort_field" and "sort_dir" settings of the collection, by default dated
     * collections by date in descending order and the other collections by title.
     *
     * @param string $collection Collection handle
     * @return array<int, int|string>|null Entry IDs in sort order or NULL if the tree defines the order or it's unknown
     */
    public function sorted( string $collection ): ?array
    {
        $settings = $this->settings( $collection );

        // the sort order of collections without stored settings is unknown
        if( !$this->hasSettings( $collection ) || $this->isStructured( $collection ) ) {
            return null;
        }

        $field = is_string( $settings['sort_field'] ?? null ) && $settings['sort_field'] !== '' ? $settings['sort_field'] : null;
        $dir = match( is_string( $settings['sort_dir'] ?? null ) ? strtolower( $settings['sort_dir'] ) : null ) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $field === null && !empty( $settings['dated'] ) ? 'desc' : 'asc',
        };
        $field ??= !empty( $settings['dated'] ) ? 'date' : 'title';

        if( !preg_match( '/^[A-Za-z_][\w]*$/', $field ) ) {
            return null;
        }

        $column = in_array( $field, $this->entryColumns(), true ) ? $field : 'data->' . $field;

        return $this->db->table( 'entries' )
            ->where( 'site', $this->site )
            ->where( 'published', true )
            ->where( 'collection', $collection )
            ->orderBy( $column, $dir )
            ->orderBy( 'id' )
            ->pluck( 'id' )
            ->all();
    }


    /**
     * Tests if the entries of the collection must be imported again although they weren't changed.
     *
     * This is the case if the collection was changed since or if its entries are hidden depending on their dates.
     *
     * @param string $collection Collection handle
     * @param int $since Timestamp of the --since option
     */
    public function refresh( string $collection, int $since ): bool
    {
        $settings = $this->settings( $collection );
        $updated = $this->collections()[$collection]['updated'] ?? null;

        return $updated !== null && $updated >= $since
            || !empty( $settings['dated'] ) && (
                in_array( $settings['future_date_behavior'] ?? null, ['private', 'unlisted'], true )
                || in_array( $settings['past_date_behavior'] ?? null, ['private', 'unlisted'], true )
            );
    }


    /**
     * Returns the title of the entry or its slug if it has no title.
     *
     * @param array<string, mixed> $data Decoded entry data
     */
    public function title( object $entry, array $data ): string
    {
        return (string) ( ( is_string( $data['title'] ?? null ) ? $data['title'] : '' ) ?: ( $entry->slug ?? '' ) ?: 'Untitled' );
    }


    /**
     * Fetches the trees of the structured collections in the site.
     *
     * The trees of the site are used, the ones without locale only if there's none of the site.
     *
     * @return array<string, array<int, array<string, mixed>>> Trees by collection handle
     */
    public function trees(): array
    {
        if( !$this->exists( 'trees' ) ) {
            return [];
        }

        $rows = $this->db->table( 'trees' )
            ->where( 'type', 'collection' )
            ->whereIn( 'handle', $this->collections )
            ->where( fn( $query ) => $query->where( 'locale', $this->site )->orWhereNull( 'locale' ) )
            ->get( ['handle', 'locale', 'tree'] )
            ->sortBy( fn( $row ) => $row->locale === null ? 1 : 0 );

        $trees = [];

        foreach( $rows as $row )
        {
            if( !isset( $trees[(string) $row->handle] ) && ( $tree = array_values( $this->decode( $row->tree ) ) ) !== [] ) {
                $trees[(string) $row->handle] = $tree;
            }
        }

        return $trees;
    }


    /**
     * Returns the information of the asset used for the imported file.
     *
     * @param \stdClass $row assets_meta record
     * @return array{name: string, extension: string, mime: string, alt: string} Title, alt text or file name, the extension, the MIME type and the alt text of the asset
     */
    protected function assetInfo( \stdClass $row ): array
    {
        $info = $this->decode( $row->meta ?? null );
        $mime = is_string( $info['mime_type'] ?? null ) && preg_match( '#^[\w.+-]+/[\w.+-]+$#', $info['mime_type'] ) ? strtolower( $info['mime_type'] ) : '';
        $meta = is_array( $info['data'] ?? null ) ? $info['data'] : [];
        $path = (string) $row->path;

        $names = array_filter( [$meta['title'] ?? null, $meta['alt'] ?? null, $row->filename ?? null], fn( $value ) => is_string( $value ) && trim( $value ) !== '' );
        $ext = is_string( $row->extension ?? null ) && $row->extension !== '' ? $row->extension : pathinfo( $path, PATHINFO_EXTENSION );

        return [
            'name' => trim( (string) ( reset( $names ) ?: pathinfo( $path, PATHINFO_FILENAME ) ) ),
            'extension' => $ext,
            'mime' => $mime,
            'alt' => is_string( $meta['alt'] ?? null ) ? trim( $meta['alt'] ) : '',
        ];
    }


    /**
     * Returns the settings of the fields in the blueprint data, also of the fields in sets and imported fieldsets.
     *
     * Fields in sets are available by "set.field" and by their handle if no other field uses the handle, the
     * first field of a handle is used then.
     *
     * @param array<mixed> $data Blueprint data
     * @return BlueprintFields
     */
    protected function blueprintFields( array $data ): array
    {
        $result = ['containers' => [], 'texts' => [], 'grids' => [], 'videos' => [], 'links' => [], 'across' => [], 'lists' => [], 'arrays' => [], 'images' => []];
        $fields = $this->fields( $data );

        // the keys of all fields are used before the handles of the fields in sets
        foreach( [false, true] as $plain )
        {
            foreach( $fields as ['key' => $key, 'handle' => $handle, 'set' => $set, 'field' => $field] )
            {
                if( $plain && $set === null ) {
                    continue;
                }

                $key = $plain ? $handle : $key;
                $type = $field['type'] ?? null;

                if( $type === 'assets' && is_string( $container = $field['container'] ?? null ) && $container !== '' ) {
                    $result['containers'][$key] ??= $container;
                }

                if( in_array( $type, static::TEXT_TYPES, true ) )
                {
                    $result['texts'][$key] ??= [
                        'type' => (string) $type,
                        'breaks' => (bool) ( $field['automatic_line_breaks'] ?? true ),
                        'links' => (bool) ( $field['automatic_links'] ?? false ),
                        'escape' => (bool) ( $field['escape_markup'] ?? false ),
                        'smart' => (bool) ( $field['smartypants'] ?? false ),
                        'antlers' => (bool) ( $field['antlers'] ?? false ),
                    ] + ( $type === 'code' ? ['mode' => is_string( $field['mode'] ?? null ) ? $field['mode'] : 'htmlmixed'] : [] );
                }

                if( $type === 'grid' ) {
                    $result['grids'][$key] = true;
                }

                if( $type === 'video' ) {
                    $result['videos'][$key] = true;
                }

                if( $type === 'link' ) {
                    $result['links'][$key] = true;
                }

                // Link and Bard fields selecting entries across sites don't link to the entries of the current site,
                // all are stored so fields in sets don't use the setting of other fields with the same handle
                if( in_array( $type, ['link', 'bard'], true ) ) {
                    $result['across'][$key] ??= !empty( $field['select_across_sites'] );
                }

                if( $type === 'list' ) {
                    $result['lists'][$key] = true;
                }

                if( $type === 'array' ) {
                    $result['arrays'][$key] ??= $this->arrayField( $field );
                }

                if( !$plain && $set === null && $type === 'assets' && (int) ( $field['max_files'] ?? 0 ) === 1 ) {
                    $result['images'][] = $handle;
                }
            }
        }

        return $result;
    }


    /**
     * Returns the labels of the keys and the column headers of an Array field.
     *
     * The keys are configured like {"street": "Street"} or like [{"key": "street", "value": "Street"}] and
     * the headers of the key and value columns are only shown in the dynamic mode.
     *
     * @param array<mixed> $field Array field settings
     * @return ArrayField Labels by key and headers of the key and value columns, empty if not set
     */
    protected function arrayField( array $field ): array
    {
        $labels = [];

        foreach( (array) ( $field['keys'] ?? [] ) as $key => $label )
        {
            [$key, $label] = is_array( $label ) ? [$label['key'] ?? null, $label['value'] ?? null] : [$key, $label];

            if( is_scalar( $key ) && is_scalar( $label ) && ( $label = trim( (string) $label ) ) !== '' ) {
                $labels[(string) $key] = $label;
            }
        }

        $header = fn( string $name ) => ( $field['mode'] ?? 'dynamic' ) === 'dynamic' && is_scalar( $field[$name] ?? null ) ? trim( (string) $field[$name] ) : '';

        return ['labels' => $labels, 'headers' => [$header( 'key_header' ), $header( 'value_header' )]];
    }


    /**
     * Returns the fields in the blueprint or fieldset data including the fields of their sets and imported fieldsets.
     *
     * Fieldsets are imported like {"import": "seo", "prefix": "seo_"} and fields reference the fields of fieldsets
     * like {"handle": "body", "field": "common.body", "config": {...}} with optional settings overwriting the ones
     * of the fieldset field.
     *
     * @param array<mixed> $data Blueprint or fieldset data or a part of it
     * @param string|null $set Handle of the Replicator or Bard set containing the fields
     * @param string $prefix Prefix of the handles of the fields from imported fieldsets
     * @param int $depth Number of fieldsets imported or referenced before
     * @return array<int, array{key: string, handle: string, set: string|null, field: array<mixed>}> Fields in the order of the blueprint
     */
    protected function fields( array $data, ?string $set = null, string $prefix = '', int $depth = 0 ): array
    {
        $fields = [];

        foreach( $data as $value )
        {
            if( !is_array( $value ) ) {
                continue;
            }

            if( is_string( $handle = $value['handle'] ?? null ) && isset( $value['field'] ) )
            {
                if( ( $field = $this->fieldConfig( $value, $depth ) ) !== null ) {
                    array_push( $fields, ...$this->field( $prefix . $handle, $field, $set, $depth ) );
                }
                continue;
            }

            if( is_string( $import = $value['import'] ?? null ) )
            {
                if( $depth < static::FIELDSET_DEPTH && ( $fieldset = $this->fieldsets()[$import] ?? null ) ) {
                    array_push( $fields, ...$this->fields( $fieldset, $set, $prefix . ( is_string( $value['prefix'] ?? null ) ? $value['prefix'] : '' ), $depth + 1 ) );
                }
                continue;
            }

            array_push( $fields, ...$this->fields( $value, $set, $prefix, $depth ) );
        }

        return $fields;
    }


    /**
     * Returns the field and the fields of its Replicator or Bard sets and of its Grid or Group rows.
     *
     * @param string $handle Field handle
     * @param array<mixed> $field Field settings
     * @param string|null $set Handle of the set containing the field
     * @param int $depth Number of fieldsets imported or referenced before
     * @return array<int, array{key: string, handle: string, set: string|null, field: array<mixed>}> Field and its nested fields
     */
    protected function field( string $handle, array $field, ?string $set, int $depth ): array
    {
        $fields = [['key' => $set !== null ? $set . '.' . $handle : $handle, 'handle' => $handle, 'set' => $set, 'field' => $field]];
        $type = $field['type'] ?? null;

        if( in_array( $type, ['replicator', 'bard'], true ) && is_array( $field['sets'] ?? null ) )
        {
            foreach( $this->sets( $field['sets'] ) as $name => $setFields ) {
                array_push( $fields, ...$this->fields( $setFields, (string) $name, '', $depth ) );
            }
        }
        elseif( in_array( $type, ['grid', 'group'], true ) && is_array( $field['fields'] ?? null ) )
        {
            array_push( $fields, ...$this->fields( $field['fields'], $set, '', $depth ) );
        }

        return $fields;
    }


    /**
     * Returns the settings of the field, of fields referencing fieldset fields merged with their own settings.
     *
     * @param array<mixed> $value Field like {"handle": "body", "field": {...}} or {"handle": "body", "field": "common.body", "config": {...}}
     * @param int $depth Number of fieldsets imported or referenced before
     * @return array<mixed>|null Field settings or NULL if the referenced field is unknown
     */
    protected function fieldConfig( array $value, int $depth ): ?array
    {
        $field = $value['field'] ?? null;

        if( is_array( $field ) ) {
            return $field;
        }

        if( !is_string( $field ) || ( $pos = strrpos( $field, '.' ) ) === false || $depth >= static::FIELDSET_DEPTH ) {
            return null;
        }

        if( ( $config = $this->fieldsetField( substr( $field, 0, $pos ), substr( $field, $pos + 1 ), $depth + 1 ) ) === null ) {
            return null;
        }

        return array_replace( $config, is_array( $value['config'] ?? null ) ? $value['config'] : [] );
    }


    /**
     * Returns the settings of the field in the fieldset, also of fields imported from other fieldsets.
     *
     * @param string $fieldset Fieldset handle
     * @param string $handle Field handle including the prefix of an imported fieldset
     * @param int $depth Number of fieldsets imported or referenced before
     * @return array<mixed>|null Field settings or NULL if the field is unknown
     */
    protected function fieldsetField( string $fieldset, string $handle, int $depth ): ?array
    {
        foreach( (array) ( $this->fieldsets()[$fieldset]['fields'] ?? [] ) as $value )
        {
            if( !is_array( $value ) ) {
                continue;
            }

            if( ( $value['handle'] ?? null ) === $handle && isset( $value['field'] ) ) {
                return $this->fieldConfig( $value, $depth );
            }

            $prefix = is_string( $value['prefix'] ?? null ) ? $value['prefix'] : '';

            if( is_string( $import = $value['import'] ?? null ) && $depth < static::FIELDSET_DEPTH && str_starts_with( $handle, $prefix )
                && ( $field = $this->fieldsetField( $import, substr( $handle, strlen( $prefix ) ), $depth + 1 ) ) !== null )
            {
                return $field;
            }
        }

        return null;
    }


    /**
     * Returns the data of the fieldsets stored in the database.
     *
     * @return array<string, array<mixed>> Fieldset data by handle
     */
    protected function fieldsets(): array
    {
        if( $this->fieldsets === null )
        {
            $this->fieldsets = [];

            if( $this->exists( 'fieldsets' ) )
            {
                foreach( $this->db->table( 'fieldsets' )->get( ['handle', 'data'] ) as $row ) {
                    $this->fieldsets[(string) $row->handle] = $this->decode( $row->data );
                }
            }
        }

        return $this->fieldsets;
    }


    /**
     * Returns the fields of the Replicator or Bard sets, also of the sets in set groups.
     *
     * @param array<mixed> $sets Sets by handle like {"quote": {"fields": [...]}} or set groups like {"main": {"sets": {...}}}
     * @return array<int|string, array<mixed>> Fields by set handle
     */
    protected function sets( array $sets ): array
    {
        $result = [];

        foreach( $sets as $name => $value )
        {
            if( is_array( $value['sets'] ?? null ) ) {
                $result += $this->sets( $value['sets'] );
            } elseif( is_array( $value['fields'] ?? null ) ) {
                $result[$name] ??= $value['fields'];
            }
        }

        return $result;
    }


    /**
     * Returns the entry data with the values the collection injects into its entries and the SEO Pro defaults.
     *
     * Statamic uses the injected values (the "cascade") if the entry has no own values. SEO Pro merges the
     * values of its site defaults, the section defaults injected by the collection and the ones of the entry.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param string $collection Collection handle
     * @param string $site Site handle of the entry
     * @return array<string, mixed> Entry data with defaults
     */
    protected function defaults( array $data, string $collection, string $site ): array
    {
        $inject = $this->decode( $this->settings( $collection )['inject'] ?? [] );
        $result = array_replace( $inject, $data );

        $own = $data['seo'] ?? null;
        $section = $inject['seo'] ?? null;

        // collections disabling SEO only disable it for entries without own SEO values, the site defaults are used for the others
        if( $own !== false && ( $section !== false || is_array( $own ) ) )
        {
            // inherited values are left out and don't overwrite the defaults
            $seo = array_replace( $this->filled( $this->seoDefaults( $site ) ), is_array( $section ) ? $this->filled( $section ) : [], is_array( $own ) ? $this->filled( $own ) : [] );

            if( $seo !== [] ) {
                $result['seo'] = $seo;
            }
        }

        return $result;
    }


    /**
     * Returns the existing columns of the entries table.
     *
     * @return array<int, string> Column names
     */
    protected function entryColumns(): array
    {
        return $this->entryColumns ??= $this->db->getSchemaBuilder()->getColumnListing( 'entries' );
    }


    /**
     * Returns the decoded data of an entries record including the fields stored in their own columns.
     *
     * Localized entries only use the fields listed in their localized fields like the Eloquent driver, the
     * other fields are inherited from their origin.
     *
     * @param \stdClass $row Entries record
     * @param array<int, string> $mapped Columns containing data fields
     * @param bool $localized TRUE if the entry has an origin
     * @return array<string, mixed> Decoded data
     */
    protected function rowData( \stdClass $row, array $mapped, bool $localized ): array
    {
        $data = $this->decode( $row->data );

        foreach( $mapped as $column )
        {
            if( ( $value = $row->{$column} ?? null ) !== null ) {
                $data[$column] = is_string( $value ) && preg_match( '/^\s*[\[{]/', $value ) && is_array( $json = json_decode( $value, true ) ) ? $json : $value;
            }
        }

        $fields = $data[self::LOCALIZED_KEY] ?? null;
        unset( $data[self::LOCALIZED_KEY] );

        return $localized && is_array( $fields ) ? array_intersect_key( $data, array_flip( array_filter( $fields, 'is_string' ) ) ) : $data;
    }


    /**
     * Returns the SEO Pro site defaults of the site.
     *
     * SEO Pro stores its site defaults in its addon settings, by site handle if there are several sites.
     * Sites inherit the defaults of their origin site.
     *
     * @param string $site Site handle
     * @return array<string, mixed> Site defaults, empty if they aren't stored in the database
     */
    protected function seoDefaults( string $site ): array
    {
        if( isset( $this->seoDefaults[$site] ) ) {
            return $this->seoDefaults[$site];
        }

        $defaults = $this->decode( $this->seoSettings()['site_defaults'] ?? [] );
        $origins = $this->decode( $this->seoSettings()['site_defaults_sites'] ?? [] );
        // the defaults are stored by site handle if multiple sites exist
        if( count( static::sites( $this->db ) ?? [] ) < 2 && !is_array( $defaults[$site] ?? null ) ) {
            return $this->seoDefaults[$site] = $defaults;
        }

        $chain = [];

        for( $handle = $site; is_string( $handle ) && !isset( $chain[$handle] ) && count( $chain ) <= static::ORIGIN_DEPTH; $handle = $origins[$handle] ?? null ) {
            $chain[$handle] = $this->decode( $defaults[$handle] ?? [] );
        }

        return $this->seoDefaults[$site] = array_replace( ...array_reverse( array_values( $chain ) ) ?: [[]] );
    }


    /**
     * Returns the SEO Pro settings stored in the addon settings.
     *
     * @return array<string, mixed> SEO Pro settings, empty if they aren't stored in the database
     */
    protected function seoSettings(): array
    {
        if( $this->seoSettings === null )
        {
            $this->seoSettings = [];

            if( $this->exists( 'addon_settings' ) && ( $row = $this->db->table( 'addon_settings' )->where( 'addon', 'statamic/seo-pro' )->first( ['settings'] ) ) ) {
                $this->seoSettings = $this->decode( $row->settings );
            }
        }

        return $this->seoSettings;
    }


    /**
     * Returns the values which aren't NULL.
     *
     * @param array<string, mixed> $values Values by key
     * @return array<string, mixed> Values without NULL values
     */
    protected function filled( array $values ): array
    {
        return array_filter( $values, fn( $value ) => $value !== null );
    }


    /**
     * Returns the existing columns of the entries from the given ones.
     *
     * @param array<int, string> $names Column names
     * @return array<int, string> Existing column names
     */
    protected function columns( array $names ): array
    {
        return array_values( array_intersect( $names, $this->entryColumns() ) );
    }


    /**
     * Returns the keys of the assets with the path in any container.
     *
     * @param array<int, string> $keys Assets like "container::path"
     * @param string $path Path of the asset
     * @return array<int, string> Matching keys
     */
    protected function keys( array $keys, string $path ): array
    {
        return array_values( array_filter( $keys, fn( $key ) => ( explode( '::', $key, 2 )[1] ?? '' ) === $path ) );
    }


    /**
     * Returns the data of the entry merged with the data of its origins and the ID of its topmost origin.
     *
     * @param string $id Entry ID
     * @param array<string, array{origin: string|null, data: array<string, mixed>}> $rows Fetched entries by ID
     * @return array{0: array<string, mixed>, 1: string} Merged data and the ID of the topmost origin
     */
    protected function merged( string $id, array $rows ): array
    {
        $chain = [];

        // origins which weren't fetched or loops end the chain
        while( isset( $rows[$id] ) && !isset( $chain[$id] ) && count( $chain ) <= static::ORIGIN_DEPTH )
        {
            $chain[$id] = $rows[$id]['data'];

            if( ( $id = $rows[$id]['origin'] ) === null ) {
                break;
            }
        }

        $root = (string) ( array_key_last( $chain ) ?? $id );

        return [array_replace( ...array_reverse( array_values( $chain ) ) ?: [[]] ), $root];
    }


    /**
     * Returns the query for the published entries of the collections and site in import order.
     *
     * @param array<int, string> $columns Fetched columns if they exist
     */
    protected function published( array $columns ): \Illuminate\Database\Query\Builder
    {
        return $this->db
            ->table( 'entries' )
            ->where( 'site', $this->site )
            ->where( 'published', true )
            ->whereIn( 'collection', $this->collections )
            ->orderBy( 'order', 'asc' )
            ->orderBy( 'date', 'asc' )
            ->select( $this->columns( $columns ) );
    }


    /**
     * Returns the decoded data of an entry.
     *
     * @param mixed $data JSON encoded or decoded data or NULL if the entry has no data
     * @return array<string, mixed>
     */
    protected function decode( mixed $data ): array
    {
        if( is_string( $data ) ) {
            return (array) ( json_decode( $data, true ) ?: [] );
        }

        return (array) json_decode( (string) json_encode( $data ?? [] ), true );
    }


    /**
     * Returns the settings and modification times of the imported collections.
     *
     * @return array<string, array{settings: array<string, mixed>, updated: int|null}> Settings and modification times by collection handle
     */
    protected function collections(): array
    {
        if( $this->settings === null )
        {
            $this->settings = [];
            $columns = $this->exists( 'collections' ) ? $this->db->getSchemaBuilder()->getColumnListing( 'collections' ) : [];

            if( in_array( 'settings', $columns, true ) )
            {
                $rows = $this->db->table( 'collections' )->whereIn( 'handle', $this->collections )
                    ->get( array_values( array_intersect( ['handle', 'settings', 'updated_at'], $columns ) ) );

                foreach( $rows as $row )
                {
                    // compared with the --since date like the modification times of the entries
                    $time = is_string( $date = $row->updated_at ?? null ) ? strtotime( $date ) : false;
                    $this->settings[(string) $row->handle] = ['settings' => $this->decode( $row->settings ), 'updated' => $time !== false ? $time : null];
                }
            }
        }

        return $this->settings;
    }


    /**
     * Returns the settings of the collection.
     *
     * @param string $collection Collection handle
     * @return array<string, mixed> Collection settings, empty if the collection or its settings don't exist
     */
    protected function settings( string $collection ): array
    {
        return $this->collections()[$collection]['settings'] ?? [];
    }


    /**
     * Tests if the table exists in the Statamic database.
     */
    protected function exists( string $table ): bool
    {
        return $this->db->getSchemaBuilder()->hasTable( $table );
    }
}
