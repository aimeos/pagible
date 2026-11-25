<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Imports the Statamic entries of the collections and trees as Pagible pages.
 */
trait Entries
{
    /**
     * Imports the entries of the collections, mounted collections below the page of their mount entry.
     */
    protected function importCollections(): void
    {
        $this->root = $root = $this->getOrCreateRoot( $this->domain );
        $mounts = $this->reader->mounts();

        foreach( $this->order( $this->listOption( 'collections' ), $mounts ) as $handle )
        {
            // collections mounted on an entry which wasn't imported are imported below the root page
            $parent = isset( $mounts[$handle] ) ? $this->createdPage( $mounts[$handle] ) ?? $root : $root;

            if( $tree = $this->trees[$handle] ?? null ) {
                $this->importTree( $tree, $parent, $this->reader->expectsRoot( $handle ) );
            }

            foreach( $this->chunks( $handle ) as $ids ) {
                $this->importEntries( $this->rows( $ids ), $parent );
            }
        }

        $this->totals( count( $this->entries ), 'entries' );
    }


    /**
     * Imports the entries and their translations below the parent page.
     *
     * @param Collection<array-key, \stdClass> $rows Statamic entries without data
     * @param PageView $parent Parent page
     */
    protected function importEntries( Collection $rows, PageView $parent ): void
    {
        $entries = $this->prepare( $rows );
        $pages = [];

        foreach( $entries as $entry )
        {
            if( !isset( $this->attempted[$entry->id] ) && ( $page = $this->importEntry( $entry, $parent ) ) ) {
                $pages[(string) $entry->id] = $page;
            }
        }

        $this->translate( $entries, $pages );
    }


    /**
     * Imports a single entry as a Pagible page or updates the page of a previous import.
     *
     * @param \stdClass $entry Statamic entry
     * @param PageView $parent Parent page
     * @param int|string|null|false $child ID of the first child entry in the tree used by "@child" redirects or FALSE if the entry isn't in a tree
     * @return PageView|null Saved page or NULL if the import failed
     */
    protected function importEntry( \stdClass $entry, PageView $parent, int|string|null|false $child = false ): ?PageView
    {
        $this->attempted[$entry->id] = true;

        // translations use the child of their origin entry
        if( $child !== false ) {
            $this->childIds[(string) $entry->id] = $child;
        }

        if( $this->homeId !== null && (string) $entry->id === (string) $this->homeId ) {
            return $this->importHome( $entry, $child );
        }

        $kept = $this->kept( $entry );
        $title = $kept ? (string) $this->paths[$entry->id] : $this->reader->title( $entry, $this->reader->data( $entry ) );

        return $this->transaction( "[{$entry->id}] {$title}", function() use ( $entry, $parent, $child, $kept )
        {
            $path = $this->paths[$entry->id] ?? null;

            // entries not changed since the given date aren't rendered again
            if( $kept && $path !== null && ( $result = $this->keepPage( $entry->id, $path, $parent ) ) ) {
                return $this->imported( $entry->id, $result['page'], (string) $result['page']->name, $path, $result['status'] );
            }

            $data = $this->reader->data( $entry );
            $title = $this->reader->title( $entry, $data );

            if( $path === null ) {
                $this->titlePaths[$path = Utils::slugify( $title )] = true;
            }

            $status = $this->status( $entry, $data, $title, $child );
            $pageData = $this->pageData( $title, $path, $status );

            if( ( $target = $this->redirect( $data, $child, $title ) ) !== null )
            {
                $result = $this->saveRedirect( $path, ['to' => $target] + $pageData, $parent )
                    ?? throw new \RuntimeException( "URL /{$path} is used by another page" );

                return $this->imported( $entry->id, $result['page'], $title, $path, $result['status'] );
            }

            ['content' => $content, 'title' => $seoTitle] = $this->pageContent( $entry, $data, $title, $child );
            $pageData = array_replace( $pageData, array_filter( ['title' => $seoTitle] ), ['to' => ''] );
            $result = $this->savePage( $path, $pageData, $content, $parent, $entry->created_at ?? null );
            $this->schedule( $result['page'], $entry, $data, $pageData, $content, $child );

            return $this->imported( $entry->id, $result['page'], $title, $path, $result['status'] );
        } );
    }


    /**
     * Imports the home entry into the root page.
     *
     * The root page of another import or changed in Pagible since isn't overwritten.
     *
     * @param \stdClass $entry Home entry
     * @param int|string|null|false $child ID of the next top level entry used by "@child" redirects or FALSE if the entry isn't in a tree
     * @return PageView|null Root page or NULL if the import failed
     */
    protected function importHome( \stdClass $entry, int|string|null|false $child = false ): ?PageView
    {
        $root = $this->root ?? $this->getOrCreateRoot( $this->domain );
        $data = $this->reader->data( $entry );
        $title = $this->reader->title( $entry, $data );

        return $this->transaction( "[{$entry->id}] {$title}", function() use ( $entry, $root, $data, $title, $child )
        {
            if( !$this->newRoot && !$this->isImported( $root ) )
            {
                $this->notice( 'Skipped home page', "{$title} (/)", "the root page wasn't imported or was changed since" );
                return $root;
            }

            ['content' => $content, 'title' => $seoTitle] = $this->pageContent( $entry, $data, $title, $child );
            $pageData = array_replace( $this->pageData( $title, '', $this->status( $entry, $data, $title, $child ), Pages::TAG_ROOT ), array_filter( ['title' => $seoTitle] ),
                ['to' => $this->redirect( $data, $child, $title ) ?? ''] );
            $status = $this->updateVersion( $root, $pageData, $content );
            $this->schedule( $root, $entry, $data, $pageData, $content, $child );

            return $this->imported( $entry->id, $root, $title, '', $status );
        } );
    }


    /**
     * Imports the entries following the tree of the collection.
     *
     * The data and pages of the entries are fetched for a batch of tree nodes at once.
     *
     * @param array<int, array<string, mixed>> $tree Tree of the collection
     * @param PageView $parent Parent page of the top level entries
     * @param bool $root TRUE if the first entry of the tree is the home page
     */
    protected function importTree( array $tree, PageView $parent, bool $root = false ): void
    {
        // the pages of the nodes with children are packed by slim() to save memory
        $parents = [];

        foreach( $this->flatten( $tree, $root ) as $nodes )
        {
            $entries = $this->prepare( $this->rows( array_map( fn( $node ) => $node['entry'], $nodes ) ) );
            $pages = [];

            foreach( $nodes as $pos => $node )
            {
                // children of entries which failed are imported later below the parent of the collection
                if( $node['parent'] !== null && !isset( $parents[$node['parent']] ) ) {
                    continue;
                }

                $parentPage = $node['parent'] !== null ? $this->slimPage( $parents[$node['parent']] ) : $parent;

                if( ( $entry = $entries->get( $pos ) ) && !isset( $this->attempted[$entry->id] ) )
                {
                    if( $page = $this->importEntry( $entry, $parentPage, $node['child'] ) ) {
                        $pages[(string) $entry->id] = $page;
                    }
                }
                else
                {
                    // nodes without imported entry keep the parent page for their children, entries in the tree twice use their page
                    $page = $node['entry'] !== null && isset( $this->attempted[$node['entry']] ) ? $this->createdPage( $node['entry'] ) : $parentPage;
                }

                if( $node['children'] && $page ) {
                    $parents[$pos] = $this->slim( $page );
                }
            }

            $this->translate( $entries, $pages );
        }
    }


    /**
     * Returns the content, meta data and SEO title of the entry.
     *
     * @param \stdClass $entry Statamic entry whose blueprint defines the containers of its asset fields
     * @param array<string, mixed> $data Decoded entry data
     * @param string $title Title of the entry
     * @param int|string|null|false $child ID of the first child entry used by "@child" links or FALSE if the entry isn't in a tree
     * @param string|null $site Site handle of translations whose "@child" links point to the localized child entry
     * @return array{content: array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, meta: array<string, mixed>}, title: string|null}
     */
    protected function pageContent( \stdClass $entry, array $data, string $title, int|string|null|false $child = false, ?string $site = null ): array
    {
        $this->current = [$child, $site];
        $containers = $this->reader->containers( $entry );
        $this->content->containers( $containers )->texts( $this->reader->texts( $entry ) )->grids( $this->reader->grids( $entry ) )
            ->videos( $this->reader->videos( $entry ) )->links( $this->reader->links( $entry ) )->across( $this->reader->across( $entry ) )
            ->lists( $this->reader->lists( $entry ) )->arrays( $this->reader->arrays( $entry ) );

        $meta = $this->meta->build( $data, $title, $containers, $this->reader->seoFallbacks( $entry ) );

        // older SEO Pro versions store their site defaults in the "content/seo.yaml" file
        if( is_array( $data['seo'] ?? null ) && !$this->reader->hasSeoSettings() ) {
            $this->warnOnce( 'seo:settings', '  The SEO Pro site defaults aren\'t stored in the "addon_settings" table, so they aren\'t imported. '
                . 'Older SEO Pro versions store them in the "content/seo.yaml" file, add them to the collection defaults or the entries before importing' );
        }
        $content = Elements::merge( Elements::imageContent( $this->featuredImage( $data, $this->reader->images( $entry ) ) ), $this->content->content( $data ) );
        $content['fileIds'] = array_values( array_unique( array_merge( $content['fileIds'], $meta['fileIds'] ) ) );

        return ['content' => $content + ['meta' => $meta['meta']], 'title' => $meta['title']];
    }


    /**
     * Returns the status of the page from the protection and the date of the entry.
     *
     * Protected entries and entries hidden by date are disabled, unlisted ones are hidden from the navigation.
     *
     * @param \stdClass $entry Statamic entry
     * @param array<string, mixed> $data Decoded entry data
     * @param string $title Title of the entry
     * @param int|string|null|false $child ID of the first child entry used by "@child" redirects or FALSE if the entry isn't in a tree
     */
    protected function status( \stdClass $entry, array $data, string $title, int|string|null|false $child = false ): int
    {
        // protected entries are only visible to logged in users or visitors who know the password
        $scheme = $this->reader->protection( $entry, $data );
        $date = $this->reader->dateRestriction( $entry );
        $reason = $scheme !== '' ? "it is protected by the \"{$scheme}\" scheme" : ( ( $date['behavior'] ?? null ) === 'private' ? $date['reason'] ?? null : null );
        // scheduled entries are published at their date by the "cms:publish" command unless they are private then too
        $reason = $reason !== null && $scheme === '' && isset( $date['at'] ) && $date['then'] !== 'private' ? $reason . ', it\'s published then by "php artisan cms:publish"' : $reason;
        // Statamic shows the not found page for entries redirecting to "404" or to targets which don't exist
        $reason = $this->notFound( $data, $child ) ?? $reason;

        if( $reason !== null )
        {
            $this->warn( "  Disabled [{$entry->id}] {$title} because {$reason}" );
            return Pages::STATUS_DISABLED;
        }

        return ( $date['behavior'] ?? null ) === 'unlisted' ? Pages::STATUS_HIDDEN : Pages::STATUS_PUBLISHED;
    }


    /**
     * Saves the scheduled version of the page if the entry is shown or hidden at its date or removes the one of a previous import.
     *
     * Pagible publishes the version at its date by the "cms:publish" command like Statamic changes the visibility of
     * the entry, e.g. scheduled entries are shown then and events are hidden after their date.
     *
     * @param PageView $page Saved page
     * @param \stdClass $entry Statamic entry
     * @param array<string, mixed> $data Decoded entry data
     * @param array<string, mixed> $pageData Page data of the saved page
     * @param array{elements: array<int, array<string, mixed>>, fileIds: array<int, string>, meta: array<string, mixed>} $content Content of the saved page
     * @param int|string|null|false $child ID of the first child entry used by "@child" redirects or FALSE if the entry isn't in a tree
     */
    protected function schedule( PageView $page, \stdClass $entry, array $data, array $pageData, array $content, int|string|null|false $child = false ): void
    {
        // protected entries and entries whose redirects show the not found page stay disabled after their date
        $date = $this->reader->protection( $entry, $data ) === '' && $this->notFound( $data, $child ) === null ? $this->reader->dateRestriction( $entry ) : null;
        $status = match( $date['then'] ?? null ) {
            'private' => Pages::STATUS_DISABLED,
            'unlisted' => Pages::STATUS_HIDDEN,
            default => Pages::STATUS_PUBLISHED,
        };
        $at = isset( $date['at'] ) && $status !== ( $pageData['status'] ?? null ) ? $date['at'] : null;

        $this->saveVersions( $page, $at ? [[array_replace( $pageData, ['status' => $status] ), $content, $at]] : [] );
    }
}
