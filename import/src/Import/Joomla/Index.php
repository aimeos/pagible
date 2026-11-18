<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;


/**
 * Decides which Joomla articles, categories and menu items are imported as pages or translations.
 *
 * The articles Joomla shows are collected with their categories and the menu items Joomla uses for them,
 * the associated articles, categories and menu items in other languages are grouped as translations.
 *
 * @phpstan-import-type ArticleRoutes from Articles
 */
class Index
{
    /** Warning about the skipped articles in other languages, gets the number of articles */
    protected const FOREIGN = 'Skipping %d Joomla articles in other languages without associated article in the import language '
        . 'because Joomla only shows them on the sites of their languages.';
    /** Kind of the translated rows in the warnings for articles */
    protected const KIND_ARTICLE = 'article';
    /** Kind of the translated rows in the warnings for categories */
    protected const KIND_CATEGORY = 'category';
    /** Kind of the translated rows in the warnings for menu items */
    protected const KIND_MENU_ITEM = 'menu item';

    /** Warning about the translations in unknown languages, gets the kind of the rows and the list of language tags */
    protected const UNKNOWN_LANGUAGES = 'Skipping %s translations of unknown or unpublished Joomla languages [%s]'
        . ', add --language=<tag>:<code> to import them.';

    /** Warning about the translations skipped because another one of their association has the same language code, gets the kind of the rows and the list of IDs */
    protected const DUPLICATES = 'Skipping Joomla %s translations [%s] because another translation of their association uses the same language code'
        . ', map their languages to own codes with --language.';

    /** @var array<string, array<string, string>> Unknown language tags warned about already by kind of the rows, so each kind reports them once */
    protected array $reported = [];


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the access checks and the languages the translations are grouped by
     * @param Reader $reader Reader for the articles and menu items
     * @param Paths $paths Builds the URL paths Joomla uses for the articles
     * @param \Closure $warn Prints a warning, gets the message as parameter
     */
    public function __construct(
        protected Site $site,
        protected Reader $reader,
        protected Paths $paths,
        protected \Closure $warn
    ) {
    }


    /**
     * Reads the categories of all published articles, groups the associated articles as translations and returns their URL paths.
     *
     * Only the IDs and categories of the articles and the IDs of the translations are kept
     * because there can be many articles. The other columns are fetched per chunk later.
     * Articles of unpublished or trashed categories are skipped like Joomla doesn't show them.
     * If the language filter of Joomla is enabled, articles in other languages without
     * associated article in the import language are skipped too.
     *
     * @param bool $withTimes TRUE to return the modification times of the articles and translations
     * @return Articles Categories, translations, modification times, menu items and URL paths of the articles
     */
    public function articles( bool $withTimes = false ): Articles
    {
        $associations = $this->associations( Reader::ARTICLE_ASSOCIATIONS );
        $shown = $this->collect( $withTimes, $this->idSet( $associations ) );

        $tags = $shown->languageTags();
        $translations = $this->translations( self::KIND_ARTICLE, $tags, $associations );
        $translated = $this->idSet( $translations );
        $articles = $shown->imported( $translated );

        if( $foreign = count( array_diff_key( $shown->foreign, $translated ) ) ) {
            ( $this->warn )( sprintf( self::FOREIGN, $foreign ) );
        }

        $associatedRoutes = $translationRoutes = [];

        // the associated articles are routed as pages or as translations once it's known which they are
        foreach( $shown->associated as $id => $article )
        {
            if( isset( $translated[$id] ) ) {
                $translationRoutes[$id] = $this->paths->articleTranslationRoute( $article->row, $article->item )->path();
            } elseif( isset( $articles[$id] ) ) {
                $associatedRoutes[$id] = $this->paths->articleRoute( $article->row, $article->item );
            }
        }

        // the associated articles aren't in the routes collected before, so the keys don't overlap
        $routes = array_intersect_key( $shown->routes + $associatedRoutes, $articles );

        return $shown->articles(
            categoryIds: $articles,
            translations: $translations,
            routes: $this->routes( $routes, $translationRoutes, $tags )
        );
    }


    /**
     * Groups the associated categories and menu items as translations and warns about the unknown languages.
     *
     * The categories of all states are the ones of the site.
     *
     * @param Collection<int, \stdClass> $menuItems Menu items of the imported menu
     * @return Folders Visible categories which aren't translations and the translations by category and menu item ID
     */
    public function folders( Collection $menuItems ): Folders
    {
        $visible = $this->site->categories->reject( fn( $category ) => $this->site->access->categoryHidden( (int) $category->id ) )->sortKeys()->all();
        $categoryTranslations = $this->associated( self::KIND_CATEGORY, $this->associations( Reader::CATEGORY_ASSOCIATIONS ), $visible );

        // the associated menu items in other languages are mostly in other menus
        $items = $menuItems->keyBy( 'id' )->sortKeys()->all();
        $associations = $this->associations( Reader::MENU_ASSOCIATIONS );
        $otherIds = array_keys( array_diff_key( $this->idSet( $associations ), $items ) );
        $otherItems = $otherIds ? $this->reader->menuItems()->only( $otherIds )->all() : [];
        $translated = $this->idSet( array_map( fn( $rows ) => array_column( $rows, 'id' ), $categoryTranslations ) );

        return new Folders(
            categories: array_diff_key( $visible, $translated ),
            categoryTranslations: $categoryTranslations,
            menuTranslations: $this->associated( self::KIND_MENU_ITEM, $associations, $items + $otherItems, $items )
        );
    }


    /**
     * Collects the categories, menu items, URL paths and languages of the articles Joomla shows in one pass.
     *
     * @param bool $withTimes TRUE to collect the modification times of the articles too
     * @param array<int|string, bool> $associated IDs of the associated articles as keys
     */
    protected function collect( bool $withTimes, array $associated ): ShownArticles
    {
        $categories = $items = $routes = $rows = $foreign = $times = [];

        foreach( $this->shown( $withTimes ) as $article )
        {
            $row = $article->row;
            $id = $row->id;
            $categories[$id] = Article::catId( $row );

            if( $article->item ) {
                $items[$id] = $article->item;
            }

            if( !$this->site->onSite( $article->tag ) ) {
                $foreign[$id] = true;
            }

            // articles shown on other sites only are imported as translations if they are associated, otherwise never
            if( isset( $associated[$id] ) ) {
                $rows[$id] = $article;
            } elseif( !isset( $foreign[$id] ) ) {
                $routes[$id] = $this->paths->articleRoute( $row, $article->item );
            }

            // articles without modification or creation time are always changed
            if( $withTimes && ( $time = $this->modified( $row ) ) !== null ) {
                $times[$id] = $time;
            }
        }

        return new ShownArticles(
            categories: $categories,
            items: $items,
            routes: $routes,
            associated: $rows,
            foreign: $foreign,
            times: $times
        );
    }


    /**
     * Returns the published articles Joomla shows with the menu item Joomla uses for them and their language tag.
     *
     * Articles of unpublished or trashed categories are skipped like Joomla doesn't show them.
     *
     * @param bool $withTimes TRUE to fetch the modification times of the articles too
     * @return \Generator<int, ShownArticle> Articles with the menu item Joomla uses for them and their language
     */
    protected function shown( bool $withTimes ): \Generator
    {
        $items = $this->articleItems();

        foreach( $this->reader->index( $withTimes ) as $row )
        {
            $tag = Row::language( $row );
            $item = $this->articleItem( $row->id, $items[$row->id] ?? [], $tag );

            if( !$this->site->access->articleHidden( Article::catId( $row ), $item !== null ) ) {
                yield new ShownArticle( $row, $item, $tag );
            }
        }
    }


    /**
     * Returns the IDs of the associated articles, categories or menu items grouped by association key if Joomla uses them.
     *
     * Joomla only uses the associations if the item associations of the language filter plugin are enabled.
     *
     * @param string $context Joomla association context, e.g. "com_content.item"
     * @return array<int|string, list<int|string>>
     */
    protected function associations( string $context ): array
    {
        return $this->site->plugins->itemAssociations() ? $this->reader->associations( $context ) : [];
    }


    /**
     * Returns the menu items of all menus linking to the articles ordered by their position like Joomla uses them.
     *
     * Menu items guests can't see aren't used.
     *
     * @return array<int|string, list<\stdClass>> Menu items by article ID
     */
    protected function articleItems(): array
    {
        $items = [];

        // Joomla uses the URL of a menu item of any menu linking to the article instead of the URL of the article
        foreach( $this->reader->menuItems() as $item )
        {
            $id = MenuItem::article( $item );

            if( $id && $this->site->access->restriction( $item ) === null ) {
                $items[$id][] = $item;
            }
        }

        return $items;
    }


    /**
     * Returns the menu item whose URL, title and options Joomla uses for the article.
     *
     * Joomla uses the menu items in the language of the article and the ones for all languages. Articles for
     * all languages use the menu items in the imported language, in Joomla 3 only the ones for all languages.
     * Without language filter, Joomla only uses the menu items for all languages.
     *
     * @param int|string $id ID of the article
     * @param list<\stdClass> $items Menu items linking to the article ordered by their position
     * @param string $tag Joomla language tag of the article
     */
    protected function articleItem( int|string $id, array $items, string $tag ): ?\stdClass
    {
        $found = null;
        // new rules per article, so they only remember the menu items selected for the target of this article
        $rules = $this->site->menuRules();
        $target = MenuRules::target( MenuItem::VIEW_ARTICLE, $id );

        foreach( $items as $item )
        {
            if( !$this->site->itemShows( Row::language( $item ), $tag ) ) {
                continue;
            }

            // menu items in a specific language replace the ones selected before, so the last selected one is used
            if( $rules->take( $target, $item ) ) {
                $found = $item;
            }
        }

        return $found;
    }


    /**
     * Groups the associated articles, categories or menu items in other languages as translations and warns about the skipped ones.
     *
     * Each unknown language is only reported once per kind of rows, even if it's read several times. Translations skipped
     * because another translation of their association uses the same language code are reported by kind and ID.
     *
     * @param string $kind Kind of the rows in the warnings, one of the KIND_* constants
     * @param array<int|string, string> $tags Joomla language tags of the associated articles, categories or menu items by ID
     * @param array<int|string, list<int|string>> $associations IDs of the associated rows grouped by association key
     * @param array<int|string, mixed> $pages Rows imported as pages themselves, which aren't translations of other rows
     * @return array<int|string, list<int|string>> IDs of the translations by ID of the source
     */
    protected function translations( string $kind, array $tags, array $associations, array $pages = [] ): array
    {
        $groups = $this->site->languages->group( $tags, $associations, $pages );

        if( ( $unknown = array_diff_key( $groups->unknown, $this->reported[$kind] ?? [] ) ) !== [] )
        {
            $this->reported[$kind] = ( $this->reported[$kind] ?? [] ) + $unknown;
            ( $this->warn )( sprintf( self::UNKNOWN_LANGUAGES, $kind, implode( ', ', $unknown ) ) );
        }

        if( $groups->duplicates !== [] )
        {
            $list = array_map( fn( $id, $dup ) => "{$id} ({$dup['tag']}, \"{$dup['lang']}\" used by {$dup['used']})",
                array_keys( $groups->duplicates ), $groups->duplicates );
            ( $this->warn )( sprintf( self::DUPLICATES, $kind, implode( ', ', $list ) ) );
        }

        return $groups->translations;
    }


    /**
     * Returns the URL paths of the articles and translations ordered by ID, so the paths stay the same in the next imports.
     *
     * @param array<int|string, RoutePath> $articles URL paths Joomla uses or NULL and aliases by article ID
     * @param array<int|string, string> $translations URL paths Joomla uses by ID of the translation
     * @param array<int|string, string> $tags Joomla language tags by ID of the translation
     * @return ArticleRoutes
     */
    protected function routes( array $articles, array $translations, array $tags ): array
    {
        $paths = [Kind::ARTICLE => $articles, Kind::ARTICLE_TRANSLATION => []];

        foreach( $translations as $id => $path ) {
            $paths[Kind::ARTICLE_TRANSLATION][$id] = new TranslationRoute( $path, $tags[$id] );
        }

        ksort( $paths[Kind::ARTICLE] );
        ksort( $paths[Kind::ARTICLE_TRANSLATION] );

        return $paths;
    }


    /**
     * Groups the associated categories or menu items in other languages as translations of the ones in the import language.
     *
     * @param string $kind Kind of the rows in the warnings, KIND_CATEGORY or KIND_MENU_ITEM
     * @param array<int|string, list<int|string>> $associations IDs of the associated rows grouped by association key
     * @param array<int|string, \stdClass> $rows Categories or menu items by ID
     * @param array<int|string, mixed> $pages Rows imported as pages themselves, which aren't translations of other rows
     * @return array<int|string, list<\stdClass>> Translations by ID of the category or menu item
     */
    protected function associated( string $kind, array $associations, array $rows, array $pages = [] ): array
    {
        $tags = array_map( Row::language( ... ), $rows );
        $result = [];

        // the rows imported as pages are skipped by the languages, so the next row in their language is the translation
        foreach( $this->translations( $kind, $tags, $associations, $pages ) as $id => $ids )
        {
            foreach( $ids as $tid ) {
                // the languages only group the IDs of the given rows, so each translation is one of them
                $result[$id][] = $rows[$tid] ?? throw new \LogicException( "No row for translation \"{$tid}\"" );
            }
        }

        return $result;
    }


    /**
     * Returns the IDs of the grouped associations or translations as keys for lookups.
     *
     * @param array<int|string, list<int|string>> $groups Lists of IDs
     * @return array<int|string, bool> TRUE by ID
     */
    protected function idSet( array $groups ): array
    {
        return array_fill_keys( Arr::flatten( $groups, 1 ), true );
    }


    /**
     * Returns the time when the article was modified last.
     *
     * @return int|null Timestamp or NULL if the article has no valid modification or creation time
     */
    protected function modified( \stdClass $article ): ?int
    {
        return Date::time( $article->modified ?? null, $article->created ?? null );
    }
}
