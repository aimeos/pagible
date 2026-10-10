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
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Nav;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Navigation;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Tenancy;
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
    public function index( Request $request, string $path = '', string $domain = '' )
    {
        if( $request->route() ) {
            $path = (string) $request->route( 'path', '' );
            $domain = (string) $request->route( 'domain', '' );
        }

        $user = $request->user();

        if( Permission::can( 'page:view', $user ) ) {
            return $this->latest( $path, $domain, $user );
        }

        $route = $request->attributes->get( 'cms.page' );

        if( !$route instanceof Nav && !$user ) {
            $route = Nav::page( $path, $domain );
        }

        if( !$route && !$user ) {
            abort( 404 );
        }

        if( $route?->access_exists && !$user ) {
            throw new AuthenticationException();
        }

        if( $route && !$route->access_exists && ( $to = $route->to ) ) {
            return redirect( $to, Response::HTTP_MOVED_PERMANENTLY );
        }

        $page = $this->published( $path, $domain, $user, $route );

        if( $page->access_exists && !$page->access_allowed ) {
            $this->deny( $user );
        }

        if( $to = $page->to ) {
            return redirect( $to, Response::HTTP_MOVED_PERMANENTLY );
        }

        $request->attributes->set(
            'cms.asset-token-page',
            $page->access_exists ? (string) $page->id : null,
        );

        $html = $this->render( $page, $page->content ?? [], $page->lang, $user );

        // Database-first transition safety: re-read the rule after rendering so a
        // concurrent insert or permission change cannot expose or cache the response.
        $access = Page::query()
            ->select( 'id' )
            ->withAccess( $user )
            ->findOrFail( $page->id );

        if( $access->access_exists && !$access->access_allowed ) {
            $this->deny( $user );
        }

        $response = new Response( $html, 200, array_filter( ['Content-Type' => 'text/html', 'Content-Language' => $page->lang] ) );

        if( $user || $access->access_exists || !$page->cache ) {
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
            'latest.elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            'latest.elements.latest',
            'latest.elements.files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
            'latest.elements.files.latest',
        ];

        // the domain is required to use the version index on domain and path,
        // without domain in the route, the few domains of the site are used instead
        $domains = $domain !== '' ? $domain : Cache::remember( 'cms-domains:' . Tenancy::value(), 60,
            fn() => PageVariant::distinct()->pluck( 'domain' )->map( strval( ... ) )->all()
        );

        // every variant has its own URL, so look up the variant and not only the source variant
        // drafts can use domains which aren't used by any variant yet or aren't in the cached list,
        // then the path is looked up in all domains which is slower but only required for editors
        $page = Page::with( $with )
            ->allVariants()
            ->whereLatest( ['domain' => $domains ?: '', 'path' => $path], true )
            ->first()
            ?? ( $domain === '' ? Page::with( $with )->allVariants()->whereLatest( ['path' => $path], true )->first() : null )
            ?? Page::with( $with )->allVariants()->where( 'domain', $domain )->where( 'path', $path )->firstOrFail();

        $version = $page->latest;

        if( $version ) {
            // The editor preview renders the draft version's content, so resolve files and
            // elements from the version's pivots instead of the page-level pivots (which only
            // reflect the published state and are synced on publish).
            $page->setRelation( 'files', $version->getRelation( 'files' ) );
            $page->setRelation( 'elements', $version->getRelation( 'elements' ) );
        }

        if( $to = $version?->data->to ?? $page->to ) {
            return redirect( $to, Response::HTTP_MOVED_PERMANENTLY );
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
     * Loads the published page and its access decision in the same query.
     *
     * @param string $path Page URL segment
     * @param string $domain Requested domain
     * @param Authenticatable|null $user Frontend user
     * @param Nav|null $route Preloaded anonymous route projection
     * @return Page Published page with access decision attributes
     */
    protected function published( string $path, string $domain, ?Authenticatable $user, ?Nav $route ) : Page
    {
        $query = Page::with( [
            'files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
            'elements' => fn( $q ) => $q->select( Element::SELECT_COLUMNS ),
            'elements.files' => fn( $q ) => $q->select( File::SELECT_COLUMNS ),
        ] )
            ->withGlobalScope( 'status', new Status() )
            ->withAccess( $user );

        if( $route && $route->variant_id ) {
            return $query->variant( (string) $route->variant_id )->findOrFail( $route->id );
        }

        return $query->allVariants()->where( 'domain', $domain )->where( 'path', $path )->firstOrFail();
    }


    /**
     * Renders published and preview pages through the same view preparation path.
     *
     * @param mixed $value Structured page content
     * @param Authenticatable|null $user Frontend user
     * @return string Rendered HTML
     */
    protected function render( Page $page, mixed $value, string $locale, ?Authenticatable $user ) : string
    {
        Theme::locale( $locale );
        Paginator::defaultView( 'cms::pagination' );
        Paginator::defaultSimpleView( 'cms::pagination-simple' );

        $content = collect( (array) $value )->groupBy( 'group' );
        $theme = Theme::views( cms( $page, 'theme' ) ?: 'cms' );
        $type = cms( $page, 'type' ) ?: 'page';
        $views = [$theme . '::layouts.' . $type, 'cms::layouts.' . $type, 'cms::layouts.page'];
        $nav = new Navigation( $page, $user );

        return view()->first( $views, compact( 'page', 'content', 'theme', 'nav' ) )->render();
    }


    /**
     * Rejects a denied page request.
     *
     * @param Authenticatable|null $user Frontend user
     * @throws AuthenticationException If no user is authenticated
     */
    private function deny( ?Authenticatable $user ) : never
    {
        if( !$user ) {
            throw new AuthenticationException();
        }

        abort( 403 );
    }
}
