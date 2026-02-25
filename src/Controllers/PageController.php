<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Scopes\Status;


class PageController extends Controller
{
    /**
     * Show the page for a given URL.
     *
     * For logged-in used with editor privileges, the latest version of the page is shown,
     * for all other users, the published version of the page is shown.
     *
     * If the page has no GET/POST parameters, the HTML is cached for the duration of the
     * page's cache time. Otherwise, the page is not cached to ensure that dynamic content
     * is always up-to-date. Proxy servers are allowed to cache pages with GET parameters
     * nevertheless because using the same parameters must always return the same content.
     *
     * @param Request $request The current HTTP request instance
     * @param string $path Page URL segment
     * @param string $domain Requested domain
     * @return Response|RedirectResponse Response of the controller action
     */
    public function index( Request $request, string $path, string $domain = '' )
    {
        if( Permission::can( 'page:view', $request->user() ) ) {
            return $this->latest( $path, $domain );
        }

        $cache = Cache::store( config( 'cms.cache', 'file' ) );
        $key = Page::key( $path, $domain );
        $args = $request->input();

        if( empty( $args ) && $request->isMethod( 'GET' ) && ( $html = $cache->get( $key ) ) )
        {
            return response( $html, 200 )
                ->header( 'Content-Type', 'text/html' )
                ->header( 'Cache-Control', 'public, max-age=' . ( $this->cache( $html ) * 60 ) );
        }

        $page = Page::withGlobalScope('status', new Status)
            ->where( 'domain', $domain )
            ->where( 'path', $path )
            ->firstOrFail();

        if( $to = $page->to ) {
            return str_starts_with( $to, 'http' ) ? redirect()->away( $to ) : redirect()->to( $to );
        }

        App::setLocale( $page->lang );
        Paginator::useBootstrap(); // Use Bootstrap CSS classes for pagination links

        $content = collect( (array) ($page->content ?? []) )->groupBy( 'group' );
        $theme = cms( $page, 'theme' ) ?: 'cms';
        $type = cms( $page, 'type' ) ?: 'page';

        $views = [$theme . '::layouts.' . $type, 'cms::layouts.page'];
        $html = view()->first( $views, ['page' => $page, 'content' => $content] )->render();

        if( empty( $args ) && $request->isMethod( 'GET' ) && $page->cache ) {
            $cache->put( $key, $html . '<!--:' . $page->cache, now()->addMinutes( (int) $page->cache ) );
        }

        $response = ( new Response( $html, 200 ) )->header( 'Content-Type', 'text/html' );

        if( $request->isMethod( 'GET' ) ) {
            $response->header( 'Cache-Control', 'public, max-age=' . ( $page->cache * 60 ) );
        }

        return $response;
    }


    /**
     * Extracts the cache time from the HTML content.
     *
     * This method looks for a specific comment in the HTML that indicates
     * the cache duration in minutes. If found, it returns the cache time as
     * an integer. If the comment is not present, it returns 0.
     *
     * @param string $html The HTML content to search for the cache comment
     * @return int The cache time in minutes, or 0 if not found
     */
    protected function cache( string $html ) : int
    {
        if( ( $pos = strpos( $html, '<!--:', -10 ) ) !== false ) {
            return (int) substr( $html, $pos + 5 );
        }

        return 0;
    }


    /**
     * Returns the latest version of the page for a given URL.
     *
     * This method is used for previewing the latest changes made to a page
     * for authenticated users with editor permissions.
     *
     * @param string $path Page URL segment
     * @param string $domain Requested domain
     * @return Response|RedirectResponse Response of the controller action
     */
    protected function latest( string $path, string $domain )
    {
        $version = Version::where( 'versionable_type', Page::class )
            ->where( 'data->domain', $domain )
            ->where( 'data->path', $path )
            ->orderByDesc( 'id' )
            ->take( 1 )
            ->first();

        $page = $version
            ? Page::where( 'id', $version->versionable_id )->firstOrFail()
            : Page::where( 'domain', $domain )->where( 'path', $path )->firstOrFail();

        if( $to = $version?->data->to ?? $page->to ) {
            return str_starts_with( $to, 'http' ) ? redirect()->away( $to ) : redirect()->to( $to );
        }

        $page->cache = 0; // don't cache sub-parts in preview requests

        App::setLocale( $version?->data->lang ?? $page->lang );
        Paginator::useBootstrap();

        $theme = cms( $page, 'theme' ) ?: 'cms';
        $type = cms( $page, 'type' ) ?: 'page';

        $content = collect( (array) ($version->aux->content ?? $page->content ?? []) )->groupBy( 'group' );

        $views = [$theme . '::layouts.' . $type, 'cms::layouts.page'];
        $html = view()->first( $views, ['page' => $page, 'content' => $content] )->render();

        return ( new Response( $html, 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', 'private, max-age=0' );
    }
}
