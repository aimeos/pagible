<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Jobs\InvalidatePages;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;


/**
 * Invalidates the cached pages after their content has been published.
 */
class Invalidation
{
    /**
     * Invalidates the routes of the given Page or PageVariant models, grouped by domain.
     *
     * Other values are ignored so lifecycle collections can be passed without additional filtering.
     *
     * @param iterable<array-key, mixed> $pages Candidate Page or PageVariant models
     */
    public static function pages( iterable $pages ) : void
    {
        $paths = [];

        foreach( $pages as $page ) {
            if( $page instanceof PageView || $page instanceof PageVariant ) {
                $paths[(string) $page->domain][] = (string) $page->path;
            }
        }

        self::routes( $paths );
    }


    /**
     * Invalidates the given routes.
     *
     * @param array<string, array<int, string>> $paths URL paths grouped by domain
     */
    public static function routes( array $paths ) : void
    {
        foreach( $paths as $domain => $domainPaths ) {
            PageInvalidated::dispatch( (string) $domain, array_values( array_unique( $domainPaths ) ) );
        }
    }


    /**
     * Invalidates the previous and current route of one changed page.
     *
     * @param PageView $page Page with its current domain and path
     * @param string $domain Previous domain of the page
     * @param string $path Previous URL path of the page
     */
    public static function moved( PageView $page, string $domain, string $path ) : void
    {
        $currentDomain = (string) $page->domain;
        $currentPath = (string) $page->path;

        if( $domain === $currentDomain ) {
            PageInvalidated::dispatch( $domain, $path === $currentPath ? [$path] : [$path, $currentPath] );
        } else {
            PageInvalidated::dispatch( $domain, [$path] );
            PageInvalidated::dispatch( $currentDomain, [$currentPath] );
        }
    }


    /**
     * Invalidates the published pages using the Elements, the Files directly or the Files through an Element.
     *
     * The pages are invalidated by queued jobs, so publishing content shared by many pages doesn't slow down
     * the request. The jobs get the page variant IDs because the references of purged items don't exist any more when they run.
     *
     * @param array<string> $elements Element UUIDs
     * @param array<string> $files File UUIDs
     */
    public static function refs( array $elements, array $files = [] ) : void
    {
        // The page table isn't joined because MySQL can't optimize IN() subqueries with UNION, which would scan all pages.
        // Deleted pages are skipped by the queued jobs instead.
        $db = DB::connection();
        $queries = [];

        if( $elements ) {
            $queries[] = $db->table( 'cms_page_element' )->select( 'variant_id' )->whereIn( 'element_id', $elements );
        }

        if( $files )
        {
            $queries[] = $db->table( 'cms_page_file' )->select( 'variant_id' )->whereIn( 'file_id', $files );
            $queries[] = $db->table( 'cms_element_file as ef' )
                ->join( 'cms_page_element as pe', 'pe.element_id', '=', 'ef.element_id' )
                ->select( 'pe.variant_id' )->whereIn( 'ef.file_id', $files );
        }

        if( !$query = array_shift( $queries ) ) {
            return;
        }

        foreach( $queries as $union ) {
            $query->unionAll( $union );
        }

        // Jobs dispatched after commit are kept in memory until then, so reading the IDs in chunks wouldn't save memory
        foreach( $query->pluck( 'variant_id' )->unique()->chunk( 1000 ) as $chunk ) {
            InvalidatePages::dispatch( Tenancy::value(), $chunk->map( strval( ... ) )->values()->all() )->afterCommit();
        }
    }
}
