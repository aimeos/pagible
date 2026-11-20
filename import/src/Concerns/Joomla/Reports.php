<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Import\Pages;


/**
 * Reports the Joomla articles, menu items and redirects of dry runs, the failures are shown by Commands::summary().
 *
 * Requires the Shared, Changes, Home, Menu, Redirects and Vanished traits used by the same class, the methods
 * of JoomlaImport it calls like startRun() and articleChunks() are declared by Shared.
 */
trait Reports
{
    /**
     * Prints a dry run summary.
     *
     * Lists the articles which would be rendered, i.e. without the ones whose pages are kept with the --since option,
     * and the menu items and redirects which would be imported, also without the menu items of the kept articles.
     * The root page is read but not created and the same decisions are made as by an import using prefetchArticles(),
     * homeMode() and menuItemMode(), only the texts of the articles aren't fetched.
     *
     * @param Joomla\Reader $reader Reader for the Joomla database
     * @param Joomla\Articles $articles Imported articles in the order Joomla uses
     */
    protected function printDryRun( Joomla\Reader $reader, Joomla\Articles $articles ): void
    {
        // an import creates the root page if there's none, so the article of the home menu item is imported into it
        $root = Pages::root( $this->domain );
        $this->startRun( $reader, $articles, $root );

        // the articles are listed in the order and chunks of the article loop of importContent(), the import fetches the articles
        // of the menu items in the chunks of the menu before, but prefetchArticles() decides about each article on its own
        $kept = [];

        foreach( $this->articleChunks( $this->chunks( $articles ), $articles ) as $chunk )
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
                    $this->line( '  ' . Joomla\Row::label( $article ) . ' (' . Joomla\Row::aliasOrSlug( $article ) . "){$featured}" );
                }
            }
        }

        // like importContent(), the home menu item, also one of another menu, is imported first and only once
        $home = $this->run->home->item;
        $homeRoot = $home ? $this->updatesHomeRoot( $home, $this->updatesRoot( $root ) ) : null;

        if( $home && $homeRoot ) {
            $this->line( '  Menu item ' . Joomla\Row::label( $home ) . ' (root page)' );
        }

        // menu items linking to an article imported before reuse its page, the article of the root page uses the root page,
        // and the pages of the kept articles aren't rendered again like the kept articles aren't listed
        $articleIds = $kept + ( $homeRoot !== null && ( $id = $this->run->home->articleId ) !== null ? [$id => true] : [] );

        foreach( $this->wiring->menu->tree() as $node )
        {
            if( !( $homeRoot !== null && $this->run->home->is( $node->item ) ) && $this->importsMenuItem( $node->item, $articleIds ) ) {
                $this->line( '  Menu item ' . Joomla\Row::label( $node->item ) );
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
        return match( $this->homeMode( Joomla\MenuLink::of( $menuItem ) ) ) {
            // only root pages which were imported before are updated, see updateHomeRoot()
            Joomla\HomeMode::Category, Joomla\HomeMode::Root => $rootImported,
            // the article is only imported into the root page if the run decided so, see importArticleHome()
            Joomla\HomeMode::Article => true,
            null => null,
        };
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
    protected function importsMenuItem( \stdClass $menuItem, array &$articleIds ): bool
    {
        $link = Joomla\MenuLink::of( $menuItem );

        return match( $this->menuItemMode( $link, $this->wiring->site->access->restriction( $menuItem ) ) ) {
            Joomla\MenuItemMode::Heading => true,
            Joomla\MenuItemMode::Link => $this->run->links->menuItemLink( $menuItem ) !== null,
            Joomla\MenuItemMode::Article => $this->importsArticle( (int) $link->article(), $articleIds ),
            Joomla\MenuItemMode::Category => $this->wiring->site->category( (int) $link->category() ) !== null
                && $this->wiring->categoryItems->hasMenuPage( $menuItem->id ),
            Joomla\MenuItemMode::Restricted, Joomla\MenuItemMode::Unsupported => false,
        };
    }


    /**
     * Tests if an import imports the article of a menu item, which isn't the case if an earlier menu item imported it already.
     *
     * The article is remembered, so the later menu items linking to it aren't listed.
     *
     * @param int $id ID of the article linked by the menu item
     * @param array<int|string, bool> $articleIds IDs of the articles imported by earlier menu items or kept, the article is added
     */
    protected function importsArticle( int $id, array &$articleIds ): bool
    {
        if( !isset( $this->run->articles->categoryIds[$id] ) || isset( $articleIds[$id] ) ) {
            return false;
        }

        return $articleIds[$id] = true;
    }
}
