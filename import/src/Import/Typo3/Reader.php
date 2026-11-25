<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Utils;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;


/**
 * Reads the pages, content elements, files and configuration of the TYPO3 database.
 */
class Reader
{
    /** Columns of the page records which are used */
    protected const PAGE_COLUMNS = ['uid', 'pid', 'title', 'nav_title', 'seo_title', 'slug', 'doktype', 'is_siteroot', 'hidden', 'nav_hide',
        'crdate', 'content_from_pid', 'shortcut', 'shortcut_mode', 'url', 'backend_layout', 'backend_layout_next_level', 'TSconfig',
        'sorting', 'sys_language_uid', 'l10n_parent', 'fe_group', 'starttime', 'endtime', 'extendToSubpages', 'mount_pid', 'l18n_cfg',
        'description', 'keywords', 'og_title', 'og_description', 'twitter_title', 'twitter_description', 'no_index', 'no_follow', 'canonical_link',
        'tsconfig_includes', 'mount_pid_ol'];

    /** Columns of the pages which are only used in the default language and not fetched for the translations */
    protected const DEFAULT_COLUMNS = ['crdate', 'content_from_pid', 'backend_layout', 'backend_layout_next_level', 'TSconfig', 'mount_pid', 'l18n_cfg',
        'tsconfig_includes', 'mount_pid_ol'];

    /** Columns of the content records which are used */
    protected const CONTENT_COLUMNS = ['uid', 'pid', 'CType', 'header', 'header_layout', 'header_link', 'subheader', 'bodytext', 'imageorient',
        'records', 'colPos', 'sorting', 'sys_language_uid', 'bullets_type', 'table_caption', 'table_delimiter', 'table_enclosure',
        'table_header_position', 'tx_container_parent', 'tx_gridelements_container', 'tx_gridelements_columns', 'file_collections',
        'filelink_sorting', 'filelink_sorting_direction', 'cols', 'quote_source', 'quote_link', 'external_media_source', 'date',
        'icon'];

    /** Columns of the file references which are used */
    protected const REFERENCE_COLUMNS = ['uid', 'uid_local', 'uid_foreign', 'tablenames', 'fieldname', 'sys_language_uid', 'title', 'alternative',
        'description', 'link', 'crop'];

    /** Columns of the file records which are used */
    protected const FILE_COLUMNS = ['uid', 'storage', 'identifier', 'name', 'extension', 'mime_type', 'size', 'creation_date', 'modification_date'];

    /** Columns of the file metadata which are used if the file references don't overwrite them */
    protected const METADATA_COLUMNS = ['file', 'sys_language_uid', 'title', 'alternative', 'description'];


    /** @var array<string, array<int, string>> Column names by TYPO3 table name */
    protected array $listings = [];

    /** @var array<int, int>|null Storage UIDs of the sys_file records */
    protected ?array $storages = null;


    /**
     * Initializes the object.
     *
     * @param Connection $db Connection to the TYPO3 database
     */
    public function __construct( protected Connection $db )
    {
    }


    /**
     * Fetches non-deleted TYPO3 pages in default language of all page types.
     *
     * Folders, spacers and recyclers are fetched too because they are part of the page tree,
     * e.g. for the inherited backend layouts and restrictions. Tree decides which pages are imported.
     *
     * @return Collection<int, mixed>
     */
    public function pages(): Collection
    {
        return $this->db
            ->table( 'pages' )
            ->where( 'deleted', 0 )
            ->where( 'sys_language_uid', 0 )
            ->where( 't3ver_wsid', 0 )
            // TYPO3 9 and older store the versions of the live workspace with pid -1
            ->where( 'pid', '>=', 0 )
            ->orderBy( 'sorting', 'asc' )
            ->get( $this->columns( 'pages', self::PAGE_COLUMNS ) );
    }


    /**
     * Fetches active tt_content records of the pages in default language or all languages.
     *
     * @param array<int, int> $pids UIDs of the TYPO3 pages
     * @return Collection<int, mixed> Records ordered by their sorting within each page
     */
    public function content( array $pids ): Collection
    {
        return Database::chunked( $pids, fn( $chunk ) => $this->contentQuery()
            ->whereIn( 'pid', $chunk )
            ->whereIn( 'sys_language_uid', [0, -1] )
            ->orderBy( 'sorting', 'asc' )
            ->orderBy( 'uid', 'asc' )
            ->get( $this->columns( 'tt_content', self::CONTENT_COLUMNS ) )
        );
    }


    /**
     * Fetches active tt_content records in default language or all languages keyed by UID.
     *
     * @param array<int, int> $uids UIDs of the content records
     * @return Collection<int, mixed>
     */
    public function contentRecords( array $uids ): Collection
    {
        return Database::chunked( $uids, fn( $chunk ) => $this->contentQuery()
            ->whereIn( 'uid', $chunk )
            ->whereIn( 'sys_language_uid', [0, -1] )
            ->get( $this->columns( 'tt_content', self::CONTENT_COLUMNS ) )
        )->keyBy( fn( $record ) => (int) $record->uid );
    }


    /**
     * Returns the page UIDs of the content records including deleted and hidden ones.
     *
     * @param array<int, int> $uids UIDs of the content records
     * @return array<int, int> Page UIDs by content UID
     */
    public function contentPids( array $uids ): array
    {
        return Database::chunked( $uids, fn( $chunk ) => $this->db->table( 'tt_content' )
            ->whereIn( 'uid', $chunk )
            ->get( ['uid', 'pid'] )
        )->mapWithKeys( fn( $record ) => [(int) $record->uid => (int) $record->pid] )->all();
    }


    /**
     * Fetches the UIDs, pages and targets of the active content shortcuts in default language or all languages.
     *
     * @return Collection<int, mixed>
     */
    public function shortcuts(): Collection
    {
        return $this->contentQuery()
            ->where( 'CType', 'shortcut' )
            ->whereIn( 'sys_language_uid', [0, -1] )
            ->get( ['uid', 'pid', 'records'] );
    }


    /**
     * Fetches the enabled redirects of the TYPO3 redirects module which are active now ordered by UID.
     *
     * @return Collection<int, mixed> sys_redirect records or an empty collection if the table doesn't exist
     */
    public function redirects(): Collection
    {
        if( !( $query = $this->table( 'sys_redirect' ) ) ) {
            return Collection::make();
        }

        $columns = array_flip( $this->listing( 'sys_redirect' ) );

        foreach( ['deleted', 'disabled'] as $column )
        {
            if( isset( $columns[$column] ) ) {
                $query->where( $column, 0 );
            }
        }

        return $this->visible( 'sys_redirect', $query )->orderBy( 'uid' )->get( $this->columns( 'sys_redirect',
            ['uid', 'source_host', 'source_path', 'is_regexp', 'target', 'target_statuscode', 'respect_query_parameters'] ) );
    }


    /**
     * Fetches database-backed TYPO3 backend layouts keyed by UID.
     *
     * @return Collection<int|string, mixed>
     */
    public function backendLayouts(): Collection
    {
        return $this->table( 'backend_layout' )
            ?->where( 'deleted', 0 )
            ->where( 'hidden', 0 )
            ->get()
            ->keyBy( 'uid' ) ?? Collection::make();
    }


    /**
     * Fetches visible Bootstrap Package items of the content elements grouped by content UID, e.g. accordion or carousel items.
     *
     * @param string $table Name of the item table
     * @param array<int, int> $uids UIDs of the content elements
     * @param array<int, string> $columns Further columns of the items which are fetched if they exist
     * @return Collection<int|string, mixed>
     */
    public function items( string $table, array $uids, array $columns = [] ): Collection
    {
        if( !( $columns = $this->columns( $table, ['uid', 'tt_content', 'header', 'bodytext', ...$columns] ) ) || !in_array( 'tt_content', $columns, true ) ) {
            return Collection::make();
        }

        // translated items belong to the translated content records
        return Database::chunked( $uids, fn( $chunk ) => $this->visible( $table, $this->db
            ->table( $table )
            ->where( 'deleted', 0 )
            ->where( 'hidden', 0 ) )
            ->whereIn( 'tt_content', $chunk )
            ->orderBy( 'sorting', 'asc' )
            ->get( $columns )
        )->groupBy( 'tt_content' );
    }


    /**
     * Fetches the file references of the Bootstrap Package items grouped by item UID.
     *
     * The sys_file record of each reference is added as "sys_file" property.
     *
     * @param string $table Name of the item table
     * @param array<int, int> $uids UIDs of the items
     * @param array<int, string> $fields Names of the fields with the files
     * @return Collection<int|string, mixed>
     */
    public function itemFileReferences( string $table, array $uids, array $fields ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', $table )
            ->whereIn( 'fieldname', $fields )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Returns the files of the TYPO3 file collections as file references in their order.
     *
     * Static collections contain the files referenced by them, folder collections the files of their folder.
     * Collections by category aren't supported.
     *
     * @param array<int, int> $uids UIDs of the sys_file_collection records
     * @return array{refs: Collection<int, mixed>, skipped: array<int, string>} File references with their sys_file record and the types of the skipped collections by UID
     */
    public function collectionFiles( array $uids ): array
    {
        if( !$uids || !( $query = $this->table( 'sys_file_collection' ) ) ) {
            return ['refs' => Collection::make(), 'skipped' => []];
        }

        $query = $this->visible( 'sys_file_collection', $query->where( 'deleted', 0 )->where( 'hidden', 0 ) );
        $collections = $query->whereIn( 'uid', $uids )->get()->keyBy( fn( $item ) => (int) $item->uid );
        $refs = Collection::make();
        $skipped = [];

        // the collections are used in the order of the content element
        foreach( $uids as $uid )
        {
            if( !( $collection = $collections->get( $uid ) ) ) {
                continue;
            }

            $type = (string) ( $collection->type ?? 'static' );

            if( $type === 'static' )
            {
                $refs = $refs->concat( $this->withFiles( $this->referenceQuery()
                    ->where( 'tablenames', 'sys_file_collection' )
                    ->where( 'fieldname', 'files' )
                    ->where( 'uid_foreign', $uid )
                    ->orderBy( 'sorting_foreign', 'asc' )
                    ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
                ) );
            }
            elseif( $type === 'folder' )
            {
                $refs = $refs->concat( $this->folderFiles( $collection ) );
            }
            else
            {
                $skipped[$uid] = $type;
            }
        }

        return ['refs' => $refs->filter( fn( $ref ) => isset( $ref->sys_file ) )->values(), 'skipped' => $skipped];
    }


    /**
     * Returns the files of the folder of the folder collection as file references ordered by name.
     *
     * TYPO3 11 and older store the folder in the "storage" and "folder" fields, newer versions combined in "folder_identifier" like "1:/images/".
     *
     * @param object $collection sys_file_collection record
     * @return Collection<int, mixed> File references with their sys_file record
     */
    protected function folderFiles( object $collection ): Collection
    {
        $identifier = (string) ( $collection->folder_identifier ?? '' );
        [$storage, $folder] = str_contains( $identifier, ':' )
            ? explode( ':', $identifier, 2 )
            : [(string) ( $collection->storage ?? '0' ), (string) ( $collection->folder ?? '' )];

        $folder = '/' . trim( $folder, '/' ) . '/';
        $folder = $folder === '//' ? '/' : $folder;
        // wildcards in the folder name match more files, they are removed afterwards
        $like = $folder . '%';

        $files = $this->files()
            ->where( 'storage', (int) $storage )
            ->where( 'identifier', 'like', $like )
            ->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) )
            // the TYPO3 local driver lists the files in natural case-insensitive order of their identifiers
            ->sort( fn( $a, $b ) => strnatcasecmp( (string) $a->identifier, (string) $b->identifier ) );

        $files = $files->filter( fn( $file ) => str_starts_with( '/' . ltrim( (string) $file->identifier, '/' ), $folder ) );

        // subfolders are only included for recursive collections
        if( empty( $collection->recursive ) ) {
            $files = $files->filter( fn( $file ) => !str_contains( substr( '/' . ltrim( (string) $file->identifier, '/' ), strlen( $folder ) ), '/' ) );
        }

        return $files->values()->map( fn( $file ) => (object) [
            'uid' => 0, 'uid_local' => (int) $file->uid, 'tablenames' => 'sys_file_collection', 'uid_foreign' => (int) ( $collection->uid ?? 0 ),
            'fieldname' => 'files', 'sys_file' => $file,
        ] );
    }


    /**
     * Returns the UIDs of the original files of TYPO3 processed files, e.g. resized images in "_processed_" folders.
     *
     * @param array<int, string> $identifiers Identifiers of the processed files with leading slash
     * @return array<string, int> UIDs of the original sys_file records by identifier
     */
    public function processedFiles( array $identifiers ): array
    {
        if( !$identifiers || !( $query = $this->table( 'sys_file_processedfile' ) ) || !in_array( 'original', $this->listing( 'sys_file_processedfile' ), true ) ) {
            return [];
        }

        $originals = [];

        foreach( $query->whereIn( 'identifier', $identifiers )->get( ['identifier', 'original'] ) as $row ) {
            $originals[(string) $row->identifier] ??= (int) $row->original;
        }

        return $originals;
    }


    /**
     * Tests if the TYPO3 table contains the column.
     */
    public function hasColumn( string $table, string $column ): bool
    {
        return $this->db->getSchemaBuilder()->hasTable( $table ) && in_array( $column, $this->listing( $table ), true );
    }


    /**
     * Fetches the primary TYPO3 domain keyed by its root page UID.
     *
     * Legacy TYPO3 installations can assign multiple domains to one page tree.
     * The first visible record by TYPO3 sorting is the primary domain.
     *
     * @return array<int, string>
     */
    public function domains(): array
    {
        $domains = [];
        $records = $this->table( 'sys_domain' )
            ?->where( 'hidden', 0 )
            ->where( 'domainName', '<>', '' )
            ->orderBy( 'sorting', 'asc' )
            ->orderBy( 'uid', 'asc' )
            ->get( ['pid', 'domainName'] ) ?? [];

        foreach( $records as $record ) {
            $domains[(int) $record->pid] ??= (string) $record->domainName;
        }

        return $domains;
    }


    /**
     * Fetches the sys_file_reference records of the content elements grouped by content element UID.
     *
     * The sys_file record of each reference is added as "sys_file" property.
     *
     * @param array<int, int> $uids UIDs of the content elements
     * @return Collection<int|string, mixed>
     */
    public function fileReferences( array $uids ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', 'tt_content' )
            ->whereIn( 'fieldname', ['image', 'media', 'assets', 'icon_file'] )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches the media references of the accordion items grouped by accordion item UID.
     *
     * The sys_file record of each reference is added as "sys_file" property.
     *
     * @param array<int, int> $uids UIDs of the accordion items
     * @return Collection<int|string, mixed>
     */
    public function accordionFileReferences( array $uids ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', 'tx_bootstrappackage_accordion_item' )
            ->where( 'fieldname', 'media' )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches the social media image references of the pages grouped by page UID.
     *
     * The sys_file record of each reference is added as "sys_file" property.
     *
     * @param array<int, int> $uids UIDs of the pages in default language or of their translations
     * @return Collection<int|string, Collection<int, mixed>>
     */
    public function pageFileReferences( array $uids ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', 'pages' )
            ->whereIn( 'fieldname', ['og_image', 'twitter_image'] )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' )
            // the Twitter card image is only used if there's no Open Graph image
            ->map( fn( $refs ) => $refs->sortBy( fn( $ref ) => ( $ref->fieldname ?? 'og_image' ) === 'og_image' ? 0 : 1 )->values() );
    }


    /**
     * Returns the base paths of the local file storages keyed by storage UID.
     *
     * Storages with relative paths are below the public directory of TYPO3, e.g. "fileadmin/",
     * the legacy storage 0 refers to the public directory itself.
     *
     * @return array<int, string> Relative base paths without leading and trailing slash
     */
    public function storageBases(): array
    {
        $bases = [];

        foreach( $this->table( 'sys_file_storage' )?->where( 'deleted', 0 )->get() ?? [] as $storage )
        {
            $config = (string) ( $storage->configuration ?? '' );

            if( ( $storage->driver ?? 'Local' ) !== 'Local'
                || !preg_match( '#<field index="basePath">\s*<value index="vDEF">([^<]*)</value>#', $config, $match )
            ) {
                continue;
            }

            // absolute paths are outside of the public directory and can't be fetched by URL
            if( !preg_match( '#<field index="pathType">\s*<value index="vDEF">absolute</value>#', $config ) ) {
                $bases[(int) $storage->uid] = trim( html_entity_decode( $match[1], ENT_QUOTES | ENT_XML1 ), '/' );
            }
        }

        return $bases;
    }


    /**
     * Fetches the image and background-image references of the carousel items grouped by carousel item UID.
     *
     * The sys_file record of each reference is added as "sys_file" property, the field in the "fieldname" property.
     *
     * @param array<int, int> $uids UIDs of the carousel items
     * @return Collection<int|string, mixed>
     */
    public function carouselFileReferences( array $uids ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', 'tx_bootstrappackage_carousel_item' )
            ->whereIn( 'fieldname', ['image', 'background_image'] )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', [...self::REFERENCE_COLUMNS, 'fieldname'] ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches the sys_file records with the identifiers keyed by identifier with leading slash and by "<storage>:<identifier>".
     *
     * The records are looked up by the indexed hashes of the identifiers if available
     * because the identifiers themselves aren't indexed in the TYPO3 database. The hashes
     * are indexed together with the storage, so the storages are part of the condition.
     *
     * @param array<int, string> $identifiers Identifiers of the files with leading slash
     * @return Collection<string, mixed>
     */
    public function filesByIdentifier( array $identifiers ): Collection
    {
        if( !$identifiers ) {
            return Collection::make();
        }

        $query = $this->files();

        if( in_array( 'identifier_hash', $this->listing( 'sys_file' ), true ) )
        {
            // the hashes are case-insensitive for storages on case-insensitive file systems
            $hashes = array_map( fn( $id ) => [sha1( $id ), sha1( mb_strtolower( $id ) )], $identifiers );
            $query->whereIn( 'identifier_hash', array_values( array_unique( array_merge( ...$hashes ) ) ) );

            if( in_array( 'storage', $this->listing( 'sys_file' ), true ) ) {
                $query->whereIn( 'storage', $this->storages() );
            }
        }
        else
        {
            $query->whereIn( 'identifier', array_merge( $identifiers, array_map( fn( $id ) => ltrim( $id, '/' ), $identifiers ) ) );
        }

        $wanted = array_flip( $identifiers );
        $result = [];

        // files with the same identifier in several storages are also available by "<storage>:<identifier>", the first one by identifier only
        foreach( $query->orderBy( 'uid' )->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) ) as $file )
        {
            if( isset( $wanted[$id = '/' . ltrim( (string) $file->identifier, '/' )] ) )
            {
                $result[(int) ( $file->storage ?? 0 ) . ':' . $id] = $file;
                $result[$id] ??= $file;
            }
        }

        return Collection::make( $result );
    }


    /**
     * Fetches the TYPO3 sys_file records by UID.
     *
     * @param array<int, int> $uids UIDs of the sys_file records
     * @return Collection<int, mixed> sys_file records keyed by UID
     */
    public function filesByUid( array $uids ): Collection
    {
        return Database::chunked( $uids, fn( $chunk ) => $this->files()
            ->whereIn( 'uid', $chunk )
            ->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) )
        )->keyBy( fn( $file ) => (int) $file->uid );
    }


    /**
     * Returns the languages of the sys_language table keyed by language UID.
     *
     * @return array<int, array{lang: string, domain: string}>
     */
    public function languages(): array
    {
        $languages = [];

        foreach( $this->table( 'sys_language' )?->where( 'hidden', 0 )->get() ?? [] as $record )
        {
            $code = strtolower( trim( (string) ( $record->language_isocode ?? '' ) ) );

            if( $code !== '' && Utils::isValidLang( $code ) ) {
                $languages[(int) $record->uid] = Variants::language( $code );
            }
        }

        return $languages;
    }


    /**
     * Fetches the URL paths of the TYPO3 page translations of all languages in chunks.
     *
     * Only the columns required for the links to the translations are fetched because the translations
     * of all pages are used and the other columns are fetched by translatedPages() for the imported pages.
     *
     * @return iterable<int, \stdClass> Translations with "l10n_parent", "sys_language_uid" and "slug" ordered by UID
     */
    public function translatedSlugs(): iterable
    {
        if( !in_array( 'l10n_parent', $this->listing( 'pages' ), true ) ) {
            return [];
        }

        return $this->translationQuery()
            ->select( $this->columns( 'pages', ['uid', 'l10n_parent', 'sys_language_uid', 'slug'] ) )
            ->lazyById( 1000, 'uid' );
    }


    /**
     * Fetches the TYPO3 page translations of the pages in the languages.
     *
     * @param array<int, int> $uids UIDs of the TYPO3 pages in default language
     * @param array<int, int> $langUids UIDs of the languages of the translations
     * @return Collection<int, \stdClass> Translations ordered by UID within each page
     */
    public function translatedPages( array $uids, array $langUids ): Collection
    {
        if( $langUids === [] || !in_array( 'l10n_parent', $this->listing( 'pages' ), true ) ) {
            return Collection::make();
        }

        return Database::chunked( $uids, fn( $chunk ) => $this->translationQuery()
            ->whereIn( 'l10n_parent', $chunk )
            ->whereIn( 'sys_language_uid', $langUids )
            ->orderBy( 'uid', 'asc' )
            ->get( $this->columns( 'pages', array_values( array_diff( self::PAGE_COLUMNS, self::DEFAULT_COLUMNS ) ) ) )
        );
    }


    /**
     * Fetches active translated tt_content records of the pages in the languages.
     *
     * @param array<int, int> $pids UIDs of the TYPO3 pages
     * @param array<int, int> $langUids UIDs of the languages of the records
     * @return Collection<int, mixed> Records ordered by their sorting within each page
     */
    public function translatedContent( array $pids, array $langUids ): Collection
    {
        if( $langUids === [] ) {
            return Collection::make();
        }

        // the records in default language they translate are required to show them in their place
        return Database::chunked( $pids, fn( $chunk ) => $this->contentQuery()
            ->whereIn( 'pid', $chunk )
            ->whereIn( 'sys_language_uid', $langUids )
            ->orderBy( 'sorting', 'asc' )
            ->orderBy( 'uid', 'asc' )
            ->get( $this->columns( 'tt_content', [...self::CONTENT_COLUMNS, 'l18n_parent'] ) )
        );
    }


    /**
     * Fetches the active translations of the content records in default language in the language.
     *
     * Used for the records shown by content shortcuts, which are translated like the records of the page.
     *
     * @param array<int, int> $uids UIDs of the content records in default language
     * @param int $langUid UID of the language
     * @return Collection<int, mixed> Translated records keyed by the UID of the record in default language
     */
    public function contentTranslations( array $uids, int $langUid ): Collection
    {
        if( !in_array( 'l18n_parent', $this->listing( 'tt_content' ), true ) ) {
            return Collection::make();
        }

        return Database::chunked( $uids, fn( $chunk ) => $this->contentQuery()
            ->whereIn( 'l18n_parent', $chunk )
            ->where( 'sys_language_uid', $langUid )
            ->orderBy( 'uid', 'asc' )
            ->get( $this->columns( 'tt_content', [...self::CONTENT_COLUMNS, 'l18n_parent'] ) )
        )->keyBy( fn( $record ) => (int) $record->l18n_parent );
    }


    /**
     * Returns the last modification times of the pages including their translations, content elements and file references.
     *
     * Deleted and hidden records are included because deleting or hiding them changes the page too. Changes of the
     * referenced files and their metadata count too and the start and end times passed since are modification times
     * because the pages or content elements are shown or hidden from then on.
     *
     * @param array<int, int> $uids UIDs of the TYPO3 pages in default language
     * @return array<int, int> Timestamps by page UID, empty if the pages have no modification times
     */
    public function changed( array $uids ): array
    {
        if( !in_array( 'tstamp', $this->listing( 'pages' ), true ) ) {
            return [];
        }

        $queries = [fn( $chunk ) => $this->db->table( 'pages' )->whereIn( 'uid', $chunk )->get( ['uid', 'tstamp'] )];

        if( in_array( 'l10n_parent', $this->listing( 'pages' ), true ) ) {
            $queries[] = fn( $chunk ) => $this->latest( 'pages', 'l10n_parent', $chunk );
        }

        // the records of the page are stored in the page, the translated content elements too
        foreach( ['tt_content', 'sys_file_reference', 'tx_bootstrappackage_accordion_item', 'tx_bootstrappackage_carousel_item',
            'tx_bootstrappackage_tab_item', 'tx_bootstrappackage_card_group_item', 'tx_bootstrappackage_timeline_item', 'tx_bootstrappackage_icon_group_item'] as $table )
        {
            if( count( array_intersect( ['pid', 'tstamp'], $this->listing( $table ) ) ) === 2 ) {
                $queries[] = fn( $chunk ) => $this->latest( $table, 'pid', $chunk );
            }
        }

        $now = time();

        foreach( ['pages' => 'uid', 'tt_content' => 'pid'] as $table => $column )
        {
            if( in_array( 'starttime', $columns = $this->listing( $table ), true ) ) {
                $queries[] = fn( $chunk ) => $this->latest( $table, $column, $chunk, 'starttime', fn( $query ) => $query->where( 'starttime', '<=', $now ) );
            }

            if( in_array( 'endtime', $columns, true ) ) {
                $queries[] = fn( $chunk ) => $this->latest( $table, $column, $chunk, 'endtime', fn( $query ) => $query->where( 'endtime', '>', 0 )->where( 'endtime', '<=', $now ) );
            }
        }

        // replaced files and changed metadata like alternative texts change the pages using them
        foreach( ['sys_file' => 'uid', 'sys_file_metadata' => 'file'] as $table => $key )
        {
            if( in_array( 'uid_local', $this->listing( 'sys_file_reference' ), true ) && count( array_intersect( [$key, 'tstamp'], $this->listing( $table ) ) ) === 2 )
            {
                $queries[] = fn( $chunk ) => $this->db->table( 'sys_file_reference' )
                    ->join( $table . ' as f', 'f.' . $key, '=', 'sys_file_reference.uid_local' )
                    ->whereIn( 'sys_file_reference.pid', $chunk )
                    ->groupBy( 'sys_file_reference.pid' )
                    ->select( 'sys_file_reference.pid as uid' )
                    ->selectRaw( 'MAX(f.tstamp) AS tstamp' )
                    ->get();
            }
        }

        $changed = [];

        foreach( $queries as $query )
        {
            foreach( Database::chunked( $uids, $query ) as $row ) {
                $changed[(int) $row->uid] = max( $changed[(int) $row->uid] ?? 0, (int) $row->tstamp );
            }
        }

        return $changed;
    }


    /**
     * Returns the storage UIDs used by the sys_file records including the legacy storage 0.
     *
     * The UIDs are fetched only once and taken from the files, so files of unknown storages are found too.
     *
     * @return array<int, int> Storage UIDs
     */
    protected function storages(): array
    {
        return $this->storages ??= $this->db->table( 'sys_file' )->distinct()->orderBy( 'storage' )->pluck( 'storage' )
            ->map( fn( $uid ) => (int) $uid )->values()->all();
    }


    /**
     * Returns the latest modification time of the records grouped by the column.
     *
     * @param string $table TYPO3 table name
     * @param string $column Column with the page UID
     * @param array<int, int> $uids Page UIDs
     * @param 'tstamp'|'starttime'|'endtime' $time Column with the time
     * @param (\Closure(\Illuminate\Database\Query\Builder): mixed)|null $where Adds conditions to the query
     * @return Collection<int, \stdClass> Records with "uid" and "tstamp" properties
     */
    protected function latest( string $table, string $column, array $uids, string $time = 'tstamp', ?\Closure $where = null ): Collection
    {
        $query = $this->db->table( $table )->whereIn( $column, $uids );

        if( $where ) {
            $where( $query );
        }

        return $query->groupBy( $column )
            ->select( $column . ' as uid' )
            ->selectRaw( match( $time ) {
                'starttime' => 'MAX(starttime) AS tstamp',
                'endtime' => 'MAX(endtime) AS tstamp',
                default => 'MAX(tstamp) AS tstamp',
            } )
            ->get();
    }


    /**
     * Returns the columns of the TYPO3 table which are fetched.
     *
     * Only the used columns are fetched because the records can contain large columns, e.g. the serialized
     * copies of the translation sources or the flexform configuration.
     *
     * @param string $table TYPO3 table name
     * @param array<int, string> $only Fetch only these columns if they exist
     * @return array<int, string> Column names
     */
    protected function columns( string $table, array $only ): array
    {
        return array_values( array_intersect( $this->listing( $table ), $only ) );
    }


    /**
     * Returns the column names of the TYPO3 table which are fetched only once.
     *
     * @param string $table TYPO3 table name
     * @return array<int, string> Column names
     */
    protected function listing( string $table ): array
    {
        return $this->listings[$table] ??= $this->db->getSchemaBuilder()->getColumnListing( $table );
    }


    /**
     * Returns the query for the TYPO3 page translations.
     */
    protected function translationQuery(): Builder
    {
        return $this->db->table( 'pages' )
            ->where( 'deleted', 0 )
            ->where( 'sys_language_uid', '>', 0 )
            ->where( 'l10n_parent', '>', 0 )
            ->where( 't3ver_wsid', 0 )
            ->where( 'pid', '>=', 0 );
    }


    /**
     * Returns the query for the active content records.
     */
    protected function contentQuery(): Builder
    {
        return $this->visible( 'tt_content', $this->db->table( 'tt_content' )->where( 'deleted', 0 )->where( 'hidden', 0 )->where( 'pid', '>=', 0 ) );
    }


    /**
     * Returns the query for the active file references.
     */
    protected function referenceQuery(): Builder
    {
        return $this->visible( 'sys_file_reference', $this->db->table( 'sys_file_reference' )->where( 'deleted', 0 )->where( 'hidden', 0 ) );
    }


    /**
     * Restricts the query to the live records which are visible for all visitors now.
     *
     * Records of workspaces aren't published yet. Records restricted to frontend user groups, expired
     * and scheduled records aren't imported because Pagible can't restrict or publish content
     * elements like TYPO3. Records for "hide at login" (-1) are visible for anonymous visitors.
     * Only the columns which exist in the table are checked.
     *
     * @param string $table TYPO3 table name
     * @param Builder $query Query for the table
     * @return Builder Query restricted to the visible records
     */
    protected function visible( string $table, Builder $query ): Builder
    {
        $columns = array_flip( $this->listing( $table ) );
        $now = time();

        if( isset( $columns['t3ver_wsid'] ) ) {
            $query->where( 't3ver_wsid', 0 );
        }

        if( isset( $columns['starttime'] ) ) {
            $query->where( 'starttime', '<=', $now );
        }

        if( isset( $columns['endtime'] ) ) {
            $query->where( fn( $query ) => $query->where( 'endtime', 0 )->orWhere( 'endtime', '>', $now ) );
        }

        if( isset( $columns['fe_group'] ) )
        {
            $query->where( fn( $query ) => $query->whereNull( 'fe_group' )->orWhereIn( 'fe_group', ['', '0', '-1'] )
                ->orWhere( 'fe_group', 'like', '-1,%' )->orWhere( 'fe_group', 'like', '%,-1' )->orWhere( 'fe_group', 'like', '%,-1,%' ) );
        }

        return $query;
    }


    /**
     * Adds the sys_file records of the file references as "sys_file" property.
     *
     * @param Collection<int, mixed> $refs sys_file_reference records
     * @return Collection<int, mixed> File references
     */
    protected function withFiles( Collection $refs ): Collection
    {
        $uids = $refs->map( fn( $ref ) => (int) $ref->uid_local )->unique()->values()->all();
        $files = Database::chunked( $uids, fn( $chunk ) => $this->files()
            ->whereIn( 'uid', $chunk )
            ->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) )
        )->keyBy( fn( $file ) => (int) $file->uid );

        $metadata = Collection::make();

        if( $this->table( 'sys_file_metadata' ) && ( $columns = $this->columns( 'sys_file_metadata', self::METADATA_COLUMNS ) ) )
        {
            // the metadata contains the default texts of the files if the references don't overwrite them
            $langs = $refs->map( fn( $ref ) => max( 0, (int) ( $ref->sys_language_uid ?? 0 ) ) )->push( 0 )->unique()->values()->all();
            $metadata = Database::chunked( $uids, function( $chunk ) use ( $columns, $langs ) {
                $query = $this->db->table( 'sys_file_metadata' )->whereIn( 'file', $chunk );

                // the metadata of the languages of the references is used, the one of the default language otherwise
                if( in_array( 'sys_language_uid', $this->listing( 'sys_file_metadata' ), true ) ) {
                    $query->whereIn( 'sys_language_uid', $langs );
                }

                return $query->get( $columns );
            } )->keyBy( fn( $meta ) => (int) $meta->file . ':' . max( 0, (int) ( $meta->sys_language_uid ?? 0 ) ) );
        }

        return $refs->each( function( $ref ) use ( $files, $metadata ) {
            $ref->sys_file = $files->get( (int) $ref->uid_local );
            $lang = max( 0, (int) ( $ref->sys_language_uid ?? 0 ) );

            if( $meta = $metadata->get( (int) $ref->uid_local . ':' . $lang ) ?? $metadata->get( (int) $ref->uid_local . ':0' ) )
            {
                // like in TYPO3, only NULL values use the metadata, empty strings overwrite them
                foreach( ['title', 'alternative', 'description'] as $name )
                {
                    if( ( $ref->{$name} ?? null ) === null && trim( (string) ( $meta->{$name} ?? '' ) ) !== '' ) {
                        $ref->{$name} = $meta->{$name};
                    }
                }
            }
        } );
    }


    /**
     * Returns a query for the sys_file records whose files aren't flagged as missing by TYPO3.
     */
    protected function files(): Builder
    {
        $query = $this->db->table( 'sys_file' );
        return in_array( 'missing', $this->listing( 'sys_file' ), true ) ? $query->where( 'missing', 0 ) : $query;
    }


    /**
     * Returns a query for the TYPO3 table or NULL if the table doesn't exist.
     */
    public function table( string $name ): ?Builder
    {
        return $this->db->getSchemaBuilder()->hasTable( $name ) ? $this->db->table( $name ) : null;
    }
}
