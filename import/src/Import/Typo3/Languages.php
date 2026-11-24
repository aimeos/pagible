<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Variants;


/**
 * Maps the TYPO3 language UIDs to the languages of the pages and collects the URL paths of the page translations.
 */
class Languages
{
    /** @var array<int, array{lang: string, domain: string}> Languages by TYPO3 language UID */
    protected array $languages;

    /** @var array<int, array{lang: string, domain: string}> Languages of the --language options by TYPO3 language UID */
    protected array $options = [];


    /**
     * Initializes the languages from the sys_language table and the --language options, which take precedence.
     *
     * The optional base of the options is the URL like "https://example.de/" if the
     * translations are on a separate domain.
     *
     * @param array<int, array{lang: string, domain: string}> $languages Languages of the sys_language table by UID
     * @param array<mixed> $options Values of the --language option like "1:de[:url]"
     * @param array<int, array<int, array{lang: string, domain: string}>> $sites Languages of the site configurations by root page UID and language UID
     * @throws \InvalidArgumentException If an option value is invalid
     */
    public function __construct( array $languages, array $options, protected array $sites = [] )
    {
        foreach( Variants::options( $options, 'TYPO3', '/^0*[1-9][0-9]*$/D' ) as $uid => $language ) {
            $languages[(int) $uid] = $this->options[(int) $uid] = $language;
        }

        $this->languages = $languages;
    }


    /**
     * Returns the language of the TYPO3 language UID.
     *
     * The language UIDs of the site configurations are per site, the --language options apply to all sites.
     *
     * @param int|null $root UID of the root page of the site configuration of the page or NULL if there's none
     * @return array{lang: string, domain: string}|null Language or NULL if unknown
     */
    public function get( int $uid, ?int $root = null ): ?array
    {
        return $this->options[$uid] ?? ( $root !== null && isset( $this->sites[$root] ) ? $this->sites[$root][$uid] ?? null : $this->languages[$uid] ?? null );
    }


    /**
     * Returns the UIDs of the languages whose translations are imported.
     *
     * @param string $lang Language code of the pages in default language, translations in this language aren't imported
     * @return array<int, int> TYPO3 language UIDs
     */
    public function imported( string $lang ): array
    {
        $languages = $this->languages;

        foreach( $this->sites as $site ) {
            $languages += $site;
        }

        $uids = [];

        foreach( [$languages, ...$this->sites] as $list )
        {
            foreach( $list as $uid => $language )
            {
                if( $language['lang'] !== $lang ) {
                    $uids[$uid] = $uid;
                }
            }
        }

        return array_values( $uids );
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
