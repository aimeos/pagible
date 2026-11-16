<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Objects JoomlaUrlAssignment and JoomlaUrls build the URLs of the imported pages from, created once per import.
 *
 * They don't depend on the imported articles, so the same ones are used for assigning the URL paths and returning the URLs.
 */
final readonly class JoomlaUrlContext
{
    /**
     * Initializes the object.
     *
     * @param JoomlaLanguageDomains $domains Domains and home page URLs of the imported languages
     * @param JoomlaPaths $paths Builds the URL paths Joomla uses for the articles, categories and menu items
     * @param JoomlaRouter $router Returns the Joomla routes with the menu items of the categories
     * @param JoomlaCategoryItems $categoryItems Decides which categories get own pages
     * @param JoomlaMenu $menu Menu items of the imported menu
     */
    public function __construct(
        public JoomlaLanguageDomains $domains,
        public JoomlaPaths $paths,
        public JoomlaRouter $router,
        public JoomlaCategoryItems $categoryItems,
        public JoomlaMenu $menu
    ) {
    }
}
