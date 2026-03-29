<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\GraphQL\Mutations;
use Aimeos\Cms\GraphQL\Query;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class BenchmarkGraphql extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:graphql
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed-only : Only seed, skip benchmarks}
        {--test-only : Only run benchmarks, skip seeding}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run GraphQL mutation benchmarks';


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
            Auth::login( $user );

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
            $pages = Page::where( 'depth', 3 )->where( 'lang', $lang )->take( 200 )->get();
            $l1Pages = Page::where( 'depth', 1 )->where( 'lang', $lang )->take( 10 )->get();

            // Preconditions: soft-delete pages for KeepPage/PurgePage
            $trashedPages = Page::where( 'depth', 3 )->where( 'lang', $lang )->skip( 200 )->take( 200 )->get();
            $trashedPages->each( fn( $p ) => $p->delete() );

            // Create unpublished versions for PubPage
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

            // Page benchmarks
            $this->benchmark( 'Page add', function() use ( $root, $lang ) {
                ( new Mutations\AddPage )( null, [
                    'parent' => $root->id,
                    'input' => [
                        'lang' => $lang, 'name' => 'GQL Bench', 'title' => 'GQL Bench',
                        'path' => 'gql-bench-' . Utils::uid(), 'status' => 1,
                    ],
                ] );
            } );

            $pageIdx = 0;
            $this->benchmark( 'Page save', function() use ( $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    ( new Mutations\SavePage )( null, [
                        'id' => $page->id,
                        'input' => ['title' => 'Updated ' . $pageIdx],
                    ] );
                }

                $pageIdx++;
            } );

            $pageIdx = 0;
            $this->benchmark( 'Page move', function() use ( $pages, $l1Pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];
                $newParent = $l1Pages[$pageIdx % $l1Pages->count()];

                if( $page instanceof Page && $newParent instanceof Page ) {
                    ( new Mutations\MovePage )( null, [
                        'id' => $page->id,
                        'parent' => $newParent->id,
                    ] );
                }

                $pageIdx++;
            } );

            $pubIdx = 0;
            $this->benchmark( 'Page publish', function() use ( $unpublishedPages, &$pubIdx ) {
                $page = $unpublishedPages[$pubIdx % $unpublishedPages->count()];

                if( $page instanceof Page ) {
                    ( new Mutations\PubPage )( null, ['id' => [$page->id]] );
                }

                $pubIdx++;
            } );

            $pageIdx = 0;
            $this->benchmark( 'Page delete', function() use ( $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    ( new Mutations\DropPage )( null, ['id' => [$page->id]] );
                }

                $pageIdx++;
            } );

            $trashIdx = 0;
            $this->benchmark( 'Page restore', function() use ( $trashedPages, &$trashIdx ) {
                $page = $trashedPages[$trashIdx % $trashedPages->count()];

                if( $page instanceof Page ) {
                    ( new Mutations\KeepPage )( null, ['id' => [$page->id]] );
                }

                $trashIdx++;
            } );

            $trashIdx = 0;
            $this->benchmark( 'Page purge', function() use ( $trashedPages, &$trashIdx ) {
                $page = $trashedPages[$trashIdx % $trashedPages->count()];

                if( $page instanceof Page ) {
                    ( new Mutations\PurgePage )( null, ['id' => [$page->id]] );
                }

                $trashIdx++;
            } );

            $this->benchmark( 'Page list', function() use ( $lang ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['lang' => $lang]] )->get();
            }, readOnly: true );

            $pageIdx = 0;
            $this->benchmark( 'Page get', function() use ( $pages, &$pageIdx ) {
                $page = $pages[$pageIdx % $pages->count()];

                if( $page instanceof Page ) {
                    Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
                }

                $pageIdx++;
            }, readOnly: true );

            // Element benchmark
            $this->benchmark( 'Element add', function() use ( $lang ) {
                ( new Mutations\AddElement )( null, [
                    'input' => [
                        'lang' => $lang, 'type' => 'text', 'name' => 'GQL Bench Element',
                        'data' => (object) ['type' => 'text', 'data' => (object) ['text' => 'Benchmark']],
                    ],
                ] );
            } );

            // File benchmark
            $this->benchmark( 'File add', function() use ( $lang ) {
                ( new Mutations\AddFile )( null, [
                    'input' => [
                        'lang' => $lang, 'name' => 'GQL Bench File',
                        'path' => 'https://placehold.co/1500x1000',
                    ],
                ] );
            } );

            $this->line( '' );
        }
        finally
        {
            Auth::logout();
            DB::connection( $conn )->rollBack();
        }

        return 0;
    }
}
