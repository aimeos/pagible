<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


class BenchmarkSearch extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:search
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run search index benchmarks';


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

        // Seeding: ensure search index is populated
        if( $this->option( 'seed' ) )
        {
            $this->info( '  Indexing pages, elements, and files for search...' );
            Page::makeAllSearchable();
            Element::makeAllSearchable();
            File::makeAllSearchable();
            $this->info( '  Search indexing complete.' );
        }

        $lang = (string) $this->option( 'lang' );
        $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->orderByDesc( 'depth' )->firstOrFail();

        $this->header();

        $this->benchmark( 'Search pages (pub)', function() {
            Page::search( 'lorem' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, searchSync: true );

        $this->benchmark( 'Search pages (adm)', function() {
            Page::search( 'lorem' )->searchFields( 'draft' )->take( 100 )->get();
        }, readOnly: true, searchSync: true );

        $this->benchmark( 'Search elements', function() {
            Element::search( 'footer' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, searchSync: true );

        $this->benchmark( 'Search files', function() {
            File::search( 'benchmark' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, searchSync: true );

        $this->benchmark( 'Index page', function() use ( $page ) {
            $page->searchable();
        }, searchSync: true );

        $this->benchmark( 'Remove from index', function() use ( $page ) {
            $page->unsearchable();
            $page->searchable();
        }, searchSync: true );

        $this->line( '' );

        return self::SUCCESS;
    }
}
