<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Joomla articles Joomla shows with their categories, menu items, URL paths and languages, collected by Index.
 *
 * The imported articles are derived by articles() once the associated articles are grouped as translations.
 *
 * @phpstan-import-type ArticleRoutes from Articles
 */
final readonly class ShownArticles
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, int> $categories Category IDs by article ID
     * @param array<int|string, \stdClass> $items Menu items Joomla uses by article ID
     * @param array<int|string, RoutePath> $routes URL paths Joomla uses or NULL and aliases of the articles which are neither associated nor foreign by article ID,
     *  not keyed by Kind like the ArticleRoutes of Articles::$routes
     * @param array<int|string, ShownArticle> $associated Associated articles with their menu items and languages by article ID
     * @param array<int|string, bool> $foreign Articles which aren't shown on the site of the imported language as keys
     * @param array<int|string, int> $times Modification times by article ID
     */
    public function __construct(
        public array $categories = [],
        public array $items = [],
        public array $routes = [],
        public array $associated = [],
        public array $foreign = [],
        public array $times = []
    ) {
    }


    /**
     * Returns the Joomla language tags (not content tags) of the associated articles.
     *
     * @return array<int|string, string> Joomla language tags by article ID
     */
    public function languageTags(): array
    {
        return array_map( fn( ShownArticle $shown ) => $shown->tag, $this->associated );
    }


    /**
     * Returns the categories of the articles imported as pages, which are neither translations nor shown on other sites only.
     *
     * @param array<int|string, bool> $translated IDs of the articles imported as translations as keys
     * @return array<int|string, int> Category IDs by article ID
     */
    public function imported( array $translated ): array
    {
        return array_diff_key( $this->categories, $translated, $this->foreign );
    }


    /**
     * Returns the articles imported as pages or translations.
     *
     * @param array<int|string, int> $categoryIds Category IDs of the articles imported as pages by article ID, see imported()
     * @param array<int|string, list<int|string>> $translations IDs of the translations by article ID
     * @param ArticleRoutes $routes URL paths of the imported articles and translations
     */
    public function articles( array $categoryIds, array $translations, array $routes ): Articles
    {
        return new Articles(
            categoryIds: $categoryIds,
            translations: $translations,
            times: $this->times,
            items: $this->items,
            visible: array_fill_keys( array_keys( $this->categories ), true ),
            routes: $routes
        );
    }
}
