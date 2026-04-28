<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;


class BenchmarkCore extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:core
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=50 : Rows per bulk insert batch}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run core model benchmarks';


    public function handle(): int
    {
        $tenant = (string) $this->option( 'tenant' );

        if( $this->option( 'unseed' ) )
        {
            $this->tenant( $tenant);
            $this->unseed( config( 'cms.db', 'sqlite' ), $tenant );
            return self::SUCCESS;
        }

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

        // Load one item per type (each benchmark iteration is rolled back)
        $root = Page::where( 'tag', 'root' )->where( 'domain', $domain )->firstOrFail();

        $count = Page::where( 'tag', '!=', 'root' )->count();
        $page = Page::where( 'tag', '!=', 'root' )
            ->orderBy( NestedSet::LFT )->skip( (int) floor( $count / 2 ) )
            ->firstOrFail();

        $parentIds = $page->ancestors()->get()->pluck( 'id' );
        $moveParent = Page::where( NestedSet::DEPTH, 1 )
            ->whereNotIn( 'id', $parentIds )
            ->firstOrFail();

        $element = Element::firstOrFail();
        $file = File::firstOrFail();

        // Create unpublished version for publish benchmark
        $unpubVersion = $page->versions()->forceCreate( [
            'lang' => 'en',
            'data' => (array) $page->latest?->data,
            'aux' => (array) $page->latest?->aux,
            'published' => false,
            'editor' => 'benchmark',
        ] );
        $page->forceFill( ['latest_id' => $unpubVersion->id] )->saveQuietly();
        $page->setRelation( 'latest', $unpubVersion );

        // Query pre-seeded soft-deleted items for restore benchmarks
        $trashedPage = Page::onlyTrashed()->firstOrFail();
        $trashedElement = Element::onlyTrashed()->firstOrFail();
        $trashedFile = File::onlyTrashed()->firstOrFail();

        $this->header();


        /**
         * Page operations
         */

        $this->benchmark( 'Page create', function() use ( $root ) {
            $p = Page::forceCreate( [
                'lang' => 'en', 'name' => 'Bench page', 'title' => 'Bench',
                'path' => 'bench-' . Utils::uid(), 'status' => 1, 'editor' => 'benchmark',
            ] );
            $p->appendToNode( $root )->save();
            $version = $p->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['name' => 'Bench page'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $p->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Page read', function() use ( $page ) {
            Page::with( 'files', 'elements.files' )->find( $page->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page list', function() {
            Page::with( 'files', 'elements.files' )->orderBy( NestedSet::LFT )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page update', function() use ( $page ) {
            $version = $page->versions()->forceCreate( [
                'lang' => 'en', 'data' => (array) $page->latest?->data,
                'aux' => (array) $page->latest?->aux, 'published' => false, 'editor' => 'benchmark',
            ] );
            $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        }, tries: $tries );

        $this->benchmark( 'Page move', function() use ( $page, $moveParent ) {
            $page->appendToNode( $moveParent )->save();
        }, tries: $tries );

        $this->benchmark( 'Page publish', function() use ( $page ) {
            $page->publish( $page->latest ?? throw new \RuntimeException( 'No latest version' ) );
        }, tries: $tries );

        $this->benchmark( 'Page delete', function() use ( $page ) {
            $page->delete();
            $page->deleted_at = null;
        }, tries: $tries );

        $this->benchmark( 'Page restore', function() use ( $trashedPage ) {
            $trashedPage->restore();
            $trashedPage->deleted_at = now();
            $trashedPage->syncOriginal();
        }, tries: $tries );

        $this->benchmark( 'Page purge', function() use ( $page ) {
            $page->forceDelete();
            $page->exists = true;
        }, tries: $tries );

        $this->benchmark( 'Page tree', function() use ( $root ) {
            Page::select( 'id', 'parent_id', 'name', 'title', 'tag', 'path', 'domain', 'lang', 'to', 'status', 'config', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
                ->where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );


        /**
         * Element operations
         */

        $this->benchmark( 'Element create', function() {
            $el = Element::forceCreate( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Bench element',
                'data' => ['type' => 'text', 'data' => ['text' => 'Bench']], 'editor' => 'benchmark',
            ] );
            $version = $el->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['type' => 'text', 'name' => 'Bench element'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $el->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Element read', function() use ( $element ) {
            Element::with( 'files' )->find( $element->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element list', function() {
            Element::with( 'files' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element update', function() use ( $element ) {
            $version = $element->versions()->forceCreate( [
                'lang' => 'en', 'data' => (array) $element->latest?->data, 'published' => false, 'editor' => 'benchmark',
            ] );
            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        }, tries: $tries );

        $this->benchmark( 'Element delete', function() use ( $element ) {
            $element->delete();
            $element->deleted_at = null;
        }, tries: $tries );

        $this->benchmark( 'Element restore', function() use ( $trashedElement ) {
            $trashedElement->restore();
            $trashedElement->deleted_at = now();
            $trashedElement->syncOriginal();
        }, tries: $tries );


        /**
         * File operations
         */

        $imagePath = realpath( __DIR__ . '/../../tests/assets/image.png' );
        $this->benchmark( 'File create', function() use ( $imagePath ) {
            $f = File::forceCreate( [
                'mime' => 'image/png', 'lang' => 'en', 'name' => 'Bench file',
                'path' => $imagePath, 'editor' => 'benchmark',
            ] );
            $version = $f->versions()->forceCreate( [
                'lang' => 'en', 'data' => ['mime' => 'image/png', 'name' => 'Bench file', 'path' => $imagePath, 'previews' => []],
                'published' => false, 'editor' => 'benchmark',
            ] );
            $f->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'File read', function() use ( $file ) {
            File::with( 'latest' )->find( $file->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File list', function() {
            File::with( 'latest' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File mime', function() {
            File::with( 'latest' )->where( 'mime', 'image/jpeg' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'File update', function() use ( $file ) {
            $version = $file->versions()->forceCreate( [
                'lang' => 'en', 'data' => (array) $file->latest?->data, 'published' => false, 'editor' => 'benchmark',
            ] );
            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        }, tries: $tries );

        $this->benchmark( 'File delete', function() use ( $file ) {
            $file->delete();
            $file->deleted_at = null;
        }, tries: $tries );

        $this->benchmark( 'File restore', function() use ( $trashedFile ) {
            $trashedFile->restore();
            $trashedFile->deleted_at = now();
            $trashedFile->syncOriginal();
        }, tries: $tries );


        /**
         * Version operations
         */

        $this->benchmark( 'Version list', function() use ( $page ) {
            $page->versions()->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Version prune', function() use ( $page ) {
            $page->removeVersions();
        }, tries: $tries );


        $this->line( '' );

        return self::SUCCESS;
    }


    /**
     * Remove all benchmark data for the tenant, respecting FK constraints.
     */
    protected function unseed( string $conn, string $tenant ): void
    {
        // Clear cache for benchmark pages
        Page::where( 'editor', 'benchmark' )->each( function( $page ) {
            Cache::forget( Page::key( $page ) );
        } );

        // Break circular page↔version FK by clearing latest_id first
        DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $tenant )
            ->where( 'editor', 'benchmark' )
            ->update( ['latest_id' => null] );

        $pageIds = DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );
        $versionIds = DB::connection( $conn )->table( 'cms_versions' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );

        // Delete pivot tables (no tenant_id column)
        foreach( $pageIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_page_file' )->whereIn( 'page_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_page_element' )->whereIn( 'page_id', $chunk )->delete();
        }

        foreach( $versionIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_version_file' )->whereIn( 'version_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_version_element' )->whereIn( 'version_id', $chunk )->delete();
        }

        // Delete main tables
        $tables = ['cms_versions', 'cms_elements', 'cms_files', 'cms_pages'];

        foreach( $tables as $table )
        {
            DB::connection( $conn )->table( $table )
                ->where( 'tenant_id', $tenant )
                ->where( 'editor', 'benchmark' )
                ->delete();
        }
    }
}
