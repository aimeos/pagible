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
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;


class BenchmarkGraphql extends Command
{
    use Benchmarks;



    protected $signature = 'cms:benchmark:graphql
        {--tenant=benchmark : Tenant ID}
        {--domain= : Domain name}
        {--lang=en : Language code}
        {--seed : Seed benchmark data before running benchmarks}
        {--pages=10000 : Total number of pages}
        {--tries=100 : Number of iterations per benchmark}
        {--chunk=500 : Rows per bulk insert batch}
        {--force : Force the operation to run in production}';

    protected $description = 'Run GraphQL mutation benchmarks';


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
            Auth::login( $user );

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();
            $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->orderByDesc( 'depth' )->firstOrFail();
            $moveParent = Page::where( 'depth', 1 )->where( 'lang', $lang )
                ->whereNotIn( 'id', $page->ancestors()->get()->pluck( 'id' ) )->firstOrFail();

            // Preconditions: soft-delete a page for KeepPage/PurgePage
            $excludeIds = $page->ancestors()->get()->pluck( 'id' )->push( $page->id );
            $trashedPage = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
                ->whereNotIn( 'id', $excludeIds )->orderByDesc( 'depth' )->firstOrFail();
            $trashedPage->delete();

            // Create unpublished version for PubPage
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
             * Page operations
             */

            $this->benchmark( 'Page add', function() use ( $root, $lang ) {
                ( new Mutations\AddPage )( null, [
                    'parent' => $root->id,
                    'input' => [
                        'lang' => $lang, 'name' => 'GQL Bench', 'title' => 'GQL Bench',
                        'path' => 'gql-bench-' . Utils::uid(), 'status' => 1,
                    ],
                ] );
            } );

            $this->benchmark( 'Page save', function() use ( $page ) {
                ( new Mutations\SavePage )( null, [
                    'id' => $page->id,
                    'input' => ['title' => 'Updated'],
                ] );
            } );

            $this->benchmark( 'Page move', function() use ( $page, $moveParent ) {
                ( new Mutations\MovePage )( null, [
                    'id' => $page->id,
                    'parent' => $moveParent->id,
                ] );
            } );

            $this->benchmark( 'Page publish', function() use ( $page ) {
                ( new Mutations\PubPage )( null, ['id' => [$page->id]] );
            } );

            $this->benchmark( 'Page delete', function() use ( $page ) {
                ( new Mutations\DropPage )( null, ['id' => [$page->id]] );
            } );

            $this->benchmark( 'Page restore', function() use ( $trashedPage ) {
                ( new Mutations\KeepPage )( null, ['id' => [$trashedPage->id]] );
            } );

            $this->benchmark( 'Page purge', function() use ( $trashedPage ) {
                ( new Mutations\PurgePage )( null, ['id' => [$trashedPage->id]] );
            } );

            $this->benchmark( 'Page list', function() use ( $lang ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['lang' => $lang]] )->items();
            }, readOnly: true );

            $this->benchmark( 'Page get', function() use ( $page ) {
                Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
            }, readOnly: true );


            /**
             * Element operations
             */

            $this->benchmark( 'Element add', function() use ( $lang ) {
                ( new Mutations\AddElement )( null, [
                    'input' => [
                        'lang' => $lang, 'type' => 'text', 'name' => 'GQL Bench Element',
                        'data' => (object) ['type' => 'text', 'data' => (object) ['text' => 'Benchmark']],
                    ],
                ] );
            } );


            /**
             * File operations
             */

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

        return self::SUCCESS;
    }
}
