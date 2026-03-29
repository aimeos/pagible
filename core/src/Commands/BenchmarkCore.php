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
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Utils;


class BenchmarkCore extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:core
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed-only : Only seed, skip benchmarks}
        {--test-only : Only run benchmarks, skip seeding}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run core model benchmarks';


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

        // Load test data
        $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
        $pages = Page::where( 'depth', 3 )->where( 'lang', $lang )->take( 200 )->get();
        $l1Pages = Page::where( 'depth', 1 )->where( 'lang', $lang )->take( 10 )->get();
        $element = Element::where( 'lang', $lang )->firstOrFail();
        $file = File::where( 'lang', $lang )->firstOrFail();

        // Preconditions: soft-delete some pages for restore/purge benchmarks
        $trashedPages = Page::where( 'depth', 3 )->where( 'lang', $lang )->skip( 200 )->take( 200 )->get();
        $trashedPages->each( fn( $p ) => $p->delete() );

        // Create unpublished versions for publish benchmark
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

        // Soft-delete elements/files for restore benchmarks
        $trashedElements = Element::where( 'lang', $lang )->skip( 1 )->take( 100 )->get();
        $trashedElements->each( fn( $e ) => $e->delete() );

        $trashedFiles = File::where( 'lang', $lang )->skip( 1 )->take( 100 )->get();
        $trashedFiles->each( fn( $f ) => $f->delete() );

        $this->header();

        // Page operations
        $pageIdx = 0;
        $this->benchmark( 'Page create', function() use ( $root, $lang ) {
            $page = Page::forceCreate( [
                'lang' => $lang, 'name' => 'Bench page', 'title' => 'Bench',
                'path' => 'bench-' . Utils::uid(), 'status' => 1, 'editor' => 'benchmark',
            ] );
            $page->appendToNode( $root )->save();
            $version = $page->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['name' => 'Bench page'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $page->publish( $version );
        } );

        $this->benchmark( 'Page read', function() use ( $pages, &$pageIdx ) {
            $page = $pages[$pageIdx % $pages->count()];

            if( $page instanceof Page ) {
                Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
            }

            $pageIdx++;
        }, readOnly: true );

        $this->benchmark( 'Page list', function() {
            Page::with( 'latest.files', 'latest.elements' )->take( 100 )->get();
        }, readOnly: true );

        $pageIdx = 0;
        $this->benchmark( 'Page update', function() use ( $pages, $lang, &$pageIdx ) {
            $page = $pages[$pageIdx % $pages->count()];

            if( $page instanceof Page ) {
                $version = $page->versions()->forceCreate( [
                    'lang' => $lang, 'data' => (array) $page->latest?->data,
                    'aux' => (array) $page->latest?->aux, 'published' => false, 'editor' => 'benchmark',
                ] );
                $page->forceFill( ['latest_id' => $version->id] )->saveQuietly();
            }

            $pageIdx++;
        } );

        $pageIdx = 0;
        $this->benchmark( 'Page move', function() use ( $pages, $l1Pages, &$pageIdx ) {
            $page = $pages[$pageIdx % $pages->count()];
            $newParent = $l1Pages[$pageIdx % $l1Pages->count()];

            if( $page instanceof Page && $newParent instanceof Page ) {
                $page->appendToNode( $newParent )->save();
            }

            $pageIdx++;
        } );

        $pubIdx = 0;
        $this->benchmark( 'Page publish', function() use ( $unpublishedPages, &$pubIdx ) {
            $page = $unpublishedPages[$pubIdx % $unpublishedPages->count()];

            if( $page instanceof Page && $page->latest ) {
                $page->publish( $page->latest );
            }

            $pubIdx++;
        } );

        $pageIdx = 0;
        $this->benchmark( 'Page delete', function() use ( $pages, &$pageIdx ) {
            $pages[$pageIdx % $pages->count()]?->delete();
            $pageIdx++;
        } );

        $trashIdx = 0;
        $this->benchmark( 'Page restore', function() use ( $trashedPages, &$trashIdx ) {
            $page = $trashedPages[$trashIdx % $trashedPages->count()];

            if( $page instanceof Page ) {
                $page->restore();
            }

            $trashIdx++;
        } );

        $trashIdx = 0;
        $this->benchmark( 'Page purge', function() use ( $trashedPages, &$trashIdx ) {
            $trashedPages[$trashIdx % $trashedPages->count()]?->forceDelete();
            $trashIdx++;
        } );

        $this->benchmark( 'Page tree', function() use ( $root ) {
            Page::where( 'parent_id', $root->id )->with( ['children', 'latest'] )->get();
        }, readOnly: true );

        // Element operations
        $this->benchmark( 'Element create', function() use ( $lang ) {
            $el = Element::forceCreate( [
                'lang' => $lang, 'type' => 'text', 'name' => 'Bench element',
                'data' => ['type' => 'text', 'data' => ['text' => 'Bench']], 'editor' => 'benchmark',
            ] );
            $version = $el->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['type' => 'text', 'name' => 'Bench element'], 'published' => false, 'editor' => 'benchmark',
            ] );
            $el->publish( $version );
        } );

        $this->benchmark( 'Element read', function() use ( $element ) {
            Element::with( 'latest' )->find( $element->id );
        }, readOnly: true );

        $this->benchmark( 'Element update', function() use ( $element, $lang ) {
            $version = $element->versions()->forceCreate( [
                'lang' => $lang, 'data' => (array) $element->latest?->data, 'published' => false, 'editor' => 'benchmark',
            ] );
            $element->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        } );

        $this->benchmark( 'Element delete', function() use ( $element ) {
            $element->delete();
        } );

        $elTrashIdx = 0;
        $this->benchmark( 'Element restore', function() use ( $trashedElements, &$elTrashIdx ) {
            $trashedElements[$elTrashIdx % $trashedElements->count()]?->restore();
            $elTrashIdx++;
        } );

        // File operations
        $this->benchmark( 'File create', function() use ( $lang ) {
            $f = File::forceCreate( [
                'mime' => 'image/png', 'lang' => $lang, 'name' => 'Bench file',
                'path' => 'https://placehold.co/1500x1000', 'editor' => 'benchmark',
            ] );
            $version = $f->versions()->forceCreate( [
                'lang' => $lang, 'data' => ['mime' => 'image/png', 'name' => 'Bench file', 'path' => 'https://placehold.co/1500x1000'],
                'published' => false, 'editor' => 'benchmark',
            ] );
            $f->publish( $version );
        } );

        $this->benchmark( 'File read', function() use ( $file ) {
            File::with( 'latest' )->find( $file->id );
        }, readOnly: true );

        $this->benchmark( 'File update', function() use ( $file, $lang ) {
            $version = $file->versions()->forceCreate( [
                'lang' => $lang, 'data' => (array) $file->latest?->data, 'published' => false, 'editor' => 'benchmark',
            ] );
            $file->forceFill( ['latest_id' => $version->id] )->saveQuietly();
        } );

        $this->benchmark( 'File delete', function() use ( $file ) {
            $file->delete();
        } );

        $fileTrashIdx = 0;
        $this->benchmark( 'File restore', function() use ( $trashedFiles, &$fileTrashIdx ) {
            $trashedFiles[$fileTrashIdx % $trashedFiles->count()]?->restore();
            $fileTrashIdx++;
        } );

        // Version operations
        $this->benchmark( 'Version list', function() use ( $pages ) {
            $page = $pages->first();

            if( $page instanceof Page ) {
                $page->versions()->get();
            }
        }, readOnly: true );

        $this->benchmark( 'Version prune', function() use ( $pages ) {
            $page = $pages->first();

            if( $page instanceof Page ) {
                $page->removeVersions();
            }
        } );

        // Sequential tree writes
        $this->benchmark( 'Sequential add', function() use ( $root, $lang ) {
            for( $i = 0; $i < 10; $i++ ) {
                $page = Page::forceCreate( [
                    'lang' => $lang, 'name' => "Seq {$i}", 'title' => "Seq {$i}",
                    'path' => 'seq-' . Utils::uid(), 'status' => 1, 'editor' => 'benchmark',
                ] );
                $page->appendToNode( $root )->save();
            }
        } );

        $this->benchmark( 'Sequential move', function() use ( $pages, $l1Pages ) {
            for( $i = 0; $i < 10; $i++ ) {
                $page = $pages[$i % $pages->count()];
                $newParent = $l1Pages[( $i + 1 ) % $l1Pages->count()];

                if( $page instanceof Page && $newParent instanceof Page ) {
                    $page->appendToNode( $newParent )->save();
                }
            }
        } );

        $this->line( '' );

        return 0;
    }
}
