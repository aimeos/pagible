<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Joomla menu items of the imported menu as pages.
 *
 * Requires the Shared trait used by the same class for the objects of the run and the shared methods.
 */
trait Menu
{
    /** What the warnings about menu items which aren't imported as pages start with */
    protected const SKIPPED_ITEM = 'Skipped menu item';


    /**
     * Imports the menu items in tree order, so new pages are added at the end of the page tree.
     *
     * The menu items are imported in chunks whose texts and pages are fetched at once. Only the
     * pages of the ancestors with children of the current menu item are kept as parents of the next menu items.
     *
     * @param Joomla\Chunks $chunks Splits the menu items into chunks whose texts are fetched at once
     * @param bool $homeImported TRUE if the home menu item uses the root page, so it isn't imported again
     */
    protected function importMenu( PageView $root, Joomla\Chunks $chunks, bool $homeImported ): void
    {
        $parents = [];
        $menu = $this->wiring->menu;
        $home = $this->run->home;

        $articleId = fn( Joomla\MenuNode $node ) => Joomla\MenuItem::article( $node->item );

        foreach( $chunks->items( $menu->tree(), $articleId ) as $chunk )
        {
            $this->prefetch( $chunk->map( $articleId )->filter()->values()->all() );
            $this->prefetchMenuFolders( $chunk->all() );

            foreach( $chunk as $pos => $node )
            {
                $menuItem = $node->item;

                // the menu items are in tree order, so the parents after the parent of the menu item have no more children
                while( ( $last = array_key_last( $parents ) ) !== null && ( $node->parent === null || $last > $node->parent ) ) {
                    unset( $parents[$last] );
                }

                // the home menu item used for the root page was imported before the menu
                if( $homeImported && $home->is( $menuItem ) ) {
                    $page = $root;
                } else {
                    $page = $this->importMenuItem( $menuItem, $node->parent !== null ? $this->slimPage( $parents[$node->parent] ) : $root, $root );
                }

                if( $menu->children( $menuItem->id ) ) {
                    $parents[$pos] = $this->slim( $page );
                }
            }
        }
    }


    /**
     * Fetches the pages, variants and images of the folder and link pages of the menu items imported next at once.
     *
     * Only the menu items importMenuItem() saves a folder or link page for are used, the pages of the articles
     * are fetched by prefetch().
     *
     * @param iterable<Joomla\MenuNode> $nodes Menu items imported next
     */
    protected function prefetchMenuFolders( iterable $nodes ): void
    {
        $run = $this->run;
        $site = $this->wiring->site;
        $categoryItems = $this->wiring->categoryItems;
        $categories = [];
        $slugs = [];

        foreach( $nodes as $node )
        {
            $menuItem = $node->item;

            // the home menu item uses the root page if it was imported before the menu
            if( $run->home->is( $menuItem ) ) {
                continue;
            }

            $link = Joomla\MenuLink::of( $menuItem );
            $mode = $this->menuItemMode( $link, $site->access->restriction( $menuItem ) );

            if( $mode === Joomla\MenuItemMode::Category )
            {
                // like importCategoryItem(), only the selected menu item of a shown category gets a page
                if( !( $category = $site->category( (int) $link->category() ) ) || !$categoryItems->hasMenuPage( $menuItem->id ) ) {
                    continue;
                }

                $categories[] = $category;
            }
            elseif( $mode !== Joomla\MenuItemMode::Heading && $mode !== Joomla\MenuItemMode::Link )
            {
                continue;
            }

            $slugs[$run->urls->menuPath( $menuItem )] = $run->menuTranslations( $menuItem ) !== [];
        }

        $this->prefetchFolders( $slugs, $run->categoryImagePaths( $categories ) );
    }


    /**
     * Imports a menu item and returns the page used as parent of its children.
     *
     * The children of menu items which aren't imported are imported below the parent page.
     */
    protected function importMenuItem( \stdClass $menuItem, PageView $parent, PageView $root ): PageView
    {
        // the ID is the one of the article or the category, also of the category whose subcategories are listed
        $link = Joomla\MenuLink::of( $menuItem );
        $reason = $this->wiring->site->access->restriction( $menuItem );

        return match( $this->menuItemMode( $link, $reason ) ) {
            Joomla\MenuItemMode::Heading => $this->importHeadingItem( $menuItem, $parent, $reason ),
            Joomla\MenuItemMode::Link => $this->importLinkItem( $menuItem, $parent, $reason ),
            Joomla\MenuItemMode::Restricted => $this->restrictedItem( $menuItem, $parent, (string) $reason ),
            Joomla\MenuItemMode::Article => $this->importArticleItem( (int) $link->article(), $parent, $root ),
            Joomla\MenuItemMode::Category => $this->importCategoryItem( $menuItem, (int) $link->category(), $parent ),
            Joomla\MenuItemMode::Unsupported => $this->unsupportedView( $menuItem, $parent ),
        };
    }


    /**
     * Returns how the menu item is imported, used by the import and the dry run, so both make the same decision.
     *
     * @param Joomla\MenuLink $link Link of the menu item
     * @param string|null $reason Why the menu item isn't visible for guests or NULL if it's public
     * @return Joomla\MenuItemMode How the menu item is imported
     */
    protected function menuItemMode( Joomla\MenuLink $link, ?string $reason ): Joomla\MenuItemMode
    {
        return match( true ) {
            // the list of all categories has no category itself
            $link->kind === Joomla\MenuLink::KIND_FOLDER || ( $link->kind === Joomla\MenuLink::KIND_CATEGORIES && $link->id === null ) => Joomla\MenuItemMode::Heading,
            // URL menu items can link to articles and categories too
            $link->kind === Joomla\MenuLink::KIND_LINK => Joomla\MenuItemMode::Link,
            $reason !== null => Joomla\MenuItemMode::Restricted,
            $link->article() !== null => Joomla\MenuItemMode::Article,
            $link->category() !== null => Joomla\MenuItemMode::Category,
            default => Joomla\MenuItemMode::Unsupported,
        };
    }


    /**
     * Warns that the article or category of the menu item isn't visible for guests and returns the page of its child items.
     *
     * The article or category is imported below its parent category instead and is published if it's public itself.
     * Like for public category menu items without own page, the child items of category menu items are imported below
     * the page of the category if it exists already, below the parent page otherwise.
     *
     * @param string $reason Why the menu item isn't visible for guests
     */
    protected function restrictedItem( \stdClass $menuItem, PageView $parent, string $reason ): PageView
    {
        $this->notice( self::SKIPPED_ITEM, Joomla\Row::label( $menuItem ), $reason );

        // like public category menu items without own page, the child items are imported below the existing category page
        $catId = Joomla\MenuLink::of( $menuItem )->category();
        $pageId = $catId !== null ? $this->run->categoryPage( $catId ) : null;

        return $pageId !== null ? $this->slimPage( $pageId ) : $parent;
    }


    /**
     * Warns that the view or type of the menu item isn't supported and returns the parent page.
     */
    protected function unsupportedView( \stdClass $menuItem, PageView $parent ): PageView
    {
        $view = Joomla\MenuItem::view( $menuItem );
        return $this->unsupported( $menuItem, $parent, $view !== null ? "Joomla view \"{$view}\"" : 'type' );
    }


    /**
     * Imports a menu heading or a list of all categories as folder page.
     *
     * @param string|null $reason Why the menu item isn't visible for guests or NULL if it's public
     * @return PageView Folder page or the parent page on failure
     */
    protected function importHeadingItem( \stdClass $menuItem, PageView $parent, ?string $reason ): PageView
    {
        return $this->transaction( $this->itemLabel( $menuItem ), fn() => $this->createMenuFolder( $menuItem, $parent, $reason ) ) ?? $parent;
    }


    /**
     * Creates the folder page of a menu item with its translations or updates the one of a previous import.
     *
     * Menu headings and lists of all categories get a folder page without category, the pages of category menu items
     * contain the description and the image of their category like Joomla shows them above the articles.
     *
     * @param string|null $reason Why the menu item isn't visible for guests or NULL if it's public
     * @param \stdClass|null $category Category shown by the page or NULL for menu headings
     * @return PageView Saved page
     */
    protected function createMenuFolder( \stdClass $menuItem, PageView $parent, ?string $reason, ?\stdClass $category = null ): PageView
    {
        $run = $this->run;
        $slug = $run->urls->menuPath( $menuItem );
        $rendered = $run->pages->menuPage( $menuItem, $slug, $reason, $category );
        $saved = $this->savePage( $slug, $rendered->data, $rendered->content, $parent );

        return $this->savePageTranslations( $saved, $menuItem, Joomla\Kind::Menu, $slug, $reason );
    }


    /**
     * Imports the article of a menu item and returns the page used as parent of the child items.
     *
     * @param int $id ID of the article
     * @return PageView Page of the article, the page of an earlier menu item linking to the same article,
     *  the root page for the home article or the parent page if the article isn't imported
     */
    protected function importArticleItem( int $id, PageView $parent, PageView $root ): PageView
    {
        $run = $this->run;
        $article = $this->pendingArticle( $id );
        $page = $article ? $this->tryImportArticle( $article, $parent ) : null;

        if( $page )
        {
            $run->addArticlePage( $id, $this->slim( $page ) );
            return $page;
        }

        // the child items of other menu items linking to the same article are imported below its page
        if( $run->home->isArticle( $id ) ) {
            return $root;
        }

        return ( $pageId = $run->articlePage( $id ) ) !== null ? $this->slimPage( $pageId ) : $parent;
    }


    /**
     * Creates the page of a menu item for a category blog or list, the articles of the category are imported below.
     *
     * Only the menu item selected by Joomla\CategoryItems gets the page of the category, so the URL paths are only
     * claimed for created pages. Other menu items of the category use its page, e.g. the root page of the home
     * menu item, or the parent page if the menu item showing the category comes later in the menu.
     *
     * @param int $catId ID of the category shown by the menu item
     */
    protected function importCategoryItem( \stdClass $menuItem, int $catId, PageView $parent ): PageView
    {
        $run = $this->run;
        $site = $this->wiring->site;

        if( !( $category = $site->category( $catId ) ) )
        {
            $this->notice( self::SKIPPED_ITEM, Joomla\Row::label( $menuItem ), "its category isn't shown by Joomla" );
            return $parent;
        }

        if( !$this->wiring->categoryItems->hasMenuPage( $menuItem->id ) )
        {
            if( ( $pageId = $run->categoryPage( $catId ) ) !== null ) {
                return $this->slimPage( $pageId );
            }

            $this->notice( self::SKIPPED_ITEM, Joomla\Row::label( $menuItem ),
                'its category is shown by another menu item, its child items are imported below the parent page' );
            return $parent;
        }

        // the menu item itself is public, only its category can restrict it
        $reason = $site->access->itemCategoryRestriction( $catId );
        // if the page can't be saved, the child items are imported below the parent page and the articles of the category
        // below the category folder created by parentForCategory() because no page is remembered for the category
        $page = $this->transaction( $this->itemLabel( $menuItem ), fn() => $this->createMenuFolder( $menuItem, $parent, $reason, $category ) );

        if( $page ) {
            $run->addCategoryPage( $catId, $this->slim( $page ) );
        }

        return $page ?? $parent;
    }


    /**
     * Warns that the menu item isn't supported and returns the parent page its child items are imported below.
     *
     * @param string $what Unsupported part of the menu item, e.g. its type or URL
     */
    protected function unsupported( \stdClass $menuItem, PageView $parent, string $what ): PageView
    {
        $this->notice( self::SKIPPED_ITEM, Joomla\Row::label( $menuItem ),
            "its {$what} isn't supported, its child items are imported below the parent page" );
        return $parent;
    }


    /**
     * Creates the link page of a URL or alias menu item or updates the one of a previous import.
     *
     * @param string|null $reason Why the menu item isn't visible for guests or NULL if it's public
     */
    protected function importLinkItem( \stdClass $menuItem, PageView $parent, ?string $reason ): PageView
    {
        $run = $this->run;

        if( ( $to = $run->links->menuItemLink( $menuItem ) ) === null )
        {
            $what = Joomla\MenuItem::type( $menuItem ) === Joomla\MenuItem::TYPE_ALIAS ? 'target page' : 'URL';
            return $this->unsupported( $menuItem, $parent, $what );
        }

        return $this->transaction( $this->itemLabel( $menuItem ), function() use ( $menuItem, $parent, $reason, $to, $run ) {
            $slug = $run->urls->menuPath( $menuItem );
            $data = $run->pages->linkPage( $menuItem, $slug, $to, $reason )->data;
            $saved = $this->linkPage( self::SKIPPED_ITEM, Joomla\Row::label( $menuItem ) . " (/{$slug})", $slug, $data, $parent );
            return $saved ? $this->savePageTranslations( $saved, $menuItem, Joomla\Kind::Menu, $slug, $reason ) : null;
        } ) ?? $parent;
    }


    /**
     * Creates a link page or updates the one of a previous import and warns if its URL is used by another page.
     *
     * Also used for the redirects, see Redirects::importRedirects().
     *
     * @param string $what What is skipped if the URL is used, e.g. "Skipped menu item" or "Skipped redirect"
     * @param string $subject Label of the menu item or redirect used in the warning
     * @param array<string, mixed> $data Page data including the link target
     * @return array{page: PageView, status: Status}|null Page and its status or NULL if the URL is used by another page
     */
    protected function linkPage( string $what, string $subject, string $slug, array $data, PageView $parent ): ?array
    {
        if( $saved = $this->saveLink( $slug, $data, $parent ) ) {
            return $saved;
        }

        $this->notice( $what, $subject, 'URL is used by another page' );
        return null;
    }


    /**
     * Returns the label of the menu item used in the messages of failed imports, e.g. "menu item [5] About".
     */
    protected function itemLabel( \stdClass $menuItem ): string
    {
        return 'menu item ' . Joomla\Row::label( $menuItem );
    }
}
