<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Support\Collection;


/**
 * Selects the Joomla home menu items shown on the home pages of the imported language and the other languages.
 *
 * The article of the home menu item of the site is imported into the root page if guests can see it, see root().
 */
class JoomlaHomes
{
    /** Why the article of the home menu item isn't imported into a root page which wasn't created by the import */
    protected const ROOT_EXISTS = 'the root page already exists';

    /** @var Collection<int, \stdClass> Published home menu items of all menus and languages within their publishing dates, including the ones restricted by access level */
    protected Collection $homes;

    /** @var array<string, \stdClass>|null Home menu items of the other languages by language tag, computed once by otherHomes() */
    protected ?array $otherHomes = null;


    /**
     * Initializes the object.
     *
     * @param JoomlaSite $site Settings of the Joomla site with the access checks of the home menu items and their articles
     * @param JoomlaReader $reader Reader for the article of the home menu item and its translations
     * @param Collection<int, \stdClass> $items Menu items of all menus, only the home menu items are used
     */
    public function __construct( protected JoomlaSite $site, protected JoomlaReader $reader, Collection $items )
    {
        // like the routes, Joomla doesn't show expired or scheduled home menu items but restricted ones show the login form
        $this->homes = $items->filter( fn( $item ) => JoomlaMenuItem::home( $item ) && $site->access->dateRestriction( $item ) === null )->values();
    }


    /**
     * Returns the home menu item of the site with the article imported into the root page and its translations.
     *
     * The article is only imported into the root page if it's imported, guests can see it on the root page
     * and the root page was created by the import.
     *
     * @param JoomlaArticles $articles Imported articles with their translations and menu items
     * @param bool $imported TRUE if the root page was created by the import, so its content can be replaced
     * @return JoomlaHomeRoot Home menu item with the article of the root page or why the article isn't used
     */
    public function root( JoomlaArticles $articles, bool $imported ): JoomlaHomeRoot
    {
        $item = $this->home();

        if( !$item || !( $id = JoomlaMenuItem::article( $item ) ) || !isset( $articles->categoryIds[$id] ) ) {
            return JoomlaHomeRoot::withoutArticle( $item );
        }

        $ids = $articles->translations[$id] ?? [];
        $rows = $this->reader->articles( [$id, ...$ids] );

        if( !( $article = $rows[$id] ?? null ) ) {
            return JoomlaHomeRoot::withoutArticle( $item );
        }

        // the home menu item shows the article instead of its own menu item
        if( ( $reason = $this->site->access->articleRestriction( $article, isset( $articles->items[$id] ), $item ) ) !== null ) {
            return JoomlaHomeRoot::withoutArticle( $item, $reason );
        }

        // the restriction is reported before the root page which was not created by the import
        if( !$imported ) {
            return JoomlaHomeRoot::withoutArticle( $item, self::ROOT_EXISTS );
        }

        return JoomlaHomeRoot::withArticle( $item, $id, JoomlaRow::pick( $rows, $ids ), $article );
    }


    /**
     * Returns the home menu item of the site which is shown on the home page, regardless of the imported menu.
     *
     * With language filter, Joomla uses the home menu item of the import language before the one for all languages.
     */
    public function home(): ?\stdClass
    {
        if( $this->site->languageFilter )
        {
            $own = fn( $item ) => !JoomlaRow::allLanguages( $item ) && $this->site->onSite( JoomlaRow::language( $item ) );
            $home = $this->homes->first( $own );

            if( $home ) {
                return $home;
            }
        }

        return $this->homes->first( JoomlaRow::allLanguages( ... ) );
    }


    /**
     * Returns the home menu items Joomla shows on the home pages of the sites of the other languages.
     *
     * The home menu items don't change during a run, so they are computed once.
     *
     * @return array<string, \stdClass> First home menu item by Joomla language tag of the imported translations, empty without language filter
     */
    public function otherHomes(): array
    {
        if( $this->otherHomes !== null ) {
            return $this->otherHomes;
        }

        $homes = [];

        if( !$this->site->languageFilter ) {
            return $this->otherHomes = $homes;
        }

        foreach( $this->homes as $item )
        {
            // home menu items for all languages are never ones of other languages
            if( $this->site->onOtherSite( $tag = JoomlaRow::language( $item ) ) ) {
                $homes[$tag] ??= $item;
            }
        }

        return $this->otherHomes = $homes;
    }
}
