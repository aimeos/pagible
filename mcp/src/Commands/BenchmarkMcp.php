<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class BenchmarkMcp extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:mcp
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run MCP tool benchmarks';


    public function handle(): int
    {
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
        $lang = (string) $this->option( 'lang' );
        $conn = config( 'cms.db', 'sqlite' );

        config( ['scout.driver' => 'cms'] );

        // Wrap everything in a transaction for user cleanup
        DB::connection( $conn )->beginTransaction();

        try
        {
            $user = $this->user();

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();

            $count = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->count();
            $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
                ->orderBy( '_lft' )->skip( (int) floor( $count / 2 ) )->firstOrFail();

            // Query pre-seeded soft-deleted page for RestorePage
            $trashedPage = Page::onlyTrashed()->where( 'lang', $lang )->firstOrFail();

            // Create unpublished version for PublishPage
            $unpubVersion = $page->versions()->forceCreate( [
                'lang' => $lang,
                'data' => (array) $page->latest?->data,
                'aux' => (array) $page->latest?->aux,
                'published' => false,
                'editor' => 'benchmark',
            ] );
            $page->forceFill( ['latest_id' => $unpubVersion->id] )->saveQuietly();
            $page->setRelation( 'latest', $unpubVersion );

            $this->header();


            /**
             * Read operations
             */

            $this->benchmark( 'Get page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPage::class, ['id' => $page->id] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Get page tree', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPageTree::class, ['lang' => $lang] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'List pages', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\ListPages::class, ['lang' => $lang] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Search pages', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchPages::class, ['lang' => $lang, 'term' => 'lorem'] );
            }, readOnly: true, tries: $tries );


            /**
             * Write operations
             */

            $this->benchmark( 'Add page', function() use ( $user, $root, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddPage::class, [
                    'parent' => $root->id, 'lang' => $lang,
                    'name' => 'MCP Bench', 'title' => 'MCP Bench',
                    'path' => 'mcp-bench-' . Utils::uid(),
                ] );
            }, tries: $tries );

            $this->benchmark( 'Save page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
                    'id' => $page->id, 'title' => 'Updated',
                ] );
            }, tries: $tries );

            $this->benchmark( 'Publish page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishPage::class, ['id' => $page->id] );
            }, tries: $tries );

            $this->benchmark( 'Drop page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropPage::class, ['id' => $page->id] );
            }, tries: $tries );

            $this->benchmark( 'Restore page', function() use ( $user, $trashedPage ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestorePage::class, ['id' => $trashedPage->id] );
            }, tries: $tries );

            $this->line( '' );
        }
        finally
        {
            DB::connection( $conn )->rollBack();
        }

        return self::SUCCESS;
    }
}
