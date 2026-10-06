<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;

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
        {--tries=100 : Number of iterations per benchmark}
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

        $domain = (string) ( $this->option( 'domain' ) ?: '' );

        config( ['scout.driver' => 'cms'] );

        // Run everything in a rolled back transaction for user cleanup
        $this->sandbox( function() use ( $domain, $tries ) {
            [
                'root' => $root, 'page' => $page, 'parent' => $moveParent, 'element' => $element, 'file' => $file,
                'trashed' => ['page' => $trashedPage],
            ] = $this->fixtures( $domain );

            $this->header();


            /**
             * Page operations
             */

            $this->benchmark( 'Page add', function() use ( $root ) {
                ( new Mutations\AddPage )( null, [
                    'parent' => $root->id,
                    'input' => [
                        'lang' => 'en', 'name' => 'GQL Bench', 'title' => 'GQL Bench',
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

            $this->benchmark( 'Page list', function() {
                ( new Query )->pages( null, ['first' => 100] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page get', function() use ( $page ) {
                Page::with( 'latest.files', 'latest.elements' )->find( $page->id );
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page lang', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['lang' => 'en']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page theme', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['theme' => 'default']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page status', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['status' => 1]] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page cache', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['cache' => 5]] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page editor', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['editor' => 'benchmark']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page type', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => ['type' => '']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Page scheduled', function() {
                ( new Query )->pages( null, ['first' => 100, 'filter' => [], 'publish' => 'SCHEDULED'] )->items();
            }, readOnly: true, tries: $tries );


            /**
             * Element operations
             */

            $this->benchmark( 'Element type', function() {
                ( new Query )->elements( null, ['first' => 100, 'filter' => ['type' => 'text']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Element lang', function() {
                ( new Query )->elements( null, ['first' => 100, 'filter' => ['lang' => 'en']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'Element get', function() use ( $element ) {
                Element::with( 'latest.files', 'bypages' )->find( $element->id );
            }, readOnly: true, tries: $tries );


            $this->benchmark( 'Element add', function() {
                ( new Mutations\AddElement )( null, [
                    'input' => [
                        'lang' => 'en', 'type' => 'text', 'name' => 'GQL Bench Element',
                        'data' => (object) ['type' => 'text', 'data' => (object) ['text' => 'Benchmark']],
                    ],
                ] );
            }, tries: $tries );


            /**
             * File operations
             */

            $this->benchmark( 'File lang', function() {
                ( new Query )->files( null, ['first' => 100, 'filter' => ['lang' => 'en']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'File mime', function() {
                ( new Query )->files( null, ['first' => 100, 'filter' => ['mime' => 'image/jpeg']] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'File name', function() {
                ( new Query )->files( null, ['first' => 100, 'filter' => [], 'sort' => [['column' => 'name', 'order' => 'asc']]] )->items();
            }, readOnly: true, tries: $tries );

            $this->benchmark( 'File get', function() use ( $file ) {
                File::with( 'latest', 'bypages', 'byelements' )->find( $file->id );
            }, readOnly: true, tries: $tries );


            $imagePath = (string) realpath( __DIR__ . '/../../assets/image.png' );

            $this->benchmark( 'File add', function() use ( $imagePath ) {
                ( new Mutations\AddFile )( null, [
                    'input' => ['lang' => 'en', 'name' => 'GQL Bench File'],
                    'file' => new \Illuminate\Http\UploadedFile(
                        $imagePath, 'image.png', 'image/png', null, true
                    ),
                ] );
            }, tries: $tries );

            $this->line( '' );
        } );

        return self::SUCCESS;
    }
}
