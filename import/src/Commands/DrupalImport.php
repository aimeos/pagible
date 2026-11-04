<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Access;
use Aimeos\Cms\Import\Drupal7;
use Aimeos\Cms\Import\Drupal8;
use Aimeos\Cms\Import\DrupalAccess;
use Aimeos\Cms\Import\DrupalBlocks;
use Aimeos\Cms\Import\DrupalContent;
use Aimeos\Cms\Import\DrupalData;
use Aimeos\Cms\Import\DrupalDomains;
use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\DrupalFiles;
use Aimeos\Cms\Import\DrupalLanguages;
use Aimeos\Cms\Import\DrupalMeta;
use Aimeos\Cms\Import\DrupalPages;
use Aimeos\Cms\Import\DrupalPlacements;
use Aimeos\Cms\Import\DrupalPrune;
use Aimeos\Cms\Import\DrupalReader;
use Aimeos\Cms\Import\DrupalRedirects;
use Aimeos\Cms\Import\DrupalSite;
use Aimeos\Cms\Import\DrupalTracker;
use Aimeos\Cms\Import\DrupalTree;
use Aimeos\Cms\Import\DrupalUrls;
use Aimeos\Cms\Import\DrupalViews;
use Aimeos\Cms\DB as CmsDB;
use Illuminate\Database\Connection;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Tally;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Aimeos\Cms\Import\Variants;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;


class DrupalImport extends BaseImport
{
    use ImportsDrupalAccess;
    use ImportsDrupalChanges;
    use ImportsDrupalDrafts;
    use ImportsDrupalFront;
    use ImportsDrupalMenu;
    use ImportsDrupalRedirects;
    use ImportsDrupalTerms;
    use ReportsDrupalImport;

    /**
     * Command name
     */
    protected $signature = 'cms:drupal-import
        {--connection=drupal : Database connection name for the Drupal 7 or newer database}
        {--domain= : Domain name for the imported pages}
        {--drupal-domain= : Machine name or ID of the Domain Access domain whose nodes are imported, import each domain separately with its own --domain}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=drupal-import : Editor name for imported records}
        {--file-base= : Base URL for Drupal files (e.g. https://example.com/sites/default/files)}
        {--types=page,article,book : Comma-separated list of Drupal content types to import}
        {--menu= : Machine name of the Drupal menu used for the page hierarchy, "main" by default and "main-menu" for Drupal 7}
        {--language=* : Drupal language code imported as page translation with its code and optional URL of a separate domain (e.g. --language=de:de or --language=fr:fr:https://example.fr/)}
        {--unpublished : Import unpublished nodes and translations as disabled pages}
        {--node-redirects : Create redirects from the /node/{nid} paths to the imported pages}
        {--role=* : Drupal role imported as page access value with another name (e.g. --role=premium_member:premium)}
        {--prune : Move the pages, translations, shared elements and files of previous imports which were not updated to the trash, use the same options as before}
        {--since= : Only render the nodes changed since the date (e.g. 2026-01-31), the pages of the others are kept as imported before, can not be used with --prune. Changed menu links, terms and blocks of Drupal 7 are detected by comparing them to the last import}
        {--terms= : Comma-separated list of Drupal vocabularies whose terms with URL alias are imported as hidden pages listing their newest 100 nodes}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Drupal 7+ nodes into Pagible CMS pages';

    /** Number of nodes whose translations are fetched at once */
    protected const TRANSLATION_SIZE = 100;

    /** @var array<int, string> Drupal language codes of the imported translations */
    protected array $langcodes = [];
    /** @var Collection<int|string, mixed> */
    protected Collection $nodes;
    /** @var array<int, array<string, string>> Titles of the node translations in the known languages by Drupal language code and node ID */
    protected array $translated = [];
    /** @var array<int, list<mixed>> Translations of the nodes imported next by node ID */
    protected array $translationRows = [];

    /** Reads the Drupal database */
    protected DrupalReader $reader;
    /** Detects the nodes and blocks changed since the last import */
    protected DrupalTracker $tracker;
    /** Returns the access values of the pages from the view grants of Drupal's node access modules */
    protected DrupalAccess $access;
    /** Renders the fields of the Drupal entities as content elements */
    protected DrupalContent $content;
    /** Reads and caches the configuration, entities, field values and files of the Drupal database */
    protected DrupalData $data;
    /** Maps the Drupal language codes to the languages, domains and URL path prefixes of the imported pages */
    protected DrupalLanguages $languages;
    /** Maps the Drupal nodes and terms to the URLs of the imported pages */
    protected DrupalUrls $urls;
    /** Imports the files of the Drupal site */
    protected DrupalFiles $files;
    /** Imports the meta data of the nodes */
    protected DrupalMeta $meta;
    /** Imports the custom blocks as shared elements */
    protected DrupalBlocks $blocks;
    /** Collects the redirects of the Redirect module, the front page and the node paths */
    protected DrupalRedirects $redirects;
    /** Page hierarchy of the nodes from the menu, the book outlines and the URL aliases */
    protected DrupalTree $tree;
    /** Page displays of the Drupal Views which menu links can refer to */
    protected DrupalViews $views;
    /** Finds the pages of previous imports by their URL path */
    protected DrupalPages $pages;
    /** @var array<string, bool> Compact IDs of the page variants created or updated by this import, see DrupalPrune::key() */
    protected array $saved = [];
    /** @var array<string, array<string, string>> Creation and modification dates of the Drupal nodes not written yet by page variant ID */
    protected array $dates = [];
    /** @var int Number of processed nodes */
    protected int $processed = 0;
    /** @var array<int, int> Node IDs in the import order */
    protected array $sequence = [];
    /** @var array<int, int> Positions of the node IDs in the import order */
    protected array $order = [];
    /** Machine name of the imported Domain Access domain or empty string for all nodes */
    protected string $drupalDomain = '';


    /**
     * Execute command
     */
    public function handle(): void
    {
        if( !$this->init( 'Drupal' ) ) {
            return;
        }

        $this->reset();

        if( !$this->sinceOption() || ( $roles = $this->roleOption() ) === null ) {
            return;
        }

        $this->access = new DrupalAccess( $this->reader, $this->data, app( Access::class ), $roles,
            fn( int $nid ) => $this->nextNodes( $nid, CmsDB::IN_SIZE * 4 ),
            fn( object $node ) => $this->restricted[$node->nid] = $this->urls->slug( $node, true ),
            fn( string $msg ) => $this->warn( $msg ),
            fn( string $key, string $msg ) => $this->warnOnce( $key, $msg )
        );

        if( !$this->data->hasTable( 'node_field_data' ) ) {
            $this->error( 'Tables "node_field_data" or "node" and "field_config_instance" not found, only Drupal 7 and newer are supported.' );
            return;
        }

        $this->drupalDomain = $this->stringOption( 'drupal-domain' );

        if( !$this->domainAccess() ) {
            return;
        }

        $nodes = $this->reader->nodes( array_map( 'trim', explode( ',', $this->stringOption( 'types' ) ) ), (bool) $this->option( 'unpublished' ), $this->drupalDomain );

        if( $nodes->isEmpty() ) {
            $this->warn( 'No Drupal nodes found.' );
            return;
        }

        $this->info( "Found {$nodes->count()} Drupal nodes." );

        $this->setup( Variants::options( (array) $this->option( 'language' ), 'Drupal' ) );
        $this->translated = $this->loadTranslations( $nodes );
        $this->nodes = $nodes->keyBy( 'nid' );
        $this->prepare();

        if( !$this->fileBase ) {
            $this->warn( 'No --file-base given, imported files only have relative paths and can\'t be downloaded.' );
        }

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $nodes );
            return;
        }

        // the root page of the domain if the nodes of a Domain Access domain are imported
        $this->importNodes( $this->getOrCreateRoot( $this->drupalDomain !== '' ? $this->domain : null ) );
    }


    /**
     * Resets the state of the previous execution because the command instance can be executed several times, e.g. by Artisan::call().
     */
    protected function reset(): void
    {
        $this->warned = $this->saved = $this->dates = $this->linkPaths = $this->langcodes = $this->sequence = $this->order = [];
        $this->drafts = $this->pendingVariants = $this->since = $this->front = null;
        $this->restricted = $this->changed = $this->placeholders = $this->failedNodes = $this->parentVariants = $this->translationRows = [];
        $this->accessValues = null;
        $this->linkTally = new Tally();
        $this->termTally = new Tally();
        $this->warnings = $this->processed = 0;
    }


    /**
     * Returns the Drupal roles mapped to the Pagible access values from the --role option.
     *
     * @return array<string, string>|null Pagible access values by Drupal role or NULL if a value is invalid
     */
    protected function roleOption(): ?array
    {
        $roles = [];

        foreach( (array) $this->option( 'role' ) as $value )
        {
            if( !is_string( $value ) || !preg_match( '/^([^:]+):(.+)$/', $value, $m ) ) {
                $this->error( 'Invalid Drupal role: ' . ( is_string( $value ) ? $value : '' ) );
                return null;
            }

            $roles[$m[1]] = trim( $m[2] );
        }

        return $roles;
    }


    /**
     * Sets the time of the --since option.
     *
     * @return bool TRUE if the option is valid or not given, FALSE if not
     */
    #[\Override]
    protected function sinceOption(): bool
    {
        if( !parent::sinceOption() ) {
            return false;
        }

        // pages of nodes which aren't rendered would be pruned otherwise
        if( $this->since !== null && $this->option( 'prune' ) ) {
            $this->error( 'The --since option can not be used together with --prune.' );
            return false;
        }

        return true;
    }


    /**
     * Returns the titles of the node translations in the known languages by language code and node ID.
     *
     * The translations are read in chunks and only their titles are kept because there can be many.
     * The other columns are fetched for the nodes imported next by nodeTranslations().
     *
     * @param Collection<int, \stdClass> $nodes Imported nodes
     * @return array<int, array<string, string>> Titles of the first translation in each language by Drupal language code and node ID
     */
    protected function loadTranslations( Collection $nodes ): array
    {
        $known = fn( int|string $langcode ) => $this->languages->language( (string) $langcode ) !== null;
        $unpublished = (bool) $this->option( 'unpublished' );
        $translated = $unknown = [];
        $count = 0;

        foreach( array_chunk( $nodes->pluck( 'nid' )->map( fn( $id ) => (int) $id )->unique()->values()->all(), CmsDB::IN_SIZE ) as $chunk )
        {
            ['rows' => $rows, 'unknown' => $codes] = Variants::known( $this->reader->translations( $chunk, $unpublished ), fn( \stdClass $node ) => (string) $node->langcode, $known );
            $unknown = array_merge( $unknown, $codes );

            foreach( $rows as $row )
            {
                $translated[(int) $row->nid][(string) $row->langcode] ??= (string) $row->title;
                $count++;
            }
        }

        if( $unknown !== [] ) {
            $this->warn( 'Skipping translations of unknown Drupal languages [' . implode( ', ', array_unique( $unknown ) ) . '], add --language=<langcode>:<code> to import them.' );
        }

        if( $count ) {
            $this->info( "Found {$count} Drupal node translations." );
        }

        return $translated;
    }


    /**
     * Returns the translations of the node in the known languages ordered by language.
     *
     * The translations of the next nodes in the import order are fetched at once and replace the ones fetched before.
     *
     * @param int $nid Node ID
     * @return list<mixed> Translations of the node
     */
    protected function nodeTranslations( int $nid ): array
    {
        if( !isset( $this->translated[$nid] ) ) {
            return [];
        }

        if( !isset( $this->translationRows[$nid] ) )
        {
            $nids = array_values( array_filter( $this->nextNodes( $nid, static::TRANSLATION_SIZE ), fn( $id ) => isset( $this->translated[$id] ) ) );
            $this->translationRows = array_fill_keys( $nids, [] );

            foreach( $this->reader->translations( $nids, (bool) $this->option( 'unpublished' ) ) as $row )
            {
                // only translations in the known languages are imported like found by loadTranslations()
                if( isset( $this->translated[(int) $row->nid][(string) $row->langcode] ) ) {
                    $this->translationRows[(int) $row->nid][] = $row;
                }
            }
        }

        return $this->translationRows[$nid];
    }


    /**
     * Checks the --drupal-domain option against the domains of the Domain Access module.
     *
     * Uses the hostname of the Drupal domain as domain of the imported pages if no --domain is given.
     *
     * @return bool TRUE if the import can continue, FALSE if not
     */
    protected function domainAccess(): bool
    {
        $domains = new DrupalDomains( $this->data );

        if( $this->drupalDomain === '' )
        {
            if( $domains->used() ) {
                $this->warn( 'Drupal uses the Domain Access module, the nodes of all domains are imported into one site. Use --drupal-domain and --domain to import each domain separately.' );
            }

            return true;
        }

        try
        {
            $host = $domains->hostname( $this->drupalDomain );
        }
        catch( \RuntimeException $e )
        {
            $this->error( $e->getMessage() );
            return false;
        }

        if( $this->domain === '' && $host !== '' )
        {
            $this->domain = $host;
            $this->info( "Using the hostname of the Domain Access domain as --domain={$host}." );
        }

        return true;
    }


    /**
     * Returns the content, meta data and custom blocks of the node in the given language.
     *
     * @param object{nid: int, type: string, title: string} $node
     * @param string $langcode Drupal language code of the content
     * @param bool $fallback TRUE to use field values of another language if none exist for the language
     * @param int|null $vid Revision ID of a draft to render or NULL for the current revision
     * @param string|null $title Title of the draft for tokens like "[node:title]" or NULL for the node title
     * @return array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, elementIds: array<int, string>, meta: array<string, mixed>}
     */
    protected function nodeContent( object $node, string $langcode, bool $fallback, ?int $vid = null, ?string $title = null ): array
    {
        [[$content, $meta], $referenced] = $this->blocks->collect( fn() => $this->revision( $node->nid, $vid, $title, fn() => [
            $this->content->render( $node, $langcode, $fallback, $vid ),
            $this->meta->build( $node, $langcode, $fallback ),
        ] ) );
        $this->content->removed( "[{$node->nid}] " . ( $title ?? $node->title ) . " [{$langcode}]" );
        $blocks = $this->blocks->placed( $node, $langcode );

        return [
            'elements' => [...$blocks['before'], ...$content['elements'], ...$blocks['elements']],
            'fileIds' => array_values( array_unique( [...$content['fileIds'], ...array_merge( ...array_values( array_column( $meta, 'files' ) ) )] ) ),
            'elementIds' => array_values( array_unique( [...$blocks['elementIds'], ...$referenced] ) ),
            'meta' => $meta,
        ];
    }


    /**
     * Executes the function while the fields and the title of the node are read from the draft revision.
     *
     * @template T
     * @param int $nid Node ID
     * @param int|null $vid Revision ID of the draft or NULL for the current revision
     * @param string|null $title Title of the draft for tokens like "[node:title]" or NULL for the node title
     * @param \Closure(): T $fn Function to execute
     * @return T Result of the function
     */
    protected function revision( int $nid, ?int $vid, ?string $title, \Closure $fn ): mixed
    {
        return $this->data->revision( $nid, $vid, fn() => $this->meta->title( $nid, $title, $fn ) );
    }


    /**
     * Reads the common options and sets up the reader and the tracker for the Drupal version.
     *
     * @param string $label Name of the source system
     * @param string $prefix Prefix of the database environment variables, the upper case label if empty
     * @return bool TRUE if the options are valid, FALSE if not
     */
    protected function init( string $label, string $prefix = '' ): bool
    {
        if( !parent::init( $label, $prefix ) ) {
            return false;
        }

        $this->reader = $this->createReader( DB::connection( $this->connection ) );
        $this->data = new DrupalData( $this->reader, $this->stringOption( 'drupal-domain' ) );
        $this->tracker = $this->reader->tracker( $this->menuName(), fn( \stdClass $link ) => $this->urls->linkNid( $link ) );

        return true;
    }


    /**
     * Creates the objects which map the languages and find the pages and views of previous imports.
     *
     * @param array<string, array{lang: string, domain: string}|null> $languages Languages by Drupal language code from the --language option
     */
    protected function setup( array $languages ): void
    {
        $this->languages = new DrupalLanguages( $this->data, $languages );
        $this->pages = new DrupalPages( $this->domain );
        $this->views = new DrupalViews( $this->data );
    }


    /**
     * Creates the objects which import the URLs, files, meta data, content and blocks of the imported nodes and translations.
     */
    protected function prepare(): void
    {
        $warn = fn( string $msg ) => $this->warn( $msg );
        $warnOnce = fn( string $key, string $msg ) => $this->warnOnce( $key, $msg );
        $vocabularies = array_values( array_filter( array_map( 'trim', explode( ',', $this->stringOption( 'terms' ) ) ) ) );

        $this->urls = new DrupalUrls( $this->reader, $this->data, $this->languages, $this->lang, $this->nodes, $this->translated,
            fn() => $this->front, $this->drupalDomain, $vocabularies );
        $site = new DrupalSite( $this->fileBase, $this->domain, $this->languages->domains() );
        $this->files = new DrupalFiles( $this->data, $site, fn( string $mime, string $name, string $path, bool $rename = true ) => $this->createFile( $mime, $name, $path, $rename ),
            $warn, fn( array $paths ) => $this->prefetchFiles( $paths ) );
        $this->meta = new DrupalMeta( $this->data, $this->files );
        $this->content = DrupalContent::create( $this->data, $this->languages, $this->urls, $site, $this->files, $this->meta,
            fn( int $id, string $langcode, bool $fallback, int $depth ) => $this->blocks->render( $id, $langcode, $fallback, $depth ), $warn, $warnOnce );
        $placements = new DrupalPlacements( $this->data, $this->urls, $this->menuName(), $this->drupalDomain, $warn, $warnOnce );
        $this->blocks = new DrupalBlocks( $this->reader, $this->data, $this->content, $this->languages, $placements,
            fn( string $name, string $lang, array $element, array $fileIds ) => Elements::shared( $name, $lang, $element, $fileIds, $this->editor ), $warnOnce );
        $this->redirects = new DrupalRedirects( $this->reader, $this->data, $this->languages, $this->urls, $this->nodes );
        $this->tree = new DrupalTree( $this->reader, $this->data, $this->urls, $this->nodes, $this->reader->menu( $this->menuName() ) );
    }


    /**
     * Returns the reader for the Drupal 7 or Drupal 8+ database.
     */
    protected function createReader( Connection $db ): DrupalReader
    {
        $warn = fn( string $msg ) => $this->warn( $msg );
        return Drupal7::is( $db ) ? new Drupal7( $db, $warn ) : new Drupal8( $db, $warn );
    }


    /**
     * Returns the name of the Drupal menu used for the page hierarchy.
     */
    protected function menuName(): string
    {
        return $this->stringOption( 'menu' ) ?: $this->reader->defaultMenu();
    }


    /**
     * Imports a single Drupal node including its translations as a Pagible page.
     *
     * Pages imported by a previous run are updated and moved below the new parent page.
     *
     * @param object{title: string, status: int, nid: int, created: int, changed?: int, type: string, langcode?: string} $node
     */
    protected function importNode( object $node, PageView $parent ): PageView
    {
        // before rendering, so pages of restricted nodes which fail to import are known
        $access = empty( $node->status ) ? false : $this->access->node( $node );
        $slug = $this->urls->slug( $node, true );
        $this->prefetchNodePages( $node->nid, $slug );

        // nodes not changed since the given date aren't rendered again
        if( $this->unchangedSince( $node->nid ) && ( $page = $this->previousPage( $slug ) ) ) {
            return $this->keep( $node, $page, $parent, $slug, $access );
        }

        $content = $this->nodeContent( $node, $node->langcode ?? '', true );
        $data = $this->nodePageData( $node, $slug );

        ['page' => $page, 'status' => $status] = $this->savePage( $slug, $data, $content, $parent );

        $this->touch( $page, $node );
        $this->setAccess( $page, $access );
        $this->pending( $page, $node, $data, $content, true );
        $this->saveTranslations( $node, $page );

        return $this->imported( $node->nid, $page, $node->title, $slug, $status );
    }


    /**
     * Imports the node with its book or alias parents first if it isn't part of the menu.
     *
     * A node in a book outline of the Book module is imported below its parent in the book,
     * otherwise a node with the alias "/about/team" is imported below the node with the alias "/about".
     *
     * @param array<int, bool> $seen Node IDs already visited to avoid loops
     */
    protected function importByAlias( int $nid, PageView $root, array $seen = [] ): void
    {
        if( $this->createdPages->has( $nid ) || isset( $this->failedNodes[$nid] ) || !$this->nodes->has( $nid ) ) {
            return;
        }

        $parentNid = $this->tree->parent( $nid );

        if( $parentNid && !isset( $seen[$parentNid] ) ) {
            $this->importByAlias( $parentNid, $root, $seen + [$nid => true] );
        }

        $this->importTree( $nid, $parentNid ? $this->createdPage( $parentNid ) ?? $root : $root );
    }


    /**
     * Imports all nodes, using menu and alias hierarchy when available.
     *
     * The Drupal front page is imported into the root page if the root page was created by the import.
     */
    protected function importNodes( PageView $root ): void
    {
        $nids = $this->nodes->keys()->map( fn( $nid ) => (int) $nid )->all();
        $this->front = $root->editor === $this->editor ? $this->urls->frontNode() : null;

        // menu link pages get variants in all languages of the imported translations
        $codes = [];

        foreach( $this->translated as $titles ) {
            $codes += $titles;
        }

        $this->langcodes = Collection::make( array_keys( $codes ) )->map( fn( $code ) => (string) $code )
            ->filter( fn( $code ) => ( $this->languages->language( $code )['lang'] ?? $this->lang ) !== $this->lang )->sort()->values()->all();

        if( $this->since !== null ) {
            $this->changed = $this->fetchChanged( $nids, $this->since );
            $this->refreshBlocks();
        }

        $sequence = $this->sequence = array_values( $this->tree->sequence() );
        $this->order = array_flip( $sequence );
        $this->data->sequence( $sequence, fn( int $nid ) => $this->unchangedSince( $nid ), $this->nodes->map( fn( $node ) => (string) $node->type )->all() );

        $this->importFront( $root );

        foreach( $this->tree->roots() as $link ) {
            $this->importLink( $link, $root );
        }

        foreach( $sequence as $nid ) {
            $this->importByAlias( $nid, $root );
        }

        $this->protect();

        $this->totals( $this->nodes->count(), 'nodes' );

        $this->subtotals( $this->linkTally, 'menu links' );

        $this->importTerms( $root );
        $this->reportViews();
        $this->importRedirects( $root );
        $this->writeDates();
        $this->reportStale();

        $this->summary();
    }


    /**
     * Fetches the pages of the node and the next nodes in the import order and their variants at once if the page of the node isn't fetched yet.
     *
     * @param int $nid Node ID
     * @param string $slug URL path of the node
     */
    protected function prefetchNodePages( int $nid, string $slug ): void
    {
        if( array_key_exists( trim( $slug, '/' ), $this->prefetchedPages ) ) {
            return;
        }

        $slugs = [$nid => $slug];

        foreach( array_slice( $this->nextNodes( $nid, CmsDB::IN_SIZE ), 1 ) as $id )
        {
            if( $next = $this->nodes->get( $id ) ) {
                $slugs[$id] = $this->urls->slug( $next, true );
            }
        }

        $this->prefetchPages( array_values( $slugs ) );

        // the variants of the existing pages of translated nodes are fetched at once for keep() and saveTranslations()
        $ids = [];

        foreach( $slugs as $id => $path )
        {
            if( isset( $this->translated[$id] ) && ( $pageId = ( $this->prefetchedPages[trim( $path, '/' )] ?? null )?->id ) ) {
                $ids[] = $pageId;
            }
        }

        $this->prefetchVariants( $ids );
    }


    /**
     * Returns the ID of the node and the IDs of the next nodes in the import order which aren't imported yet.
     *
     * @param int $nid Node ID
     * @param int $count Maximum number of node IDs
     * @return array<int, int> Node IDs, the given one first
     */
    protected function nextNodes( int $nid, int $count ): array
    {
        $nids = [$nid];
        $pos = $this->order[$nid] ?? null;

        for( $idx = $pos === null ? PHP_INT_MAX : $pos + 1, $len = count( $this->sequence ); $idx < $len && count( $nids ) < $count; $idx++ )
        {
            $id = $this->sequence[$idx];

            if( !$this->createdPages->has( $id ) && !isset( $this->failedNodes[$id] ) && $this->nodes->has( $id ) ) {
                $nids[] = $id;
            }
        }

        return $nids;
    }


    /**
     * Returns the data of the page for the node using the menu link title as page name and the Metatag title.
     *
     * @param object{nid: int, type: string, title: string, status: int, langcode?: string} $node
     * @return array<string, mixed>
     */
    protected function nodePageData( object $node, string $slug ): array
    {
        $data = $this->pageData( $node->title, $slug, (int) $node->status );
        $titles = $this->tree->names( $node->nid );
        $langcode = $node->langcode ?? '';

        // translations without translated menu link keep their own title
        $default = isset( $this->nodes ) && ( $this->nodes->get( $node->nid )->langcode ?? null ) === $langcode;

        if( $name = $titles[$langcode] ?? ( $default ? reset( $titles ) : '' ) ) {
            $data['name'] = $name;
        }

        if( $title = $this->meta->tags( $node, $langcode )['title'] ?? '' ) {
            $data['title'] = Str::limit( $title, 254, '…', true );
        }

        return $data;
    }


    /**
     * Creates or updates the variants of the page from the node translations.
     *
     * @param object{nid: int, type: string} $node
     */
    protected function saveTranslations( object $node, PageView $page ): void
    {
        $translations = $this->nodeTranslations( $node->nid );
        // the variants of all languages including trashed ones are fetched at once
        $variants = count( $translations ) ? $this->variants( $page ) : [];

        foreach( $translations as $translation )
        {
            $language = $this->languages->language( $translation->langcode );

            if( !$language || $language['lang'] === $this->lang ) {
                continue;
            }

            // translations changed in Pagible aren't overwritten
            $existing = $variants[$language['lang']] ?? null;

            if( $this->skipsTranslation( $existing, $translation->title, $language['lang'] ) ) {
                continue;
            }

            $content = $this->nodeContent( $translation, $translation->langcode, false );
            // translations of the front page use the language prefix if available, e.g. "/de"
            $slug = $node->nid === $this->urls->front() ? $this->languages->prefix( $translation->langcode ) ?: $this->urls->slug( $translation ) : $this->urls->slug( $translation );

            $data = $this->nodePageData( $translation, $slug );

            if( $variant = $this->saveVariant( $page, $language, $slug, $data, $content, $existing ) ) {
                // later translations in the same language use the saved variant
                $variants[$language['lang']] = $variant;
                $this->touch( $variant, $translation );
                $this->pending( $variant, $translation, $data, $content, false );
            }
        }
    }


    /**
     * Sets the creation and last modification dates of the page to the ones of the Drupal node.
     *
     * The page variant is also remembered as saved by this import. The dates aren't part of
     * the search index, so they are only written if they differ without loading the variant.
     * They are written together for all pages of the batch before it's committed.
     *
     * @param object{created?: int, changed?: int} $node
     */
    protected function touch( PageView $page, object $node ): void
    {
        $this->markSaved( $page->variant_id );

        $dates = array_filter( [
            'created_at' => ( $node->created ?? 0 ) > 0 ? date( 'Y-m-d H:i:s', (int) $node->created ) : null,
            'updated_at' => ( $node->changed ?? 0 ) > 0 ? date( 'Y-m-d H:i:s', (int) $node->changed ) : null,
        ] );
        $dates = array_diff_assoc( $dates, array_filter( [
            'created_at' => $page->created_at?->format( 'Y-m-d H:i:s' ),
            'updated_at' => $page->updated_at?->format( 'Y-m-d H:i:s' ),
        ] ) );

        if( $dates ) {
            $this->dates[$page->variant_id] = $dates + ( $this->dates[$page->variant_id] ?? [] );
        }
    }


    /**
     * Writes the dates of the touched page variants with one query per column and chunk of variants.
     */
    protected function writeDates(): void
    {
        $db = CmsDB::connection();
        $grammar = $db->getQueryGrammar();
        $table = $grammar->wrapTable( ( new PageVariant )->getTable() );

        foreach( array_chunk( $this->dates, CmsDB::IN_SIZE / 2, true ) as $chunk )
        {
            foreach( ['created_at', 'updated_at'] as $column )
            {
                $values = array_filter( array_map( fn( $dates ) => $dates[$column] ?? null, $chunk ) );

                if( !$values ) {
                    continue;
                }

                $ids = array_map( strval( ... ), array_keys( $values ) );
                $col = $grammar->wrap( $column );
                $sql = "UPDATE {$table} SET {$col} = CASE " . $grammar->wrap( 'id' ) . str_repeat( ' WHEN ? THEN ?', count( $values ) )
                    . " ELSE {$col} END WHERE " . $grammar->wrap( 'id' ) . ' IN (' . implode( ',', array_fill( 0, count( $ids ), '?' ) ) . ')';
                $bindings = array_merge( ...array_map( fn( $id, $date ) => [$id, $date], $ids, array_values( $values ) ) );

                $db->update( $sql, [...$bindings, ...$ids] );
            }
        }

        $this->dates = [];
    }


    /**
     * Writes the dates of the touched page variants before the batch is committed.
     */
    #[\Override]
    protected function committing(): void
    {
        $this->writeDates();
        parent::committing();
    }


    /**
     * Remembers the page variant as saved by this import, so it's not reported as stale.
     */
    protected function markSaved( string $variantId ): void
    {
        $this->saved[DrupalPrune::key( $variantId )] = true;
    }
}
