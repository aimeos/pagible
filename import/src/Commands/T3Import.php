<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Commands;
use Aimeos\Cms\Concerns\Typo3\Changes;
use Aimeos\Cms\Concerns\Typo3\Content;
use Aimeos\Cms\Concerns\Typo3\Prefetch;
use Aimeos\Cms\Concerns\Typo3\Redirects;
use Aimeos\Cms\Concerns\Typo3\Reports;
use Aimeos\Cms\Concerns\Typo3\Site;
use Aimeos\Cms\Concerns\Typo3\Storages;
use Aimeos\Cms\Concerns\Typo3\Translations;
use Aimeos\Cms\Concerns\Typo3\Tree;
use Aimeos\Cms\Import\Database;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class T3Import extends Commands
{
    use Changes;
    use Content;
    use Prefetch;
    use Redirects;
    use Reports;
    use Site;
    use Storages;
    use Translations;
    use Tree;

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
        {--path= : Path to the TYPO3 installation for the site configurations and TSconfig files (e.g. /var/www/typo3)}
        {--group=* : Element group of the content elements in the TYPO3 column (e.g. --group=1:sidebar), "main" by default}
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

    /** Maps the TYPO3 language UIDs to the languages of the pages */
    protected Typo3\Languages $languages;

    /** Maps the TYPO3 page trees to their domains */
    protected Typo3\Site $site;

    protected ?Typo3\Content $typo3 = null;

    /** Reads the TYPO3 database */
    protected Typo3\Reader $reader;

    /** Imports the TYPO3 file references and fileadmin URLs */
    protected Typo3\Files $files;

    /** Selects the content records of the TYPO3 pages */
    protected Typo3\Records $records;

    /** Resolves the URLs of the TYPO3 pages */
    protected Typo3\Urls $urls;

    /** Navigates the TYPO3 page tree */
    protected Typo3\Tree $tree;

    /** @var array<int, string> Element groups by TYPO3 column number */
    protected array $groups = [];

    /** Path to the TYPO3 installation or empty string */
    protected string $path = '';

    /** @var Collection<int, mixed> TYPO3 pages keyed by UID */
    protected Collection $pagesById;


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

        if( !$this->groupOption() ) {
            return;
        }

        $this->reader = new Typo3\Reader( DB::connection( $this->connection ) );
        $this->site = new Typo3\Site( (array) $this->option( 'domain' ), fn() => $this->reader->domains() );

        if( ( $this->path = rtrim( $this->stringOption( 'path' ), '/' ) ) !== '' )
        {
            if( !is_dir( $this->path ) )
            {
                $this->error( "TYPO3 installation path \"{$this->path}\" doesn't exist." );
                return;
            }

            $this->info( "Found {$this->site->configure( $this->path )} TYPO3 site configurations." );
        }

        $this->fileBase = $this->site->fileBase( $this->fileBase );

        // the URLs of the pages are only stored in the slug column since TYPO3 9
        if( !$this->reader->hasColumn( 'pages', 'slug' ) )
        {
            $this->error( 'The TYPO3 pages have no URL paths, TYPO3 9 or newer is required, upgrade the installation first.' );
            return;
        }

        if( ( $all = $this->reader->pages() )->isEmpty() )
        {
            $this->warn( 'No TYPO3 pages found.' );
            return;
        }

        // the languages of the site configurations replace the legacy sys_language records
        $this->languages = new Typo3\Languages( $this->site->languages() + $this->reader->languages(), (array) $this->option( 'language' ), $this->site->sites() );
        $translations = $this->loadTranslations();

        $this->pagesById = $pagesById = $all->keyBy( fn( $page ) => (int) $page->uid );
        $this->siteRoots = [];
        $this->tree = new Typo3\Tree( $pagesById );

        // spacers, folders, recyclers and the pages inside recyclers aren't imported
        $pages = $this->tree->importable()->values();
        $pageIds = Typo3\Tree::uids( (array) $this->option( 'page' ) );
        $selectedPages = $pageIds === null ? $pages : $this->selectPages( $pageIds );

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
     * Parses the --group options which map the TYPO3 columns to element groups.
     */
    protected function groupOption(): bool
    {
        foreach( (array) $this->option( 'group' ) as $entry )
        {
            if( !preg_match( '/^(-?\d+):([\w-]+)$/D', (string) $entry, $matches ) )
            {
                $this->error( "Invalid --group value \"{$entry}\", use <column number>:<group> like --group=1:sidebar" );
                return false;
            }

            $this->groups[(int) $matches[1]] = $matches[2];
        }

        return true;
    }


    /**
     * Returns the selected TYPO3 pages which are imported and warns about the ones which aren't.
     *
     * @param list<int> $uids UIDs of the selected TYPO3 pages
     * @return Collection<int, mixed> Selected TYPO3 pages
     */
    protected function selectPages( array $uids ): Collection
    {
        return $this->tree->select( $uids )->filter( function( $page ) {
            if( $this->tree->imported( $page ) ) {
                return true;
            }

            $this->warn( 'Skipped [' . ( $page->uid ?? '' ) . '] ' . ( $page->title ?? '' ) . ' because spacers, folders, recyclers and their subpages are not imported' );
            return false;
        } )->values();
    }


    /**
     * Creates the objects which import the files, URLs and content of the pages.
     *
     * @param Collection<int, mixed> $pagesById TYPO3 pages by UID
     */
    protected function prepare( Collection $pagesById ): void
    {
        $this->shared = $this->failedFiles = $this->ogImages = $this->onlineMedia = [];
        $this->storageFolders = null;
        // without --lang, the default languages of the sites differ and translations into them are skipped per page
        $this->langUids = $this->languages->imported( $this->explicitLang() || !$this->site->sites() ? $this->lang : '' );
        $this->files = new Typo3\Files( $this->reader->filesByIdentifier( ... ), $this->fileBase,
            fn( string $mime, string $name, string $path, bool $unknown = false, array $options = [] ) => $this->importFile( $mime, $name, $path, str_starts_with( $path, 'http' ), $options ),
            fn( string $path ) => $this->createdFileUrls->get( $path ), $this->knownFile( ... ), $this->storages(), $this->reader->storageBases(),
            fn( string $identifier, ?int $storage ) => ( $uid = $this->reader->processedFiles( [$identifier] )[$identifier] ?? null ) ? $this->reader->filesByUid( [$uid] )->get( $uid ) : null,
            fn( int $langUid ) => $langUid > 0 ? ( $this->languages->get( $langUid )['lang'] ?? '' ) : ( $this->explicitLang() || !$this->site->sites() ? $this->lang : '' ) );
        $this->urls = new Typo3\Urls( $pagesById, $this->translated );
        $this->typo3 = new Typo3\Content(
            fn( object $ref ) => $this->attempt( $this->refLabel( $ref ), fn() => $this->files->reference( $ref ) ),
            $this->rewriter(),
            $this->reader->fileReferences( ... ),
            fn( array $uids ) => $this->reader->items( 'tx_bootstrappackage_accordion_item', $uids ),
            fn( array $uids ) => $this->reader->items( 'tx_bootstrappackage_carousel_item', $uids, ['link', 'item_type', 'button_text', 'header_layout'] ),
            $this->reader->carouselFileReferences( ... ),
            $this->reader->accordionFileReferences( ... ),
            fn( object $ref ) => $this->attempt( $this->refLabel( $ref ), fn() => $this->onlineMedia( $ref ) ),
            fn( object $ref ) => ( $sysFile = get_object_vars( $ref )['sys_file'] ?? null ) instanceof \stdClass
                ? '/' . $this->storageFolder( (int) ( $sysFile->storage ?? 0 ) ) . '/' . ltrim( (string) ( $sysFile->identifier ?? '' ), '/' ) : null,
            fn( string $table, array $uids, array $columns ) => $this->reader->items( $table, $uids, $columns ),
            fn( string $table, array $uids, array $fields ) => $this->reader->itemFileReferences( $table, $uids, $fields ),
            fn( array $uids ) => $this->reader->collectionFiles( $uids ),
            fn( string $message ) => $this->warn( '  ' . $message ),
            fn( string $id ) => Files::contents( $id )
        );
        $layouts = new Typo3\Layouts( $pagesById, $this->reader->backendLayouts(), $this->path !== '' ? $this->includeFile( ... ) : null,
            $this->path !== '' ? $this->globalTsconfig() : '',
            fn( object $record, int $colPos ) => $this->warn( '  Skipped content element [' . ( $record->uid ?? '' ) . "] in column {$colPos} not part of the backend layout" ) );
        $this->records = new Typo3\Records( $pagesById, $this->reader->content( ... ), fn( array $pids ) => $this->reader->translatedContent( $pids, $this->langUids ),
            $this->reader->contentRecords( ... ), $this->reader->shortcuts(), $layouts,
            fn( int $langUid, ?object $t3Page ) => $this->site->fallback( $langUid, $t3Page ? $this->siteRoot( $t3Page ) : null ), $this->reader->contentTranslations( ... ) );
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
            'tag' => $this->isRoot( $t3Page ) ? Pages::TAG_ROOT : Pages::TAG_PAGE,
            'domain' => $domain,
            'lang' => $this->langOf( $t3Page ),
            'to' => $to,
            'theme' => $this->theme,
            'status' => !empty( $t3Page->hidden ) || $reason !== null ? Pages::STATUS_DISABLED : ( !empty( $t3Page->nav_hide ) ? Pages::STATUS_HIDDEN : Pages::STATUS_PUBLISHED ),
            'editor' => $this->editor,
        ];
    }


    /**
     * Builds the meta data of the page from the SEO and social media fields of the TYPO3 page.
     *
     * @param object $t3Page TYPO3 page or page translation
     * @param array{uid: int, source: string}|null $translation TYPO3 language of the translation or NULL for the source page
     * @return array{meta: array<string, array{type: string, data: array<string, mixed>, files: array<int, string>}>, fileIds: array<int, string>}
     */
    protected function meta( object $t3Page, ?array $translation = null ): array
    {
        $description = trim( (string) ( $t3Page->description ?? '' ) );
        $meta = array_filter( ['meta-tags' => Elements::metaTags( $description, (string) ( $t3Page->keywords ?? '' ) )] );
        $fileId = null;

        if( property_exists( $t3Page, 'og_image' ) || property_exists( $t3Page, 'og_title' ) )
        {
            $uid = (int) ( $t3Page->uid ?? 0 );
            $refs = array_key_exists( $uid, $this->ogImages ) ? $this->ogImages[$uid] : $this->reader->pageFileReferences( [$uid] )->get( $uid );
            $ref = $refs?->first();
            $fileId = $ref ? $this->attempt( $this->refLabel( $ref ), fn() => $this->files->reference( $ref ) ) : null;
        }

        // the Twitter card texts are used if there are no Open Graph texts
        $title = trim( (string) ( $t3Page->og_title ?? '' ) ) ?: trim( (string) ( $t3Page->twitter_title ?? '' ) );

        if( $title !== '' || $fileId )
        {
            $meta['social-media'] = ['type' => 'social-media', 'data' => array_filter( [
                'title' => Str::limit( $title ?: ( ( $t3Page->seo_title ?? null ) ?: (string) ( $t3Page->title ?? '' ) ), Elements::SOCIAL_TITLE_SIZE, '…', true ),
                'description' => Str::limit( (string) preg_replace( '/\s+/', ' ', trim( (string) ( $t3Page->og_description ?? '' ) ) ?: trim( (string) ( $t3Page->twitter_description ?? '' ) ) ?: $description ), Elements::DESCRIPTION_SIZE, '…', true ),
                'file' => $fileId ? Elements::file( $fileId ) : null,
            ] ), 'files' => $fileId ? [$fileId] : []];
        }

        $canonical = trim( (string) ( $t3Page->canonical_link ?? '' ) );

        if( preg_match( '#^t3://page\?(?:.*&)?uid=(\d+)#i', $canonical, $matches ) ) {
            $canonical = (string) $this->urls->page( (int) $matches[1], '', $translation );
        }

        if( $canonical !== '' && ( str_starts_with( $canonical, '/' ) || filter_var( $canonical, FILTER_VALIDATE_URL ) && preg_match( '#^https?://#i', $canonical ) ) ) {
            $meta['canonical'] = ['type' => 'canonical', 'data' => ['url' => $canonical], 'files' => []];
        }

        $robots = implode( ',', array_filter( [!empty( $t3Page->no_index ) ? 'noindex' : null, !empty( $t3Page->no_follow ) ? 'nofollow' : null] ) );

        if( $robots = Elements::robots( $robots ) ) {
            $meta['robots'] = $robots;
        }

        return ['meta' => $meta, 'fileIds' => $fileId ? [$fileId] : []];
    }


    /**
     * Gets, updates or creates the root page for a domain.
     *
     * Root pages which weren't imported or were changed since are used as they are.
     */
    protected function getRootPage( object $t3Root, string $domain ): PageView
    {
        $page = Pages::find( ['tag' => Pages::TAG_ROOT, 'domain' => $domain] );

        if( $page && !$this->isImported( $page ) )
        {
            $this->info( "Using existing root page: {$page->name} ({$domain})" );
        }
        else
        {
            $slug = Typo3\Urls::slug( $t3Root->slug ?? null );
            ['page' => $page, 'status' => $status] = $this->save( $t3Root, ['tag' => Pages::TAG_ROOT] + $this->buildPageData( $t3Root, $slug, $domain ), $page );
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
            && !$selected->has( (int) $page->uid ) && !$this->isRoot( $page )
        )->keyBy( fn( $page ) => (int) $page->uid )->values();

        $destinations = new Typo3\Destinations( $selectedPages->concat( $parents ), $this->domainOf( ... ), $this->isRoot( ... ) );
        $this->sequence( $selectedPages->all() );

        foreach( $selectedPages as $t3Page )
        {
            // trashed pages are only restored within the transaction of the page, so they stay trashed if its import fails
            $this->preload( $t3Page, $destinations->peek( $t3Page, $this->domainOf( $t3Page ) ) );
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
     * @param Typo3\Destinations $destinations Existing pages of the selected TYPO3 pages
     * @return array{page: PageView, status: Status, path: string, domain: string}
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    protected function importSelectedPage( object $t3Page, Collection $processed, Typo3\Destinations $destinations ): array
    {
        $domain = $this->domainOf( $t3Page );
        $path = Typo3\Urls::slug( $t3Page->slug ?? null );
        $pageData = $this->buildPageData( $t3Page, $path, $domain, $this->redirect( $t3Page ) );
        $isRoot = $this->isRoot( $t3Page );
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
        $this->currentPage = $t3Page;
        $content = $this->withMeta( $this->buildContent( $this->records->page( $t3Page ), $this->typo3, (string) ( $pageData['lang'] ?? $this->langOf( $t3Page ) ) ), $t3Page );
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
     * Adds the meta data of the TYPO3 page to the content.
     *
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>} $content Content of the page
     * @param array{uid: int, source: string}|null $translation TYPO3 language of the translation or NULL for the source page
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>, meta: array<string, mixed>}
     */
    protected function withMeta( array $content, object $t3Page, ?array $translation = null ): array
    {
        ['meta' => $meta, 'fileIds' => $fileIds] = $this->meta( $t3Page, $translation );
        return ['fileIds' => array_values( array_unique( [...$content['fileIds'], ...$fileIds] ) ), 'meta' => $meta] + $content;
    }


    /**
     * Imports all pages recursively following the TYPO3 page hierarchy.
     *
     * @param Collection<int, mixed> $pages
     */
    protected function importPages( Collection $pages ): void
    {
        // site roots with own domain inside the page tree are imported as root pages of their domain
        $rootPages = $pages->filter( fn( $page ) => $this->isRoot( $page ) )->values();
        $pageMap = $pages->filter( fn( $page ) => !$this->isRoot( $page ) )->groupBy( fn( $page ) => (int) $this->tree->pid( $page ) );
        $this->sequence( $this->treeOrder( $pageMap, $rootPages ) );

        $roots = [];

        foreach( $rootPages as $t3Root )
        {
            $domain = $this->site->domain( $t3Root );
            $root = $this->getRootPage( $t3Root, $domain );
            $this->info( "Importing tree: {$t3Root->title} ({$domain})" );

            $this->importChildren( $pageMap, $t3Root->uid, $root, $domain );
            $slim = $this->slimPage( $this->slim( $root ) );

            // redirects of the domains of translations are saved below the root page too
            foreach( $this->hostsOf( $t3Root ) as $host => $lang ) {
                $roots[$host] ??= ['page' => $slim, 'lang' => $lang];
            }
        }

        // root pages aren't counted
        $this->totals( $pages->count() - $rootPages->count(), 'child pages' );
        $this->importRedirects( $roots, $pages );
    }


}
