<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;


class ModelTest extends CoreTestAbstract
{
    public function testPageChangedDefaultsToNull(): void
    {
        $this->assertNull( ( new Page() )->changed );
    }


    public function testPageStoresChangedInformation(): void
    {
        $info = [
            'editor' => 'test@example.com',
            'data' => ['title' => ['previous' => 'a', 'current' => 'b']],
        ];
        $page = new Page();

        $page->setChanged( $info );

        $this->assertEquals( $info, $page->changed );
    }


    public function testPageToString(): void
    {
        $page = new Page( ['name' => 'Home', 'title' => 'Home | Laravel CMS'] );
        $page->content = [(object) ['type' => 'heading', 'data' => (object) ['title' => 'Welcome to Laravel CMS']]];
        $page->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Home', (string) $page );
        $this->assertStringContainsString( 'Home | Laravel CMS', (string) $page );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $page );
    }


    public function testPageToStringEmpty(): void
    {
        $page = new Page( ['name' => 'Disabled'] );
        $page->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Disabled', (string) $page );
        $this->assertStringNotContainsString( 'Welcome', (string) $page );
    }


    public function testPageAcceptsCanonicalStructuredAttributes(): void
    {
        $page = new Page();
        $page->meta = ['meta-tags' => [
            'type' => 'meta-tags',
            'data' => ['description' => 'Test'], 'files' => [],
        ]];
        $page->config = ['logo' => [
            'type' => 'logo',
            'data' => ['file' => ['type' => 'file', 'id' => 'file-1']], 'files' => ['file-1'],
        ]];

        $this->assertEquals( 'Test', $page->meta->{'meta-tags'}->data->description );
        $this->assertObjectNotHasProperty( 'id', $page->meta->{'meta-tags'} );
        $this->assertEquals( ['file-1'], $page->config->logo->files );
    }


    public function testJsonKeyOrderDoesNotMarkModelDirty(): void
    {
        $page = new Page();
        $page->setRawAttributes( [
            'meta' => '{"meta-tags":{"data":{"keywords":"cms","description":"Test"},"type":"meta-tags","files":[]}}',
            'content' => '[{"type":"heading"},{"type":"text"}]',
        ], true );

        $page->meta = ['meta-tags' => [
            'type' => 'meta-tags',
            'data' => ['description' => 'Test', 'keywords' => 'cms'],
            'files' => [],
        ]];
        $page->content = [['type' => 'heading'], ['type' => 'text']];

        $this->assertFalse( $page->isDirty( 'meta' ) );
        $this->assertFalse( $page->isDirty( 'content' ) );

        $page->content = [['type' => 'text'], ['type' => 'heading']];

        $this->assertTrue( $page->isDirty( 'content' ) );
    }


    public function testPageRejectsLegacyStructuredAttributes(): void
    {
        $this->expectException( \Aimeos\Cms\Exception::class );

        $page = new Page();
        $page->meta = [['type' => 'meta-tags', 'data' => ['description' => 'Test']]];
    }


    public function testPageHasDescendantCount(): void
    {
        $page = new Page();

        // leaf: rgt = lft + 1, no descendants
        $page->setAttribute( NestedSet::LFT, 2 );
        $page->setAttribute( NestedSet::RGT, 3 );
        $this->assertSame( 0, $page->has );

        // subtree spanning 3 descendants: (9 - 2 - 1) / 2
        $page->setAttribute( NestedSet::RGT, 9 );
        $this->assertSame( 3, $page->has );
    }


    public function testPageKeepsRelatedIdAsIs(): void
    {
        $page = new Page();
        $page->related_id = ' ID-with-original-case ';

        $this->assertSame( ' ID-with-original-case ', $page->related_id );
    }


    public function testSqlServerUppercasesUuids(): void
    {
        $model = new class extends Page {
            public function getConnection()
            {
                return new class {
                    public function getDriverName() : string
                    {
                        return 'sqlsrv';
                    }
                };
            }
        };
        $version = new class extends Version {
            public function getConnection()
            {
                return new class {
                    public function getDriverName() : string
                    {
                        return 'sqlsrv';
                    }
                };
            }
        };
        $id = '019f8abc-def0-7abc-8abc-abcdef123456';
        $modelId = $model->newUniqueId();
        $versionId = $version->newUniqueId();

        $this->assertSame( strtoupper( $id ), $model->getIdAttribute( $id ) );
        $this->assertSame( strtoupper( $id ), $version->getIdAttribute( $id ) );
        $this->assertSame( strtoupper( $modelId ), $modelId );
        $this->assertSame( strtoupper( $versionId ), $versionId );
    }


    public function testUnsavedModelsHaveNullIds(): void
    {
        $this->assertNull( ( new Page() )->id );
    }


    public function testDefaultTenantUsesNormalizedStoragePath(): void
    {
        app()->instance( Tenancy::class, new Tenancy( '' ) );
        config( ['cms.disk' => 'default-path'] );
        Storage::fake( 'default-path' );

        $file = ( new File() )->addFile(
            UploadedFile::fake()->create( 'document.txt', 1, 'text/plain' ),
        );

        $this->assertStringStartsWith( 'cms/', $file->path );
        $this->assertStringNotContainsString( 'cms//', $file->path );
        Storage::disk( 'default-path' )->assertExists( $file->path );
    }


    public function testRemotePreviewHonorsUploadLimit(): void
    {
        config( ['cms.allow-internal' => true, 'cms.upload.filesize' => 0.001] );
        Http::fake( ['*' => Http::response( str_repeat( 'x', 4097 ), 200 )] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Remote file exceeds the maximum size of 0.001 MB' );

        ( new File( ['name' => 'remote.png'] ) )->addPreviews( 'http://127.0.0.1/remote.png' );
    }


    public function testPreviewRejectsConfiguredPixelLimit(): void
    {
        $header = pack( 'NNCCCCC', 5000, 4000, 8, 2, 0, 0, 0 );
        $chunk = 'IHDR' . $header;
        $png = "\x89PNG\r\n\x1a\n" . pack( 'N', strlen( $header ) ) . $chunk . pack( 'N', crc32( $chunk ) );
        $upload = UploadedFile::fake()->createWithContent( 'large.png', $png );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Image exceeds the maximum size of 16777216 pixels' );

        ( new File( ['name' => 'large.png'] ) )->addPreviews( $upload );
    }


    public function testRemotePreviewRejectsConfiguredPixelLimit(): void
    {
        config( ['cms.allow-internal' => true, 'cms.upload.maxpixels' => 10_000_000] );
        $header = pack( 'NNCCCCC', 5000, 4000, 8, 2, 0, 0, 0 );
        $chunk = 'IHDR' . $header;
        $png = "\x89PNG\r\n\x1a\n" . pack( 'N', strlen( $header ) ) . $chunk . pack( 'N', crc32( $chunk ) );
        Http::fake( ['*' => Http::response( $png, 200, ['Content-Type' => 'image/png'] )] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Image exceeds the maximum size of 10000000 pixels' );

        ( new File( ['name' => 'remote.png'] ) )->addPreviews( 'http://127.0.0.1/remote.png' );
    }


    public function testRemoteSvgzHonorsDecompressedUploadLimit(): void
    {
        config( ['cms.allow-internal' => true, 'cms.upload.filesize' => 0.001] );
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><!--'
            . str_repeat( 'x', 4096 ) . '--><rect width="1" height="1"/></svg>';
        Http::fake( ['*' => Http::response( gzencode( $svg ), 200 )] );

        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Decompressed SVG exceeds the maximum upload size' );

        ( new File( ['name' => 'remote.svgz'] ) )->addPreviews( 'http://127.0.0.1/remote.svgz' );
    }


    public function testElementToString(): void
    {
        $element = new Element();
        $element->type = 'heading';
        $element->name = 'Test';
        $element->data = ['type' => 'heading', 'data' => ['title' => 'Test heading']];

        $this->assertStringContainsString( 'Test', (string) $element );
        $this->assertStringContainsString( 'Test heading', (string) $element );
    }


    public function testElementToStringEmpty(): void
    {
        $element = new Element();
        $element->type = 'unknown';
        $element->data = ['type' => 'unknown', 'data' => ['text' => 'test']];

        $this->assertEmpty( (string) $element );
    }


    public function testFileToString(): void
    {
        $file = new File( ['name' => 'Test image', 'description' => (object) ['en' => 'Test file description']] );

        $this->assertStringContainsString( 'Test image', (string) $file );
        $this->assertStringContainsString( "en:\nTest file description", (string) $file );
    }


    public function testFileToStringEmpty(): void
    {
        $file = new File();
        $this->assertEmpty( (string) $file );
    }


    public function testVersionToStringPage(): void
    {
        $version = new Version( [
            'data' => (object) ['name' => 'Home', 'type' => 'heading', 'data' => (object) ['title' => 'Welcome to Laravel CMS']],
            'aux' => (object) ['content' => []],
        ] );
        $version->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Home', (string) $version );
        $this->assertStringContainsString( 'Welcome to Laravel CMS', (string) $version );
    }


    public function testVersionToStringElement(): void
    {
        $version = new Version( ['data' => (object) []] );

        $this->assertNotNull( $version );
    }


    public function testVersionAcceptsCanonicalStructuredAux(): void
    {
        $version = new Version( [
            'data' => (object) ['type' => 'page'],
            'aux' => [
                'meta' => ['meta-tags' => [
                    'type' => 'meta-tags',
                    'data' => ['description' => 'Test'], 'files' => [],
                ]],
                'config' => ['logo' => [
                    'type' => 'logo',
                    'data' => ['file' => ['type' => 'file', 'id' => 'file-1']], 'files' => ['file-1'],
                ]],
            ],
        ] );

        $this->assertEquals( 'Test', $version->aux->meta->{'meta-tags'}->data->description );
        $this->assertObjectNotHasProperty( 'id', $version->aux->meta->{'meta-tags'} );
        $this->assertEquals( ['file-1'], $version->aux->config->logo->files );
    }


    public function testVersionToStringFile(): void
    {
        $version = new Version( [
            'data' => (object) ['name' => 'Test image'],
            'aux' => (object) ['description' => (object) ['en' => 'Test file description']],
        ] );
        $version->setRelation( 'elements', collect( [] ) );

        $this->assertStringContainsString( 'Test image', (string) $version );
        $this->assertStringContainsString( "en:\nTest file description", (string) $version );
    }
}
