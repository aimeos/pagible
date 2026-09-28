<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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


    public function testFileMutationToolsOnlyRequireTheirActionPermission()
    {
        $user = new \App\Models\User([
            'cmsperms' => ['file:save', 'file:drop', 'file:keep', 'file:publish', 'file:relocate'],
        ]);

        foreach( [
            \Aimeos\Cms\Tools\SaveFile::class,
            \Aimeos\Cms\Tools\RelocateFile::class,
            \Aimeos\Cms\Tools\DropFile::class,
            \Aimeos\Cms\Tools\RestoreFile::class,
            \Aimeos\Cms\Tools\PublishFile::class,
        ] as $tool ) {
            CmsServer::actingAs( $user )->tool( $tool )
                ->assertDontSee( 'not found' );
        }
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

        // the created file's id must be part of the response (File::$visible omits it)
        $file = File::where( 'name', 'New test file' )->first();
        $this->assertNotNull( $file );
        $response->assertSee( [$file->id] );
    }


    public function testAddFileContent()
    {
        Storage::fake( 'public' );
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'content' => 'data:image/svg+xml;base64,' . base64_encode( $svg ),
            'name' => 'Inline icon',
        ] );

        $file = File::where( 'name', 'Inline icon' )->firstOrFail();

        $response->assertOk()->assertSee( [$file->id, 'image/svg+xml'] );
        Storage::disk( 'public' )->assertExists( $file->path );
    }


    public function testAddFileContentInvalid()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'content' => 'not base64!',
        ] );

        $response->assertOk()->assertSee( ['Invalid file content'] );
    }


    public function testAddFileContentMimetype()
    {
        config( ['cms.upload.mimetypes' => ['image/']] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'content' => base64_encode( 'plain text content' ),
            'name' => 'text.txt',
        ] );

        $response->assertOk()->assertSee( ['not allowed'] );
        $this->assertNull( File::where( 'name', 'text.txt' )->first() );
    }


    public function testAddFileContentTooLarge()
    {
        config( ['cms.upload.filesize' => 0.0001] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'content' => base64_encode( str_repeat( 'x', 1000 ) ),
        ] );

        $response->assertOk()->assertSee( ['exceeds the maximum'] );
    }


    public function testAddFileContentLimit()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'content' => str_repeat( 'A', 8388612 ),
        ] );

        $response->assertHasErrors( ['must not exceed 8 MB'] );
    }


    public function testAddFileUrlAndContent()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'https://example.com/document.txt',
            'content' => base64_encode( 'text' ),
        ] );

        $response->assertHasErrors( ['url'] );
    }


    public function testAddFileRequiresSource()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'name' => 'Nothing',
        ] );

        $response->assertHasErrors( ['You must specify the URL'] );
    }


    public function testAddFileDownloadFailed()
    {
        Http::fake( ['https://example.com/*' => Http::response( '', 404 )] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'https://example.com/missing.jpg',
        ] );

        $response->assertOk()->assertSee( ['Failed to download'] );
    }


    public function testAddFileInvalidUrl()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'ftp://invalid.com/file.jpg',
        ] );

        $response->assertHasErrors( ['valid "http" or "https" URL'] );
    }


    public function testAddFilePrivate()
    {
        config( ['cms.disks.private.name' => 'mcp-private-upload'] );
        Storage::fake( 'mcp-private-upload' );
        Http::fake( [
            'https://example.com/*' => Http::response( 'plain text content', 200, [
                'Content-Type' => 'text/plain',
            ] ),
        ] );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\AddFile::class, [
            'url' => 'https://example.com/private.txt',
            'disk' => 'private',
            'name' => 'Private file',
        ] );

        $file = File::where( 'name', 'Private file' )->firstOrFail();

        $response->assertOk()->assertSee( ['private', $file->id] );
        $this->assertSame( 'private', $file->disk );
        Storage::disk( 'mcp-private-upload' )->assertExists( $file->path );
    }


    public function testSaveFile()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'latest_id' => $file->latest_id,
            'name' => 'Renamed image',
        ] );

        $response->assertOk()->assertSee( ['Renamed image'] );
    }


    public function testSaveFileContent()
    {
        Storage::fake( 'public' );
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'latest_id' => $file->latest_id,
            'content' => base64_encode( 'plain text content' ),
            'name' => 'notes.txt',
        ] );

        $response->assertOk()->assertSee( ['notes.txt', 'text/plain'] );

        $path = $file->fresh()->latest->data->path;
        $this->assertNotSame( $file->path, $path );
        Storage::disk( 'public' )->assertExists( $path );
    }


    public function testSaveFileContentInvalid()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'latest_id' => $file->latest_id,
            'content' => '%%%',
        ] );

        $response->assertOk()->assertSee( ['Invalid file content'] );
    }


    public function testSaveFileNotFound()
    {
        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => '00000000-0000-0000-0000-000000000000',
            'latest_id' => '00000000-0000-0000-0000-000000000000',
            'name' => 'Nope',
        ] );

        $response->assertOk()->assertSee( ['error'] );
    }


    public function testSaveFileRequiresLatestId()
    {
        $file = File::where( 'name', 'Test image' )->first();

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\SaveFile::class, [
            'id' => $file->id,
            'name' => 'No token',
        ] );

        $response->assertHasErrors( ['latest_id'] );
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


    public function testPublishFileSkipsPublishedSchedule()
    {
        $file = File::where( 'name', 'Test image' )->firstOrFail();
        $file->latest()->update( ['published' => true] );
        $publishAt = $file->latest()->value( 'publish_at' );

        $response = CmsServer::actingAs($this->user)->tool( \Aimeos\Cms\Tools\PublishFile::class, [
            'id' => [$file->id],
            'at' => '2099-12-31 23:59:59',
        ] );

        $response->assertOk()
            ->assertSee( ['skipped', 'Already published'] )
            ->assertDontSee( ['scheduled_at'] );
        $this->assertSame( $publishAt, $file->latest()->value( 'publish_at' ) );
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


    public function testRelocateFile()
    {
        config( [
            'cms.disks.public.name' => 'mcp-relocate-public',
            'cms.disks.private.name' => 'mcp-relocate-private',
        ] );
        Storage::fake( 'mcp-relocate-public' );
        Storage::fake( 'mcp-relocate-private' );

        $file = new File();
        $file->setUniqueIds();
        $file = File::forceCreate( [
            'id' => $file->id,
            'disk' => 'public',
            'mime' => 'text/plain',
            'name' => 'Relocate file',
            'path' => $file->dir() . '/relocate.txt',
            'editor' => 'test',
        ] );
        Storage::disk( 'mcp-relocate-public' )->put( $file->path, 'relocate' );

        $relocator = new \App\Models\User( ['cmsperms' => ['file:relocate']] );
        $response = CmsServer::actingAs($relocator)->tool( \Aimeos\Cms\Tools\RelocateFile::class, [
            'id' => $file->id,
            'disk' => 'private',
        ] );

        $response->assertOk()->assertSee( [$file->id, 'private'] );
        $this->assertSame( 'private', $file->refresh()->disk );
        Storage::disk( 'mcp-relocate-public' )->assertMissing( $file->path );
        Storage::disk( 'mcp-relocate-private' )->assertExists( $file->path );
    }
}
