<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Events\PageInvalidated;
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
        {--tries=100 : Number of iterations per benchmark}
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run core model benchmarks';


    public function handle(): int
    {
        $domain = (string) ( $this->option( 'domain' ) ?: '' );
        $tenant = (string) $this->option( 'tenant' );
        $tries = (int) $this->option( 'tries' );
        $force = (bool) $this->option( 'force' );
        $unseed = (bool) $this->option( 'unseed' );

        if( !$this->checks( $tenant, $tries, $force, !$unseed ) ) {
            return self::FAILURE;
        }

        if( $unseed )
        {
            $this->unseed( config( 'cms.db', 'sqlite' ), $tenant, $domain );
            return self::SUCCESS;
        }

        [
            'root' => $root, 'page' => $page, 'parent' => $moveParent, 'element' => $element, 'file' => $file,
            'trashed' => ['page' => $trashedPage, 'element' => $trashedElement, 'file' => $trashedFile],
        ] = $this->fixtures( $domain );

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
            $this->draft( $page );
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
            $this->draft( $element );
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

        $imagePath = realpath( __DIR__ . '/../../assets/image.png' );
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
            $this->draft( $file );
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
     * Remove the cached responses of all benchmark pages of the domain.
     */
    public static function invalidate( string $domain ): void
    {
        $paths = array_values( Page::withTrashed()
            ->where( 'editor', 'benchmark' )
            ->where( 'domain', $domain )
            ->pluck( 'path' )
            ->map( fn( $path ) => (string) $path )
            ->all() );

        if( $paths ) {
            PageInvalidated::dispatch( $domain, $paths );
        }
    }


    /**
     * Remove all benchmark data for the tenant, respecting FK constraints.
     */
    protected function unseed( string $conn, string $tenant, string $domain ): void
    {
        self::invalidate( $domain );

        // Break circular page↔version FK by clearing latest_id first
        DB::connection( $conn )->table( 'cms_page_variants' )
            ->where( 'tenant_id', $tenant )
            ->where( 'editor', 'benchmark' )
            ->update( ['latest_id' => null] );

        $variants = DB::connection( $conn )->table( 'cms_page_variants' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->get( ['id', 'page_id'] );
        $versionIds = DB::connection( $conn )->table( 'cms_versions' )
            ->where( 'tenant_id', $tenant )->where( 'editor', 'benchmark' )->pluck( 'id' );

        // Delete pivot tables (no tenant_id column)
        foreach( $variants->pluck( 'id' )->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_page_file' )->whereIn( 'variant_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_page_element' )->whereIn( 'variant_id', $chunk )->delete();
        }

        foreach( $versionIds->chunk( 500 ) as $chunk )
        {
            DB::connection( $conn )->table( 'cms_version_file' )->whereIn( 'version_id', $chunk )->delete();
            DB::connection( $conn )->table( 'cms_version_element' )->whereIn( 'version_id', $chunk )->delete();
        }

        // Delete main tables
        $tables = ['cms_versions', 'cms_elements', 'cms_files', 'cms_page_variants'];

        foreach( $tables as $table )
        {
            DB::connection( $conn )->table( $table )
                ->where( 'tenant_id', $tenant )
                ->where( 'editor', 'benchmark' )
                ->delete();
        }

        // Pages have no editor column, they are identified by their variants
        foreach( $variants->pluck( 'page_id' )->unique()->chunk( 500 ) as $chunk ) {
            DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $tenant )->whereIn( 'id', $chunk )->delete();
        }
    }
}
