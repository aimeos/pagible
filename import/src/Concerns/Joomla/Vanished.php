<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Joomla;


/**
 * Disables the pages of previous imports whose Joomla articles, categories or menu items aren't imported any more.
 *
 * Pages of articles which were deleted, trashed, unpublished, moved to a category which isn't imported or aren't
 * selected any more get no URL path of the current import, see \Aimeos\Cms\Concerns\Vanished.
 *
 * Requires the Shared trait used by the same class for the objects of the run.
 */
trait Vanished
{
    use \Aimeos\Cms\Concerns\Vanished;

    /**
     * Returns the URL paths of the pages of the imported articles, the visible categories and the menu items of the imported menu.
     *
     * Contains the paths of all articles selected for the import, also of the ones kept by the --since option whose rows
     * aren't fetched. The paths of categories without imported articles and of restricted menu items are included too, so
     * pages of Joomla categories and menu items which still exist aren't disabled.
     *
     * @return array<string, bool> URL paths without slashes as keys
     */
    protected function importedPaths(): array
    {
        $urls = $this->run->urls;
        $categoryItems = $this->wiring->categoryItems;
        $paths = [];

        foreach( array_keys( $this->run->articles->categoryIds ) as $id ) {
            $paths[] = $urls->articleUrl( $id );
        }

        foreach( $this->wiring->site->categories->keys() as $catId )
        {
            if( $category = $categoryItems->category( (int) $catId ) ) {
                $paths[] = $urls->categoryPath( $category );
            }

            $paths[] = $urls->categoryUrl( (int) $catId );
        }

        foreach( $this->wiring->menu->tree() as $node ) {
            $paths[] = $urls->menuPath( $node->item );
        }

        $known = [];

        foreach( $paths as $path )
        {
            // absolute URLs are the ones of variants in languages with own domains
            if( $path !== null && !str_contains( $path, '://' ) ) {
                $known[trim( $path, '/' )] = true;
            }
        }

        return $known;
    }


    /**
     * Returns why the pages of previous imports are disabled.
     */
    protected function vanishedReason(): string
    {
        return "its Joomla article, category or menu item isn't imported any more, e.g. it was deleted, trashed or unpublished";
    }


    /**
     * Returns the warning shown after disabling the pages of previous imports.
     *
     * @param int $count Number of disabled pages
     */
    protected function vanishedMessage( int $count ): string
    {
        return "Disabled {$count} pages of previous imports whose Joomla articles, categories or menu items aren't imported any more.";
    }
}
