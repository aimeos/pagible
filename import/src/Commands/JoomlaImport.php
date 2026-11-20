<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Commands;
use Aimeos\Cms\Concerns\Joomla\Shared;
use Aimeos\Cms\Concerns\Joomla\Articles;
use Aimeos\Cms\Concerns\Joomla\Categories;
use Aimeos\Cms\Concerns\Joomla\Changes;
use Aimeos\Cms\Concerns\Joomla\Home;
use Aimeos\Cms\Concerns\Joomla\Menu;
use Aimeos\Cms\Concerns\Joomla\Redirects;
use Aimeos\Cms\Concerns\Joomla\Reports;
use Aimeos\Cms\Concerns\Joomla\Vanished;
use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class JoomlaImport extends Commands
{
    use Shared;
    use Articles;
    use Categories;
    use Changes;
    use Home;
    use Menu;
    use Redirects;
    use Reports;
    use Vanished;


    /**
     * Command name
     */
    protected $signature = 'cms:joomla-import
        {--connection=joomla : Database connection name for the Joomla database}
        {--domain= : Domain name for the imported pages}
        {--lang=en : Language code for the imported pages}
        {--tenant= : Tenant ID for multi-tenant setups}
        {--editor=joomla-import : Editor name for imported records}
        {--file-base= : Base URL for Joomla files (e.g. https://example.com)}
        {--prefix=#__ : Joomla database table prefix}
        {--menu=mainmenu : Menu type to use for page hierarchy}
        {--language=* : Joomla language tag imported as page translation with its code and optional URL of a separate domain (e.g. --language=de-DE:de or --language=fr-FR:fr:https://example.fr/)}
        {--suffix : Joomla adds ".html" to the URLs (the "sef_suffix" setting), the suffix is removed from the URLs of the redirects}
        {--timezone= : Time zone of the Joomla site (the "offset" setting) the dates of calendar fields are shown in (e.g. Europe/Berlin), UTC by default}
        {--since= : Only import the articles changed since the date (e.g. 2026-01-31) including their translations, the pages of the others are kept as imported before. The date is in the --timezone of the Joomla site. Articles published or expired since count as changed, also by the publishing dates of the menu items linking to them or their categories (not of URL or alias menu items) and articles and translations whose category or one of its parents was saved since (e.g. changed access, state, name or alias), renamed menu items rename the pages of their articles and translations and the root page is compared with the home menu item in every run, but other changes of menu item options or modules, state changes Joomla does not record as modification (publishing, unpublishing, archiving or featuring articles from the article list) and articles whose import failed in a previous run need an import without --since}
        {--dry-run : Show what would be imported without making changes}';

    /**
     * Command description
     */
    protected $description = 'Imports Joomla articles into Pagible CMS pages';

    /** Maximum number of article and translation texts fetched at once, the texts can contain large HTML and embedded images */
    protected const TEXT_SIZE = 100;

    /** Only the IDs of the imported articles are kept, the menu parents are kept separately */
    protected bool $slimPages = false;


    /**
     * Execute command
     *
     * Each run creates new reader, wiring and run objects, so nothing of a previous run in the same process is reused.
     */
    public function handle(): int
    {
        // the --since date is in the time zone of the Joomla site like the dates Joomla shows
        if( !$this->init( 'Joomla' ) || !( $timezone = $this->timezoneOption() ) || !$this->sinceOption( $timezone ) ) {
            return self::FAILURE;
        }

        // base URLs without scheme would be relative paths of the files and couldn't recognize the URLs of the site
        if( ( $fileBase = Joomla\Origin::baseUrl( $this->fileBase ) ) !== $this->fileBase )
        {
            $this->warn( "Using {$fileBase} for --file-base without scheme." );
            $this->fileBase = $fileBase;
        }

        $host = new Joomla\Host(
            warn: $this->warn( ... ),
            warnOnce: fn( string $msg ) => $this->warnOnce( $msg, $msg ),
            // texts, tags and field values of articles which weren't prefetched are only reported in the debug output
            debug: fn( string $msg ) => $this->line( $msg, null, 'vvv' ),
            image: $this->imageFile( ... ),
            pageData: $this->pageData( ... ),
            timezone: $timezone
        );
        $reader = new Joomla\Reader( DB::connection( $this->connection ), $this->stringOption( 'prefix' ), $host->warn );
        $origin = new Joomla\Origin( $this->domain, $this->fileBase, !empty( $this->option( 'suffix' ) ) );
        $this->wiring = Joomla\Wiring::read( $reader, $host, $origin, $this->stringOption( 'menu' ), (array) $this->option( 'language' ), $this->lang );

        $articles = $this->wiring->articles( $this->since !== null );

        if( $articles->categoryIds === [] ) {
            $this->warn( 'No Joomla articles found.' );
            return self::SUCCESS;
        }

        $this->info( 'Found ' . count( $articles->categoryIds ) . ' Joomla articles.' );

        if( $articles->translations !== [] ) {
            $this->info( 'Found ' . array_sum( array_map( 'count', $articles->translations ) ) . ' Joomla article translations.' );
        }

        if( $this->option( 'dry-run' ) ) {
            $this->printDryRun( $reader, $articles );
            return self::SUCCESS;
        }

        $root = $this->getOrCreateRoot( $this->domain );
        $this->startRun( $reader, $articles, $root );
        $this->importContent( $root, $this->newRoot );

        return $this->summary();
    }


    /**
     * Creates the objects of the run and the modification times of the articles, the same for the import and the dry run.
     *
     * @param Joomla\Reader $reader Reader for the Joomla database
     * @param Joomla\Articles $articles Imported articles and translations
     * @param PageView|null $root Root page or NULL if the dry run finds none, an import would create it
     */
    protected function startRun( Joomla\Reader $reader, Joomla\Articles $articles, ?PageView $root ): void
    {
        $this->run = $this->wiring->run( $articles, $this->updatesRoot( $root ) );
        // the modification times are needed by prefetch() and unchangedSince() before anything is imported
        $this->changed = $this->since !== null
            ? ( new Joomla\Tracker( $reader, $this->wiring->site ) )->changed( $articles, $this->since )
            : [];
    }


    /**
     * Tests if the import may update the root page, i.e. if it's created by the import or was imported before and wasn't changed since.
     *
     * @param PageView|null $root Root page or NULL if the dry run finds none, an import would create it
     */
    protected function updatesRoot( ?PageView $root ): bool
    {
        return !$root || $this->isImported( $root );
    }


    /**
     * Returns the time zone of the Joomla site from the --timezone option.
     *
     * @return \DateTimeZone|null Time zone, UTC without option or NULL if the option is invalid
     */
    protected function timezoneOption(): ?\DateTimeZone
    {
        try {
            return new \DateTimeZone( $this->stringOption( 'timezone' ) ?: Joomla\Date::TIMEZONE );
        } catch( \Exception ) {
            $this->error( 'Invalid time zone for --timezone: ' . $this->stringOption( 'timezone' ) );
            return null;
        }
    }


    /**
     * Imports an image of an article, a category or an HTML tag.
     *
     * @param string $path Path or URL of the image, the suffix of the Joomla 4+ media field is removed
     * @param string $alt Alternative text used as file name, the file name of the path if empty
     * @return string|null ID of the file or NULL if there's no image
     */
    protected function imageFile( string $path, string $alt ): ?string
    {
        // the article, category, field and HTML images are all imported here, so the suffix is removed in one place
        if( ( $path = Joomla\Image::path( $path ) ) === '' ) {
            return null;
        }

        $name = basename( parse_url( $path, PHP_URL_PATH ) ?: 'image' );

        return $this->createFile( Files::mime( $path ), $alt ?: $name, $this->resolveFile( $path ) );
    }


    /**
     * Imports the home pages, the menu items with their categories and articles and the remaining articles below their categories.
     *
     * @param bool $newRoot TRUE if the root page was created by this import
     */
    protected function importContent( PageView $root, bool $newRoot ): void
    {
        $articles = $this->run->articles;

        // the home menu item, also one of another menu, is imported first, so menu items showing the same article or category use the root page
        $homeImported = false;

        if( $home = $this->run->home->item )
        {
            // the articles of the home menu items of the other languages are shown by the variants of the root page
            $this->prefetch( array_filter( [$this->run->home->linkedArticleId()] ), $root );
            $homeImported = $this->importHome( $home, $root, $newRoot );
        }

        // the articles and menu items are split into chunks whose texts are fetched at once
        $chunks = $this->chunks( $articles );
        $this->importMenu( $root, $chunks, $homeImported );

        foreach( $this->articleChunks( $chunks, $articles ) as $chunk )
        {
            // the articles, the texts of the articles and their translations and the pages of the articles are fetched per chunk
            $this->prefetch( $chunk->keys()->all() );
            // the folders of the categories are created while the articles are imported below them
            $this->prefetchCategories( array_values( array_intersect_key( $articles->categoryIds, $chunk->all() ) ) );

            foreach( $chunk->keys() as $id )
            {
                if( $article = $this->pendingArticle( $id ) ) {
                    $this->tryImportArticle( $article, $this->parentForCategory( $articles->categoryIds[$id], $root ) );
                }
            }
        }

        $this->totals( count( $articles->categoryIds ), 'articles' );
        $this->importRedirects( $root );
        // the pages of the records which aren't imported any more are only known after all pages were saved
        $this->disableVanished();
    }


    /**
     * Returns the object splitting the articles and menu items into chunks whose texts are fetched at once.
     *
     * @param Joomla\Articles $articles Imported articles and translations
     */
    protected function chunks( Joomla\Articles $articles ): Joomla\Chunks
    {
        return new Joomla\Chunks( $articles->translations, static::TEXT_SIZE );
    }


    /**
     * Returns the chunks of the articles in import order, the same for the import and the dry run.
     *
     * @param Joomla\Chunks $chunks Splits the articles into chunks, see chunks()
     * @param Joomla\Articles $articles Imported articles and translations
     * @return \Generator<int, Collection<int|string, int>> Positions of the categories by article ID per chunk
     */
    protected function articleChunks( Joomla\Chunks $chunks, Joomla\Articles $articles ): \Generator
    {
        return $chunks->articles( $this->articleOrder( $this->wiring->site->categories, $articles->categoryIds ) );
    }


    /**
     * Returns the positions of the articles ordered by the positions of their categories in the category tree.
     *
     * Articles are imported by category in tree order, so new pages are added at the end of the page tree.
     *
     * @param Collection<array-key, \stdClass> $categories Categories by ID in tree order
     * @param array<int|string, int> $articleCategories Category IDs by article ID
     * @return array<int|string, int> Positions of the categories by article ID in import order
     */
    protected function articleOrder( Collection $categories, array $articleCategories ): array
    {
        $positions = array_flip( $categories->keys()->all() );
        $order = array_map( fn( $catId ) => $positions[$catId] ?? PHP_INT_MAX, $articleCategories );
        asort( $order );

        return $order;
    }
}
