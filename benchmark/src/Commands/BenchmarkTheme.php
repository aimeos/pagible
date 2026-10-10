<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Resource;
use Illuminate\Console\Command;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;
use Aimeos\Cms\Access;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Controllers\PageController;
use Aimeos\Cms\Controllers\SearchController;
use Aimeos\Cms\Controllers\SitemapController;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Http\Middleware\ServeCachedPage;
use Aimeos\Nestedset\NestedSet;
use Database\Seeders\BenchmarkSeeder;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;


class BenchmarkTheme extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:theme
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--tries=100 : Number of iterations per benchmark}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run theme controller benchmarks';


    public function handle(): int
    {
        if( $this->option( 'unseed' ) ) {
            return self::SUCCESS;
        }

        $tenant = (string) $this->option( 'tenant' );
        $tries = (int) $this->option( 'tries' );
        $force = (bool) $this->option( 'force' );

        if( !$this->checks( $tenant, $tries, $force ) ) {
            return self::FAILURE;
        }

        $domain = (string) ( $this->option( 'domain' ) ?: '' );
        $multidomain = config( 'cms.multidomain' );

        // Benchmark data is scoped by --domain. Make the synthetic request use the
        // same routing mode, independently of the application's normal configuration.
        config( ['cms.multidomain' => $domain !== ''] );

        try {
            return $this->runBenchmarks( $domain, $tries );
        } finally {
            config( ['cms.multidomain' => $multidomain] );
        }
    }


    private function runBenchmarks( string $domain, int $tries ) : int
    {
        $appurl = (array) parse_url( (string) config( 'app.url' ) );
        $scheme = in_array( $appurl['scheme'] ?? null, ['http', 'https'], true ) ? $appurl['scheme'] : 'http';
        $host = $domain !== '' ? $domain : ( $appurl['host'] ?? 'localhost' );
        $port = isset( $appurl['port'] ) ? ':' . $appurl['port'] : '';
        $baseurl = $scheme . '://' . $host . $port;

        config( ['scout.driver' => 'cms'] );

        // Get a page with cache=0 for uncached rendering
        $uncachedPage = Page::where( 'tag', '!=', 'root' )
            ->wherePublic()
            ->where( 'domain', $domain )->orderByDesc( NestedSet::DEPTH )->firstOrFail();
        Page::withoutSyncingToSearch( fn() => Resource::updatePage( $uncachedPage, ['cache' => 0] ) );

        // Get a page with cache=5 for cached rendering
        $cachedPage = Page::where( 'tag', '!=', 'root' )
            ->wherePublic()
            ->where( 'domain', $domain )->where( 'id', '!=', $uncachedPage->id )
            ->orderByDesc( NestedSet::DEPTH )->firstOrFail();
        Page::withoutSyncingToSearch( fn() => Resource::updatePage( $cachedPage, ['cache' => 5] ) );

        $this->header();
        $middleware = new ServeCachedPage();

        // Page render (uncached)
        $this->benchmark( 'Page render', function() use ( $uncachedPage, $domain, $baseurl, $middleware ) {
            $request = Request::create( $baseurl . '/' . $uncachedPage->path, 'GET' );
            $response = $middleware->handle( $request, fn( $request ) =>
                ( new PageController )->index( $request, $uncachedPage->path, $domain )
            );
            $this->expectStatus( $response, Response::HTTP_OK );
        }, readOnly: true, tries: $tries );

        // Translated page whose navigation falls back to the source language of untranslated pages
        $variantPage = Page::visible( BenchmarkSeeder::TRANSLATION )->where( 'lang', BenchmarkSeeder::TRANSLATION )
            ->where( 'tag', '!=', 'root' )->wherePublic()->where( 'domain', $domain )
            ->orderByDesc( NestedSet::DEPTH )->first();

        if( $variantPage )
        {
            Page::withoutSyncingToSearch( fn() => Resource::updatePage( $variantPage, ['cache' => 0] ) );

            $this->benchmark( 'Variant render', function() use ( $variantPage, $domain, $baseurl, $middleware ) {
                $request = Request::create( $baseurl . '/' . $variantPage->path, 'GET' );
                $response = $middleware->handle( $request, fn( $request ) =>
                    ( new PageController )->index( $request, $variantPage->path, $domain )
                );
                $this->expectStatus( $response, Response::HTTP_OK );
            }, readOnly: true, tries: $tries );
        }

        // Complete-page caching belongs to the pre-session middleware. Warm it by
        // running the controller through that boundary once.
        $warmRequest = Request::create( $baseurl . '/' . $cachedPage->path, 'GET' );
        $warmResponse = $middleware->handle( $warmRequest, fn( $request ) =>
            ( new PageController )->index( $request, $cachedPage->path, $domain )
        );
        $this->expectStatus( $warmResponse, Response::HTTP_OK );

        // True anonymous hot path: middleware returns the complete-page cache before
        // the session middleware and controller are invoked.
        $this->benchmark( 'Page middleware cached', function() use ( $cachedPage, $baseurl, $middleware ) {
            $request = Request::create( $baseurl . '/' . $cachedPage->path, 'GET' );
            $response = $middleware->handle( $request, fn() => throw new \LogicException( 'Unexpected cache miss' ) );
            $this->expectStatus( $response, Response::HTTP_OK );
        }, readOnly: true, tries: $tries );

        // Exercise the catch-all negative path through both middleware and controller.
        $this->benchmark( 'Page missing', function() use ( $domain, $baseurl, $middleware ) {
            $path = '__cms_missing_benchmark__';
            $request = Request::create( $baseurl . '/' . $path, 'GET' );
            $response = $middleware->handle( $request, function( $request ) use ( $domain, $path ) {
                try {
                    return ( new PageController() )->index( $request, $path, $domain );
                } catch( \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e ) {
                    return new Response( '', $e->getStatusCode() );
                }
            } );
            $this->expectStatus( $response, Response::HTTP_NOT_FOUND );
        }, readOnly: true, tries: $tries );

        // Authenticated public pages bypass complete-page caching and render privately.
        $userClass = config( 'auth.providers.users.model', 'App\\Models\\User' );
        $viewer = new $userClass();

        if( !$viewer instanceof \Illuminate\Database\Eloquent\Model
            || !$viewer instanceof \Illuminate\Contracts\Auth\Authenticatable
        ) {
            throw new \RuntimeException( 'User model must be an authenticatable Eloquent model' );
        }

        $viewer->forceFill( ['cmsperms' => []] );
        $this->benchmark( 'Page authenticated', function() use ( $cachedPage, $domain, $baseurl, $middleware, $viewer ) {
            $request = Request::create( $baseurl . '/' . $cachedPage->path, 'GET' );
            $request->headers->set( 'Authorization', 'Bearer benchmark' );
            $request->setUserResolver( fn() => $viewer );
            $response = $middleware->handle( $request, fn( $request ) =>
                ( new PageController() )->index( $request, $cachedPage->path, $domain )
            );
            $this->expectStatus( $response, Response::HTTP_OK );
        }, readOnly: true, tries: $tries );

        $this->benchmarkAccess( $uncachedPage, $domain, $baseurl, $middleware, $tries );

        // Page latest (editor preview via versioned path)
        $user = $this->user();
        $latestRequest = Request::create( '/' . $uncachedPage->path, 'GET' );
        $latestRequest->setUserResolver( fn() => $user );

        $this->benchmark( 'Page latest', function() use ( $uncachedPage, $domain, $latestRequest ) {
            ( new PageController )->index( $latestRequest, $uncachedPage->path, $domain );
        }, readOnly: true, tries: $tries );

        // Search
        $this->benchmark( 'Search', function() use ( $domain ) {
            $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'lorem', 'locale' => 'en', 'size' => 10] );
            ( new SearchController )->index( $request, $domain );
        }, readOnly: true, tries: $tries );

        // Sitemap, the controller reads the domain from the route of the current request
        $sitemapRequest = Request::create( $baseurl . '/sitemap.xml', 'GET' );
        $sitemapRoute = ( new Route( 'GET', 'sitemap.xml', [] ) )->bind( $sitemapRequest );
        $sitemapRoute->setParameter( 'domain', $domain );
        $sitemapRequest->setRouteResolver( fn() => $sitemapRoute );
        $request = app( 'request' );
        app()->instance( 'request', $sitemapRequest );

        try {
            $this->benchmark( 'Sitemap', function() {
                ob_start();
                ( new SitemapController )->index()->sendContent();
                ob_end_clean();
            }, readOnly: true, tries: (int) ceil( $tries / 10 ) );
        } finally {
            app()->instance( 'request', $request );
        }

        $this->line( '' );

        return self::SUCCESS;
    }


    private function benchmarkAccess( Page $page, string $domain, string $baseurl,
        ServeCachedPage $middleware, int $tries ) : void
    {
        $temporaryAccess = !Permission::has( 'access:view' );

        if( $temporaryAccess ) {
            Access::using( static fn() => ['benchmark.frontend'] );
        }

        try
        {
            $restrictedId = $page->getKey();

            if( !is_string( $restrictedId ) ) {
                throw new \RuntimeException( 'Expected a string page ID' );
            }

            PageAccess::set( [$restrictedId], [] );

            // Restricted routes deliberately fall through to the inner web stack;
            // model its unauthenticated response without requiring an application login route.
            try {
                $this->benchmark( 'Page restricted', function() use ( $page, $baseurl, $middleware ) {
                    $request = Request::create( $baseurl . '/' . $page->path, 'GET' );
                    $response = $middleware->handle( $request, fn() =>
                        new Response( '', Response::HTTP_UNAUTHORIZED )
                    );
                    $this->expectStatus( $response, Response::HTTP_UNAUTHORIZED );
                }, readOnly: true, tries: $tries );
            } finally {
                PageAccess::set( [$restrictedId], null );
            }

            // Reuse an application-owned frontend value when one exists. Empty
            // catalogs still exercise authenticated-current-tenant restrictions.
            $available = app( Access::class )->list();
            $values = isset( $available[0] ) ? [$available[0]] : null;
            // The complete benchmark tree exceeds the synchronous bulk limit.
            // Use one generated top-level branch (991 pages for the default data).
            $branch = Page::where( NestedSet::DEPTH, 1 )
                ->where( 'domain', $domain )->orderBy( NestedSet::LFT )->firstOrFail();
            $branchId = $branch->getKey();
            $accessTries = max( 1, (int) ceil( $tries / 10 ) );

            if( !is_string( $branchId ) ) {
                throw new \RuntimeException( 'Expected a string branch page ID' );
            }

            $this->benchmark( 'Access subtree changed', fn() =>
                PageAccess::set( [$branchId], $values ?? [], descendants: true ),
                tries: $accessTries,
            );

            PageAccess::set( [$branchId], $values ?? [], descendants: true );

            try {
                $this->benchmark( 'Access subtree retry', fn() =>
                    PageAccess::set( [$branchId], $values ?? [], descendants: true ),
                    tries: $accessTries,
                );
            } finally {
                PageAccess::set( [$branchId], null, descendants: true );
            }
        }
        finally
        {
            if( $temporaryAccess ) {
                Access::using( null );
            }
        }
    }


    private function expectStatus( mixed $response, int $expected ) : void
    {
        if( !$response instanceof SymfonyResponse || $response->getStatusCode() !== $expected ) {
            throw new \LogicException( sprintf( 'Expected benchmark response status %d.', $expected ) );
        }
    }


}
