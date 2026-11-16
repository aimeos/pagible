<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Joomla articles imported as pages or translations, selected by JoomlaIndex::articles().
 *
 * Only the IDs, category IDs, times and URL paths of the articles are kept because there can be many articles.
 *
 * Routes: routes of the articles (JoomlaKind::ARTICLE) and the translated articles (JoomlaKind::ARTICLE_TRANSLATION) by ID
 *
 * @phpstan-type Routes array{a: array<int|string, JoomlaRoutePath>, ta: array<int|string, JoomlaTranslationRoute>}
 */
final readonly class JoomlaArticles
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, int> $categoryIds Category IDs of the articles imported as pages by article ID ordered by their position in the categories
     * @param array<int|string, list<int|string>> $translations IDs of the translations by article ID
     * @param array<int|string, int> $times Modification times of the articles and translations by ID, only if requested
     * @param array<int|string, \stdClass> $items Menu items Joomla uses for the articles and translations by ID
     * @param array<int|string, bool> $visible IDs of all articles Joomla shows as keys, also the skipped ones and the translations
     * @param Routes $routes URL paths Joomla uses or NULL and aliases of the articles (JoomlaKind::ARTICLE) and URL paths and language tags
     *  of the translations (JoomlaKind::ARTICLE_TRANSLATION) by ID, both ordered by ID, so the paths stay the same in the next imports
     */
    public function __construct(
        public array $categoryIds,
        public array $translations,
        public array $times,
        public array $items,
        public array $visible,
        public array $routes
    ) {
    }
}
