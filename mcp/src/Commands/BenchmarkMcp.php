<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
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
        {--chunk=50 : Rows per bulk insert batch}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run MCP tool benchmarks';


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

            // ── Page setup ────────────────────────────────────────────

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();

            $count = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->count();
            $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
                ->orderBy( '_lft' )->skip( (int) floor( $count / 2 ) )->firstOrFail();

            $trashedPage = Page::onlyTrashed()->where( 'lang', $lang )->firstOrFail();

            $unpubVersion = $page->versions()->forceCreate( [
                'lang' => $lang,
                'data' => (array) $page->latest?->data,
                'aux' => (array) $page->latest?->aux,
                'published' => false,
                'editor' => 'benchmark',
            ] );
            $page->forceFill( ['latest_id' => $unpubVersion->id] )->saveQuietly();
            $page->setRelation( 'latest', $unpubVersion );

            // ── Element setup ─────────────────────────────────────────

            $element = Element::where( 'editor', 'benchmark' )->firstOrFail();
            $trashedElement = Element::onlyTrashed()->where( 'editor', 'benchmark' )->firstOrFail();

            $unpubElVersion = $element->versions()->forceCreate( [
                'lang' => $lang,
                'data' => (array) $element->latest?->data,
                'aux' => (array) $element->latest?->aux,
                'published' => false,
                'editor' => 'benchmark',
            ] );
            $element->forceFill( ['latest_id' => $unpubElVersion->id] )->saveQuietly();
            $element->setRelation( 'latest', $unpubElVersion );

            // ── File setup ────────────────────────────────────────────

            $file = File::where( 'editor', 'benchmark' )->firstOrFail();
            $trashedFile = File::onlyTrashed()->where( 'editor', 'benchmark' )->firstOrFail();

            $unpubFileVersion = $file->versions()->forceCreate( [
                'lang' => $lang,
                'data' => (array) $file->latest?->data,
                'aux' => (array) $file->latest?->aux,
                'published' => false,
                'editor' => 'benchmark',
            ] );
            $file->forceFill( ['latest_id' => $unpubFileVersion->id] )->saveQuietly();
            $file->setRelation( 'latest', $unpubFileVersion );

            Http::fake( ['*' => Http::response( 'benchmark', 200 )] );

            $this->header();


            /**
             * Page – Read
             */

            $this->benchmark( 'Get page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPage::class, ['id' => $page->id] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Get page tree', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPageTree::class, ['lang' => $lang] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Search pages', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchPages::class, ['lang' => $lang, 'term' => 'lorem'] );
            }, readOnly: true, tries: $tries );


            /**
             * Page – Write
             */

            $this->benchmark( 'Add page', function() use ( $user, $root, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddPage::class, [
                    'parent_id' => $root->id, 'lang' => $lang,
                    'name' => 'MCP Bench', 'title' => 'MCP Bench',
                    'path' => 'mcp-bench-' . Utils::uid(),
                    'content' => [['type' => 'text', 'data' => ['text' => 'Benchmark']]],
                    'meta' => ['meta-tags' => ['description' => 'Benchmark page']],
                ] );
            }, tries: $tries );

            $this->benchmark( 'Save page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SavePage::class, [
                    'id' => $page->id, 'title' => 'Updated',
                ] );
            }, tries: $tries );

            $this->benchmark( 'Publish page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishPage::class, ['id' => [$page->id]] );
            }, tries: $tries );

            $this->benchmark( 'Drop page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropPage::class, ['id' => $page->id] );
            }, tries: $tries );

            $this->benchmark( 'Restore page', function() use ( $user, $trashedPage ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestorePage::class, ['id' => $trashedPage->id] );
            }, tries: $tries );


            /**
             * Element – Read
             */

            $this->benchmark( 'Get element', function() use ( $user, $element ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetElement::class, ['id' => $element->id] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Search elements', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchElements::class, ['term' => 'benchmark'] );
            }, readOnly: true, tries: $tries );


            /**
             * Element – Write
             */

            $this->benchmark( 'Add element', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddElement::class, [
                    'type' => 'text', 'name' => 'MCP Bench', 'lang' => $lang,
                    'data' => ['text' => 'Benchmark'],
                ] );
            }, tries: $tries );

            $this->benchmark( 'Save element', function() use ( $user, $element ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SaveElement::class, [
                    'id' => $element->id, 'name' => 'Updated',
                ] );
            }, tries: $tries );

            $this->benchmark( 'Publish element', function() use ( $user, $element ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishElement::class, ['id' => [$element->id]] );
            }, tries: $tries );

            $this->benchmark( 'Drop element', function() use ( $user, $element ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropElement::class, ['id' => $element->id] );
            }, tries: $tries );

            $this->benchmark( 'Restore element', function() use ( $user, $trashedElement ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestoreElement::class, ['id' => $trashedElement->id] );
            }, tries: $tries );


            /**
             * File – Read
             */

            $this->benchmark( 'Get file', function() use ( $user, $file ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetFile::class, ['id' => $file->id] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Search files', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchFiles::class, ['term' => 'benchmark'] );
            }, readOnly: true, tries: $tries );


            /**
             * File – Write
             */

            $this->benchmark( 'Add file', function() use ( $user, $lang ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddFile::class, [
                    'url' => 'https://example.com/bench.txt', 'name' => 'MCP Bench', 'lang' => $lang,
                ] );
            }, tries: $tries );

            $this->benchmark( 'Save file', function() use ( $user, $file ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SaveFile::class, [
                    'id' => $file->id, 'name' => 'Updated',
                ] );
            }, tries: $tries );

            $this->benchmark( 'Publish file', function() use ( $user, $file ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\PublishFile::class, ['id' => [$file->id]] );
            }, tries: $tries );

            $this->benchmark( 'Drop file', function() use ( $user, $file ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\DropFile::class, ['id' => $file->id] );
            }, tries: $tries );

            $this->benchmark( 'Restore file', function() use ( $user, $trashedFile ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\RestoreFile::class, ['id' => $trashedFile->id] );
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
