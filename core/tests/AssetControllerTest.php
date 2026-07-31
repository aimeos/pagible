<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\FileResponse;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;


class AssetControllerTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        config( ['cms.disks.private.name' => 'asset-private'] );
        Storage::fake( 'asset-private' );
        Access::using( fn() => [] );
    }


    protected function defineRoutes( $router ) : void
    {
        $router->get( '/login', fn() => '' )->name( 'login' );
    }


    public function testAssetRateLimiter()
    {
        $this->assertNotNull( RateLimiter::limiter( 'cms-asset' ) );
    }


    public function testPublicPageCanDeliverPrivateFile()
    {
        [$page, $file] = $this->asset();
        $this->expectsDatabaseQueryCount( 3 );

        $response = $this->get( route( 'cms.asset', ['page' => $page->id, 'file' => $file->id], false ) )
            ->assertOk();

        $this->assertStringContainsString( 'private', (string) $response->headers->get( 'Cache-Control' ) );
        $this->assertStringContainsString( 'no-store', (string) $response->headers->get( 'Cache-Control' ) );
        $this->assertSame( 'private document', $response->baseResponse->getFile()->getContent() );
    }


    public function testPublicPageCanDeliverPrivateFileFromSharedElement()
    {
        [$page, $file] = $this->asset( false );
        $this->expectsDatabaseQueryCount( 3 );

        $response = $this->get( route( 'cms.asset', ['page' => $page->id, 'file' => $file->id], false ) )
            ->assertOk();

        $this->assertSame( 'private document', $response->baseResponse->getFile()->getContent() );
    }


    public function testPrivateFileRequiresAccessToItsPage()
    {
        [$page, $file] = $this->asset();
        PageAccess::set( [$page->id], [] );
        $url = route( 'cms.asset', ['page' => $page->id, 'file' => $file->id], false );

        $this->get( $url )->assertRedirect( '/login' );

        $user = new \App\Models\User( [
            'tenant_id' => 'test',
            'cmsperms' => [],
        ] );

        $response = $this->actingAs( $user )->get( $url )->assertOk();
        $this->assertSame( 'private document', $response->baseResponse->getFile()->getContent() );
    }


    public function testSignedPrivateFileSkipsRepeatedPageAuthorization()
    {
        [$page, $file] = $this->asset();
        PageAccess::set( [$page->id], [] );
        $url = URL::temporarySignedRoute(
            'cms.asset',
            now()->addMinute(),
            ['page' => $page->id, 'file' => $file->id, 'tenant' => Tenancy::value()],
        );
        $this->expectsDatabaseQueryCount( 1 );

        $response = $this->get( $url )->assertOk();

        $this->assertSame( 'private document', $response->baseResponse->getFile()->getContent() );
    }


    public function testSignedPrivateRemoteUrlDoesNotOutlivePageToken()
    {
        [$page, $file] = $this->asset();
        config( [
            'cms.disks.private.name' => 'asset-signed',
            'cms.disks.private.ttl' => 300,
        ] );
        $expiration = now()->addSeconds( 30 );
        $target = 'https://storage.example/private.txt?signature=test';
        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );

        $storage->shouldReceive( 'getAdapter' )->once()
            ->andReturn( \Mockery::mock( \League\Flysystem\FilesystemAdapter::class ) );
        $storage->shouldReceive( 'providesTemporaryUrls' )->once()->andReturnTrue();
        $storage->shouldReceive( 'temporaryUrl' )->once()->with(
            $file->path,
            \Mockery::on( fn( \DateTimeInterface $value ) =>
                $value->getTimestamp() === $expiration->getTimestamp()
            ),
            \Mockery::type( 'array' ),
        )->andReturn( $target );
        Storage::shouldReceive( 'disk' )->with( 'asset-signed' )->andReturn( $storage );

        $url = URL::temporarySignedRoute(
            'cms.asset',
            $expiration,
            ['page' => $page->id, 'file' => $file->id, 'tenant' => Tenancy::value()],
        );

        $this->get( $url )->assertRedirect( $target );
    }


    public function testExpiredPrivateFileTokenDoesNotBypassPageAuthorization()
    {
        [$page, $file] = $this->asset();
        PageAccess::set( [$page->id], [] );
        $url = URL::temporarySignedRoute(
            'cms.asset',
            now()->subMinute(),
            ['page' => $page->id, 'file' => $file->id, 'tenant' => Tenancy::value()],
        );

        $this->get( $url )->assertRedirect( '/login' );
    }


    public function testSignedPrivateFileTokenIsBoundToTenant()
    {
        [$page, $file] = $this->asset();
        $url = URL::temporarySignedRoute(
            'cms.asset',
            now()->addMinute(),
            ['page' => $page->id, 'file' => $file->id, 'tenant' => Tenancy::value()],
        );

        Tenancy::run( 'other', fn() => $this->get( $url )->assertForbidden() );
    }


    public function testSignedPrivateFileTokenRequiresTenant()
    {
        [$page, $file] = $this->asset();
        $url = URL::temporarySignedRoute(
            'cms.asset',
            now()->addMinute(),
            ['page' => $page->id, 'file' => $file->id],
        );

        $this->get( $url )->assertForbidden();
    }


    public function testPrivateFileMustBelongToAuthorizationPage()
    {
        [$page, $file] = $this->asset();
        $other = Page::whereKeyNot( $page->id )->firstOrFail();

        $this->get( route( 'cms.asset', ['page' => $other->id, 'file' => $file->id], false ) )
            ->assertNotFound();
    }


    public function testPrivatePreviewUsesItsStoredMimeType()
    {
        [$page, $file] = $this->asset();
        $path = dirname( $file->path ) . '/private.svg';
        Storage::disk( 'asset-private' )->put( $path, '<svg xmlns="http://www.w3.org/2000/svg"/>' );
        $file->previews = [500 => $path];
        $file->save();

        $this->get( route( 'cms.asset', [
            'page' => $page->id,
            'file' => $file->id,
            'variant' => 500,
        ], false ) )
            ->assertOk()
            ->assertHeader( 'Content-Disposition', 'inline; filename=private.svg' )
            ->assertHeader( 'Content-Security-Policy', "sandbox; default-src 'none'" )
            ->assertHeader( 'Content-Type', 'image/svg+xml' );
    }


    public function testPrivateUnsafeContentIsDownloaded()
    {
        [$page, $file] = $this->asset();
        $file->forceFill( ['mime' => 'text/html', 'name' => 'unsafe.html'] )->saveQuietly();

        $this->get( route( 'cms.asset', [
            'page' => $page->id,
            'file' => $file->id,
        ], false ) )
            ->assertOk()
            ->assertHeader( 'Content-Disposition', 'attachment; filename=unsafe.html' )
            ->assertHeader( 'Content-Security-Policy', "sandbox; default-src 'none'" )
            ->assertHeader( 'Content-Type', 'application/octet-stream' )
            ->assertHeader( 'X-Content-Type-Options', 'nosniff' );
    }


    public function testPrivateFileRejectsPathOwnedByAnotherUuid()
    {
        [$page, $file] = $this->asset();
        $other = new File();
        $other->setUniqueIds();
        $path = $other->dir() . '/private.txt';
        Storage::disk( 'asset-private' )->put( $path, 'other private document' );
        $file->forceFill( ['path' => $path] )->saveQuietly();

        $this->get( route( 'cms.asset', [
            'page' => $page->id,
            'file' => $file->id,
        ], false ) )->assertNotFound();
    }


    public function testPrivateRemoteDiskUsesFilesystemResponse()
    {
        config( ['cms.disks.private.name' => 'asset-remote'] );
        [, $file] = $this->asset();
        $path = $file->path;
        $headers = [
            'Cache-Control' => 'private, no-store',
            'Content-Disposition' => 'attachment; filename=private.txt',
            'Content-Security-Policy' => "sandbox; default-src 'none'",
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ];
        $response = response()->stream( fn() => null, 200, $headers );
        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );

        $storage->shouldReceive( 'getAdapter' )
            ->andReturn( \Mockery::mock( \League\Flysystem\FilesystemAdapter::class ) );
        $storage->shouldReceive( 'exists' )->with( $path )->andReturnTrue();
        $storage->shouldReceive( 'providesTemporaryUrls' )->andReturnFalse();
        $storage->shouldReceive( 'response' )->with( $path, null, $headers )->andReturn( $response );
        Storage::shouldReceive( 'disk' )->with( 'asset-remote' )->andReturn( $storage );

        $this->assertSame( $response, FileResponse::make( $file->id ) );
        $this->assertStringContainsString( 'private', (string) $response->headers->get( 'Cache-Control' ) );
    }


    public function testPrivateRemoteDiskUsesTemporaryUrlWithoutExistenceCheck()
    {
        config( [
            'cms.disks.private.name' => 'asset-signed',
            'cms.disks.private.ttl' => 120,
        ] );
        [, $file] = $this->asset();
        $path = $file->path;
        $url = 'https://storage.example/private.txt?signature=test';
        $options = [
            'ResponseCacheControl' => 'private, no-store',
            'ResponseContentDisposition' => 'attachment; filename=private.txt',
            'ResponseContentType' => 'application/octet-stream',
        ];
        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );

        $storage->shouldReceive( 'getAdapter' )->once()
            ->andReturn( \Mockery::mock( \League\Flysystem\FilesystemAdapter::class ) );
        $storage->shouldReceive( 'providesTemporaryUrls' )->once()->andReturnTrue();
        $storage->shouldReceive( 'temporaryUrl' )->once()
            ->with( $path, \Mockery::type( \DateTimeInterface::class ), $options )->andReturn( $url );
        $storage->shouldNotReceive( 'exists' );
        $storage->shouldNotReceive( 'response' );
        Storage::shouldReceive( 'disk' )->with( 'asset-signed' )->andReturn( $storage );

        $response = FileResponse::make( $file->id );

        $this->assertSame( $url, $response->headers->get( 'Location' ) );
        $this->assertSame( 'attachment; filename=private.txt', $response->headers->get( 'Content-Disposition' ) );
        $this->assertStringContainsString( 'private', (string) $response->headers->get( 'Cache-Control' ) );
    }


    public function testPrivateDraftElementFileCanBePreviewedByEditor()
    {
        $page = Page::where( 'path', 'blog' )->firstOrFail();
        $element = Element::firstOrFail();
        $file = new File();
        $file->setUniqueIds();
        $path = $file->dir() . '/private-draft.txt';
        Storage::disk( 'asset-private' )->put( $path, 'private draft document' );

        $file = File::forceCreate( [
            'id' => $file->id,
            'disk' => 'private',
            'lang' => 'en',
            'mime' => 'text/plain',
            'name' => 'private-draft.txt',
            'path' => $path,
            'editor' => 'test',
        ] );

        $page->latest->elements()->syncWithoutDetaching( [$element->id] );
        $element->latest->files()->syncWithoutDetaching( [$file->id] );

        $user = new \App\Models\User( [
            'tenant_id' => 'test',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] );

        $this->expectsDatabaseQueryCount( 3 );

        $response = $this->actingAs( $user )
            ->get( route( 'cms.asset', ['page' => $page->id, 'file' => $file->id], false ) )
            ->assertOk();

        $this->assertSame( 'private draft document', $response->baseResponse->getFile()->getContent() );
    }


    /**
     * @return array{Page, File}
     */
    private function asset( bool $direct = true ) : array
    {
        $page = Page::where( 'path', 'blog' )->firstOrFail();
        $file = new File();
        $file->setUniqueIds();
        $path = $file->dir() . '/private.txt';
        Storage::disk( 'asset-private' )->put( $path, 'private document' );

        $file = File::forceCreate( [
            'id' => $file->id,
            'disk' => 'private',
            'lang' => 'en',
            'mime' => 'text/plain',
            'name' => 'private.txt',
            'path' => $path,
            'editor' => 'test',
        ] );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        if( $direct ) {
            $db->table( 'cms_page_file' )->insert( [
                'page_id' => $page->id,
                'file_id' => $file->id,
            ] );
        } else {
            $element = Element::firstOrFail();
            $db->table( 'cms_page_element' )->updateOrInsert( [
                'page_id' => $page->id,
                'element_id' => $element->id,
            ] );
            $db->table( 'cms_element_file' )->updateOrInsert( [
                'element_id' => $element->id,
                'file_id' => $file->id,
            ] );
        }

        return [$page, $file];
    }
}
