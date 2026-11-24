<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Typo3;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;


/**
 * Finds the existing pages of the selected TYPO3 pages which are updated by the import.
 */
class Destinations
{
    /** @var array<string, array<string, PageView|null>> Existing pages of the TYPO3 pages by domain and path */
    protected array $pages;


    /**
     * Fetches the existing pages of the TYPO3 pages at once for each domain.
     *
     * @param Collection<int, \stdClass> $t3Pages Selected TYPO3 pages
     * @param \Closure(\stdClass): string $domain Returns the domain of the TYPO3 page
     * @param (\Closure(object): bool)|null $isRoot Tests if the TYPO3 page is imported as root page of its domain
     */
    public function __construct( Collection $t3Pages, \Closure $domain, protected ?\Closure $isRoot = null )
    {
        $this->pages = $t3Pages->groupBy( $domain )->map( fn( $pages, $domain ) => Pages::findAll(
            ['domain' => (string) $domain], 'path', $pages->map( fn( $page ) => Urls::slug( $page->slug ) )->all()
        ) )->all();
    }


    /**
     * Returns the existing page of the TYPO3 page, the root page of the domain for TYPO3 root pages.
     */
    public function find( object $t3Page, string $domain ): ?PageView
    {
        if( $this->root( $t3Page ) ) {
            return Pages::find( ['tag' => Pages::TAG_ROOT, 'domain' => $domain] );
        }

        $path = Urls::slug( $t3Page->slug ?? null );

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
        if( $this->root( $t3Page ) ) {
            return PageView::withTrashed()->where( ['tag' => Pages::TAG_ROOT, 'domain' => $domain] )->first();
        }

        $path = Urls::slug( $t3Page->slug ?? null );

        if( array_key_exists( $path, $this->pages[$domain] ?? [] ) ) {
            return $this->pages[$domain][$path];
        }

        return PageView::withTrashed()->where( ['domain' => $domain, 'path' => $path] )->first();
    }


    /**
     * Tests if the TYPO3 page is imported as root page of its domain.
     */
    protected function root( object $t3Page ): bool
    {
        return $this->isRoot ? ( $this->isRoot )( $t3Page ) : (int) ( $t3Page->pid ?? 0 ) === 0 || !empty( $t3Page->is_siteroot );
    }


    /**
     * Forgets the pages of the TYPO3 page in all domains, e.g. because its import failed.
     */
    public function forget( object $t3Page ): void
    {
        foreach( $this->pages as $domain => $list ) {
            unset( $this->pages[$domain][Urls::slug( $t3Page->slug ?? null )] );
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
