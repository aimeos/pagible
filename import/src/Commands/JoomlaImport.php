<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Files;
use Aimeos\Cms\Import\JoomlaArticles;
use Aimeos\Cms\Import\JoomlaCategoryTree;
use Aimeos\Cms\Import\JoomlaChunks;
use Aimeos\Cms\Import\JoomlaDate;
use Aimeos\Cms\Import\JoomlaHost;
use Aimeos\Cms\Import\JoomlaImage;
use Aimeos\Cms\Import\JoomlaMenuItem;
use Aimeos\Cms\Import\JoomlaMenuLink;
use Aimeos\Cms\Import\JoomlaOrigin;
use Aimeos\Cms\Import\JoomlaReader;
use Aimeos\Cms\Import\JoomlaRow;
use Aimeos\Cms\Import\JoomlaWiring;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;


class JoomlaImport extends BaseImport
{
    use ImportsJoomla;
    use ImportsJoomlaArticles;
    use ImportsJoomlaHome;
    use ImportsJoomlaMenu;
    use ImportsJoomlaVanished;


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

    /** Modification time of articles and translations without known time, they count as changed */
    protected const UNKNOWN_TIME = PHP_INT_MAX;

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
        if( ( $fileBase = JoomlaOrigin::baseUrl( $this->fileBase ) ) !== $this->fileBase )
        {
            $this->warn( "Using {$fileBase} for --file-base without scheme." );
            $this->fileBase = $fileBase;
        }

        $host = new JoomlaHost(
            warn: $this->warn( ... ),
            warnOnce: fn( string $msg ) => $this->warnOnce( $msg, $msg ),
            // texts, tags and field values of articles which weren't prefetched are only reported in the debug output
            debug: fn( string $msg ) => $this->line( $msg, null, 'vvv' ),
            image: $this->imageFile( ... ),
            pageData: $this->pageData( ... ),
            timezone: $timezone
        );
        $reader = new JoomlaReader( DB::connection( $this->connection ), $this->stringOption( 'prefix' ), $host->warn );
        $origin = new JoomlaOrigin( $this->domain, $this->fileBase, !empty( $this->option( 'suffix' ) ) );
        $this->wiring = JoomlaWiring::read( $reader, $host, $origin, $this->stringOption( 'menu' ), (array) $this->option( 'language' ), $this->lang );

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
        $this->run = $this->wiring->run( $articles, $this->isImported( $root ) );
        // the modification times are needed by prefetch() and unchangedSince() before anything is imported
        $this->changed = $this->since !== null
            ? $this->changedTimes( $articles, $this->scheduledTimes( $reader, $this->since, $articles ) )
            : [];

        $this->importContent( $root );

        return $this->summary();
    }


    /**
     * Sets the time of the --since option, dates without time zone are in the time zone of the Joomla site.
     *
     * The dates in the Joomla database are in UTC, so the time is compared with them as timestamp.
     *
     * @param \DateTimeZone|null $timezone Time zone of the Joomla site or NULL for the one of the application
     * @return bool TRUE if the option is valid or not given, FALSE if not
     */
    protected function sinceOption( ?\DateTimeZone $timezone = null ): bool
    {
        if( !parent::sinceOption() ) {
            return false;
        }

        if( $this->since === null || $timezone === null ) {
            return true;
        }

        try {
            // dates validated by the parent are valid for DateTimeImmutable too because both use the same parser
            $this->since = ( new \DateTimeImmutable( $this->stringOption( 'since' ), $timezone ) )->getTimestamp();
        } catch( \Exception ) {
            $this->error( 'Invalid date for --since: ' . $this->stringOption( 'since' ) );
            return false;
        }

        return true;
    }


    /**
     * Shows the failed imports including the ones of categories, menu items and redirects and their number at the end.
     *
     * @return int Exit code of the command, Command::FAILURE if anything failed
     */
    protected function summary(): int
    {
        if( $this->failures )
        {
            $this->error( $this->failed . ' imports failed:' );

            foreach( $this->failures as $failure ) {
                $this->error( '  ' . $failure );
            }

            if( $this->failed > count( $this->failures ) ) {
                $this->error( '  ... and ' . ( $this->failed - count( $this->failures ) ) . ' more' );
            }
        }

        $this->info( "Finished with {$this->failed} failures." );

        return $this->failed > 0 ? self::FAILURE : self::SUCCESS;
    }


    /**
     * Returns the time zone of the Joomla site from the --timezone option.
     *
     * @return \DateTimeZone|null Time zone, UTC without option or NULL if the option is invalid
     */
    protected function timezoneOption(): ?\DateTimeZone
    {
        try {
            return new \DateTimeZone( $this->stringOption( 'timezone' ) ?: JoomlaDate::TIMEZONE );
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
        if( ( $path = JoomlaImage::path( $path ) ) === '' ) {
            return null;
        }

        $name = basename( parse_url( $path, PHP_URL_PATH ) ?: 'image' );

        return $this->createFile( Files::mime( $path ), $alt ?: $name, $this->resolveFile( $path ) );
    }


    /**
     * Imports the home pages, the menu items with their categories and articles and the remaining articles below their categories.
     */
    protected function importContent( PageView $root ): void
    {
        $articles = $this->run->articles;

        // the home menu item, also one of another menu, is imported first, so menu items showing the same article or category use the root page
        $homeImported = false;

        if( $home = $this->run->home->item )
        {
            // the articles of the home menu items of the other languages are shown by the variants of the root page
            $this->prefetch( array_filter( [$this->run->home->linkedArticleId()] ), $root );
            $homeImported = $this->importHome( $home, $root );
        }

        // the articles and menu items are split into chunks whose texts are fetched at once
        $chunks = new JoomlaChunks( $articles->translations, static::TEXT_SIZE );
        $this->importMenu( $root, $chunks, $homeImported );

        $order = $this->articleOrder( $this->wiring->site->categories, $articles->categoryIds );

        foreach( $chunks->articles( $order ) as $chunk )
        {
            // the articles, the texts of the articles and their translations and the pages of the articles are fetched per chunk
            $this->prefetch( $chunk->keys()->all() );

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
     * Returns the last modification times of the articles including their translations.
     *
     * The publishing dates passed since the --since date are changes too, articles without known modification time stay changed.
     * The article of the root page is rendered in every run, so the articles of the home menu items of the other languages
     * shown by the variants of the root page aren't included.
     *
     * @param JoomlaArticles $articles Imported articles with the modification times of the articles and translations
     * @param array<int|string, int> $scheduled Publishing dates passed since the --since date by article ID, see scheduledTimes()
     * @return array<int|string, int> Latest modification times by article ID, unknown ones are the newest
     */
    protected function changedTimes( JoomlaArticles $articles, array $scheduled = [] ): array
    {
        $changed = [];
        $times = $articles->times;

        // articles and translations without known modification time are changed anyway, the publishing dates don't change that
        foreach( $scheduled as $id => $time ) {
            $times[$id] = max( $times[$id] ?? static::UNKNOWN_TIME, $time );
        }

        foreach( array_keys( $articles->categoryIds ) as $id )
        {
            $latest = $times[$id] ?? static::UNKNOWN_TIME;

            foreach( $articles->translations[$id] ?? [] as $tid ) {
                $latest = max( $latest, $times[$tid] ?? static::UNKNOWN_TIME );
            }

            $changed[$id] = $latest;
        }

        return $changed;
    }


    /**
     * Returns the publishing dates of the articles and the menu items linking to them which passed since the date.
     *
     * Joomla doesn't change the articles and menu items when they are published or expire, so the pages of
     * articles whose publishing dates passed since the date must be imported again. Dates in the future aren't
     * changes yet, they are found by the next import after they passed. The dates of the menu items linking to
     * a category and the modification times of the categories count for the imported articles and translations of
     * the category and its subcategories because Joomla shows the articles without own menu item using the menu
     * items and the access, state, name and alias of the categories decide about the access and the URLs of the
     * articles. The dates of other menu items like URLs or aliases aren't used, pages whose URL path changed
     * because of them aren't found by their previous path and are rendered anyway.
     *
     * @param JoomlaReader $reader Reader for the Joomla database
     * @param int $since Timestamp of the --since date
     * @param JoomlaArticles $articles Imported articles and translations whose categories are checked
     * @return array<int|string, int> Latest passed publishing or category modification date as timestamp by article ID
     */
    protected function scheduledTimes( JoomlaReader $reader, int $since, JoomlaArticles $articles ): array
    {
        // the same time as used by the access checks of the articles and menu items
        $now = $this->wiring->site->access->now;
        $times = $reader->scheduled( $since, $now );
        $categoryTimes = $this->modifiedCategories( $since, $now );

        // the menu items linking to the articles and categories decide about their URLs, titles and options
        foreach( $reader->menuItems() as $item )
        {
            $latest = null;

            foreach( [$item->publish_up ?? null, $item->publish_down ?? null] as $date )
            {
                if( ( $time = JoomlaDate::time( $date ) ) !== null && $time >= $since && $time <= $now ) {
                    $latest = max( $latest ?? $time, $time );
                }
            }

            if( $latest === null ) {
                continue;
            }

            if( ( $id = JoomlaMenuItem::article( $item ) ) !== null ) {
                $times[$id] = max( $times[$id] ?? $latest, $latest );
            } elseif( ( $catId = JoomlaMenuItem::category( $item ) ) !== null ) {
                $categoryTimes[$catId] = max( $categoryTimes[$catId] ?? $latest, $latest );
            }
        }

        if( $categoryTimes === [] ) {
            return $times;
        }

        // the latest date of each category and its parents, computed once per category
        $latest = [];

        foreach( $this->wiring->site->categories->keys() as $catId )
        {
            $lineage = JoomlaCategoryTree::lineage( $this->wiring->site->categories, (int) $catId )->categories;

            if( ( $time = max( [0, ...array_intersect_key( $categoryTimes, $lineage )] ) ) > 0 ) {
                $latest[$catId] = $time;
            }
        }

        // the translations are in categories of their own languages, so the articles are fetched by category
        foreach( $latest ? $reader->categoryArticles( array_keys( $latest ) ) : [] as $id => $catId )
        {
            if( isset( $articles->visible[$id] ) ) {
                $times[$id] = max( $times[$id] ?? $latest[$catId], $latest[$catId] );
            }
        }

        return $times;
    }


    /**
     * Returns the modification times of the categories changed since the date.
     *
     * Joomla updates the modification time when a category is saved, e.g. if its access level, state, title,
     * alias or parent changed, but not the ones of its articles and subcategories. Like the publishing dates,
     * times in the future aren't changes yet, they are found by the next import after they passed.
     *
     * @param int $since Timestamp of the --since date
     * @param int $now Timestamp of the current time used by the access checks
     * @return array<int|string, int> Modification times as timestamp by category ID
     */
    protected function modifiedCategories( int $since, int $now ): array
    {
        $times = [];

        foreach( $this->wiring->site->categories as $catId => $category )
        {
            if( ( $time = JoomlaDate::time( $category->modified_time ?? null ) ) !== null && $time >= $since && $time <= $now ) {
                $times[$catId] = $time;
            }
        }

        return $times;
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


    /**
     * Fetches the articles and their translations, their texts, the pages of the articles and their variants at once.
     *
     * The fetched rows replace the ones of the previous chunk. The pages are fetched before the texts because
     * only the unchanged articles with a page of a previous import and unchanged names don't need their texts.
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @param PageView|null $root Root page if the article of the home menu item is imported next, the texts of the
     *  articles of the home menu items of the other languages are fetched too because the variants of the root page show them
     */
    protected function prefetch( array $ids, ?PageView $root = null ): void
    {
        // the texts of kept articles aren't needed
        $rendered = $this->prefetchArticles( $ids );
        $homes = $root ? $this->otherHomeArticles() : [];

        $this->prefetchTexts( array_values( array_unique( [...$this->run->withTranslations( $rendered ), ...$homes] ) ) );
    }


    /**
     * Fetches the pending articles and their translations and pages and decides which of them are kept, see prefetchKept().
     *
     * Used by the import and the dry run, so both make the same decisions. Only the import fetches the texts afterwards.
     *
     * @param array<int|string> $ids IDs of the articles imported next
     * @return list<int|string> IDs of the pending articles which are rendered, i.e. not kept
     */
    protected function prefetchArticles( array $ids ): array
    {
        // the translations are needed by saveTranslations() if the article is rendered
        $ids = array_values( array_filter( $ids, $this->pending( ... ) ) );
        $this->run->prefetch( $ids );

        return array_values( array_diff( $ids, $this->prefetchKept( $ids ) ) );
    }


    /**
     * Fetches the pages and variants of the fetched articles and keeps the pages of the unchanged ones, see keepsArticle().
     *
     * Decides once per chunk which articles aren't rendered again, run->kept() is TRUE for them afterwards. The pages
     * of the articles, the variants of the pages with translations and the URLs of the missing or moved variants used
     * by other pages are fetched at once before, the variants are needed to compare the names and URL paths of the
     * translations and by saveTranslations() for the rendered articles. The article of the root page is always rendered
     * because the pages don't store which article the root page shows, the root page only gets a new version if its
     * content changed. Its own URL path isn't used then.
     *
     * @param array<int|string> $ids IDs of the fetched articles
     * @return list<int|string> IDs of the kept articles
     */
    protected function prefetchKept( array $ids ): array
    {
        $slugs = $this->articleSlugs( array_filter( $ids, fn( $id ) => !$this->run->home->isArticle( $id ) ) );

        $this->prefetchPages( array_values( $slugs ) );
        $this->prefetchArticleVariants( $slugs );

        $pages = [];

        foreach( $slugs as $id => $slug )
        {
            if( $page = $this->prefetchedPages[trim( $slug, '/' )] ?? null ) {
                $pages[$id] = $page;
            }
        }

        // without --since, all articles are rendered again, so the keep checks and their queries aren't needed
        if( $this->since === null )
        {
            $this->run->keep( [] );
            return [];
        }

        $this->prefetchTranslationUrls( $pages );
        $kept = [];

        foreach( $pages as $id => $page )
        {
            if( ( $article = $this->run->row( $id ) ) && $this->keepsArticle( $article, $page ) ) {
                $kept[] = $id;
            }
        }

        $this->run->keep( $kept );
        return $kept;
    }


    /**
     * Returns the IDs of the articles Joomla shows for the home menu items of the other languages.
     *
     * @return list<int> Article IDs
     */
    protected function otherHomeArticles(): array
    {
        $ids = array_map( JoomlaMenuItem::article( ... ), $this->wiring->homes->otherHomes() );
        return array_values( array_filter( $ids, fn( $id ) => $id !== null && isset( $this->run->articles->visible[$id] ) ) );
    }


    /**
     * Returns the slugs of the pages of the fetched articles.
     *
     * @param array<int|string> $ids IDs of the articles
     * @return array<int|string, string> Slugs by article ID
     */
    protected function articleSlugs( array $ids ): array
    {
        $slugs = [];

        foreach( $ids as $id )
        {
            if( $article = $this->run->row( $id ) ) {
                $slugs[$id] = $this->run->urls->articlePath( $article );
            }
        }

        return $slugs;
    }


    /**
     * Fetches the variants of the existing pages of the articles with translations at once.
     *
     * The variants are compared with the names and URL paths of the translations by keepsArticle() and needed by saveTranslations()
     * for the rendered articles.
     *
     * @param array<int|string, string> $slugs Slugs of the pages of the articles by article ID
     */
    protected function prefetchArticleVariants( array $slugs ): void
    {
        $pageIds = [];

        foreach( $slugs as $id => $slug )
        {
            if( empty( $this->run->articles->translations[$id] ) ) {
                continue;
            }

            if( $page = $this->prefetchedPages[trim( $slug, '/' )] ?? null ) {
                $pageIds[] = (string) $page->id;
            }
        }

        $this->prefetchVariants( $pageIds );
    }


    /**
     * Fetches the texts, tags and field values of the articles and the files of their images at once.
     *
     * @param array<int|string> $ids IDs of the articles and translations
     */
    protected function prefetchTexts( array $ids ): void
    {
        $this->prefetchFiles( array_map( $this->resolveFile( ... ), $this->run->prefetchTexts( $ids ) ) );
    }


    /**
     * Tests if the article is imported and has no page yet.
     *
     * Articles handled without page, e.g. the article of the root page or other articles whose import failed, aren't pending.
     */
    protected function pending( int|string $id ): bool
    {
        return isset( $this->run->articles->categoryIds[$id] ) && !$this->createdPages->has( $id ) && !$this->run->handled( $id );
    }


    /**
     * Returns the fetched article if it's imported and has no page yet.
     *
     * @return \stdClass|null Article or NULL if it isn't imported, has a page already or doesn't exist any more
     * @throws \LogicException If the pending article wasn't prefetched for the current chunk
     */
    protected function pendingArticle( int|string $id ): ?\stdClass
    {
        return $this->pending( $id ) ? $this->run->article( $id ) : null;
    }


    /**
     * Imports an article within a transaction and logs the result.
     *
     * Articles whose import failed are handled, so other menu items and the category loop don't try and report them again.
     *
     * @return PageView|null Created, updated or unchanged page or NULL on failure
     */
    protected function tryImportArticle( \stdClass $article, PageView $parent ): ?PageView
    {
        $page = $this->transaction( JoomlaRow::label( $article ), fn() => $this->importArticle( $article, $parent ) );

        if( $page === null ) {
            $this->run->markHandled( $article->id );
        }

        return $page;
    }


    /**
     * Prints a dry run summary.
     *
     * Lists the articles which would be rendered, i.e. without the ones whose pages are kept with the --since option,
     * and the menu items and redirects which would be imported, also without the menu items of the kept articles.
     * The root page is read but not created and the same decisions are made as by an import using the same chunks
     * and prefetchArticles(), only the texts of the articles aren't fetched.
     *
     * @param JoomlaReader $reader Reader for the Joomla database
     * @param JoomlaArticles $articles Imported articles in the order Joomla uses
     */
    protected function printDryRun( JoomlaReader $reader, JoomlaArticles $articles ): void
    {
        // an import creates the root page if there's none, so the article of the home menu item is imported into it
        $root = Pages::root( $this->domain );
        $this->run = $this->wiring->run( $articles, !$root || $this->isImported( $root ) );
        $this->changed = $this->since !== null
            ? $this->changedTimes( $articles, $this->scheduledTimes( $reader, $this->since, $articles ) )
            : [];

        // the articles are listed in the order the import uses and fetched in the same chunks by the same prefetch as by importContent()
        $chunks = new JoomlaChunks( $articles->translations, static::TEXT_SIZE );
        $kept = [];

        foreach( $chunks->articles( $this->articleOrder( $this->wiring->site->categories, $articles->categoryIds ) ) as $chunk )
        {
            $this->prefetchArticles( $chunk->keys()->all() );

            foreach( $chunk->keys() as $id )
            {
                // kept articles are remembered for the menu items, run->kept() only knows the ones of the current chunk
                if( $this->run->kept( $id ) )
                {
                    $kept[$id] = true;
                    continue;
                }

                // the article of the root page is listed with the home menu item, see importArticleHome()
                if( !$this->run->home->isArticle( $id ) && ( $article = $this->run->row( $id ) ) )
                {
                    $featured = !empty( $article->featured ) ? ' [featured]' : '';
                    $this->line( '  ' . JoomlaRow::label( $article ) . ' (' . JoomlaRow::aliasOrSlug( $article ) . "){$featured}" );
                }
            }
        }

        // like importContent(), the home menu item, also one of another menu, is imported first and only once
        $home = $this->run->home->item;
        $homeRoot = $home ? $this->updatesHomeRoot( $home, !$root || $this->isImported( $root ) ) : null;

        if( $home && $homeRoot ) {
            $this->line( '  Menu item ' . JoomlaRow::label( $home ) . ' (root page)' );
        }

        // menu items linking to an article imported before reuse its page, the article of the root page uses the root page,
        // and the pages of the kept articles aren't rendered again like the kept articles aren't listed
        $articleIds = $kept + ( $homeRoot !== null && ( $id = $this->run->home->articleId ) !== null ? [$id => true] : [] );

        foreach( $this->wiring->menu->tree() as $node )
        {
            if( !( $homeRoot !== null && $this->run->home->is( $node->item ) ) && $this->importsMenuItem( $node->item, $articleIds ) ) {
                $this->line( '  Menu item ' . JoomlaRow::label( $node->item ) );
            }
        }

        // the same redirects as by importRedirects(), the ones whose URL paths are used by pages were skipped before
        $this->skippedRedirects();

        foreach( $this->run->urls->redirects() as $redirect ) {
            $this->line( "  Redirect /{$redirect->path} -> {$this->redirectTarget( $redirect )}" );
        }

        // the pages of previous imports whose Joomla records aren't imported any more would be disabled
        $this->printVanished();

        $this->info( 'Dry run complete. No changes were made.' );
    }


    /**
     * Tests if an import updates the root page for the home menu item, see importHome().
     *
     * @param bool $rootImported TRUE if the root page is created or was imported before and wasn't changed since
     * @return bool|null TRUE if the root page is updated, FALSE if the root page is used without changes or
     *  NULL if the home menu item doesn't use the root page and is imported like the other menu items
     */
    protected function updatesHomeRoot( \stdClass $menuItem, bool $rootImported ): ?bool
    {
        $link = JoomlaMenuLink::of( $menuItem );

        // only the category Joomla shows on the home page uses the root page, see importCategoryHome()
        if( $link->kind === JoomlaMenuLink::KIND_CATEGORY && $link->id ) {
            return $this->wiring->categoryItems->homeCatId() === $link->id ? $rootImported : null;
        }

        // other home pages like the featured articles only update root pages which were imported before
        if( !( $id = $link->article() ) ) {
            return $rootImported;
        }

        // the article is only imported into the root page if it was decided by the run, see importArticleHome()
        return isset( $this->run->articles->categoryIds[$id] ) && $this->run->home->isArticle( $id ) ? true : null;
    }


    /**
     * Tests if an import creates or updates a page for the menu item or imports its article, see importMenuItem().
     *
     * The home menu item using the root page is tested by updatesHomeRoot() instead. Menu items linking to an article
     * whose page was imported by an earlier menu item or is kept with the --since option only reuse that page, so they
     * aren't listed.
     *
     * @param array<int|string, bool> $articleIds IDs of the articles imported by earlier menu items or kept, the article of the menu item is added
     */
    protected function importsMenuItem( \stdClass $menuItem, array &$articleIds = [] ): bool
    {
        $link = JoomlaMenuLink::of( $menuItem );
        $articleId = $link->article();
        $catId = $link->category();
        $site = $this->wiring->site;

        return match( true ) {
            $link->kind === JoomlaMenuLink::KIND_FOLDER || ( $link->kind === JoomlaMenuLink::KIND_CATEGORIES && $link->id === null ) => true,
            $link->kind === JoomlaMenuLink::KIND_LINK => $this->run->links->menuItemLink( $menuItem ) !== null,
            $site->access->restriction( $menuItem ) !== null => false,
            // the article is remembered, so the later menu items linking to it aren't listed
            $articleId !== null => isset( $this->run->articles->categoryIds[$articleId] ) && !isset( $articleIds[$articleId] )
                && ( $articleIds[$articleId] = true ),
            $catId !== null => $site->category( $catId ) !== null && $this->wiring->categoryItems->hasMenuPage( $menuItem->id ),
            default => false,
        };
    }
}
