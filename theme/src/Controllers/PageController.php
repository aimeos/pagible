<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\App;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Navigation;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Theme;


class PageController extends Controller
{
    /**
     * Issues a CSRF token and starts the session on demand.
     *
     * Cacheable pages can omit the per-session token from their HTML and fetch it
     * only when a visitor actually submits a form. See theme/public/csrf.js.
     *
     * @return JsonResponse JSON response containing the CSRF token
     */
    public function csrf() : JsonResponse
    {
        return response()->json( ['token' => csrf_token()] );
    }


    /**
     * Show the page for a given URL.
     *
     * For logged-in used with editor privileges, the latest version of the page is shown,
     * for all other users, the published version of the page is shown.
     *
     * Pages without query parameters can also be cached by the application. Proxy servers
     * may cache pages with query parameters because repeated requests with the same URL
     * must return the same content.
     *
     * @param Request $request The current HTTP request instance
     * @param string $path Page URL segment
     * @param string $domain Requested domain
     * @return Response|RedirectResponse Response of the controller action
     */
    public function index( Request $request, string $path, string $domain = '' )
    {
        $user = $request->user();

        if( Permission::can( 'page:view', $user ) ) {
            return $this->latest( $path, $domain, $user );
        }

        $route = $request->attributes->get( 'cms.page' );

        if( !$route instanceof Nav ) {
            $route = Nav::page( $path, $domain );
        }

        if( !$route ) {
            abort( 404 );
        }

        if( $route->access_exists ) {
            if( !$user ) {
                throw new AuthenticationException();
            }

            if( !PageAccess::allows( $route->access, $user ) ) {
                abort( 403 );
            }
        }

        if( $to = $route->to ) {
            return str_starts_with( $to, 'http' ) ? redirect()->away( $to ) : redirect()->to( $to );
        }

        $page = Page::with( [
            'files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
            'elements' => fn( $q ) => $q->select( [...Element::SELECT_COLUMNS, 'name'] ),
        ] )
            ->withGlobalScope( 'status', new Status )
            ->findOrFail( $route->id );

        $html = $this->render( $page, $page->content ?? [], $page->lang, $user );

        // Database-first transition safety: re-read the rule after rendering so a
        // concurrent insert or permission change cannot expose or cache the response.
        $currentAccess = $route->access_exists ? $page->access()->get() : null;

        if( $currentAccess === null && $page->access()->exists() ) {
            $currentAccess = $page->access()->get();
        }

        if( $currentAccess?->isNotEmpty() ) {
            if( !$user ) {
                throw new AuthenticationException();
            }

            if( !PageAccess::allows( $currentAccess, $user ) ) {
                abort( 403 );
            }
        }

        $response = new Response( $html, 200, ['Content-Type' => 'text/html'] );

        if( $user || $currentAccess?->isNotEmpty() || !$page->cache ) {
            return $response->header( 'Cache-Control', 'no-store, private' );
        }

        $maxage = (int) $page->cache * 60;

        return $response
            ->header( 'Cache-Control', "public, s-maxage={$maxage}, max-age=0, must-revalidate" )
            ->setExpires( now()->addSeconds( $maxage ) );
    }


    /**
     * Returns the latest version of the page for a given URL.
     *
     * This method is used for previewing the latest changes made to a page
     * for authenticated users with editor permissions.
     *
     * @param string $path Page URL segment
     * @param string $domain Requested domain
     * @param Authenticatable|null $user Authenticated editor
     * @return Response|RedirectResponse Response of the controller action
     */
    protected function latest( string $path, string $domain, ?Authenticatable $user )
    {
        $with = [
            'latest',
            'latest.files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
            'latest.files.latest',
            'latest.elements' => fn( $q ) => $q->select( [...Element::SELECT_COLUMNS, 'name'] ),
            'latest.elements.latest',
            'latest.elements.files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
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

        $html = $this->render(
            $page,
            $version->aux->content ?? $page->content ?? [],
            $version?->data->lang ?? $page->lang,
            $user,
        );

        return ( new Response( $html, 200 ) )
            ->header( 'Content-Type', 'text/html' )
            ->header( 'Cache-Control', 'private, max-age=0' );
    }


    /**
     * Renders published and preview pages through the same view preparation path.
     */
    protected function render( Page $page, mixed $value, string $locale, ?Authenticatable $user ) : string
    {
        App::setLocale( $locale );
        Paginator::useBootstrap();

        $content = collect( (array) $value )->groupBy( 'group' );
        $theme = Theme::views( cms( $page, 'theme' ) ?: 'cms' );
        $type = cms( $page, 'type' ) ?: 'page';
        $views = [$theme . '::layouts.' . $type, 'cms::layouts.' . $type, 'cms::layouts.page'];
        $nav = new Navigation( $page, $user );

        return view()->first( $views, compact( 'page', 'content', 'theme', 'nav' ) )->render();
    }
}
