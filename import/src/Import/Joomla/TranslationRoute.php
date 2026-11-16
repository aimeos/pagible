<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * URL path Joomla uses for a translated article and the language tag of the translation.
 *
 * Collected by Index::articles() and claimed in the domain of the language by UrlAssignment.
 */
final readonly class TranslationRoute
{
    /**
     * Initializes the object.
     *
     * @param string $path URL path Joomla uses for the translated article in the site of its language
     * @param string $tag Joomla language tag of the translation, e.g. "de-DE"
     */
    public function __construct(
        public string $path,
        public string $tag
    ) {
    }
}
