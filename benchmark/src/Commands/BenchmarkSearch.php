<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Nestedset\NestedSet;


class BenchmarkSearch extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:search
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--seed : Seed benchmark data before running benchmarks}
        {--unseed : Remove search index data and exit}
        {--tries=100 : Number of iterations per benchmark}
        {--force : Force the operation to run in production}';

    protected $description = 'Run search index benchmarks';


    public function handle(): int
    {
        if( !( $options = $this->settings() ) ) {
            return self::FAILURE;
        }

        $tries = $options['tries'];

        if( $options['unseed'] )
        {
            $this->output->write( '  Flushing search index... ' );
            PageView::removeAllFromSearch();
            Element::removeAllFromSearch();
            File::removeAllFromSearch();
            $this->line( 'done' );
            $this->output->write( '  Rebuilding search index... ' );
            Scout::rebuild();
            $this->line( 'done' );
            return self::SUCCESS;
        }

        // Seeding: ensure search index is populated
        if( $this->option( 'seed' ) )
        {
            $this->output->write( '  Indexing pages, elements, and files for search... ' );
            Scout::rebuild();
            $this->line( 'done' );
        }

        $page = PageView::where( 'tag', '!=', 'root' )->orderByDesc( NestedSet::DEPTH )->firstOrFail();

        $this->header();

        $this->benchmark( 'Search pages (pub)', function() {
            PageView::search( 'lorem' )->searchFields( 'content' )->take( 100 )->get();
        }, readOnly: true, tries: $tries, searchSync: true );

        $this->benchmark( 'Search pages (adm)', function() {
            PageView::search( 'lorem' )->searchFields( 'draft' )->take( 100 )->get();
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
