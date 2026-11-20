<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Concerns\Drupal;

use Aimeos\Cms\DB;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Status;
use Aimeos\Cms\Import\Tally;
use Aimeos\Cms\Models\PageView;
use Illuminate\Support\LazyCollection;


/**
 * Imports the redirects of the Drupal site as hidden redirect pages.
 */
trait Redirects
{
    /**
     * Imports the redirects of the Redirect module and the /node/{nid} paths as hidden redirect pages.
     *
     * Redirects of languages with their own domain are created in that domain and language.
     */
    protected function importRedirects( PageView $root ): void
    {
        $redirects = $this->redirects->all( $this->createdPath( ... ), $this->createdPages->keys(), (bool) $this->option( 'node-redirects' ) );
        $tally = new Tally();

        // the redirects are processed in chunks instead of keeping all of them in memory
        foreach( LazyCollection::make( fn() => $redirects )->chunk( DB::IN_SIZE ) as $chunk )
        {
            $lists = $languages = [];

            // later redirects for the same URL path overwrite earlier ones
            foreach( $chunk as $item )
            {
                $lists[$item['domain']][$item['path']] = $item['to'];
                $languages[$item['domain']] = $item['language'];
            }

            foreach( $lists as $domain => $list )
            {
                $language = $languages[$domain] ?? null;
                $list = array_filter( $list, fn( $to, $path ) => $to && $path !== '' && $path !== trim( $to, '/' ), ARRAY_FILTER_USE_BOTH );

                // the existing pages are fetched at once instead of one query per redirect
                $pages = $list ? $this->pages->paths( array_map( 'strval', array_keys( $list ) ), $language['domain'] ?? null ) : [];

                foreach( $list as $path => $to )
                {
                    $label = 'redirect ' . ( $language ? $language['domain'] : '' ) . "/{$path}";
                    $page = $pages[(string) $path] ?? null;

                    $result = $this->transaction( $label, fn() => $this->importRedirect( (string) $path, $to, $root, $page, '', Pages::STATUS_HIDDEN, $language ) );
                    $tally->add( $result['status'] ?? null );
                }
            }
        }

        $this->subtotals( $tally, 'redirects' );
    }


    /**
     * Creates the redirect page or updates the one imported by a previous run.
     *
     * Redirect pages saved before by this import are updated too because later redirects for the same
     * URL path overwrite earlier ones.
     *
     * @param string $path URL path without leading slash
     * @param string $to Redirect target
     * @param PageView $parent Parent page
     * @param PageView|null $page Page using the URL path or NULL if there's none
     * @param string $name Page name or empty string to use the path
     * @param int $status Page status, one of the Pages::STATUS_* constants
     * @param array{lang: string, domain: string}|null $language Language with its own domain or NULL for the imported pages
     * @return array{page: PageView, status: Status}|null Page and its status, NULL if the URL is used by another page
     */
    protected function importRedirect( string $path, string $to, PageView $parent, ?PageView $page, string $name = '', int $status = Pages::STATUS_HIDDEN, ?array $language = null ): ?array
    {
        $data = array_replace( ['to' => $to] + $this->pageData( $name !== '' ? $name : $path, $path, $status ), $language ?? [] );

        if( $page )
        {
            if( !$this->linkOwned( $page, $data ) )
            {
                $this->warn( '  Skipped redirect: ' . ( $language['domain'] ?? '' ) . "/{$path} (URL is used by another page)" );
                return null;
            }

            // the page isn't stale even if updating it fails
            $this->markSaved( $page->variant_id );
        }

        $result = $this->storeLink( $data, $parent, $page );
        $this->markSaved( $result['page']->variant_id );

        return $result;
    }
}
