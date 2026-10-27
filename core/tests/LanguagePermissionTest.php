<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Exceptions\PermissionException;
use Aimeos\Cms\Facades\Elements;
use Aimeos\Cms\Facades\Files;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Permission;
use Database\Seeders\TestSeeder;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\RefreshDatabase;


class LanguagePermissionTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actor( null );
    }


    public function testLangs()
    {
        $this->assertNull( Permission::langs( $this->actor( null ) ) );
        $this->assertEquals( ['de'], Permission::langs( $this->actor( ['de'] ) ) );
        $this->assertSame( [], Permission::langs( $this->actor( [] ) ) );
        $this->assertSame( [], Permission::langs( $this->raw( 'null' ) ) );
        $this->assertSame( [], Permission::langs( $this->raw( '{"de"' ) ) );
        $this->assertSame( [], Permission::langs( $this->raw( '{"a":"de"}' ) ) );
    }


    public function testLangsMissingAttribute()
    {
        $this->assertSame( [], Permission::langs( new GenericUser( ['id' => 1, 'cmsperms' => Permission::all()] ) ) );
        $this->assertNull( Permission::langs( new GenericUser( ['id' => 1, 'cmslangs' => null] ) ) );
    }


    public function testLangsWithoutUser()
    {
        $this->assertSame( [], Permission::langs( null ) );
        $this->assertNull( Permission::system( fn() => Permission::langs( null ) ) );
    }


    public function testLangsCaseSensitive()
    {
        $this->assertFalse( Permission::writable( 'de-CH', $this->actor( ['de-ch'] ) ) );
        $this->assertTrue( Permission::writable( 'de-CH', $this->actor( ['de-CH'] ) ) );
        $this->assertFalse( Permission::writable( null, $this->actor( ['de'] ) ) );
        $this->assertTrue( Permission::writable( null, $this->actor( null ) ) );
    }


    public function testVariantActionsImpliedByPage()
    {
        $page = $this->actor( null, ['page:add', 'page:drop'] );
        $variant = $this->actor( null, ['variant:add', 'variant:drop'] );

        $this->assertTrue( Permission::can( 'variant:add', $page ) );
        $this->assertTrue( Permission::can( 'variant:drop', $page ) );
        $this->assertFalse( Permission::can( 'variant:purge', $page ) );

        $this->assertTrue( Permission::can( 'variant:add', $variant ) );
        $this->assertFalse( Permission::can( 'page:add', $variant ) );
        $this->assertFalse( Permission::can( 'page:drop', $variant ) );
    }


    public function testSetKeepsLangs()
    {
        $user = $this->persisted( ['de'] );

        Permission::set( $user, ['page:view'] );
        $this->assertEquals( ['de'], Permission::langs( $user->fresh() ) );

        Permission::set( $user, ['page:view'], ['fr'] );
        $this->assertEquals( ['fr'], Permission::langs( $user->fresh() ) );

        Permission::set( $user, ['page:view'], null );
        $this->assertNull( Permission::langs( $user->fresh() ) );
    }


    public function testAddVariant()
    {
        $page = $this->page();
        $user = $this->actor( ['de'] );

        $this->assertEquals( 'de', Variants::add( $page->id, 'de', $user )->lang );

        $this->expectException( PermissionException::class );
        Variants::add( $page->id, 'fr', $user );
    }


    public function testVariantActionsOnly()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $user = $this->actor( ['de'], ['page:view', 'page:save', 'variant:add', 'variant:drop', 'variant:keep', 'variant:purge'] );

        Variants::drop( $page->id, 'de', $user );
        $this->assertNotNull( PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->deleted_at );

        Variants::restore( $page->id, 'de', $user );
        $this->assertNull( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->deleted_at );

        $this->expectException( PermissionException::class );
        Pages::drop( $page->id, $user );
    }


    public function testVariantLifecycleUnassigned()
    {
        $page = $this->page();
        Variants::add( $page->id, 'fr', $this->user );

        $user = $this->actor( ['de'] );

        foreach( ['drop', 'restore', 'purge'] as $method )
        {
            try {
                Variants::$method( $page->id, 'fr', $user );
                $this->fail( 'Expected permission exception for ' . $method );
            } catch( PermissionException $e ) {
                $this->assertStringContainsString( 'language', $e->getMessage() );
            }
        }

        $this->assertNotNull( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->first() );
    }


    public function testSaveVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );

        $user = $this->actor( ['de'] );

        $this->assertEquals( 'Deutsch', Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $user )->latest->data->title );

        $this->expectException( PermissionException::class );
        Variants::save( $page->id, 'fr', ['title' => 'Français'], $user );
    }


    public function testSaveSourceVariantUnassigned()
    {
        $page = $this->page();

        $this->expectException( PermissionException::class );
        Pages::save( $page->id, ['title' => 'English'], $this->actor( ['de'] ) );
    }


    public function testSaveDomainChange()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $user = $this->actor( ['de'] );
        $domain = (string) PageView::language( 'de' )->findOrFail( $page->id )->domain;

        Variants::save( $page->id, 'de', ['title' => 'Deutsch', 'domain' => $domain], $user );

        $this->expectException( PermissionException::class );
        $this->expectExceptionMessage( 'Insufficient permissions for all languages' );
        Variants::save( $page->id, 'de', ['domain' => 'other.example.com'], $user );
    }


    public function testSaveUpperCaseDomain()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['domain' => 'Legacy.Example.COM'] );

        // the validated input is lower case
        $result = Variants::save( $page->id, 'de', ['title' => 'Deutsch', 'domain' => 'Legacy.Example.COM'], $this->actor( ['de'] ) );

        $this->assertEquals( 'Deutsch', $result->latest->data->title );
    }


    public function testSaveDraftLanguageUnassigned()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        // the latest draft of the "de" variant has another language
        $variant = PageView::language( 'de' )->findOrFail( $page->id );
        $variant->latest->forceFill( ['lang' => 'fr'] )->save();

        $this->expectException( PermissionException::class );
        $this->expectExceptionMessage( 'Insufficient permissions for language "fr"' );
        Variants::save( $page->id, 'de', ['title' => 'Deutsch'], $this->actor( ['de'] ) );
    }


    public function testBulkSkipsUnassigned()
    {
        $en = $this->page();
        $de = Pages::add( [
            'lang' => 'de', 'name' => 'Seite', 'title' => 'Seite', 'path' => 'seite-' . \Illuminate\Support\Str::random( 8 ),
        ], $this->user, parent: $this->root()->id );

        $result = Pages::bulk( [$en->id, $de->id], ['status' => 1], $this->actor( ['de'] ) );

        $this->assertEquals( [$de->id], $result->ids );
        $this->assertEquals( 1, $result->failed );
        $this->assertNotEquals( 1, PageView::findOrFail( $en->id )->latest->data->status ?? null );
    }


    public function testBulkLanguageUnassigned()
    {
        $page = $this->page();
        Variants::add( $page->id, 'fr', $this->user );

        $this->expectException( PermissionException::class );
        Variants::bulk( [$page->id], 'fr', ['status' => 1], $this->actor( ['de'] ) );
    }


    public function testBulkDomainRequiresUnrestricted()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( PermissionException::class );
        Variants::bulk( [$page->id], 'de', ['domain' => 'example.com'], $this->actor( ['de'] ) );
    }


    public function testAddPage()
    {
        $user = $this->actor( ['de'] );
        $page = Pages::add( [
            'lang' => 'de', 'name' => 'Seite', 'title' => 'Seite', 'path' => 'seite-test',
        ], $user, parent: $this->root()->id );

        $this->assertEquals( 'de', $page->lang );
        $this->assertEquals( (string) $this->root()->domain, (string) $page->domain );

        $this->expectException( PermissionException::class );
        Pages::add( ['lang' => 'fr', 'name' => 'Page', 'title' => 'Page', 'path' => 'page-fr'], $user, parent: $this->root()->id );
    }


    public function testAddPageDomain()
    {
        $this->expectException( PermissionException::class );
        $this->expectExceptionMessage( 'Insufficient permissions for all languages' );

        Pages::add( [
            'lang' => 'de', 'name' => 'Seite', 'title' => 'Seite', 'path' => 'seite-test', 'domain' => 'other.example.com',
        ], $this->actor( ['de'] ), parent: $this->root()->id );
    }


    public function testSetSourceRequiresUnrestricted()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $this->expectException( PermissionException::class );
        Variants::setSource( $page->id, 'de', $this->actor( ['de', 'en'] ) );
    }


    public function testPublishVariant()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::add( $page->id, 'fr', $this->user );

        $user = $this->actor( ['de'] );
        Variants::publish( $page->id, 'de', $user );

        $this->expectException( PermissionException::class );
        Variants::publish( $page->id, 'fr', $user );
    }


    public function testPublishUnpublishedDependency()
    {
        $element = Elements::add( ['type' => 'text', 'lang' => 'fr', 'data' => ['text' => 'Bonjour']], $this->user );
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );
        Variants::save( $page->id, 'de', ['content' => [['type' => 'reference', 'refid' => $element->id, 'group' => 'main']]], $this->user );

        $this->expectException( PermissionException::class );
        $this->expectExceptionMessage( 'Insufficient permissions for language "fr"' );
        Variants::publish( $page->id, 'de', $this->actor( ['de'] ) );
    }


    public function testReferenceAnyLanguage()
    {
        $page = $this->page();
        Variants::add( $page->id, 'de', $this->user );

        $de = Elements::add( ['type' => 'text', 'lang' => 'de', 'data' => ['text' => 'Hallo']], $this->user );
        $fr = Elements::add( ['type' => 'text', 'lang' => 'fr', 'data' => ['text' => 'Bonjour']], $this->user );
        $none = Elements::add( ['type' => 'text', 'data' => ['text' => 'Neutral']], $this->user );

        // references don't write the referenced items, publishing them is checked instead
        $result = Variants::save( $page->id, 'de', ['content' => [
            ['type' => 'reference', 'refid' => $de->id, 'group' => 'main'],
            ['type' => 'reference', 'refid' => $fr->id, 'group' => 'main'],
            ['type' => 'reference', 'refid' => $none->id, 'group' => 'main'],
        ]], $this->actor( ['de'] ) );

        $this->assertEqualsCanonicalizing( [$de->id, $fr->id, $none->id], $result->latest->elements()->pluck( 'cms_elements.id' )->all() );
    }


    public function testElements()
    {
        $user = $this->actor( ['de'] );
        $element = Elements::add( ['type' => 'text', 'lang' => 'de', 'data' => ['text' => 'Hallo']], $user );

        $this->assertEquals( 'Welt', Elements::save( $element->id, ['data' => ['text' => 'Welt']], $user )->latest->data->data->text );

        foreach( [
            fn() => Elements::add( ['type' => 'text', 'data' => ['text' => 'Neutral']], $user ),
            fn() => Elements::add( ['type' => 'text', 'lang' => 'fr', 'data' => ['text' => 'Bonjour']], $user ),
            fn() => Elements::save( $element->id, ['lang' => 'fr'], $user ),
            fn() => Elements::save( $element->id, ['lang' => null], $user ),
            fn() => Elements::save( Element::where( 'lang', 'en' )->firstOrFail()->id, ['name' => 'Footer'], $user ),
        ] as $idx => $fcn )
        {
            try {
                $fcn();
                $this->fail( 'Expected permission exception for case ' . $idx );
            } catch( PermissionException $e ) {
                $this->assertInstanceOf( PermissionException::class, $e );
            }
        }
    }


    public function testElementsMany()
    {
        $user = $this->actor( ['de'] );
        $de = Elements::add( ['type' => 'text', 'lang' => 'de', 'data' => ['text' => 'Hallo']], $this->user );
        $fr = Elements::add( ['type' => 'text', 'lang' => 'fr', 'data' => ['text' => 'Bonjour']], $this->user );

        try {
            Elements::dropMany( [$de->id, $fr->id], $user );
            $this->fail( 'Expected permission exception' );
        } catch( PermissionException $e ) {
            $this->assertNull( Element::withTrashed()->findOrFail( $de->id )->deleted_at );
        }

        $this->assertCount( 1, Elements::dropMany( [$de->id], $user ) );

        $result = Elements::bulk( [$de->id, $fr->id], ['name' => 'Bulk'], $user );
        $this->assertEquals( [$de->id], $result->ids );
        $this->assertEquals( 1, $result->failed );
    }


    public function testElementFileReferences()
    {
        $user = $this->actor( ['de'] );
        $element = Elements::add( ['type' => 'text', 'lang' => 'de', 'data' => ['text' => 'Hallo']], $this->user );
        $file = File::where( 'lang', 'en' )->firstOrFail();

        // references don't write the referenced items, publishing them is checked instead
        $result = Elements::save( $element->id, ['type' => 'image', 'data' => ['file' => ['id' => $file->id, 'type' => 'file']]], $user );

        $this->assertEquals( [$file->id], $result->latest->files()->pluck( 'cms_files.id' )->all() );
    }


    public function testFiles()
    {
        $user = $this->actor( ['de'] );
        $file = File::where( 'lang', 'en' )->firstOrFail();

        foreach( [
            fn() => Files::save( $file->id, ['name' => 'Renamed'], $user ),
            fn() => Files::drop( $file->id, $user ),
            fn() => Files::publish( $file->id, $user ),
            fn() => Files::add( 'https://example.com/image.jpg', ['lang' => 'fr'], $user ),
            fn() => Files::purgeMany( [$file->id], $user ),
        ] as $idx => $fcn )
        {
            try {
                $fcn();
                $this->fail( 'Expected permission exception for case ' . $idx );
            } catch( PermissionException $e ) {
                $this->assertInstanceOf( PermissionException::class, $e );
            }
        }

        $this->assertEquals( $file->name, File::findOrFail( $file->id )->name );
        $this->assertNull( File::findOrFail( $file->id )->deleted_at );
    }


    public function testUnrestrictedWithoutActionPermission()
    {
        $page = $this->page();

        $this->expectException( PermissionException::class );
        Variants::add( $page->id, 'de', $this->actor( null, ['page:view'] ) );
    }


    protected function actor( ?array $langs, ?array $perms = null ) : \App\Models\User
    {
        return $this->raw( $langs === null ? null : json_encode( $langs ), $perms );
    }


    protected function raw( ?string $langs, ?array $perms = null ) : \App\Models\User
    {
        return ( new \App\Models\User( [
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => $perms ?? Permission::all(),
        ] ) )->forceFill( ['cmslangs' => $langs] );
    }


    protected function persisted( ?array $langs ) : \App\Models\User
    {
        $user = $this->actor( $langs );
        $user->forceFill( ['email' => 'lang-' . \Illuminate\Support\Str::random( 8 ) . '@testbench'] )->save();

        return $user;
    }


    protected function page() : PageView
    {
        return Pages::add( [
            'lang' => 'en', 'name' => 'Test', 'title' => 'Test', 'path' => 'lang-' . \Illuminate\Support\Str::random( 12 ),
            'content' => [['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']]],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : PageView
    {
        return PageView::where( 'tag', 'root' )->firstOrFail();
    }
}
