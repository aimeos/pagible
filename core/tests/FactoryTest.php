<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;


class FactoryTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    public function testPage() : void
    {
        $page = PageView::factory()->create( ['name' => 'About', 'content' => [['type' => 'text', 'data' => ['text' => 'Hello']]]] );

        $this->assertTrue( $page->exists );
        $this->assertTrue( $page->wasRecentlyCreated );
        $this->assertEquals( 'About', $page->latest?->data->name );
        $this->assertEquals( 'Hello', $page->latest?->aux->content[0]->data->text );
        $this->assertEquals( 1, $page->latest?->data->status );
        $this->assertEquals( 0, $page->status );
        $this->assertNull( PageView::live()->find( $page->id ) );
    }


    public function testPageChild() : void
    {
        $root = PageView::factory()->create();
        $child = PageView::factory()->for( $root, 'parent' )->create();
        $other = PageView::factory()->create( ['parent_id' => $root->id] );

        $this->assertEquals( $root->id, $child->parent_id );
        $this->assertEquals( $root->id, $other->parent_id );
    }


    public function testPagePublished() : void
    {
        $pages = PageView::factory()->count( 2 )->published()->create();

        $this->assertCount( 2, PageView::live()->whereKey( $pages->pluck( 'id' ) )->get() );
        $this->assertTrue( $pages->every( fn( $page ) => $page->latest?->published ) );
    }


    public function testElement() : void
    {
        $element = Element::factory()->published()->create( ['name' => 'Shared'] );

        $this->assertEquals( 'Shared', $element->name );
        $this->assertEquals( 'text', $element->type );
        $this->assertTrue( (bool) $element->latest?->published );
    }


    public function testFile() : void
    {
        Storage::fake( 'public' );

        $file = File::factory()->create( ['name' => 'Logo'] );

        $this->assertEquals( 'Logo', $file->latest?->data->name );
        $this->assertStringStartsWith( 'image/', (string) $file->latest?->data->mime );
        Storage::disk( 'public' )->assertExists( (string) $file->latest?->data->path );
    }


    public function testFilePrivate() : void
    {
        Storage::fake( 'local' );

        $file = File::factory()->private()->create( ['file' => UploadedFile::fake()->createWithContent( 'doc.pdf', '%PDF-1.4' )] );

        $this->assertEquals( 'private', $file->disk );
        $this->assertEquals( 'application/pdf', $file->latest?->data->mime );
    }


    public function testLive() : void
    {
        $page = PageView::factory()->published()->create();
        $file = File::factory()->published()->create();

        $page = Permission::system( fn() => \Aimeos\Cms\Facades\Pages::save( $page->id, ['content' => [
            ['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]],
        ]] ) );
        Permission::system( fn() => \Aimeos\Cms\Facades\Pages::publish( $page->id ) );

        $live = PageView::live( null, 'de' )->findOrFail( $page->id );

        $this->assertEquals( 'en', $live->lang );
        $this->assertTrue( $live->relationLoaded( 'files' ) );
        $this->assertEquals( [$file->id], $live->files->pluck( 'id' )->all() );
    }


    public function testLiveInactive() : void
    {
        $page = PageView::factory()->inactive()->published()->create();
        $hidden = PageView::factory()->hidden()->published()->create();
        $editor = $this->user( 'admin' );

        $this->actingAs( $editor );

        $this->assertNull( PageView::live( $editor )->find( $page->id ) );
        $this->assertNotNull( PageView::live( $editor )->find( $hidden->id ) );
    }


    public function testLiveRestricted() : void
    {
        Access::using( fn() => ['member'] );

        $page = PageView::factory()->published()->create();
        Permission::system( fn() => PageAccess::set( [$page->id], [] ) );

        $this->assertNull( PageView::live()->find( $page->id ) );
        $this->assertNotNull( PageView::live( $this->user() )->find( $page->id ) );
    }


    public function testUser() : void
    {
        $user = $this->user( 'editor', '!page:drop' );

        $this->assertEquals( ['editor', '!page:drop'], $user->fresh()?->cmsPermissions() );
        $this->assertTrue( $user->can( 'page:save' ) );
        $this->assertFalse( $user->can( 'page:drop' ) );
        $this->assertFalse( $user->can( 'page:publish' ) );

        $this->assertEquals( ['publisher'], $user->setCmsPermissions( ['publisher'] ) );
        $this->assertTrue( $user->can( 'page:publish' ) );
    }


    protected function user( string ...$perms ) : User
    {
        return UserFactory::new()->cms( ...$perms )->create();
    }
}
