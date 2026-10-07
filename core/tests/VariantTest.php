<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;


class VariantTest extends CoreTestAbstract
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


    public function testAddVariant()
    {
        $page = $this->page();
        $de = Resource::addVariant( $page->id, 'de', $this->user );

        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->assertEquals( $page->id, $de->id );
        $this->assertEquals( 'de', $de->lang );
        $this->assertEquals( 'en', $de->source );
        $this->assertEquals( $variant->id, $de->variant_id );
        $this->assertEquals( $page->path . '-de', $variant->path );
        $this->assertEquals( 0, $variant->status );
        $this->assertTrue( $variant->stale );

        // texts are copied untranslated, so they still count as changed
        $this->assertSame( '', $variant->hashes['page:title'] );
        $this->assertSame( '', $variant->hashes['el:el1'] );
        $this->assertNotSame( '', $variant->hashes['page:order'] );

        $this->assertEquals( 'de', $de->latest->lang );
        $this->assertEquals( 'Test', $de->latest->data->title );
        $this->assertEquals( $variant->id, $de->latest->versionable_id );
        $this->assertEquals( PageVariant::class, $de->latest->versionable_type );
        $this->assertEquals( 'el1', $de->latest->aux->content[0]->id );

        // the source variant is unchanged
        $this->assertEquals( 'en', Page::findOrFail( $page->id )->lang );
        $this->assertEquals( 1, Version::where( 'versionable_id', $page->variant_id )->count() );
    }


    public function testAddVariantFullLanguageCode()
    {
        $page = $this->page();
        $variant = Resource::addVariant( $page->id, 'zh-Hant', $this->user );

        $this->assertEquals( 'zh-Hant', $variant->lang );
        $this->assertEquals( 'zh-Hant', $variant->latest->lang );
        $this->assertEquals( 'zh-Hant', Page::language( 'zh-Hant' )->findOrFail( $page->id )->lang );
    }


    public function testAddVariantInvalidLanguage()
    {
        $this->expectException( Exception::class );
        Resource::addVariant( $this->page()->id, 'de_DE', $this->user );
    }


    public function testAddVariantExisting()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Resource::addVariant( $page->id, 'de', $this->user );
    }


    public function testAddVariantTrashed()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::dropVariant( $page->id, 'de', $this->user );

        try {
            Resource::addVariant( $page->id, 'de', $this->user );
            $this->fail( 'Adding a trashed language must be refused' );
        } catch( Exception $e ) {
            $this->assertStringContainsString( 'restore', $e->getMessage() );
        }

        $this->assertEquals( 1, PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->count() );
    }


    public function testDropSourceVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Resource::dropVariant( $page->id, 'en', $this->user );
    }


    public function testSetSourceWithoutVariant()
    {
        $page = $this->page();

        $this->expectException( Exception::class );
        Resource::setSource( $page->id, 'de', $this->user );
    }


    public function testSetSource()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $result = Resource::setSource( $page->id, 'de', $this->user );

        $this->assertEquals( 'de', $result->source );
        $this->assertEquals( 'de', DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_pages' )->where( 'id', $page->id )->value( 'source' ) );
        $this->assertEquals( 'de', Page::findOrFail( $page->id )->lang );

        $de = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $en = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'en' )->firstOrFail();
        $published = Page::language( 'de' )->findOrFail( $page->id );
        $expected = Hashes::page( $published->only( Hashes::PAGE_FIELDS ), $published->content, $published->meta, $published->config );

        $this->assertEquals( [], $de->hashes );
        $this->assertFalse( $de->stale );
        $this->assertEquals( $expected, $en->hashes );
        $this->assertFalse( $en->stale );
    }


    public function testSetSourceNotLinked()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $page->id, 'fr', $this->user );

        // imported translation with another structure
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->update( ['hashes' => '{}'] );
        Resource::savePage( $page->id, ['content' => [['id' => 'other', 'type' => 'text', 'data' => ['text' => 'Autre']]]], $this->user, lang: 'fr' );
        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        Resource::setSource( $page->id, 'de', $this->user );

        $fr = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->firstOrFail();

        $this->assertEquals( [], $fr->hashes );
        $this->assertTrue( $fr->stale );
    }


    public function testEditorsSaveDifferentLanguages()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $enLatest = Page::findOrFail( $page->id )->latest_id;
        $deLatest = Page::language( 'de' )->findOrFail( $page->id )->latest_id;

        $en = Resource::savePage( $page->id, ['title' => 'English'], $this->user, $enLatest );
        $de = Resource::savePage( $page->id, ['title' => 'Deutsch'], $this->user, $deLatest, 'de' );

        $this->assertEquals( 'English', $en->latest->data->title );
        $this->assertEquals( 'en', $en->latest->lang );
        $this->assertEquals( 'Deutsch', $de->latest->data->title );
        $this->assertEquals( 'de', $de->latest->lang );

        $this->assertEquals( 'English', Page::findOrFail( $page->id )->latest->data->title );
        $this->assertEquals( 'Deutsch', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testSaveRefusesLanguageOfOtherVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Resource::savePage( $page->id, ['lang' => 'de'], $this->user );
    }


    public function testPublishVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['title' => 'Deutsch', 'status' => 1], $this->user, lang: 'de' );

        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $this->assertEquals( 'Deutsch', Page::language( 'de' )->findOrFail( $page->id )->title );
        $this->assertNotEquals( 'Deutsch', Page::findOrFail( $page->id )->title );
        $this->assertFalse( (bool) Page::findOrFail( $page->id )->latest->published );
    }


    public function testPublishVariantAttachments()
    {
        $files = File::take( 2 )->get();
        $this->assertCount( 2, $files );

        $page = $this->page( [['id' => 'img', 'type' => 'image', 'data' => ['file' => ['id' => $files[0]->id, 'type' => 'file']]]] );
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['content' => [
            ['id' => 'img', 'type' => 'image', 'data' => ['file' => ['id' => $files[1]->id, 'type' => 'file']]]
        ]], $this->user, lang: 'de' );

        Publication::publish( Page::class, [$page->id], $this->user );
        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $en = Page::findOrFail( $page->id );
        $de = Page::language( 'de' )->findOrFail( $page->id );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertEquals( [$files[0]->id], $db->table( 'cms_page_file' )->where( 'variant_id', $en->variant_id )->pluck( 'file_id' )->all() );
        $this->assertEquals( [$files[1]->id], $db->table( 'cms_page_file' )->where( 'variant_id', $de->variant_id )->pluck( 'file_id' )->all() );
    }


    public function testPublishSourceMarksStale()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $page->id, 'fr', $this->user );

        $source = Page::findOrFail( $page->id );
        $hashes = Hashes::page( $source->only( Hashes::PAGE_FIELDS ), $source->content, $source->meta, $source->config );

        // de is up to date, fr ignores the title change
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )
            ->update( ['hashes' => json_encode( $hashes ), 'stale' => false] );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )
            ->update( ['hashes' => json_encode( ['page:title' => Hashes::hash( 'Changed' )] + $hashes ), 'stale' => false] );

        Resource::savePage( $page->id, ['title' => 'Changed'], $this->user );
        Publication::publish( Page::class, [$page->id], $this->user );

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'stale' ) );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->value( 'stale' ) );
    }


    public function testPublishTranslationKeepsOthersFresh()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $page->id, 'fr', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->update( ['stale' => false] );

        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->value( 'stale' ) );
    }


    public function testPageLifecycle()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Publication::publish( Page::class, [$page->id], $this->user );
        Publication::publish( Page::class, [$page->id], $this->user, lang: 'de' );

        $variants = PageVariant::where( 'page_id', $page->id )->pluck( 'id' )->all();

        Resource::drop( Page::class, [$page->id], $this->user );

        $this->assertNull( Page::find( $page->id ) );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( Page::withTrashed()->language( 'de' )->find( $page->id ) );

        Resource::restore( Page::class, [$page->id], $this->user );

        $this->assertNotNull( Page::find( $page->id ) );
        $this->assertNotNull( Page::language( 'de' )->find( $page->id ) );

        Resource::drop( Page::class, [$page->id], $this->user );
        Resource::purge( Page::class, [$page->id], $this->user );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertEquals( 0, PageVariant::withTrashed()->where( 'page_id', $page->id )->count() );
        $this->assertEquals( 0, Version::whereIn( 'versionable_id', $variants )->count() );
        $this->assertEquals( 0, $db->table( 'cms_page_element' )->whereIn( 'variant_id', $variants )->count() );
        $this->assertEquals( 0, $db->table( 'cms_page_file' )->whereIn( 'variant_id', $variants )->count() );
    }


    public function testPageRestoreKeepsTrashedVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $page->id, 'fr', $this->user );
        Resource::dropVariant( $page->id, 'de', $this->user );

        Resource::drop( Page::class, [$page->id], $this->user );
        Resource::restore( Page::class, [$page->id], $this->user );

        $this->assertNotNull( Page::find( $page->id ) );
        $this->assertNotNull( Page::language( 'fr' )->find( $page->id ) );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( Page::language( 'de', true )->find( $page->id ) );
    }


    public function testVariantLifecycle()
    {
        $page = $this->page();
        Publication::publish( Page::class, [$page->id], $this->user );
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::savePage( $page->id, ['title' => 'Deutsch'], $this->user, lang: 'de' );

        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        PageVariant::whereKey( $variant->id )->update( ['stale' => false] );

        $dropped = Resource::dropVariant( $page->id, 'de', $this->user );

        $this->assertNotNull( $dropped->variant_deleted_at );
        $this->assertNull( Page::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( Page::find( $page->id ) );
        $this->assertEquals( 2, Version::where( 'versionable_id', $variant->id )->count() );

        Resource::restoreVariant( $page->id, 'de', $this->user );

        $restored = Page::language( 'de' )->findOrFail( $page->id );
        $this->assertEquals( 'Deutsch', $restored->latest->data->title );
        $this->assertTrue( PageVariant::whereKey( $variant->id )->value( 'stale' ) );

        Resource::dropVariant( $page->id, 'de', $this->user );
        Resource::purgeVariant( $page->id, 'de', $this->user );

        $this->assertEquals( 0, PageVariant::withTrashed()->whereKey( $variant->id )->count() );
        $this->assertEquals( 0, Version::where( 'versionable_id', $variant->id )->count() );
        $this->assertNotNull( Page::find( $page->id ) );
    }


    public function testPurgeSourceVariant()
    {
        $page = $this->page();

        $this->expectException( Exception::class );
        Resource::purgeVariant( $page->id, 'en', $this->user );
    }


    public function testPurgeKeepsOtherTenants()
    {
        \Aimeos\Cms\Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        try {
            $root = Resource::addPage( ['lang' => 'en', 'name' => 'Other', 'title' => 'Other', 'path' => 'other'], $this->user );
            $child = Resource::addPage( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'other-child'], $this->user, parent: $root->id );
        } finally {
            \Aimeos\Cms\Tenancy::$callback = fn() => 'test';
            app()->forgetScopedInstances();
        }

        $root = $this->root();
        $this->assertGreaterThanOrEqual( $root->_lft, $child->_lft );
        $this->assertLessThanOrEqual( $root->_rgt, $child->_rgt );

        Resource::purge( Page::class, [$root->id], $this->user );

        $this->assertEquals( 0, Page::withTrashed()->count() );
        $this->assertEquals( 2, Page::withoutTenancy()->withTrashed()->where( 'tenant_id', 'other' )->count() );
        $this->assertEquals( 2, PageVariant::withoutTenancy()->withTrashed()->where( 'tenant_id', 'other' )->count() );
    }


    public function testPruneVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $page->id, 'fr', $this->user );
        Resource::dropVariant( $page->id, 'de', $this->user );

        $variant = PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->travel( config( 'cms.prune', 30 ) + 1 )->days();
        $this->artisan( 'model:prune', ['--model' => [PageVariant::class]] )->assertSuccessful();

        $this->assertEquals( 0, PageVariant::withTrashed()->whereKey( $variant->id )->count() );
        $this->assertEquals( 0, Version::where( 'versionable_id', $variant->id )->count() );
        $this->assertNotNull( Page::find( $page->id ) );
        $this->assertNotNull( Page::language( 'fr' )->find( $page->id ) );
    }


    public function testCopyPage()
    {
        $page = $this->page();
        $child = Resource::addPage( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid(),
            'content' => [['id' => 'el2', 'type' => 'text', 'data' => ['text' => 'Child']]],
        ], $this->user, parent: $page->id );

        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::addVariant( $child->id, 'de', $this->user );

        $copy = Resource::copyPage( $page->id, null, $this->root()->id, $this->user );

        $this->assertNotEquals( $page->id, $copy->id );
        $this->assertEquals( 'en', $copy->source );
        $this->assertEquals( 2, PageVariant::where( 'page_id', $copy->id )->count() );
        $this->assertNotEquals( $page->path, $copy->path );

        $orig = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $de = Page::language( 'de' )->findOrFail( $copy->id );
        $variant = PageVariant::findOrFail( $de->variant_id );

        $this->assertEquals( $orig->hashes, $variant->hashes );
        $this->assertEquals( 'el1', $de->latest->aux->content[0]->id );
        $this->assertEquals( 1, Version::where( 'versionable_id', $de->variant_id )->count() );

        $children = Page::where( 'parent_id', $copy->id )->get();
        $this->assertCount( 1, $children );
        $this->assertEquals( 2, PageVariant::where( 'page_id', $children[0]->id )->count() );
        $this->assertEquals( 'el2', Page::language( 'de' )->findOrFail( $children[0]->id )->latest->aux->content[0]->id );
    }


    public function testFallbackLanguage()
    {
        $page = $this->page();
        $other = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $ids = [$page->id, $other->id];
        $langs = fn( bool $trashed = false ) => Page::fallback( 'de', $trashed )->withTrashed()->whereIn( 'id', $ids )
            ->get()->pluck( 'lang', 'id' )->all();

        $this->assertEquals( [$page->id => 'de', $other->id => 'en'], $langs() );

        // a trashed variant falls back to the source variant unless trashed ones are requested
        Resource::dropVariant( $page->id, 'de', $this->user );

        $this->assertEquals( [$page->id => 'en', $other->id => 'en'], $langs() );
        $this->assertEquals( [$page->id => 'de', $other->id => 'en'], $langs( true ) );
    }


    public function testSearchIndexesAllVariants()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );

        $source = Page::findOrFail( $page->id );
        $variant = Page::language( 'de' )->findOrFail( $page->id );

        $this->assertTrue( $source->shouldBeSearchable() );
        $this->assertTrue( $variant->shouldBeSearchable() );
        $this->assertEquals( $page->id, $source->getScoutKey() );
        $this->assertEquals( $variant->variant_id, $variant->getScoutKey() );
        $this->assertNotEquals( $page->id, $variant->getScoutKey() );
    }


    protected function page( ?array $content = null ) : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'var-' . Utils::uid(),
            'content' => $content ?? [['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']]],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }
}
