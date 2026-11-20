<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Models\PageView;


/**
 * Imports the Joomla categories without menu item as folder pages the articles are imported below.
 *
 * Requires the Shared trait used by the same class for the objects of the run and the shared methods.
 */
trait Categories
{
    /**
     * Returns the page of the category and creates it including its parent categories if necessary.
     *
     * Top-level categories have no page unless a menu item links to them.
     */
    protected function parentForCategory( int $catId, PageView $root ): PageView
    {
        $run = $this->run;

        if( ( $id = $run->categoryPage( $catId ) ) !== null ) {
            return $this->slimPage( $id );
        }

        $categoryItems = $this->wiring->categoryItems;

        // articles with own menu item in categories with hidden parents are shown by Joomla 4+ but their categories aren't
        if( !( $category = $categoryItems->category( $catId ) ) ) {
            return $root;
        }

        $parent = $category->parent_id ? $this->parentForCategory( (int) $category->parent_id, $root ) : $root;
        $routes = $this->wiring->router->contentRoutes( Joomla\Row::language( $category ) );
        // category() already ruled out the menu items of the imported menu, so the own menu item of the category is one of another menu
        $other = $categoryItems->otherItem( $category );
        // the articles are imported below the parent if the page of the category can't be saved, the error is reported once
        $page = $this->transaction( 'category ' . Joomla\Row::label( $category ),
            fn() => $this->createCategoryFolder( $category, $routes, $other, $parent ) ) ?? $parent;
        $run->addCategoryPage( $catId, $this->slim( $page ) );

        return $page;
    }


    /**
     * Fetches the pages, variants and images of the categories without page yet the articles are imported below at once.
     *
     * The parent categories are included like parentForCategory() creates them, so the folders of the
     * articles imported next don't fetch their pages, variants and image files one by one.
     *
     * @param array<int|string> $catIds IDs of the categories of the articles imported next
     */
    protected function prefetchCategories( array $catIds ): void
    {
        $run = $this->run;
        $categoryItems = $this->wiring->categoryItems;
        $categories = [];

        foreach( array_unique( $catIds ) as $catId )
        {
            // the same checks as parentForCategory(), which stops at categories with page or without own folder
            while( $catId && !isset( $categories[$catId] ) && $run->categoryPage( (int) $catId ) === null && ( $category = $categoryItems->category( (int) $catId ) ) )
            {
                $categories[$catId] = $category;
                $catId = (int) $category->parent_id;
            }
        }

        $slugs = [];
        $rows = $categories;

        foreach( $categories as $category )
        {
            $translations = $run->categoryTranslations( $category );
            $slugs[$run->urls->categoryPath( $category )] = $translations !== [];
            array_push( $rows, ...$translations );
        }

        $this->prefetchFolders( $slugs, $run->categoryImagePaths( $rows ) );
    }


    /**
     * Creates the page of a category without menu item or updates the one of a previous import.
     *
     * Category pages contain the description and the image of the category like Joomla shows them above the articles.
     *
     * @param Joomla\Routes $routes Routes of the language of the category
     * @param \stdClass|null $item Menu item of another menu linking to the category or NULL if there's none
     * @return PageView Saved page
     */
    protected function createCategoryFolder( \stdClass $category, Joomla\Routes $routes, ?\stdClass $item, PageView $parent ): PageView
    {
        $slug = $this->run->urls->categoryPath( $category );
        $rendered = $this->run->pages->categoryPage( $category, $slug, $routes, $item );
        $saved = $this->savePage( $slug, $rendered->data, $rendered->content, $parent );

        return $this->savePageTranslations( $saved, $category, Joomla\Kind::Category, $slug, $rendered->reason );
    }
}
