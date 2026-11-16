<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Creates the objects importing a Joomla site, built once per run.
 *
 * The objects building the Joomla routes and deciding which articles, categories and menu items are imported
 * don't depend on the imported articles and are created at once. The objects assigning the URLs and building
 * the pages depend on the imported articles and the article of the root page and are created by run(), which
 * selects the article of the root page and reads the redirects, or by assemble() from the given ones.
 */
final class JoomlaWiring
{
    /** Builds the Joomla routes of the languages */
    public readonly JoomlaRouter $router;
    /** Menu items of the imported menu */
    public readonly JoomlaMenu $menu;
    /** Decides which categories get own pages and returns the menu items showing them */
    public readonly JoomlaCategoryItems $categoryItems;
    /** Selects the home menu items of the imported and the other languages */
    public readonly JoomlaHomes $homes;
    /** Domains, routes and menu items the URLs of the imported pages are built from, its paths are the ones Joomla uses */
    public readonly JoomlaUrlContext $urlContext;
    /** Decides which articles, categories and menu items are imported as pages or translations */
    protected readonly JoomlaIndex $index;
    /** @var Collection<int, \stdClass> Menu items of the imported menu ordered by their position */
    protected readonly Collection $menuItems;
    /** @var Collection<array-key, \stdClass> Menu items of all menus by ID ordered by their position used for the routes, home pages and links */
    protected readonly Collection $allItems;


    /**
     * Initializes the object and creates the objects building the Joomla routes.
     *
     * Joomla builds the URLs from the menu items of all menus, the home menu items can be ones of other menus too.
     *
     * @param JoomlaReader $reader Reader for the Joomla database
     * @param JoomlaHost $host Functions and settings of the import command
     * @param JoomlaOrigin $origin Domain of the imported pages and the URLs of the Joomla site
     * @param JoomlaSite $site Settings of the Joomla site
     * @param Collection<int, \stdClass> $menuItems Menu items of the imported menu ordered by their position
     * @param Collection<array-key, \stdClass> $allItems Menu items of all menus by ID ordered by their position used for the routes, home pages and links
     */
    public function __construct(
        protected readonly JoomlaReader $reader,
        protected readonly JoomlaHost $host,
        protected readonly JoomlaOrigin $origin,
        public readonly JoomlaSite $site,
        Collection $menuItems,
        Collection $allItems
    ) {
        $all = $allItems->values();

        $this->allItems = $allItems->keyBy( 'id' );
        $this->menuItems = $menuItems;
        $this->router = new JoomlaRouter( $site, $all->filter( JoomlaMenuItem::routing( ... ) )->values() );
        $this->menu = new JoomlaMenu( $menuItems, $site->access );
        $this->homes = new JoomlaHomes( $site, $reader, $all );
        $this->categoryItems = new JoomlaCategoryItems( $site, $this->router, $this->menu, $this->allItems, $this->homes->home() );
        $paths = new JoomlaPaths( $this->router, $this->categoryItems );
        $domains = new JoomlaLanguageDomains( $site->languages, $origin );
        $this->urlContext = new JoomlaUrlContext( $domains, $paths, $this->router, $this->categoryItems, $this->menu );
        $this->index = new JoomlaIndex( $site, $reader, $paths, $host->warn );
    }


    /**
     * Reads the settings of the Joomla site and the menu items of the imported menu.
     *
     * Only the published menu items Joomla shows on the site of the imported language are imported.
     *
     * @param JoomlaReader $reader Reader for the Joomla database
     * @param JoomlaHost $host Functions and settings of the import command
     * @param JoomlaOrigin $origin Domain of the imported pages and the URLs of the Joomla site
     * @param string $menuType Joomla menu type, e.g. "mainmenu"
     * @param array<int|string, mixed> $options Joomla language tags imported as translations with their codes and optional URLs like "de-DE:de"
     * @param string $lang Language code of the imported pages
     */
    public static function read( JoomlaReader $reader, JoomlaHost $host, JoomlaOrigin $origin, string $menuType,
        array $options = [], string $lang = 'en' ): self
    {
        $site = JoomlaSite::read( $reader, $options, $lang );
        $all = $reader->menuItems();
        $menu = fn( $item ) => (string) ( $item->menutype ?? '' ) === $menuType && $site->onSite( JoomlaRow::language( $item ) );

        return new self( $reader, $host, $origin, $site, $all->filter( $menu )->values(), $all );
    }


    /**
     * Returns the imported articles with their categories, translations, menu items and URL paths.
     *
     * @param bool $withTimes TRUE to return the modification times of the articles and translations
     */
    public function articles( bool $withTimes = false ): JoomlaArticles
    {
        return $this->index->articles( $withTimes );
    }


    /**
     * Returns the visible categories which aren't translations and the translated categories and menu items.
     */
    public function folders(): JoomlaFolders
    {
        return $this->index->folders( $this->menuItems );
    }


    /**
     * Creates the data of the run with the objects assigning the URLs and building the content of the pages.
     *
     * The article of the root page is decided before the URLs are assigned because they depend on it.
     *
     * @param JoomlaArticles $articles Imported articles with the URL paths Joomla uses for them and their translations
     * @param bool $rootImported TRUE if the root page was created by the import, so the article of the home menu item can replace its content
     */
    public function run( JoomlaArticles $articles, bool $rootImported ): JoomlaRun
    {
        $home = $this->homes->root( $articles, $rootImported );
        $redirects = JoomlaRedirects::all( $this->reader, $this->site->plugins, $this->host->warn, $this->origin );

        return $this->assemble( articles: $articles, folders: $this->folders(), home: $home, redirects: $redirects );
    }


    /**
     * Creates the data of the run from the given articles, folders, root page article and redirects.
     *
     * Used by run() after reading the root page article and the redirects. It's public so tests can build the
     * objects of a run from their own rows without database, call it with named arguments.
     *
     * @param JoomlaArticles $articles Imported articles with the URL paths Joomla uses for them and their translations
     * @param JoomlaFolders $folders Visible categories which aren't translations and the translated categories and menu items grouped by ID
     * @param JoomlaHomeRoot $home Home menu item and the article imported into the root page the URLs depend on
     * @param iterable<\stdClass> $redirects Joomla redirects of the enabled "Redirect" plugin
     */
    public function assemble( JoomlaArticles $articles, JoomlaFolders $folders, JoomlaHomeRoot $home, iterable $redirects ): JoomlaRun
    {
        // links can point to any published menu item of the site, the same ones the routes and home pages are built from
        $linker = fn( JoomlaUrls $urls ) => new JoomlaLinks( $this->site, $this->allItems, $urls, $this->router, $this->menu, $this->origin );
        // the assignment resolves the targets of the redirects with the same links, so only the imported ones claim their paths
        $assignment = new JoomlaUrlAssignment( $this->urlContext, $this->origin, fn( JoomlaAssignedUrls $assigned ) => $linker( new JoomlaUrls( $this->urlContext, $assigned ) ) );
        $urls = new JoomlaUrls( $this->urlContext, $assignment->assign( $articles, $folders, $home, $redirects, $this->homes->otherHomes() ) );
        $links = $linker( $urls );

        // texts, tags and field values of articles which weren't prefetched are only reported in the debug output
        $extras = new JoomlaExtras( $this->reader, $this->site->access, $this->site->plugins, $this->site->categories, $this->host->debug );
        $texts = new JoomlaTexts( $this->reader, $this->origin, $this->host->debug );
        $content = $this->content( $links, $extras );
        $pages = new JoomlaPages( $this->site, $content, $articles, $links, $texts, $this->host );

        return new JoomlaRun(
            reader: $this->reader,
            articles: $articles,
            folders: $folders,
            home: $home,
            urls: $urls,
            links: $links,
            extras: $extras,
            texts: $texts,
            content: $content,
            pages: $pages
        );
    }


    /**
     * Creates the renderer of the custom fields, Joomla 3 renders them in the legacy way.
     *
     * @param iterable<\stdClass> $fields Fields ordered by their position
     */
    protected function fields( iterable $fields ): JoomlaFields
    {
        $types = new JoomlaFieldTypes( $this->origin, $this->host->timezone, $this->site->version );
        return new JoomlaFields( $fields, $this->site->plugins, new JoomlaFieldLookups( $this->reader ), $types );
    }


    /**
     * Creates the builder of the content elements with the custom fields, the modules and the content plugins.
     *
     * @param JoomlaLinks $links Resolves the Joomla links to the URL paths of the pages
     * @param JoomlaExtras $extras Tags and custom field values of the articles and categories
     */
    protected function content( JoomlaLinks $links, JoomlaExtras $extras ): JoomlaContent
    {
        $modules = new JoomlaModulePreparer( $this->site, new JoomlaModules( $this->site, $this->reader, $this->host->warnOnce ) );
        $articleFields = $this->fields( $extras->shownFields( JoomlaFields::ARTICLE_CONTEXT ) );
        $categoryFields = $this->fields( $extras->shownFields( JoomlaFields::CATEGORY_CONTEXT ) );
        $fields = new JoomlaCustomFields( $this->site, $extras, $modules, $articleFields, $categoryFields );
        $image = $this->host->image;
        $origin = $this->origin;

        // images of the HTML are imported like the ones of the articles and categories, with their entities decoded
        // and the same trimmed sources as prefetched by JoomlaHtml::sources(), so the prefetched files are found
        // inline images aren't files and are removed like by the other importers because they can be very large
        $html = new JoomlaHtml( function( string $tag ) use ( $image, $origin ) {
            $source = JoomlaHtml::imageSource( $tag );
            return $source ? $image( $origin->fileSource( $source['src'] ), $source['alt'] ) : null;
        }, $this->host->warnOnce );

        return new JoomlaContent(
            site: $this->site,
            html: $html,
            links: $links,
            extras: $extras,
            fields: $fields,
            modules: $modules
        );
    }
}
