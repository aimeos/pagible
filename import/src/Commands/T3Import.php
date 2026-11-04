<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Import\Typo3Destinations;
use Aimeos\Cms\Import\Typo3Files;
use Aimeos\Cms\Import\Typo3Html;
use Aimeos\Cms\Import\Typo3Languages;
use Aimeos\Cms\Import\Typo3Layouts;
use Aimeos\Cms\Import\Typo3Reader;
use Aimeos\Cms\Import\Typo3Records;
use Aimeos\Cms\Import\Typo3Site;
use Aimeos\Cms\Import\Typo3Tree;
use Aimeos\Cms\Import\Typo3Urls;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB as CmsDB;
use Illuminate\Support\Facades\DB;


class T3Import extends BaseImport
{
    /**
     * Command name
     */
    protected $signature = 'cms:t3-import
        {--connection=typo3 : Database connection name for the TYPO3 database}
        {--domain=* : Override the detected domain, optionally for a root page UID (e.g. --domain=example.com or --domain=example.com:1 --domain=example.de:2)}
        {--lang=en : Language code for the imported pages}
        {--language=* : TYPO3 language UID imported as page translation with its code and optional URL of a separate domain (e.g. --language=1:de or --language=2:fr:https://example.fr/)}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=t3-import : Editor name for imported records}
        {--theme= : Pagible theme name applied to imported pages}
        {--file-base= : Base URL for TYPO3 files (e.g. https://example.com/fileadmin)}
        {--page=* : Import or update only the TYPO3 page with this UID (repeatable)}
        {--since= : Only import the pages changed since the date (e.g. 2026-01-31) including their translations and content, the pages of the others are kept as imported before}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports TYPO3 pages into Pagible CMS pages';

    /** Maximum number of existing pages of the next sibling pages kept while the descendants of a page are imported */
    protected const SIBLING_SIZE = 50;

    /** Number of the next pages in import order whose content records, file references and files are fetched at once */
    protected const CONTENT_SIZE = 100;

    protected bool $keepPages = false;
    protected string $theme = '';

    /** @var array<string, array<string, bool>> URL paths of the next sibling pages without existing page by domain, TRUE if a page or translation has been saved with the path since */
    protected array $watched = [];

    /** @var list<object> TYPO3 pages in import order, so their content and modification times are fetched in chunks */
    protected array $sequence = [];

    /** @var array<int, int> Positions of the TYPO3 pages in $sequence by UID */
    protected array $positions = [];

    /** @var array{0: int, 1: int} First and next position in $sequence of the pages whose content was prefetched last */
    protected array $window = [0, 0];

    /** @var Collection<string, string> URLs of the created files by source path */
    protected Collection $createdFileUrls;

    /** Maps the TYPO3 language UIDs to the languages of the pages */
    protected Typo3Languages $languages;

    /** @var array<int, int> UIDs of the TYPO3 languages whose translations are imported */
    protected array $langUids = [];

    /** Maps the TYPO3 page trees to their domains */
    protected Typo3Site $site;

    /** @var array<int, array<int, string>> URL paths of the TYPO3 page translations in the known languages by page UID and language UID */
    protected array $translated = [];

    /** @var array<int, Collection<int, mixed>> TYPO3 page translations of the upcoming pages by page UID */
    protected array $translations = [];

    protected ?Typo3 $typo3 = null;

    /** Reads the TYPO3 database */
    protected Typo3Reader $reader;

    /** Imports the TYPO3 file references and fileadmin URLs */
    protected Typo3Files $files;

    /** Selects the content records of the TYPO3 pages */
    protected Typo3Records $records;

    /** Resolves the URLs of the TYPO3 pages */
    protected Typo3Urls $urls;

    /** Navigates the TYPO3 page tree */
    protected Typo3Tree $tree;

    /** @var array<string, array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}> References to the shared elements by TYPO3 content and language */
    protected array $shared = [];


    /**
     * Execute command
     */
    public function handle(): void
    {
        $this->theme = $this->stringOption( 'theme' );
        $this->createdFileUrls = Collection::make();

        if( !$this->init( 'TYPO3', 'T3' ) || !$this->sinceOption() ) {
            return;
        }

        $this->reader = new Typo3Reader( DB::connection( $this->connection ) );

        $this->site = new Typo3Site( (array) $this->option( 'domain' ), fn() => $this->reader->domains() );
        $this->fileBase = $this->site->fileBase( $this->fileBase );

        if( ( $pages = $this->reader->pages() )->isEmpty() )
        {
            $this->warn( 'No TYPO3 pages found.' );
            return;
        }

        $this->languages = new Typo3Languages( $this->reader->languages(), (array) $this->option( 'language' ) );
        $translations = $this->loadTranslations();

        $pagesById = $pages->keyBy( fn( $page ) => (int) $page->uid );
        $this->tree = new Typo3Tree( $pagesById );

        $pageIds = Typo3Tree::uids( (array) $this->option( 'page' ) );
        $selectedPages = $pageIds === null ? $pages : $this->tree->select( $pageIds );

        $this->info( $pageIds === null
            ? "Found {$pages->count()} TYPO3 pages."
            : "Selected {$selectedPages->count()} of {$pages->count()} TYPO3 pages."
        );

        if( $translations > 0 ) {
            $this->info( "Found {$translations} TYPO3 page translations." );
        }

        if( $this->option( 'dry-run' ) )
        {
            $pageIds === null ? $this->printDryRun( $pages ) : $this->printSelectedDryRun( $selectedPages );
            return;
        }

        $this->prepare( $pagesById );

        try {
            $pageIds === null ? $this->importPages( $pages ) : $this->importSelectedPages( $selectedPages );
        } finally {
            $this->forgetDownloads();
        }
    }


    /**
     * Collects the URL paths of the page translations in the known languages.
     *
     * Only the URL paths of the translations are kept for the links to them, the other
     * columns are fetched for the upcoming pages when they are imported.
     *
     * @return int Number of translations in the known languages
     */
    protected function loadTranslations(): int
    {
        ['slugs' => $this->translated, 'count' => $count, 'unknown' => $unknown] = $this->languages->translations( $this->reader->translatedSlugs() );
        $this->translations = [];

        if( $unknown !== [] ) {
            $this->warn( 'Skipping translations of unknown TYPO3 languages [' . implode( ', ', $unknown ) . '], add --language=<uid>:<code> to import them.' );
        }

        return $count;
    }


    /**
     * Creates the objects which import the files, URLs and content of the pages.
     *
     * @param Collection<int, mixed> $pagesById TYPO3 pages by UID
     */
    protected function prepare( Collection $pagesById ): void
    {
        $this->shared = $this->failedFiles = [];
        $this->langUids = $this->languages->imported( $this->lang );
        $this->files = new Typo3Files( $this->reader->filesByIdentifier( ... ), $this->fileBase, $this->importFile( ... ),
            fn( string $path ) => $this->createdFileUrls->get( $path ), $this->knownFile( ... ) );
        $this->urls = new Typo3Urls( $pagesById, $this->translated );
        $this->typo3 = new Typo3(
            fn( object $ref ) => $this->attempt( $this->refLabel( $ref ), fn() => $this->files->reference( $ref ) ),
            $this->rewriter(),
            $this->reader->fileReferences( ... ),
            fn( array $uids ) => $this->reader->items( 'tx_bootstrappackage_accordion_item', $uids ),
            fn( array $uids ) => $this->reader->items( 'tx_bootstrappackage_carousel_item', $uids ),
            $this->reader->carouselFileReferences( ... )
        );
        $this->records = new Typo3Records( $pagesById, $this->reader->content( ... ), fn( array $pids ) => $this->reader->translatedContent( $pids, $this->langUids ),
            $this->reader->contentRecords( ... ), $this->reader->shortcuts(), new Typo3Layouts( $pagesById, $this->reader->backendLayouts() ) );
    }


    /**
     * Keeps only the last references to the shared elements before the batch is committed.
     *
     * The shared elements of other TYPO3 content are found by their name again when they are used.
     */
    #[\Override]
    protected function committing(): void
    {
        parent::committing();
        $this->shared = Database::limit( $this->shared, static::PREFETCH_SIZE );
    }


    /**
     * Keeps only the URLs of the created and reused files which are still known.
     */
    #[\Override]
    protected function forgetFiles(): void
    {
        parent::forgetFiles();

        if( $this->createdFileUrls->count() > $this->createdFiles->count() + count( $this->reusedFiles ) ) {
            $this->createdFileUrls = $this->createdFileUrls->only( [...$this->createdFiles->keys()->all(), ...array_keys( $this->reusedFiles )] );
        }
    }


    /**
     * Adds the file of a previous import so it's used instead of creating it again.
     *
     * The URLs of the stored files are used in the HTML of the content elements.
     *
     * @param string $path Source URL or path of the file
     */
    protected function reuseFile( string $path, File $file ): void
    {
        parent::reuseFile( $path, $file );
        $this->createdFileUrls->put( $path, Typo3Files::stored( $file ) );
    }


    /**
     * Executes the callback and skips it on failure.
     *
     * The callback stores its records in nested transactions itself, so a failure doesn't leave partial
     * records behind and no savepoint is required for every content element, file reference and URL.
     *
     * @template T
     * @param string $label Label of the skipped record in the warning
     * @param \Closure(): T $fn Callback
     * @return T|null Result of the callback or NULL on failure
     */
    protected function attempt( string $label, \Closure $fn ): mixed
    {
        try {
            return $fn();
        } catch( \Exception $e ) {
            // the import of the record and its batch can't continue
            if( $this->causedByConcurrencyError( $e ) ) {
                throw $e;
            }

            $this->warn( "  Skipped {$label}: " . $e->getMessage() );
        }

        return null;
    }


    /**
     * Executes the callback within a transaction and forgets the shared elements stored by it on failure.
     *
     * @template T
     * @param \Closure(): T $fn Callback
     * @param \Closure(\Exception): void $failed Error handler
     * @return T|null Result of the callback or NULL on failure
     */
    #[\Override]
    protected function guard( \Closure $fn, \Closure $failed ): mixed
    {
        // shared elements are only added during the callback, so the ones stored by it are at the end
        $count = count( $this->shared );

        return parent::guard( $fn, function( \Exception $e ) use ( $failed, $count ) {
            self::truncate( $this->shared, $count );
            $failed( $e );
        } );
    }


    /**
     * Returns the label of the file reference in the warnings.
     *
     * @param object $ref sys_file_reference record
     */
    protected function refLabel( object $ref ): string
    {
        $data = get_object_vars( $ref );
        $label = fn( string $key ) => is_scalar( $data[$key] ?? null ) ? (string) $data[$key] : '?';

        return "file reference [{$label( 'uid' )}] of {$label( 'tablenames' )} [{$label( 'uid_foreign' )}]";
    }


    /**
     * Builds content elements array from TYPO3 tt_content records.
     *
     * @param Collection<int, mixed> $records
     * @param Typo3|null $typo3 Converter of the content elements
     * @param string $lang Language code of the content elements
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}
     */
    protected function buildContent( Collection $records, ?Typo3 $typo3, string $lang ): array
    {
        $elements = $fileIds = $elementIds = [];

        // the file references of all content elements of the page and the files of previous imports are fetched at once
        if( $typo3 )
        {
            $typo3->prefetch( $records );
            $this->prefetchFiles( $this->files->paths( $typo3->references(), $typo3->texts( $records ) ), false );
        }

        foreach( $records as $record )
        {
            $result = $this->attempt( 'content element [' . ( $record->uid ?? '?' ) . ']', fn() => $this->convert( $record, $typo3, $lang ) );

            if( $result )
            {
                $elements = array_merge( $elements, $result['elements'] );
                $fileIds = array_merge( $fileIds, $result['fileIds'] );
                $elementIds = array_merge( $elementIds, $result['elementIds'] );
            }
        }

        return [
            'elements' => $elements,
            'fileIds' => array_values( array_unique( $fileIds ) ),
            'elementIds' => array_values( array_unique( $elementIds ) ),
        ];
    }


    /**
     * Returns the rewriter for the files and links in the HTML of the content elements.
     *
     * @param array{uid: int, source: string}|null $translation TYPO3 language of translated content or NULL for the source content
     */
    protected function rewriter( ?array $translation = null ): Typo3Html
    {
        return new Typo3Html(
            fn( string $url ) => $this->attempt( "file {$url}", fn() => $this->files->html( $url ) ),
            fn( int $uid, string $fragment ) => $this->urls->page( $uid, $fragment, $translation )
        );
    }


    /**
     * Converts the tt_content record and stores its elements as shared elements if it's referenced by other pages.
     *
     * @param Typo3|null $typo3 Converter of the content element
     * @param string $lang Language code of the content element
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>}|null
     */
    protected function convert( object $record, ?Typo3 $typo3, string $lang ): ?array
    {
        // content shown on several pages is converted and stored only once per language
        $key = isset( $record->_pagible_shared ) ? $this->sharedElementName( $record, 0 ) . '/' . $lang : null;

        if( $key && isset( $this->shared[$key] ) ) {
            return $this->shared[$key];
        }

        if( !$typo3 || !( $result = $typo3->convert( $record ) ) ) {
            return null;
        }

        if( !$key ) {
            return ['elements' => $result['elements'], 'fileIds' => $result['fileIds'] ?? [], 'elementIds' => []];
        }

        // the shared elements of the record are stored together or not at all
        $elementIds = CmsDB::connection()->transaction( fn() => array_map(
            fn( $position, $element ) => Elements::shared( $this->sharedElementName( $record, $position ), $lang, $element, $result['fileIds'] ?? [], $this->editor ),
            array_keys( $result['elements'] ), $result['elements']
        ) );
        $references = array_map( fn( $id ) => ['type' => 'reference', 'refid' => $id, 'group' => 'main'], $elementIds );

        return $this->shared[$key] = ['elements' => $references, 'fileIds' => [], 'elementIds' => $elementIds];
    }


    /**
     * Returns the persistent, source-specific name used to find shared elements on re-import.
     */
    protected function sharedElementName( object $record, int $position ): string
    {
        return sprintf(
            'TYPO3 element %s/%d/%d/%d',
            substr( sha1( $this->connection ), 0, 12 ),
            (int) ( $record->_pagible_shared_page ?? $record->pid ?? 0 ),
            (int) ( $record->_pagible_shared_uid ?? $record->uid ?? 0 ),
            $position
        );
    }


    /**
     * Returns the redirect target of the TYPO3 external URL or shortcut page and warns about invalid external URLs.
     */
    protected function redirect( object $t3Page ): string
    {
        $to = $this->urls->redirect( $t3Page );

        if( $to === '' && (int) ( $t3Page->doktype ?? 0 ) === 3 && trim( (string) ( $t3Page->url ?? '' ) ) !== '' ) {
            $this->warn( '  Skipped invalid external URL of [' . ( $t3Page->uid ?? '' ) . '] ' . ( $t3Page->title ?? '' ) . ', only http(s) URLs and paths are allowed' );
        }

        return $to;
    }


    /**
     * Builds the page data array.
     *
     * @return array<string, mixed>
     */
    protected function buildPageData( object $t3Page, string $slug, string $domain, string $to = '' ): array
    {
        // Pagible can't restrict pages to TYPO3 frontend users or publish them later
        if( ( $reason = $this->tree->restriction( $t3Page ) ) !== null && empty( $t3Page->hidden ) ) {
            $this->warn( '  Disabled [' . ( $t3Page->uid ?? '' ) . '] ' . ( $t3Page->title ?? '' ) . " because {$reason}" );
        }

        return [
            'name' => ( $t3Page->nav_title ?? null ) ?: ( $t3Page->title ?? null ),
            'title' => ( $t3Page->seo_title ?? null ) ?: ( $t3Page->title ?? null ),
            'path' => $slug,
            'tag' => !empty( $t3Page->is_siteroot ) ? 'root' : 'page',
            'domain' => $domain,
            'lang' => $this->lang,
            'to' => $to,
            'theme' => $this->theme,
            'status' => !empty( $t3Page->hidden ) || $reason !== null ? 0 : ( !empty( $t3Page->nav_hide ) ? 2 : 1 ),
            'editor' => $this->editor,
        ];
    }


    /**
     * Creates a File record with a published version or returns the one created before by this import.
     *
     * Unlike createFile(), existing files aren't looked up by path because downloaded files get a new one.
     *
     * @param bool $unknown TRUE if knownFile() has just returned no file for the path, so it isn't looked up again
     */
    protected function importFile( string $mime, string $name, string $path, bool $unknown = false ): string
    {
        if( !$unknown && ( $existing = $this->knownFile( $path ) ) ) {
            return $existing;
        }

        // files used several times are only downloaded once if the download fails
        $download = str_starts_with( $path, 'http' );
        $file = $this->downloadOnce( $path, $download, fn() => CmsDB::connection()->transaction(
            fn() => Files::create( $mime, $name, $path, $this->editor, $download, $download ? $this->downloaded( $path ) : null )
        ) );

        $id = $file->id ?? '';

        // URLs of rolled back files are overwritten when the file is created again
        $this->createdFiles->put( $path, $id );
        $this->createdFileUrls->put( $path, Typo3Files::stored( $file ) );

        return $id;
    }


    /**
     * Gets, updates or creates the root page for a domain.
     *
     * Root pages which weren't imported or were changed since are used as they are.
     */
    protected function getRootPage( object $t3Root, string $domain ): PageView
    {
        $page = Pages::find( ['tag' => 'root', 'domain' => $domain] );

        if( $page && !$this->isImported( $page ) )
        {
            $this->info( "Using existing root page: {$page->name} ({$domain})" );
        }
        else
        {
            $slug = Typo3Urls::slug( $t3Root->slug ?? null );
            ['page' => $page, 'status' => $status] = $this->save( $t3Root, ['tag' => 'root'] + $this->buildPageData( $t3Root, $slug, $domain ), $page );
            $this->info( ( $status === Status::Imported ? 'Created' : $status->label() ) . ' root page: ' . ( $t3Root->title ?? '' ) . " ({$domain})" );
        }

        $this->usedPages[$page->id ?? ''] = true;
        return $page;
    }


    /**
     * Imports only explicitly selected pages, updating matching destination
     * routes with a new published version and creating missing pages when their
     * destination parent already exists.
     *
     * @param Collection<int, mixed> $selectedPages
     */
    protected function importSelectedPages( Collection $selectedPages ): void
    {
        $processed = Collection::make();

        // parents are imported before their children and new pages are added at the end of the page tree
        $selectedPages = $selectedPages->sortBy( fn( $page ) => $this->tree->order( $page ), SORT_STRING )->values();

        // the existing pages of the parents which aren't selected are fetched together with the ones of the selected pages
        $selected = $selectedPages->keyBy( fn( $page ) => (int) $page->uid );
        $parents = $selectedPages->map( fn( $page ) => $this->tree->parent( $page ) )->filter( fn( $page ) => $page
            && !$selected->has( (int) $page->uid ) && (int) ( $page->pid ?? 0 ) !== 0 && empty( $page->is_siteroot )
        )->keyBy( fn( $page ) => (int) $page->uid )->values();

        $destinations = new Typo3Destinations( $selectedPages->concat( $parents ), fn( $page ) => $this->site->domain( $this->tree->root( $page ) ) );
        $this->sequence( $selectedPages->all() );

        foreach( $selectedPages as $t3Page )
        {
            // trashed pages are only restored within the transaction of the page, so they stay trashed if its import fails
            $this->preload( $t3Page, $destinations->peek( $t3Page, $this->site->domain( $this->tree->root( $t3Page ) ) ) );
            $result = $this->transaction( "[{$t3Page->uid}] {$t3Page->title}",
                fn() => $this->importSelectedPage( $t3Page, $processed, $destinations )
            );

            if( !$result )
            {
                // the page may have been changed by the failed import
                $destinations->forget( $t3Page );

                continue;
            }

            $this->report( $t3Page, $result['page'], $result['path'], $result['domain'], $result['status'] );

            // the imported pages are only used as parents or to detect duplicate URLs, so only their IDs and URLs are kept
            $slim = $this->slim( $result['page'] );
            $processed->put( (int) $t3Page->uid, $slim );
            $destinations->put( $result['domain'], $result['path'], $this->slimPage( $slim ) );
            unset( $result );
        }

        $this->totals( $selectedPages->count(), 'pages' );
    }


    /**
     * Imports or updates one selected TYPO3 page.
     *
     * @param Collection<int|string, string> $processed Imported pages packed by slim() by TYPO3 page UID
     * @param Typo3Destinations $destinations Existing pages of the selected TYPO3 pages
     * @return array{page: PageView, status: Status, path: string, domain: string}
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function importSelectedPage( object $t3Page, Collection $processed, Typo3Destinations $destinations ): array
    {
        $domain = $this->site->domain( $this->tree->root( $t3Page ) );
        $path = Typo3Urls::slug( $t3Page->slug ?? null );
        $pageData = $this->buildPageData( $t3Page, $path, $domain, $this->redirect( $t3Page ) );
        $isRoot = (int) ( $t3Page->pid ?? 0 ) === 0 || !empty( $t3Page->is_siteroot );
        $pageData['tag'] = $isRoot ? 'root' : $pageData['tag'];
        $page = $destinations->find( $t3Page, $domain );
        $parent = null;

        // pages with the same URL must not overwrite each other
        if( $page && isset( $this->usedPages[$page->id ?? ''] ) ) {
            throw new \RuntimeException( "URL /{$path} is used by another page of this import" );
        }

        if( $page && !$this->isImported( $page ) ) {
            throw new \RuntimeException( "URL /{$path} is used by a page which wasn't imported or was changed since" );
        }

        if( !$page && !$isRoot )
        {
            if( !( $parentRecord = $this->tree->parent( $t3Page ) ) ) {
                throw new \RuntimeException( 'TYPO3 parent page is missing from the source tree.' );
            }

            $packed = $processed->get( (int) $parentRecord->uid );
            $parent = $packed ? $this->slimPage( $packed ) : $destinations->find( $parentRecord, $domain );

            if( !$parent ) {
                throw new \RuntimeException( sprintf( 'Destination parent page [%d] %s has not been imported.', (int) $parentRecord->uid, (string) $parentRecord->title ) );
            }
        }

        $saved = $this->save( $t3Page, $pageData, $page, $parent );
        $this->usedPages[$saved['page']->id ?? ''] = true;

        return $saved + ['path' => $path, 'domain' => $domain];
    }


    /**
     * Creates or updates a page from a TYPO3 page record and publishes its content.
     *
     * Pages of previous imports get no new version if nothing changed.
     *
     * @param array<string, mixed> $pageData
     * @return array{page: PageView, status: Status} Saved page and its status
     */
    protected function save( object $t3Page, array $pageData, ?PageView $page = null, ?PageView $parent = null ): array
    {
        // pages not changed since the given date are kept as imported before including their translations
        if( $page && $this->unchangedPage( $t3Page ) ) {
            return ['page' => $page, 'status' => Status::Unchanged];
        }

        $this->prefetchContent( $t3Page );
        $content = $this->buildContent( $this->records->page( $t3Page ), $this->typo3, $this->lang );
        $created = $parent && (int) ( $t3Page->crdate ?? 0 ) > 0 ? date( 'Y-m-d H:i:s', (int) ( $t3Page->crdate ?? 0 ) ) : null;

        if( $page ) {
            $status = $this->updateVersion( $page, $pageData, $content );
        } else {
            $page = $this->createPage( $pageData, $content, $parent, $created );
            $status = Status::Imported;
        }

        $this->saveTranslations( $t3Page, $page, $pageData, $status === Status::Imported );

        return ['page' => $page, 'status' => $status];
    }


    /**
     * Tests if the TYPO3 page including its translations and content wasn't changed since the date of the --since option.
     */
    protected function unchangedPage( object $t3Page ): bool
    {
        $uid = (int) ( $t3Page->uid ?? 0 );

        // the modification times of the next pages in import order are fetched at once
        if( $this->since !== null && !array_key_exists( $uid, $this->changed ) ) {
            $this->fetchChanged( isset( $this->positions[$uid] ) ? array_slice( $this->sequence, $this->positions[$uid], CmsDB::IN_SIZE ) : [$t3Page] );
        }

        return $this->unchangedSince( $uid );
    }


    /**
     * Remembers the order the TYPO3 pages are imported in, so their content and the modification times for --since are fetched in chunks.
     *
     * @param array<int, object> $t3Pages TYPO3 pages in import order
     */
    protected function sequence( array $t3Pages ): void
    {
        $this->sequence = array_values( $t3Pages );
        $this->positions = array_flip( array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $this->sequence ) );
        $this->window = [0, 0];
    }


    /**
     * Fetches the last modification times of the TYPO3 pages including the pages their content is taken from.
     *
     * The content can be taken from another page or from the pages of the content records shown by
     * the content shortcuts, e.g. folders which aren't imported themselves.
     *
     * @param array<int, object> $t3Pages TYPO3 pages in default language
     */
    protected function fetchChanged( array $t3Pages ): void
    {
        $sources = array_map( fn( $page ) => (int) ( $page->content_from_pid ?? 0 ), $t3Pages );
        $uids = array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $t3Pages );
        $targets = $this->records->shortcutTargets( array_merge( $uids, array_filter( $sources ) ) );
        $pids = $targets ? $this->reader->contentPids( array_merge( ...array_values( $targets ) ) ) : [];
        $times = $this->reader->changed( array_merge( $uids, array_filter( $sources ), array_values( $pids ) ) );

        if( !$times && !$this->changed ) {
            $this->warn( 'No modification dates of the TYPO3 pages found, all pages are imported.' );
        }

        foreach( $uids as $idx => $uid )
        {
            // pages without modification time are changed
            $time = $times[$uid] ?? PHP_INT_MAX;
            $time = $sources[$idx] ? max( $time, $times[$sources[$idx]] ?? PHP_INT_MAX ) : $time;

            // content records which don't exist anymore don't change the page
            foreach( [...$targets[$uid] ?? [], ...$targets[$sources[$idx]] ?? []] as $content ) {
                $time = isset( $pids[$content] ) ? max( $time, $times[$pids[$content]] ?? PHP_INT_MAX ) : $time;
            }

            $this->changed[$uid] = $time;
        }

        $this->changed = Database::limit( $this->changed, 10000, count( $t3Pages ) );
    }


    /**
     * Counts the page and reports the result of its import including the domain.
     */
    protected function report( object $t3Page, PageView $page, string $path, string $domain, Status $status ): void
    {
        $note = " [{$domain}]" . ( $status === Status::Reused ? " (URL is used by a page which wasn't imported, was changed since or was saved by this import)" : '' );
        $this->imported( (int) ( $t3Page->uid ?? 0 ), $page, (string) ( $t3Page->title ?? '' ), $path, $status, $note );
    }


    /**
     * Creates or updates the variants of the page from its TYPO3 translations.
     *
     * @param array<string, mixed> $pageData Data of the source variant
     * @param bool $new TRUE if the page has just been created and has no variants yet
     */
    protected function saveTranslations( object $t3Page, PageView $page, array $pageData, bool $new = false ): void
    {
        $sourceDomain = (string) ( $pageData['domain'] ?? '' );
        $records = $this->translationRecords( $t3Page );
        // the variants of all languages including trashed ones are fetched at once
        $variants = $records->isNotEmpty() && !$new ? $this->variants( $page ) : [];
        $translations = [];

        foreach( $records->unique( 'sys_language_uid' ) as $record )
        {
            $langUid = (int) ( $record->sys_language_uid ?? 0 );
            $language = $this->languages->get( $langUid );

            if( !$language || $language['lang'] === $this->lang ) {
                continue;
            }

            if( $this->skipsTranslation( $variants[$language['lang']] ?? null, (string) ( $record->title ?? '' ), $language['lang'] ) ) {
                continue;
            }

            $translations[] = [$record, $langUid, $language, $this->records->page( $t3Page, $langUid )];
        }

        // the file references and files of all languages are fetched at once before the converters are copied
        if( $this->typo3 && $translations )
        {
            $all = Collection::make( array_column( $translations, 3 ) )->flatten( 1 );
            $this->typo3->prefetch( $all );
            $this->prefetchFiles( $this->files->paths( $this->typo3->references(), $this->typo3->texts( $all ) ), false );
        }

        foreach( $translations as [$record, $langUid, $language, $contentRecords] )
        {
            // links in the content point to the translated pages
            $typo3 = $this->typo3?->withRewriter( $this->rewriter( [
                'uid' => $langUid,
                'source' => $language['domain'] !== '' && $language['domain'] !== $sourceDomain ? $sourceDomain : '',
            ] ) );

            $content = $this->buildContent( $contentRecords, $typo3, $language['lang'] );
            $data = array_replace( $this->buildPageData( $record, '', '', (string) ( $pageData['to'] ?? '' ) ), [
                'tag' => $pageData['tag'] ?? 'page',
            ] );

            $this->saveVariant( $page, $language, Typo3Urls::slug( $record->slug ?? '' ), $data, $content, $variants[$language['lang']] ?? null );
        }
    }


    /**
     * Returns the translations of the TYPO3 page, fetching them if they aren't prefetched with the upcoming pages.
     *
     * @return Collection<int, mixed> TYPO3 page translations ordered by UID
     */
    protected function translationRecords( object $t3Page ): Collection
    {
        if( !isset( $this->translated[$uid = (int) ( $t3Page->uid ?? 0 )] ) ) {
            return Collection::make();
        }

        return $this->translations[$uid] ??= $this->reader->translatedPages( [$uid], $this->langUids );
    }


    /**
     * Fetches the content and downloads the files of the page and its translations before it's imported, so the batch transaction isn't kept open during the downloads.
     *
     * Pages which aren't imported again, e.g. unchanged ones or the ones only used as parent, are skipped.
     *
     * @param PageView|null $page Existing page with the URL of the TYPO3 page
     */
    protected function preload( object $t3Page, ?PageView $page ): void
    {
        if( !( $typo3 = $this->typo3 ) || $page && ( isset( $this->usedPages[$page->id ?? ''] ) || !$this->isImported( $page ) || $this->unchangedPage( $t3Page ) ) ) {
            return;
        }

        $this->prefetchContent( $t3Page );

        try
        {
            // the content records and file references are used again when the page and its translations are imported
            $records = $this->records->page( $t3Page );

            foreach( $this->translationRecords( $t3Page )->unique( 'sys_language_uid' ) as $record )
            {
                if( ( $language = $this->languages->get( $langUid = (int) ( $record->sys_language_uid ?? 0 ) ) ) && $language['lang'] !== $this->lang ) {
                    $records = $records->concat( $this->records->page( $t3Page, $langUid ) );
                }
            }

            $typo3->prefetch( $records );
            $paths = $this->files->paths( $typo3->references(), $typo3->texts( $records ) );
        }
        catch( \Exception )
        {
            // errors are reported when the page is imported
            return;
        }

        $this->prefetchFiles( $paths, false );

        // all files of the page are downloaded because they would be downloaded within the transaction of the page otherwise
        $this->download( [], array_filter( $paths, fn( $path ) => str_starts_with( $path, 'http' ) ) );
    }


    /**
     * Fetches the content records, file references and files of the next pages in import order at once if the ones of the page aren't fetched yet.
     *
     * The content of unchanged pages isn't fetched for --since and the content of the pages
     * fetched before is replaced, so only the content of a limited number of pages is in memory.
     */
    protected function prefetchContent( object $t3Page ): void
    {
        if( !$this->typo3 ) {
            return;
        }

        try
        {
            if( $this->records->prefetched( $t3Page ) ) {
                return;
            }

            $uid = (int) ( $t3Page->uid ?? 0 );
            $pos = $this->positions[$uid] ?? null;

            // unchanged pages are imported if they don't exist, their content is fetched on its own instead of fetching the upcoming pages again
            if( $pos !== null && $pos >= $this->window[0] && $pos < $this->window[1] ) {
                return;
            }

            $size = $this->contentSize();
            $this->window = $pos !== null ? [$pos, $pos + $size] : [0, 0];
            $t3Pages = $pos !== null ? array_slice( $this->sequence, $pos, $size ) : [$t3Page];
            $t3Pages = array_filter( $t3Pages, fn( $page ) => $page === $t3Page || !$this->unchangedPage( $page ) );
            $translated = array_filter( $t3Pages, fn( $page ) => isset( $this->translated[(int) ( $page->uid ?? 0 )] ) );

            // the translations of the pages are replaced by the ones of the upcoming pages
            $this->translations = [];
            $this->translations = $this->reader->translatedPages( array_map( fn( $page ) => (int) ( $page->uid ?? 0 ), $translated ), $this->langUids )
                ->groupBy( fn( $page ) => (int) $page->l10n_parent )->all();

            $records = $this->records->prefetch( $t3Pages, $translated );
            $paths = $this->files->paths( $this->typo3->ahead( $records ), $this->typo3->texts( $records ) );
        }
        catch( \Exception )
        {
            // errors are reported when the page is imported
            return;
        }

        $this->prefetchFiles( $paths );
    }


    /**
     * Returns the number of the next pages whose content is fetched at once.
     *
     * The content of each page is fetched for every imported language, so fewer pages are
     * fetched for more languages to keep the number of content records in memory similar.
     * The content of at least ten pages is fetched at once, for two languages or less CONTENT_SIZE.
     */
    protected function contentSize(): int
    {
        return min( static::CONTENT_SIZE, max( 10, intdiv( static::CONTENT_SIZE * 3, 1 + count( $this->langUids ) ) ) );
    }


    /**
     * Imports the child page and returns the saved or reused page.
     *
     * @param array<string, PageView|null> $found Existing pages of the domain by path
     * @return array{page: PageView, status: Status, path: string} Imported page, its status and its path
     */
    protected function importChild( object $t3Page, PageView $parentPage, string $domain, array $found ): array
    {
        $slug = Typo3Urls::slug( $t3Page->slug ?? '' );
        $page = $found[$slug] ?? null;

        // pages which weren't imported or were saved by this import are only used as parent
        if( $page && ( !$this->isImported( $page ) || isset( $this->usedPages[$page->id ?? ''] ) ) )
        {
            if( $page->trashed() ) {
                throw new \RuntimeException( "URL /{$slug} is used by a page which wasn't imported or was changed since" );
            }

            return ['page' => $page, 'status' => Status::Reused, 'path' => $slug];
        }

        if( $page )
        {
            $page = $this->restore( $page );
            $this->moveBelow( $page, $parentPage );
        }

        $pageData = $this->buildPageData( $t3Page, $slug, $domain, $this->redirect( $t3Page ) );
        $saved = $this->save( $t3Page, $pageData, $page, $parentPage );
        $this->usedPages[$saved['page']->id ?? ''] = true;
        $this->savedPath( $domain, $slug );

        return $saved + ['path' => $slug];
    }


    /**
     * Imports the child pages of the TYPO3 page and their descendants.
     *
     * @param Collection<int|string, Collection<int, mixed>> $pageMap TYPO3 pages grouped by parent ID
     */
    protected function importChildren( Collection $pageMap, int $parentUid, PageView $parentPage, string $domain ): void
    {
        // the existing pages of the children are fetched in chunks, so not all pages including their content are in memory
        foreach( $pageMap->get( $parentUid, Collection::make() )->chunk( CmsDB::IN_SIZE ) as $children )
        {
            // pages imported for previous chunks are fetched from the database
            $slugs = $children->map( fn( $page ) => Typo3Urls::slug( $page->slug ) )->values()->all();
            $found = Pages::findAll( ['domain' => $domain], 'path', $slugs );
            // existing pages removed from $found while the descendants are imported, fetched again when needed
            $unloaded = $watch = [];

            // saved pages and translations are only remembered if they use the URLs of pages which weren't found
            foreach( $found as $slug => $page )
            {
                if( $page === null && !isset( $this->watched[$domain][$slug] ) )
                {
                    $this->watched[$domain][$slug] = false;
                    $watch[] = $slug;
                }
            }

            foreach( $children->values() as $pos => $t3Page )
            {
                if( isset( $unloaded[$slugs[$pos]] ) ) {
                    $found = array_replace( $found, $this->reload( $slugs, $pos, $unloaded, $domain ) );
                }

                $this->prefetchSiblingVariants( $children->values(), $slugs, $pos, $found );
                $this->preload( $t3Page, $found[$slugs[$pos]] ?? null );
                $result = $this->transaction( "[{$t3Page->uid}] {$t3Page->title}", fn() => $this->importChild( $t3Page, $parentPage, $domain, $found ) );

                if( !$result ) {
                    continue;
                }

                // later pages with the same URL use the imported page, only its IDs and URL are kept
                $slim = $this->slim( $result['page'] );
                $found[$result['path']] = $this->slimPage( $slim );

                $this->report( $t3Page, $result['page'], $result['path'], $domain, $result['status'] );
                unset( $result );

                if( !$pageMap->has( (int) $t3Page->uid ) ) {
                    continue;
                }

                // only the pages of the next siblings are kept while the descendants are imported to limit the memory used per tree level
                foreach( array_slice( $slugs, $pos + 1 + static::SIBLING_SIZE ) as $slug )
                {
                    if( ( $page = $found[$slug] ?? null ) && !isset( $this->usedPages[$page->id ?? ''] ) )
                    {
                        unset( $found[$slug] );
                        $unloaded[$slug] = true;
                    }
                }

                // the descendants are imported directly after their parent, so new pages are added at the end of the page tree
                $this->importChildren( $pageMap, (int) $t3Page->uid, $this->slimPage( $slim ), $domain );

                // the descendants may have used the URLs of the next pages which weren't found before
                foreach( array_slice( $slugs, $pos + 1 ) as $slug )
                {
                    if( array_key_exists( $slug, $found ) && $found[$slug] === null && !empty( $this->watched[$domain][$slug] ) )
                    {
                        unset( $found[$slug] );
                        $unloaded[$slug] = true;
                    }
                }
            }

            foreach( $watch as $slug ) {
                unset( $this->watched[$domain][$slug] );
            }
        }
    }


    /**
     * Fetches the variants of the existing pages of the next translated sibling pages at once.
     *
     * The variants of the siblings are kept while the descendants of the previous page are imported and
     * only the ones which aren't prefetched yet are fetched, e.g. if they have been discarded meanwhile.
     *
     * @param Collection<int, mixed> $children TYPO3 sibling pages
     * @param array<int, string> $slugs URL paths of the sibling pages
     * @param int $pos Position of the sibling page imported next
     * @param array<string, PageView|null> $found Existing pages of the domain by path
     */
    protected function prefetchSiblingVariants( Collection $children, array $slugs, int $pos, array $found ): void
    {
        $page = $this->translatedPage( $children[$pos], $found[$slugs[$pos]] ?? null );

        if( !$page || isset( $this->prefetchedVariants[(string) $page->id] ) ) {
            return;
        }

        $ids = [];

        foreach( array_slice( $slugs, $pos, static::SIBLING_SIZE, true ) as $idx => $slug )
        {
            if( $page = $this->translatedPage( $children[$idx], $found[$slug] ?? null ) ) {
                $ids[] = (string) $page->id;
            }
        }

        $this->prefetchVariants( $ids, true );
    }


    /**
     * Returns the existing page of the TYPO3 page if its translations are saved by the import.
     *
     * @param PageView|null $page Existing page with the URL of the TYPO3 page
     */
    protected function translatedPage( object $t3Page, ?PageView $page ): ?PageView
    {
        if( !$page || !isset( $this->translated[(int) ( $t3Page->uid ?? 0 )] ) ) {
            return null;
        }

        return $this->isImported( $page ) && !isset( $this->usedPages[$page->id ?? ''] ) && !$this->unchangedPage( $t3Page ) ? $page : null;
    }


    /**
     * Fetches the existing pages of the next sibling pages removed before at once.
     *
     * @param array<int, string> $slugs URL paths of the sibling pages
     * @param int $pos Position of the sibling page imported next
     * @param array<string, bool> $unloaded URL paths of the removed pages, the fetched ones are removed
     * @param string $domain Domain of the pages
     * @return array<string, PageView|null> Existing pages by URL path, NULL if no page uses the URL
     */
    protected function reload( array $slugs, int $pos, array &$unloaded, string $domain ): array
    {
        $next = [];

        foreach( array_slice( $slugs, $pos ) as $slug )
        {
            if( count( $next ) >= static::SIBLING_SIZE ) {
                break;
            }

            if( isset( $unloaded[$slug] ) )
            {
                $next[] = $slug;
                unset( $unloaded[$slug] );
            }
        }

        return Pages::findAll( ['domain' => $domain], 'path', $next );
    }


    /**
     * Remembers that a page or translation has been saved with the URL path if it's the one of a next sibling page without existing page.
     */
    protected function savedPath( string $domain, string $path ): void
    {
        if( isset( $this->watched[$domain][$path] ) ) {
            $this->watched[$domain][$path] = true;
        }
    }


    /**
     * Saves the translation of the page and remembers its URL for the next sibling pages.
     *
     * @param array{lang: string, domain: string} $language Language code and domain of the translation
     * @param array<string, mixed> $pageData Page data from pageData()
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds?: array<int, string>, meta?: array<string, mixed>} $content Content elements, file IDs, shared element IDs and meta data
     * @param PageView|false|null $existing Variant in the language including trashed ones if already loaded, NULL if there's none or FALSE to load it
     * @return PageView|null Saved variant or NULL if its URL is already used
     */
    protected function saveVariant( PageView $page, array $language, string $slug, array $pageData, array $content, PageView|false|null $existing = false ): ?PageView
    {
        if( $variant = parent::saveVariant( $page, $language, $slug, $pageData, $content, $existing ) ) {
            $this->savedPath( (string) $variant->domain, (string) $variant->path );
        }

        return $variant;
    }


    /**
     * Imports all pages recursively following the TYPO3 page hierarchy.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function importPages( Collection $pages ): void
    {
        $pageMap = $pages->groupBy( 'pid' );
        $rootPages = $pageMap->get( 0, Collection::make() );
        $this->sequence( $this->treeOrder( $pageMap ) );

        foreach( $rootPages as $t3Root )
        {
            $domain = $this->site->domain( $t3Root );
            $root = $this->getRootPage( $t3Root, $domain );
            $this->info( "Importing tree: {$t3Root->title} ({$domain})" );

            $this->importChildren( $pageMap, $t3Root->uid, $root, $domain );
        }

        // root pages aren't counted
        $this->totals( $pages->count() - $rootPages->count(), 'child pages' );
    }


    /**
     * Returns the TYPO3 pages in the order they are imported, the descendants directly after their parent.
     *
     * @param Collection<int|string, Collection<int, mixed>> $pageMap TYPO3 pages grouped by parent ID
     * @return list<object> TYPO3 pages in import order
     */
    protected function treeOrder( Collection $pageMap ): array
    {
        $list = [];
        $stack = $pageMap->get( 0, Collection::make() )->reverse()->values()->all();

        while( $page = array_pop( $stack ) )
        {
            $list[] = $page;
            array_push( $stack, ...$pageMap->get( (int) $page->uid, Collection::make() )->reverse()->values()->all() );
        }

        return $list;
    }


    /**
     * Prints a dry run summary of page hierarchy.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function printDryRun( Collection $pages ): void
    {
        $pageMap = $pages->groupBy( 'pid' );

        $printTree = function( int $pid, int $depth ) use ( &$printTree, $pageMap ) {
            foreach( $pageMap->get( $pid, Collection::make() ) as $page )
            {
                $this->line( str_repeat( '  ', $depth ) . $this->dryRunLine( $page ) );
                $printTree( $page->uid, $depth + 1 );
            }
        };

        $printTree( 0, 0 );
        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Prints explicitly selected pages for a dry run.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function printSelectedDryRun( Collection $pages ): void
    {
        foreach( $pages as $page ) {
            $this->line( $this->dryRunLine( $page ) );
        }

        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Returns the line of the TYPO3 page printed by the dry run.
     */
    protected function dryRunLine( object $page ): string
    {
        $type = (int) ( $page->doktype ?? 0 ) === 4 ? ' [shortcut]' : '';
        $hidden = !empty( $page->hidden ) ? ' [hidden]' : '';

        return '[' . ( $page->uid ?? '' ) . '] ' . ( $page->title ?? '' ) . ' (' . ( $page->slug ?? '' ) . "){$type}{$hidden}";
    }
}
