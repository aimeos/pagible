<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Http\Request;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Controllers\PageController;
use Aimeos\Cms\Controllers\SearchController;
use Aimeos\Cms\Controllers\SitemapController;
use Aimeos\Cms\Models\Page;


class BenchmarkTheme extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:theme
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=50 : Rows per bulk insert batch}
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

        $this->tenant( $tenant );

        if( !$this->hasSeededData() )
        {
            $this->error( 'No benchmark data found. Run `php artisan cms:benchmark --seed` first.' );
            return self::FAILURE;
        }

        $domain = (string) ( $this->option( 'domain' ) ?: '' );

        config( ['scout.driver' => 'cms'] );

        // Get a page with cache=0 for uncached rendering
        $uncachedPage = Page::where( 'tag', '!=', 'root' )
            ->where( 'domain', $domain )->orderByDesc( 'depth' )->firstOrFail();
        $uncachedPage->forceFill( ['cache' => 0] )->saveQuietly();

        // Get a page with cache=5 for cached rendering
        $cachedPage = Page::where( 'tag', '!=', 'root' )
            ->where( 'domain', $domain )->where( 'id', '!=', $uncachedPage->id )
            ->orderByDesc( 'depth' )->firstOrFail();
        $cachedPage->forceFill( ['cache' => 5] )->saveQuietly();

        $this->header();

        // Page render (uncached)
        $this->benchmark( 'Page render', function() use ( $uncachedPage, $domain ) {
            $request = Request::create( '/' . $uncachedPage->path, 'GET' );
            ( new PageController )->index( $request, $uncachedPage->path, $domain );
        }, readOnly: true, tries: $tries );

        // Page cached — warm cache first
        $warmRequest = Request::create( '/' . $cachedPage->path, 'GET' );
        ( new PageController )->index( $warmRequest, $cachedPage->path, $domain );

        $this->benchmark( 'Page cached', function() use ( $cachedPage, $domain ) {
            $request = Request::create( '/' . $cachedPage->path, 'GET' );
            ( new PageController )->index( $request, $cachedPage->path, $domain );
        }, readOnly: true, tries: $tries );

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

        // Sitemap
        $this->benchmark( 'Sitemap', function() {
            ob_start();
            ( new SitemapController )->index()->sendContent();
            ob_end_clean();
        }, readOnly: true, tries: (int) ceil( $tries / 10 ) );

        $this->line( '' );

        return self::SUCCESS;
    }
}
