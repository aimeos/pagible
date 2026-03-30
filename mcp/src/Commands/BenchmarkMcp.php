<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
        $conn = config( 'cms.db', 'sqlite' );

        config( ['scout.driver' => 'cms'] );

        // Wrap everything in a transaction for user cleanup
        DB::connection( $conn )->beginTransaction();

        try
        {
            // Create benchmark user
            $userClass = config( 'auth.providers.users.model', 'App\\Models\\User' );
            $user = new $userClass();

            if( !$user instanceof \Illuminate\Foundation\Auth\User ) {
                throw new \RuntimeException( 'User model must extend Illuminate\Foundation\Auth\User' );
            }

            $user->mergeCasts( ['cmsperms' => 'array'] );
            $user->forceFill( [
                'name' => 'Benchmark User',
                'email' => 'benchmark@cms.benchmark',
                'password' => bcrypt( Str::random( 64 ) ),
                'cmsperms' => ['*'],
            ] )->save();

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
            $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->orderByDesc( 'depth' )->firstOrFail();

            // Preconditions: soft-delete a page for RestorePage
            $excludeIds = $page->ancestors()->pluck( 'id' )->push( $page->id );
            $trashedPage = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
                ->whereNotIn( 'id', $excludeIds )->orderByDesc( 'depth' )->firstOrFail();
            $trashedPage->delete();

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
            }, readOnly: true );

            $this->benchmark( 'Get page tree', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPageTree::class, ['lang' => $lang] );
            }, readOnly: true );

            $this->benchmark( 'List pages', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\ListPages::class, ['lang' => $lang] );
            }, readOnly: true );

            $this->benchmark( 'Search pages', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchPages::class, ['lang' => $lang, 'term' => 'lorem'] );
            }, readOnly: true );


            /**
             * Write operations
             */

            $this->benchmark( 'Add page', function() use ( $user, $root, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddPage::class, [
                    'parent' => $root->id, 'lang' => $lang,
                    'name' => 'MCP Bench', 'title' => 'MCP Bench',
                    'path' => 'mcp-bench-' . Utils::uid(),
                ] );
            } );

            $this->benchmark( 'Save page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
                    'id' => $page->id, 'title' => 'Updated',
                ] );
            } );

            $this->benchmark( 'Publish page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishPage::class, ['id' => $page->id] );
            } );

            $this->benchmark( 'Drop page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropPage::class, ['id' => $page->id] );
            } );

            $this->benchmark( 'Restore page', function() use ( $user, $trashedPage ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestorePage::class, ['id' => $trashedPage->id] );
            } );

            $this->line( '' );
        }
        finally
        {
            DB::connection( $conn )->rollBack();
        }

        return self::SUCCESS;
    }
}
