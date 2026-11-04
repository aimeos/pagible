<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Resolves the Pagible URLs of TYPO3 pages and the targets of TYPO3 shortcut and external URL pages.
 *
 * The translation passed to the methods is the TYPO3 language of the content which is built:
 * its UID and the domain of the source page if the translation is on a separate domain.
 *
 * @phpstan-type Translation array{uid: int, source: string}|null
 */
class Typo3Urls
{
    /**
     * @param Collection<int, mixed> $pages TYPO3 pages keyed by UID
     * @param array<int, array<int, string>> $translations URL paths of the TYPO3 page translations by the UID of the page in default language and the language UID
     */
    public function __construct( protected Collection $pages, protected array $translations )
    {
    }


    /**
     * Returns the Pagible path for a TYPO3 page UID or NULL if the page is unknown or has no target.
     *
     * @param Translation $translation
     */
    public function page( int $uid, string $fragment = '', ?array $translation = null ): ?string
    {
        if( !( $page = $this->pages->get( $uid ) ) ) {
            return null;
        }

        $url = in_array( (int) $page->doktype, [3, 4], true )
            ? $this->destination( $page, $translation, [] )
            : $this->url( $page, $translation );

        if( $url === '' ) {
            return null;
        }

        return $url . ( $fragment !== '' ? '#' . ltrim( $fragment, '#' ) : '' );
    }


    /**
     * Resolves the redirect target of TYPO3 external URL and selected-page shortcut records.
     */
    public function redirect( object $t3Page ): string
    {
        return in_array( (int) ( $t3Page->doktype ?? 0 ), [3, 4], true ) ? $this->destination( $t3Page, null, [] ) : '';
    }


    /**
     * Extracts a clean slug from a TYPO3 page slug/path.
     */
    public static function slug( ?string $path ): string
    {
        return trim( $path ?? '', '/' );
    }


    /**
     * Resolves a TYPO3 shortcut destination to an external URL or Pagible path.
     *
     * @param Translation $translation
     * @param array<int, bool> $seen
     */
    protected function destination( object $t3Page, ?array $translation, array $seen ): string
    {
        $uid = (int) ( $t3Page->uid ?? 0 );

        if( isset( $seen[$uid] ) ) {
            return '';
        }

        $seen[$uid] = true;
        $doktype = (int) ( $t3Page->doktype ?? 0 );

        // only http(s) URLs and paths are allowed, not e.g. "javascript:" URLs
        if( $doktype === 3 ) {
            return Utils::isValidUrl( $url = trim( (string) ( $t3Page->url ?? '' ) ), false ) ? $url : '';
        }

        if( $doktype === 4 )
        {
            if( (int) ( $t3Page->shortcut_mode ?? 0 ) !== 0 ) {
                return '';
            }

            $target = $this->pages->get( (int) ( $t3Page->shortcut ?? 0 ) );

            return $target ? $this->destination( $target, $translation, $seen ) : '';
        }

        return $this->url( $t3Page, $translation );
    }


    /**
     * Returns the URL of an imported TYPO3 page in the language of the content which is built.
     *
     * Links in translated content point to the translation of the page. If the page isn't
     * translated, they point to the page in the default language, using an absolute URL
     * if the translation is on another domain.
     *
     * @param Translation $translation
     */
    protected function url( object $t3Page, ?array $translation ): string
    {
        $path = self::slug( $t3Page->slug ?? '' );

        if( $translation === null ) {
            return '/' . $path;
        }

        if( ( $slug = $this->translations[(int) ( $t3Page->uid ?? 0 )][$translation['uid']] ?? null ) !== null ) {
            return '/' . self::slug( $slug );
        }

        return $translation['source'] !== '' ? 'https://' . $translation['source'] . '/' . $path : '/' . $path;
    }
}
