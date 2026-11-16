<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Joomla categories and menu items imported as folder pages or translations, selected by JoomlaIndex::folders().
 */
final readonly class JoomlaFolders
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, \stdClass> $categories Visible categories which aren't translations by ID
     * @param array<int|string, list<\stdClass>> $categoryTranslations Translated categories by category ID
     * @param array<int|string, list<\stdClass>> $menuTranslations Translated menu items by menu item ID
     */
    public function __construct(
        public array $categories = [],
        public array $categoryTranslations = [],
        public array $menuTranslations = []
    ) {
    }
}
