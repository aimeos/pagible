<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Associated Joomla articles, categories or menu items grouped as translations by Languages::group().
 */
final readonly class TranslationGroups
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, list<int|string>> $translations IDs of the translations by ID of their source
     * @param array<string, string> $unknown Language tags of the skipped translations in unknown languages as keys and values
     * @param array<int|string, array{lang: string, tag: string, used: int|string}> $duplicates Language code and tag of the skipped
     *  translations whose association has another translation in the same language code and the ID of the used one by ID
     */
    public function __construct(
        public array $translations = [],
        public array $unknown = [],
        public array $duplicates = []
    ) {
    }
}
