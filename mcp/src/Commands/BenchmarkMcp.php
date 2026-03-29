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
        {--seed-only : Only seed, skip benchmarks}
        {--test-only : Only run benchmarks, skip seeding}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run MCP tool benchmarks';


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
        $conn = config( 'cms.db', 'sqlite' );

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

            $user->forceFill( [
                'name' => 'Benchmark User',
                'email' => 'benchmark@cms.benchmark',
                'password' => bcrypt( Str::random( 64 ) ),
                'cmsperms' => ['*'],
            ] )->save();

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
            $pages = Page::where( 'depth', 3 )->where( 'lang', $lang )->take( 200 )->get();

            // Preconditions: soft-delete pages for RestorePage
            $trashedPages = Page::where( 'depth', 3 )->where( 'lang', $lang )->skip( 200 )->take( 200 )->get();
            $trashedPages->each( fn( $p ) => $p->delete() );

            // Create unpublished versions for PublishPage
            $unpublishedPages = $pages->take( 100 );
            foreach( $unpublishedPages as $page )
            {
                if( !$page instanceof Page ) {
                    continue;
                }

                $version = $page->versions()->forceCreate( [
                    'lang' => $lang,
                    'data' => (array) $page->latest?->data,
                    'aux' => (array) $page->latest?->aux,
                    'published' => false,
                    'editor' => 'benchmark',
                ] );
                $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
                $page->setRelation( 'latest', $version );
            }

            $this->header();

            // Read benchmarks
            $pageIdx = 0;
            $this->benchmark( 'Get page', function() use ( $user, $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPage::class, ['id' => $page->id] );
                }

                $pageIdx++;
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

            // Write benchmarks — each creates a fresh tool instance per call (numcalls guard)
            $this->benchmark( 'Add page', function() use ( $user, $root, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddPage::class, [
                    'parent' => $root->id, 'lang' => $lang,
                    'name' => 'MCP Bench', 'title' => 'MCP Bench',
                    'path' => 'mcp-bench-' . Utils::uid(),
                ] );
            } );

            $pageIdx = 0;
            $this->benchmark( 'Save page', function() use ( $user, $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
                        'id' => $page->id, 'title' => 'Updated ' . $pageIdx,
                    ] );
                }

                $pageIdx++;
            } );

            $pubIdx = 0;
            $this->benchmark( 'Publish page', function() use ( $user, $unpublishedPages, &$pubIdx ) {
                $page = $unpublishedPages[$pubIdx % $unpublishedPages->count()];

                if( $page instanceof Page ) {
                    CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishPage::class, ['id' => $page->id] );
                }

                $pubIdx++;
            } );

            $pageIdx = 0;
            $this->benchmark( 'Drop page', function() use ( $user, $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropPage::class, ['id' => $page->id] );
                }

                $pageIdx++;
            } );

            $trashIdx = 0;
            $this->benchmark( 'Restore page', function() use ( $user, $trashedPages, &$trashIdx ) {
                $page = $trashedPages[$trashIdx % $trashedPages->count()];

                if( $page instanceof Page ) {
                    CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestorePage::class, ['id' => $page->id] );
                }

                $trashIdx++;
            } );

            $this->line( '' );
        }
        finally
        {
            DB::connection( $conn )->rollBack();
        }

        return 0;
    }
}
