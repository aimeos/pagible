<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\PageView;


/**
 * Finds the pages of previous imports by their URL path.
 */
class DrupalPages
{
    /**
     * Initializes the object.
     *
     * @param string $domain Domain of the imported pages
     */
    public function __construct( protected string $domain )
    {
    }


    /**
     * Returns the page or page variant using the URL path in the domain, also if trashed.
     *
     * @param string $path URL path with or without leading slash
     * @param string|null $domain Domain of the page or NULL for the domain of the imported pages
     */
    public function path( string $path, ?string $domain = null ): ?PageView
    {
        $path = trim( $path, '/' );

        return PageView::withTrashed()->where( 'domain', $domain ?? $this->domain )->whereIn( 'path', [$path, '/' . $path] )->first();
    }


    /**
     * Returns the pages or page variants using the URL paths in the domain, also if trashed.
     *
     * @param array<int, string> $paths URL paths without leading slash
     * @param string|null $domain Domain of the pages or NULL for the domain of the imported pages
     * @return array<string, PageView> Pages by URL path without leading slash
     */
    public function paths( array $paths, ?string $domain = null ): array
    {
        $list = [];
        $pages = PageView::withTrashed()->where( 'domain', $domain ?? $this->domain )
            ->whereIn( 'path', [...$paths, ...array_map( fn( $path ) => '/' . $path, $paths )] )->get();

        foreach( $pages as $page )
        {
            if( $page instanceof PageView ) {
                $list[trim( (string) $page->getAttribute( 'path' ), '/' )] ??= $page;
            }
        }

        return $list;
    }
}
