<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class UploadsTest extends CoreTestAbstract
{
    public function testExtensionAllowed()
    {
        $this->assertSame( 'jpg', Uploads::extension( 'jpg' ) );
        $this->assertSame( 'pdf', Uploads::extension( 'PDF' ) );
        $this->assertSame( 'tar', Uploads::extension( '.tar' ) );
    }


    public function testExtensionEmpty()
    {
        $this->assertSame( 'bin', Uploads::extension( null ) );
        $this->assertSame( 'bin', Uploads::extension( '' ) );
        $this->assertSame( 'bin', Uploads::extension( '..' ) );
    }


    public function testExtensionNeutralized()
    {
        $this->assertSame( 'bin', Uploads::extension( 'php' ) );
        $this->assertSame( 'bin', Uploads::extension( 'PHP5' ) );
        $this->assertSame( 'bin', Uploads::extension( 'phtml' ) );
        $this->assertSame( 'bin', Uploads::extension( 'phar' ) );
        $this->assertSame( 'bin', Uploads::extension( 'asp' ) );
        $this->assertSame( 'bin', Uploads::extension( 'aspx' ) );
        $this->assertSame( 'bin', Uploads::extension( 'jsp' ) );
        $this->assertSame( 'bin', Uploads::extension( 'html' ) );
        $this->assertSame( 'bin', Uploads::extension( 'xhtml' ) );
        $this->assertSame( 'bin', Uploads::extension( 'htaccess' ) );
        $this->assertSame( 'bin', Uploads::extension( 'cgi' ) );
        $this->assertSame( 'bin', Uploads::extension( 'pl' ) );
    }


    public function testIsValidUploadAllowed()
    {
        $upload = UploadedFile::fake()->image( 'test.jpg', 100, 100 );

        $this->assertTrue( Uploads::isValidUpload( $upload ) );
    }


    public function testIsValidUploadSizeExceeded()
    {
        config()->set( 'cms.upload.filesize', 0.001 ); // ~1 KB

        $upload = UploadedFile::fake()->create( 'test.pdf', 100, 'application/pdf' );

        $this->assertFalse( Uploads::isValidUpload( $upload ) );
    }


    public function testIsValidMimetypeAllowed()
    {
        $this->assertTrue( Uploads::isValidMimetype( 'image/png' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'audio/mpeg' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'video/mp4' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'text/plain' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'application/pdf' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'application/zip' ) );
        $this->assertTrue( Uploads::isValidMimetype( 'application/gzip' ) );
    }


    public function testIsValidMimetypeRejected()
    {
        $this->assertFalse( Uploads::isValidMimetype( 'application/x-httpd-php' ) );
        $this->assertFalse( Uploads::isValidMimetype( 'application/x-executable' ) );
        $this->assertFalse( Uploads::isValidMimetype( 'application/x-sharedlib' ) );
    }


    public function testMimetypeRejectsPathTraversal()
    {
        // A crafted path must be rejected before any disk read so its mime type
        // (and existence) can't be probed outside the intended directory.
        foreach( ['cms/test/../other/secret.jpg', 'cms/test/..\\x.jpg', "cms/test/x.jpg\0"] as $path )
        {
            try {
                Uploads::mimetype( $path );
                $this->fail( sprintf( 'Expected exception for path "%s"', $path ) );
            } catch( \Aimeos\Cms\Exception $e ) {
                $this->assertEquals( 'Invalid file path', $e->getMessage() );
            }
        }
    }


    public function testMimetypeReadsValidPath()
    {
        Storage::fake( 'public' );
        $id = ( new \Aimeos\Cms\Models\File() )->newUniqueId();
        $path = 'cms/test/' . $id . '/hello.txt';
        Storage::disk( 'public' )->put( $path, 'hello world, plain text content' );

        $this->assertEquals( 'text/plain', Uploads::mimetype( $path ) );
    }


    public function testMimetypeRejectsAnotherTenantBeforeReading()
    {
        Storage::fake( 'public' );
        Storage::disk( 'public' )->put( 'cms/other/secret.txt', 'secret' );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Invalid file path' );

        Uploads::mimetype( 'cms/other/secret.txt' );
    }
}
