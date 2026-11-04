<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Finds the existing pages of the selected TYPO3 pages which are updated by the import.
 */
class Typo3Destinations
{
    /** @var array<string, array<string, PageView|null>> Existing pages of the TYPO3 pages by domain and path */
    protected array $pages;


    /**
     * Fetches the existing pages of the TYPO3 pages at once for each domain.
     *
     * @param Collection<int, \stdClass> $t3Pages Selected TYPO3 pages
     * @param \Closure(\stdClass): string $domain Returns the domain of the TYPO3 page
     */
    public function __construct( Collection $t3Pages, \Closure $domain )
    {
        $this->pages = $t3Pages->groupBy( $domain )->map( fn( $pages, $domain ) => Pages::findAll(
            ['domain' => (string) $domain], 'path', $pages->map( fn( $page ) => Typo3Urls::slug( $page->slug ) )->all()
        ) )->all();
    }


    /**
     * Returns the existing page of the TYPO3 page, the root page of the domain for TYPO3 root pages.
     */
    public function find( object $t3Page, string $domain ): ?PageView
    {
        if( (int) ( $t3Page->pid ?? 0 ) === 0 || !empty( $t3Page->is_siteroot ) ) {
            return Pages::find( ['tag' => 'root', 'domain' => $domain] );
        }

        $path = Typo3Urls::slug( $t3Page->slug ?? null );

        if( array_key_exists( $path, $this->pages[$domain] ?? [] ) && !$this->pages[$domain][$path]?->trashed() ) {
            return $this->pages[$domain][$path];
        }

        return Pages::find( ['domain' => $domain, 'path' => $path] );
    }


    /**
     * Returns the existing page of the TYPO3 page including trashed ones without restoring them, the root page of the domain for TYPO3 root pages.
     */
    public function peek( object $t3Page, string $domain ): ?PageView
    {
        if( (int) ( $t3Page->pid ?? 0 ) === 0 || !empty( $t3Page->is_siteroot ) ) {
            return PageView::withTrashed()->where( ['tag' => 'root', 'domain' => $domain] )->first();
        }

        $path = Typo3Urls::slug( $t3Page->slug ?? null );

        if( array_key_exists( $path, $this->pages[$domain] ?? [] ) ) {
            return $this->pages[$domain][$path];
        }

        return PageView::withTrashed()->where( ['domain' => $domain, 'path' => $path] )->first();
    }


    /**
     * Forgets the pages of the TYPO3 page in all domains, e.g. because its import failed.
     */
    public function forget( object $t3Page ): void
    {
        foreach( $this->pages as $domain => $list ) {
            unset( $this->pages[$domain][Typo3Urls::slug( $t3Page->slug ?? null )] );
        }
    }


    /**
     * Remembers the imported page of the domain and path.
     */
    public function put( string $domain, string $path, PageView $page ): void
    {
        $this->pages[$domain][$path] = $page;
    }
}
