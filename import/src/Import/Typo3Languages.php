<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Maps the TYPO3 language UIDs to the languages of the pages and collects the URL paths of the page translations.
 */
class Typo3Languages
{
    /** @var array<int, array{lang: string, domain: string}> Languages by TYPO3 language UID */
    protected array $languages;


    /**
     * Initializes the languages from the sys_language table and the --language options, which take precedence.
     *
     * The optional base of the options is the URL like "https://example.de/" if the
     * translations are on a separate domain.
     *
     * @param array<int, array{lang: string, domain: string}> $languages Languages of the sys_language table by UID
     * @param array<mixed> $options Values of the --language option like "1:de[:url]"
     * @throws \InvalidArgumentException If an option value is invalid
     */
    public function __construct( array $languages, array $options )
    {
        foreach( Variants::options( $options, 'TYPO3', '/^0*[1-9][0-9]*$/D' ) as $uid => $language ) {
            $languages[(int) $uid] = $language;
        }

        $this->languages = $languages;
    }


    /**
     * Returns the language of the TYPO3 language UID.
     *
     * @return array{lang: string, domain: string}|null Language or NULL if unknown
     */
    public function get( int $uid ): ?array
    {
        return $this->languages[$uid] ?? null;
    }


    /**
     * Returns the UIDs of the languages whose translations are imported.
     *
     * @param string $lang Language code of the pages in default language, translations in this language aren't imported
     * @return array<int, int> TYPO3 language UIDs
     */
    public function imported( string $lang ): array
    {
        return array_keys( array_filter( $this->languages, fn( $language ) => $language['lang'] !== $lang ) );
    }


    /**
     * Returns the URL paths of the page translations of the known languages by the UID of the page in default language and the language UID.
     *
     * Only the first translation of a page in each language is used like by the import.
     *
     * @param iterable<\stdClass> $pages TYPO3 page translations with "l10n_parent", "sys_language_uid" and "slug" ordered by UID
     * @return array{slugs: array<int, array<int, string>>, count: int, unknown: array<int, int>}
     *  URL paths by page and language UID, the number of translations in the known languages and the UIDs of the unknown languages of skipped translations
     */
    public function translations( iterable $pages ): array
    {
        $slugs = $unknown = [];
        $count = 0;

        foreach( $pages as $page )
        {
            $uid = (int) $page->sys_language_uid;

            if( !isset( $this->languages[$uid] ) )
            {
                $unknown[$uid] = true;
                continue;
            }

            $slugs[(int) $page->l10n_parent][$uid] ??= (string) ( $page->slug ?? '' );
            $count++;
        }

        return ['slugs' => $slugs, 'count' => $count, 'unknown' => array_keys( $unknown )];
    }
}
