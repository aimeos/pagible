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
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Theme;


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

        $cache = Cache::store( config( 'cms.theme.cache', 'file' ) );
        $key = Page::key( $path, $domain );
        $np = empty( $request->input() );

        if( $np && $request->isMethod( 'GET' ) && ( $html = $cache->get( $key ) ) )
        {
            return response( $this->inject( $html ), 200 )
                ->header( 'Content-Type', 'text/html' )
                ->header( 'Expires', substr( $html, -29 ) );
        }

        $page = Page::with( [
            'files' => fn( $q ) => $q->select( File::SELECT_COLS ),
            'elements' => fn( $q ) => $q->select( [...Element::SELECT_COLS, 'name'] ),
        ] )
            ->withGlobalScope('status', new Status)
            ->where( 'domain', $domain )
            ->where( 'path', $path )
            ->firstOrFail();

        if( $to = $page->to ) {
            return str_starts_with( $to, 'http' ) ? redirect()->away( $to ) : redirect()->to( $to );
        }

        App::setLocale( $page->lang );
        Paginator::useBootstrap(); // Use Bootstrap CSS classes for pagination links

        $content = collect( (array) ($page->content ?? []) )->groupBy( 'group' );
        $theme = Theme::views( cms( $page, 'theme' ) ?: 'cms' );
        $type = cms( $page, 'type' ) ?: 'page';

        $views = [$theme . '::layouts.' . $type, 'cms::layouts.' . $type, 'cms::layouts.page'];
        $html = view()->first( $views, ['page' => $page, 'content' => $content, 'theme' => $theme] )->render();

        $expires = gmdate( 'D, d M Y H:i:s', time() + (int) $page->cache * 60 ) . ' GMT';

        if( $np && $request->isMethod( 'GET' ) && $page->cache ) {
            $cache->put( $key, $html . '<!-- ' . $expires, now()->addMinutes( (int) $page->cache ) );
        }

        $response = ( new Response( $this->inject( $html ), 200 ) )->header( 'Content-Type', 'text/html' );

        if( $request->isMethod( 'GET' ) ) {
            $response->header( 'Expires', $expires );
        }

        return $response;
    }


    /**
     * Replaces the cache-safe CSRF token and CSP nonce placeholders with fresh per-request values.
     *
     * The rendered page HTML is cached verbatim and served to every visitor, so it must not embed
     * a session-bound CSRF token or a reusable CSP nonce. The layout/forms emit placeholders and
     * this injects a unique nonce and the current request's CSRF token into each response (on both
     * cache miss and cache hit).
     *
     * @param string $html Rendered HTML containing the placeholders
     * @return string HTML with per-request token and nonce
     */
    protected function inject( string $html ) : string
    {
        return str_replace(
            ['%%CMS_NONCE%%', '%%CMS_CSRF%%'],
            [base64_encode( random_bytes( 16 ) ), (string) csrf_token()],
            $html
        );
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
        $with = [
            'latest',
            'latest.files' => fn( $q ) => $q->select( File::SELECT_COLS ),
            'latest.files.latest',
            'latest.elements' => fn( $q ) => $q->select( [...Element::SELECT_COLS, 'name'] ),
            'latest.elements.latest',
            'latest.elements.files' => fn( $q ) => $q->select( File::SELECT_COLS ),
            'latest.elements.files.latest',
        ];

        $page = Page::with( $with )
            ->whereLatest( ['path' => $path] + ( $domain !== '' ? ['domain' => $domain] : [] ) )
            ->first()
            ?? Page::with( $with )->where( 'domain', $domain )->where( 'path', $path )->firstOrFail();

        $version = $page->latest;

        if( $version ) {
            // The editor preview renders the draft version's content, so resolve files and
            // elements from the version's pivots instead of the page-level pivots (which only
            // reflect the published state and are synced on publish).
            $page->setRelation( 'files', $version->getRelation( 'files' ) );
            $page->setRelation( 'elements', $version->getRelation( 'elements' ) );
        }

        if( $to = $version?->data->to ?? $page->to ) {
            return str_starts_with( $to, 'http' ) ? redirect()->away( $to ) : redirect()->to( $to );
        }

        $page->cache = 0; // don't cache sub-parts in preview requests

        App::setLocale( $version?->data->lang ?? $page->lang );
        Paginator::useBootstrap();

        $theme = Theme::views( cms( $page, 'theme' ) ?: 'cms' );
        $type = cms( $page, 'type', 'page' );

        $content = collect( (array) ($version->aux->content ?? $page->content ?? []) )->groupBy( 'group' );

        $views = [$theme . '::layouts.' . $type, 'cms::layouts.' . $type, 'cms::layouts.page'];
        $html = view()->first( $views, ['page' => $page, 'content' => $content, 'theme' => $theme] )->render();

        return ( new Response( $this->inject( $html ), 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', 'private, max-age=0' );
    }
}
