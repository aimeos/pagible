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
        {--lang=en : Language code}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run theme controller benchmarks';


    public function handle(): int
    {
        if( !$this->validateOptions() ) {
            return self::FAILURE;
        }

        $this->tenant();

        if( !$this->hasSeededData() )
        {
            $this->error( 'No benchmark data found. Run `php artisan cms:benchmark --seed` first.' );
            return self::FAILURE;
        }

        $domain = (string) ( $this->option( 'domain' ) ?: '' );
        $lang = (string) $this->option( 'lang' );

        config( ['scout.driver' => 'cms'] );

        // Get a page with cache=0 for uncached rendering
        $uncachedPage = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
            ->where( 'domain', $domain )->orderByDesc( 'depth' )->firstOrFail();
        $uncachedPage->forceFill( ['cache' => 0] )->saveQuietly();

        // Get a page with cache=5 for cached rendering
        $cachedPage = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
            ->where( 'domain', $domain )->where( 'id', '!=', $uncachedPage->id )
            ->orderByDesc( 'depth' )->firstOrFail();
        $cachedPage->forceFill( ['cache' => 5] )->saveQuietly();

        $this->header();

        // Page render (uncached)
        $this->benchmark( 'Page render', function() use ( $uncachedPage, $domain ) {
            $request = Request::create( '/' . $uncachedPage->path, 'GET' );
            ( new PageController )->index( $request, $uncachedPage->path, $domain );
        }, readOnly: true );

        // Page cached — warm cache first
        $warmRequest = Request::create( '/' . $cachedPage->path, 'GET' );
        ( new PageController )->index( $warmRequest, $cachedPage->path, $domain );

        $this->benchmark( 'Page cached', function() use ( $cachedPage, $domain ) {
            $request = Request::create( '/' . $cachedPage->path, 'GET' );
            ( new PageController )->index( $request, $cachedPage->path, $domain );
        }, readOnly: true );

        // Search
        $this->benchmark( 'Search', function() use ( $domain, $lang ) {
            $request = Request::create( '/cmsapi/search', 'GET', ['q' => 'lorem', 'locale' => $lang, 'size' => 10] );
            ( new SearchController )->index( $request, $domain );
        }, readOnly: true );

        // Sitemap
        $this->benchmark( 'Sitemap', function() {
            ob_start();
            ( new SitemapController )->index()->sendContent();
            ob_end_clean();
        }, readOnly: true );

        $this->line( '' );

        return self::SUCCESS;
    }
}
