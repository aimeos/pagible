<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Jobs\SyncIndex;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;


class ResourceTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;
    private PageInvalidationSpy $invalidator;


    protected function setUp(): void
    {
        parent::setUp();

        $this->invalidator = new PageInvalidationSpy();
        Event::listen( PagesInvalidated::class, [$this->invalidator, 'handle'] );

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ]);
    }


    public function testPublishInvalidatesPreviousAndCurrentPageRoutes(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $oldPath = $page->path;
        $newPath = 'renamed-page';

        Resource::savePage( $page->id, ['path' => $newPath], $this->user );
        Resource::publish( Page::class, [$page->id], $this->user->email );

        $this->assertSame(
            [[$oldPath, $newPath]],
            array_map( fn( $batch ) => array_column( $batch, 'path' ), $this->invalidator->batches ),
        );
    }


    public function testDropInvalidatesCompletePageSubtree(): void
    {
        $parent = $this->page( [] );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $parent->id );
        $this->invalidator->reset();

        Resource::drop( Page::class, [(string) $parent->id], 'editor@testbench' );

        $this->assertEqualsCanonicalizing(
            [$parent->path, $child->path],
            array_column( $this->invalidator->batches[0], 'path' ),
        );
        $this->assertTrue( Page::withTrashed()->findOrFail( (string) $parent->id )->trashed() );
        $this->assertTrue( Page::withTrashed()->findOrFail( (string) $child->id )->trashed() );
    }


    public function testPurgeInvalidatesCompletePageSubtree(): void
    {
        $parent = $this->page( [] );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $parent->id );
        Resource::drop( Page::class, [(string) $parent->id], 'editor@testbench' );
        $this->invalidator->reset();

        Resource::purge( Page::class, [(string) $parent->id], 'editor@testbench' );

        $this->assertEqualsCanonicalizing(
            [$parent->path, $child->path],
            array_column( $this->invalidator->batches[0], 'path' ),
        );
        $this->assertNull( Page::withTrashed()->find( (string) $parent->id ) );
        $this->assertNull( Page::withTrashed()->find( (string) $child->id ) );
    }


    public function testDropQueuesSubtreeIndexReconciliation(): void
    {
        $parent = $this->page( [] );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $parent->id );
        $this->searchEngine();
        Queue::fake();

        Resource::drop( Page::class, [(string) $parent->id], 'editor@testbench' );

        Queue::assertPushed( SyncIndex::class, fn( SyncIndex $job ) =>
            $job->model === Page::class
                && $job->ids === [(string) $parent->id, (string) $child->id]
                && $job->tenant === 'test'
        );
    }


    public function testRestoreQueuesSubtreeIndexReconciliation(): void
    {
        $parent = $this->page( [] );
        $child = Resource::addPage( [
            'lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $parent->id );
        Resource::drop( Page::class, [(string) $parent->id], 'editor@testbench' );
        $this->searchEngine();
        Queue::fake();

        Resource::restore( Page::class, [(string) $parent->id], 'editor@testbench' );

        Queue::assertPushed( SyncIndex::class, fn( SyncIndex $job ) =>
            $job->model === Page::class
                && $job->ids === [(string) $parent->id, (string) $child->id]
                && $job->tenant === 'test'
        );
    }


    public function testRestoreReindexesExternalPageLanguage(): void
    {
        $page = Resource::addPage( [
            'lang' => 'de', 'name' => 'German', 'title' => 'German', 'path' => 'res-' . Utils::uid(),
            'content' => [],
        ], $this->user, parent: (string) $this->root()->id );
        Resource::drop( Page::class, [(string) $page->id], 'editor@testbench' );
        $engine = $this->searchEngine();

        Resource::restore( Page::class, [(string) $page->id], 'editor@testbench' );

        $this->assertSame( 'de', $engine->documents[(string) $page->id]['lang'] ?? null );
    }


    public function testSyncIndexReconcilesExistingAndMissingPages(): void
    {
        $page = $this->page( [] );
        $engine = $this->searchEngine();
        $missing = '01900000-0000-7000-8000-000000000001';

        ( new SyncIndex( Page::class, [(string) $page->id, $missing], 'test' ) )->handle();

        $this->assertSame( [[(string) $page->id]], $engine->updates );
        $this->assertSame( [[$missing]], $engine->deletions );
    }


    public function testSyncIndexKeepsSoftDeletedPagesWhenConfigured(): void
    {
        $page = $this->page( [] );
        Resource::drop( Page::class, [(string) $page->id], 'editor@testbench' );
        $engine = $this->searchEngine();
        config( ['scout.soft_delete' => true] );

        ( new SyncIndex( Page::class, [(string) $page->id], 'test' ) )->handle();

        $this->assertSame( [[(string) $page->id]], $engine->updates );
        $this->assertSame( [], $engine->deletions );
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

        $saved = Resource::bulkPage( [$p->id], ['title' => 'Renamed'], $this->user, descendants: true );

        // depth-first pre-order: a whole subtree before the next sibling, parents before children
        $this->assertSame( [$p->id, $a->id, $a1->id, $b->id], $saved['ids'] );
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


    private function searchEngine() : ResourceSearchEngineSpy
    {
        $engine = new ResourceSearchEngineSpy();
        $manager = app( EngineManager::class );

        $manager->extend( 'resource-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'resource-test'] );

        return $engine;
    }
}


class ResourceSearchEngineSpy extends NullEngine
{
    /** @var list<list<string>> */
    public array $updates = [];
    /** @var list<list<string>> */
    public array $deletions = [];
    /** @var array<string, array<string, mixed>> */
    public array $documents = [];


    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Page> $models
     */
    public function update( $models ) : void
    {
        $this->updates[] = array_values( array_map( strval(...), $models->modelKeys() ) );

        foreach( $models as $model ) {
            $this->documents[(string) $model->id] = $model->toSearchableArray();
        }
    }


    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Page> $models
     */
    public function delete( $models ) : void
    {
        $this->deletions[] = array_values( array_map( strval(...), $models->modelKeys() ) );
    }
}
