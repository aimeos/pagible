<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;


/**
 * Reads the pages, content elements, files and configuration of the TYPO3 database.
 */
class Typo3Reader
{
    /** Columns of the page records which are used */
    protected const PAGE_COLUMNS = ['uid', 'pid', 'title', 'nav_title', 'seo_title', 'slug', 'doktype', 'is_siteroot', 'hidden', 'nav_hide',
        'crdate', 'content_from_pid', 'shortcut', 'shortcut_mode', 'url', 'backend_layout', 'backend_layout_next_level', 'TSconfig',
        'sorting', 'sys_language_uid', 'l10n_parent', 'fe_group', 'starttime', 'endtime', 'extendToSubpages'];

    /** Columns of the pages which are only used in the default language and not fetched for the translations */
    protected const DEFAULT_COLUMNS = ['crdate', 'content_from_pid', 'backend_layout', 'backend_layout_next_level', 'TSconfig'];

    /** Columns of the content records which are used */
    protected const CONTENT_COLUMNS = ['uid', 'pid', 'CType', 'header', 'header_layout', 'bodytext', 'imageorient', 'records',
        'colPos', 'sorting', 'sys_language_uid'];

    /** Columns of the file references which are used */
    protected const REFERENCE_COLUMNS = ['uid', 'uid_local', 'uid_foreign', 'tablenames', 'title', 'alternative'];

    /** Columns of the file records which are used */
    protected const FILE_COLUMNS = ['uid', 'identifier', 'name', 'extension', 'mime_type'];

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
     * Fetches non-deleted TYPO3 pages in default language.
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
            ->whereIn( 'doktype', [1, 3, 4] )
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
     * Fetches visible Bootstrap Package accordion or carousel items of the content elements grouped by content UID.
     *
     * @param string $table Name of the accordion or carousel item table
     * @param array<int, int> $uids UIDs of the content elements
     * @return Collection<int|string, mixed>
     */
    public function items( string $table, array $uids ): Collection
    {
        if( !( $columns = $this->columns( $table, ['uid', 'tt_content', 'header', 'bodytext'] ) ) ) {
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
            ->whereIn( 'fieldname', ['image', 'media', 'assets'] )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches the background-image references of the carousel items grouped by carousel item UID.
     *
     * The sys_file record of each reference is added as "sys_file" property.
     *
     * @param array<int, int> $uids UIDs of the carousel items
     * @return Collection<int|string, mixed>
     */
    public function carouselFileReferences( array $uids ): Collection
    {
        return $this->withFiles( Database::chunked( $uids, fn( $chunk ) => $this->referenceQuery()
            ->where( 'tablenames', 'tx_bootstrappackage_carousel_item' )
            ->where( 'fieldname', 'background_image' )
            ->whereIn( 'uid_foreign', $chunk )
            ->orderBy( 'sorting_foreign', 'asc' )
            ->get( $this->columns( 'sys_file_reference', self::REFERENCE_COLUMNS ) )
        ) )->groupBy( 'uid_foreign' );
    }


    /**
     * Fetches the sys_file records with the identifiers keyed by identifier with leading slash.
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

        $query = $this->db->table( 'sys_file' );

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

        return $query->orderBy( 'uid' )
            ->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) )
            ->keyBy( fn( $file ) => '/' . ltrim( (string) $file->identifier, '/' ) )
            ->only( $identifiers );
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

        return Database::chunked( $pids, fn( $chunk ) => $this->contentQuery()
            ->whereIn( 'pid', $chunk )
            ->whereIn( 'sys_language_uid', $langUids )
            ->orderBy( 'sorting', 'asc' )
            ->orderBy( 'uid', 'asc' )
            ->get( $this->columns( 'tt_content', self::CONTENT_COLUMNS ) )
        );
    }


    /**
     * Returns the last modification times of the pages including their translations, content elements and file references.
     *
     * Deleted and hidden records are included because deleting or hiding them changes the page too.
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
        foreach( ['tt_content', 'sys_file_reference', 'tx_bootstrappackage_accordion_item', 'tx_bootstrappackage_carousel_item'] as $table )
        {
            if( count( array_intersect( ['pid', 'tstamp'], $this->listing( $table ) ) ) === 2 ) {
                $queries[] = fn( $chunk ) => $this->latest( $table, 'pid', $chunk );
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
     * @return Collection<int, \stdClass> Records with "uid" and "tstamp" properties
     */
    protected function latest( string $table, string $column, array $uids ): Collection
    {
        return $this->db->table( $table )
            ->whereIn( $column, $uids )
            ->groupBy( $column )
            ->select( $column . ' as uid' )
            ->selectRaw( 'MAX(tstamp) AS tstamp' )
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
            ->where( 't3ver_wsid', 0 );
    }


    /**
     * Returns the query for the active content records.
     */
    protected function contentQuery(): Builder
    {
        return $this->visible( 'tt_content', $this->db->table( 'tt_content' )->where( 'deleted', 0 )->where( 'hidden', 0 ) );
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
        $files = Database::chunked( $uids, fn( $chunk ) => $this->db->table( 'sys_file' )
            ->whereIn( 'uid', $chunk )
            ->get( $this->columns( 'sys_file', self::FILE_COLUMNS ) )
        )->keyBy( fn( $file ) => (int) $file->uid );

        return $refs->each( fn( $ref ) => $ref->sys_file = $files->get( (int) $ref->uid_local ) );
    }


    /**
     * Returns a query for the TYPO3 table or NULL if the table doesn't exist.
     */
    public function table( string $name ): ?Builder
    {
        return $this->db->getSchemaBuilder()->hasTable( $name ) ? $this->db->table( $name ) : null;
    }
}
