<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Objects UrlAssignment and Urls build the URLs of the imported pages from, created once per import.
 *
 * They don't depend on the imported articles, so the same ones are used for assigning the URL paths and returning the URLs.
 */
final readonly class UrlContext
{
    /**
     * Initializes the object.
     *
     * @param LanguageDomains $domains Domains and home page URLs of the imported languages
     * @param Paths $paths Builds the URL paths Joomla uses for the articles, categories and menu items
     * @param Router $router Returns the Joomla routes with the menu items of the categories
     * @param CategoryItems $categoryItems Decides which categories get own pages
     * @param Menu $menu Menu items of the imported menu
     */
    public function __construct(
        public LanguageDomains $domains,
        public Paths $paths,
        public Router $router,
        public CategoryItems $categoryItems,
        public Menu $menu
    ) {
    }
}
