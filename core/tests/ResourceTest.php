<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Jobs\IndexModels;
use Laravel\Scout\Jobs\RemoveFromSearch;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Jobs\DeleteFilePaths;
use Aimeos\Cms\Jobs\PruneVersions;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Scout;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Base;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;


class ResourceTest extends CoreTestAbstract
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
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ]);
    }


    public function testSavePageKeepsFilesWhenSectionOmitted()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]] );

        $this->assertContains( $file->id, $page->latest->files()->pluck( 'cms_files.id' )->all() );

        // change only the title; content/files are not sent
        $page = Resource::savePage( $page->id, ['title' => 'Renamed'], $this->user );

        $this->assertContains( $file->id, $page->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testSavePageKeepsExistingFilesWithoutFileView()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page editor',
            'cmsperms' => ['page:view', 'page:save'],
        ] );

        $page = Resource::savePage( $page->id, ['title' => 'Renamed'], $user );

        $this->assertContains( $file->id, $page->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testSavePageRejectsFileReferencesWithoutFileView()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'heading', 'data' => ['title' => 'No image']]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page editor',
            'cmsperms' => ['page:view', 'page:save'],
        ] );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        Resource::savePage( $page->id, [
            'content' => [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]],
        ], $user );
    }


    public function testSavePageMergesFromStaleVersion()
    {
        $page = $this->page( [] );
        $base = $page->latest_id;

        Resource::savePage( $page->id, ['title' => 'Changed elsewhere'], $this->user );
        $page = Resource::savePage( $page->id, ['name' => 'My change'], $this->user, $base );

        $this->assertSame( 'Changed elsewhere', $page->latest->data->title );
        $this->assertSame( 'My change', $page->latest->data->name );
    }


    public function testSavePageDetachesRemovedReference()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]] );

        $page = Resource::savePage( $page->id, [
            'content' => [['type' => 'heading', 'data' => ['title' => 'No image any more']]],
        ], $this->user );

        $this->assertNotContains( $file->id, $page->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testSavePageCollectsNestedAndMetaFiles()
    {
        $jpeg = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $tiff = File::where( 'mime', 'image/tiff' )->firstOrFail();

        $page = Resource::addPage( [
            'lang' => 'en', 'name' => 'Nested', 'title' => 'Nested', 'path' => 'nested-test',
            'content' => [['type' => 'hero', 'data' => ['background' => ['id' => $jpeg->id, 'type' => 'file']]]],
            'meta' => ['social-media' => [
                'type' => 'social-media',
                'data' => ['file' => ['id' => $tiff->id, 'type' => 'file']],
                'files' => [$tiff->id],
            ]],
        ], $this->user, parent: $this->root()->id );

        $ids = $page->latest->files()->pluck( 'cms_files.id' )->all();

        $this->assertContains( $jpeg->id, $ids );  // nested hero.background
        $this->assertContains( $tiff->id, $ids );  // meta social-media.file
        $this->assertEquals( 'social-media', $page->latest->aux->meta->{'social-media'}->type );
        $this->assertEquals( [$tiff->id], $page->latest->aux->meta->{'social-media'}->files );
        $this->assertObjectNotHasProperty( 'id', $page->latest->aux->meta->{'social-media'} );
        $this->assertObjectNotHasProperty( 'group', $page->latest->aux->meta->{'social-media'} );
    }


    public function testAddPageRejectsCompactMeta()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'expected type, data and files' );

        Resource::addPage( [
            'lang' => 'en', 'name' => 'Invalid', 'title' => 'Invalid', 'path' => 'invalid-meta',
            'meta' => ['meta-tags' => ['description' => 'Invalid']],
        ], $this->user, parent: $this->root()->id );
    }


    public function testSavePageAttachesReferencedElement()
    {
        $element = Element::where( 'type', 'footer' )->firstOrFail();
        $page = Resource::addPage( [
            'lang' => 'en', 'name' => 'Ref', 'title' => 'Ref', 'path' => 'ref-test',
            'content' => [['type' => 'reference', 'refid' => $element->id, 'group' => 'main']],
        ], $this->user, parent: $this->root()->id );

        $this->assertContains( $element->id, $page->latest->elements()->pluck( 'cms_elements.id' )->all() );
    }


    public function testAddPageRejectsElementReferencesWithoutElementView()
    {
        $element = Element::where( 'type', 'footer' )->firstOrFail();
        $user = new \App\Models\User( [
            'name' => 'Restricted page editor',
            'cmsperms' => ['page:add'],
        ] );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        Resource::addPage( [
            'lang' => 'en', 'name' => 'Ref', 'title' => 'Ref', 'path' => 'restricted-ref-test',
            'content' => [['type' => 'reference', 'refid' => $element->id, 'group' => 'main']],
        ], $user, parent: $this->root()->id );
    }


    public function testSavePageThrowsOnUnavailableFile()
    {
        $page = $this->page( [['type' => 'heading', 'data' => ['title' => 'Hi']]] );

        $this->expectException( Exception::class );

        Resource::savePage( $page->id, [
            'content' => [['type' => 'image', 'data' => ['file' => ['id' => \Illuminate\Support\Str::uuid7()->toString(), 'type' => 'file']]]],
        ], $this->user );
    }


    public function testAddPageThrowsOnUnavailableElement()
    {
        $this->expectException( Exception::class );

        Resource::addPage( [
            'lang' => 'en', 'name' => 'Bad', 'title' => 'Bad', 'path' => 'bad-test',
            'content' => [['type' => 'reference', 'refid' => \Illuminate\Support\Str::uuid7()->toString(), 'group' => 'main']],
        ], $this->user, parent: $this->root()->id );
    }


    public function testSaveElementKeepsFilesWhenDataOmitted()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Shared image',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );

        $this->assertContains( $file->id, $element->latest->files()->pluck( 'cms_files.id' )->all() );

        // change only the name; data/files are not sent
        $element = Resource::saveElement( $element->id, ['name' => 'Renamed'], $this->user );

        $this->assertContains( $file->id, $element->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testSaveElementKeepsExistingFilesWithoutFileView()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Shared image',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        $user = new \App\Models\User( [
            'name' => 'Restricted element editor',
            'cmsperms' => ['element:view', 'element:save'],
        ] );

        $element = Resource::saveElement( $element->id, ['name' => 'Renamed'], $user );

        $this->assertContains( $file->id, $element->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testSaveElementRejectsFileReferencesWithoutFileView()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Shared image',
            'data' => [],
        ], $this->user );
        $user = new \App\Models\User( [
            'name' => 'Restricted element editor',
            'cmsperms' => ['element:view', 'element:save'],
        ] );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        Resource::saveElement( $element->id, [
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $user );
    }


    public function testAddElementRejectsFileReferencesWithoutFileView()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $user = new \App\Models\User( [
            'name' => 'Restricted element editor',
            'cmsperms' => ['element:add'],
        ] );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Insufficient permissions' );

        Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Shared image',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $user );
    }


    public function testCheckPathRejectsTraversal()
    {
        // Tenancy::value() is "test" here, so the prefix is "cms/test/". Each of these
        // begins with the prefix yet resolves into another tenant's directory.
        $method = new \ReflectionMethod( Resource::class, 'checkPath' );

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
                $method->invoke( null, $path );
                $this->fail( sprintf( 'Expected exception for path "%s"', $path ) );
            } catch( Exception $e ) {
                $this->assertStringContainsString( 'Invalid file path', $e->getMessage() );
            }
        }
    }


    public function testCheckPathAllowsValidPaths()
    {
        $method = new \ReflectionMethod( Resource::class, 'checkPath' );

        $this->assertNull( $method->invoke( null, null ) );
        $this->assertEquals( 'cms/test/image_ab12.jpg', $method->invoke( null, 'cms/test/image_ab12.jpg' ) );
        $this->assertEquals( 'cms/test/image_ab12.jpg', $method->invoke( null, 'cms//test/./image_ab12.jpg' ) );
        // External URLs bypass the tenant prefix and never touch the tenant disk.
        $this->assertEquals( 'https://example.com/a/b.jpg', $method->invoke( null, 'https://example.com/a/b.jpg' ) );
    }


    public function testSaveFileRejectsCrossTenantPath()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectException( Exception::class );

        Resource::saveFile( $file->id, ['path' => 'cms/test/../other/secret.jpg'], $this->user );
    }


    public function testSaveFileRejectsCrossTenantPreview()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectException( Exception::class );

        Resource::saveFile( $file->id, ['previews' => ['cms/test/../other/secret.jpg']], $this->user );
    }


    public function testSaveFileCleansPreparedUploadAfterTransactionFailure()
    {
        config( ['cms.disk' => 'failed-save'] );
        Storage::fake( 'failed-save' );
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        Event::listen( 'eloquent.creating: ' . Version::class, function() {
            throw new \RuntimeException( 'Forced version failure' );
        } );

        try {
            Resource::saveFile( $file->id, ['name' => 'Failed'], $this->user, upload:
                UploadedFile::fake()->createWithContent( 'replacement.pdf', '%PDF-1.4 replacement' ) );
            $this->fail( 'Expected the version write to fail' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'Forced version failure', $e->getMessage() );
        }

        $this->assertSame( [], Storage::disk( 'failed-save' )->allFiles() );
    }


    public function testSaveFileLoadsMetadataOnce()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();

        $this->expectsDatabaseQueryCount( 5 );

        $file = Resource::saveFile( $file->id, ['name' => 'Metadata only'], $this->user );

        $this->assertSame( 'Metadata only', $file->latest?->data->name );
    }


    public function testSaveFileDoesNotStoreUploadForMissingModel()
    {
        config( ['cms.disk' => 'missing-save'] );
        Storage::fake( 'missing-save' );

        try {
            Resource::saveFile( \Illuminate\Support\Str::uuid7()->toString(), [], $this->user, upload:
                UploadedFile::fake()->createWithContent( 'replacement.pdf', '%PDF-1.4 replacement' ) );
            $this->fail( 'Expected the missing model to be rejected' );
        } catch( \Illuminate\Database\Eloquent\ModelNotFoundException ) {
            $this->assertSame( [], Storage::disk( 'missing-save' )->allFiles() );
        }
    }


    public function testFileVersionMovesTextToAuxOnCreate()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $description = ['en' => 'Description'];
        $transcription = ['en' => 'Transcription'];

        $version = $file->versions()->forceCreate( [
            'data' => compact( 'description', 'transcription' ),
            'aux' => ['keep' => 'value'],
            'editor' => 'tester',
        ] );

        $this->assertObjectNotHasProperty( 'description', $version->data );
        $this->assertObjectNotHasProperty( 'transcription', $version->data );
        $this->assertEquals( $description, (array) $version->aux->description );
        $this->assertEquals( $transcription, (array) $version->aux->transcription );
        $this->assertEquals( 'value', $version->aux->keep );
    }


    public function testSaveFileStoresTextInAuxAndPublishesIt()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $description = ['en' => 'Updated description'];
        $transcription = ['en' => 'Updated transcription'];

        $file->latest->aux = (array) $file->latest->aux + ['keep' => 'value'];
        $file->latest->saveQuietly();

        $file = Resource::saveFile( $file->id, compact( 'description', 'transcription' ), $this->user );

        $this->assertObjectNotHasProperty( 'description', $file->latest->data );
        $this->assertObjectNotHasProperty( 'transcription', $file->latest->data );
        $this->assertEquals( $description, (array) $file->latest->aux->description );
        $this->assertEquals( $transcription, (array) $file->latest->aux->transcription );
        $this->assertEquals( 'value', $file->latest->aux->keep );

        $file->publish( $file->latest );

        $this->assertEquals( $description, (array) $file->description );
        $this->assertEquals( $transcription, (array) $file->transcription );
    }


    public function testSaveFileReportsAuxConflict()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $baseId = $file->latest_id;

        Resource::saveFile( $file->id, ['description' => ['en' => 'Other editor']], $this->user, $baseId );
        $file = Resource::saveFile( $file->id, ['description' => ['en' => 'My edit']], $this->user, $baseId );

        $this->assertEquals( ['en' => 'My edit'], (array) $file->latest->aux->description );
        $this->assertEquals( ['en' => 'Other editor'], (array) $file->changed['aux']['description']['overwritten'] );
        $this->assertArrayNotHasKey( 'description', $file->changed['data'] ?? [] );
        $this->assertEquals( ['en' => 'My edit'], (array) $file->changed['latest']['aux']['description'] );
    }


    public function testBulkPageBestEffortSkipsMissing()
    {
        $page = $this->page( [['type' => 'heading', 'data' => ['title' => 'Hi']]] );

        // a non-existent id is silently skipped, the existing page is still saved
        $saved = Resource::bulkPage(
            [$page->id, \Illuminate\Support\Str::uuid7()->toString()],
            ['title' => 'Renamed'],
            $this->user
        );

        $this->assertCount( 1, $saved['ids'] );
        $this->assertEquals( $page->id, $saved['ids'][0] );
        $this->assertArrayHasKey( $page->id, $saved['latest'] );
        $this->assertEquals( 'Renamed', $saved['data']['title'] );
        // the missing id was attempted but not saved, so it is counted as failed
        $this->assertSame( 1, $saved['failed'] );
        $this->assertEquals( 'Renamed', ( (array) Page::findOrFail( $page->id )->latest->data )['title'] );
    }


    public function testBulkPagePreservesUnchangedReferences()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $good = $this->page( [] );
        $bad = $this->page( [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]] );
        $file->delete();

        $saved = Resource::bulkPage( [$good->id, $bad->id], ['title' => 'Renamed'], $this->user );

        $this->assertSame( [$good->id, $bad->id], $saved['ids'] );
        $this->assertSame( 0, $saved['failed'] );
        $this->assertSame( 'Renamed', Page::findOrFail( $good->id )->latest->data->title );
        $bad = Page::findOrFail( $bad->id );
        $this->assertSame( 'Renamed', $bad->latest->data->title );
        $this->assertTrue( DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_version_file' )
            ->where( 'version_id', $bad->latest_id )->where( 'file_id', $file->id )->exists() );
    }


    public function testBulkPageDoesNotRevalidateUnchangedReferences()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $content = [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]];
        $pages = [$this->page( $content ), $this->page( $content )];
        $user = new \App\Models\User( [
            'name' => 'Restricted bulk page editor',
            'cmsperms' => ['page:view', 'page:save'],
        ] );

        $saved = Resource::bulkPage( collect( $pages )->pluck( 'id' )->all(), ['title' => 'Renamed'], $user );

        $this->assertSame( collect( $pages )->pluck( 'id' )->all(), $saved['ids'] );
        $this->assertSame( 0, $saved['failed'] );
        foreach( $pages as $page ) {
            $this->assertSame( [$file->id], $page->fresh()->latest->files()->pluck( 'cms_files.id' )->all() );
        }
    }


    public function testBulkPageReplacesChangedReferences()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]] );

        Resource::bulkPage( [$page->id], [
            'content' => [['type' => 'heading', 'data' => ['title' => 'No image']]],
        ], $this->user );

        $this->assertNotContains( $file->id, Page::findOrFail( $page->id )->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testBulkPageBoundsDescendantQueries()
    {
        $ids = [];

        foreach( range( 1, 51 ) as $num ) {
            $ids[] = Resource::addPage( [
                'lang' => 'en', 'name' => 'Root ' . $num, 'title' => 'Root ' . $num,
                'path' => 'res-' . $num . '-' . Utils::uid(), 'content' => [],
            ], $this->user, parent: $this->root()->id )->id;
        }

        $this->expectsDatabaseQueryCount( 223 );

        $saved = Resource::bulkPage( $ids, ['title' => 'Renamed'], $this->user, descendants: true );

        $this->assertSame( $ids, $saved['ids'] );
    }


    public function testBulkPageProcessesInDepthFirstTreeOrder()
    {
        $mk = fn( string $name, string $parent ) => Resource::addPage(
            ['lang' => 'en', 'name' => $name, 'title' => $name, 'path' => 'res-' . Utils::uid(), 'content' => []],
            $this->user, parent: $parent
        );

        // tree: p -> [a -> [a1], b]; appendToNode keeps b to the right of a
        $p = $mk( 'P', $this->root()->id );
        $a = $mk( 'A', $p->id );
        $b = $mk( 'B', $p->id );
        $a1 = $mk( 'A1', $a->id );

        $saved = Resource::bulkPage( [$p->id, $a->id], ['title' => 'Renamed'], $this->user, descendants: true );

        // depth-first pre-order: a whole subtree before the next sibling, parents before children
        $this->assertSame( [$p->id, $a->id, $a1->id, $b->id], $saved['ids'] );
    }


    public function testBulkPageUsesScoutQueue()
    {
        $page = $this->page( [['type' => 'heading', 'data' => ['title' => 'Hi']]] );

        config( ['scout.queue' => true] );
        Queue::fake();

        Resource::bulkPage( [$page->id], ['title' => 'Renamed'], $this->user );

        Queue::assertPushed( IndexModels::class, 1 );
        Queue::assertPushed( IndexModels::class, fn( $job ) => $job->model === Page::class && $job->ids === [$page->id] );
    }


    public function testBulkPageQueuesOnePruneBatch()
    {
        $pages = [$this->page( [] ), $this->page( [] )];
        config( ['scout.queue' => true] );
        Queue::fake();

        Resource::bulkPage( collect( $pages )->pluck( 'id' )->all(), ['title' => 'Renamed'], $this->user );

        Queue::assertPushed( PruneVersions::class, 1 );
        Queue::assertPushed( PruneVersions::class, fn( $job ) => $job->model === Page::class
            && $job->ids === collect( $pages )->pluck( 'id' )->sort()->values()->all() );
    }


    public function testBulkFileQueuesOnePruneBatch()
    {
        $files = File::limit( 2 )->get();
        config( ['scout.queue' => true] );
        Queue::fake();

        Resource::bulkFile( $files->modelKeys(), ['lang' => 'de'], $this->user );

        Queue::assertPushed( PruneVersions::class, 1 );
        Queue::assertPushed( PruneVersions::class, fn( $job ) => $job->model === File::class
            && $job->ids === collect( $files->modelKeys() )->sort()->values()->all() );
    }


    public function testScoutQueueDefersModelLoading()
    {
        $page = Page::firstOrFail();
        config( ['scout.queue' => true] );
        Queue::fake();

        $this->expectsDatabaseQueryCount( 0 );

        Scout::index( Page::class, [$page->id] );

        Queue::assertPushed( IndexModels::class, fn( $job ) => $job->model === Page::class && $job->ids === [$page->id] );
    }


    public function testIndexJobUsesCapturedTenant()
    {
        $page = $this->page( [['type' => 'heading', 'data' => ['title' => 'Indexed']]] );
        $tenant = Tenancy::value();
        $loaded = [];
        Page::retrieved( function( Page $page ) use ( &$loaded ) {
            $loaded[] = $page->tenant_id;
        } );
        $this->app->instance( Tenancy::class, new Tenancy( 'other' ) );

        ( new IndexModels( Page::class, [$page->id], $tenant ) )->handle();

        $this->assertSame( 'other', Tenancy::value() );
        $this->assertContains( $tenant, $loaded );
        $this->assertNotContains( 'other', $loaded );
    }


    public function testLifecycleUsesOneScoutBatch()
    {
        $ids = [];

        Element::withoutSyncingToSearch( function() use ( &$ids ) {
            foreach( range( 1, 2 ) as $num ) {
                $element = Resource::addElement( [
                    'lang' => 'en', 'type' => 'text', 'name' => 'Lifecycle ' . $num,
                    'data' => ['text' => 'Lifecycle'],
                ], $this->user );
                $ids[] = $element->id;
            }
        } );

        config( ['scout.queue' => true, 'scout.soft_delete' => true] );

        Queue::fake();
        Publication::publish( Element::class, $ids, $this->user );
        Queue::assertPushed( IndexModels::class, 1 );

        Queue::fake();
        Resource::drop( Element::class, $ids, $this->user );
        Queue::assertPushed( IndexModels::class, 1 );

        Queue::fake();
        Resource::restore( Element::class, $ids, $this->user );
        Queue::assertPushed( IndexModels::class, 1 );

        Queue::fake();
        Resource::purge( Element::class, $ids, $this->user );
        Queue::assertPushed( RemoveFromSearch::class, 1 );
    }


    public function testFlatLifecycleUsesOneModelWritePerChunk()
    {
        Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Second lifecycle element',
            'data' => ['text' => 'Lifecycle'],
        ], $this->user );
        Queue::fake();
        $ids = [];

        foreach( [Element::class, File::class] as $model )
        {
            $ids[$model] = $model::limit( 2 )->pluck( 'id' )->all();
            $this->assertCount( 2, $ids[$model] );
        }

        $this->expectsDatabaseQueryCount( 22 );

        foreach( $ids as $model => $modelIds ) {
            Resource::drop( $model, $modelIds, $this->user );
            Resource::restore( $model, $modelIds, $this->user );
            Resource::purge( $model, $modelIds, $this->user );
        }
    }


    public function testFlatLifecycleProjectsRequestedFields()
    {
        Queue::fake();

        $element = Element::where( 'type', 'footer' )->firstOrFail();
        $dropped = Resource::drop(
            Element::class, [$element->id], $this->user, ['id', 'deleted_at'],
        )->firstOrFail();

        $this->assertArrayNotHasKey( 'data', $dropped->getAttributes() );
        $this->assertArrayNotHasKey( 'name', $dropped->getAttributes() );

        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $purged = Resource::purge( File::class, [$file->id], $this->user, ['id'] )->firstOrFail();

        $this->assertArrayHasKey( 'path', $purged->getAttributes() );
        $this->assertArrayHasKey( 'previews', $purged->getAttributes() );
        $this->assertArrayNotHasKey( 'description', $purged->getAttributes() );
        $this->assertArrayNotHasKey( 'transcription', $purged->getAttributes() );
    }


    public function testFlatLifecycleLocksIdsInGlobalOrder()
    {
        $ids = array_map(
            fn( int $num ) => sprintf( '00000000-0000-0000-0000-%012d', $num ),
            range( 1, 101 ),
        );
        $time = now();

        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_elements' )->insert(
            array_map( fn( string $id ) => [
                'id' => $id,
                'tenant_id' => Tenancy::value(),
                'type' => 'text',
                'lang' => 'en',
                'name' => $id,
                'data' => '{}',
                'latest_id' => null,
                'editor' => 'test',
                'created_at' => $time,
                'updated_at' => $time,
                'deleted_at' => null,
            ], $ids ),
        );
        Queue::fake();

        $items = Resource::drop( Element::class, array_reverse( $ids ), $this->user );

        $this->assertSame( $ids, $items->pluck( 'id' )->all() );
    }


    public function testPublishBatchesModelAndVersionWrites()
    {
        $ids = [];

        foreach( range( 1, 2 ) as $num ) {
            $element = Resource::addElement( [
                'lang' => 'en', 'type' => 'text', 'name' => 'Before ' . $num,
                'data' => ['text' => 'Before'],
            ], $this->user );
            Resource::saveElement( $element->id, ['name' => 'After ' . $num], $this->user );
            $ids[] = $element->id;
        }

        Element::withoutSyncingToSearch( fn() => Element::whereIn( 'id', $ids )
            ->update( ['updated_at' => '2000-01-01 00:00:00'] ) );
        $this->expectsDatabaseQueryCount( 3 );

        $items = Publication::publish( Element::class, $ids, $this->user );

        $this->assertSame( ['After 1', 'After 2'], $items->sortBy( 'name' )->pluck( 'name' )->all() );
    }


    public function testPublishBatchesRootAndDependencyVersions()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => false] );
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Dependency',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $this->expectsDatabaseQueryCount( 11 );

        Publication::publish( Page::class, [$page->id], $this->user );

        $versionIds = [$file->latest_id, $element->latest_id, $page->latest_id];

        $this->assertSame( 3, Version::whereIn( 'id', $versionIds )->where( 'published', true )->count() );
    }


    public function testPublishSkipsPublishedLatestVersions()
    {
        $published = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Published',
            'data' => ['text' => 'Published'],
        ], $this->user );
        $draft = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Draft',
            'data' => ['text' => 'Draft'],
        ], $this->user );

        Publication::publish( Element::class, [$published->id], $this->user );
        Element::withoutSyncingToSearch( fn() => Element::whereKey( $published->id )
            ->update( ['updated_at' => '2000-01-01 00:00:00'] ) );

        config( ['scout.queue' => true] );
        Queue::fake();

        Publication::publish( Element::class, [$published->id, $draft->id], $this->user );

        $this->assertSame( '2000-01-01 00:00:00', (string) $published->fresh()->updated_at );
        $this->assertTrue( (bool) $draft->latest->fresh()->published );
        Queue::assertPushed( IndexModels::class, fn( $job ) => $job->ids === [$draft->id] );
    }


    public function testPublishDoesNotSchedulePublishedLatestVersion()
    {
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Published',
            'data' => ['text' => 'Published'],
        ], $this->user );

        Publication::publish( Element::class, [$element->id], $this->user );
        Publication::publish( Element::class, [$element->id], $this->user, '2030-01-01 00:00:00' );

        $latest = $element->latest->fresh();
        $this->assertNull( $latest->publish_at );
        $this->assertSame( 0, $latest->data->scheduled );
    }


    public function testPublishBatchesScheduleWrites()
    {
        Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Scheduled second',
            'data' => ['text' => 'Scheduled'],
        ], $this->user );
        $ids = Element::limit( 2 )->pluck( 'id' )->all();
        $this->expectsDatabaseQueryCount( 2 );

        $items = Publication::publish( Element::class, $ids, $this->user, '2030-01-01 00:00:00' );

        $this->assertCount( 2, $items );
        $this->assertTrue( $items->every(
            fn( Element $item ) => (string) $item->latest?->publish_at === '2030-01-01 00:00:00',
        ) );
    }


    public function testPublishAllowsAlreadyPublishedDependenciesWithoutPublishRights()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => false] );
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Published dependency',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        Publication::publish( Element::class, [$element->id], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $user = new \App\Models\User( [
            'name' => 'Page publisher',
            'cmsperms' => ['publisher', '!element:publish', '!file:publish'],
        ] );

        Publication::publish( Page::class, [$page->id], $user );

        $this->assertTrue( (bool) $page->latest->fresh()->published );
    }


    public function testPublishRejectsDeniedElementDependency()
    {
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Denied dependency',
            'data' => ['text' => 'Draft'],
        ], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page publisher',
            'cmsperms' => ['publisher', '!element:publish'],
        ] );

        try {
            Publication::publish( Page::class, [$page->id], $user );
            $this->fail( 'Expected the unpublished element dependency to be denied' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $this->assertFalse( (bool) $page->latest->fresh()->published );
        $this->assertFalse( (bool) $element->latest->fresh()->published );
    }


    public function testPublishRejectsDeniedNestedFileDependency()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => false] );
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'File dependency',
            'data' => ['file' => ['id' => $file->id, 'type' => 'file']],
        ], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page publisher',
            'cmsperms' => ['publisher', '!file:publish'],
        ] );

        try {
            Publication::publish( Page::class, [$page->id], $user );
            $this->fail( 'Expected the unpublished file dependency to be denied' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $this->assertFalse( (bool) $page->latest->fresh()->published );
        $this->assertFalse( (bool) $element->latest->fresh()->published );
        $this->assertFalse( (bool) $file->latest->fresh()->published );
    }


    public function testScheduleRejectsDeniedPublishedElementDependency()
    {
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Published dependency',
            'data' => ['text' => 'Published'],
        ], $this->user );
        Publication::publish( Element::class, [$element->id], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page publisher',
            'cmsperms' => ['publisher', '!element:publish'],
        ] );

        try {
            Publication::publish( Page::class, [$page->id], $user, '2030-01-01 00:00:00' );
            $this->fail( 'Expected the scheduled published element dependency to be denied' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $this->assertNull( $page->latest->fresh()->publish_at );
        $this->assertTrue( (bool) $element->latest->fresh()->published );
    }


    public function testScheduleRequiresFilePublishForElementDependencies()
    {
        $element = Resource::addElement( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Mutable dependency',
            'data' => ['text' => 'No file yet'],
        ], $this->user );
        Publication::publish( Element::class, [$element->id], $this->user );
        $page = $this->page( [[
            'type' => 'reference', 'refid' => $element->id, 'group' => 'main',
        ]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page publisher',
            'cmsperms' => ['publisher', '!file:publish'],
        ] );

        try {
            Publication::publish( Page::class, [$page->id], $user, '2030-01-01 00:00:00' );
            $this->fail( 'Expected a mutable element dependency to require file publication rights' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $this->assertNull( $page->latest->fresh()->publish_at );
    }


    public function testScheduleRejectsDeniedPublishedFileDependency()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $file->latest()->update( ['published' => true] );
        $page = $this->page( [['type' => 'image', 'data' => [
            'file' => ['id' => $file->id, 'type' => 'file'],
        ]]] );
        $user = new \App\Models\User( [
            'name' => 'Restricted page publisher',
            'cmsperms' => ['publisher', '!file:publish'],
        ] );

        try {
            Publication::publish( Page::class, [$page->id], $user, '2030-01-01 00:00:00' );
            $this->fail( 'Expected the scheduled file dependency to be denied' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $this->assertNull( $page->latest->fresh()->publish_at );
        $this->assertTrue( (bool) $file->latest->fresh()->published );
    }


    public function testPublishSkipsUnchangedPagePivots()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $content = [['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]]];
        $pages = [$this->page( $content ), $this->page( $content )];
        Page::withoutSyncingToSearch( fn() => Page::whereKey( $pages[0]->id )
            ->update( ['updated_at' => '2000-01-01 00:00:00'] ) );
        $this->expectsDatabaseQueryCount( 11 );

        Publication::publish( Page::class, collect( $pages )->pluck( 'id' )->all(), $this->user );

        foreach( $pages as $page ) {
            $this->assertSame( [$file->id], $page->fresh()->files()->pluck( 'cms_files.id' )->all() );
        }
    }


    public function testPublishSynchronizesChangedPagePivots()
    {
        $target = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $other = File::where( 'mime', 'image/tiff' )->firstOrFail();
        $content = [['type' => 'image', 'data' => ['file' => ['id' => $target->id, 'type' => 'file']]]];
        $page = $this->page( $content );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->table( 'cms_page_file' )->where( 'page_id', $page->id )->delete();
        $db->table( 'cms_page_file' )->insert( ['page_id' => $page->id, 'file_id' => $other->id] );
        $this->expectsDatabaseQueryCount( 11 );

        Publication::publish( Page::class, [$page->id], $this->user );

        $this->assertSame( [$target->id], Page::findOrFail( $page->id )->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testPublishInvalidatesPreviousAndCurrentRoutes()
    {
        $page = $this->page( [] );
        $previous = ['domain' => (string) $page->domain, 'path' => (string) $page->path];
        $page = Resource::savePage( $page->id, ['path' => 'changed-route'], $this->user );
        Event::fake( [PagesInvalidated::class] );

        Publication::publish( Page::class, [$page->id], $this->user );

        Event::assertDispatched( PagesInvalidated::class, function( PagesInvalidated $event ) use ( $page, $previous ) {
            return $event->routes === [
                $previous,
                ['domain' => (string) $page->domain, 'path' => 'changed-route'],
            ];
        } );
    }


    public function testPublishDirectUsesPublication()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => [
            'file' => ['id' => $file->id, 'type' => 'file'],
        ]]] );
        $version = $page->latest()->firstOrFail();
        Event::fake( [PagesInvalidated::class] );

        $page->publish( $version );

        $this->assertTrue( (bool) $version->refresh()->published );
        $this->assertSame( [$file->id], $page->files()->pluck( 'cms_files.id' )->all() );
        Event::assertDispatched( PagesInvalidated::class, fn( PagesInvalidated $event ) => $event->routes === [[
            'domain' => (string) $page->domain,
            'path' => (string) $page->path,
        ]] );
    }


    public function testPublicationKeepsIdsAsIs()
    {
        $publication = new Publication();
        $ids = [' Mixed,ID ', ' mixed,id '];

        foreach( $ids as $id )
        {
            $model = new class extends Base {
                public function getIdAttribute( ?string $value ) : ?string
                {
                    return $value;
                }

                public function save( array $options = [] )
                {
                    return true;
                }
            };
            $version = new class extends Version {
                public function save( array $options = [] )
                {
                    return true;
                }
            };
            $model->id = $id;
            $publication->apply( $model, $version );
        }

        $property = new \ReflectionProperty( Publication::class, 'models' );
        $models = $property->getValue( $publication );

        $this->assertSame( $ids, array_keys( $models[$model::class] ) );
    }


    public function testDropInvalidatesPageRoutes()
    {
        $pages = [$this->page( [] ), $this->page( [] )];
        $pages[] = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $pages[0]->id );
        Event::fake( [PagesInvalidated::class] );

        Resource::drop( Page::class, collect( $pages )->take( 2 )->pluck( 'id' )->all(), $this->user );

        $routes = collect( $pages )->map( fn( $page ) => [
            'domain' => (string) $page->domain,
            'path' => (string) $page->path,
        ] )->sortBy( 'path' )->values()->all();

        Event::assertDispatched( PagesInvalidated::class, fn( PagesInvalidated $event ) =>
            collect( $event->routes )->sortBy( 'path' )->values()->all() === $routes
        );
    }


    public function testPurgeInvalidatesPageRoutes()
    {
        $page = $this->page( [] );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $page->id );
        Event::fake( [PagesInvalidated::class] );

        Resource::purge( Page::class, [$page->id], $this->user );

        Event::assertDispatched( PagesInvalidated::class, fn( PagesInvalidated $event ) =>
            collect( $event->routes )->sortBy( 'path' )->values()->all() === collect( [$page, $child] )
                ->map( fn( Page $item ) => [
                    'domain' => (string) $item->domain,
                    'path' => (string) $item->path,
                ] )->sortBy( 'path' )->values()->all()
        );
    }


    public function testPageLifecycleSkipsLargeColumns()
    {
        $actions = [
            'drop' => fn( Page $page ) => Resource::drop( Page::class, [$page->id], $this->user, ['id'] ),
            'purge' => fn( Page $page ) => Resource::purge( Page::class, [$page->id], $this->user, ['id'] ),
            'restore' => function( Page $page ) {
                $page->delete();
                return Resource::restore( Page::class, [$page->id], $this->user, ['id'] );
            },
        ];

        foreach( $actions as $action => $run )
        {
            $page = $this->page( [['type' => 'text', 'data' => ['text' => str_repeat( 'x', 1000 )]]] );
            $item = $run( $page )->firstOrFail();

            $this->assertArrayHasKey( 'id', $item->getAttributes(), $action );
            $this->assertArrayNotHasKey( 'content', $item->getAttributes(), $action );
            $this->assertArrayNotHasKey( 'meta', $item->getAttributes(), $action );
        }
    }


    public function testPublishRejectsCumulativeReferenceWork()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $page = $this->page( [['type' => 'image', 'data' => [
            'file' => ['id' => $file->id, 'type' => 'file'],
        ]]] );
        $publication = new Publication();
        $property = new \ReflectionProperty( Publication::class, 'work' );
        $property->setValue( $publication, Page::MAX_BULK );
        $version = $page->latest()->firstOrFail();
        $this->expectsDatabaseQueryCount( 1 );

        try {
            $publication->prepare( collect( [$version] ) );
            $this->fail( 'The cumulative reference limit was not enforced' );
        } catch( Exception $e ) {
            $this->assertStringContainsString( 'No more than 1000 items', $e->getMessage() );
        }

    }


    public function testBulkElementBestEffortSkipsFailing()
    {
        $keep = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        $drop = File::where( 'mime', 'image/tiff' )->firstOrFail();

        $good = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Good',
            'data' => ['file' => ['id' => $keep->id, 'type' => 'file']],
        ], $this->user );

        $bad = Resource::addElement( [
            'lang' => 'en', 'type' => 'image', 'name' => 'Bad',
            'data' => ['file' => ['id' => $drop->id, 'type' => 'file']],
        ], $this->user );

        // the file $bad references vanishes, so re-saving it re-collects a now-missing reference
        $drop->delete();

        $saved = Resource::bulkElement( [$good->id, $bad->id], ['lang' => 'de'], $this->user );

        // best effort: the good element is committed and returned, the failing one is rolled back
        $this->assertCount( 1, $saved['ids'] );
        $this->assertEquals( $good->id, $saved['ids'][0] );
        $this->assertArrayHasKey( $good->id, $saved['latest'] );
        $this->assertArrayNotHasKey( $bad->id, $saved['latest'] );
        // the element whose save threw is counted as failed, not silently dropped
        $this->assertSame( 1, $saved['failed'] );
        $this->assertEquals( 'de', Element::findOrFail( $good->id )->latest->lang );
        $this->assertEquals( 'en', Element::findOrFail( $bad->id )->latest->lang );
    }


    public function testBulkLimit()
    {
        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No more than 1000 items' );

        Resource::bulkPage(
            array_map( strval(...), range( 1, Page::MAX_BULK + 1 ) ),
            ['title' => 'Renamed'],
            $this->user,
        );
    }


    public function testFileVersionCleanupWaitsForCommit()
    {
        config( ['cms.disk' => 'version-cleanup', 'cms.versions' => 1] );
        Storage::fake( 'version-cleanup' );

        $file = File::forceCreate( [
            'lang' => 'en', 'mime' => 'application/octet-stream', 'name' => 'cleanup.bin',
            'path' => 'cms/test/current.bin', 'editor' => 'test',
        ] );
        $paths = [];

        foreach( range( 1, 205 ) as $num )
        {
            $path = 'cms/test/version-' . $num . '.bin';
            Storage::disk( 'version-cleanup' )->put( $path, 'content' );
            $paths[] = $path;

            $file->versions()->forceCreate( [
                'lang' => 'en', 'editor' => 'test',
                'data' => ['path' => $path, 'previews' => []],
            ] );
        }

        try {
            DB::connection( config( 'cms.db', 'sqlite' ) )->transaction( function() use ( $file ) {
                $file->removeVersions();
                throw new \RuntimeException( 'rollback' );
            } );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'rollback', $e->getMessage() );
        }

        $this->assertSame( 205, $file->versions()->count() );
        foreach( $paths as $path ) {
            Storage::disk( 'version-cleanup' )->assertExists( $path );
        }

        $file->removeVersions();

        $this->assertSame( 1, $file->versions()->count() );
        Storage::disk( 'version-cleanup' )->assertExists( end( $paths ) );
        foreach( array_slice( $paths, 0, -1 ) as $path ) {
            Storage::disk( 'version-cleanup' )->assertMissing( $path );
        }
    }


    public function testFileCleanupJobsAreBounded()
    {
        Queue::fake( [DeleteFilePaths::class] );
        $method = new \ReflectionMethod( File::class, 'deletePaths' );
        $paths = collect( array_map( fn( $num ) => 'cms/test/' . $num . '.bin', range( 1, 205 ) ) );

        $method->invoke( null, $paths, 'test' );

        Queue::assertPushed( DeleteFilePaths::class, 3 );
        Queue::assertPushed( DeleteFilePaths::class, fn( $job ) => $job->tenant === 'test'
            && count( $job->paths ) <= 100 );
    }


    public function testFileCleanupOnlyDeletesUnownedTenantPaths()
    {
        config( ['cms.disk' => 'guarded-cleanup'] );
        Storage::fake( 'guarded-cleanup' );

        $owned = 'cms/test/owned.bin';
        $versioned = 'cms/test/versioned.bin';
        $orphan = 'cms/test/orphan.bin';
        $foreign = 'cms/other/foreign.bin';

        foreach( [$owned, $versioned, $orphan, $foreign] as $path ) {
            Storage::disk( 'guarded-cleanup' )->put( $path, 'content' );
        }

        $file = File::forceCreate( [
            'lang' => 'en', 'mime' => 'application/octet-stream', 'name' => 'owned.bin',
            'path' => $owned, 'editor' => 'test',
        ] );
        $file->versions()->forceCreate( [
            'lang' => 'en', 'editor' => 'test',
            'data' => ['path' => $versioned, 'previews' => []],
        ] );

        ( new DeleteFilePaths( 'guarded-cleanup', 'test', [$owned, $versioned, $orphan, $foreign] ) )->handle();

        Storage::disk( 'guarded-cleanup' )->assertExists( $owned );
        Storage::disk( 'guarded-cleanup' )->assertExists( $versioned );
        Storage::disk( 'guarded-cleanup' )->assertMissing( $orphan );
        Storage::disk( 'guarded-cleanup' )->assertExists( $foreign );
    }


    public function testFileCleanupCanonicalizesPathsBeforeOwnershipChecks()
    {
        Storage::fake( 'canonical-cleanup' );
        Storage::disk( 'canonical-cleanup' )->put( 'cms/test/owned.bin', 'content' );

        File::forceCreate( [
            'lang' => 'en', 'mime' => 'application/octet-stream', 'name' => 'owned.bin',
            'path' => 'cms/test/owned.bin', 'editor' => 'test',
        ] );

        ( new DeleteFilePaths(
            'canonical-cleanup', 'test', ['cms//test/./owned.bin'],
        ) )->handle();

        Storage::disk( 'canonical-cleanup' )->assertExists( 'cms/test/owned.bin' );
    }


    public function testDefaultTenantCleanupRejectsNestedTenantPaths()
    {
        Storage::fake( 'default-cleanup' );
        Storage::disk( 'default-cleanup' )->put( 'cms/other/foreign.bin', 'content' );

        ( new DeleteFilePaths( 'default-cleanup', '', ['cms/other/foreign.bin'] ) )->handle();

        Storage::disk( 'default-cleanup' )->assertExists( 'cms/other/foreign.bin' );
    }


    public function testFileSaveQueuesVersionCleanup()
    {
        $file = File::where( 'mime', 'image/jpeg' )->firstOrFail();
        Queue::fake( [PruneVersions::class] );

        Resource::saveFile( $file->id, ['name' => 'Cleanup queued'], $this->user );

        Queue::assertPushed( PruneVersions::class, 1 );
        Queue::assertPushed( PruneVersions::class, fn( $job ) => $job->model === File::class
            && $job->tenant === $file->tenant_id && $job->ids === [$file->id] );
    }


    public function testRemoveVersionsDropsCompleteBacklog()
    {
        config( ['cms.versions' => 2] );

        $element = Element::forceCreate( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Versions',
            'data' => ['text' => 'Versions'], 'editor' => 'test',
        ] );

        foreach( range( 1, 15 ) as $num ) {
            $element->versions()->forceCreate( [
                'lang' => 'en', 'editor' => 'test',
                'data' => ['name' => 'Version ' . $num],
            ] );
        }

        $element->removeVersions();

        $this->assertSame( 2, $element->versions()->count() );
    }


    public function testPruneVersionsStreamsCompleteBacklogOnce()
    {
        config( ['cms.versions' => 2] );

        $element = Element::forceCreate( [
            'lang' => 'en', 'type' => 'text', 'name' => 'Queued versions',
            'data' => ['text' => 'Versions'], 'editor' => 'test',
        ] );

        foreach( range( 1, 505 ) as $num ) {
            $element->versions()->forceCreate( [
                'lang' => 'en', 'editor' => 'test',
                'data' => ['name' => 'Version ' . $num],
            ] );
        }

        ( new PruneVersions( Element::class, (string) $element->tenant_id, [$element->id] ) )->handle();

        $this->assertSame( 2, $element->versions()->count() );
    }


    public function testReferenceLimit()
    {
        $method = new \ReflectionMethod( Resource::class, 'refs' );
        $content = array_map( fn( $num ) => ['type' => 'reference', 'refid' => 'ref-' . $num], range( 1, Page::MAX_BULK + 1 ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No more than 1000 items' );

        $method->invoke( null, ['content' => $content] );
    }


    /**
     * Creates a draft page below the root node with the given content.
     *
     * @param array<int, array<string, mixed>> $content Content blocks
     * @return Page
     */
    protected function page( array $content ) : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'res-' . Utils::uid(),
            'content' => $content,
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
