<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Database\Seeders\TestSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Aimeos\Cms\Events\BackupCreated;
use Aimeos\Cms\Events\RestoreCompleted;


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
    }


    protected function tearDown(): void
    {
        Storage::disk( 'backup' )->deleteDirectory( '' );
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


    public function testBackupCrossTenant(): void
    {
        $conn = config( 'cms.db', 'sqlite' );
        $t = $this->tenant;

        $pageCount = DB::connection( $conn )->table( 'cms_pages' )->where( 'tenant_id', $t )->count();
        $backupFile = $this->backup( $t );

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
}
