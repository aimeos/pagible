<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Files;
use Aimeos\Cms\Models\File;
use Illuminate\Support\Facades\Cache;


class FilesTest extends CoreTestAbstract
{
    public function testNormalizePathCanonicalizesStorageAliases()
    {
        $id = ( new \Aimeos\Cms\Models\File() )->newUniqueId();
        $path = 'cms/test/' . $id . '/image.jpg';

        $this->assertSame( $path, Files::normalizePath( 'cms//test/./' . $id . '/image.jpg', 'test' ) );
        $this->assertNull( Files::normalizePath( 'cms/test/' . $id . '/../other/image.jpg', 'test' ) );
        $this->assertNull( Files::normalizePath( 'cms/other/' . $id . '/image.jpg', 'test' ) );
        $this->assertNull( Files::normalizePath( 'cms/test/image.jpg', 'test' ) );
        $this->assertNull( Files::normalizePath( 'cms/test/' . $id, 'test' ) );
        $this->assertSame(
            'cms/www.example.com/' . $id . '/image.jpg',
            Files::normalizePath( 'cms/www.example.com/' . $id . '/image.jpg', 'www.example.com' ),
        );
        $this->assertNull( Files::normalizePath( 'cms/www..example.com/' . $id . '/image.jpg', 'www..example.com' ) );
        $this->assertSame(
            'cms/' . $id . '/image.jpg',
            Files::normalizePath( 'cms/' . $id . '/image.jpg', '' ),
        );
    }

    public function testFileOwnerRecognizesOnlyItsUuidDirectory(): void
    {
        $id = ( new File() )->newUniqueId();
        $upper = '019F8ABC-DEF0-7ABC-8ABC-ABCDEF123456';
        $lower = '019f8abc-def0-7abc-8abc-abcdef123456';

        $this->assertSame( $id, Files::owner( 'test', 'cms/test/' . $id . '/file.pdf' ) );
        $this->assertSame( $id, Files::owner( '', 'cms/' . $id . '/file.pdf' ) );
        $this->assertSame( $upper, Files::owner( 'test', 'cms/test/' . $upper . '/file.pdf' ) );
        $this->assertTrue( Files::owns( 'test', $lower, 'cms/test/' . $upper . '/file.pdf' ) );
        $this->assertNull( Files::owner( 'test', 'cms/test/file.pdf' ) );
        $this->assertNull( Files::owner( 'test', 'cms/test/' . $id ) );
        $this->assertNull( Files::owner( 'test', 'https://example.com/file.pdf' ) );
        $this->assertNull( Files::owner( '', 'cms/other/' . $id . '/file.pdf' ) );
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
                Files::checkPath( $path );
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

        $this->assertNull( Files::checkPath( null ) );
        $this->assertEquals( $path, Files::checkPath( $path ) );
        $this->assertEquals( $path, Files::checkPath( 'cms//test/./' . $id . '/image_ab12.jpg' ) );
        // External URLs bypass the tenant prefix and never touch the tenant disk.
        $this->assertEquals( 'https://example.com/a/b.jpg', Files::checkPath( 'https://example.com/a/b.jpg' ) );
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

        $this->assertSame( 'result', Files::storageLock( 'test', fn() => 'result', 7 ) );
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

        $result = Files::storageLock( 'test', function() use ( &$inside, &$released ) {
            $inside = true;
            Files::deferStorage( 'test', function() use ( &$inside, &$released ) {
                $this->assertFalse( $inside );
                $released = true;
            } );
            return 'result';
        } );

        $this->assertSame( 'result', $result );
        $this->assertTrue( $released );
    }
}
