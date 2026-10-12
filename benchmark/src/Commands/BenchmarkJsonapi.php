<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Models\PageView;
use Aimeos\Nestedset\NestedSet;


class BenchmarkJsonapi extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:jsonapi
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--tries=100 : Number of iterations per benchmark}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run JSON:API benchmarks';


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

        $root = PageView::where( 'tag', 'root' )->where( 'domain', $domain )->firstOrFail();
        $page = PageView::where( 'tag', '!=', 'root' )->orderByDesc( NestedSet::DEPTH )->firstOrFail();

        $this->header();

        $this->benchmark( 'Page list', function() use ( $domain ) {
            PageView::with( ['files', 'elements.files'] )->where( 'domain', $domain )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page detail', function() use ( $page ) {
            PageView::with( ['files', 'elements.files'] )->find( $page->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page w/children', function() use ( $root ) {
            PageView::with( ['children', 'files', 'elements.files'] )->find( $root->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page w/ancestors', function() use ( $page ) {
            PageView::with( ['ancestors', 'files', 'elements.files'] )->find( $page->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page path', function() use ( $page ) {
            PageView::with( ['files', 'elements.files'] )->where( 'path', $page->path )->first();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page domain', function() use ( $domain ) {
            PageView::with( ['files', 'elements.files'] )->where( 'domain', $domain )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page tag', function() {
            PageView::with( ['files', 'elements.files'] )->where( 'tag', 'root' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page lang', function() {
            PageView::with( ['files', 'elements.files'] )->where( 'lang', 'en' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->line( '' );

        return self::SUCCESS;
    }
}
