<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Statamic;

use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Utils;
use Illuminate\Support\Collection;


/**
 * Imports the localized Statamic entries of the other sites as page translations.
 */
trait Translations
{
    /** @var array<string, array{site: string, path: string}> Sites and URL paths of the published localized entries by ID */
    protected array $localPaths = [];
    /** @var array<string, array<string, string>> IDs of the localized entries by site handle and ID of their topmost origin */
    protected array $localIds = [];
    /** @var array<string, string> IDs of the topmost origin entries by ID of the localized entries */
    protected array $roots = [];


    /**
     * Creates or updates the variants of the imported pages from the translations of their entries.
     *
     * @param Collection<array-key, \stdClass> $entries Imported entries
     * @param array<string, PageView> $pages Pages of the imported entries by entry ID
     */
    protected function translate( Collection $entries, array $pages ): void
    {
        if( $this->languages === [] || $pages === [] ) {
            return;
        }

        $entries = $entries->filter( fn( $entry ) => isset( $pages[(string) $entry->id] ) )->values();
        $translations = $this->reader->translations( $entries, array_keys( $this->languages ) );

        foreach( $translations as $id => $rows )
        {
            // translations of entries kept as imported before are only imported if they changed since
            $translations[$id] = array_filter( $rows, fn( $row ) => !$this->unchangedSince( $id ) || $this->modified( $row ) >= $this->since );
        }

        if( ( $translations = array_filter( $translations ) ) === [] ) {
            return;
        }

        $rows = $this->reader->withData( Collection::make( array_merge( ...array_values( array_map( 'array_values', $translations ) ) ) ) )
            ->keyBy( fn( $row ) => (string) $row->id );
        $this->prefetchVariants( array_values( array_map( fn( $id ) => (string) $pages[$id]->id, array_map( 'strval', array_keys( $translations ) ) ) ) );

        foreach( $translations as $id => $sites )
        {
            $page = $pages[(string) $id];
            // the variants of all languages including trashed ones are fetched at once
            $variants = $this->variants( $page );

            foreach( $sites as $site => $translation )
            {
                $language = $this->languages[$site] ?? null;
                $row = $rows->get( (string) $translation->id, $translation );

                if( !$language || $language['lang'] === $this->lang ) {
                    continue;
                }

                // Statamic has no URLs for entries of collections without route for their site
                if( ( !is_string( $row->uri ?? null ) || $row->uri === '' ) && !$this->reader->routable( $collection = (string) ( $row->collection ?? '' ), $site ) )
                {
                    $this->warnOnce( "route:{$collection}:{$site}", "  Skipped the translations of the \"{$collection}\" collection in the \"{$site}\" site because the collection has no route for the site, so they have no URLs" );
                    continue;
                }

                $data = $this->reader->data( $row );
                $title = $this->reader->title( $row, $data );
                // translations changed in Pagible aren't overwritten
                $existing = $variants[$language['lang']] ?? null;

                if( $this->skipsTranslation( $existing, $title, $language['lang'] ) ) {
                    continue;
                }

                // Statamic resolves "@child" by the localized tree which usually has the same structure as the origin tree
                $child = array_key_exists( (string) $id, $this->childIds ) ? $this->childIds[(string) $id] : false;

                $variant = $this->transaction( "[{$row->id}] {$title} [{$language['lang']}]", function() use ( $page, $row, $data, $title, $language, $existing, $id, $child, $site )
                {
                    $home = $this->homeId !== null && (string) $id === (string) $this->homeId;
                    $uri = is_string( $row->uri ?? null ) && $row->uri !== '' ? trim( $row->uri, '/' ) : null;
                    $slug = $this->localPath( (string) $site, $uri ?? ( $home ? '' : ( (string) ( $row->slug ?? '' ) ?: Utils::slugify( $title ) ) ) );

                    $pageData = $this->pageData( $title, $slug, $this->status( $row, $data, $title, $child ), $home ? Pages::TAG_ROOT : Pages::TAG_PAGE );

                    // translations inherit the redirects of their origins and can redirect on their own, redirects have no content
                    if( ( $target = $this->redirect( $data, $child, $title, (string) $site ) ) !== null )
                    {
                        if( $variant = $this->saveVariant( $page, $language, $slug, ['to' => $target] + $pageData, ['elements' => [], 'fileIds' => []], $existing ) ) {
                            $this->info( "  Translated: {$title} (/{$slug} -> {$target}) [{$language['lang']}]" );
                        }

                        return $variant;
                    }

                    ['content' => $content, 'title' => $seoTitle] = $this->pageContent( $row, $data, $title, $child, (string) $site );
                    $pageData = array_replace( $pageData, array_filter( ['title' => $seoTitle] ), ['to' => ''] );

                    if( $variant = $this->saveVariant( $page, $language, $slug, $pageData, $content, $existing ) )
                    {
                        $this->schedule( $variant, $row, $data, $pageData, $content, $child );
                        $this->info( "  Translated: {$title} (/{$slug}) [{$language['lang']}]" );
                    }

                    return $variant;
                } );

                if( $variant ) {
                    // later translations in the same language use the saved variant
                    $variants[$language['lang']] = $variant;
                } else {
                    // links of the following entries don't point to the URL of the skipped translation
                    unset( $this->localPaths[(string) $row->id] );
                }
            }
        }
    }


    /**
     * Fetches the URL paths of the localized entries in the sites of the translations used by links and redirects.
     *
     * Links and redirects in translations refer to the localized entries of their site by the IDs of these entries.
     */
    protected function indexLocalized(): void
    {
        // localized entries can originate from localized entries of sites which aren't imported
        $origins = $this->reader->origins();
        $routable = [];

        foreach( $this->reader->localized( array_keys( $this->languages ) ) as $row )
        {
            $id = (string) $row->id;
            $site = (string) $row->site;
            $origins[$id] = (string) $row->origin_id;

            // Statamic has no URLs for entries of collections without route for their site
            if( ( !is_string( $row->uri ?? null ) || $row->uri === '' ) && !( $routable[$site][(string) $row->collection] ??= $this->reader->routable( (string) $row->collection, $site ) ) ) {
                continue;
            }

            if( ( $path = $this->path( $row ) ) !== null ) {
                $this->localPaths[$id] = ['site' => $site, 'path' => $this->localPath( $site, $path )];
            }
        }

        foreach( array_keys( $origins ) as $id )
        {
            // localized entries can be localizations of other localized entries
            for( $root = (string) $id, $depth = 0; isset( $origins[$root] ) && $depth < 10; $depth++ ) {
                $root = $origins[$root];
            }

            $this->roots[(string) $id] = $root;

            if( $local = $this->localPaths[(string) $id] ?? null ) {
                $this->localIds[$local['site']][$root] ??= (string) $id;
            }
        }
    }


    /**
     * Returns the URL of the page of the entry in the default site.
     *
     * The URL is absolute if the converted entry is a translation whose site has an own domain, so the links
     * in the translation refer to the domain of the default site.
     *
     * @param string $id ID of the entry in the default site
     * @return string|null URL of the page or NULL if the entry has no page
     */
    protected function pageUrl( string $id ): ?string
    {
        if( !isset( $this->paths[$id] ) ) {
            return null;
        }

        $domain = $this->current[1] !== null ? $this->languages[$this->current[1]]['domain'] ?? '' : '';

        return ( $domain !== '' && $this->domain !== '' && $domain !== $this->domain ? 'https://' . $this->domain : '' ) . '/' . $this->paths[$id];
    }


    /**
     * Returns the URL of the localized entry, absolute if its site has an own domain.
     *
     * @param string $id ID of the localized entry
     * @return string|null URL of the entry or NULL if it's no published localized entry with URL
     */
    protected function localUrl( string $id ): ?string
    {
        if( ( $local = $this->localPaths[$id] ?? null ) === null ) {
            return null;
        }

        $domain = $this->languages[$local['site']]['domain'] ?? '';

        return ( $domain !== '' ? 'https://' . $domain : '' ) . '/' . $local['path'];
    }


    /**
     * Returns the URL path of the localized entry prefixed by the URL path of its site.
     *
     * @param string $site Site handle
     * @param string $path URL path of the entry without slashes, empty for the home page
     * @return string URL path without slashes like "de/ueber-uns" or "de" for the home page
     */
    protected function localPath( string $site, string $path ): string
    {
        $prefix = $this->prefixes[$site] ?? '';
        return $prefix !== '' ? trim( $prefix . '/' . $path, '/' ) : $path;
    }
}
