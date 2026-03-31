<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class BenchmarkCore extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:core
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run core model benchmarks';


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

        // Load one item per type (each benchmark iteration is rolled back)
        $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();

        $count = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->count();
        $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
            ->orderBy( '_lft' )->skip( (int) floor( $count / 2 ) )->firstOrFail();

        $moveParent = Page::where( 'depth', 1 )->where( 'lang', $lang )
            ->whereNotIn( 'id', $page->ancestors()->get()->pluck( 'id' ) )->firstOrFail();
        $element = Element::where( 'lang', $lang )->firstOrFail();
        $file = File::where( 'lang', $lang )->firstOrFail();

        // Create unpublished version for publish benchmark
        $unpubVersion = $page->versions()->forceCreate( [
            'lang' => $lang,
            'data' => (array) $page->latest?->data,
            'aux' => (array) $page->latest?->aux,
            'published' => false,
            'editor' => 'benchmark',
        ] );
        $page->forceFill( ['latest_id' => $unpubVersion->id] )->saveQuietly();
        $page->setRelation( 'latest', $unpubVersion );

        // Query pre-seeded soft-deleted items for restore benchmarks
        $trashedPage = Page::onlyTrashed()->where( 'lang', $lang )->firstOrFail();
        $trashedElement = Element::onlyTrashed()->where( 'lang', $lang )->firstOrFail();
        $trashedFile = File::onlyTrashed()->where( 'lang', $lang )->firstOrFail();

        $this->header();


        /**
         * Page operations
         */

        $this->benchmark( 'Page create', function() use ( $root, $lang ) {
            $p = Page::forceCreate( [
                'lang' => $lang, 'name' => 'Bench page', 'title' => 'Bench',
                'path' => 'bench-' . Utils::uid(), 'status' => 1, 'editor' => 'benchmark',
            ] );
            $p->appendToNode( $root )->save();
            $version = $p->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['name' => 'Bench page'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $p->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Page read', function() use ( $page ) {
            Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page list', function() {
            Page::with( 'latest.files', 'latest.elements' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Page update', function() use ( $page, $lang ) {
            $version = $page->versions()->forceCreate( [
                'lang' => $lang, 'data' => (array) $page->latest?->data,
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
        }, tries: $tries );

        $this->benchmark( 'Page purge', function() use ( $page ) {
            $page->forceDelete();
        }, tries: $tries );

        $this->benchmark( 'Page tree', function() use ( $root ) {
            Page::where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true, tries: $tries );


        /**
         * Element operations
         */

        $this->benchmark( 'Element create', function() use ( $lang ) {
            $el = Element::forceCreate( [
                'lang' => $lang, 'type' => 'text', 'name' => 'Bench element',
                'data' => ['type' => 'text', 'data' => ['text' => 'Bench']], 'editor' => 'benchmark',
            ] );
            $version = $el->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['type' => 'text', 'name' => 'Bench element'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $el->publish( $version );
        }, tries: $tries );

        $this->benchmark( 'Element read', function() use ( $element ) {
            Element::with( 'latest.files' )->find( $element->id );
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element list', function() {
            Element::with( 'latest.files' )->take( 100 )->get();
        }, readOnly: true, tries: $tries );

        $this->benchmark( 'Element update', function() use ( $element, $lang ) {
            $version = $element->versions()->forceCreate( [
                'lang' => $lang, 'data' => (array) $element->latest?->data, 'published' => false, 'editor' => 'benchmark',
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
        }, tries: $tries );


        /**
         * File operations
         */

        $this->benchmark( 'File create', function() use ( $lang ) {
            $f = File::forceCreate( [
                'mime' => 'image/png', 'lang' => $lang, 'name' => 'Bench file',
                'path' => 'https://placehold.co/1500x1000', 'editor' => 'benchmark',
            ] );
            $version = $f->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['mime' => 'image/png', 'name' => 'Bench file', 'path' => 'https://placehold.co/1500x1000', 'previews' => []],
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

        $this->benchmark( 'File update', function() use ( $file, $lang ) {
            $version = $file->versions()->forceCreate( [
                'lang' => $lang, 'data' => (array) $file->latest?->data, 'published' => false, 'editor' => 'benchmark',
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
}
