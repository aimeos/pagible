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
        {--seed : Seed benchmark data before running benchmarks}
        {--unseed : Remove search index data and exit}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=50 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run search index benchmarks';


    public function handle(): int
    {
        $tenant = (string) $this->option( 'tenant' );
        $tries = (int) $this->option( 'tries' );
        $force = (bool) $this->option( 'force' );

        if( !$this->checks( $tenant, $tries, $force ) ) {
            return self::FAILURE;
        }

        $this->tenant( $tenant );

        if( $this->option( 'unseed' ) )
        {
            $this->output->write( '  Flushing search index... ' );
            Page::removeAllFromSearch();
            Element::removeAllFromSearch();
            File::removeAllFromSearch();
            $this->line( 'done' );
            $this->output->write( '  Rebuilding search index... ' );
            Page::makeAllSearchable();
            Element::makeAllSearchable();
            File::makeAllSearchable();
            $this->line( 'done' );
            return self::SUCCESS;
        }

        if( !$this->hasSeededData() )
        {
            $this->error( 'No benchmark data found. Run `php artisan cms:benchmark --seed` first.' );
            return self::FAILURE;
        }

        // Seeding: ensure search index is populated
        if( $this->option( 'seed' ) )
        {
            $this->output->write( '  Indexing pages, elements, and files for search... ' );
            Page::makeAllSearchable();
            Element::makeAllSearchable();
            File::makeAllSearchable();
            $this->line( 'done' );
        }

        $page = Page::where( 'tag', '!=', 'root' )->orderByDesc( 'depth' )->firstOrFail();

        $this->header();

        $this->benchmark( 'Search pages (pub)', function() {
            Page::search( 'lorem' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, tries: $tries, searchSync: true );

        $this->benchmark( 'Search pages (adm)', function() {
            Page::search( 'lorem' )->searchFields( 'draft' )->take( 100 )->get();
        }, readOnly: true, tries: $tries, searchSync: true );

        $this->benchmark( 'Search elements', function() {
            Element::search( 'footer' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, tries: $tries, searchSync: true );

        $this->benchmark( 'Search files', function() {
            File::search( 'benchmark' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, tries: $tries, searchSync: true );

        $this->benchmark( 'Index page', function() use ( $page ) {
            $page->searchable();
        }, tries: $tries, searchSync: true );

        $this->benchmark( 'Remove from index', function() use ( $page ) {
            $page->unsearchable();
            $page->searchable();
        }, tries: $tries, searchSync: true );

        $this->line( '' );

        return self::SUCCESS;
    }
}
