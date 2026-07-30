<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;


class FileStorageMigrationTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected string $seeder = TestSeeder::class;


    public function testFileCatalogHasTenantPaginationIndex(): void
    {
        $indexes = Schema::connection( config( 'cms.db', 'sqlite' ) )->getIndexes( 'cms_files' );
        $index = collect( $indexes )->firstWhere( 'name', 'cms_files_tenant_id_id_index' );

        $this->assertSame( ['tenant_id', 'id'], $index['columns'] ?? null );
    }


    public function testInvalidationScopesFileReferencesToCurrentTenant(): void
    {
        config( ['cms.disks.public.name' => 'uuid-migration-public'] );
        Storage::fake( 'uuid-migration-public' );

        $current = File::forceCreate( [
            'mime' => 'application/pdf', 'name' => 'current.pdf',
            'path' => 'cms/test/current.pdf', 'editor' => 'test',
        ] );
        $foreign = Tenancy::run( 'foreign', fn() => File::forceCreate( [
            'mime' => 'application/pdf', 'name' => 'foreign.pdf',
            'path' => 'cms/foreign/foreign.pdf', 'editor' => 'test',
        ] ) );
        $page = Page::forceCreate( [
            'lang' => 'en', 'name' => 'Isolated', 'title' => 'Isolated',
            'path' => 'isolated', 'status' => 1, 'editor' => 'test',
        ] );
        $owned = Page::forceCreate( [
            'lang' => 'en', 'name' => 'Owned', 'title' => 'Owned',
            'path' => 'owned', 'status' => 1, 'editor' => 'test',
        ] );

        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_page_file' )->insert( [
            ['page_id' => $page->id, 'file_id' => $foreign->id],
            ['page_id' => $owned->id, 'file_id' => $current->id],
        ] );
        Event::fake( [PageInvalidated::class] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';
        $migration->up();

        Event::assertDispatched( PageInvalidated::class, fn( PageInvalidated $event ) =>
            $event->tenant === 'test' && in_array( 'owned', $event->paths, true )
        );
        Event::assertNotDispatched( PageInvalidated::class, fn( PageInvalidated $event ) =>
            $event->tenant === 'test' && in_array( 'isolated', $event->paths, true )
        );
    }


    public function testMigrationRunsWithoutTransaction(): void
    {
        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';

        $this->assertFalse( $migration->withinTransaction );
    }


    public function testMigrationRejectsUnsafeTenantBeforeStorageChanges(): void
    {
        config( ['cms.disks.public.name' => 'uuid-migration-tenant'] );
        Storage::fake( 'uuid-migration-tenant' );
        $file = File::firstOrFail();
        $source = 'cms/unsafe%2Ftenant/legacy.pdf';
        $target = 'cms/unsafe%2Ftenant/' . strtolower( $file->id ) . '/'
            . substr( hash( 'sha256', $source ), 0, 24 ) . '.pdf';
        Storage::disk( 'uuid-migration-tenant' )->put( $source, 'legacy' );
        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_files' )
            ->where( 'id', $file->id )->update( [
                'tenant_id' => 'unsafe%2Ftenant',
                'path' => $source,
            ] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';

        try {
            $migration->up();
            $this->fail( 'Expected an unsafe tenant ID to be rejected' );
        } catch( \InvalidArgumentException $e ) {
            $this->assertSame( 'Invalid tenant ID', $e->getMessage() );
        }

        Storage::disk( 'uuid-migration-tenant' )->assertExists( $source );
        Storage::disk( 'uuid-migration-tenant' )->assertMissing( $target );
    }


    public function testMigrationUsesLeanCopyQueriesAndChunksVersionUpserts(): void
    {
        config( ['cms.disks.public.name' => 'uuid-migration-chunks'] );
        Storage::fake( 'uuid-migration-chunks' );

        $path = 'cms/test/chunked.pdf';
        Storage::disk( 'uuid-migration-chunks' )->put( $path, 'chunked' );
        $file = File::forceCreate( [
            'mime' => 'application/pdf', 'name' => 'chunked.pdf',
            'path' => $path, 'editor' => 'test',
        ] );
        $ids = [];

        foreach( range( 1, 101 ) as $num ) {
            $ids[] = $file->versions()->forceCreate( [
                'editor' => 'test',
                'data' => ['path' => $path, 'previews' => []],
                'aux' => ['transcription' => str_repeat( (string) $num, 100 )],
            ] )->id;
        }

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $grammar = $db->getQueryGrammar();
        $table = $grammar->wrapTable( 'cms_versions' );
        $columns = implode( ', ', array_map(
            $grammar->wrap(...),
            ['id', 'versionable_id', 'data'],
        ) );
        $queries = [];
        $db->listen(
            function( QueryExecuted $query ) use ( &$queries ) {
                $queries[] = $query;
            },
        );
        Event::fake( [PageInvalidated::class] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';
        $migration->up();

        $this->assertTrue( collect( $queries )->contains( fn( QueryExecuted $query ) =>
            str_contains( $query->sql, "select {$columns} from {$table}" )
        ) );

        $upserts = collect( $queries )->filter( function( QueryExecuted $query ) use ( $ids, $table ) {
            return ( str_contains( $query->sql, "insert into {$table}" )
                    || str_contains( $query->sql, "merge {$table}" ) )
                && array_intersect( $ids, array_map( strval(...), $query->bindings ) );
        } );

        $this->assertCount( 2, $upserts );
        $this->assertTrue( $file->versions()->get()->every(
            fn( $version ) => File::owner( 'test', $version->data->path ) === strtolower( $file->id ),
        ) );
    }


    public function testMovesCurrentPreviewAndVersionPathsIntoFileDirectories(): void
    {
        config( [
            'cms.disks.public.name' => 'uuid-migration-public',
            'cms.disks.private.name' => 'uuid-migration-private',
        ] );
        Storage::fake( 'uuid-migration-public' );
        Storage::fake( 'uuid-migration-private' );

        $shared = 'cms/test/shared.pdf';
        $preview = 'cms/test/preview.webp';
        $versioned = 'cms/test/versioned.pdf';
        $private = 'cms/test/private.pdf';

        foreach( [$shared, $preview, $versioned] as $path ) {
            Storage::disk( 'uuid-migration-public' )->put( $path, $path );
        }
        Storage::disk( 'uuid-migration-private' )->put( $private, $private );

        $first = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'first.pdf',
            'path' => $shared, 'previews' => [500 => $preview], 'editor' => 'test',
        ] );
        $firstVersion = $first->versions()->forceCreate( [
            'editor' => 'test', 'data' => ['path' => $versioned, 'previews' => [500 => $preview]],
        ] );
        $second = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'second.pdf',
            'path' => $shared, 'editor' => 'test',
        ] );
        $owned = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'owned.pdf',
            'path' => 'https://example.com/pending.pdf', 'editor' => 'test',
        ] );
        $owned->path = $owned->dir() . '/owned.pdf';
        $owned->save();
        Storage::disk( 'uuid-migration-public' )->put( $owned->path, 'owned' );
        $borrower = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'borrowed.pdf',
            'path' => $owned->path, 'editor' => 'test',
        ] );
        $privateFile = File::forceCreate( [
            'disk' => 'private', 'mime' => 'application/pdf', 'name' => 'private.pdf',
            'path' => $private, 'editor' => 'test',
        ] );
        $remote = File::forceCreate( [
            'disk' => 'public', 'mime' => 'image/jpeg', 'name' => 'remote.jpg',
            'path' => 'https://example.com/remote.jpg', 'editor' => 'test',
        ] );
        $missing = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'missing.pdf',
            'path' => 'cms/test/missing.pdf', 'editor' => 'test',
        ] );

        $page = Page::where( 'path', 'blog' )->firstOrFail();
        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_page_file' )->updateOrInsert( [
            'page_id' => $page->id, 'file_id' => $first->id,
        ] );
        Event::fake( [PageInvalidated::class] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';
        $migration->up();

        $first->refresh();
        $second->refresh();
        $owned->refresh();
        $borrower->refresh();
        $privateFile->refresh();
        $remote->refresh();
        $missing->refresh();
        $firstVersion->refresh();

        $this->assertSame( strtolower( $first->id ), File::owner( 'test', $first->path ) );
        $this->assertSame( strtolower( $first->id ), File::owner( 'test', $first->previews->{500} ) );
        $this->assertSame( strtolower( $first->id ), File::owner( 'test', $firstVersion->data->path ) );
        $this->assertSame( strtolower( $second->id ), File::owner( 'test', $second->path ) );
        $this->assertSame( strtolower( $owned->id ), File::owner( 'test', $owned->path ) );
        $this->assertSame( strtolower( $borrower->id ), File::owner( 'test', $borrower->path ) );
        $this->assertSame( strtolower( $privateFile->id ), File::owner( 'test', $privateFile->path ) );
        $this->assertSame( strtolower( $missing->id ), File::owner( 'test', $missing->path ) );
        $this->assertNotSame( $first->path, $second->path );
        $this->assertNotSame( $owned->path, $borrower->path );
        $this->assertSame( 'https://example.com/remote.jpg', $remote->path );

        $this->assertSame( $shared, Storage::disk( 'uuid-migration-public' )->get( $first->path ) );
        $this->assertSame( $shared, Storage::disk( 'uuid-migration-public' )->get( $second->path ) );
        $this->assertSame( 'owned', Storage::disk( 'uuid-migration-public' )->get( $owned->path ) );
        $this->assertSame( 'owned', Storage::disk( 'uuid-migration-public' )->get( $borrower->path ) );
        $this->assertSame( $private, Storage::disk( 'uuid-migration-private' )->get( $privateFile->path ) );
        Storage::disk( 'uuid-migration-public' )->assertMissing( $missing->path );
        Storage::disk( 'uuid-migration-public' )->assertMissing( [$shared, $preview, $versioned] );
        Storage::disk( 'uuid-migration-private' )->assertMissing( $private );
        Event::assertDispatched( PageInvalidated::class );

        $paths = [
            $first->path, $second->path, $owned->path, $borrower->path,
            $privateFile->path, $missing->path, $firstVersion->data->path,
        ];
        Event::fake( [PageInvalidated::class] );

        $migration->up();

        $this->assertSame( $paths, [
            $first->refresh()->path,
            $second->refresh()->path,
            $owned->refresh()->path,
            $borrower->refresh()->path,
            $privateFile->refresh()->path,
            $missing->refresh()->path,
            $firstVersion->refresh()->data->path,
        ] );
        Event::assertNotDispatched( PageInvalidated::class );
    }
}
