<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Import\Statamic\Content;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Models\PageView;


/**
 * Converts the redirects and links of the Statamic entries to the URLs of the imported pages.
 */
trait Redirects
{
    /** @var array<string, int|string|null> IDs of the first child entries used by "@child" redirects by ID of the entries in the trees */
    protected array $childIds = [];
    /** @var array{0: int|string|null|false, 1: string|null} First child entry and site handle of the converted entry for "@child" links */
    protected array $current = [false, null];


    /**
     * Returns the link target of the redirect entry or NULL if it's no redirect.
     *
     * Redirects can point to URLs, entries like "entry::ID", assets or the first child entry by "@child".
     * Redirects to "404" show the not found page and the entries are disabled by status() instead.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param int|string|null|false $child ID of the first child entry in the tree or FALSE if the entry isn't in a tree
     * @param string $title Title of the entry used in the notice if the target can't be resolved
     * @param string|null $site Site handle of translations whose "@child" redirects point to the localized child entry
     * @return string|null URL of the redirect target or NULL if the entry isn't a redirect or the target is unknown
     */
    protected function redirect( array $data, int|string|null|false $child, string $title, ?string $site = null ): ?string
    {
        // redirects are resolved before the content, so links to entries must use the site of this entry
        $this->current = [$child, $site];

        if( ( $value = $this->redirectValue( $data ) ) === null || $this->notFound( $data, $child ) !== null ) {
            return null;
        }

        $target = $value === '@child' ? $this->childUrl( $child, $site ) : $this->content->link( $value );

        if( $target === null )
        {
            // taxonomies aren't imported, so their terms have no pages
            $reason = preg_match( '/^(?:statamic:\/\/)?term::/', $value ) ? "taxonomy terms like \"{$value}\" aren't imported" : "the redirect target \"{$value}\" can't be resolved";
            $this->notice( 'Imported as page', $title, $reason );
        }

        return $target;
    }


    /**
     * Returns why Statamic shows the not found page instead of redirecting.
     *
     * Statamic shows the not found page for entries redirecting to "404" and if the redirect target doesn't
     * exist, e.g. "@child" redirects of entries without children or links to deleted entries.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @param int|string|null|false $child ID of the first child entry used by "@child" redirects or FALSE if the entry isn't in a tree
     * @return string|null Reason or NULL if the entry isn't a redirect or Statamic redirects
     */
    protected function notFound( array $data, int|string|null|false $child ): ?string
    {
        $value = $this->redirectValue( $data );

        if( $value === '404' ) {
            return 'it redirects to "404", so Statamic shows the not found page';
        }

        if( $value === '@child' && $child === null ) {
            return 'it redirects to its first child but has none, so Statamic shows the not found page';
        }

        if( $value === '@child' && $child === false ) {
            return 'it redirects to its first child but isn\'t in a page tree, so Statamic can\'t resolve the redirect';
        }

        if( $value !== null && preg_match( '/^(?:statamic:\/\/)?entry::(.+)$/s', $value, $match ) && !isset( $this->paths[$match[1]] ) && !$this->reader->hasEntry( $match[1] ) ) {
            return "its redirect target \"{$value}\" doesn't exist, so Statamic shows the not found page";
        }

        return null;
    }


    /**
     * Returns the redirect target of the entry.
     *
     * The redirect field stores the target or the target and the HTTP status like {"url": "...", "status": 301}.
     * Pagible redirects don't have a status, the status is ignored.
     *
     * @param array<string, mixed> $data Decoded entry data
     * @return string|null Redirect target or NULL if the entry isn't a redirect
     */
    protected function redirectValue( array $data ): ?string
    {
        $value = $data['redirect'] ?? null;
        $value = is_array( $value ) ? $value['url'] ?? null : $value;
        $value = is_int( $value ) ? (string) $value : $value;

        return is_string( $value ) && ( $value = trim( $value ) ) !== '' ? $value : null;
    }


    /**
     * Returns the URL of the first child entry used by "@child" redirects and links.
     *
     * @param int|string|null|false $child ID of the first child entry or NULL/FALSE if there's none
     * @param string|null $site Site handle of translations which link to the localized child entry
     * @return string|null URL of the child entry or NULL if it has no URL
     */
    protected function childUrl( int|string|null|false $child, ?string $site = null ): ?string
    {
        if( $child === null || $child === false ) {
            return null;
        }

        $local = $site !== null ? $this->localIds[$site][(string) $child] ?? null : null;

        return ( $local !== null ? $this->localUrl( $local ) : null ) ?? ( isset( $this->paths[$child] ) ? '/' . $this->paths[$child] : null );
    }


    /**
     * Returns the URL of a Statamic reference used in links.
     *
     * Links to entries refer to the entries of the site of the converted entry with the same topmost origin
     * entry like in Statamic unless the field selects entries across sites. The "@child" links always refer
     * to the child of the entry. Links in translations with an own domain to the pages of the default site
     * are absolute.
     *
     * @param string $ref Reference like "entry::ID", "asset::container::path" or "@child" for the first child of the converted entry
     * @param bool $localize If links to entries refer to the localized entries of the site of the converted entry
     * @return string|null URL path of the imported entry, URL of the asset or NULL if it's unknown
     */
    protected function reference( string $ref, bool $localize = true ): ?string
    {
        if( $ref === '@child' ) {
            return $this->childUrl( ...$this->current );
        }

        if( preg_match( '/^entry::(.+)$/s', $ref, $match ) )
        {
            $site = $this->current[1];
            $root = $this->roots[$match[1]] ?? $match[1];
            $local = $localize && $site !== null ? $this->localIds[$site][$root] ?? null : null;
            $url = $localize ? ( $site !== null ? ( $local !== null ? $this->localUrl( $local ) : null ) : $this->pageUrl( $root ) ) : null;

            // links to entries without localized entry in the site refer to the linked entry
            return $url ?? $this->pageUrl( $match[1] ) ?? $this->localUrl( $match[1] );
        }

        $url = $this->fileUrl( Content::asset( $ref, $this->container ) );

        // asset URLs without base URL can't be linked
        return preg_match( '#^(?:https?://|/)#i', $url ) ? $url : null;
    }


    /**
     * Creates the redirect page or updates the one of a previous import.
     *
     * The page of an entry imported before the entry became a redirect becomes the redirect page.
     *
     * @param string $slug URL path of the redirect
     * @param array<string, mixed> $pageData Page data from pageData() including the link target
     * @param PageView $parent Parent page
     * @return array{page: PageView, status: Status}|null Saved page and its status or NULL if the URL is used by another page
     */
    protected function saveRedirect( string $slug, array $pageData, PageView $parent ): ?array
    {
        $page = $this->pageByPath( $slug );

        if( $page && $page->to === '' && $this->isImported( $page ) && !isset( $this->usedPages[$page->id ?? ''] ) ) {
            return $this->storeLink( $pageData, $parent, $page );
        }

        return $this->saveLink( $slug, $pageData, $parent );
    }


    /**
     * Returns the page of the entry or the redirect page of a previous import which uses the URL path.
     *
     * Entries which were redirects before replace their redirect pages.
     *
     * @param string $path URL path of the page
     * @return PageView|null Page restored if it was trashed or NULL if no page uses the URL
     * @throws \RuntimeException If the URL is used by a page which wasn't imported, was changed since or was saved by this import
     */
    #[\Override]
    protected function previousPage( string $path ): ?PageView
    {
        $page = $this->pageByPath( $path );

        if( $page && $page->to !== '' && $this->linkOwned( $page, ['lang' => $this->lang] ) && !isset( $this->usedPages[$page->id ?? ''] ) )
        {
            $this->usedPages[$page->id ?? ''] = true;
            return $this->restore( $page );
        }

        // the fetched page is used by the parent method instead of fetching it again
        $this->prefetchedPages[trim( $path, '/' )] = $page;

        return parent::previousPage( $path );
    }
}
