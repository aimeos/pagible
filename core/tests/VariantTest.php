<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\ContentAction;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Resource\Pages;
use Aimeos\Cms\Resource\Variants;
use Aimeos\Cms\Utils;
use Aimeos\Nestedset\NestedSet;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;


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
        $de = Variants::add( $page->id, 'de', $this->user );

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
        $this->assertEquals( 'en', PageView::findOrFail( $page->id )->lang );
        $this->assertEquals( 1, Version::where( 'versionable_id', $page->variant_id )->count() );
    }


    public function testVariantNormalizesValues()
    {
        $variant = PageVariant::where( 'page_id', $this->page()->id )->firstOrFail();
        $variant->update( ['domain' => 'Example.COM', 'cache' => null, 'status' => '1', 'tag' => null] );

        $this->assertEquals( 'example.com', $variant->domain );
        $this->assertSame( 5, $variant->cache );
        $this->assertSame( 1, $variant->status );
        $this->assertSame( '', $variant->tag );
        $this->assertEquals( 'example.com', $variant->view->domain );

        $this->expectException( Exception::class );
        $variant->update( ['meta' => ['invalid']] );
    }


    public function testAddVariantUniquePath()
    {
        $page = $this->page();
        $other = $this->page();

        PageVariant::where( 'page_id', $other->id )->update( ['path' => $page->path . '-de'] );
        Variants::add( $page->id, 'de', $this->user );

        $this->assertEquals( $page->path . '-de-2', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testAddVariantLongPrefix()
    {
        $page = $this->page();
        $child = Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid()],
            $this->user, parent: $page->id );

        Variants::add( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['path' => str_repeat( 'x', 250 )] );

        Variants::add( $child->id, 'de', $this->user );
        $path = PageView::language( 'de' )->findOrFail( $child->id )->path;

        // the path including a suffix for used paths must fit into the column
        $this->assertLessThanOrEqual( 255 - 2 - 6, mb_strlen( $path ) );
        $this->assertStringStartsWith( str_repeat( 'x', 240 ), $path );
    }


    public function testAddVariantUniquePathOneQuery()
    {
        $page = $this->page();

        foreach( ['-de', '-de-2', '-de-3', '-de-4'] as $suffix ) {
            PageVariant::where( 'page_id', $this->page()->id )->update( ['path' => $page->path . $suffix] );
        }

        // the used paths are loaded at once instead of checking each candidate
        $queries = 0;
        \Illuminate\Support\Facades\DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$queries ) {
            $queries += (int) ( str_contains( $query->sql, 'exists' ) && str_contains( $query->sql, '"path" = ?' ) );
        } );

        Variants::add( $page->id, 'de', $this->user );

        $this->assertEquals( $page->path . '-de-5', PageView::language( 'de' )->findOrFail( $page->id )->path );
        $this->assertSame( 0, $queries );
    }


    public function testAddVariantNullFields()
    {
        $page = $this->page();
        $version = Version::findOrFail( $page->latest_id );
        $version->data = (object) ( ['to' => null, 'theme' => null] + (array) $version->data );
        $version->save();

        Variants::add( $page->id, 'de', $this->user );
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->assertSame( '', $variant->to );
        $this->assertSame( '', $variant->theme );
    }


    public function testAddVariantFullLanguageCode()
    {
        $page = $this->page();
        $locales = config( 'cms.locales' );
        config( ['cms.locales' => [...$locales, 'zh-Hant']] );

        try {
            $variant = Variants::add( $page->id, 'zh-Hant', $this->user );
        } finally {
            config( ['cms.locales' => $locales] );
        }

        $this->assertEquals( 'zh-Hant', $variant->lang );
        $this->assertEquals( 'zh-Hant', $variant->latest->lang );
        $this->assertEquals( 'zh-Hant', PageView::language( 'zh-Hant' )->findOrFail( $page->id )->lang );
    }


    public function testAddVariantInvalidLanguage()
    {
        $this->expectException( Exception::class );
        Variants::add( $this->page()->id, 'de_DE', $this->user );
    }


    public function testAddVariantUnconfiguredLanguage()
    {
        // languages outside of "cms.locales" would become allowed for AI translations
        $this->expectException( Exception::class );
        Variants::add( $this->page()->id, 'it', $this->user );
    }


    public function testSavePageUnconfiguredLanguage()
    {
        $this->expectException( Exception::class );
        Pages::save( $this->page()->id, ['lang' => 'it'], $this->user );
    }


    public function testAddVariantExisting()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Variants::add( $page->id, 'de', $this->user );
    }


    public function testAddVariantTrashed()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::drop( $page->id, 'de', $this->user );

        try {
            Variants::add( $page->id, 'de', $this->user );
            $this->fail( 'Adding a trashed language must be refused' );
        } catch( Exception $e ) {
            $this->assertStringContainsString( 'restore', $e->getMessage() );
        }

        $this->assertEquals( 1, PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->count() );
    }


    public function testDropSourceVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Variants::drop( $page->id, 'en', $this->user );
    }


    public function testSetSourceWithoutVariant()
    {
        $page = $this->page();

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Variants::setSource( $page->id, 'de', $this->user );
    }


    public function testSetSource()
    {
        $page = $this->page();
        Pages::publishMany( [$page->id], $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::publishMany( [$page->id], 'de', $this->user );

        $result = Variants::setSource( $page->id, 'de', $this->user );

        $this->assertEquals( 'de', $result->source );
        $this->assertEquals( 'de', DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_pages' )->where( 'id', $page->id )->value( 'source' ) );
        $this->assertEquals( 'de', PageView::findOrFail( $page->id )->lang );

        $de = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $en = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'en' )->firstOrFail();
        $published = PageView::language( 'de' )->findOrFail( $page->id );
        $expected = Hashes::page( $published->only( PageVariant::SYNC_FIELDS ), $published->content, $published->meta, $published->config );

        $this->assertEquals( [], $de->hashes );
        $this->assertFalse( $de->stale );
        $this->assertEquals( $expected, $en->hashes );
        $this->assertFalse( $en->stale );
    }


    public function testSetSourceNotLinked()
    {
        $page = $this->page();
        Pages::publishMany( [$page->id], $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );

        // imported translation with another structure
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->update( ['hashes' => '{}'] );
        Variants::save( $page->id, 'fr', ['content' => [['id' => 'other', 'type' => 'text', 'data' => ['text' => 'Autre']]]], $this->user );
        Variants::publishMany( [$page->id], 'de', $this->user );

        Variants::setSource( $page->id, 'de', $this->user );

        $fr = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->firstOrFail();

        $this->assertEquals( [], $fr->hashes );
        $this->assertTrue( $fr->stale );
    }


    public function testEditorsSaveDifferentLanguages()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $enLatest = PageView::findOrFail( $page->id )->latest_id;
        $deLatest = PageView::language( 'de' )->findOrFail( $page->id )->latest_id;

        $en = Pages::save( $page->id, ['title' => 'English'], $this->user, $enLatest );
        $de = Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $this->user, $deLatest );

        $this->assertEquals( 'English', $en->latest->data->title );
        $this->assertEquals( 'en', $en->latest->lang );
        $this->assertEquals( 'Deutsch', $de->latest->data->title );
        $this->assertEquals( 'de', $de->latest->lang );

        $this->assertEquals( 'English', PageView::findOrFail( $page->id )->latest->data->title );
        $this->assertEquals( 'Deutsch', PageView::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testSaveRefusesLanguageOfOtherVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Pages::save( $page->id, ['lang' => 'de'], $this->user );
    }


    public function testSaveRefusesInvalidLanguage()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Variants::save( $page->id, 'de', ['lang' => '<x>'], $this->user );
    }


    public function testPublishLanguageChangeUpdatesSource()
    {
        $page = $this->page();
        Pages::save( $page->id, ['lang' => 'fr'], $this->user );

        Pages::publishMany( [$page->id], $this->user );

        $this->assertEquals( 'fr', Page::findOrFail( $page->id )->source );
        $this->assertEquals( 'fr', PageView::findOrFail( $page->id )->lang );
    }


    public function testPublishOneLanguageChangeUpdatesSource()
    {
        $page = $this->page();
        Pages::save( $page->id, ['lang' => 'fr'], $this->user );

        $page = PageView::with( 'latest' )->findOrFail( $page->id );
        $page->publish( $page->latest );

        $this->assertEquals( 'fr', Page::findOrFail( $page->id )->source );
        $this->assertEquals( 'fr', $page->source );
    }


    public function testAddVariantTrashedPage()
    {
        $page = $this->page();
        Pages::dropMany( [$page->id], $this->user );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Variants::add( $page->id, 'de', $this->user );
    }


    public function testIgnoreSingle()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->assertEquals( 'de', Variants::ignore( $page->id, 'de', $this->user )->lang );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Variants::ignore( $page->id, 'fr', $this->user );
    }


    public function testSaveVariantOnly()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $de = Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $this->user );

        $this->assertEquals( 'de', $de->lang );
        $this->assertEquals( 'Deutsch', $de->latest?->data->title );
        $this->assertNotEquals( 'Deutsch', PageView::with( 'latest' )->findOrFail( $page->id )->latest?->data->title );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Variants::save( $page->id, 'fr', ['title' => 'Français'], $this->user );
    }


    public function testBulkVariantSkipsMissing()
    {
        $page = $this->page();
        $other = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $result = Variants::bulk( [$page->id, $other->id], 'de', ['title' => 'Bulk DE'], $this->user );

        $this->assertEquals( [$page->id], $result->ids );
        $this->assertEquals( 'Bulk DE', PageView::language( 'de' )->with( 'latest' )->findOrFail( $page->id )->latest?->data->title );
    }


    public function testPublishVariantSingle()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $this->user );

        $this->assertEquals( 'Deutsch', Variants::publish( $page->id, 'de', $this->user )->title );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );
        Variants::publish( $page->id, 'fr', $this->user );
    }


    public function testPublishVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['title' => 'Deutsch', 'status' => 1], $this->user );

        Variants::publishMany( [$page->id], 'de', $this->user );

        $this->assertEquals( 'Deutsch', PageView::language( 'de' )->findOrFail( $page->id )->title );
        $this->assertNotEquals( 'Deutsch', PageView::findOrFail( $page->id )->title );
        $this->assertFalse( (bool) PageView::findOrFail( $page->id )->latest->published );
    }


    public function testPublishVariantAttachments()
    {
        $files = File::take( 2 )->get();
        $this->assertCount( 2, $files );

        $page = $this->page( [['id' => 'img', 'type' => 'image', 'data' => ['file' => ['id' => $files[0]->id, 'type' => 'file']]]] );
        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['content' => [
            ['id' => 'img', 'type' => 'image', 'data' => ['file' => ['id' => $files[1]->id, 'type' => 'file']]]
        ]], $this->user );

        Pages::publishMany( [$page->id], $this->user );
        Variants::publishMany( [$page->id], 'de', $this->user );

        $en = PageView::findOrFail( $page->id );
        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertEquals( [$files[0]->id], $db->table( 'cms_page_file' )->where( 'variant_id', $en->variant_id )->pluck( 'file_id' )->all() );
        $this->assertEquals( [$files[1]->id], $db->table( 'cms_page_file' )->where( 'variant_id', $de->variant_id )->pluck( 'file_id' )->all() );
    }


    public function testPublishSourceMarksStale()
    {
        $page = $this->page();
        Pages::publishMany( [$page->id], $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );

        $source = PageView::findOrFail( $page->id );
        $hashes = Hashes::page( $source->only( PageVariant::SYNC_FIELDS ), $source->content, $source->meta, $source->config );

        // de is up to date, fr ignores the title change
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )
            ->update( ['hashes' => json_encode( $hashes ), 'stale' => false] );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )
            ->update( ['hashes' => json_encode( ['page:title' => Hashes::hash( 'Changed' )] + $hashes ), 'stale' => false] );

        Pages::save( $page->id, ['title' => 'Changed'], $this->user );
        Pages::publishMany( [$page->id], $this->user );

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'stale' ) );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->value( 'stale' ) );
    }


    public function testPublishTranslationKeepsOthersFresh()
    {
        $page = $this->page();
        Pages::publishMany( [$page->id], $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->update( ['stale' => false] );

        Variants::publishMany( [$page->id], 'de', $this->user );

        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->value( 'stale' ) );
    }


    public function testPageLifecycle()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Pages::publishMany( [$page->id], $this->user );
        Variants::publishMany( [$page->id], 'de', $this->user );

        $variants = PageVariant::where( 'page_id', $page->id )->pluck( 'id' )->all();

        Pages::dropMany( [$page->id], $this->user );

        $this->assertNull( PageView::find( $page->id ) );
        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( PageView::withTrashed()->language( 'de' )->find( $page->id ) );

        Pages::restoreMany( [$page->id], $this->user );

        $this->assertNotNull( PageView::find( $page->id ) );
        $this->assertNotNull( PageView::language( 'de' )->find( $page->id ) );

        Pages::dropMany( [$page->id], $this->user );
        Pages::purgeMany( [$page->id], $this->user );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertEquals( 0, PageVariant::withTrashed()->where( 'page_id', $page->id )->count() );
        $this->assertEquals( 0, Version::whereIn( 'versionable_id', $variants )->count() );
        $this->assertEquals( 0, $db->table( 'cms_page_element' )->whereIn( 'variant_id', $variants )->count() );
        $this->assertEquals( 0, $db->table( 'cms_page_file' )->whereIn( 'variant_id', $variants )->count() );
    }


    public function testPageRestoreKeepsTrashedVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );
        Variants::drop( $page->id, 'de', $this->user );

        Pages::dropMany( [$page->id], $this->user );
        Pages::restoreMany( [$page->id], $this->user );

        $this->assertNotNull( PageView::find( $page->id ) );
        $this->assertNotNull( PageView::language( 'fr' )->find( $page->id ) );
        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( PageView::language( 'de', true )->find( $page->id ) );
    }


    public function testVariantLifecycle()
    {
        $page = $this->page();
        Pages::publishMany( [$page->id], $this->user );
        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $this->user );

        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        PageVariant::whereKey( $variant->id )->update( ['stale' => false] );

        $dropped = Variants::drop( $page->id, 'de', $this->user );

        $this->assertNotNull( $dropped->variant_deleted_at );
        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
        $this->assertNotNull( PageView::find( $page->id ) );
        $this->assertEquals( 2, Version::where( 'versionable_id', $variant->id )->count() );

        Variants::restore( $page->id, 'de', $this->user );

        $restored = PageView::language( 'de' )->findOrFail( $page->id );
        $this->assertEquals( 'Deutsch', $restored->latest->data->title );
        $this->assertTrue( PageVariant::whereKey( $variant->id )->value( 'stale' ) );

        Variants::drop( $page->id, 'de', $this->user );
        $purged = Variants::purge( $page->id, 'de', $this->user );

        $this->assertEquals( $page->id, $purged->id );
        $this->assertEquals( 0, PageVariant::withTrashed()->whereKey( $variant->id )->count() );
        $this->assertEquals( 0, Version::where( 'versionable_id', $variant->id )->count() );
        $this->assertNotNull( PageView::find( $page->id ) );
    }


    public function testVariantsFields()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        // only the required columns and the requested fields are loaded
        $dropped = Variants::dropMany( [$page->id], 'de', $this->user, ['id', 'name'] )->firstOrFail();

        $this->assertNotNull( $dropped->variant_deleted_at );
        $this->assertNotNull( $dropped->name );
        $this->assertArrayNotHasKey( 'content', $dropped->getAttributes() );

        $restored = Variants::restoreMany( [$page->id], 'de', $this->user, ['id', 'title'] )->firstOrFail();

        $this->assertNull( $restored->variant_deleted_at );
        $this->assertNotNull( $restored->title );
        $this->assertArrayNotHasKey( 'content', $restored->getAttributes() );

        $ignored = Variants::ignoreMany( [$page->id], 'de', $this->user, ['id'] )->firstOrFail();

        $this->assertEquals( 'de', $ignored->lang );
        $this->assertArrayNotHasKey( 'content', $ignored->getAttributes() );

        $purged = Variants::purgeMany( [$page->id], 'de', $this->user, ['id', 'path'] )->firstOrFail();

        $this->assertFalse( $purged->exists );
        $this->assertNotNull( $purged->path );
        $this->assertArrayNotHasKey( 'content', $purged->getAttributes() );
        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
    }


    public function testPurgeSourceVariant()
    {
        $page = $this->page();

        // source variants are skipped
        $this->assertCount( 0, Variants::purgeMany( [$page->id], 'en', $this->user ) );
        $this->assertNotNull( PageView::language( 'en' )->find( $page->id ) );
    }


    public function testPurgeSourceVariantStrict()
    {
        $page = $this->page();

        $this->expectException( Exception::class );
        Variants::purge( $page->id, 'en', $this->user );
    }


    public function testPurgeKeepsOtherTenants()
    {
        \Aimeos\Cms\Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        try {
            $root = Pages::add( ['lang' => 'en', 'name' => 'Other', 'title' => 'Other', 'path' => 'other'] );
            $child = Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'other-child'], parent: $root->id );
        } finally {
            \Aimeos\Cms\Tenancy::$callback = fn() => 'test';
            app()->forgetScopedInstances();
        }

        $root = $this->root();
        $this->assertGreaterThanOrEqual( $root->_lft, $child->_lft );
        $this->assertLessThanOrEqual( $root->_rgt, $child->_rgt );

        Pages::purgeMany( [$root->id], $this->user );

        $this->assertEquals( 0, PageView::withTrashed()->count() );
        $this->assertEquals( 2, PageView::withoutTenancy()->withTrashed()->where( 'tenant_id', 'other' )->count() );
        $this->assertEquals( 2, PageVariant::withoutTenancy()->withTrashed()->where( 'tenant_id', 'other' )->count() );
    }


    public function testPurgeSubtreesKeepsTree()
    {
        $root = $this->root();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name, 'path' => $name], $this->user, parent: $parent );

        $first = $add( 'purge-first', $root->id );
        $child = $add( 'purge-child', $first->id );
        $kept = $add( 'purge-kept', $root->id );
        $last = $add( 'purge-last', $root->id );
        $count = Page::withTrashed()->count();

        // the nested child is removed together with its parent already
        Pages::purgeMany( [$last->id, $child->id, $first->id], $this->user );

        $this->assertFalse( Page::withTrashed()->isBroken() );
        $this->assertEquals( $count - 3, Page::withTrashed()->count() );
        $this->assertEquals( 0, PageVariant::withTrashed()->whereIn( 'page_id', [$first->id, $child->id, $last->id] )->count() );
        $this->assertNotNull( PageView::find( $kept->id ) );
        $this->assertEquals( $root->id, Page::findOrFail( $kept->id )->parent_id );
    }


    public function testTrustedTreeChangesKeepTree()
    {
        $root = $this->root();
        $add = fn( string $name, ?string $parent = null, ?string $ref = null ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name, 'path' => $name], $this->user, $ref, $parent );

        $first = $add( 'trusted-first', $root->id );
        $child = $add( 'trusted-child', $first->id );
        $second = $add( 'trusted-second', $root->id );
        $before = $add( 'trusted-before', ref: $first->id );

        // subtree moves to the right and to the left of the original position
        Pages::move( $first->id, $this->user, parent: $second->id );
        Pages::move( $child->id, $this->user, ref: $before->id );
        Pages::move( $first->id, $this->user, parent: $root->id );
        Pages::copy( $second->id, $this->user, ref: $first->id );

        $this->assertFalse( Page::withTrashed()->isBroken() );
        $this->assertEquals( $root->id, Page::findOrFail( $first->id )->parent_id );
        $this->assertEquals( $root->id, Page::findOrFail( $child->id )->parent_id );

        // positions are re-read again outside of the trusted scope
        $node = Page::findOrFail( $second->id );
        Page::query()->whereKey( $node->id )->update( ['_rgt' => 0] );
        $node->refreshNode();

        $this->assertEquals( 0, $node->getRgt() );
    }


    public function testDropRestoreSubtrees()
    {
        $root = $this->root();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name, 'path' => $name], $this->user, parent: $parent );
        $trashed = fn( PageView ...$pages ) => Page::onlyTrashed()->whereKey( array_map( fn( $page ) => $page->id, $pages ) )->count();

        $first = $add( 'subtree-first', $root->id );
        $child = $add( 'subtree-child', $first->id );
        $leaf = $add( 'subtree-leaf', $child->id );
        $second = $add( 'subtree-second', $root->id );

        Pages::dropMany( [$leaf->id], $this->user );
        $this->travel( 2 )->seconds();

        // the nested child is dropped together with its parent and the trashed leaf keeps its time
        $items = Pages::dropMany( [$second->id, $child->id, $first->id], $this->user );

        $this->assertEquals( 4, $trashed( $first, $child, $leaf, $second ) );
        $this->assertTrue( $items->every( fn( PageView $item ) => $item->trashed() ) );
        $this->assertLessThan( Page::withTrashed()->findOrFail( $first->id )->deleted_at, Page::withTrashed()->findOrFail( $leaf->id )->deleted_at );

        // the leaf trashed before its ancestors stays in the trash
        $items = Pages::restoreMany( [$first->id, $second->id], $this->user );

        $this->assertEquals( 1, $trashed( $first, $child, $leaf, $second ) );
        $this->assertEquals( 1, $trashed( $leaf ) );
        $this->assertFalse( $items->contains( fn( PageView $item ) => $item->trashed() ) );

        // pages trashed before are restored if passed together with their ancestors
        Pages::dropMany( [$first->id], $this->user );
        Pages::restoreMany( [$first->id, $leaf->id], $this->user );

        $this->assertEquals( 0, $trashed( $first, $child, $leaf, $second ) );
        $this->assertFalse( Page::withTrashed()->isBroken() );
    }


    public function testAddVariantReturnsStoredView()
    {
        $page = $this->page();
        $de = Variants::add( $page->id, 'de', $this->user );
        $stored = PageView::language( 'de' )->findOrFail( $page->id );

        $keys = ['id', 'variant_id', 'variant_deleted_at', 'parent_id', 'source', 'lang', 'domain', 'path', 'to', 'name', 'title',
            'type', 'theme', 'tag', 'cache', 'status', 'meta', 'config', 'content', 'hashes', 'stale', 'latest_id', 'editor',
            '_lft', '_rgt', 'depth', 'deleted_at'];

        $this->assertEquals( $stored->only( $keys ), $de->only( $keys ) );
        $this->assertTrue( $de->exists );
    }


    public function testPruneVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );
        Variants::drop( $page->id, 'de', $this->user );

        $variant = PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->travel( config( 'cms.prune', 30 ) + 1 )->days();
        $this->artisan( 'model:prune', ['--model' => [PageVariant::class]] )->assertSuccessful();

        $this->assertEquals( 0, PageVariant::withTrashed()->whereKey( $variant->id )->count() );
        $this->assertEquals( 0, Version::where( 'versionable_id', $variant->id )->count() );
        $this->assertNotNull( PageView::find( $page->id ) );
        $this->assertNotNull( PageView::language( 'fr' )->find( $page->id ) );
    }


    public function testPrunePageTenant()
    {
        $count = PageView::withTrashed()->count();

        $ids = \Aimeos\Cms\Tenancy::run( 'other', function() {
            $root = Pages::add( ['name' => 'Root', 'path' => 'other-root'] );
            $child = Pages::add( ['name' => 'Child', 'path' => 'other-child'], parent: $root->id );
            $sub = Pages::add( ['name' => 'Sub', 'path' => 'other-sub'], parent: $child->id );
            Pages::dropMany( [$child->id] );

            return [$root->id, $child->id, $sub->id];
        } );

        $this->travel( config( 'cms.prune', 30 ) + 1 )->days();
        $this->artisan( 'model:prune', ['--model' => [\Aimeos\Cms\Models\Page::class]] )->assertSuccessful();

        // pages of other tenants with overlapping nested set values are untouched
        $this->assertEquals( $count, PageView::withTrashed()->count() );
        $this->assertEquals( [$ids[0]], PageView::withoutTenancy()->withTrashed()->where( 'tenant_id', 'other' )->pluck( 'id' )->all() );
        $this->assertEquals( 0, PageVariant::withoutTenancy()->withTrashed()->whereIn( 'page_id', array_slice( $ids, 1 ) )->count() );
        $this->assertEquals( 0, Version::withoutTenancy()->whereIn( 'versionable_id', array_slice( $ids, 1 ) )->count() );
    }


    public function testPrunableSkipsDescendantsOfExpiredPages()
    {
        $parent = Pages::add( ['name' => 'Parent', 'path' => 'prunable-parent'], $this->user );
        $child = Pages::add( ['name' => 'Child', 'path' => 'prunable-child'], $this->user, parent: $parent->id );
        Pages::add( ['name' => 'Sub', 'path' => 'prunable-sub'], $this->user, parent: $child->id );
        Pages::dropMany( [$parent->id], $this->user );

        $this->travel( config( 'cms.prune', 30 ) + 1 )->days();

        $this->assertEquals( [$parent->id], ( new \Aimeos\Cms\Models\Page() )->prunable()->withTrashed()->pluck( 'id' )->all() );
    }


    public function testPrunePageWithTrashedChild()
    {
        $parent = Pages::add( ['name' => 'Parent', 'path' => 'prune-parent'], $this->user );
        Pages::add( ['name' => 'Child', 'path' => 'prune-child'], $this->user, parent: $parent->id );
        $next = Pages::add( ['name' => 'Next', 'path' => 'prune-next'], $this->user );
        $sub = Pages::add( ['name' => 'Sub', 'path' => 'prune-sub'], $this->user, parent: $next->id );
        Variants::add( $sub->id, 'de', $this->user );

        Pages::dropMany( [$parent->id], $this->user );
        $count = PageVariant::where( 'page_id', $sub->id )->count();

        $this->travel( config( 'cms.prune', 30 ) + 1 )->days();
        $this->artisan( 'model:prune', ['--model' => [\Aimeos\Cms\Models\Page::class]] )->assertSuccessful();

        // stale tree positions of pruned descendants must not remove live pages
        $this->assertEquals( $count, PageVariant::where( 'page_id', $sub->id )->count() );
        $this->assertEquals( 0, \Aimeos\Cms\Models\Page::countErrors()['oddness'] ?? 0 );

        $next = PageView::findOrFail( $next->id );
        $sub = PageView::findOrFail( $sub->id );
        $this->assertTrue( $next->_lft < $sub->_lft && $sub->_rgt < $next->_rgt );
    }


    public function testDropAndPurgeInvalidateVariantUrls()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        $de = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $paths = [];
        Event::listen( PageInvalidated::class, function( PageInvalidated $event ) use ( &$paths ) {
            array_push( $paths, ...$event->paths );
        } );

        Pages::dropMany( [$page->id], $this->user );

        $this->assertContains( $page->path, $paths );
        $this->assertContains( $de->path, $paths );

        $paths = [];
        Pages::purgeMany( [$page->id], $this->user );

        $this->assertContains( $page->path, $paths );
        $this->assertContains( $de->path, $paths );
    }


    public function testPurgeAnnouncesLanguageOfVariantsOnly()
    {
        $page = $this->page();
        $other = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $events = [];
        Event::listen( Bulk::class, function( Bulk $event ) use ( &$events ) {
            $events[] = $event;
        } );

        // the language marks events of single variants, whole pages have none
        Variants::purgeMany( [$page->id], 'de', $this->user );
        Pages::purgeMany( [$page->id, $other->id], $this->user );

        $this->assertCount( 2, $events );
        $this->assertEquals( ['purged', 'purged'], array_map( fn( Bulk $event ) => $event->action, $events ) );
        $this->assertEquals( [[$page->id => 'de'], []], array_map( fn( Bulk $event ) => $event->langs, $events ) );
    }


    public function testAnnounceManyGroupsVariantsByLanguage()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $events = [];
        Event::listen( Bulk::class, function( Bulk $event ) use ( &$events ) {
            $events[] = $event;
        } );

        $items = collect( [PageView::findOrFail( $page->id ), PageView::language( 'de', true )->findOrFail( $page->id )] );
        PageView::announceMany( $items, ContentAction::Published, 'editor', ['published' => true], true );

        $this->assertCount( 2, $events );
        $this->assertEquals( [[$page->id => 'en'], [$page->id => 'de']], array_map( fn( Bulk $event ) => $event->langs, $events ) );
        $this->assertEquals( [[$page->id], [$page->id]], array_map( fn( Bulk $event ) => $event->ids, $events ) );
    }


    public function testCopyPage()
    {
        $page = $this->page();
        $child = Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid(),
            'content' => [['id' => 'el2', 'type' => 'text', 'data' => ['text' => 'Child']]],
        ], $this->user, parent: $page->id );

        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $child->id, 'de', $this->user );

        $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );

        $this->assertNotEquals( $page->id, $copy->id );
        $this->assertEquals( 'en', $copy->source );
        $this->assertEquals( 2, PageVariant::where( 'page_id', $copy->id )->count() );
        $this->assertNotEquals( $page->path, $copy->path );

        // copies are disabled until they are published
        $this->assertEquals( [0, 0], PageVariant::where( 'page_id', $copy->id )->pluck( 'status' )->all() );
        $this->assertEquals( 0, $copy->status );

        $orig = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $copy->id );
        $variant = PageVariant::findOrFail( $de->variant_id );

        $this->assertEquals( $orig->hashes, $variant->hashes );
        $this->assertEquals( 'el1', $de->latest->aux->content[0]->id );
        $this->assertEquals( 1, Version::where( 'versionable_id', $de->variant_id )->count() );

        $children = PageView::where( 'parent_id', $copy->id )->get();
        $this->assertCount( 1, $children );
        $this->assertEquals( 2, PageVariant::where( 'page_id', $children[0]->id )->count() );
        $this->assertEquals( 'el2', PageView::language( 'de' )->findOrFail( $children[0]->id )->latest->aux->content[0]->id );
    }


    public function testCopyPageResetsScheduled()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        // translations are copied in bulk without firing the "saving" event of the versions
        $orig = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $version = Version::findOrFail( $orig->latest_id );
        $version->forceFill( ['publish_at' => now()->addDay()] )->save();

        $this->assertEquals( 1, $version->fresh()?->data->scheduled );

        $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );
        $latest = PageView::language( 'de' )->findOrFail( $copy->id )->latest;

        $this->assertNull( $latest?->publish_at );
        $this->assertEquals( 0, $latest?->data->scheduled );
    }


    public function testCopyPageSubtree()
    {
        $page = $this->page();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name,
            'path' => strtolower( $name ) . '-' . Utils::uid()], $this->user, parent: $parent );

        $first = $add( 'First', $page->id );
        $add( 'Deep', $first->id );
        $add( 'Second', $page->id );
        $next = $this->page();

        // copied before a following page to check that the nodes after the gap are moved
        $copy = Pages::copy( $page->id, $this->user, ref: $next->id );

        $this->assertFalse( Page::withTrashed()->isBroken() );

        $tree = PageView::select( 'id', 'parent_id', 'name', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( NestedSet::LFT, '>=', $copy->fresh()?->getLft() )
            ->where( NestedSet::RGT, '<=', $copy->fresh()?->getRgt() )
            ->orderBy( NestedSet::LFT )->get();

        $this->assertEquals( ['Test', 'First', 'Deep', 'Second'], $tree->pluck( 'name' )->all() );
        $this->assertEquals( [$copy->id, $tree[1]->id, $copy->id], $tree->slice( 1 )->pluck( 'parent_id' )->all() );
        $this->assertEquals( [0, 1, 2, 1], $tree->map( fn( $p ) => $p->getDepth() - $copy->fresh()?->getDepth() )->all() );
        $this->assertLessThan( $next->fresh()?->getLft(), $copy->fresh()?->getRgt() );
    }


    public function testCopyPageSkipsOrphans()
    {
        $page = $this->page();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name,
            'path' => strtolower( $name ) . '-' . Utils::uid()], $this->user, parent: $parent );

        $first = $add( 'First', $page->id );
        $add( 'Deep', $first->id );
        $add( 'Second', $page->id );

        // a page without variants isn't copied, so its sub-pages must not be copied loose
        PageVariant::where( 'page_id', $first->id )->delete();

        $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );

        $this->assertFalse( Page::withTrashed()->isBroken() );
        $this->assertEquals( ['Second'], PageView::where( 'parent_id', $copy->id )->pluck( 'name' )->all() );
        $this->assertEquals( 1, PageView::where( 'name', 'Deep' )->count() );
        $this->assertEquals( 0, PageView::where( 'parent_id', $this->root()->id )->where( 'name', 'Deep' )->count() );
    }


    public function testCopyPageRootWithoutVariants()
    {
        $page = $this->page();
        Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid()],
            $this->user, parent: $page->id );

        PageVariant::where( 'page_id', $page->id )->delete();

        $this->expectException( \Aimeos\Cms\Exception::class );
        Pages::copy( $page->id, $this->user, parent: $this->root()->id );
    }


    public function testCopyPageAccess()
    {
        $page = $this->page();
        $child = Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child-' . Utils::uid()],
            $this->user, parent: $page->id );
        $open = Pages::add( ['lang' => 'en', 'name' => 'Open', 'title' => 'Open', 'path' => 'open-' . Utils::uid()],
            $this->user, parent: $page->id );

        Access::using( fn() => ['admins', 'members'] );

        try {
            PageAccess::set( [$page->id], ['members'] );
            PageAccess::set( [$child->id], ['admins', 'members'] );
        } finally {
            Access::using( null );
        }

        $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );
        $children = PageView::where( 'parent_id', $copy->id )->orderBy( NestedSet::LFT )->pluck( 'id' )->all();
        $values = fn( string $id ) => PageAccess::where( 'page_id', $id )->orderBy( 'value' )->pluck( 'value' )->all();

        $this->assertCount( 2, $children );
        $this->assertEquals( ['members'], $values( $copy->id ) );
        $this->assertEquals( ['admins', 'members'], $values( $children[0] ) );
        $this->assertEquals( [], $values( $children[1] ) );
        $this->assertEquals( [], $values( $open->id ) );
    }


    public function testCopyPageRepeated()
    {
        $page = $this->page();
        $child = Pages::add( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child_%*?[-' . Utils::uid()],
            $this->user, parent: $page->id );
        Variants::add( $page->id, 'de', $this->user );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $paths = [];

        for( $i = 0; $i < 3; $i++ )
        {
            $db->flushQueryLog();
            $db->enableQueryLog();

            $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );

            $db->disableQueryLog();

            // the used paths are fetched by one query, not once per tried path
            $queries = array_filter( $db->getQueryLog(), fn( $q ) => preg_match( '/^select .*from ["`\[]cms_page_variants["`\]] where .*["`\[]path["`\]]/', $q['query'] ) );
            $this->assertCount( 1, $queries );

            $paths[] = PageVariant::where( 'page_id', $copy->id )->orderBy( 'lang' )->pluck( 'path' )->all();
            $paths[] = PageView::where( 'parent_id', $copy->id )->firstOrFail()->path;
        }

        $base = $page->path;
        $de = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'path' );

        $this->assertEquals( [
            [$de . '-de', $base . '-en'], $child->path . '-en',
            [$de . '-de-2', $base . '-en-2'], $child->path . '-en-2',
            [$de . '-de-3', $base . '-en-3'], $child->path . '-en-3',
        ], $paths );
    }


    public function testCopyPageChunks()
    {
        $page = $this->page();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name,
            'path' => strtolower( $name ) . '-' . Utils::uid(),
            'content' => [['id' => 'el' . $name, 'type' => 'text', 'data' => ['text' => $name]]],
        ], $this->user, parent: $parent );

        $first = $add( 'First', $page->id );

        // more pages than copied per chunk
        for( $i = 0; $i < 55; $i++ ) {
            $last = $add( 'Child' . $i, $first->id );
        }

        $deep = $add( 'Deep', $last->id );
        Variants::add( $deep->id, 'de', $this->user );

        $added = [];
        Event::listen( \Aimeos\Cms\Events\Added::class, function( $event ) use ( &$added ) { $added[] = $event->id; } );

        $copy = Pages::copy( $page->id, $this->user, parent: $this->root()->id );

        $this->assertFalse( Page::withTrashed()->isBroken() );

        $tree = PageView::select( 'id', 'parent_id', 'name', 'latest_id', NestedSet::LFT, NestedSet::RGT, NestedSet::DEPTH )
            ->where( NestedSet::LFT, '>', $copy->fresh()?->getLft() )
            ->where( NestedSet::RGT, '<', $copy->fresh()?->getRgt() )
            ->orderBy( NestedSet::LFT )->get();

        $this->assertCount( 57, $tree );
        $this->assertEquals( [$copy->id, ...$tree->pluck( 'id' )->all()], $added );
        $this->assertEquals( ['First', 'Child0', 'Child54', 'Deep'], [$tree[0]->name, $tree[1]->name, $tree[55]->name, $tree[56]->name] );
        $this->assertEquals( $tree[56]->parent_id, $tree[55]->id );
        $this->assertEquals( 3, $tree[56]->getDepth() - $copy->fresh()?->getDepth() );
        $this->assertEquals( 'elChild54', $tree[55]->latest?->aux->content[0]->id );

        $de = PageView::language( 'de' )->findOrFail( $tree[56]->id );
        $this->assertEquals( 'elDeep', $de->latest?->aux->content[0]->id );
        $this->assertEquals( 1, Version::where( 'versionable_id', $de->variant_id )->count() );
    }


    public function testSubtreePrunesHiddenBranches()
    {
        $page = $this->page();
        $add = fn( string $name, string $parent ) => Pages::add( ['lang' => 'en', 'name' => $name, 'title' => $name,
            'path' => strtolower( $name ) . '-' . Utils::uid()], $this->user, parent: $parent );

        $first = $add( 'First', $page->id );
        $deep = $add( 'Deep', $first->id );
        $second = $add( 'Second', $page->id );
        $add( 'Inner', $second->id );

        PageVariant::whereIn( 'page_id', [$page->id, $first->id, $deep->id] )->update( ['status' => 1] );
        $names = fn( $page ) => $page->subtree->pluck( 'name' )->all();

        // disabled pages are left out together with their sub-pages
        $this->assertEquals( ['First', 'Deep'], $names( PageView::findOrFail( $page->id ) ) );

        // eager loading limits the depth relative to the tree root
        $eager = $names( PageView::with( 'subtree' )->findOrFail( $page->id ) );
        $this->assertContains( 'First', $eager );
        $this->assertNotContains( 'Second', $eager );
        $this->assertNotContains( 'Inner', $eager );

        // sub-pages of a hidden page without variant in the language are hidden too
        foreach( [$page->id, $deep->id] as $id ) {
            Variants::add( $id, 'de', $this->user );
        }

        PageVariant::where( 'lang', 'de' )->update( ['status' => 1] );
        $this->assertEquals( [], $names( PageView::language( 'de' )->findOrFail( $page->id ) ) );

        // nothing is shown below a disabled page
        PageVariant::where( 'page_id', $page->id )->update( ['status' => 0] );
        $this->assertEquals( [], $names( PageView::findOrFail( $page->id ) ) );
    }


    public function testFallbackLanguage()
    {
        $page = $this->page();
        $other = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $ids = [$page->id, $other->id];
        $langs = fn( bool $trashed = false ) => PageView::fallback( 'de', $trashed )->withTrashed()->whereIn( 'id', $ids )
            ->get()->pluck( 'lang', 'id' )->all();

        $this->assertEquals( [$page->id => 'de', $other->id => 'en'], $langs() );

        // a trashed variant falls back to the source variant unless trashed ones are requested
        Variants::drop( $page->id, 'de', $this->user );

        $this->assertEquals( [$page->id => 'en', $other->id => 'en'], $langs() );
        $this->assertEquals( [$page->id => 'de', $other->id => 'en'], $langs( true ) );
    }


    public function testTreeQueriesKeepLanguage()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        // the nested set builds subqueries on the cms_pages table which must not get the variant condition
        $this->assertEquals( [$page->id], PageView::language( 'de' )->has( 'ancestors' )->pluck( 'id' )->all() );
        $this->assertEquals( [$page->id], PageView::language( 'de' )->whereDescendantOf( $this->root()->id )->pluck( 'id' )->all() );
        $this->assertEquals( 0, PageView::language( 'de' )->whereAncestorOf( $page->id )->count() );
        $this->assertEquals( 1, PageView::fallback( 'de' )->whereAncestorOf( $page->id )->count() );
    }


    public function testSearchIndexesAllVariants()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $source = PageView::findOrFail( $page->id );
        $variant = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertTrue( $source->shouldBeSearchable() );
        $this->assertTrue( $variant->shouldBeSearchable() );
        $this->assertEquals( $source->variant_id, $source->getScoutKey() );
        $this->assertEquals( $variant->variant_id, $variant->getScoutKey() );
        $this->assertNotEquals( $source->getScoutKey(), $variant->getScoutKey() );
    }


    protected function page( ?array $content = null ) : PageView
    {
        return Pages::add( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'var-' . Utils::uid(),
            'content' => $content ?? [['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']]],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : PageView
    {
        return PageView::where( 'tag', 'root' )->firstOrFail();
    }
}
