<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Exception;
use Aimeos\Cms\FileStorage;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Facades\Cache;


class FilesTest extends CoreTestAbstract
{
    public function testNormalizePathCanonicalizesStorageAliases()
    {
        $id = ( new \Aimeos\Cms\Models\File() )->newUniqueId();
        $path = 'cms/test/' . $id . '/image.jpg';

        $this->assertSame( $path, FileStorage::normalizePath( 'cms//test/./' . $id . '/image.jpg', 'test' ) );
        $this->assertNull( FileStorage::normalizePath( 'cms/test/' . $id . '/../other/image.jpg', 'test' ) );
        $this->assertNull( FileStorage::normalizePath( 'cms/other/' . $id . '/image.jpg', 'test' ) );
        $this->assertNull( FileStorage::normalizePath( 'cms/test/image.jpg', 'test' ) );
        $this->assertNull( FileStorage::normalizePath( 'cms/test/' . $id, 'test' ) );
        $this->assertSame(
            'cms/www.example.com/' . $id . '/image.jpg',
            FileStorage::normalizePath( 'cms/www.example.com/' . $id . '/image.jpg', 'www.example.com' ),
        );
        $this->assertNull( FileStorage::normalizePath( 'cms/www..example.com/' . $id . '/image.jpg', 'www..example.com' ) );
        $this->assertSame(
            'cms/' . $id . '/image.jpg',
            FileStorage::normalizePath( 'cms/' . $id . '/image.jpg', '' ),
        );
    }

    public function testFileOwnerRecognizesOnlyItsUuidDirectory(): void
    {
        $id = ( new File() )->newUniqueId();
        $upper = '019F8ABC-DEF0-7ABC-8ABC-ABCDEF123456';
        $lower = '019f8abc-def0-7abc-8abc-abcdef123456';

        $this->assertSame( $id, FileStorage::owner( 'test', 'cms/test/' . $id . '/file.pdf' ) );
        $this->assertSame( $id, FileStorage::owner( '', 'cms/' . $id . '/file.pdf' ) );
        $this->assertSame( $upper, FileStorage::owner( 'test', 'cms/test/' . $upper . '/file.pdf' ) );
        $this->assertTrue( FileStorage::owns( 'test', $lower, 'cms/test/' . $upper . '/file.pdf' ) );
        $this->assertNull( FileStorage::owner( 'test', 'cms/test/file.pdf' ) );
        $this->assertNull( FileStorage::owner( 'test', 'cms/test/' . $id ) );
        $this->assertNull( FileStorage::owner( 'test', 'https://example.com/file.pdf' ) );
        $this->assertNull( FileStorage::owner( '', 'cms/other/' . $id . '/file.pdf' ) );
    }


    public function testCheckPathRejectsTraversal()
    {
        // Tenancy::value() is "test" here, so the prefix is "cms/test/". Each of these
        // begins with the prefix yet resolves into another tenant's directory.
        $paths = [
            'cms/test/../other/secret.jpg',
            'cms/test/..\\other\\secret.jpg',
            'cms/test/sub/../../other/secret.jpg',
            'cms/other/secret.jpg',
            "cms/test/secret.jpg\0.png",
        ];

        foreach( $paths as $path )
        {
            try {
                FileStorage::checkPath( $path );
                $this->fail( sprintf( 'Expected exception for path "%s"', $path ) );
            } catch( Exception $e ) {
                $this->assertStringContainsString( 'Invalid file path', $e->getMessage() );
            }
        }
    }


    public function testCheckPathAllowsValidPaths()
    {
        $id = ( new File() )->newUniqueId();
        $path = 'cms/test/' . $id . '/image_ab12.jpg';

        $this->assertNull( FileStorage::checkPath( null ) );
        $this->assertEquals( $path, FileStorage::checkPath( $path ) );
        $this->assertEquals( $path, FileStorage::checkPath( 'cms//test/./' . $id . '/image_ab12.jpg' ) );
        // External URLs bypass the tenant prefix and never touch the tenant disk.
        $this->assertEquals( 'https://example.com/a/b.jpg', FileStorage::checkPath( 'https://example.com/a/b.jpg' ) );
    }


    public function testStorageLockUsesTenantScopedKey(): void
    {
        $key = 'cms_storage_' . hash( 'sha256', 'test' );
        $lock = \Mockery::mock( \Illuminate\Contracts\Cache\Lock::class );
        $lock->shouldReceive( 'block' )
            ->once()
            ->with( 7, \Mockery::type( \Closure::class ) )
            ->andReturnUsing( fn( int $seconds, \Closure $callback ) => $callback() );

        Cache::shouldReceive( 'lock' )
            ->once()
            ->with( $key, 86400 )
            ->andReturn( $lock );

        $this->assertSame( 'result', FileStorage::storageLock( 'test', fn() => 'result', 7 ) );
    }


    public function testStorageLockRunsDeferredWorkAfterRelease(): void
    {
        $inside = false;
        $released = false;
        $lock = \Mockery::mock( \Illuminate\Contracts\Cache\Lock::class );
        $lock->shouldReceive( 'block' )
            ->once()
            ->andReturnUsing( function( int $seconds, \Closure $callback ) use ( &$inside, &$released ) {
                $result = $callback();
                $this->assertFalse( $released );
                $inside = false;
                return $result;
            } );

        Cache::shouldReceive( 'lock' )->once()->andReturn( $lock );

        $result = FileStorage::storageLock( 'test', function() use ( &$inside, &$released ) {
            $inside = true;
            FileStorage::deferStorage( 'test', function() use ( &$inside, &$released ) {
                $this->assertFalse( $inside );
                $released = true;
            } );
            return 'result';
        } );

        $this->assertSame( 'result', $result );
        $this->assertTrue( $released );
    }
}
