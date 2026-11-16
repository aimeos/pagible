<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\JoomlaChunks;
use Aimeos\Cms\Import\JoomlaKind;
use Aimeos\Cms\Import\JoomlaMenuItem;
use Aimeos\Cms\Import\JoomlaMenuLink;
use Aimeos\Cms\Import\JoomlaMenuNode;
use Aimeos\Cms\Import\JoomlaKeptRedirect;
use Aimeos\Cms\Import\JoomlaRow;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Joomla menu items of the imported menu and the Joomla redirects as pages.
 *
 * Requires the ImportsJoomla trait used by the same class for the objects of the run and the shared methods.
 */
trait ImportsJoomlaMenu
{
    /**
     * Imports the menu items in tree order, so new pages are added at the end of the page tree.
     *
     * The menu items are imported in chunks whose texts and pages are fetched at once. Only the
     * pages of the ancestors with children of the current menu item are kept as parents of the next menu items.
     *
     * @param JoomlaChunks $chunks Splits the menu items into chunks whose texts are fetched at once
     * @param bool $homeImported TRUE if the home menu item uses the root page, so it isn't imported again
     */
    protected function importMenu( PageView $root, JoomlaChunks $chunks, bool $homeImported ): void
    {
        $parents = [];
        $menu = $this->wiring->menu;
        $home = $this->run->home;

        $articleId = fn( JoomlaMenuNode $node ) => JoomlaMenuItem::article( $node->item );

        foreach( $chunks->items( $menu->tree(), $articleId ) as $chunk )
        {
            $this->prefetch( $chunk->map( $articleId )->filter()->values()->all() );

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
     * Imports a menu item and returns the page used as parent of its children.
     *
     * The children of menu items which aren't imported are imported below the parent page.
     */
    protected function importMenuItem( \stdClass $menuItem, PageView $parent, PageView $root ): PageView
    {
        // the ID is the one of the article or the category, also of the category whose subcategories are listed
        $link = JoomlaMenuLink::of( $menuItem );
        $articleId = $link->article();
        $catId = $link->category();

        $reason = $this->wiring->site->access->restriction( $menuItem );

        return match( true ) {
            // the list of all categories has no category itself
            $link->kind === JoomlaMenuLink::KIND_FOLDER || ( $link->kind === JoomlaMenuLink::KIND_CATEGORIES && $link->id === null )
                => $this->importHeadingItem( $menuItem, $parent, $reason ),
            // URL menu items can link to articles and categories too
            $link->kind === JoomlaMenuLink::KIND_LINK => $this->importLinkItem( $menuItem, $parent, $reason ),
            $reason !== null => $this->restrictedItem( $menuItem, $parent, $reason ),
            $articleId !== null => $this->importArticleItem( $articleId, $parent, $root ),
            $catId !== null => $this->importCategoryItem( $menuItem, $catId, $parent ),
            default => $this->unsupportedView( $menuItem, $parent ),
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
        $this->notice( 'Skipped menu item', JoomlaRow::label( $menuItem ), $reason );

        // like public category menu items without own page, the child items are imported below the existing category page
        $catId = JoomlaMenuLink::of( $menuItem )->category();
        $pageId = $catId !== null ? $this->run->categoryPage( $catId ) : null;

        return $pageId !== null ? $this->slimPage( $pageId ) : $parent;
    }


    /**
     * Warns that the view or type of the menu item isn't supported and returns the parent page.
     */
    protected function unsupportedView( \stdClass $menuItem, PageView $parent ): PageView
    {
        $view = JoomlaMenuItem::view( $menuItem );
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
     * Category pages contain the description and the image of the category like Joomla shows them above the articles.
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

        return $this->savedPage( $saved, $menuItem, JoomlaKind::MENU, $slug, $reason );
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
     * Only the menu item selected by JoomlaCategoryItems gets the page of the category, so the URL paths are only
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
            $this->notice( 'Skipped menu item', JoomlaRow::label( $menuItem ), "its category isn't shown by Joomla" );
            return $parent;
        }

        if( !$this->wiring->categoryItems->hasMenuPage( $menuItem->id ) )
        {
            if( ( $pageId = $run->categoryPage( $catId ) ) !== null ) {
                return $this->slimPage( $pageId );
            }

            $this->notice( 'Skipped menu item', JoomlaRow::label( $menuItem ),
                'its category is shown by another menu item, its child items are imported below the parent page' );
            return $parent;
        }

        // the menu item itself is public, only its category can restrict it
        $reason = $site->access->itemCategoryRestriction( $catId );
        // the articles of the category are imported below the root page if the page can't be saved
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
        $this->notice( 'Skipped menu item', JoomlaRow::label( $menuItem ),
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
            $what = JoomlaMenuItem::type( $menuItem ) === JoomlaMenuItem::TYPE_ALIAS ? 'target page' : 'URL';
            return $this->unsupported( $menuItem, $parent, $what );
        }

        return $this->transaction( $this->itemLabel( $menuItem ), function() use ( $menuItem, $parent, $reason, $to, $run ) {
            $slug = $run->urls->menuPath( $menuItem );
            $data = $run->pages->linkPage( $menuItem, $slug, $to, $reason )->data;
            $saved = $this->linkPage( 'Skipped menu item', JoomlaRow::label( $menuItem ) . " (/{$slug})", $slug, $data, $parent );
            return $saved ? $this->savedPage( $saved, $menuItem, JoomlaKind::MENU, $slug, $reason ) : null;
        } ) ?? $parent;
    }


    /**
     * Creates a link page or updates the one of a previous import and warns if its URL is used by another page.
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
     * Imports the redirects of the Joomla "Redirect" component as hidden link pages below the root page.
     *
     * Redirects whose URL paths are used by imported pages or whose targets aren't supported were skipped by
     * JoomlaUrlAssignment already, which checks the targets the same way, see JoomlaLinks::redirectTarget().
     */
    protected function importRedirects( PageView $root ): void
    {
        $tally = new Tally();
        $run = $this->run;

        $this->skippedRedirects();

        foreach( array_chunk( $run->urls->redirects(), static::PREFETCH_SIZE ) as $chunk )
        {
            // the pages using the URL paths are fetched at once instead of one query per redirect
            $this->prefetchPages( array_map( fn( JoomlaKeptRedirect $redirect ) => $redirect->path, $chunk ) );

            foreach( $chunk as $redirect )
            {
                $path = $redirect->path;
                $data = $run->pages->redirectData( $path, $this->redirectTarget( $redirect ) );
                $saved = $this->transaction( "redirect /{$path}", fn() => $this->linkPage( 'Skipped redirect', "/{$path}", $path, $data, $root ) );

                $tally->add( $saved['status'] ?? null );
            }
        }

        $this->subtotals( $tally, 'redirects' );
    }


    /**
     * Warns about the Joomla redirects skipped because their targets aren't supported, see JoomlaUrls::unsupportedRedirects().
     */
    protected function skippedRedirects(): void
    {
        foreach( $this->run->urls->unsupportedRedirects() as $redirect ) {
            $this->notice( 'Skipped redirect', "/{$redirect->path}", "its target {$redirect->url} isn't supported" );
        }
    }


    /**
     * Returns the URL the kept Joomla redirect is imported with, see JoomlaLinks::redirectTarget().
     *
     * JoomlaUrlAssignment keeps only the redirects whose targets resolve by the same check, see JoomlaUrls::redirects().
     *
     * @return string Target URL
     * @throws \LogicException If the target of the kept redirect doesn't resolve
     */
    protected function redirectTarget( JoomlaKeptRedirect $redirect ): string
    {
        return $this->run->links->redirectTarget( $redirect )
            ?? throw new \LogicException( "Target {$redirect->url} of the kept redirect /{$redirect->path} doesn't resolve" );
    }


    /**
     * Returns the label of the menu item used in the messages of failed imports, e.g. "menu item [5] About".
     */
    protected function itemLabel( \stdClass $menuItem ): string
    {
        return 'menu item ' . JoomlaRow::label( $menuItem );
    }
}
