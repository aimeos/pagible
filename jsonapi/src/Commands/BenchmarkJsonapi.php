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
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run JSON:API benchmarks';


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

        $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
        $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->orderByDesc( 'depth' )->firstOrFail();

        $this->header();

        $this->benchmark( 'Page list', function() use ( $domain ) {
            Page::with( ['files', 'elements.files'] )->where( 'domain', $domain )->take( 100 )->get();
        }, readOnly: true );

        $this->benchmark( 'Page detail', function() use ( $page ) {
            Page::with( ['files', 'elements.files'] )->find( $page->id );
        }, readOnly: true );

        $this->benchmark( 'Page w/children', function() use ( $root ) {
            Page::with( ['children', 'files', 'elements.files'] )->find( $root->id );
        }, readOnly: true );

        $this->benchmark( 'Page w/ancestors', function() use ( $page ) {
            Page::with( ['ancestors', 'files', 'elements.files'] )->find( $page->id );
        }, readOnly: true );

        $this->line( '' );

        return self::SUCCESS;
    }
}
