<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Aimeos\Cms\Commands\Backup as BackupCommand;
use Aimeos\Cms\Commands\Restore as RestoreCommand;
use Aimeos\Cms\Events\BackupCreated;
use Aimeos\Cms\Events\RestoreCompleted;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;


class BackupTest extends BackupTestAbstract
{
    use CmsWithMigrations;
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    protected $seeder = TestSeeder::class;

    private string $tenant = 'test';


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );
        $app['config']->set( 'filesystems.disks.backup', [
            'driver' => 'local',
            'root' => storage_path( 'app/backup-test' ),
        ] );
        $app['config']->set( 'filesystems.disks.backup-public', [
            'driver' => 'local',
            'root' => storage_path( 'app/backup-public-test' ),
        ] );
        $app['config']->set( 'filesystems.disks.backup-private', [
            'driver' => 'local',
            'root' => storage_path( 'app/backup-private-test' ),
        ] );
        $app['config']->set( 'cms.disks.public.name', 'backup-public' );
        $app['config']->set( 'cms.disks.private.name', 'backup-private' );
    }


    protected function tearDown(): void
    {
        Storage::disk( 'backup' )->deleteDirectory( '' );
        Storage::disk( 'backup-public' )->deleteDirectory( '' );
        Storage::disk( 'backup-private' )->deleteDirectory( '' );
        parent::tearDown();
    }


    public function testBackupAndRestore(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $t = $this->tenant;

        $pageCount = DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count();
        $elementCount = DB::connection( $conn )->table( 'cms_elements' )->where( 'tenant_id', $t )->count();
        $fileCount = DB::connection( $conn )->table( 'cms_files' )->where( 'tenant_id', $t )->count();
        $versionCount = DB::connection( $conn )->table( 'cms_versions' )->where( 'tenant_id', $t )->count();

        $this->assertGreaterThan( 0, $pageCount );
        $this->assertGreaterThan( 0, $elementCount );
        $this->assertGreaterThan( 0, $fileCount );
        $this->assertGreaterThan( 0, $versionCount );

        Event::fake();

        $backupFile = $this->backup( $t );

        Event::assertDispatched( BackupCreated::class, function( $event ) use ( $t ) {
            return $event->tenant === $t;
        } );

        $this->cleanup( $conn, $t );

        $this->assertEquals( 0, DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count() );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--tenant' => $t,
            '--disk' => 'backup',
            '--no-media' => true,
            '--force' => true,
        ] )->assertSuccessful();

        Event::assertDispatched( RestoreCompleted::class, function( $event ) use ( $t ) {
            return $event->tenant === $t;
        } );

        $this->assertEquals( $pageCount, DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count() );
        $this->assertEquals( $elementCount, DB::connection( $conn )->table( 'cms_elements' )->where( 'tenant_id', $t )->count() );
        $this->assertEquals( $fileCount, DB::connection( $conn )->table( 'cms_files' )->where( 'tenant_id', $t )->count() );
        $this->assertEquals( $versionCount, DB::connection( $conn )->table( 'cms_versions' )->where( 'tenant_id', $t )->count() );
    }


    public function testBackupRejectsUnsafeTenant(): void
    {
        $this->artisan( 'cms:backup', [
            '--tenant' => '019faa86-6307-71d3-bc70-75fa4f6f0720',
            '--disk' => 'backup',
        ] )
            ->expectsOutput( 'Backup failed: Invalid tenant ID' )
            ->assertExitCode( 1 );
    }


    public function testBackupRejectsConcurrentMediaOperation(): void
    {
        $status = Utils::storageLock( $this->tenant, fn() =>
            \Illuminate\Support\Facades\Artisan::call( 'cms:backup', [
                '--tenant' => $this->tenant,
                '--disk' => 'backup',
            ] ),
        );

        $this->assertSame( 1, $status );
        $this->assertStringContainsString(
            'Another backup/restore or media operation is in progress for this tenant.',
            \Illuminate\Support\Facades\Artisan::output(),
        );
    }


    public function testBackupDefaultTenantOnlyCopiesCatalogMedia(): void
    {
        [$current, $preview, $history] = Tenancy::run( '', function() {
            $file = new File();
            $file->setUniqueIds();
            $dir = $file->dir();
            $current = $dir . '/current.txt';
            $preview = $dir . '/preview.webp';
            $history = $dir . '/history.txt';

            $file = File::forceCreate( [
                'id' => $file->id,
                'disk' => 'public',
                'mime' => 'text/plain',
                'name' => 'current.txt',
                'path' => $current,
                'previews' => [480 => $preview],
                'editor' => 'test',
            ] );
            $file->versions()->forceCreate( [
                'editor' => 'test',
                'data' => [
                    'path' => $history,
                    'previews' => [480 => $preview, 960 => 'https://example.com/remote.webp'],
                ],
            ] );

            File::forceCreate( [
                'disk' => 'public',
                'mime' => 'image/jpeg',
                'name' => 'remote.jpg',
                'path' => 'https://example.com/remote.jpg',
                'editor' => 'test',
            ] );
            $file->deleteQuietly();

            return [$current, $preview, $history];
        } );

        $foreign = new File();
        $foreign->setUniqueIds();
        $foreignPath = $foreign->dir() . '/foreign.txt';
        File::forceCreate( [
            'id' => $foreign->id,
            'disk' => 'public',
            'mime' => 'text/plain',
            'name' => 'foreign.txt',
            'path' => $foreignPath,
            'editor' => 'test',
        ] );
        $orphan = 'cms/' . ( new File() )->newUniqueId() . '/orphan.txt';

        foreach( [$current, $preview, $history, $foreignPath, $orphan] as $path ) {
            Storage::disk( 'backup-public' )->put( $path, $path );
        }

        $this->artisan( 'cms:backup', ['--tenant' => '', '--disk' => 'backup'] )
            ->assertSuccessful();

        $file = collect( Storage::disk( 'backup' )->files() )
            ->first( fn( $path ) => str_starts_with( $path, 'pagible--' ) );
        $this->assertNotNull( $file );

        $zip = new \ZipArchive();
        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $file ) ) );
        $names = [];

        for( $i = 0; $i < $zip->numFiles; $i++ ) {
            $names[] = $zip->getNameIndex( $i );
        }

        $zip->close();

        foreach( [$current, $preview, $history] as $path ) {
            $this->assertSame( 1, count( array_keys( $names, 'media/public/' . $path, true ) ) );
        }

        $this->assertNotContains( 'media/public/' . $foreignPath, $names );
        $this->assertNotContains( 'media/public/' . $orphan, $names );
    }


    public function testBackupScopesRelationshipTablesToTenant(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $page = (array) $db->table( 'cms_pages' )->where( 'tenant_id', $this->tenant )->first();
        $file = (array) $db->table( 'cms_files' )->where( 'tenant_id', $this->tenant )->first();
        $pageId = (string) \Illuminate\Support\Str::uuid7();
        $fileId = (string) \Illuminate\Support\Str::uuid7();

        $page['id'] = $pageId;
        $page['tenant_id'] = 'other';
        $page['parent_id'] = null;
        $page['related_id'] = null;
        $page['latest_id'] = null;
        $page['domain'] = 'other.example';
        $page['path'] = 'foreign-' . substr( $pageId, 0, 8 );
        $file['id'] = $fileId;
        $file['tenant_id'] = 'other';
        $file['latest_id'] = null;
        $file['path'] = 'cms/other/' . $fileId . '/foreign.txt';

        $db->table( 'cms_pages' )->insert( $page );
        $db->table( 'cms_files' )->insert( $file );
        $db->table( 'cms_page_file' )->insert( ['page_id' => $pageId, 'file_id' => $fileId] );

        $backup = $this->backup( $this->tenant );
        $zip = new \ZipArchive();
        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backup ) ) );

        try {
            $relations = $zip->getFromName( 'cms_page_file.ndjson' );
            $this->assertNotFalse( $relations );
            $this->assertStringNotContainsString( $pageId, $relations );
            $this->assertStringNotContainsString( $fileId, $relations );
        } finally {
            $zip->close();
        }
    }


    public function testBackupKeep(): void
    {
        $t = $this->tenant;
        $prefix = 'pagible-' . $t . '-';

        for( $i = 0; $i < 3; $i++ )
        {
            $this->artisan( 'cms:backup', ['--tenant' => $t, '--disk' => 'backup', '--no-media' => true] )
                ->assertSuccessful();
            usleep( 100_000 );
        }

        $files = collect( Storage::disk( 'backup' )->files() )
            ->filter( fn( $f ) => str_starts_with( $f, $prefix ) );

        $this->assertEquals( 3, $files->count() );

        $this->artisan( 'cms:backup', ['--tenant' => $t, '--disk' => 'backup', '--no-media' => true, '--keep' => 2] )
            ->assertSuccessful();

        $files = collect( Storage::disk( 'backup' )->files() )
            ->filter( fn( $f ) => str_starts_with( $f, $prefix ) );

        $this->assertEquals( 2, $files->count() );
    }


    public function testBackupList(): void
    {
        $t = $this->tenant;

        $this->artisan( 'cms:backup', ['--tenant' => $t, '--disk' => 'backup', '--no-media' => true] )
            ->assertSuccessful();

        $this->artisan( 'cms:restore', ['--list' => true, '--disk' => 'backup'] )
            ->assertSuccessful();
    }


    public function testBackupRestoresPublicAndPrivateMedia(): void
    {
        $public = File::where( 'tenant_id', $this->tenant )->firstOrFail();
        $public->forceFill( [
            'disk' => 'public',
            'path' => 'cms/test/' . strtolower( (string) $public->id ) . '/public.txt',
            'previews' => (object) [],
        ] )->saveQuietly();

        $private = File::where( 'tenant_id', $this->tenant )->whereKeyNot( $public->id )->firstOrFail();
        $private->forceFill( [
            'disk' => 'private',
            'path' => 'cms/test/' . strtolower( (string) $private->id ) . '/private.txt',
            'previews' => (object) [],
        ] )->saveQuietly();
        $publicHistory = dirname( $public->path ) . '/public-history.txt';
        $privateHistory = dirname( $private->path ) . '/private-history.txt';
        $public->versions()->forceCreate( [
            'editor' => 'test',
            'data' => ['path' => $publicHistory, 'previews' => []],
        ] );
        $private->versions()->forceCreate( [
            'editor' => 'test',
            'data' => ['path' => $privateHistory, 'previews' => []],
        ] );

        Storage::disk( 'backup-public' )->put( $public->path, 'public media' );
        Storage::disk( 'backup-public' )->put( $publicHistory, 'public history' );
        Storage::disk( 'backup-private' )->put( $private->path, 'private media' );
        Storage::disk( 'backup-private' )->put( $privateHistory, 'private history' );

        $this->artisan( 'cms:backup', ['--tenant' => $this->tenant, '--disk' => 'backup'] )
            ->assertSuccessful();

        $prefix = 'pagible-' . $this->tenant . '-';
        $file = collect( Storage::disk( 'backup' )->files() )
            ->first( fn( $path ) => str_starts_with( $path, $prefix ) );
        $this->assertNotNull( $file );

        Storage::disk( 'backup-public' )->delete( $public->path );
        Storage::disk( 'backup-public' )->delete( $publicHistory );
        Storage::disk( 'backup-private' )->delete( $private->path );
        Storage::disk( 'backup-private' )->delete( $privateHistory );

        $this->artisan( 'cms:restore', [
            'file' => $file,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--media-only' => true,
            '--force' => true,
        ] )->assertSuccessful();

        $this->assertSame( 'public media', Storage::disk( 'backup-public' )->get( $public->path ) );
        $this->assertSame( 'public history', Storage::disk( 'backup-public' )->get( $publicHistory ) );
        $this->assertSame( 'private media', Storage::disk( 'backup-private' )->get( $private->path ) );
        $this->assertSame( 'private history', Storage::disk( 'backup-private' )->get( $privateHistory ) );
    }


    public function testBackupRestoreRejectsMediaOnWrongLogicalDisk(): void
    {
        $file = File::where( 'tenant_id', $this->tenant )->firstOrFail();
        $path = 'cms/test/' . strtolower( (string) $file->id ) . '/private.txt';
        $file->forceFill( [
            'disk' => 'private',
            'path' => $path,
            'previews' => (object) [],
        ] )->saveQuietly();
        Storage::disk( 'backup-private' )->put( $path, 'private media' );

        $this->artisan( 'cms:backup', ['--tenant' => $this->tenant, '--disk' => 'backup'] )
            ->assertSuccessful();

        $prefix = 'pagible-' . $this->tenant . '-';
        $backup = collect( Storage::disk( 'backup' )->files() )
            ->first( fn( $entry ) => str_starts_with( $entry, $prefix ) );
        $this->assertNotNull( $backup );

        $zip = new \ZipArchive();
        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backup ) ) );
        $this->assertTrue( $zip->renameName( 'media/private/' . $path, 'media/public/' . $path ) );
        $this->resign( $zip );
        $this->assertTrue( $zip->close() );
        Storage::disk( 'backup-private' )->delete( $path );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--media-only' => true,
            '--force' => true,
        ] )
            ->expectsOutput( sprintf(
                'Restore failed: Media disk "public" does not match catalog disk "private" for path "%s"',
                $path,
            ) )
            ->assertExitCode( 1 );

        $this->assertFalse( Storage::disk( 'backup-public' )->exists( $path ) );
        $this->assertFalse( Storage::disk( 'backup-private' )->exists( $path ) );
    }


    public function testBackupRestoreAutomaticallyRejectsTamperedMedia(): void
    {
        [, $path, $backup] = $this->privateBackup();
        $zip = new \ZipArchive();

        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backup ) ) );
        $this->assertTrue( $zip->addFromString( 'media/private/' . $path, 'tampered media' ) );
        $this->assertTrue( $zip->close() );
        Storage::disk( 'backup-private' )->delete( $path );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--media-only' => true,
            '--force' => true,
        ] )
            ->expectsOutput( '  FAILED: media/private/' . $path )
            ->expectsOutput( 'Backup integrity check failed.' )
            ->assertExitCode( 1 );

        $this->assertFalse( Storage::disk( 'backup-private' )->exists( $path ) );
    }


    public function testBackupRestoreReconcilesOppositeDisk(): void
    {
        [$file, $path, $backup] = $this->privateBackup();
        $file->forceFill( ['disk' => 'public'] )->saveQuietly();
        Storage::disk( 'backup-private' )->delete( $path );
        Storage::disk( 'backup-public' )->put( $path, 'stale public media' );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--force' => true,
        ] )->assertSuccessful();

        $this->assertSame( 'private', $file->refresh()->disk );
        $this->assertSame( 'private media', Storage::disk( 'backup-private' )->get( $path ) );
        $this->assertFalse( Storage::disk( 'backup-public' )->exists( $path ) );
    }


    public function testBackupRestoreOverwritesExistingMedia(): void
    {
        [, $path, $backup] = $this->privateBackup();
        Storage::disk( 'backup-private' )->put( $path, 'stale private media' );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--media-only' => true,
            '--force' => true,
        ] )->assertSuccessful();

        $this->assertSame( 'private media', Storage::disk( 'backup-private' )->get( $path ) );
    }


    public function testBackupRestoreRollsBackOverwrittenMediaWhenDatabaseRestoreFails(): void
    {
        [, $path, $backup] = $this->privateBackup();
        Storage::disk( 'backup-private' )->put( $path, 'live private media' );
        $zip = new \ZipArchive();

        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backup ) ) );
        $this->assertTrue( $zip->deleteName( 'cms_pages.ndjson' ) );
        $this->assertTrue( $zip->addFromString( 'cms_pages.ndjson', "{\"id\":null}\n" ) );
        $this->assertTrue( $zip->close() );
        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backup ) ) );
        $this->resign( $zip );
        $this->assertTrue( $zip->close() );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--force' => true,
        ] )->assertExitCode( 1 );

        $this->assertSame( 'live private media', Storage::disk( 'backup-private' )->get( $path ) );
    }


    public function testBackupRestoreMediaOnlyRejectsDiskChanges(): void
    {
        [$file, $path, $backup] = $this->privateBackup();
        $file->forceFill( ['disk' => 'public'] )->saveQuietly();
        Storage::disk( 'backup-private' )->delete( $path );
        Storage::disk( 'backup-public' )->put( $path, 'public media' );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--media-only' => true,
            '--force' => true,
        ] )
            ->expectsOutput( sprintf(
                'Restore failed: File "%s" uses disk "public", backup expects "private"',
                $file->id,
            ) )
            ->assertExitCode( 1 );

        $this->assertSame( 'public media', Storage::disk( 'backup-public' )->get( $path ) );
        $this->assertFalse( Storage::disk( 'backup-private' )->exists( $path ) );
    }


    public function testBackupRestoreRejectsConflictingMediaOptions(): void
    {
        $this->artisan( 'cms:restore', [
            'file' => 'backup.zip',
            '--disk' => 'backup',
            '--media-only' => true,
            '--no-media' => true,
        ] )
            ->expectsOutput( 'The --no-media and --media-only options cannot be combined.' )
            ->assertExitCode( 1 );
    }


    public function testBackupRestoreNoMediaRejectsDiskChanges(): void
    {
        [$file, $path, $backup] = $this->privateBackup();
        $file->forceFill( ['disk' => 'public'] )->saveQuietly();
        Storage::disk( 'backup-private' )->delete( $path );
        Storage::disk( 'backup-public' )->put( $path, 'public media' );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--no-media' => true,
            '--force' => true,
        ] )
            ->expectsOutput( sprintf(
                'Restore failed: File "%s" uses disk "public", backup expects "private"',
                $file->id,
            ) )
            ->assertExitCode( 1 );

        $this->assertSame( 'public', $file->refresh()->disk );
        $this->assertSame( 'public media', Storage::disk( 'backup-public' )->get( $path ) );
    }


    public function testBackupRestoreNoMediaRejectsOppositeDiskMedia(): void
    {
        [$file, $path, $backup] = $this->privateBackup();
        Storage::disk( 'backup-public' )->put( $path, 'stale public media' );

        $this->artisan( 'cms:restore', [
            'file' => $backup,
            '--tenant' => $this->tenant,
            '--disk' => 'backup',
            '--no-media' => true,
            '--force' => true,
        ] )
            ->expectsOutput( sprintf(
                'Restore failed: Media path "%s" still exists on disk "public"; restore it without --no-media',
                $path,
            ) )
            ->assertExitCode( 1 );

        $this->assertSame( 'private', $file->refresh()->disk );
        $this->assertSame( 'stale public media', Storage::disk( 'backup-public' )->get( $path ) );
    }


    public function testBackupMerge(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $t = $this->tenant;

        $pageCount = DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count();
        $backupFile = $this->backup( $t );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--tenant' => $t,
            '--disk' => 'backup',
            '--no-media' => true,
            '--merge' => true,
            '--force' => true,
        ] )->assertSuccessful();

        $this->assertEquals( $pageCount, DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count() );
    }


    public function testBackupVerify(): void
    {
        $t = $this->tenant;
        $backupFile = $this->backup( $t );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--disk' => 'backup',
            '--verify' => true,
        ] )->assertSuccessful();
    }


    public function testBackupRejectsUnsupportedFormat(): void
    {
        $backupFile = $this->backup( $this->tenant );
        $zip = new \ZipArchive();

        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backupFile ) ) );

        $manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
        $manifest['format_version'] = '1';

        $this->assertTrue( $zip->addFromString( 'manifest.json', (string) json_encode( $manifest ) ) );
        $this->assertTrue( $zip->close() );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--disk' => 'backup',
            '--verify' => true,
        ] )
            ->expectsOutput( 'Restore failed: Unsupported backup format version "1"' )
            ->assertExitCode( 1 );
    }


    public function testBackupRejectsTamperedManifest(): void
    {
        $backupFile = $this->backup( $this->tenant );
        $zip = new \ZipArchive();

        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backupFile ) ) );
        $manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
        $manifest['tenant_id'] = 'other';
        $this->assertTrue( $zip->addFromString( 'manifest.json', (string) json_encode( $manifest ) ) );
        $this->assertTrue( $zip->close() );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--disk' => 'backup',
            '--verify' => true,
        ] )
            ->expectsOutput( 'Restore failed: Backup manifest signature is invalid' )
            ->assertExitCode( 1 );
    }


    public function testBackupRejectsUncheckedEntry(): void
    {
        $backupFile = $this->backup( $this->tenant );
        $zip = new \ZipArchive();

        $this->assertTrue( $zip->open( Storage::disk( 'backup' )->path( $backupFile ) ) );
        $this->assertTrue( $zip->addFromString( 'unchecked.txt', 'unchecked' ) );
        $this->assertTrue( $zip->close() );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--disk' => 'backup',
            '--verify' => true,
        ] )
            ->expectsOutput( '  UNCHECKED: unchecked.txt' )
            ->expectsOutput( 'Backup integrity check failed.' )
            ->assertExitCode( 1 );
    }


    public function testBackupCrossTenant(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $t = $this->tenant;
        $context = null;

        $pageCount = DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count();
        $backupFile = $this->backup( $t );
        Event::listen( RestoreCompleted::class, function() use ( &$context ) {
            $context = Tenancy::value();
        } );

        // Delete source tenant data so UUIDs don't conflict
        $this->cleanup( $conn, $t );

        $this->artisan( 'cms:restore', [
            'file' => $backupFile,
            '--tenant' => 'other',
            '--disk' => 'backup',
            '--no-media' => true,
            '--force' => true,
        ] )->assertSuccessful();

        $this->assertEquals( $pageCount, DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', 'other' )->count() );
        $this->assertSame( 'other', $context );
        $this->assertSame( 'test', Tenancy::value() );
    }


    public function testBackupCrossTenantRejectsForeignIdCollision(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $source = DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $this->tenant )->count();
        $backupFile = $this->backup( $this->tenant );

        $status = \Illuminate\Support\Facades\Artisan::call( 'cms:restore', [
            'file' => $backupFile,
            '--tenant' => 'other',
            '--disk' => 'backup',
            '--no-media' => true,
            '--merge' => true,
            '--force' => true,
        ] );

        $this->assertSame( 1, $status );
        $this->assertStringContainsString(
            'Cross-tenant restore conflicts with existing rows in table',
            \Illuminate\Support\Facades\Artisan::output(),
        );
        $this->assertSame( $source, DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', $this->tenant )->count() );
        $this->assertSame( 0, DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', 'other' )->count() );
    }


    public function testRestoreManagedTenantMismatchFailsBeforeWriting(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $backupFile = $this->backup( $this->tenant );
        $managed = new \ReflectionProperty( Tenancy::class, 'managed' );
        $managed->setValue( null, true );

        try
        {
            $this->artisan( 'cms:restore', [
                'file' => $backupFile,
                '--tenant' => 'other',
                '--disk' => 'backup',
                '--no-media' => true,
                '--force' => true,
            ] )
                ->expectsOutput( 'Restore failed: Operation was not initialized in its tenant context.' )
                ->assertExitCode( 1 );
        }
        finally {
            $managed->setValue( null, false );
        }

        $this->assertSame( 0, DB::connection( $conn )->table( 'cms_pages' )
            ->where( 'tenant_id', 'other' )->count() );
    }


    public function testRestorePreservesPublicRemoteHotlinks(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $table, string $tenant ): array
            {
                return $this->rewrite( $row, $table, $tenant, $tenant, true, [] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();
        $row = $command->row( [
            'id' => $id,
            'disk' => 'public',
            'path' => 'https://example.com/file.pdf',
            'previews' => '{}',
        ], 'cms_files', 'test' );

        $this->assertSame( 'https://example.com/file.pdf', $row['path'] );
    }


    public function testRestorePreservesPublicRemoteVersionHotlinks(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $tenant, string $disk ): array
            {
                $id = strtolower( (string) $row['versionable_id'] );

                return $this->rewrite( $row, 'cms_versions', $tenant, $tenant, true, [
                    $id => ['disk' => $disk, 'paths' => []],
                ] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();
        $row = $command->row( [
            'versionable_id' => $id,
            'versionable_type' => File::class,
            'data' => json_encode( ['path' => 'https://example.com/file.pdf'] ),
        ], 'test', 'public' );

        $this->assertSame( 'https://example.com/file.pdf', json_decode( $row['data'], true )['path'] );
    }


    public function testRestoreRejectsTruncatedRemoteBackup(): void
    {
        $stream = fopen( 'php://temp', 'w+' );
        $this->assertIsResource( $stream );
        fwrite( $stream, 'short' );
        rewind( $stream );

        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );
        $storage->shouldReceive( 'exists' )->once()->with( 'backup.zip' )->andReturnTrue();
        $storage->shouldReceive( 'path' )->once()->with( 'backup.zip' )->andReturn( '/missing/backup.zip' );
        $storage->shouldReceive( 'size' )->once()->with( 'backup.zip' )->andReturn( 10 );
        $storage->shouldReceive( 'readStream' )->once()->with( 'backup.zip' )->andReturn( $stream );
        Storage::set( 'remote-backup', $storage );

        $command = new class extends RestoreCommand {
            public function backupPath( string $disk, string $file ): string
            {
                return $this->path( $disk, $file );
            }
        };

        try {
            $command->backupPath( 'remote-backup', 'backup.zip' );
            $this->fail( 'Expected a truncated backup to be rejected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'Failed to verify temporary backup file', $e->getMessage() );
        } finally {
            Storage::forgetDisk( 'remote-backup' );
        }
    }


    public function testRestoreRemovesTemporaryBackupWhenDownloadThrows(): void
    {
        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );
        $storage->shouldReceive( 'exists' )->once()->with( 'backup.zip' )->andReturnTrue();
        $storage->shouldReceive( 'path' )->once()->with( 'backup.zip' )->andReturn( '/missing/backup.zip' );
        $storage->shouldReceive( 'size' )->once()->with( 'backup.zip' )
            ->andThrow( new \RuntimeException( 'Remote size failed' ) );
        Storage::set( 'throwing-backup', $storage );

        $command = new class extends RestoreCommand {
            public ?string $temporary = null;

            public function backupPath( string $disk, string $file ): string
            {
                return $this->path( $disk, $file );
            }


            protected function tempFilePath( string $prefix ): string
            {
                return $this->temporary = parent::tempFilePath( $prefix );
            }
        };

        try
        {
            $command->backupPath( 'throwing-backup', 'backup.zip' );
            $this->fail( 'Expected the remote size lookup to fail' );
        }
        catch( \RuntimeException $e ) {
            $this->assertSame( 'Remote size failed', $e->getMessage() );
            $this->assertNotNull( $command->temporary );
            $this->assertFileDoesNotExist( $command->temporary );
        }
        finally {
            Storage::forgetDisk( 'throwing-backup' );
        }
    }


    public function testBackupDeletesUnverifiedArchiveWhenUploadThrows(): void
    {
        $dir = storage_path( 'app/cms-backup-source-' . bin2hex( random_bytes( 8 ) ) );
        mkdir( $dir, 0700 );
        file_put_contents( $dir . '/data.txt', 'backup' );

        $storage = \Mockery::mock( \Illuminate\Filesystem\FilesystemAdapter::class );
        $storage->shouldReceive( 'writeStream' )->once()->with(
            'partial.zip',
            \Mockery::type( 'resource' ),
        )->andThrow( new \RuntimeException( 'Upload failed' ) );
        $storage->shouldReceive( 'delete' )->once()->with( 'partial.zip' )->andReturnTrue();
        Storage::set( 'throwing-upload', $storage );

        $command = new class extends BackupCommand {
            public function archive( string $dir, string $disk, string $file ): string
            {
                return $this->createZip( $dir, $disk, $file );
            }
        };

        try
        {
            $command->archive( $dir, 'throwing-upload', 'partial.zip' );
            $this->fail( 'Expected the backup upload to fail' );
        }
        catch( \RuntimeException $e ) {
            $this->assertSame( 'Upload failed', $e->getMessage() );
        }
        finally
        {
            Storage::forgetDisk( 'throwing-upload' );
            @unlink( $dir . '/data.txt' );
            @rmdir( $dir );
        }
    }


    public function testRestoreRejectsFilePathOutsideUuidDirectory(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $table, string $tenant ): array
            {
                return $this->rewrite( $row, $table, $tenant, $tenant, true, [] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();
        $other = (string) \Illuminate\Support\Str::uuid7();

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'outside UUID directory' );

        $command->row( [
            'id' => $id,
            'disk' => 'private',
            'path' => 'cms/test/' . $other . '/file.pdf',
            'previews' => '{}',
        ], 'cms_files', 'test' );
    }


    public function testRestoreRejectsInvalidLogicalDisk(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $table, string $tenant ): array
            {
                return $this->rewrite( $row, $table, $tenant, $tenant, true, [] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'Invalid file disk' );

        $command->row( [
            'id' => $id,
            'disk' => 'archive',
            'path' => 'cms/test/' . $id . '/file.pdf',
            'previews' => '{}',
        ], 'cms_files', 'test' );
    }


    public function testRestoreRejectsPrivateRemoteVersionHotlinks(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $tenant, string $disk ): array
            {
                $id = strtolower( (string) $row['versionable_id'] );

                return $this->rewrite( $row, 'cms_versions', $tenant, $tenant, true, [
                    $id => ['disk' => $disk, 'paths' => []],
                ] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'outside UUID directory' );

        $command->row( [
            'versionable_id' => $id,
            'versionable_type' => File::class,
            'data' => json_encode( ['path' => 'https://example.com/file.pdf'] ),
        ], 'test', 'private' );
    }


    public function testRestoreRejectsVersionPathOutsideUuidDirectory(): void
    {
        $command = new class extends RestoreCommand {
            public function row( array $row, string $table, string $tenant, string $disk ): array
            {
                $id = strtolower( (string) $row['versionable_id'] );

                return $this->rewrite( $row, $table, $tenant, $tenant, true, [
                    $id => ['disk' => $disk, 'paths' => []],
                ] );
            }
        };
        $id = (string) \Illuminate\Support\Str::uuid7();
        $other = (string) \Illuminate\Support\Str::uuid7();

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessage( 'outside UUID directory' );

        $command->row( [
            'versionable_id' => $id,
            'versionable_type' => File::class,
            'data' => json_encode( ['path' => 'cms/test/' . $other . '/file.pdf'] ),
        ], 'cms_versions', 'test', 'private' );
    }


    private function backup( string $tenant ): string
    {
        $this->artisan( 'cms:backup', ['--tenant' => $tenant, '--disk' => 'backup', '--no-media' => true] )
            ->assertSuccessful();

        $prefix = 'pagible-' . $tenant . '-';
        $file = collect( Storage::disk( 'backup' )->files() )->first( fn( $f ) => str_starts_with( $f, $prefix ) );
        $this->assertNotNull( $file );

        return $file;
    }


    private function cleanup( string $conn, string $tenant ): void
    {
        DB::connection( $conn )->table( 'cms_page_element' )->delete();
        DB::connection( $conn )->table( 'cms_page_file' )->delete();
        DB::connection( $conn )->table( 'cms_element_file' )->delete();
        DB::connection( $conn )->table( 'cms_version_element' )->delete();
        DB::connection( $conn )->table( 'cms_version_file' )->delete();
        DB::connection( $conn )->table( 'cms_versions' )->where( 'tenant_id', $tenant )->delete();
        DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $tenant )->delete();
        DB::connection( $conn )->table( 'cms_elements' )->where( 'tenant_id', $tenant )->delete();
        DB::connection( $conn )->table( 'cms_files' )->where( 'tenant_id', $tenant )->delete();
    }


    /**
     * @return array{0: File, 1: string, 2: string}
     */
    private function privateBackup(): array
    {
        $file = File::where( 'tenant_id', $this->tenant )->firstOrFail();
        $path = 'cms/test/' . strtolower( (string) $file->id ) . '/private.txt';
        $file->forceFill( [
            'disk' => 'private',
            'path' => $path,
            'previews' => (object) [],
        ] )->saveQuietly();
        $file->versions->each( function( $version ) use ( $path ) {
            $data = $version->data;
            $data->path = $path;
            $data->previews = (object) [];
            $version->forceFill( ['data' => $data] )->saveQuietly();
        } );
        Storage::disk( 'backup-private' )->put( $path, 'private media' );

        $this->artisan( 'cms:backup', ['--tenant' => $this->tenant, '--disk' => 'backup'] )
            ->assertSuccessful();

        $prefix = 'pagible-' . $this->tenant . '-';
        $backup = collect( Storage::disk( 'backup' )->files() )
            ->first( fn( $entry ) => str_starts_with( $entry, $prefix ) );
        $this->assertNotNull( $backup );

        return [$file, $path, $backup];
    }


    private function resign( \ZipArchive $zip ): void
    {
        $manifest = json_decode( (string) $zip->getFromName( 'manifest.json' ), true );
        $checksums = [];

        for( $i = 0; $i < $zip->numFiles; $i++ )
        {
            $name = $zip->getNameIndex( $i );

            if( !$name || $name === 'manifest.json' || str_ends_with( $name, '/' ) ) {
                continue;
            }

            $content = $zip->getFromIndex( $i );
            $this->assertNotFalse( $content );
            $checksums[$name] = hash( 'sha256', $content );
        }

        ksort( $checksums );
        $manifest['checksums'] = $checksums;
        unset( $manifest['signature'] );
        $manifest['signature'] = hash_hmac( 'sha256', json_encode(
            $manifest,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ), (string) config( 'app.key' ) );

        $this->assertTrue( $zip->addFromString( 'manifest.json', json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ) . "\n" ) );
    }
}
