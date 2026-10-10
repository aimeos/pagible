<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Hashes;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Resource;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


class T3ImportTest extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.typo3', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection( 'typo3' );

        $schema->create( 'pages', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'pid' )->default( 0 );
            $table->string( 'title' );
            $table->string( 'nav_title' )->default( '' );
            $table->string( 'seo_title' )->default( '' );
            $table->string( 'slug' )->default( '' );
            $table->unsignedSmallInteger( 'doktype' )->default( 1 );
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'nav_hide' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->boolean( 'is_siteroot' )->default( false );
            $table->integer( 'sys_language_uid' )->default( 0 );
            $table->unsignedInteger( 'l10n_parent' )->default( 0 );
            $table->unsignedInteger( 't3ver_wsid' )->default( 0 );
            $table->unsignedInteger( 'content_from_pid' )->default( 0 );
            $table->unsignedInteger( 'shortcut' )->default( 0 );
            $table->unsignedInteger( 'shortcut_mode' )->default( 0 );
            $table->string( 'url' )->default( '' );
            $table->string( 'backend_layout' )->default( '' );
            $table->string( 'backend_layout_next_level' )->default( '' );
            $table->text( 'TSconfig' )->nullable();
            $table->unsignedInteger( 'sorting' )->default( 0 );
            $table->unsignedInteger( 'crdate' )->default( 0 );
        } );

        $schema->create( 'tt_content', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'pid' );
            $table->string( 'CType' );
            $table->string( 'header' )->default( '' );
            $table->string( 'header_layout' )->default( '' );
            $table->text( 'bodytext' )->nullable();
            $table->string( 'records' )->default( '' );
            $table->integer( 'colPos' )->default( 0 );
            $table->unsignedInteger( 'imageorient' )->default( 0 );
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->integer( 'sys_language_uid' )->default( 0 );
            $table->unsignedInteger( 'l18n_parent' )->default( 0 );
            $table->unsignedInteger( 'sorting' )->default( 0 );
        } );

        $schema->create( 'sys_language', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->string( 'title' );
            $table->string( 'language_isocode' )->default( '' );
            $table->boolean( 'hidden' )->default( false );
        } );

        $schema->create( 'sys_file', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->string( 'identifier' );
            $table->string( 'name' );
            $table->string( 'mime_type' )->default( '' );
            $table->string( 'extension' )->default( '' );
        } );

        $schema->create( 'sys_file_reference', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'uid_local' );
            $table->unsignedInteger( 'uid_foreign' );
            $table->string( 'tablenames' );
            $table->string( 'fieldname' );
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'sorting_foreign' )->default( 0 );
        } );

        $db = DB::connection( 'typo3' );

        foreach( [
            ['uid' => 1, 'title' => 'Deutsch', 'language_isocode' => 'de'],
            ['uid' => 2, 'title' => 'Français', 'language_isocode' => 'fr'],
        ] as $row ) { $db->table( 'sys_language' )->insert( $row ); }

        foreach( [
            ['uid' => 1, 'pid' => 0, 'title' => 'Home', 'slug' => '/', 'is_siteroot' => 1, 'sorting' => 1],
            ['uid' => 2, 'pid' => 1, 'title' => 'About', 'slug' => '/about', 'sorting' => 2],
            ['uid' => 3, 'pid' => 0, 'title' => 'Startseite', 'slug' => '/', 'sys_language_uid' => 1, 'l10n_parent' => 1, 'sorting' => 1],
            ['uid' => 4, 'pid' => 1, 'title' => 'Über uns', 'slug' => '/ueber-uns', 'sys_language_uid' => 1, 'l10n_parent' => 2, 'sorting' => 2],
            ['uid' => 5, 'pid' => 1, 'title' => 'À propos', 'slug' => '/a-propos', 'sys_language_uid' => 2, 'l10n_parent' => 2, 'sorting' => 2],
            ['uid' => 6, 'pid' => 1, 'title' => 'Contact', 'slug' => '/contact', 'sorting' => 3],
        ] as $row ) { $db->table( 'pages' )->insert( $row ); }

        foreach( [
            ['uid' => 10, 'pid' => 2, 'CType' => 'header', 'header' => 'Welcome', 'bodytext' => null, 'sys_language_uid' => 0, 'l18n_parent' => 0, 'sorting' => 1],
            ['uid' => 11, 'pid' => 2, 'CType' => 'text', 'header' => '', 'bodytext' => '<p>Hello</p>', 'sys_language_uid' => 0, 'l18n_parent' => 0, 'sorting' => 2],
            ['uid' => 20, 'pid' => 2, 'CType' => 'header', 'header' => 'Willkommen', 'bodytext' => null, 'sys_language_uid' => 1, 'l18n_parent' => 10, 'sorting' => 1],
            ['uid' => 21, 'pid' => 2, 'CType' => 'text', 'header' => '', 'bodytext' => '<p>Hallo</p>', 'sys_language_uid' => 1, 'l18n_parent' => 11, 'sorting' => 2],
            ['uid' => 30, 'pid' => 2, 'CType' => 'header', 'header' => 'Bienvenue', 'bodytext' => null, 'sys_language_uid' => 2, 'l18n_parent' => 0, 'sorting' => 1],
        ] as $row ) { $db->table( 'tt_content' )->insert( $row ); }
    }


    public function testImportTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $variants = PageVariant::where( 'page_id', $page->id )->get()->keyBy( 'lang' );

        $this->assertEquals( ['de', 'en', 'fr'], $variants->keys()->sort()->values()->all() );
        $this->assertSame( 'en', $page->source );

        $de = Page::language( 'de' )->findOrFail( $page->id );
        $hashes = Hashes::page( $page->only( Hashes::PAGE_FIELDS ), $page->content, $page->meta, $page->config );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertSame( 'example.com', $de->domain );
        $this->assertSame( 'Willkommen', $de->content[0]->data->title );
        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $de->content, 'id' ) );
        $this->assertFalse( $variants['de']->stale );
        $this->assertEquals( $hashes, $variants['de']->hashes );
        $this->assertFalse( Hashes::stale( $hashes, $variants['de']->hashes ) );

        $fr = Page::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( 'a-propos', $fr->path );
        $this->assertCount( 1, (array) $fr->content );
        $this->assertNotSame( $page->content[0]->id, $fr->content[0]->id );
        $this->assertTrue( $variants['fr']->stale );
        $this->assertEmpty( $variants['fr']->hashes );

        $root = Page::where( 'tag', 'root' )->where( 'domain', 'example.com' )->firstOrFail();

        // the URL "/" of the translated root page is already used by the source page
        $this->assertNull( Page::language( 'de' )->find( $root->id ) );
        $this->assertNull( Page::language( 'fr' )->find( $root->id ) );
    }


    public function testImportTranslationsLanguageOption(): void
    {
        $result = Artisan::call( 'cms:t3-import', [
            '--domain' => 'example.com',
            '--language' => ['1:de:https://example.de/', '2:fr-CA:https://example.ca/'],
        ] );

        $this->assertSame( 0, $result );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();

        $this->assertSame( 'example.de', Page::language( 'de' )->findOrFail( $page->id )->domain );
        $this->assertSame( 'ueber-uns', Page::language( 'de' )->findOrFail( $page->id )->path );
        $this->assertSame( 'a-propos', Page::language( 'fr-CA' )->findOrFail( $page->id )->path );
        $this->assertSame( 'example.ca', Page::language( 'fr-CA' )->findOrFail( $page->id )->domain );
        $this->assertNull( Page::language( 'fr' )->find( $page->id ) );

        $root = Page::where( 'tag', 'root' )->where( 'domain', 'example.com' )->firstOrFail();
        $this->assertSame( '', Page::language( 'de' )->findOrFail( $root->id )->path );
    }


    public function testImportTranslationsInvalidUrl(): void
    {
        $this->expectException( \InvalidArgumentException::class );
        Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--language' => ['1:de:/de/']] );
    }


    public function testImportTranslationsInvalidLanguage(): void
    {
        $this->expectException( \InvalidArgumentException::class );
        Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--language' => ['1:Deutsch']] );
    }


    public function testImportTranslationsUnknownLanguage(): void
    {
        DB::connection( 'typo3' )->table( 'sys_language' )->where( 'uid', 2 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'unknown TYPO3 languages [2]', Artisan::output() );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $this->assertEquals( ['de', 'en'], PageVariant::where( 'page_id', $page->id )->pluck( 'lang' )->sort()->values()->all() );
    }


    public function testImportTranslationsLinks(): void
    {
        $links = '<p><a href="t3://page?uid=2">About</a> <a href="t3://page?uid=6">Contact</a> <a href="t3://page?uid=1">Home</a></p>';
        DB::connection( 'typo3' )->table( 'tt_content' )->whereIn( 'uid', [11, 21] )->update( ['bodytext' => $links] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $de = Page::language( 'de' )->findOrFail( $page->id );

        $this->assertStringContainsString( '<a href="/about">', $page->content[1]->data->text );
        $this->assertStringContainsString( '<a href="/ueber-uns">', $de->content[1]->data->text );
        $this->assertStringContainsString( '<a href="/contact">', $de->content[1]->data->text );
        $this->assertStringContainsString( '<a href="/">', $de->content[1]->data->text );
    }


    public function testImportTranslationsLinksDomain(): void
    {
        $links = '<p><a href="t3://page?uid=2">About</a> <a href="t3://page?uid=6">Contact</a></p>';
        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 21 )->update( ['bodytext' => $links] );

        $result = Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--language' => ['1:de:https://example.de/']] );
        $this->assertSame( 0, $result );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $de = Page::language( 'de' )->findOrFail( $page->id );

        $this->assertStringContainsString( '<a href="/ueber-uns">', $de->content[1]->data->text );
        $this->assertStringContainsString( '<a href="https://example.com/contact">', $de->content[1]->data->text );
    }


    public function testImportTranslationsPathCollision(): void
    {
        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->insert( ['uid' => 7, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 4] );
        $db->table( 'pages' )->insert( ['uid' => 8, 'pid' => 2, 'title' => 'Team', 'slug' => '/ueber-uns/team', 'sys_language_uid' => 1, 'l10n_parent' => 7, 'sorting' => 4] );
        $db->table( 'pages' )->insert( ['uid' => 9, 'pid' => 1, 'title' => 'Kontakt', 'slug' => '/contact', 'sys_language_uid' => 1, 'l10n_parent' => 6, 'sorting' => 3] );
        $db->table( 'tt_content' )->where( 'uid', 21 )->update( ['bodytext' => '<p><a href="t3://page?uid=7">Team</a> <a href="t3://page?uid=6">Kontakt</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped translation: Kontakt (/contact) [de]', Artisan::output() );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $team = Page::where( 'domain', 'example.com' )->where( 'path', 'about/team' )->firstOrFail();
        $contact = Page::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        $text = Page::language( 'de' )->findOrFail( $page->id )->content[1]->data->text;

        $this->assertSame( 'ueber-uns/team', Page::language( 'de' )->findOrFail( $team->id )->path );
        $this->assertNull( Page::language( 'de' )->find( $contact->id ) );
        $this->assertStringContainsString( '<a href="/ueber-uns/team">', $text );
        $this->assertStringContainsString( '<a href="/contact">', $text );
    }


    public function testUpdateTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 21 )->update( ['bodytext' => '<p>Hallo Welt</p>'] );
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            'uid' => 31, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p>Bonjour</p>', 'sys_language_uid' => 2, 'sorting' => 2,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );

        $de = Page::language( 'de' )->findOrFail( $page->id );
        $fr = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->firstOrFail();

        $this->assertSame( $variant->id, $de->variant_id );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertStringContainsString( 'Hallo Welt', $de->content[1]->data->text );
        $this->assertFalse( PageVariant::findOrFail( $variant->id )->stale );
        $this->assertSame( 'a-propos', $fr->path );
        $this->assertFalse( $fr->stale );
        $this->assertNotEmpty( $fr->hashes );
        $this->assertSame( 3, PageVariant::where( 'page_id', $page->id )->count() );
    }


    public function testTranslateNotLinked(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = Page::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $fr = Resource::translatePage( $page->id, 'fr' );

        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $fr->latest->aux->content, 'id' ) );
    }
}
