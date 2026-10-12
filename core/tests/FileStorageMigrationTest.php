<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Database\Seeders\TestSeeder;
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


    public function testMigrationRunsWithoutTransaction(): void
    {
        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';

        $this->assertFalse( $migration->withinTransaction );
    }


    public function testSkipsTablesWithPageVariants(): void
    {
        config( ['cms.disks.public.name' => 'uuid-migration-public'] );
        Storage::fake( 'uuid-migration-public' );

        $path = 'cms/test/legacy.pdf';
        Storage::disk( 'uuid-migration-public' )->put( $path, 'legacy' );
        $file = File::forceCreate( [
            'disk' => 'public', 'mime' => 'application/pdf', 'name' => 'legacy.pdf',
            'path' => $path, 'editor' => 'test',
        ] );
        $version = $file->versions()->forceCreate( [
            'editor' => 'test', 'data' => ['path' => $path, 'previews' => []],
        ] );

        $page = PageView::where( 'path', 'blog' )->firstOrFail();
        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_page_file' )->updateOrInsert( [
            'variant_id' => $page->variant_id, 'file_id' => $file->id,
        ] );
        Event::fake( [PageInvalidated::class] );

        $migration = require dirname( __DIR__ ) . '/database/migrations/2026_07_30_000000_move_files_to_uuid_directories.php';
        $migration->up();

        $this->assertSame( $path, $file->refresh()->path );
        $this->assertSame( $path, $version->refresh()->data->path );
        Storage::disk( 'uuid-migration-public' )->assertExists( $path );
        Event::assertNotDispatched( PageInvalidated::class );
    }
}
