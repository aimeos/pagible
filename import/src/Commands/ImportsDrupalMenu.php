<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;


/**
 * Imports the pages of the Drupal menu tree including the menu links to other targets and the Views pages.
 */
trait ImportsDrupalMenu
{
    /** Counts of the imported menu links which don't point to nodes */
    protected Tally $linkTally;
    /** @var array<string, bool> Domains and URL paths used by the menu link pages of this import */
    protected array $linkPaths = [];
    /** @var array<string, bool> URL paths of the Views pages linked in the menu */
    protected array $placeholders = [];
    /** @var array<int, bool> IDs of the nodes which failed to import, so they aren't rendered again for other parents */
    protected array $failedNodes = [];
    /** @var array<string, array<string, PageView>> Variants of the parent pages whose menu links are imported by page ID */
    protected array $parentVariants = [];


    /**
     * Imports the node and its menu children recursively unless already imported.
     */
    protected function importTree( int $nid, PageView $parent ): void
    {
        $node = $this->nodes->get( $nid );

        if( !$node || $this->createdPages->has( $nid ) || isset( $this->failedNodes[$nid] ) ) {
            return;
        }

        $page = $this->transaction( "[{$node->nid}] {$node->title}", fn() => $this->importNode( $node, $parent ) );
        $this->data->forget( $nid );

        if( !$page ) {
            $this->failedNodes[$nid] = true;
        }

        if( ++$this->processed % 100 === 0 ) {
            $this->info( "Processed {$this->processed} of {$this->nodes->count()} nodes." );
        }

        if( $page ) {
            $this->importChildren( 'node:' . $nid, $page );
        }
    }


    /**
     * Imports the menu links below the parent menu link.
     *
     * @param string $key Key of the parent menu link, e.g. "node:42" or "link:<uuid>"
     */
    protected function importChildren( string $key, PageView $parent ): void
    {
        foreach( $this->tree->children( $key ) as $link ) {
            $this->importLink( $link, $parent );
        }

        // the variants of the parent page are only shared by its menu links
        unset( $this->parentVariants[(string) $parent->id] );
    }


    /**
     * Imports the node of the menu link or a redirect page for links to other targets.
     *
     * Links to external URLs or existing pages become redirect pages shown in the navigation.
     * Links to Views pages become disabled placeholder pages using the URL of the Views page.
     * If the target can't be imported, the child links are imported below the parent.
     *
     * @param array{nid: int|null, uuid: string, title: string, uri: string} $link Menu link
     */
    protected function importLink( array $link, PageView $parent ): void
    {
        if( $link['nid'] )
        {
            $this->importTree( $link['nid'], $parent );
            return;
        }

        $to = $this->redirects->target( $link['uri'], $this->createdPath( ... ) );
        $target = str_starts_with( $to, '/' ) ? $this->pages->path( $to ) : null;

        if( ( $to === '' || str_starts_with( $to, '/' ) && !$target ) && ( $view = $this->views->find( $link['uri'] ) ) )
        {
            $this->placeholders[$view['path']] = true;
            $result = $this->transaction( "Views page {$view['path']}", fn() => $this->importView( $link, $view, $parent ) );

            $this->linkTally->add( $result['status'] ?? null );
            $this->importChildren( 'link:' . $link['uuid'], $result['page'] ?? $parent );
            return;
        }

        if( $to === '' || str_starts_with( $to, '/' ) && ( !$target || $target->to !== '' ) )
        {
            $this->warn( "  Skipped menu link: {$link['title']} ({$link['uri']}), target isn't imported" );
            $this->linkTally->add( null );
            $this->importChildren( 'link:' . $link['uuid'], $parent );
            return;
        }

        $path = $this->linkPath( $link['title'] ?: $link['uuid'], $parent->tag !== 'root' ? trim( (string) $parent->path, '/' ) : '', $this->domain );
        $result = $this->transaction( "menu link {$link['title']}", function() use ( $link, $path, $to, $parent ) {
            if( $result = $this->importRedirect( $path, $to, $parent, $link['title'], 1 ) ) {
                $this->saveLinkTranslations( $link, $to, $result['page'], $parent, $result['status'] === Status::Imported );
            }

            return $result;
        } );

        $this->linkTally->add( $result['status'] ?? null );

        if( !$result )
        {
            $this->importChildren( 'link:' . $link['uuid'], $parent );
            return;
        }

        $this->info( '  ' . $result['status']->label() . " menu link: {$link['title']} (/{$path} → {$to})" );
        $this->importChildren( 'link:' . $link['uuid'], $result['page'] );
    }


    /**
     * Creates a disabled placeholder page for the Views page linked in the menu.
     *
     * The content of Views pages can't be imported, so editors have to add it and enable the page.
     * Placeholder pages of previous imports are kept as they are, only moved below the parent page.
     *
     * @param array{nid: int|null, uuid: string, title: string, uri: string} $link Menu link
     * @param array{id: string, display: string, label: string, path: string} $view Views page
     * @return array{page: PageView, status: Status}|null Page and imported or unchanged, NULL if the URL is used by another page
     */
    protected function importView( array $link, array $view, PageView $parent ): ?array
    {
        $name = $link['title'] ?: $view['label'];

        if( !( $page = $this->pages->path( $view['path'] ) ) )
        {
            $page = $this->createPage( $this->pageData( $name, $view['path'], 0 ), Elements::merge(), $parent );
            $this->markSaved( $page->variant_id );
            $this->warn( "  Created disabled placeholder page: {$name} (/{$view['path']}) for the Views page \"{$view['id']}\", add its content in Pagible and enable it" );
            $this->saveViewTranslations( $link, $view, $page );

            return ['page' => $page, 'status' => Status::Imported];
        }

        if( $page->to !== '' || $page->lang !== $this->lang || $page->trashed() && $page->editor !== $this->editor )
        {
            $this->warn( "  Skipped placeholder page: {$name} (/{$view['path']}) for the Views page \"{$view['id']}\" (URL is used by another page)" );
            return null;
        }

        // placeholder pages changed in Pagible stay where they are
        if( $page->editor === $this->editor )
        {
            $page = $this->restore( $page );
            $this->markSaved( $page->variant_id );

            $this->moveBelow( $page, $parent );

            $this->saveViewTranslations( $link, $view, $page );
        }

        $this->info( "  Unchanged placeholder page: {$name} (/{$view['path']})" );
        return ['page' => $page, 'status' => Status::Unchanged];
    }


    /**
     * Creates the disabled variants of the placeholder page in the languages of the imported translations.
     *
     * The variants use the translated title of the menu link and the path of the Views page with the
     * language prefix like in Drupal. Variants changed in Pagible since, e.g. enabled ones, are kept.
     *
     * @param array{nid: int|null, uuid: string, title: string, uri: string} $link Menu link
     * @param array{id: string, display: string, label: string, path: string} $view Views page
     * @param PageView $page Placeholder page
     */
    protected function saveViewTranslations( array $link, array $view, PageView $page ): void
    {
        // the variants of all languages including trashed ones are fetched at once
        $variants = $this->langcodes ? $this->variants( $page ) : [];

        foreach( $this->langcodes as $langcode )
        {
            if( !( $language = $this->languages->language( $langcode ) ) ) {
                continue;
            }

            $lang = $language['lang'];
            $existing = $variants[$lang] ?? null;

            if( $existing && $existing->editor !== $this->editor ) {
                continue;
            }

            $title = $this->tree->title( $link['uuid'], $langcode ) ?? '' ?: $link['title'] ?: $view['label'];
            $path = trim( $this->languages->prefix( $langcode ) . '/' . $view['path'], '/' );

            if( $saved = $this->saveVariant( $page, $language, $path, $this->pageData( $title, $path, 0 ), Elements::merge(), $existing ) )
            {
                $variants[$lang] = $saved;
                $this->markSaved( $saved->variant_id );
            }
        }
    }


    /**
     * Warns about the Views pages which aren't linked in the menu and therefore not imported.
     */
    protected function reportViews(): void
    {
        $skipped = [];

        foreach( $this->views->all() as $path => $view )
        {
            if( !isset( $this->placeholders[$path] ) && !$this->pages->path( $path ) ) {
                $skipped[] = "/{$path} ({$view['id']})";
            }
        }

        if( $skipped ) {
            $this->warn( 'Skipped ' . count( $skipped ) . ' Views pages which are not in the menu, add their content in Pagible if needed: ' . implode( ', ', $skipped ) );
        }
    }


    /**
     * Returns an URL path for the page of a menu link which isn't used by other menu links of this import.
     *
     * @param string $title Title of the menu link
     * @param string $base URL path of the parent page without slashes or empty string below the root page
     * @param string $domain Domain of the page
     */
    protected function linkPath( string $title, string $base, string $domain ): string
    {
        $base = $base !== '' ? $base . '/' : '';
        $slug = Utils::slugify( $title ) ?: 'link';
        $path = $base . $slug;

        for( $i = 2; isset( $this->linkPaths["{$domain}:{$path}"] ); $i++ ) {
            $path = "{$base}{$slug}-{$i}";
        }

        $this->linkPaths["{$domain}:{$path}"] = true;
        return $path;
    }


    /**
     * Creates or updates the variants of the menu link page in the languages of the imported translations.
     *
     * The variants use the translated title of the menu link if available and are placed below the
     * variant of the parent page in the same language. Internal targets point to the page of the
     * same language if it exists, e.g. "/de/shop" instead of "/shop".
     *
     * @param array{nid: int|null, uuid: string, title: string, uri: string} $link Menu link
     * @param string $to Redirect target of the menu link page
     * @param PageView $page Page of the menu link
     * @param PageView $parent Parent page
     * @param bool $created TRUE if the page has just been created and has no variants in other languages yet
     */
    protected function saveLinkTranslations( array $link, string $to, PageView $page, PageView $parent, bool $created = false ): void
    {
        if( !$this->langcodes ) {
            return;
        }

        // the variants of the page and its parent and the pages of the translated targets are fetched at once,
        // the variants of the parent page are reused for its other menu links
        $variants = $created ? [] : $this->variants( $page );
        $parents = $this->parentVariants[(string) $parent->id] ??= $this->variants( $parent );
        $targets = [];

        foreach( $this->langcodes as $langcode ) {
            $targets[$langcode] = '/' . trim( $this->languages->prefix( $langcode ) . $to, '/' );
        }

        $paths = str_starts_with( $to, '/' ) ? array_map( fn( $path ) => trim( $path, '/' ), array_values( array_diff( $targets, [$to] ) ) ) : [];
        $found = $paths ? $this->pages->paths( $paths ) : [];

        foreach( $this->langcodes as $langcode )
        {
            if( !( $language = $this->languages->language( $langcode ) ) ) {
                continue;
            }

            $lang = $language['lang'];
            $title = $this->tree->title( $link['uuid'], $langcode ) ?? $link['title'];

            // translations changed in Pagible aren't overwritten
            $existing = $variants[$lang] ?? null;

            if( $existing && $existing->editor !== $this->editor )
            {
                $this->warn( "  Skipped translation: {$title} [{$lang}] (changed in Pagible since the last import)" );
                continue;
            }

            $variant = $parents[$lang] ?? null;
            $base = $variant && $variant->getAttribute( 'variant_deleted_at' ) === null ? trim( (string) $variant->path, '/' ) : $this->languages->prefix( $langcode );
            $path = $this->linkPath( $title ?: $link['uuid'], $base, $language['domain'] ?: $this->domain );

            $prefixed = $targets[$langcode];
            $target = str_starts_with( $to, '/' ) && $prefixed !== $to && isset( $found[trim( $prefixed, '/' )] ) ? $prefixed : $to;

            if( $saved = $this->saveVariant( $page, $language, $path, ['to' => $target] + $this->pageData( $title, $path, 1 ), Elements::merge(), $existing ) )
            {
                $variants[$lang] = $saved;
                $this->markSaved( $saved->variant_id );
            }
        }
    }
}
