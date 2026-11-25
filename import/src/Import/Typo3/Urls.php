<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Resolves the Pagible URLs of TYPO3 pages and the targets of TYPO3 shortcut and external URL pages.
 *
 * The translation passed to the methods is the TYPO3 language of the content which is built:
 * its UID, the domain of the source page if the translation is on a separate domain and
 * optionally the UIDs of the fallback languages of the site configuration in their order.
 *
 * @phpstan-type Translation array{uid: int, source: string, chain?: array<int, int>}|null
 */
class Urls
{
    /** @var array<int, Collection<int, mixed>>|null Possible shortcut targets grouped by the UID of their parent page */
    protected ?array $children = null;

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
        // spacers, folders and recyclers aren't imported, backend user sections and pages for frontend users aren't linked by TYPO3
        if( !( $page = $this->pages->get( $uid ) ) || ( $doktype = (int) ( $page->doktype ?? 1 ) ) >= 199 || $doktype === Tree::BE_SECTION
            || Tree::restricted( (object) ['fe_group' => $page->fe_group ?? ''] ) !== null
        ) {
            return null;
        }

        $url = $this->redirects( $page )
            ? $this->destination( $page, $translation, [] )
            : $this->url( $page, $translation );

        if( $url === '' ) {
            return null;
        }

        return $url . ( ( $fragment = self::fragment( $fragment ) ) !== '' ? '#' . $fragment : '' );
    }


    /**
     * Resolves the redirect target of TYPO3 external URL, shortcut and mount point pages.
     *
     * @param Translation $translation TYPO3 language of the translated page or NULL for the default language
     */
    public function redirect( object $t3Page, ?array $translation = null ): string
    {
        return $this->redirects( $t3Page ) ? $this->destination( $t3Page, $translation, [] ) : '';
    }


    /**
     * Tests if the TYPO3 page redirects to another page or URL instead of showing its own content.
     *
     * Mount points only show the mounted page if "mount_pid_ol" is set, otherwise their own content is shown.
     */
    public function redirects( object $t3Page ): bool
    {
        return match( (int) ( $t3Page->doktype ?? 1 ) ) {
            3, 4 => true,
            7 => !empty( $t3Page->mount_pid_ol ),
            default => false,
        };
    }


    /**
     * Returns the ID of the first Pagible element of the TYPO3 content record, which replaces its anchor like "c123".
     *
     * The ID is derived from the UID, so links to content elements of pages imported later can be rewritten too.
     */
    public static function anchor( int $uid ): string
    {
        $base64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $hash = hash( 'sha256', 'typo3-content:' . $uid, true );
        // the same format as the IDs of Utils::uid() whose first character is a letter
        $id = $base64[ord( $hash[0] ) % 52];

        for( $i = 1; $i < 6; $i++ ) {
            $id .= $base64[ord( $hash[$i] ) & 63];
        }

        return $id;
    }


    /**
     * Returns the fragment of the URL, the anchors of the TYPO3 content records like "c123" are replaced by the IDs of their elements.
     */
    public static function fragment( string $fragment ): string
    {
        $fragment = ltrim( $fragment, '#' );
        return preg_match( '/^c(\d+)$/D', $fragment, $match ) ? self::anchor( (int) $match[1] ) : $fragment;
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
            return $this->external( trim( (string) ( $t3Page->url ?? '' ) ), $translation, $seen );
        }

        // mount points showing the mounted page tree use the URL of the mounted page
        if( $doktype === 7 && !empty( $t3Page->mount_pid_ol ) )
        {
            $target = $this->pages->get( (int) ( $t3Page->mount_pid ?? 0 ) );
            return $target ? $this->destination( $target, $translation, $seen ) : '';
        }

        if( $doktype === 4 )
        {
            $target = match( (int) ( $t3Page->shortcut_mode ?? 0 ) ) {
                0 => $this->pages->get( (int) ( $t3Page->shortcut ?? 0 ) ),
                1 => $this->children( (int) ( ( $t3Page->shortcut ?? 0 ) ?: $uid ) )->first(),
                2 => $this->children( (int) ( ( $t3Page->shortcut ?? 0 ) ?: $uid ) )->first(), // random subpages can't be imported, the first one is used
                3 => $this->pages->get( (int) ( $t3Page->pid ?? 0 ) ),
                default => null,
            };

            // shortcuts to themselves are ignored
            return $target && (int) $target->uid !== $uid ? $this->destination( $target, $translation, $seen ) : '';
        }

        // the targets of shortcuts and mount points must be linkable themselves
        return $doktype >= 199 || $doktype === Tree::BE_SECTION || Tree::restricted( (object) ['fe_group' => $t3Page->fe_group ?? ''] ) !== null
            ? '' : $this->url( $t3Page, $translation );
    }


    /**
     * Returns the subpages of the page which can be the target of a shortcut in their order.
     *
     * Like TYPO3 for anonymous visitors, hidden pages, pages restricted to frontend user groups,
     * expired and scheduled pages, spacers, folders, recyclers and backend user sections are skipped.
     *
     * @return Collection<int, mixed> TYPO3 pages
     */
    protected function children( int $pid ): Collection
    {
        $this->children ??= $this->pages->filter( fn( $page ) => empty( $page->hidden ) && (int) ( $page->doktype ?? 1 ) < 199
            && (int) ( $page->doktype ?? 1 ) !== 6 && Tree::restricted( $page ) === null )->groupBy( fn( $page ) => (int) ( $page->pid ?? 0 ) )->all();

        return $this->children[$pid] ?? Collection::make();
    }


    /**
     * Returns the target of an external URL page.
     *
     * Links to pages (t3://page) are resolved and URLs without scheme like "www.example.com" are
     * prefixed by "https://" like TYPO3 does. Only http(s) URLs and paths are allowed.
     *
     * @param array{uid: int, source: string, chain?: array<int, int>}|null $translation TYPO3 language of the translation
     * @param array<int, bool> $seen UIDs of the pages already visited
     */
    protected function external( string $url, ?array $translation, array $seen ): string
    {
        if( preg_match( '#^t3://page\?(?:.*&)?uid=(\d+)(?:[^\#]*)(?:\#(.*))?$#i', $url, $match ) )
        {
            $target = $this->pages->get( (int) $match[1] );
            $path = $target ? $this->destination( $target, $translation, $seen ) : '';

            return $path !== '' && !empty( $match[2] ) ? $path . '#' . self::fragment( $match[2] ) : $path;
        }

        if( $url !== '' && !preg_match( '#^(?:[a-z][a-z0-9+.-]*:|/)#i', $url ) && preg_match( '#^[\w-]+(?:\.[\w-]+)+(?:[/:?\#]|$)#', $url ) ) {
            $url = 'https://' . $url;
        }

        return Utils::isValidUrl( $url, false ) ? $url : '';
    }


    /**
     * Returns the URL of an imported TYPO3 page in the language of the content which is built.
     *
     * Links in translated content point to the translation of the page. If the page isn't
     * translated, they point to the translation in the first fallback language of the site
     * configuration or to the page in the default language, using an absolute URL if the
     * translation is on another domain. Like in TYPO3, pages hidden in the default language
     * or hidden if they aren't translated ("l18n_cfg") aren't linked then.
     *
     * @param Translation $translation
     */
    protected function url( object $t3Page, ?array $translation ): string
    {
        $path = self::slug( $t3Page->slug ?? '' );
        $config = (int) ( $t3Page->l18n_cfg ?? 0 );

        // pages hidden in the default language aren't linked
        if( $translation === null ) {
            return $config & 1 ? '' : '/' . $path;
        }

        $uid = (int) ( $t3Page->uid ?? 0 );

        foreach( [$translation['uid'], ...( $translation['chain'] ?? [] )] as $langUid )
        {
            if( ( $slug = $this->translations[$uid][$langUid] ?? null ) !== null ) {
                return '/' . self::slug( $slug );
            }
        }

        // pages hidden if they aren't translated and pages hidden in the default language aren't linked without translation
        if( $config & 3 ) {
            return '';
        }

        return $translation['source'] !== '' ? 'https://' . $translation['source'] . '/' . $path : '/' . $path;
    }
}
