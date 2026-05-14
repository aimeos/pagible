<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;


class FileToolsTest extends McpTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all()
        ]);
    }


    // ── Read Files ─────────────────────────────────────────────────────

    public function testGetFile()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );
    }


    public function testGetFileNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\GetFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSearchFilesNoTerm()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class );

        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );
    }


    public function testSearchFilesFilterMime()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class, [
            'mime' => 'image/tiff',
        ] );

        $response->assertOk()->assertSee( ['Test file', 'image/tiff'] );
    }


    public function testSearchFiles()
    {
        if( DB::connection( config( 'cms.db' ) )->getDriverName() === 'sqlsrv' ) {
            sleep( 5 );
        }

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class, [
            'term' => 'Test image',
        ] );
        $response->assertOk()->assertSee( ['Test image', 'image/jpeg'] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SearchFiles::class, [
            'term' => 'Test',
            'mime' => 'image/tiff',
        ] );
        $response->assertOk()->assertSee( ['Test file', 'image/tiff'] );
    }


    // ── Write Files ────────────────────────────────────────────────────

    public function testAddFile()
    {
        Http::fake([
            'https://example.com/*' => Http::response( 'plain text content', 200 ),
        ]);

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'https://example.com/document.txt',
            'name' => 'New test file',
            'lang' => 'en',
            'description' => ['en' => 'A test file'],
        ] );

        $response->assertOk()->assertSee( ['New test file'] );
    }


    public function testAddFileInvalidUrl()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'ftp://invalid.com/file.jpg',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSaveFile()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'name' => 'Renamed image',
        ] );

        $response->assertOk()->assertSee( ['Renamed image'] );
    }


    public function testSaveFileNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testPublishFile()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishFile::class, [
            'id' => [$file->id],
        ] );

        $response->assertOk()->assertSee( ['published'] );
    }


    public function testPublishFileScheduled()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishFile::class, [
            'id' => [$file->id],
            'at' => '2099-12-31 23:59:59',
        ] );

        $response->assertOk()->assertSee( ['scheduled_at', '2099-12-31'] );
    }


    public function testDropFile()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image'] );
        $this->assertSoftDeleted( 'cms_files', ['id' => $file->id] );
    }


    public function testDropFileNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\DropFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testRestoreFile()
    {
        $file = File::where( 'name', 'Test image' )->first();
        $file->delete();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['Test image'] );
        $this->assertNull( File::find( $file->id )->deleted_at );
    }


    public function testRestoreFileNotDeleted()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\RestoreFile::class, [
            'id' => $file->id,
        ] );

        $response->assertOk()->assertSee( ['error', 'not deleted'] );
    }
}
