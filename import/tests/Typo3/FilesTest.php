<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Typo3;

use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Models\File;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;


/**
 * Tests the import of the TYPO3 files and file references.
 */
class FilesTest extends TestAbstract
{
    public function testFilesCropImagesAndDescribeFilesPerLanguage(): void
    {
        $calls = [];
        $files = new Typo3\Files( fn( array $identifiers ) => Collection::make(), 'https://t3.org', function( string $mime, string $name, string $path, bool $new, array $options ) use ( &$calls ) {
            $calls[] = [$name, $path, $options];
            return 'file-' . count( $calls );
        }, fn( string $path ) => 'url:' . $path, null, [], [], null, fn( int $uid ) => [0 => 'en', 1 => 'de'][$uid] ?? '' );

        $png = (object) ['uid' => 1, 'identifier' => '/a.png', 'name' => 'a.png', 'extension' => 'png', 'mime_type' => 'image/png'];
        $svg = (object) ['uid' => 2, 'identifier' => '/b.svg', 'name' => 'b.svg', 'extension' => 'svg', 'mime_type' => 'image/svg+xml'];
        $crop = json_encode( ['default' => ['cropArea' => ['x' => 0.1, 'y' => 0, 'width' => 0.5, 'height' => 1]]] );
        $full = json_encode( ['default' => ['cropArea' => ['x' => 0, 'y' => 0, 'width' => 1, 'height' => 1]]] );

        $this->assertSame( ['x' => 0.1, 'y' => 0.0, 'width' => 0.5, 'height' => 1.0], $files->crop( (object) ['crop' => $crop] ) );
        $this->assertNull( $files->crop( (object) ['crop' => $full] ) );
        $this->assertNull( $files->crop( (object) ['crop' => '{"default":{"cropArea":{"x":2,"y":0,"width":1,"height":1}}}'] ) );
        $this->assertNull( $files->crop( (object) ['crop' => ''] ) );

        $files->reference( (object) ['sys_file' => $png, 'crop' => $crop, 'alternative' => 'Bild', 'sys_language_uid' => 1] );
        $files->reference( (object) ['sys_file' => $png, 'crop' => $full, 'description' => '<p>Caption</p>'] );
        // vector images can't be cropped
        $files->reference( (object) ['sys_file' => $svg, 'crop' => $crop] );

        $this->assertSame( [
            ['a.png', 'https://t3.org/a.png#crop=0.1,0,0.5,1', ['description' => ['de' => 'Bild'], 'crop' => ['x' => 0.1, 'y' => 0.0, 'width' => 0.5, 'height' => 1.0]]],
            ['a.png', 'https://t3.org/a.png', ['description' => ['en' => 'Caption']]],
            ['b.svg', 'https://t3.org/b.svg', []],
        ], $calls );
        // cropped images are separate files
        $this->assertSame( ['https://t3.org/a.png#crop=0.1,0,0.5,1'], $files->paths( [(object) ['sys_file' => $png, 'crop' => $crop]] ) );
    }


    public function testFilesResolvesReferencesAndUrls(): void
    {
        $calls = [];
        $calls = $lookups = [];
        $logo = (object) ['uid' => 1, 'identifier' => '/user_upload/Ä b.svg', 'name' => 'Logo.svg', 'extension' => 'svg', 'mime_type' => 'text/plain'];
        $pdf = (object) ['uid' => 2, 'identifier' => 'docs/a.pdf', 'name' => 'a.pdf', 'extension' => 'pdf', 'mime_type' => 'application/pdf'];
        $files = new Typo3\Files( function( array $identifiers ) use ( $logo, &$lookups ) {
            $lookups[] = $identifiers;
            return $this->sysFiles( ['/user_upload/Ä b.svg' => $logo] )->only( $identifiers );
        }, 'https://t3.org', function( string $mime, string $name, string $path ) use ( &$calls ) {
            $calls[] = [$mime, $name, $path];
            return 'file-' . count( $calls );
        }, fn( string $path ) => 'url:' . $path );

        $this->assertSame( ['id' => 'file-1', 'url' => 'url:https://t3.org/user_upload/%C3%84%20b.svg'], $files->html( 'https://old.org/fileadmin/user_upload/%C3%84%20b.svg' ) );
        $this->assertSame( 'file-2', $files->reference( (object) ['uid_local' => 2, 'title' => 'Title', 'alternative' => 'Alt', 'sys_file' => $pdf] ) );
        $this->assertSame( 'file-3', $files->reference( (object) ['uid_local' => 2, 'title' => '', 'alternative' => 'Alt', 'sys_file' => $pdf] ) );
        $this->assertNull( $files->reference( (object) ['uid_local' => 3, 'sys_file' => null] ) );

        // the files of the identifiers are only looked up once
        $this->assertSame( ['id' => 'file-4', 'url' => 'url:https://t3.org/user_upload/%C3%84%20b.svg'], $files->html( 'https://old.org/fileadmin/user_upload/%C3%84%20b.svg' ) );
        $this->assertSame( [['/user_upload/%C3%84%20b.svg', '/user_upload/Ä b.svg']], $lookups );
        $this->assertNull( $files->html( 'javascript:fileadmin/a.pdf' ) );
        // files in the legacy uploads folder have no sys_file record but are imported too
        $this->assertSame( ['id' => 'file-5', 'url' => 'url:https://t3.org/uploads/a.pdf'], $files->html( 'https://old.org/uploads/a.pdf' ) );

        $this->assertSame( [
            ['image/svg+xml', 'Logo.svg', 'https://t3.org/user_upload/%C3%84%20b.svg'],
            ['application/pdf', 'Title', 'https://t3.org/docs/a.pdf'],
            // the alternative text is the description of the file, not its name
            ['application/pdf', 'a.pdf', 'https://t3.org/docs/a.pdf'],
            ['image/svg+xml', 'Logo.svg', 'https://t3.org/user_upload/%C3%84%20b.svg'],
            ['application/pdf', 'a.pdf', 'https://t3.org/uploads/a.pdf'],
        ], $calls );
    }


    public function testFilesLimitsCachedIdentifiers(): void
    {
        $lookups = [];
        $files = new class( function( array $identifiers ) use ( &$lookups ) {
            $lookups[] = $identifiers;
            return Collection::make();
        }, 'https://t3.org', fn() => 'id', fn( string $path ) => 'url:' . $path ) extends Typo3\Files {
            protected const CACHE_SIZE = 2;
        };

        foreach( ['a', 'b', 'c', 'c', 'b', 'a', 'a%20b', 'b'] as $name ) {
            $files->html( "/fileadmin/{$name}.png" );
        }

        // only the last identifiers are kept but the ones just looked up are always kept
        $this->assertSame( [['/a.png'], ['/b.png'], ['/c.png'], ['/b.png'], ['/a.png'], ['/a%20b.png', '/a b.png'], ['/b.png']], $lookups );
    }


    public function testFilesUsesKnownFilesAndReturnsPaths(): void
    {
        $lookups = $calls = [];
        $known = ['https://t3.org/a.png' => 'known-a', 'https://t3.org/b.png' => 'known-b'];
        $files = new Typo3\Files( function( array $identifiers ) use ( &$lookups ) {
            $lookups[] = $identifiers;
            return Collection::make();
        }, 'https://t3.org', function( string $mime, string $name, string $path ) use ( &$calls ) {
            $calls[] = $path;
            return 'new';
        }, fn( string $path ) => $path === 'https://t3.org/b.png' ? null : 'url:' . $path, fn( string $path ) => $known[$path] ?? null );

        // known files are neither looked up nor created again if their URL is available
        $this->assertSame( ['id' => 'known-a', 'url' => 'url:https://t3.org/a.png'], $files->html( '/fileadmin/a.png' ) );
        $this->assertNull( $files->html( '/fileadmin/b.png' ) );
        $this->assertSame( ['id' => 'new', 'url' => 'url:https://t3.org/c.png'], $files->html( '/fileadmin/c.png' ) );
        $this->assertSame( [['/b.png'], ['/c.png']], $lookups );
        $this->assertSame( ['https://t3.org/b.png', 'https://t3.org/c.png'], $calls );

        $refs = [
            (object) ['sys_file' => (object) ['identifier' => '/user_upload/a.png']],
            (object) ['sys_file' => null],
            (object) ['sys_file' => (object) ['identifier' => '/user_upload/a.png']],
        ];
        $texts = [
            '<img src="/fileadmin/x.png" srcset="/fileadmin/y.png 2x, /fileadmin/z.png 3x"><a href=https://old.org/fileadmin/doc.pdf?v=1>Doc</a>',
            null,
            '<a href="/other/x.png">Other</a><a href="javascript:fileadmin/a.pdf">Script</a>',
            '<img srcset="/fileadmin/v.png,/fileadmin/w.png 2x">',
        ];

        $this->assertSame( [
            'https://t3.org/user_upload/a.png', 'https://t3.org/x.png', 'https://t3.org/y.png', 'https://t3.org/z.png', 'https://t3.org/doc.pdf',
            'https://t3.org/v.png', 'https://t3.org/w.png',
        ], $files->paths( $refs, $texts ) );

        // without base URL, the URLs of other hosts are used without query string
        $files = new Typo3\Files( fn() => Collection::make(), '', fn() => '', fn() => null );
        $this->assertSame( ['user_upload/a.png', 'x.png', 'y.png', 'z.png', 'https://old.org/fileadmin/doc.pdf'], $files->paths( $refs, [$texts[0], '<img src="fileadmin/x.png">'] ) );
    }


    public function testFilesStoredReturnsPortableUrls(): void
    {
        $name = config( 'cms.disks.public.name' );
        $file = ( new File )->forceFill( ['disk' => 'public', 'path' => 'cms/a.pdf'] );

        config( ['filesystems.disks.cms-test' => ['driver' => 'local', 'root' => sys_get_temp_dir(), 'url' => 'http://localhost:8000/storage']] );
        config( ['filesystems.disks.cms-remote' => ['driver' => 'cms-remote', 'url' => 'https://cdn.example.com']] );
        Storage::extend( 'cms-remote', fn( $app, array $config ) => new FilesystemAdapter(
            new Filesystem( $adapter = new LocalFilesystemAdapter( sys_get_temp_dir() ) ), $adapter, $config
        ) );

        try
        {
            config( ['cms.disks.public.name' => 'cms-test'] );
            $this->assertSame( '/storage/cms/a.pdf', Typo3\Files::stored( $file ) );

            config( ['cms.disks.public.name' => 'cms-remote'] );
            $this->assertSame( 'https://cdn.example.com/cms/a.pdf', Typo3\Files::stored( $file ) );
        }
        finally
        {
            config( ['cms.disks.public.name' => $name, 'filesystems.disks.cms-test' => null, 'filesystems.disks.cms-remote' => null] );
        }
    }
}
