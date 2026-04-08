<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Aimeos\Cms\Concerns\Benchmarks;
use Aimeos\Cms\GraphQL\Mutations;
use Aimeos\Cms\GraphQL\Query;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Utils;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Schema\SchemaBuilder;


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
        {--unseed : Remove benchmark data and exit}
        {--force : Force the operation to run in production}';

    protected $description = 'Run GraphQL mutation benchmarks';


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
            Auth::login( $user );

            $root = Page::where( 'tag', 'root' )->where( 'lang', $lang )->where( 'domain', $domain )->firstOrFail();

            $count = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )->count();
            $page = Page::where( 'tag', '!=', 'root' )->where( 'lang', $lang )
                ->orderBy( '_lft' )->skip( (int) floor( $count / 2 ) )->firstOrFail();

            $moveParent = Page::where( 'depth', 1 )->where( 'lang', $lang )
                ->whereNotIn( 'id', $page->ancestors()->get()->pluck( 'id' ) )->firstOrFail();

            // Query pre-seeded soft-deleted page for KeepPage
            $trashedPage = Page::onlyTrashed()->where( 'lang', $lang )->firstOrFail();

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

            // Build a ResolveInfo mirroring a realistic admin page-list query
            // so Query::pages() exercises pageColumns() projection.
            config( [
                'lighthouse.schema_path' => dirname( __DIR__, 2 ) . '/tests/default-schema.graphql',
                'lighthouse.namespaces.models' => ['App\\Models', 'Aimeos\\Cms\\Models'],
                'lighthouse.namespaces.mutations' => ['Aimeos\\Cms\\GraphQL\\Mutations'],
                'lighthouse.namespaces.directives' => ['Aimeos\\Cms\\GraphQL\\Directives'],
            ] );

            $gqlQuery = '{ pages(first: 100) { data { id name title path lang status editor } } }';
            $schema = app( SchemaBuilder::class )->schema();
            $document = Parser::parse( $gqlQuery );
            $op = $document->definitions[0];
            $pagesSel = $op->selectionSet->selections[0];
            $parentType = $schema->getQueryType();
            $fieldDef = $parentType->getField( 'pages' );

            $resolveInfo = new ResolveInfo(
                $fieldDef,
                new \ArrayObject( [$pagesSel] ),
                $parentType,
                [],
                $schema,
                new \ArrayObject(),
                null,
                $op,
                []
            );

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
            }, tries: $tries );

            $this->benchmark( 'Page save', function() use ( $page ) {
                ( new Mutations\SavePage )( null, [
                    'id' => $page->id,
                    'input' => ['title' => 'Updated'],
                ] );
            }, tries: $tries );

            $this->benchmark( 'Page move', function() use ( $page, $moveParent ) {
                ( new Mutations\MovePage )( null, [
                    'id' => $page->id,
                    'parent' => $moveParent->id,
                ] );
            }, tries: $tries );

            $this->benchmark( 'Page publish', function() use ( $page ) {
                ( new Mutations\PubPage )( null, ['id' => [$page->id]] );
            }, tries: $tries );

            $this->benchmark( 'Page delete', function() use ( $page ) {
                ( new Mutations\DropPage )( null, ['id' => [$page->id]] );
            }, tries: $tries );

            $this->benchmark( 'Page restore', function() use ( $trashedPage ) {
                ( new Mutations\KeepPage )( null, ['id' => [$trashedPage->id]] );
            }, tries: $tries );

            $this->benchmark( 'Page purge', function() use ( $page ) {
                ( new Mutations\PurgePage )( null, ['id' => [$page->id]] );
            }, tries: $tries );

            $this->benchmark( 'Page list', function() use ( $lang, $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['lang' => $lang]], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page get', function() use ( $page ) {
                Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page lang', function() use ( $lang, $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['lang' => $lang]], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page theme', function() use ( $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['theme' => 'default']], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page status', function() use ( $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['status' => 1]], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page cache', function() use ( $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['cache' => 5]], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page editor', function() use ( $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['editor' => 'benchmark']], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page type', function() use ( $resolveInfo ) {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['type' => '']], null, $resolveInfo )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'File mime', function() {
                ( new Query )->files( null, ['first' => 100, 'filter' => ['mime' => 'image/jpeg']] )->items();
            }, readOnly: true, tries: $tries );


            /**
             * Element operations
             */

            $this->benchmark( 'Element type', function() {
                ( new Query )->elements( null, ['first' => 100, 'filter' => ['type' => 'text']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Element lang', function() use ( $lang ) {
                ( new Query )->elements( null, ['first' => 100, 'filter' => ['lang' => $lang]] )->items();
            }, readOnly: true, tries: $tries );


            $this->benchmark( 'Element add', function() use ( $lang ) {
                ( new Mutations\AddElement )( null, [
                    'input' => [
                        'lang' => $lang, 'type' => 'text', 'name' => 'GQL Bench Element',
                        'data' => (object) ['type' => 'text', 'data' => (object) ['text' => 'Benchmark']],
                    ],
                ] );
            }, tries: $tries );


            /**
             * File operations
             */

            $this->benchmark( 'File lang', function() use ( $lang ) {
                ( new Query )->files( null, ['first' => 100, 'filter' => ['lang' => $lang]] )->items();
            }, readOnly: true, tries: $tries );


            $this->benchmark( 'File add', function() use ( $lang ) {
                ( new Mutations\AddFile )( null, [
                    'input' => [
                        'lang' => $lang, 'name' => 'GQL Bench File',
                        'path' => 'https://placehold.co/1500x1000',
                    ],
                ] );
            }, tries: $tries );

            $this->line( '' );
        }
        finally
        {
            Auth::logout();
            Auth::guard()->forgetUser();
            DB::connection( $conn )->rollBack();
        }

        return self::SUCCESS;
    }
}
