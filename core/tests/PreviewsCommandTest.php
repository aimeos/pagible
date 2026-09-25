<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Commands\Previews;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class PreviewsCommandTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();

        config( ['cms.disks.public.name' => 'previews-public'] );
        Storage::fake( 'previews-public' );
    }


    protected function tearDown(): void
    {
        config( [
            'cms.disks.public.name' => 'public',
            'cms.image.preview-sizes' => [
                ['width' => 480, 'height' => 270],
                ['width' => 720, 'height' => 405],
                ['width' => 960, 'height' => 540],
                ['width' => 1920, 'height' => 1080],
            ],
        ] );

        parent::tearDown();
    }


    public function testPreviews(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 960]]] );

        $file = $this->file();
        $previews = (array) $file->previews;
        $disk = Storage::disk( 'previews-public' );
        $latestId = $file->latest_id;

        $this->assertEquals( [480, 960], array_keys( $previews ) );

        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

        $this->artisan( 'cms:previews', ['--tenant' => 'test'] )->assertExitCode( 0 );

        $file = File::findOrFail( $file->id );
        $updated = (array) $file->previews;
        $version = Version::findOrFail( $file->latest_id );

        $this->assertEquals( [480, 720], array_keys( $updated ) );
        $this->assertEquals( $previews[480], $updated[480] );
        $this->assertStringContainsString( '_720_', $updated[720] );
        $this->assertEquals( $updated, (array) $version->data->previews );
        $this->assertNotEquals( $latestId, $version->id );
        $this->assertEquals( Previews::EDITOR, $version->editor );
        $this->assertTrue( (bool) $version->published );
        $this->assertEquals( $previews, (array) Version::findOrFail( $latestId )->data->previews );

        $disk->assertExists( $updated[480] );
        $disk->assertExists( $updated[720] );
        $disk->assertExists( $previews[960] );
    }


    public function testPreviewsDifferentVersion(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 960]]] );

        $file = $this->file();
        $previews = (array) $file->previews;
        $version = Version::findOrFail( $file->latest_id );
        $data = $version->data;
        $data->previews = (object) [960 => $previews[960]];
        Version::whereKey( $version->id )->toBase()->update( ['data' => json_encode( $data )] );

        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

        $this->artisan( 'cms:previews', ['--tenant' => 'test'] )->assertExitCode( 0 );

        $file = File::findOrFail( $file->id );
        $updated = (array) $file->previews;
        $versioned = (array) Version::findOrFail( $file->latest_id )->data->previews;

        $this->assertEquals( [480, 720], array_keys( $updated ) );
        $this->assertEquals( $updated, $versioned );
        $this->assertStringContainsString( '_720_', $versioned[720] );
        $this->assertEquals( [960], array_keys( (array) Version::findOrFail( $version->id )->data->previews ) );
        Storage::disk( 'previews-public' )->assertExists( array_values( $updated ) );
        Storage::disk( 'previews-public' )->assertExists( $previews[960] );
    }


    public function testPreviewsId(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $file = $this->file();
        $other = $this->file();

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $this->artisan( 'cms:previews', ['--id' => [$file->id]] )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $this->assertEquals( [720], array_keys( (array) File::findOrFail( $file->id )->previews ) );
        $this->assertEquals( [480], array_keys( (array) File::findOrFail( $other->id )->previews ) );
    }


    public function testPreviewsVerbose(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $this->file();

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $this->artisan( 'cms:previews', ['-v' => true] )
            ->expectsOutput( '.' )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );
    }


    public function testPreviewsVeryVerbose(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $file = $this->file();

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $this->artisan( 'cms:previews', ['-vv' => true] )
            ->expectsOutput( sprintf( 'File "%s": Updated', $file->id ) )
            ->doesntExpectOutput( '.' )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );
    }


    public function testPreviewsCollapsedSizes(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720], ['width' => 960]]] );

        $file = new File();
        $file->ingest( UploadedFile::fake()->image( 'small.jpg', 600, 300 ) );
        $file = Resource::addFile( $file );

        // the 720 and 960 sizes result in the same width because the image is smaller and share one preview
        $this->assertEquals( [480, 600], array_keys( (array) $file->previews ) );
        $this->assertStringContainsString( '_720-960_', ( (array) $file->previews )[600] );
        $this->assertCount( 3, Storage::disk( 'previews-public' )->files( $file->dir() ) );
        $this->assertEquals( [], $file->diffPreviews( (array) $file->previews )['missing'] );

        $this->artisan( 'cms:previews' )
            ->expectsOutput( 'Tenant "test": 0 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $this->assertEquals( (array) $file->previews, (array) File::findOrFail( $file->id )->previews );
    }


    public function testPreviewsCollapsedSizesAdded(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 1920]]] );

        $file = $this->file();

        // the added size results in the same width as the existing one because the image is smaller
        config( ['cms.image.preview-sizes' => [['width' => 1920], ['width' => 2400]]] );

        $this->artisan( 'cms:previews' )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $file = File::findOrFail( $file->id );
        $previews = (array) $file->previews;

        $this->assertEquals( [1200], array_keys( $previews ) );
        $this->assertStringContainsString( '_1920-2400_', $previews[1200] );
        $this->assertEquals( [], $file->diffPreviews( $previews )['missing'] );

        $this->artisan( 'cms:previews' )
            ->expectsOutput( 'Tenant "test": 0 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );
    }


    public function testPreviewsUnknownName(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $file = $this->file();
        $previews = (array) $file->previews;
        $path = $file->dir() . '/photo.webp';

        Storage::disk( 'previews-public' )->put( $path, 'x' );
        $version = Version::findOrFail( $file->latest_id );
        $data = $version->data;
        $data->previews = (object) ( $previews + [300 => $path] );
        Version::whereKey( $version->id )->toBase()->update( ['data' => json_encode( $data )] );
        File::whereKey( $file->id )->toBase()->update( ['previews' => json_encode( $data->previews )] );

        $this->artisan( 'cms:previews' )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $this->assertEquals( $previews, (array) File::findOrFail( $file->id )->previews );
        Storage::disk( 'previews-public' )->assertExists( $path );
    }


    public function testPreviewsSkipsChangedFile(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $file = $this->file();
        $stored = Storage::disk( 'previews-public' )->files( $file->dir() );

        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

        $calls = 0;

        // an editor changes the file while the previews are generated the first time
        $restore = $this->whileStoring( function() use ( $file, &$calls ) {
            if( $calls++ === 0 )
            {
                $version = Version::findOrFail( File::findOrFail( $file->id )->latest_id );
                $data = clone $version->data;
                $data->name = 'changed';
                Version::whereKey( $version->id )->toBase()->update( ['data' => json_encode( $data )] );
            }
        } );

        \Illuminate\Support\Facades\Log::spy();

        try
        {
            $this->artisan( 'cms:previews' )
                ->expectsOutput( sprintf( 'File "%s": Skipped because it has been changed in the meantime', $file->id ) )
                ->expectsOutput( 'Tenant "test": 0 file(s) updated, 1 skipped, 0 failed' )
                ->assertExitCode( 0 );
        }
        finally
        {
            $restore();
        }

        $this->assertEquals( 1, $calls );
        $this->assertEquals( [480], array_keys( (array) File::findOrFail( $file->id )->previews ) );
        $this->assertEquals( $stored, Storage::disk( 'previews-public' )->files( $file->dir() ) );

        \Illuminate\Support\Facades\Log::shouldHaveReceived( 'warning' )
            ->withArgs( fn( $msg, $ctx ) => $msg === 'cms.previews' && $ctx['skipped'] === [$file->id] )->once();
    }


    public function testPreviewsForce(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 960]]] );

        $file = $this->file();
        $previews = (array) $file->previews;

        $this->artisan( 'cms:previews', ['--force' => true] )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $updated = File::findOrFail( $file->id );
        $versioned = (array) Version::findOrFail( $updated->latest_id )->data->previews;
        $updated = (array) $updated->previews;

        $this->assertEquals( [480, 960], array_keys( $updated ) );
        $this->assertEmpty( array_intersect( $previews, $updated ) );
        $this->assertEquals( $updated, $versioned );
        $this->assertEquals( $previews, (array) Version::findOrFail( $file->latest_id )->data->previews );
        Storage::disk( 'previews-public' )->assertExists( array_values( $previews ) );
        Storage::disk( 'previews-public' )->assertExists( array_values( $updated ) );
    }


    public function testPreviewsKeepsPreviewsOfNonImages(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 960]]] );

        $file = new File();
        $file->ingest(
            UploadedFile::fake()->createWithContent( 'document.txt', 'text' ),
            UploadedFile::fake()->image( 'preview.jpg', 1200, 600 ),
        );
        $file = Resource::addFile( $file );

        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

        $this->artisan( 'cms:previews', ['--force' => true] )
            ->expectsOutput( 'Tenant "test": 0 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $this->assertEquals( (array) $file->previews, (array) File::findOrFail( $file->id )->previews );
    }


    public function testPreviewsDraft(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 960]]] );

        $file = $this->file( false );
        $previews = (array) $file->previews;

        config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

        $this->artisan( 'cms:previews' )
            ->expectsOutput( 'Tenant "test": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $updated = File::findOrFail( $file->id );
        $version = Version::findOrFail( $updated->latest_id );

        // the file keeps the published previews until the new version is published
        $this->assertEquals( $previews, (array) $updated->previews );
        $this->assertNotEquals( $file->latest_id, $version->id );
        $this->assertFalse( (bool) $version->published );
        $this->assertEquals( [480, 720], array_keys( (array) $version->data->previews ) );
    }


    public function testPreviewsPrunesVersions(): void
    {
        config( ['cms.versions' => 1, 'cms.image.preview-sizes' => [['width' => 480], ['width' => 960]]] );

        try
        {
            $file = $this->file();
            $previews = (array) $file->previews;

            config( ['cms.image.preview-sizes' => [['width' => 480], ['width' => 720]]] );

            $this->artisan( 'cms:previews' )->assertExitCode( 0 );

            $file = File::findOrFail( $file->id );

            $this->assertEquals( 1, Version::where( 'versionable_id', $file->id )->count() );
            Storage::disk( 'previews-public' )->assertMissing( $previews[960] );
            Storage::disk( 'previews-public' )->assertExists( array_values( (array) $file->previews ) );
        }
        finally
        {
            config( ['cms.versions' => 10] );
        }
    }


    public function testPreviewsFailed(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $ids = [$this->file()->id, $this->file()->id];
        sort( $ids );

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $restore = $this->whileStoring( fn() => throw new \RuntimeException( 'broken' ) );

        try
        {
            $this->artisan( 'cms:previews', ['--force' => true] )
                ->expectsOutput( sprintf( 'File "%s": broken', $ids[0] ) )
                ->expectsOutput( 'Tenant "test": 0 file(s) updated, 0 skipped, 2 failed' )
                ->assertExitCode( 1 );
        }
        finally
        {
            $restore();
        }
    }


    public function testPreviewsStorageLocked(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $this->file();
        $this->file();

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $calls = 0;
        $restore = $this->whileStoring( function() use ( &$calls ) {
            $calls++;
            throw new \Illuminate\Contracts\Cache\LockTimeoutException();
        } );

        try
        {
            // the remaining files aren't processed if the storage is locked, e.g. by a running backup
            $this->artisan( 'cms:previews' )
                ->expectsOutput( 'Tenant "test": Stopped because the storage is locked, e.g. by a running backup' )
                ->expectsOutput( 'Tenant "test": 0 file(s) updated, 0 skipped, 0 failed' )
                ->assertExitCode( 1 );

            $this->assertEquals( 1, $calls );
        }
        finally
        {
            $restore();
        }
    }


    public function testPreviewsManagedTenancy(): void
    {
        $this->manageTenancy();

        try
        {
            $this->artisan( 'cms:previews', ['--tenant' => 'other'] )
                ->expectsOutput( 'Run the command for other tenants using: php artisan tenants:run cms:previews' )
                ->assertExitCode( 1 );
        }
        finally
        {
            $this->manageTenancy( false );
        }
    }


    public function testPreviewsEmptyTenant(): void
    {
        config( ['cms.image.preview-sizes' => [['width' => 480]]] );

        $file = $this->file();
        $default = Tenancy::run( '', fn() => $this->file() );

        config( ['cms.image.preview-sizes' => [['width' => 720]]] );

        $this->artisan( 'cms:previews', ['--tenant' => ''] )
            ->expectsOutput( 'Tenant "": 1 file(s) updated, 0 skipped, 0 failed' )
            ->assertExitCode( 0 );

        $this->assertEquals( [480], array_keys( (array) File::findOrFail( $file->id )->previews ) );
        $this->assertEquals( [720], array_keys( (array) File::withoutTenancy()->findOrFail( $default->id )->previews ) );
    }


    protected function file( bool $published = true ): File
    {
        $file = new File();
        $file->ingest( UploadedFile::fake()->image( 'photo.jpg', 1200, 600 ) );
        $file = Resource::addFile( $file );

        return $published ? $this->publish( $file ) : $file;
    }


    /**
     * Calls the callback before each preview is stored, e.g. to change the file concurrently.
     *
     * @param \Closure(string): mixed $callback Receives the path of the preview
     * @return \Closure(): void Restores the original disk
     */
    protected function whileStoring( \Closure $callback ): \Closure
    {
        $disk = Storage::disk( 'previews-public' );

        Storage::set( 'previews-public', new class( $disk->getDriver(), $disk->getAdapter(), $disk->getConfig(), $callback ) extends \Illuminate\Filesystem\FilesystemAdapter {
            public function __construct( mixed $driver, mixed $adapter, array $config, private \Closure $callback )
            {
                parent::__construct( $driver, $adapter, $config );
            }

            public function put( $path, $contents, $options = [] )
            {
                ( $this->callback )( $path );
                return parent::put( $path, $contents, $options );
            }

            public function writeStream( $path, $resource, array $options = [] )
            {
                ( $this->callback )( $path );
                return parent::writeStream( $path, $resource, $options );
            }
        } );

        return fn() => Storage::set( 'previews-public', $disk );
    }


    protected function publish( File $file ): File
    {
        Version::whereKey( $file->latest_id )->toBase()->update( ['published' => true] );
        return $file;
    }
}
