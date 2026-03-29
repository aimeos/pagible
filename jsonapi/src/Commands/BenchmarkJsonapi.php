<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Models\Page;


class BenchmarkJsonapi extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:jsonapi
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed-only : Only seed, skip benchmarks}
        {--test-only : Only run benchmarks, skip seeding}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run JSON:API benchmarks';


    public function handle(): int
    {
        if( !$this->validateOptions() ) {
            return 1;
        }

        $this->tenant();

        if( !$this->hasSeededData() )
        {
            $this->error( 'No benchmark data found. Run `php artisan cms:benchmark --seed-only` first.' );
            return 1;
        }

        if( $this->option( 'seed-only' ) ) {
            return 0;
        }

        $domain = (string) ( $this->option( 'domain' ) ?: '' );
        $lang = (string) $this->option( 'lang' );

        $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
        $page = Page::where( 'depth', 3 )->where( 'lang', $lang )->firstOrFail();

        $this->header();

        $this->benchmark( 'Page list', function() use ( $domain ) {
            Page::with( ['files', 'elements.files'] )->where( 'domain', $domain )->take( 100 )->get();
        }, readOnly: true );

        $this->benchmark( 'Page detail', function() use ( $page ) {
            Page::with( ['files', 'elements.files'] )->find( $page->id );
        }, readOnly: true );

        $this->benchmark( 'Page with children', function() use ( $root ) {
            Page::with( ['children', 'files', 'elements.files'] )->find( $root->id );
        }, readOnly: true );

        $this->benchmark( 'Page with ancestors', function() use ( $page ) {
            Page::with( ['ancestors', 'files', 'elements.files'] )->find( $page->id );
        }, readOnly: true );

        $this->line( '' );

        return 0;
    }
}
