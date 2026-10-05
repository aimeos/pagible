<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Http;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Utils;


class BenchmarkMcp extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:mcp
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--tries=100 : Number of iterations per benchmark}
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

        $domain = (string) ( $this->option( 'domain' ) ?: '' );

        config( ['scout.driver' => 'cms'] );

        // Run everything in a rolled back transaction for user cleanup
        $this->sandbox( function( $user ) use ( $domain, $tries ) {
            [
                'root' => $root, 'page' => $page, 'element' => $element, 'file' => $file,
                'trashed' => ['page' => $trashedPage, 'element' => $trashedElement, 'file' => $trashedFile],
            ] = $this->fixtures( $domain, true );

            Http::fake( fn() => Http::response( 'benchmark', 200 ) );

            $this->header();


            /**
             * Page – Read
             */

            $this->benchmark( 'Get page', function() use ( $user, $page ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPage::class, ['id' => $page->id] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Get page tree', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\GetPageTree::class, ['lang' => 'en'] );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Search pages', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\SearchPages::class, ['lang' => 'en', 'term' => 'lorem'] );
            }, readOnly: true, tries: $tries );


            /**
             * Page – Write
             */

            $this->benchmark( 'Add page', function() use ( $user, $root ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddPage::class, [
                    'parent_id' => $root->id, 'lang' => 'en',
                    'name' => 'MCP Bench', 'title' => 'MCP Bench',
                    'path' => 'mcp-bench-' . Utils::uid(),
                    'content' => [['type' => 'text', 'data' => ['text' => 'Benchmark']]],
                    'meta' => ['meta-tags' => [
                        'type' => 'meta-tags',
                        'data' => ['description' => 'Benchmark page'],
                        'files' => [],
                    ]],
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

            $this->benchmark( 'Add element', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddElement::class, [
                    'type' => 'text', 'name' => 'MCP Bench', 'lang' => 'en',
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

            $this->benchmark( 'Add file', function() use ( $user ) {
                CmsServer::actingAs( $user )->tool( \Aimeos\Cms\Tools\AddFile::class, [
                    'url' => 'https://example.com/bench.txt', 'name' => 'MCP Bench', 'lang' => 'en',
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
        } );

        return self::SUCCESS;
    }
}
