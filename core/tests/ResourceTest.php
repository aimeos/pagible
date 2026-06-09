<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Database\Seeders\TestSeeder;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;


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
            'meta' => ['social-media' => ['file' => ['id' => $tiff->id, 'type' => 'file']]],
        ], $this->user, parent: $this->root()->id );

        $ids = $page->latest->files()->pluck( 'cms_files.id' )->all();

        $this->assertContains( $jpeg->id, $ids );  // nested hero.background
        $this->assertContains( $tiff->id, $ids );  // meta social-media.file
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
