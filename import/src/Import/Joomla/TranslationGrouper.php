<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Groups the associated Joomla articles, categories or menu items in other languages as translations of their source.
 */
class TranslationGrouper
{
    /**
     * Initializes the object.
     *
     * @param Languages $languages Maps the Joomla language tags to the languages of the pages
     */
    public function __construct( protected Languages $languages )
    {
    }


    /**
     * Groups associated articles, categories or menu items in other languages as translations of their source.
     *
     * The source of an association is the one in the import language or for all languages ("*").
     * Associations without a source are kept as pages. If several associated rows are in the same
     * language, only the one with the lowest ID is used as translation, the others aren't translations
     * and are imported as pages of their own like the rows of associations without a source. They are
     * returned as duplicates, so they can be reported, e.g. if "de-AT" and "de-CH" both get "de".
     * Rows which are imported as pages anyway, e.g. the menu items of the imported menu, are never
     * translations, so the next row in their language is used instead.
     *
     * @param array<int|string, string> $tags Language tags of the published associated articles, categories or menu items by ID
     * @param array<int|string, array<int, int|string>> $associations Associated IDs grouped by association
     * @param array<int|string, mixed> $pages IDs of the rows imported as pages themselves as keys, which aren't translations of other rows
     * @return TranslationGroups IDs of the translations by source ID, the unknown language tags and the duplicates of skipped translations
     */
    public function group( array $tags, array $associations, array $pages = [] ): TranslationGroups
    {
        $translations = [];
        $duplicates = [];
        $unknown = [];

        foreach( $associations as $ids )
        {
            $ids = array_filter( $ids, fn( $id ) => isset( $tags[$id] ) );
            sort( $ids );

            // the first one in the imported language or for all languages is the source of the translations,
            // the other ones in these languages aren't translations and stay pages of their own
            $sources = array_values( array_filter( $ids, fn( $id ) => $this->languages->source( $tags[$id] ) ) );

            if( $sources === [] ) {
                continue;
            }

            // rows imported as pages anyway are never translations, so the next row in their language is used instead
            $ids = array_values( array_filter( array_diff( $ids, $sources ), fn( $id ) => !isset( $pages[$id] ) ) );

            if( $ids = $this->translationIds( $ids, $tags, $unknown, $duplicates ) ) {
                $translations[$sources[0]] = [...$translations[$sources[0]] ?? [], ...$ids];
            }
        }

        return new TranslationGroups( $translations, $unknown, $duplicates );
    }


    /**
     * Returns the IDs of the translations of one association, the first one per language.
     *
     * Each language has one variant, so only the first translation in the language is used, the others are added
     * to the duplicates. Rows of unknown languages are skipped and their tags added to the unknown ones.
     *
     * @param list<int|string> $ids Sorted IDs of the associated rows which are neither sources nor imported as pages
     * @param array<int|string, string> $tags Language tags of the associated rows by ID
     * @param array<string, string> $unknown Unknown language tags as keys and values, extended by this method
     * @param array<int|string, array{lang: string, tag: string, used: int|string}> $duplicates Skipped translations by ID, extended by this method
     * @return list<int|string> IDs of the translations
     */
    protected function translationIds( array $ids, array $tags, array &$unknown, array &$duplicates ): array
    {
        $langs = [];

        foreach( $ids as $id )
        {
            $tag = $tags[$id];

            if( !( $language = $this->languages->language( $tag ) ) ) {
                $unknown[$tag] = $tag;
                continue;
            }

            if( isset( $langs[$language['lang']] ) )
            {
                $duplicates[$id] = ['lang' => $language['lang'], 'tag' => $tag, 'used' => $langs[$language['lang']]];
                continue;
            }

            $langs[$language['lang']] = $id;
        }

        return array_values( $langs );
    }
}
