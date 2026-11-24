<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Typo3;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\Collection;
use Aimeos\Cms\DB as CmsDB;


/**
 * Imports the TYPO3 page tree below the root pages.
 */
trait Tree
{
    /** @var array<string, array<string, bool>> URL paths of the next sibling pages without existing page by domain, TRUE if a page or translation has been saved with the path since */
    protected array $watched = [];


    /**
     * Imports the child page and returns the saved or reused page.
     *
     * @param array<string, PageView|null> $found Existing pages of the domain by path
     * @return array{page: PageView, status: Status, path: string} Imported page, its status and its path
     */
    protected function importChild( object $t3Page, PageView $parentPage, string $domain, array $found ): array
    {
        $slug = Typo3\Urls::slug( $t3Page->slug ?? '' );
        $page = $found[$slug] ?? null;

        // pages which weren't imported or were saved by this import are only used as parent
        if( $page && ( !$this->isImported( $page ) || isset( $this->usedPages[$page->id ?? ''] ) ) )
        {
            if( $page->trashed() ) {
                throw new \RuntimeException( "URL /{$slug} is used by a page which wasn't imported or was changed since" );
            }

            return ['page' => $page, 'status' => Status::Reused, 'path' => $slug];
        }

        if( $page )
        {
            $page = $this->restore( $page );
            $this->moveBelow( $page, $parentPage );
        }

        $pageData = $this->buildPageData( $t3Page, $slug, $domain, $this->redirect( $t3Page ) );
        $saved = $this->save( $t3Page, $pageData, $page, $parentPage );
        $this->usedPages[$saved['page']->id ?? ''] = true;
        $this->savedPath( $domain, $slug );

        return $saved + ['path' => $slug];
    }


    /**
     * Imports the child pages of the TYPO3 page and their descendants.
     *
     * @param Collection<int|string, Collection<int, mixed>> $pageMap TYPO3 pages grouped by parent ID
     */
    protected function importChildren( Collection $pageMap, int $parentUid, PageView $parentPage, string $domain ): void
    {
        // the existing pages of the children are fetched in chunks, so not all pages including their content are in memory
        foreach( $pageMap->get( $parentUid, Collection::make() )->chunk( CmsDB::IN_SIZE ) as $children )
        {
            // pages imported for previous chunks are fetched from the database
            $slugs = $children->map( fn( $page ) => Typo3\Urls::slug( $page->slug ) )->values()->all();
            $found = Pages::findAll( ['domain' => $domain], 'path', $slugs );
            // existing pages removed from $found while the descendants are imported, fetched again when needed
            $unloaded = $watch = [];

            // saved pages and translations are only remembered if they use the URLs of pages which weren't found
            foreach( $found as $slug => $page )
            {
                if( $page === null && !isset( $this->watched[$domain][$slug] ) )
                {
                    $this->watched[$domain][$slug] = false;
                    $watch[] = $slug;
                }
            }

            foreach( $children->values() as $pos => $t3Page )
            {
                if( isset( $unloaded[$slugs[$pos]] ) ) {
                    $found = array_replace( $found, $this->reload( $slugs, $pos, $unloaded, $domain ) );
                }

                $this->prefetchSiblingVariants( $children->values(), $slugs, $pos, $found );
                $this->preload( $t3Page, $found[$slugs[$pos]] ?? null );
                $result = $this->transaction( "[{$t3Page->uid}] {$t3Page->title}", fn() => $this->importChild( $t3Page, $parentPage, $domain, $found ) );

                if( !$result ) {
                    continue;
                }

                // later pages with the same URL use the imported page, only its IDs and URL are kept
                $slim = $this->slim( $result['page'] );
                $found[$result['path']] = $this->slimPage( $slim );

                $this->report( $t3Page, $result['page'], $result['path'], $domain, $result['status'] );
                unset( $result );

                if( !$pageMap->has( (int) $t3Page->uid ) ) {
                    continue;
                }

                // only the pages of the next siblings are kept while the descendants are imported to limit the memory used per tree level
                foreach( array_slice( $slugs, $pos + 1 + static::SIBLING_SIZE ) as $slug )
                {
                    if( ( $page = $found[$slug] ?? null ) && !isset( $this->usedPages[$page->id ?? ''] ) )
                    {
                        unset( $found[$slug] );
                        $unloaded[$slug] = true;
                    }
                }

                // the descendants are imported directly after their parent, so new pages are added at the end of the page tree
                $this->importChildren( $pageMap, (int) $t3Page->uid, $this->slimPage( $slim ), $domain );

                // the descendants may have used the URLs of the next pages which weren't found before
                foreach( array_slice( $slugs, $pos + 1 ) as $slug )
                {
                    if( array_key_exists( $slug, $found ) && $found[$slug] === null && !empty( $this->watched[$domain][$slug] ) )
                    {
                        unset( $found[$slug] );
                        $unloaded[$slug] = true;
                    }
                }
            }

            foreach( $watch as $slug ) {
                unset( $this->watched[$domain][$slug] );
            }
        }
    }


    /**
     * Fetches the variants of the existing pages of the next translated sibling pages at once.
     *
     * The variants of the siblings are kept while the descendants of the previous page are imported and
     * only the ones which aren't prefetched yet are fetched, e.g. if they have been discarded meanwhile.
     *
     * @param Collection<int, mixed> $children TYPO3 sibling pages
     * @param array<int, string> $slugs URL paths of the sibling pages
     * @param int $pos Position of the sibling page imported next
     * @param array<string, PageView|null> $found Existing pages of the domain by path
     */
    protected function prefetchSiblingVariants( Collection $children, array $slugs, int $pos, array $found ): void
    {
        $page = $this->translatedPage( $children[$pos], $found[$slugs[$pos]] ?? null );

        if( !$page || isset( $this->prefetchedVariants[(string) $page->id] ) ) {
            return;
        }

        $ids = [];

        foreach( array_slice( $slugs, $pos, static::SIBLING_SIZE, true ) as $idx => $slug )
        {
            if( $page = $this->translatedPage( $children[$idx], $found[$slug] ?? null ) ) {
                $ids[] = (string) $page->id;
            }
        }

        $this->prefetchVariants( $ids, true );
    }


    /**
     * Fetches the existing pages of the next sibling pages removed before at once.
     *
     * @param array<int, string> $slugs URL paths of the sibling pages
     * @param int $pos Position of the sibling page imported next
     * @param array<string, bool> $unloaded URL paths of the removed pages, the fetched ones are removed
     * @param string $domain Domain of the pages
     * @return array<string, PageView|null> Existing pages by URL path, NULL if no page uses the URL
     */
    protected function reload( array $slugs, int $pos, array &$unloaded, string $domain ): array
    {
        $next = [];

        foreach( array_slice( $slugs, $pos ) as $slug )
        {
            if( count( $next ) >= static::SIBLING_SIZE ) {
                break;
            }

            if( isset( $unloaded[$slug] ) )
            {
                $next[] = $slug;
                unset( $unloaded[$slug] );
            }
        }

        return Pages::findAll( ['domain' => $domain], 'path', $next );
    }


    /**
     * Remembers that a page or translation has been saved with the URL path if it's the one of a next sibling page without existing page.
     */
    protected function savedPath( string $domain, string $path ): void
    {
        if( isset( $this->watched[$domain][$path] ) ) {
            $this->watched[$domain][$path] = true;
        }
    }
}
