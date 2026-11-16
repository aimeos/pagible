<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Joomla article Joomla shows with the menu item Joomla uses for it and its language, collected by JoomlaIndex.
 */
final readonly class JoomlaShownArticle
{
    /**
     * Initializes the object.
     *
     * @param \stdClass $row Article with the index columns read by JoomlaReader::index()
     * @param \stdClass|null $item Menu item whose URL, title and options Joomla uses for the article or NULL if there's none
     * @param string $tag Joomla language tag of the article
     */
    public function __construct(
        public \stdClass $row,
        public ?\stdClass $item,
        public string $tag
    ) {
    }
}
