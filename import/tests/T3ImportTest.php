<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Permission;
use Aimeos\Cms\Translation\Hashes;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Facades\Variants;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use Aimeos\Cms\Commands\T3Import;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Import\Typo3;
use Aimeos\Cms\Import\Typo3Html;
use Aimeos\Cms\Import\Typo3Layouts;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

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
            ['uid' => 2, 'pid' => 1, 'title' => 'About', 'slug' => '/about', 'sorting' => 2, 'crdate' => 1600000000],
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

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $variants = PageVariant::where( 'page_id', $page->id )->get()->keyBy( 'lang' );

        $this->assertEquals( ['de', 'en', 'fr'], $variants->keys()->sort()->values()->all() );
        $this->assertSame( 'en', $page->source );
        $this->assertSame( date( 'Y-m-d H:i:s', 1600000000 ), $page->created_at?->format( 'Y-m-d H:i:s' ) );

        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $hashes = Hashes::page( $page->only( PageVariant::SYNC_FIELDS ), $page->content, $page->meta, $page->config );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertSame( 'example.com', $de->domain );
        $this->assertSame( 'Willkommen', $de->content[0]->data->title );
        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $de->content, 'id' ) );
        $this->assertFalse( $variants['de']->stale );
        $this->assertEquals( $hashes, $variants['de']->hashes );
        $this->assertFalse( Hashes::stale( $hashes, $variants['de']->hashes ) );

        $fr = PageView::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( 'a-propos', $fr->path );
        $this->assertCount( 1, (array) $fr->content );
        $this->assertNotSame( $page->content[0]->id, $fr->content[0]->id );
        $this->assertTrue( $variants['fr']->stale );
        $this->assertEmpty( $variants['fr']->hashes );

        $root = PageView::where( 'tag', 'root' )->where( 'domain', 'example.com' )->firstOrFail();

        // the URL "/" of the translated root page is already used by the source page
        $this->assertNull( PageView::language( 'de' )->find( $root->id ) );
        $this->assertNull( PageView::language( 'fr' )->find( $root->id ) );

        // translations changed in Pagible aren't overwritten
        PageVariant::where( 'path', 'ueber-uns' )->update( ['editor' => 'someone'] );
        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 20 )->update( ['header' => 'Neu'] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped translation: Über uns [de] (changed in Pagible since the last import)', Artisan::output() );
        $this->assertSame( 'Willkommen', PageView::language( 'de' )->findOrFail( $page->id )->content[0]->data->title ?? null );
    }


    public function testImportTranslationsLanguageOption(): void
    {
        $result = Artisan::call( 'cms:t3-import', [
            '--domain' => 'example.com',
            '--language' => ['1:de:https://example.de/', '2:fr-CA:https://example.ca/'],
        ] );

        $this->assertSame( 0, $result );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();

        $this->assertSame( 'example.de', PageView::language( 'de' )->findOrFail( $page->id )->domain );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
        $this->assertSame( 'a-propos', PageView::language( 'fr-CA' )->findOrFail( $page->id )->path );
        $this->assertSame( 'example.ca', PageView::language( 'fr-CA' )->findOrFail( $page->id )->domain );
        $this->assertNull( PageView::language( 'fr' )->find( $page->id ) );

        $root = PageView::where( 'tag', 'root' )->where( 'domain', 'example.com' )->firstOrFail();
        $this->assertSame( '', PageView::language( 'de' )->findOrFail( $root->id )->path );
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

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $this->assertEquals( ['de', 'en'], PageVariant::where( 'page_id', $page->id )->pluck( 'lang' )->sort()->values()->all() );
    }


    public function testUnknownLanguagesAreNotFetched(): void
    {
        $db = DB::connection( 'typo3' );
        $db->table( 'sys_language' )->where( 'uid', 2 )->delete();

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )
            ->filter( fn( $query ) => preg_match( '/from "(pages|tt_content)" where .*"sys_language_uid" in \(\?\)/', $query['query'] ) === 1 );
        $db->disableQueryLog();

        $this->assertStringContainsString( 'unknown TYPO3 languages [2]', Artisan::output() );

        // the translated pages and content are only fetched for the imported language
        $this->assertCount( 2, $queries );
        foreach( $queries as $query ) {
            $this->assertSame( 1, end( $query['bindings'] ) );
        }

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $this->assertEquals( ['de', 'en'], PageVariant::where( 'page_id', $page->id )->pluck( 'lang' )->sort()->values()->all() );
        $this->assertSame( 'Willkommen', PageView::language( 'de' )->findOrFail( $page->id )->content[0]->data->title ?? null );
    }


    public function testImportTranslationsLinks(): void
    {
        $links = '<p><a href="t3://page?uid=2">About</a> <a href="t3://page?uid=6">Contact</a> <a href="t3://page?uid=1">Home</a></p>';
        DB::connection( 'typo3' )->table( 'tt_content' )->whereIn( 'uid', [11, 21] )->update( ['bodytext' => $links] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

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

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

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

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $team = PageView::where( 'domain', 'example.com' )->where( 'path', 'about/team' )->firstOrFail();
        $contact = PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        $text = PageView::language( 'de' )->findOrFail( $page->id )->content[1]->data->text;

        $this->assertSame( 'ueber-uns/team', PageView::language( 'de' )->findOrFail( $team->id )->path );
        $this->assertNull( PageView::language( 'de' )->find( $contact->id ) );
        $this->assertStringContainsString( '<a href="/ueber-uns/team">', $text );
        $this->assertStringContainsString( '<a href="/contact">', $text );
    }


    public function testUpdateTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 21 )->update( ['bodytext' => '<p>Hallo Welt</p>'] );
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            'uid' => 31, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p>Bonjour</p>', 'sys_language_uid' => 2, 'sorting' => 2,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );

        $de = PageView::language( 'de' )->findOrFail( $page->id );
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

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $fr = Permission::system( fn() => Variants::translate( $page->id, 'fr' ) );

        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $fr->latest->aux->content, 'id' ) );
    }


    public function testDryRunListsPages(): void
    {
        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->insert( ['uid' => 7, 'pid' => 2, 'title' => 'Link', 'slug' => '/link', 'doktype' => 4, 'sorting' => 4] );
        $db->table( 'pages' )->insert( ['uid' => 8, 'pid' => 1, 'title' => 'Secret', 'slug' => '/secret', 'hidden' => 1, 'sorting' => 5] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "[1] Home (/)\n  [2] About (/about)\n    [7] Link (/link) [shortcut]\n", $output );
        $this->assertStringContainsString( "  [8] Secret (/secret) [hidden]\n", $output );
        $this->assertStringContainsString( 'Dry run complete. No changes were made.', $output );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--dry-run' => true, '--page' => [7]] ) );
        $this->assertStringContainsString( "[7] Link (/link) [shortcut]\n", Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testAttemptForgetsFilesOfFailedRecords(): void
    {
        $command = new class extends T3Import {
            /** @return array{0: mixed, 1: string} */
            public function check(): array
            {
                $this->editor = 'importer';
                $this->createdFiles = Collection::make();
                $this->createdFileUrls = Collection::make();
                $failed = $this->attempt( 'file in test', function() {
                    $this->importFile( 'image/png', 'Image', 'user_upload/image.png' );
                    throw new \RuntimeException( 'failed' );
                } );

                return [$failed, $this->importFile( 'image/png', 'Image', 'user_upload/image.png' )];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), $output = new BufferedOutput() ) );

        [$failed, $id] = $command->check();

        $this->assertNull( $failed );
        $this->assertNotNull( File::find( $id ) );
        $this->assertStringContainsString( 'Skipped file in test: failed', $output->fetch() );
    }


    public function testAttemptForgetsSharedElementsOfFailedRecords(): void
    {
        $command = new class extends T3Import {
            /** @return array{0: mixed, 1: array<int, string>} */
            public function check(): array
            {
                $this->editor = 'importer';
                $this->connection = 'typo3';
                $this->createdFiles = Collection::make();
                $typo3 = new Typo3( fn() => null, new Typo3Html( fn() => null, fn() => null ), fn() => Collection::make(), fn() => Collection::make(), fn() => Collection::make(), fn() => Collection::make() );
                $record = (object) ['uid' => 41, 'pid' => 2, 'CType' => 'text', 'header' => '', 'bodytext' => '<p>Shared</p>', '_pagible_shared' => 'reference'];

                $failed = $this->attempt( 'page in test', function() use ( $record, $typo3 ) {
                    $this->convert( $record, $typo3, 'en' );
                    throw new \RuntimeException( 'failed' );
                } );

                return [$failed, $this->convert( $record, $typo3, 'en' )['elementIds'] ?? []];
            }
        };
        $command->setOutput( new OutputStyle( new ArrayInput( [] ), new BufferedOutput() ) );

        [$failed, $ids] = $command->check();

        // the shared element of the failed record is stored again
        $this->assertNull( $failed );
        $this->assertCount( 1, $ids );
        $this->assertNotNull( Element::find( $ids[0] ) );
    }


    public function testOrdersContentByBackendLayoutColumns(): void
    {
        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->where( 'uid', 1 )->update( ['TSconfig' => "mod.web_layout.BackendLayouts.two {\n config { colPos = 1\n colPos = 0 }\n}"] );
        $db->table( 'pages' )->where( 'uid', 6 )->update( ['backend_layout' => 'pagets__two'] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 50, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Main</p>', 'colPos' => 0, 'sorting' => 1],
            ['uid' => 51, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Side</p>', 'colPos' => 1, 'sorting' => 2],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $content = (array) PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->content;

        $this->assertSame( ['<p>Side</p>', '<p>Main</p>'], array_values( array_filter( array_map( fn( $item ) => $item->data->text ?? null, $content ) ) ) );
    }


    public function testParsesInheritedPageTsconfigOnce(): void
    {
        $pages = [
            1 => (object) ['uid' => 1, 'pid' => 0, 'backend_layout' => 'pagets__two', 'TSconfig' => "mod.web_layout.BackendLayouts.two {\n config { colPos = 1\n colPos = 0 }\n}"],
            2 => (object) ['uid' => 2, 'pid' => 1, 'backend_layout' => 'pagets__two', 'TSconfig' => ''],
            3 => (object) ['uid' => 3, 'pid' => 2, 'backend_layout' => 'pagets__two', 'TSconfig' => ''],
            4 => (object) ['uid' => 4, 'pid' => 1, 'backend_layout' => 'pagets__two', 'TSconfig' => "mod.web_layout.BackendLayouts.two {\n config { colPos = 0\n colPos = 1 }\n}"],
            5 => (object) ['uid' => 5, 'pid' => 4, 'backend_layout' => 'pagets__two', 'TSconfig' => ''],
        ];
        $layouts = new class( Collection::make( $pages ), Collection::make() ) extends Typo3Layouts {
            public int $parsed = 0;

            protected function pageTs( object $t3Page, string $name ): string
            {
                $this->parsed++;
                return parent::pageTs( $t3Page, $name );
            }
        };
        $records = Collection::make( [
            (object) ['uid' => 1, 'colPos' => 0, 'sorting' => 1],
            (object) ['uid' => 2, 'colPos' => 1, 'sorting' => 2],
        ] );
        $order = fn( int $uid ) => $layouts->sort( $records, $pages[$uid] )->pluck( 'uid' )->all();

        $this->assertSame( [2, 1], $order( 1 ) );
        $this->assertSame( [2, 1], $order( 2 ) );
        $this->assertSame( [2, 1], $order( 3 ) );
        // the PageTSconfig is parsed only once for all pages inheriting it from the same page
        $this->assertSame( 1, $layouts->parsed );
        // pages with own PageTSconfig and their child pages use the overwritten layout
        $this->assertSame( [1, 2], $order( 4 ) );
        $this->assertSame( [1, 2], $order( 5 ) );
        $this->assertSame( 2, $layouts->parsed );
    }


    public function testImportsSelectedPagesInTreeOrder(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        DB::connection( 'typo3' )->table( 'pages' )->insert( ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 5] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [6, 10, 2]] ) );
        $output = Artisan::output();

        // children are imported directly after their parents, so new pages are added at the end of the page tree
        $positions = array_map( fn( $path ) => strpos( $output, "({$path})" ), ['/about', '/about/team', '/contact'] );

        $this->assertNotContains( false, $positions );
        $this->assertSame( $positions, Collection::make( $positions )->sort()->values()->all() );
        $this->assertSame( 'about', PageView::where( 'path', 'about/team' )->firstOrFail()->parent?->path );
    }


    public function testFetchesUnselectedParentsOfSelectedPagesAtOnce(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 5],
            ['uid' => 11, 'pid' => 2, 'title' => 'Jobs', 'slug' => '/about/jobs', 'sorting' => 6],
        ] );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [10, 11]] ) );
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"path" = ?' ) && in_array( 'about', $query['bindings'], true ) );
        DB::connection()->disableQueryLog();
        DB::connection()->flushQueryLog();

        // the existing page of the unselected parent is fetched together with the selected pages
        $this->assertCount( 0, $lookups );
        $this->assertSame( 'about', PageView::where( 'path', 'about/team' )->firstOrFail()->parent?->path );
        $this->assertSame( 'about', PageView::where( 'path', 'about/jobs' )->firstOrFail()->parent?->path );
    }


    public function testImportsChildrenOfPagesInChunks(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 5],
            ['uid' => 11, 'pid' => 2, 'title' => 'Contact us', 'slug' => '/contact', 'sorting' => 6],
        ] );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"path" in' ) );
        DB::connection()->disableQueryLog();

        // the child pages are imported directly before their own children
        $positions = array_map( fn( $path ) => strpos( $output, "({$path})" ), ['/about', '/about/team', '/contact'] );

        $this->assertNotContains( false, $positions );
        $this->assertSame( $positions, Collection::make( $positions )->sort()->values()->all() );
        $this->assertSame( 'about', PageView::where( 'path', 'about/team' )->firstOrFail()->parent?->path );
        $this->assertSame( ['about'], PageView::where( 'tag', 'root' )->firstOrFail()->children->pluck( 'path' )->all() );
        // the URLs of the next pages which weren't found are fetched again if the descendants used them
        $this->assertSame( [['about', 'contact'], ['about/team', 'contact'], ['contact']], $lookups->map( fn( $query ) => array_slice( $query['bindings'], 1, -1 ) )->values()->all() );
        $this->assertStringContainsString( 'Reused: Contact (/contact)', $output );
        $this->assertSame( 'about', PageView::where( 'path', 'contact' )->firstOrFail()->parent?->path );
    }


    public function testFetchesNextPagesOnlyIfDescendantsUsedThem(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 5],
            ['uid' => 11, 'pid' => 6, 'title' => 'Form', 'slug' => '/contact/form', 'sorting' => 6],
            ['uid' => 12, 'pid' => 1, 'title' => 'Imprint', 'slug' => '/imprint', 'sorting' => 7],
        ] );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"path" in' ) );
        DB::connection()->disableQueryLog();

        // the URLs of the next pages aren't fetched again because the descendants saved other URLs
        $this->assertSame( [['about', 'contact', 'imprint'], ['about/team'], ['contact/form']], $lookups->map( fn( $query ) => array_slice( $query['bindings'], 1, -1 ) )->values()->all() );
        $this->assertSame( ['about', 'contact', 'imprint'], PageView::where( 'tag', 'root' )->firstOrFail()->children->pluck( 'path' )->all() );
        $this->assertSame( 'contact', PageView::where( 'path', 'contact/form' )->firstOrFail()->parent?->path );
    }


    public function testFetchesNextPagesAgainIfDescendantTranslationsUsedThem(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 5],
            ['uid' => 11, 'pid' => 2, 'title' => 'Kontakt', 'slug' => '/kontakt', 'sys_language_uid' => 1, 'l10n_parent' => 10, 'sorting' => 5],
            ['uid' => 12, 'pid' => 1, 'title' => 'Kontakt', 'slug' => '/kontakt', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 7],
        ] );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"path" in' ) );
        DB::connection()->disableQueryLog();

        // the URL of the next page used by the translation of a descendant is fetched again
        $this->assertSame( [['about', 'contact', 'kontakt'], ['about/team'], ['kontakt']], $lookups->map( fn( $query ) => array_slice( $query['bindings'], 1, -1 ) )->values()->all() );
    }


    public function testRemembersOnlySavedPathsOfMissingSiblings(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 5],
            ['uid' => 11, 'pid' => 2, 'title' => 'Kontakt', 'slug' => '/kontakt', 'sys_language_uid' => 1, 'l10n_parent' => 10, 'sorting' => 5],
            ['uid' => 12, 'pid' => 1, 'title' => 'Kontakt', 'slug' => '/kontakt', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 7],
        ] );

        $command = new class extends T3Import {
            /** @var array<int, array<string, bool>> */
            public array $states = [];

            protected function savedPath( string $domain, string $path ): void
            {
                parent::savedPath( $domain, $path );
                $this->states[] = $this->watched[$domain] ?? [];
            }

            /** @return array<string, array<string, bool>> */
            public function watched(): array
            {
                return $this->watched;
            }
        };
        $command->setLaravel( app() );

        $this->assertSame( 0, $command->run( new ArrayInput( ['--domain' => 'example.com'] ), new BufferedOutput() ) );

        // only the URLs of the siblings which weren't found are tracked and only while their level is imported
        $this->assertContains( ['about' => true, 'contact' => false, 'kontakt' => true, 'about/team' => true], $command->states );
        $this->assertSame( [], array_filter( $command->states, fn( $state ) => count( $state ) > 4 ) );
        $this->assertSame( [], array_filter( $command->watched() ) );
    }


    public function testFetchesVariantsOfTranslatedSiblingsAtOnce(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 12, 'pid' => 1, 'title' => 'Imprint', 'slug' => '/imprint', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 7],
            ['uid' => 13, 'pid' => 1, 'title' => 'Impressum', 'slug' => '/impressum', 'sys_language_uid' => 1, 'l10n_parent' => 12, 'sorting' => 7],
            ['uid' => 14, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sys_language_uid' => 0, 'l10n_parent' => 0, 'sorting' => 8],
            ['uid' => 15, 'pid' => 2, 'title' => 'Mannschaft', 'slug' => '/ueber-uns/team', 'sys_language_uid' => 1, 'l10n_parent' => 14, 'sorting' => 8],
        ] );

        $variants = function() {
            DB::connection()->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
            $queries = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], 'where "cms_page_view"."id" ' ) );
            DB::connection()->disableQueryLog();
            DB::connection()->flushQueryLog();

            return $queries->map( fn( $query ) => $query['bindings'] )->values()->all();
        };

        // the new pages have no variants yet
        $this->assertSame( [], $variants() );

        $root = PageView::where( 'domain', 'example.com' )->where( 'tag', 'root' )->firstOrFail();
        $about = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $imprint = PageView::where( 'domain', 'example.com' )->where( 'path', 'imprint' )->firstOrFail();
        $team = PageView::where( 'domain', 'example.com' )->where( 'path', 'about/team' )->firstOrFail();

        // the variants of the existing pages of the translated siblings are fetched at once
        // and are kept while the descendants of the previous sibling are imported
        $this->assertSame( [[$root->id, 'test'], [$about->id, $imprint->id, 'test'], [$team->id, 'test']], $variants() );
        $this->assertSame( 'Impressum', PageView::language( 'de' )->findOrFail( $imprint->id )->title );
        $this->assertSame( 'Mannschaft', PageView::language( 'de' )->findOrFail( $team->id )->title );
    }


    public function testKeepsLimitedNextPagesWhileImportingDescendants(): void
    {
        $rows = array_map( fn( $uid ) => ['uid' => $uid, 'pid' => 1, 'title' => "Page {$uid}", 'slug' => "/page-{$uid}", 'sorting' => $uid], range( 100, 219 ) );
        $rows[] = ['uid' => 10, 'pid' => 2, 'title' => 'Team', 'slug' => '/about/team', 'sorting' => 5];
        DB::connection( 'typo3' )->table( 'pages' )->insert( $rows );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"path" in' ) );
        DB::connection()->disableQueryLog();

        $slugs = array_map( fn( $uid ) => "page-{$uid}", range( 100, 219 ) );
        // the existing pages after the next 50 siblings are dropped while the children are imported and fetched again afterwards, 50 at once
        $this->assertSame( [['about', 'contact', ...$slugs], ['about/team'], array_slice( $slugs, 49, 50 ), array_slice( $slugs, 99 )], $lookups->map( fn( $query ) => array_slice( $query['bindings'], 1, -1 ) )->values()->all() );
        $this->assertStringContainsString( 'Unchanged: Page 219 (/page-219)', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 0 updated, 123 unchanged', $output );
        $this->assertSame( 120, PageView::where( 'domain', 'example.com' )->where( 'path', 'like', 'page-%' )->count() );
    }


    public function testSharedContentElements(): void
    {
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_41', 'sorting' => 1],
            ['uid' => 41, 'pid' => 2, 'CType' => 'text', 'header' => 'Shared', 'bodytext' => '<p>Shared text</p>', 'records' => '', 'sorting' => 3],
        ] );

        DB::connection()->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $lookups = $this->queries( DB::connection() )->filter( fn( $query ) => preg_match( '/from "cms_elements" where .*"name" = \?/', $query['query'] ) === 1 );
        DB::connection()->disableQueryLog();

        // the shared elements are stored once although they are shown on two pages
        $this->assertCount( 2, $lookups );
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2, 6]] ) );

        $elements = Element::where( 'name', 'like', 'TYPO3 element %' )->orderBy( 'name' )->get();
        $refs = fn( string $path ) => array_values( array_filter( array_map(
            fn( $item ) => $item->refid ?? null,
            (array) PageView::where( 'domain', 'example.com' )->where( 'path', $path )->firstOrFail()->content
        ) ) );

        $this->assertSame( 2, $elements->count() );
        $this->assertMatchesRegularExpression( '#^TYPO3 element [0-9a-f]{12}/2/41/0$#', $elements->pluck( 'name' )->first() );
        $this->assertMatchesRegularExpression( '#^TYPO3 element [0-9a-f]{12}/2/41/1$#', $elements->pluck( 'name' )->last() );
        $this->assertSame( $elements->pluck( 'id' )->all(), $refs( 'contact' ) );
        $this->assertSame( $refs( 'about' ), $refs( 'contact' ) );
        $this->assertSame( [1, 1], $elements->map( fn( $element ) => $element->versions()->count() )->all() );

        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 41 )->update( ['bodytext' => '<p>Changed text</p>'] );
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2, 6]] ) );

        $this->assertSame( [1, 2], $elements->map( fn( $element ) => $element->versions()->count() )->all() );
        $this->assertStringContainsString( 'Changed text', Element::findOrFail( $elements->pluck( 'id' )->last() )->data->text ?? '' );
    }


    public function testFetchesShortcutTargetsOfUpcomingPages(): void
    {
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_41', 'sys_language_uid' => 0, 'sorting' => 1],
            ['uid' => 41, 'pid' => 2, 'CType' => 'text', 'header' => 'Shared', 'bodytext' => '<p>Shared text</p>', 'records' => '', 'sys_language_uid' => 0, 'sorting' => 3],
            ['uid' => 42, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_43', 'sys_language_uid' => 0, 'sorting' => 2],
            ['uid' => 43, 'pid' => 2, 'CType' => 'text', 'header' => 'Other', 'bodytext' => '<p>Other text</p>', 'records' => '', 'sys_language_uid' => 0, 'sorting' => 4],
            ['uid' => 44, 'pid' => 2, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_43', 'sys_language_uid' => 0, 'sorting' => 5],
            ['uid' => 47, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_48', 'sys_language_uid' => 0, 'sorting' => 4],
            ['uid' => 48, 'pid' => 2, 'CType' => 'text', 'header' => 'Third', 'bodytext' => '<p>Third text</p>', 'records' => '', 'sys_language_uid' => 0, 'sorting' => 7],
            ['uid' => 45, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_46', 'sys_language_uid' => 1, 'sorting' => 3],
            ['uid' => 46, 'pid' => 2, 'CType' => 'text', 'header' => 'Own', 'bodytext' => '<p>Own text</p>', 'records' => '', 'sys_language_uid' => 0, 'sorting' => 6],
        ] );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 41, 'tablenames' => 'tt_content', 'fieldname' => 'image'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $log = $this->queries( $db );
        $db->disableQueryLog();

        // the targets of the shortcuts of all upcoming pages are fetched together and only once at all
        $queries = $log->filter( fn( $query ) => preg_match( '/from "tt_content" .*"uid" in/', $query['query'] ) === 1 );
        $this->assertSame( [[41, 43, 48]], $queries->map( fn( $query ) => array_slice( $query['bindings'], 2, -2 ) )->values()->all() );

        // the file references of the targets are fetched together with the ones of the upcoming pages
        $refs = $log->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file_reference"' ) && in_array( 41, $query['bindings'], true ) );
        $this->assertCount( 1, $refs );
        $this->assertContains( 40, $refs->first()['bindings'] ?? [] );

        // only shortcuts in the default language share their targets
        $this->assertSame( 0, Element::where( 'name', 'like', 'TYPO3 element %/2/46/%' )->count() );
        $this->assertSame( 2, Element::where( 'name', 'like', 'TYPO3 element %/2/43/%' )->count() );
    }


    public function testSharedContentElementsOfTranslations(): void
    {
        // content elements of all languages are shared in each language
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'header' => '', 'bodytext' => null, 'records' => 'tt_content_41', 'sys_language_uid' => 0, 'sorting' => 1],
            ['uid' => 41, 'pid' => 2, 'CType' => 'text', 'header' => 'Shared', 'bodytext' => '<p>Shared text</p>', 'records' => '', 'sys_language_uid' => -1, 'sorting' => 3],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $langs = Element::where( 'name', 'like', 'TYPO3 element %/2/41/%' )->pluck( 'lang' )->unique()->sort()->values()->all();

        $this->assertSame( ['de', 'en', 'fr'], $langs );
    }


    public function testFetchesSharedContentOfTranslationsOnce(): void
    {
        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 49, 'tablenames' => 'tt_content', 'fieldname' => 'image'] );
        $db->table( 'tt_content' )->insert( ['uid' => 49, 'pid' => 2, 'CType' => 'text', 'header' => 'All', 'bodytext' => '<p>All languages</p>', 'sys_language_uid' => -1, 'sorting' => 3] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db );
        $db->disableQueryLog();

        // the content and file references for all languages are fetched once for the page and its translations
        $content = $queries->filter( fn( $query ) => end( $query['bindings'] ) === -1 && in_array( 2, $this->contentPids( $query ), true ) );
        $refs = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file_reference"' ) && in_array( 49, $query['bindings'], true ) );

        $this->assertCount( 1, $content );
        $this->assertCount( 1, $refs );

        // the file references of the translated content of all languages are fetched at once
        $translated = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file_reference"' ) && array_intersect( [20, 21, 30], $query['bindings'] ) );
        $this->assertSame( [[20, 21, 30]], $translated->map( fn( $query ) => Collection::make( $query['bindings'] )->intersect( [20, 21, 30] )->sort()->values()->all() )->values()->all() );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();

        foreach( [$page, PageView::language( 'de' )->findOrFail( $page->id ), PageView::language( 'fr' )->findOrFail( $page->id )] as $variant ) {
            $this->assertStringContainsString( 'All languages', (string) json_encode( $variant->content ) );
        }
    }


    public function testFetchesUsedAccordionItemColumns(): void
    {
        Schema::connection( 'typo3' )->create( 'tx_bootstrappackage_accordion_item', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'tt_content' );
            $table->string( 'header' )->default( '' );
            $table->text( 'bodytext' )->nullable();
            $table->text( 'l10n_diffsource' )->nullable();
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'sorting' )->default( 0 );
        } );

        $db = DB::connection( 'typo3' );
        $db->table( 'tx_bootstrappackage_accordion_item' )->insert( ['uid' => 1, 'tt_content' => 50, 'header' => 'Question', 'bodytext' => '<p>Answer</p>', 'l10n_diffsource' => 'a:0:{}'] );
        $db->table( 'tt_content' )->insert( ['uid' => 50, 'pid' => 6, 'CType' => 'accordion', 'header' => 'FAQ', 'bodytext' => null, 'sorting' => 1] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, 'from "tx_bootstrappackage_accordion_item"' ) );
        $db->disableQueryLog();

        // only the used columns of the accordion items are fetched
        $this->assertSame( ['select "uid", "tt_content", "header", "bodytext"'], $queries->map( fn( $query ) => explode( ' from ', $query )[0] )->values()->all() );

        $content = (array) PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->content;
        $this->assertSame( 'questions', $content[0]->type ?? null );
        $this->assertSame( 'Question', $content[0]->data->items[0]->title ?? null );
    }


    public function testDownloadsAccordionFilesBeforePageTransactions(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $cms->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $cms, &$requests ) {
            $requests[] = [$request->url(), $cms->transactionLevel()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        Schema::connection( 'typo3' )->create( 'tx_bootstrappackage_accordion_item', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'tt_content' );
            $table->string( 'header' )->default( '' );
            $table->text( 'bodytext' )->nullable();
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'sorting' )->default( 0 );
        } );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/docs/a.png', 'name' => 'A.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/docs/b.png', 'name' => 'B.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'tx_bootstrappackage_accordion_item' )->insert( [
            ['uid' => 1, 'tt_content' => 50, 'header' => 'First', 'bodytext' => '<p><img src="fileadmin/docs/a.png" alt=""></p>', 'sorting' => 1],
            ['uid' => 2, 'tt_content' => 50, 'header' => 'Second', 'bodytext' => '<p><img src="fileadmin/docs/b.png" alt=""></p>', 'sorting' => 2],
        ] );
        $db->table( 'tt_content' )->insert( ['uid' => 50, 'pid' => 6, 'CType' => 'accordion', 'header' => 'FAQ', 'bodytext' => null, 'sorting' => 1] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $sysFiles = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file" where "identifier"' ) );
        $db->disableQueryLog();

        // the files in the accordion items are looked up at once and downloaded outside the transactions of the pages
        $urls = ['http://127.0.0.1/fileadmin/docs/a.png', 'http://127.0.0.1/fileadmin/docs/b.png'];
        $this->assertSame( [[$urls[0], $level + 1], [$urls[1], $level + 1]], $requests, Artisan::output() );
        $this->assertCount( 1, $sysFiles );
        $this->assertSame( ['A.png', 'B.png'], File::orderBy( 'name' )->pluck( 'name' )->all() );
    }


    public function testDownloadsFailedFilesOnce(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $requests = 0;
        Http::fake( function() use ( &$requests ) {
            $requests++;
            return Http::response( 'Not found', 404 );
        } );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( [
            ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image'],
            ['uid' => 2, 'uid_local' => 1, 'uid_foreign' => 13, 'tablenames' => 'tt_content', 'fieldname' => 'image'],
        ] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3],
            ['uid' => 13, 'pid' => 6, 'CType' => 'image', 'bodytext' => null, 'sorting' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $output = Artisan::output();

        // the failure is reported for each use of the file
        $this->assertSame( 1, $requests );
        $this->assertSame( 2, substr_count( $output, 'Skipped file reference' ) );
        $this->assertSame( 0, File::count() );
    }


    public function testSkipsUnusedColumns(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $schema = Schema::connection( 'typo3' );
        $schema->table( 'tt_content', fn( Blueprint $table ) => $table->text( 'l18n_diffsource' )->nullable() );
        $schema->table( 'tt_content', fn( Blueprint $table ) => $table->text( 'pi_flexform' )->nullable() );
        $schema->table( 'pages', fn( Blueprint $table ) => $table->text( 'l10n_diffsource' )->nullable() );
        $schema->table( 'sys_file_reference', fn( Blueprint $table ) => $table->text( 'l10n_diffsource' )->nullable() );
        $schema->table( 'sys_file', fn( Blueprint $table ) => $table->string( 'sha1' )->default( '' ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image', 'l10n_diffsource' => 'a:0:{}'] );
        $db->table( 'tt_content' )->insert( ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3, 'l18n_diffsource' => 'a:0:{}'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        // only the used columns are fetched
        $selects = $queries->filter( fn( $query ) => preg_match( '/^select .* from "(pages|tt_content|sys_file_reference|sys_file)"/', $query ) === 1 );
        $tables = $selects->map( fn( $query ) => preg_replace( '/^select .* from "([a-z_]+)".*$/s', '$1', $query ) );
        $this->assertEqualsCanonicalizing( ['pages', 'tt_content', 'sys_file_reference', 'sys_file'], $tables->unique()->values()->all() );
        $this->assertFalse( $selects->contains( fn( $query ) => preg_match( '/\*|diffsource|sha1|pi_flexform|l18n_parent|fieldname|sorting_foreign|deleted/', explode( ' from ', $query )[0] ) === 1 ) );

        // the columns of each table are listed once
        $listings = $queries->filter( fn( $query ) => str_contains( $query, 'pragma_table_xinfo' ) );
        $this->assertSame( 4, $listings->count() );
        $this->assertSame( 4, $listings->unique()->count() );

        $content = collect( (array) PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail()->content );
        $image = $content->first( fn( $item ) => isset( $item->data->file->id ) );
        $this->assertSame( 'photo.png', File::findOrFail( $image->data->file->id ?? '' )->name ?? null );
        $this->assertSame( 'Welcome', $content->first()->data->title ?? null );
    }


    public function testFetchesContentAndFilesOfNextPagesAtOnce(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        Schema::connection( 'typo3' )->table( 'sys_file', fn( Blueprint $table ) => $table->string( 'identifier_hash' )->default( '' ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'identifier_hash' => sha1( '/user_upload/photo.png' ), 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/docs/Logo.png', 'identifier_hash' => sha1( '/docs/logo.png' ), 'name' => 'Brand.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 3, 'identifier' => '/user_upload/unused.png', 'identifier_hash' => sha1( '/user_upload/unused.png' ), 'name' => 'unused.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image'] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3],
            ['uid' => 13, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p><img src="fileadmin/docs/Logo.png" alt=""></p>', 'sorting' => 1],
            ['uid' => 14, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p>Text</p>', 'sorting' => 4],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $queries = $this->queries( $db );
        $db->flushQueryLog();

        // the sys_file records are fetched by the UIDs of the references and the hashes of the linked files only
        $files = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file" ' ) );
        $this->assertCount( 2, $files );
        $this->assertSame( 1, $files->filter( fn( $query ) => str_contains( $query['query'], '"uid" in (?)' ) && $query['bindings'] === [1] )->count() );
        $this->assertSame( 1, $files->filter( fn( $query ) => str_contains( $query['query'], '"identifier_hash" in' ) )->count() );
        $this->assertSame( ['Brand.png', 'photo.png'], File::orderBy( 'name' )->pluck( 'name' )->all() );

        // the file references of all content elements of the next pages are fetched at once
        $refs = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file_reference"' ) );
        $this->assertSame( 1, $refs->filter( fn( $query ) => !array_diff( [12, 13, 14], $query['bindings'] ) )->count() );
        $this->assertSame( 0, $refs->filter( fn( $query ) => array_intersect( [12, 13, 14], $query['bindings'] ) && array_diff( [12, 13, 14], $query['bindings'] ) )->count() );

        // the content records of the next pages in import order are fetched at once
        $content = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "tt_content"' ) );
        $this->assertSame( 0, $content->reject( fn( $query ) => $this->contentPids( $query ) || str_contains( $query['query'], '"CType" = ?' ) )->count() );
        $this->assertSame( [[1, 2, 6], [1, 2]], $content->map( fn( $query ) => $this->contentPids( $query ) )->filter()->values()->all() );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin', '--page' => [6]] ) );
        $pids = $this->queries( $db )->map( fn( $query ) => $this->contentPids( $query ) )->filter();
        $db->disableQueryLog();

        // only the content of the selected pages is fetched
        $this->assertSame( [[6]], $pids->values()->all() );

        $db->flushQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin', '--page' => [2, 6]] ) );
        $pids = $this->queries( $db )->map( fn( $query ) => $this->contentPids( $query ) )->filter();
        $db->disableQueryLog();

        // the content of the selected pages is fetched at once before the first page is imported
        $this->assertSame( [[2, 6], [2]], $pids->values()->all() );
    }


    public function testKeepsReferencesOfCurrentAndNextContent(): void
    {
        $grouped = fn( \Closure $uid ) => fn( array $uids ) => Collection::make( $uids )->mapWithKeys( fn( $id ) => [$id => Collection::make( [(object) ['uid' => $uid( $id )]] )] );
        $typo3 = new Typo3( fn() => null, new Typo3Html( fn() => null, fn() => null ),
            $grouped( fn( $id ) => $id * 10 ), fn() => Collection::make(), $grouped( fn( $id ) => $id + 100 ), $grouped( fn( $id ) => $id * 1000 ) );
        $uids = fn( Collection $refs ) => $refs->map( fn( $ref ) => $ref->uid )->sort()->values()->all();

        $typo3->prefetch( [(object) ['uid' => 1, 'CType' => 'carousel']] );

        // only the references of the next content are returned but the ones of the current content are kept
        $this->assertSame( [20, 102000], $uids( $typo3->ahead( [(object) ['uid' => 2, 'CType' => 'carousel']] ) ) );
        $this->assertSame( [10, 101000], $uids( $typo3->references() ) );

        $typo3->prefetch( [(object) ['uid' => 2, 'CType' => 'carousel']] );
        $this->assertSame( [20, 102000], $uids( $typo3->references() ) );
    }


    public function testFetchesCarouselsOfImportedContent(): void
    {
        Schema::connection( 'typo3' )->create( 'tx_bootstrappackage_carousel_item', function( Blueprint $table ) {
            $table->id( 'uid' );
            $table->unsignedInteger( 'tt_content' );
            $table->string( 'header' )->default( '' );
            $table->boolean( 'hidden' )->default( false );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'sorting' )->default( 0 );
        } );

        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );
        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/slides/a.png', 'name' => 'a.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/slides/b.png', 'name' => 'b.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 3, 'identifier' => '/slides/c.png', 'name' => 'c.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'tx_bootstrappackage_carousel_item' )->insert( [
            ['uid' => 1, 'tt_content' => 51, 'sorting' => 2],
            ['uid' => 2, 'tt_content' => 51, 'sorting' => 1],
            ['uid' => 3, 'tt_content' => 52, 'sorting' => 1],
        ] );
        $db->table( 'sys_file_reference' )->insert( [
            ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 1, 'tablenames' => 'tx_bootstrappackage_carousel_item', 'fieldname' => 'background_image'],
            ['uid' => 2, 'uid_local' => 2, 'uid_foreign' => 2, 'tablenames' => 'tx_bootstrappackage_carousel_item', 'fieldname' => 'background_image'],
            ['uid' => 3, 'uid_local' => 3, 'uid_foreign' => 3, 'tablenames' => 'tx_bootstrappackage_carousel_item', 'fieldname' => 'background_image'],
        ] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 51, 'pid' => 6, 'CType' => 'carousel', 'header' => 'Slides', 'bodytext' => null, 'sorting' => 1, 'deleted' => 0],
            ['uid' => 52, 'pid' => 6, 'CType' => 'carousel', 'header' => 'Deleted', 'bodytext' => null, 'sorting' => 2, 'deleted' => 1],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db );
        $db->disableQueryLog();

        // only the carousel items and their references of the imported content elements are fetched
        $items = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "tx_bootstrappackage_carousel_item"' ) );
        $refs = $queries->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file_reference"' ) && in_array( 'tx_bootstrappackage_carousel_item', $query['bindings'], true ) );
        $this->assertSame( [[0, 0, 51]], $items->pluck( 'bindings' )->values()->all() );
        $this->assertSame( [[0, 0, 'tx_bootstrappackage_carousel_item', 'background_image', 2, 1]], $refs->pluck( 'bindings' )->values()->all() );

        $content = (array) PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->content;
        $slides = array_values( array_filter( $content, fn( $element ) => ( $element->type ?? null ) === 'slideshow' ) );
        $this->assertCount( 1, $slides );
        $ids = array_column( (array) ( $slides[0]->data->files ?? [] ), 'id' );
        $this->assertSame( ['b.png', 'a.png'], array_map( fn( $id ) => File::whereKey( $id )->value( 'name' ), $ids ) );
        $this->assertSame( 2, File::count() );
    }


    public function testFindsFilesWithoutLeadingSlash(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        // identifiers of old TYPO3 versions without identifier hash may lack the leading slash
        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 2, 'identifier' => 'docs/logo.png', 'name' => 'Brand.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'tt_content' )->insert( ['uid' => 13, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p><img src="fileadmin/docs/logo.png" alt=""></p>', 'sorting' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );

        $this->assertSame( ['Brand.png'], File::pluck( 'name' )->all() );
    }


    public function testDownloadsFilesOfPreviousImportsAgain(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image'] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3],
            ['uid' => 13, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p><img src="/fileadmin/user_upload/inline.png"></p>', 'sorting' => 4],
        ] );

        $options = ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'];
        $text = fn() => collect( (array) PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail()->content )
            ->map( fn( $item ) => $item->data->text ?? '' )->first( fn( $text ) => str_contains( $text, '<img' ) );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options ) );

        $ids = File::orderBy( 'id' )->pluck( 'id' )->all();
        $content = $text();

        $this->assertCount( 2, $ids, Artisan::output() );
        $this->assertStringContainsString( '/storage/', (string) $content );
        Http::assertSentCount( 2 );

        // the files of previous imports aren't reused because they are stored under new paths
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options + ['--page' => [2]] ) );

        $this->assertSame( 4, File::count() );
        Http::assertSentCount( 4 );

        // without --file-base, the files are fetched from the fileadmin URL of the domain
        File::orderBy( 'id' )->firstOrFail()->delete();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );

        Http::assertSent( fn( $request ) => str_starts_with( $request->url(), 'https://example.com/fileadmin/' ) );
    }


    public function testRestrictsLinkedFilesToStorages(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        Schema::connection( 'typo3' )->table( 'sys_file', function( Blueprint $table ) {
            $table->string( 'identifier_hash' )->default( '' );
            $table->unsignedInteger( 'storage' )->default( 0 );
        } );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'storage' => 2, 'identifier' => '/docs/a.png', 'identifier_hash' => sha1( '/docs/a.png' ), 'name' => 'A.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'storage' => 0, 'identifier' => '/docs/b.png', 'identifier_hash' => sha1( '/docs/b.png' ), 'name' => 'B.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 13, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p><img src="/fileadmin/docs/a.png"></p>', 'sorting' => 4],
            ['uid' => 14, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p><img src="/fileadmin/docs/b.png"></p>', 'sorting' => 1],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $queries = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file"' ) );
        $db->disableQueryLog();

        // the linked files are looked up by the indexed storages and hashes, the storages are fetched once
        $hashes = $queries->filter( fn( $query ) => str_contains( $query['query'], '"identifier_hash" in' ) );
        $this->assertSame( 2, $hashes->count() );
        $this->assertSame( 2, $hashes->filter( fn( $query ) => preg_match( '/"identifier_hash" in \([?, ]+\) and "storage" in \(\?, \?\)/', $query['query'] ) === 1
            && array_slice( $query['bindings'], -2 ) === [0, 2] )->count() );
        $this->assertSame( 1, $queries->filter( fn( $query ) => str_starts_with( $query['query'], 'select distinct "storage"' ) )->count() );
        $this->assertSame( ['A.png', 'B.png'], File::orderBy( 'name' )->pluck( 'name' )->all() );
    }


    public function testConvertsContentElementsWithoutSavepoints(): void
    {
        $count = function( string $domain ) {
            $begins = 0;
            Event::listen( TransactionBeginning::class, function() use ( &$begins ) { $begins++; } );
            $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => $domain, '--page' => [1, 2]] ) );
            Event::forget( TransactionBeginning::class );
            return $begins;
        };

        $begins = $count( 'example.com' );

        DB::connection( 'typo3' )->table( 'tt_content' )->insert( array_map(
            fn( $uid ) => ['uid' => $uid, 'pid' => 2, 'CType' => 'text', 'bodytext' => "<p>Text {$uid}</p>", 'sorting' => $uid], range( 100, 119 )
        ) );

        // more content elements don't need more transactions
        $this->assertSame( $begins, $count( 'example.org' ) );
        $this->assertStringContainsString( 'Text 119', (string) json_encode( PageView::where( 'domain', 'example.org' )->where( 'path', 'about' )->firstOrFail()->content ) );
    }


    public function testCommitsPagesInBatches(): void
    {
        $command = new class extends T3Import {
            protected int $batchSize = 1;

            public function kept(): int
            {
                return $this->createdPages->count();
            }
        };
        $command->setLaravel( app() );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $db->transactionLevel();
        $commits = 0;
        Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$commits ) {
            $commits += (int) ( $event->connection->transactionLevel() === $level );
        } );

        $this->assertSame( 0, $command->run( new ArrayInput( ['--domain' => 'example.com'] ), new BufferedOutput() ) );
        // after the pages "about" and "contact" and at the end, not after their content elements
        $this->assertSame( 3, $commits );
        $this->assertSame( $level, $db->transactionLevel() );
        $this->assertSame( 2, PageView::where( 'domain', 'example.com' )->whereIn( 'path', ['about', 'contact'] )->count() );
        // the pages aren't looked up by the ID of their source record
        $this->assertSame( 0, $command->kept() );
    }


    public function testSkipsUnchangedPages(): void
    {
        $queries = [];
        DB::connection( config( 'cms.db', 'sqlite' ) )->listen( function( $query ) use ( &$queries ) {
            $queries[] = [$this->sql( $query->sql ), $query->bindings];
        } );

        // the domain of the site root is used for its child pages
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => ['example.com:1']] ) );
        // the child pages are fetched at once
        $this->assertSame( 1, $this->pageQueries( $queries, '"path" in (?, ?)', ['about', 'contact'] ) );
        $this->assertSame( 0, $this->pageQueries( $queries, '"path" = ?', ['about', 'contact'] ) );

        $about = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $contact = PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        $queries = [];

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => ['example.com:1'], '--page' => [2, 6]] ) );
        // the selected pages and the translations are fetched at once
        $this->assertSame( 1, $this->pageQueries( $queries, '"path" in (?, ?)', ['about', 'contact'] ) );
        $this->assertSame( 0, $this->pageQueries( $queries, '"path" = ?', ['about', 'contact'] ) );
        // the translations of the page are fetched by one query and its source variant isn't fetched again
        $this->assertCount( 1, array_filter( $queries, fn( $query ) => str_contains( $query[0], 'cms_page_view' )
            && in_array( $about->id, $query[1], true ) ) );
        // nothing is written for unchanged pages and translations
        $this->assertSame( [], array_values( array_filter( $queries, fn( $query ) => !str_starts_with( $query[0], 'select' ) ) ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Unchanged: Contact (/contact)', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 0 updated, 2 unchanged (2/2 pages processed).', $output );
        $this->assertSame( 1, $about->versions()->where( 'lang', 'en' )->count() );
        $this->assertSame( 1, $contact->versions()->where( 'lang', 'en' )->count() );

        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 11 )->update( ['bodytext' => '<p>Hello world</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2, 6]] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: About (/about)', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 1 updated, 1 unchanged (2/2 pages processed).', $output );
        $this->assertSame( 2, $about->versions()->where( 'lang', 'en' )->count() );
        $this->assertStringContainsString( 'Hello world', PageView::findOrFail( $about->id )->content[1]->data->text ?? '' );
        // the translation is linked to the changed source
        $this->assertEquals( Hashes::published( PageView::findOrFail( $about->id ) ), PageVariant::where( 'page_id', $about->id )->where( 'lang', 'de' )->firstOrFail()->hashes );
    }


    public function testSinceKeepsUnchangedPages(): void
    {
        foreach( ['pages', 'tt_content', 'sys_file_reference'] as $name ) {
            Schema::connection( 'typo3' )->table( $name, fn( Blueprint $table ) => $table->unsignedInteger( 'tstamp' )->default( 1000 ) );
        }
        Schema::connection( 'typo3' )->table( 'sys_file_reference', fn( Blueprint $table ) => $table->unsignedInteger( 'pid' )->default( 0 ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->insert( ['uid' => 7, 'pid' => 1, 'title' => 'Team', 'slug' => '/team', 'sorting' => 4, 'content_from_pid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => 'invalid'] ) );
        $this->assertStringContainsString( 'Invalid date for --since: invalid', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $db->table( 'tt_content' )->where( 'uid', 11 )->update( ['bodytext' => '<p>Hello world</p>'] );
        $text = fn( string $value ) => Element::get()->contains( fn( $element ) => str_contains( (string) json_encode( $element->data ), $value ) );
        // UIDs of the pages whose content is rendered
        $rendered = function() use ( $db ) {
            $db->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => '@2000'] ) );
            $log = $this->queries( $db );
            $db->disableQueryLog();
            $db->flushQueryLog();

            return [$log, $log->flatMap( fn( $query ) => $this->contentPids( $query ) )->unique()->sort()->values()->all()];
        };

        [$log, $pids] = $rendered();
        $times = $log->filter( fn( $query ) => str_contains( $query['query'], 'tstamp' ) );

        // the modification times of the root page and of the child pages are fetched at once in import order
        $this->assertCount( 4, $times );
        $this->assertSame( [1, 2, 6, 7], $times->last()['bindings'] ?? null );
        $this->assertSame( [], $pids );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Unchanged: Team (/team)', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 0 updated, 3 unchanged (3/3 child pages processed).', $output );
        $this->assertFalse( $text( 'Hello world' ) );

        // changed translated content changes the page and the page which shows its content
        $db->table( 'tt_content' )->where( 'uid', 21 )->update( ['tstamp' => 3000] );
        $db->table( 'pages' )->where( 'uid', 7 )->update( ['title' => 'Our team'] );
        $this->assertSame( [2], $rendered()[1] );
        $this->assertTrue( $text( 'Hello world' ) );
        $this->assertStringContainsString( 'Updated: Our team (/team)', Artisan::output() );

        // changed file references and translations change the page
        $db->table( 'tt_content' )->where( 'uid', 21 )->update( ['tstamp' => 1000] );
        $db->table( 'sys_file_reference' )->insert( ['uid_local' => 1, 'uid_foreign' => 99, 'tablenames' => 'tt_content', 'fieldname' => 'image', 'pid' => 6, 'tstamp' => 3000] );
        $db->table( 'pages' )->where( 'uid', 4 )->update( ['tstamp' => 3000] );
        $this->assertSame( [2, 6], $rendered()[1] );

        // trashed pages of unchanged records are restored
        $db->table( 'pages' )->where( 'uid', 4 )->update( ['tstamp' => 1000] );
        $db->table( 'sys_file_reference' )->update( ['tstamp' => 1000] );
        PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->page?->delete();
        $this->assertSame( [], $rendered()[1] );
        $this->assertTrue( PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->exists() );

        // unchanged pages without page are imported with the content of the next changed pages fetched at once
        PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail()->page?->forceDelete();
        $db->table( 'pages' )->where( 'uid', 6 )->update( ['tstamp' => 3000] );
        $this->assertSame( [[2, 6], [2]], $rendered()[0]->map( fn( $query ) => $this->contentPids( $query ) )->filter()->values()->all() );
        $this->assertTrue( PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->exists() );
    }


    public function testSinceFetchesContentOfMissingUnchangedPagesOnItsOwn(): void
    {
        foreach( ['pages', 'tt_content', 'sys_file_reference'] as $name ) {
            Schema::connection( 'typo3' )->table( $name, fn( Blueprint $table ) => $table->unsignedInteger( 'tstamp' )->default( 1000 ) );
        }
        Schema::connection( 'typo3' )->table( 'sys_file_reference', fn( Blueprint $table ) => $table->unsignedInteger( 'pid' )->default( 0 ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->insert( [
            ['uid' => 7, 'pid' => 1, 'title' => 'News', 'slug' => '/news', 'sorting' => 4],
            ['uid' => 8, 'pid' => 1, 'title' => 'Jobs', 'slug' => '/jobs', 'sorting' => 5],
            ['uid' => 9, 'pid' => 1, 'title' => 'Events', 'slug' => '/events', 'sorting' => 6],
        ] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 70, 'pid' => 7, 'CType' => 'text', 'bodytext' => '<p>News</p>', 'sorting' => 1],
            ['uid' => 80, 'pid' => 8, 'CType' => 'text', 'bodytext' => '<p>Jobs</p>', 'sorting' => 1],
            ['uid' => 90, 'pid' => 9, 'CType' => 'text', 'bodytext' => '<p>Events</p>', 'sorting' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        // unchanged pages without page are imported between the changed pages
        foreach( ['news', 'jobs'] as $path ) {
            PageView::where( 'domain', 'example.com' )->where( 'path', $path )->firstOrFail()->page?->forceDelete();
        }
        $db->table( 'pages' )->whereIn( 'uid', [2, 9] )->update( ['tstamp' => 3000] );
        $db->table( 'pages' )->where( 'uid', 9 )->update( ['title' => 'Our events'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => '@2000'] ) );
        $pids = $this->queries( $db )->flatMap( fn( $query ) => $this->contentPids( $query ) );
        $db->disableQueryLog();
        $output = Artisan::output();

        // the content of the missing pages is fetched on its own and the one of the next changed page only once
        $this->assertSame( 1, $pids->filter( fn( $pid ) => $pid === 9 )->count(), $output );
        $this->assertSame( 1, $pids->filter( fn( $pid ) => $pid === 7 )->count() );
        $this->assertSame( 1, $pids->filter( fn( $pid ) => $pid === 8 )->count() );
        $this->assertStringContainsString( 'Imported: News (/news)', $output );
        $this->assertStringContainsString( 'Imported: Jobs (/jobs)', $output );
        $this->assertStringContainsString( 'Updated: Our events (/events)', $output );

        foreach( ['news' => 'News', 'jobs' => 'Jobs', 'events' => 'Events'] as $path => $text ) {
            $this->assertStringContainsString( $text, (string) json_encode( PageView::where( 'domain', 'example.com' )->where( 'path', $path )->firstOrFail()->content ) );
        }
    }


    public function testSinceWithoutModificationTimes(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 11 )->update( ['bodytext' => '<p>Hello world</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => '@2000'] ) );
        $output = Artisan::output();

        $this->assertSame( 1, substr_count( $output, 'No modification dates of the TYPO3 pages found, all pages are imported.' ) );
        $this->assertStringContainsString( 'Updated: About (/about)', $output );
    }


    public function testRestoresTrashedTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->delete();

        // trashed translations aren't marked as stale if the source changes
        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 11 )->update( ['bodytext' => '<p>Hello world</p>'] );

        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );

        $de = PageView::language( 'de' )->find( $page->id );

        $this->assertInstanceOf( PageView::class, $de );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertFalse( $de->stale );
        $this->assertEquals( Hashes::published( PageView::findOrFail( $page->id ) ), PageVariant::whereKey( $de->variant_id )->firstOrFail()->hashes );
        $this->assertSame( 3, PageVariant::where( 'page_id', $page->id )->count() );
    }

    public function testPublishesRestoredUnchangedTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $latest = $variant->latest_id;
        $variant->delete();

        // the unchanged translation is published again, so it's added to the search index too
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );

        $restored = PageVariant::findOrFail( $variant->id );

        $this->assertInstanceOf( PageView::class, PageView::language( 'de' )->find( $page->id ) );
        $this->assertNotSame( $latest, $restored->latest_id );

        // repeated imports don't publish it again
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2]] ) );
        $this->assertSame( $restored->latest_id, PageVariant::findOrFail( $variant->id )->latest_id );
    }


    public function testDeadlockOfSkippableRecordStopsImport(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image'] );
        $db->table( 'tt_content' )->insert( ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3] );

        // file references and content elements which fail are skipped, but not after deadlocks
        Event::listen( 'eloquent.creating: ' . File::class, fn() => throw new \RuntimeException( 'Deadlock found when trying to get lock; try restarting transaction' ) );
        $level = DB::connection()->transactionLevel();

        try {
            Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] );
            $this->fail( 'Exception expected' );
        } catch( \Throwable $e ) {
            $this->assertStringContainsString( 'Deadlock found', $e->getMessage() );
        }

        $output = Artisan::output();

        $this->assertStringContainsString( 'Import stopped by a concurrent change in the database', $output );
        $this->assertStringNotContainsString( 'Skipped file reference', $output );
        $this->assertStringNotContainsString( 'Skipped content element', $output );
        $this->assertSame( $level, DB::connection()->transactionLevel() );
        $this->assertSame( 0, File::count() );
    }


    public function testRestoresTrashedPages(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $trash = function() {
            foreach( ['about', 'contact'] as $path ) {
                PageView::where( 'domain', 'example.com' )->where( 'path', $path )->firstOrFail()->page->delete();
            }
        };

        $trash();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 2, PageView::where( 'domain', 'example.com' )->whereIn( 'path', ['about', 'contact'] )->count() );

        $trash();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [2, 6]] ) );
        $this->assertSame( 2, PageView::where( 'domain', 'example.com' )->whereIn( 'path', ['about', 'contact'] )->count() );
        $this->assertSame( 2, PageView::withTrashed()->where( 'domain', 'example.com' )->whereIn( 'path', ['about', 'contact'] )->count() );
    }


    public function testRejectedSelectedPagesStayTrashed(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $contact = PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        PageVariant::where( 'page_id', $contact->id )->update( ['editor' => 'someone'] );
        $contact->page?->delete();

        // pages which were changed since aren't restored if the import of the selected page is rejected
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [6]] ) );

        $this->assertStringContainsString( "Failed to import [6] Contact: URL /contact is used by a page which wasn't imported or was changed since", Artisan::output() );
        $this->assertSame( 0, PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->count() );
        $this->assertTrue( (bool) PageView::withTrashed()->where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->trashed() );
    }


    public function testSinceChangesPagesWithShortcutsToChangedRecords(): void
    {
        foreach( ['pages', 'tt_content', 'sys_file_reference'] as $name ) {
            Schema::connection( 'typo3' )->table( $name, fn( Blueprint $table ) => $table->unsignedInteger( 'tstamp' )->default( 1000 ) );
        }
        Schema::connection( 'typo3' )->table( 'sys_file_reference', fn( Blueprint $table ) => $table->unsignedInteger( 'pid' )->default( 0 ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'pages' )->insert( [
            ['uid' => 8, 'pid' => 1, 'title' => 'Records', 'slug' => '/records', 'doktype' => 254, 'sorting' => 5],
            ['uid' => 9, 'pid' => 1, 'title' => 'More records', 'slug' => '/more-records', 'doktype' => 254, 'sorting' => 6],
            ['uid' => 11, 'pid' => 1, 'title' => 'Source', 'slug' => '/source', 'doktype' => 254, 'sorting' => 8],
        ] );
        $db->table( 'pages' )->insert( ['uid' => 10, 'pid' => 1, 'title' => 'Mirror', 'slug' => '/mirror', 'sorting' => 7, 'content_from_pid' => 11] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_41', 'sorting' => 1],
            ['uid' => 41, 'pid' => 8, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_42', 'sorting' => 1],
            ['uid' => 42, 'pid' => 9, 'CType' => 'text', 'bodytext' => '<p>Shared text</p>', 'records' => '', 'sorting' => 2],
            ['uid' => 43, 'pid' => 11, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_41', 'sorting' => 1],
        ] );

        $content = fn() => (string) json_encode( Element::get()->map( fn( $element ) => $element->data ) );
        $since = function() use ( $db ) {
            $db->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => '@2000'] ) );
            $pids = $this->queries( $db )->flatMap( fn( $query ) => $this->contentPids( $query ) )->unique()->sort()->values()->all();
            $db->flushQueryLog();
            $db->disableQueryLog();

            return $pids;
        };

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Shared text', $content() );

        // pages with shortcuts to unchanged records are unchanged
        $this->assertSame( [], $since(), Artisan::output() );

        $db->table( 'tt_content' )->where( 'uid', 42 )->update( ['bodytext' => '<p>Changed text</p>', 'tstamp' => 3000] );

        // changed records of pages which aren't imported change the pages with nested shortcuts to them
        // including the pages whose content is taken from pages with these shortcuts
        $this->assertSame( [6, 11], $since(), Artisan::output() );
        $this->assertStringContainsString( 'Changed text', $content() );
    }


    public function testSinceChangesPagesWithShortcutsToRecordsWithoutModificationTime(): void
    {
        Schema::connection( 'typo3' )->table( 'pages', fn( Blueprint $table ) => $table->unsignedInteger( 'tstamp' )->default( 1000 ) );

        // the record isn't stored in a page with modification time
        $db = DB::connection( 'typo3' );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_41', 'sorting' => 1],
            ['uid' => 41, 'pid' => 99, 'CType' => 'text', 'bodytext' => '<p>Shared text</p>', 'records' => '', 'sorting' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--since' => '@2000'] ) );
        $pids = $this->queries( $db )->flatMap( fn( $query ) => $this->contentPids( $query ) )->unique()->values()->all();
        $db->disableQueryLog();

        // pages with shortcuts to records without modification time are imported again
        $this->assertSame( [6], $pids, Artisan::output() );
    }


    public function testImportsShortcutsReferencingEachOther(): void
    {
        DB::connection( 'typo3' )->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_41', 'sorting' => 1],
            ['uid' => 41, 'pid' => 99, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_42', 'sorting' => 1],
            ['uid' => 42, 'pid' => 99, 'CType' => 'shortcut', 'bodytext' => null, 'records' => 'tt_content_40,tt_content_41', 'sorting' => 2],
        ] );

        // cyclic shortcuts are fetched once and don't stop the import
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Failed to import', Artisan::output() );
        $this->assertSame( 1, PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->count() );
    }


    public function testPagesWithSameSlugDontOverwritePages(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert(
            ['uid' => 7, 'pid' => 1, 'title' => 'Contact again', 'slug' => '/contact', 'sorting' => 4]
        );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // pages with the same URL are imported below the page of the first one
        $this->assertStringContainsString( 'Reused: Contact again (/contact) [example.com]', $output );
        $this->assertStringNotContainsString( 'Failed to import', $output );
        $this->assertSame( 1, PageView::withTrashed()->where( 'domain', 'example.com' )->where( 'path', 'contact' )->count() );
        $this->assertSame( 'Contact', PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->title );

        // parents are imported before their selected children
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'other.example', '--page' => [6, 7, 1]] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Failed to import [7] Contact again: URL /contact is used by another page of this import', $output );
        $this->assertStringContainsString( 'Import complete. 2 imported, 0 updated, 0 unchanged (2/3 pages processed).', $output );
        $this->assertSame( 1, PageView::withTrashed()->where( 'domain', 'other.example' )->where( 'path', 'contact' )->count() );
        $this->assertSame( 'Contact', PageView::where( 'domain', 'other.example' )->where( 'path', 'contact' )->firstOrFail()->title );
    }


    public function testFetchesExistingChildPagesInChunks(): void
    {
        $rows = array_map( fn( $uid ) => ['uid' => $uid, 'pid' => 1, 'title' => "Page {$uid}", 'slug' => "/page-{$uid}", 'sorting' => $uid], range( 100, 599 ) );
        $rows[] = ['uid' => 600, 'pid' => 1, 'title' => 'Page again', 'slug' => '/page-100', 'sorting' => 600];
        DB::connection( 'typo3' )->table( 'pages' )->insert( $rows );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        // the existing pages of the second chunk are fetched after the pages of the first chunk are imported
        $lookup = fn( $query ) => preg_match( '/^select .* from "cms_page_view" .*"path" in \(\?, \?/', $query ) === 1;
        $this->assertCount( 2, $queries->filter( $lookup ) );
        $count = 0;
        $inserts = $queries->takeUntil( function( $query ) use ( $lookup, &$count ) { return $lookup( $query ) && ++$count === 2; } )
            ->filter( fn( $query ) => str_starts_with( $query, 'insert into "cms_pages"' ) );
        $this->assertGreaterThan( 500, $inserts->count() );
        // the page imported for the first chunk is reused for the same URL in the second one
        $this->assertStringContainsString( 'Reused: Page again (/page-100) [example.com]', $output );
        $this->assertSame( 1, PageView::where( 'domain', 'example.com' )->where( 'path', 'page-100' )->count() );
    }


    public function testReimportUpdatesAndMovesPages(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( ['uid' => 8, 'pid' => 1, 'title' => 'Team', 'slug' => '/team', 'sorting' => 5] );
        Pages::create( ['name' => 'Manual', 'title' => 'Manual', 'path' => 'team', 'tag' => 'page', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Created root page: Home (example.com)', $output );
        $this->assertStringContainsString( 'Imported: About (/about) [example.com]', $output );
        // pages which weren't imported are only used as parent
        $this->assertStringContainsString( "Reused: Team (/team) [example.com] (URL is used by a page which wasn't imported, was changed since or was saved by this import)", $output );
        $this->assertStringContainsString( 'Import complete. 2 imported, 0 updated, 1 unchanged (3/3 child pages processed).', $output );

        $about = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $contact = PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        $root = PageView::where( 'domain', 'example.com' )->where( 'tag', 'root' )->firstOrFail();

        DB::connection( 'typo3' )->table( 'pages' )->where( 'uid', 6 )->update( ['pid' => 2] );
        DB::connection( 'typo3' )->table( 'tt_content' )->where( 'uid', 11 )->update( ['bodytext' => '<p>Hello world</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged root page: Home (example.com)', $output );
        $this->assertStringContainsString( 'Updated: About (/about) [example.com]', $output );
        $this->assertStringContainsString( 'Unchanged: Contact (/contact) [example.com]', $output );
        $this->assertStringContainsString( 'Reused: Team (/team) [example.com]', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 1 updated, 2 unchanged (3/3 child pages processed).', $output );
        $this->assertSame( $root->id, PageView::findOrFail( $about->id )->parent_id );
        $this->assertSame( $about->id, PageView::findOrFail( $contact->id )->parent_id );
        $this->assertStringContainsString( 'Hello world', (string) json_encode( PageView::findOrFail( $about->id )->content ) );
        $this->assertSame( 'Manual', PageView::where( 'domain', 'example.com' )->where( 'path', 'team' )->firstOrFail()->name );

        // selected pages don't overwrite pages which weren't imported
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--page' => [8]] ) );

        $this->assertStringContainsString( "Failed to import [8] Team: URL /team is used by a page which wasn't imported or was changed since", Artisan::output() );
        $this->assertSame( 'Manual', PageView::where( 'domain', 'example.com' )->where( 'path', 'team' )->firstOrFail()->name );
    }

    public function testDoesntOverwritePagesWhichWerentImported(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( ['uid' => 8, 'pid' => 1, 'title' => 'Team', 'slug' => '/team', 'sorting' => 5] );
        Pages::create( ['name' => 'Manual root', 'title' => 'Manual root', 'path' => '', 'tag' => 'root', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [] );
        Pages::create( ['name' => 'Manual', 'title' => 'Manual', 'path' => 'team', 'tag' => 'page', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [] );
        PageView::where( 'domain', 'example.com' )->where( 'path', 'team' )->firstOrFail()->page->delete();

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Using existing root page: Manual root (example.com)', $output );
        $this->assertStringContainsString( "[8] Team: URL /team is used by a page which wasn't imported or was changed since", $output );
        $this->assertSame( 'Manual root', PageView::where( 'domain', 'example.com' )->where( 'tag', 'root' )->sole()->name );
        $this->assertSame( 0, PageView::where( 'domain', 'example.com' )->where( 'path', 'team' )->count() );
    }


    public function testChildWithRootUrlDoesntOverwriteRoot(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( ['uid' => 9, 'pid' => 1, 'title' => 'Home again', 'slug' => '/', 'sorting' => 6] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );

        $this->assertStringContainsString( 'Reused: Home again (/) [example.com]', Artisan::output() );
        $this->assertSame( 'Home', PageView::where( 'domain', 'example.com' )->where( 'tag', 'root' )->sole()->name );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'other.example', '--page' => [1, 9]] ) );

        $this->assertStringContainsString( 'Failed to import [9] Home again: URL / is used by another page of this import', Artisan::output() );
        $this->assertSame( 'Home', PageView::where( 'domain', 'other.example' )->where( 'tag', 'root' )->sole()->name );
    }


    public function testDownloadsFilesBeforePageTransactions(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $cms->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $cms, &$requests ) {
            $requests[] = [$request->url(), $cms->transactionLevel()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        foreach( ['pages', 'tt_content', 'sys_file_reference'] as $name ) {
            Schema::connection( 'typo3' )->table( $name, fn( Blueprint $table ) => $table->unsignedInteger( 'tstamp' )->default( 1000 ) );
        }
        Schema::connection( 'typo3' )->table( 'sys_file_reference', fn( Blueprint $table ) => $table->unsignedInteger( 'pid' )->default( 0 ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/docs/logo.png', 'name' => 'logo.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'sys_file_reference' )->insert( ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 12, 'tablenames' => 'tt_content', 'fieldname' => 'image', 'pid' => 2] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 12, 'pid' => 2, 'CType' => 'image', 'bodytext' => null, 'sorting' => 3],
            ['uid' => 13, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p><img src="fileadmin/docs/logo.png" alt=""></p>', 'sorting' => 1],
        ] );

        $options = ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'];
        $urls = ['http://127.0.0.1/fileadmin/user_upload/photo.png', 'http://127.0.0.1/fileadmin/docs/logo.png'];

        // the files of the child pages are downloaded outside the transactions of the pages
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options ) );
        $this->assertSame( [[$urls[0], $level + 1], [$urls[1], $level + 1]], $requests, Artisan::output() );
        $this->assertSame( 2, File::count() );

        // the files of the selected pages are downloaded outside the transactions of the pages too
        File::get()->each( fn( $file ) => $file->delete() );
        $requests = [];

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options + ['--page' => [2, 6]] ) );
        $this->assertSame( [[$urls[0], $level + 1], [$urls[1], $level + 1]], $requests, Artisan::output() );
        $this->assertSame( 2, File::count() );

        // the files of unchanged pages and of pages which weren't imported aren't downloaded
        File::get()->each( fn( $file ) => $file->delete() );
        $contact = PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail();
        PageVariant::where( 'page_id', $contact->id )->update( ['editor' => 'someone'] );
        $db->table( 'pages' )->where( 'uid', 6 )->update( ['tstamp' => 3000] );
        $requests = [];

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options + ['--since' => '@2000'] ) );
        $output = Artisan::output();

        $this->assertCount( 0, $requests, $output );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Reused: Contact (/contact)', $output );
        $this->assertSame( 0, File::count() );
    }


    public function testDownloadsTranslationFilesBeforePageTransactions(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $cms->transactionLevel();
        $requests = [];

        Http::fake( function( $request ) use ( $png, $cms, &$requests ) {
            $requests[] = [$request->url(), $cms->transactionLevel()];
            return Http::response( $png, 200, ['Content-Type' => 'image/png'] );
        } );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/docs/de.png', 'name' => 'de.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/docs/fr.png', 'name' => 'fr.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'tt_content' )->where( 'uid', 21 )->update( ['bodytext' => '<p><img src="fileadmin/docs/de.png" alt=""></p>'] );
        $db->table( 'tt_content' )->insert( ['uid' => 31, 'pid' => 2, 'CType' => 'text', 'bodytext' => '<p><img src="fileadmin/docs/fr.png" alt=""></p>',
            'sys_language_uid' => 2, 'l18n_parent' => 0, 'sorting' => 2] );

        $options = ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'];
        $urls = ['http://127.0.0.1/fileadmin/docs/de.png', 'http://127.0.0.1/fileadmin/docs/fr.png'];

        // the files of the translated content are downloaded outside the transactions of the pages
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options ) );
        $this->assertSame( [[$urls[0], $level + 1], [$urls[1], $level + 1]], $requests, Artisan::output() );
        $this->assertSame( 2, File::count() );

        // the files of the translations of selected pages too
        File::get()->each( fn( $file ) => $file->delete() );
        $requests = [];

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', $options + ['--page' => [2]] ) );
        $this->assertSame( [[$urls[0], $level + 1], [$urls[1], $level + 1]], $requests, Artisan::output() );
        $this->assertSame( 2, File::count() );
    }


    public function testFetchesSysFilesOfLinkedFilesAtOnce(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/docs/a.png', 'name' => 'A.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/docs/b.png', 'name' => 'B.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 3, 'identifier' => '/docs/c.png', 'name' => 'C.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'tt_content' )->insert( ['uid' => 13, 'pid' => 6, 'CType' => 'text', 'sorting' => 1,
            'bodytext' => '<p><img src="fileadmin/docs/a.png" alt=""><img src="fileadmin/docs/b.png" alt=""><a href="fileadmin/docs/c.png">C</a></p>'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $sysFiles = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "sys_file" where "identifier"' ) );
        $db->disableQueryLog();

        // the sys_file records of all files linked in the content are fetched by one query
        $this->assertCount( 1, $sysFiles, Artisan::output() );
        $this->assertSame( ['A.png', 'B.png', 'C.png'], File::orderBy( 'name' )->pluck( 'name' )->all() );
        Http::assertSentCount( 3 );
    }


    public function testCommittingKeepsLastSharedElements(): void
    {
        $command = new class extends T3Import {
            /** @return array{0: int, 1: array<int, string>, 2: array<int, string>} */
            public function check(): array
            {
                $entry = ['elements' => [], 'fileIds' => [], 'elementIds' => []];
                $this->createdFiles = $this->createdFileUrls = Collection::make();
                $this->shared = array_fill_keys( array_map( fn( $num ) => "content/{$num}/en", range( 1, static::PREFETCH_SIZE ) ), $entry );
                $this->committing();
                $kept = array_keys( $this->shared );

                $this->shared['content/new/en'] = $entry;
                $this->committing();

                return [static::PREFETCH_SIZE, $kept, array_keys( $this->shared )];
            }
        };

        [$size, $kept, $limited] = $command->check();

        // the shared elements are kept up to the limit, the last ones are kept if there are more
        $this->assertCount( $size, $kept );
        $this->assertLessThanOrEqual( $size, count( $limited ) );
        $this->assertGreaterThan( 0, count( $limited ) );
        $this->assertSame( 'content/new/en', end( $limited ) );
        $this->assertNotContains( 'content/1/en', $limited );
    }


    public function testExternalUrlPagesOnlyRedirectToWebUrls(): void
    {
        DB::connection( 'typo3' )->table( 'pages' )->insert( [
            ['uid' => 7, 'pid' => 1, 'title' => 'Script', 'slug' => '/script', 'doktype' => 3, 'url' => 'javascript://x%0aalert(1)', 'sorting' => 4],
            ['uid' => 8, 'pid' => 1, 'title' => 'Partner', 'slug' => '/partner', 'doktype' => 3, 'url' => ' https://example.org/partner ', 'sorting' => 5],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // only http(s) URLs and paths are used as redirect targets
        $this->assertStringContainsString( 'Skipped invalid external URL of [7] Script, only http(s) URLs and paths are allowed', $output );
        $this->assertStringNotContainsString( 'external URL of [8]', $output );
        $this->assertSame( '', (string) PageView::where( 'domain', 'example.com' )->where( 'path', 'script' )->firstOrFail()->to );
        $this->assertSame( 'https://example.org/partner', PageView::where( 'domain', 'example.com' )->where( 'path', 'partner' )->firstOrFail()->to );
    }


    public function testRestrictedPagesAreDisabled(): void
    {
        Schema::connection( 'typo3' )->table( 'pages', function( Blueprint $table ) {
            $table->string( 'fe_group' )->default( '' );
            $table->unsignedInteger( 'starttime' )->default( 0 );
            $table->unsignedInteger( 'endtime' )->default( 0 );
            $table->boolean( 'extendToSubpages' )->default( false );
        } );

        $db = DB::connection( 'typo3' );
        foreach( [
            ['uid' => 7, 'pid' => 1, 'title' => 'Members', 'slug' => '/members', 'fe_group' => '1', 'extendToSubpages' => 1, 'sorting' => 4],
            ['uid' => 8, 'pid' => 7, 'title' => 'Area', 'slug' => '/members/area', 'sorting' => 1],
            ['uid' => 9, 'pid' => 1, 'title' => 'Group', 'slug' => '/group', 'fe_group' => '-2', 'sorting' => 5],
            ['uid' => 10, 'pid' => 9, 'title' => 'Open', 'slug' => '/group/open', 'sorting' => 1],
            ['uid' => 11, 'pid' => 1, 'title' => 'Guests', 'slug' => '/guests', 'fe_group' => '2,-1', 'sorting' => 6],
            ['uid' => 12, 'pid' => 1, 'title' => 'Expired', 'slug' => '/expired', 'endtime' => time() - 3600, 'sorting' => 7],
            ['uid' => 13, 'pid' => 1, 'title' => 'Scheduled', 'slug' => '/scheduled', 'starttime' => time() + 86400, 'sorting' => 8],
            ['uid' => 14, 'pid' => 1, 'title' => 'Current', 'slug' => '/current', 'starttime' => time() - 3600, 'endtime' => time() + 86400, 'sorting' => 9],
            ['uid' => 15, 'pid' => 1, 'title' => 'Kontakt', 'slug' => '/kontakt', 'sys_language_uid' => 1, 'l10n_parent' => 6, 'fe_group' => '3', 'sorting' => 3],
            ['uid' => 16, 'pid' => 1, 'title' => 'Mitglieder', 'slug' => '/mitglieder', 'sys_language_uid' => 1, 'l10n_parent' => 7, 'fe_group' => '', 'sorting' => 4],
        ] as $row ) { $db->table( 'pages' )->insert( $row ); }

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $status = fn( string $path, string $lang = 'en' ) => PageView::language( $lang )->where( 'domain', 'example.com' )->where( 'path', $path )->firstOrFail()->status;

        // pages which aren't visible for all visitors aren't published
        $this->assertStringContainsString( 'Disabled [7] Members because it is restricted to TYPO3 frontend user groups', $output );
        $this->assertStringContainsString( 'Disabled [8] Area because it is restricted to TYPO3 frontend user groups', $output );
        $this->assertStringContainsString( 'Disabled [12] Expired because it expired on ' . date( 'Y-m-d H:i', time() - 3600 ), $output );
        $this->assertStringContainsString( 'Disabled [13] Scheduled because it is scheduled for ' . date( 'Y-m-d H:i', time() + 86400 ) . ', enable it then', $output );
        $this->assertStringContainsString( 'Disabled [15] Kontakt because', $output );
        $this->assertStringContainsString( 'Disabled [16] Mitglieder because', $output );
        $this->assertSame( [0, 0, 0, 1, 1, 0, 0, 1], array_map( $status, ['members', 'members/area', 'group', 'group/open', 'guests', 'expired', 'scheduled', 'current'] ) );
        $this->assertSame( [1, 0, 0], [$status( 'contact' ), $status( 'kontakt', 'de' ), $status( 'mitglieder', 'de' )] );

        // pages are published again if the restriction is removed
        $db->table( 'pages' )->where( 'uid', 7 )->update( ['fe_group' => ''] );
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( [1, 1, 1], [$status( 'members' ), $status( 'members/area' ), $status( 'mitglieder', 'de' )] );
    }


    public function testRestrictedAndDraftContentIsSkipped(): void
    {
        $config = ['cms.allow-internal' => config( 'cms.allow-internal' ), 'cms.image.preview-sizes' => config( 'cms.image.preview-sizes' )];
        $this->beforeApplicationDestroyed( fn() => config( $config ) );
        config( ['cms.allow-internal' => true, 'cms.image.preview-sizes' => []] );
        Storage::fake( (string) config( 'cms.disks.public.name', 'public' ) );

        $png = (string) base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=' );
        Http::fake( fn() => Http::response( $png, 200, ['Content-Type' => 'image/png'] ) );

        $schema = Schema::connection( 'typo3' );
        $schema->table( 'tt_content', function( Blueprint $table ) {
            $table->string( 'fe_group' )->nullable()->default( '' );
            $table->unsignedInteger( 'starttime' )->default( 0 );
            $table->unsignedInteger( 'endtime' )->default( 0 );
            $table->unsignedInteger( 't3ver_wsid' )->default( 0 );
        } );
        $schema->table( 'sys_file_reference', fn( Blueprint $table ) => $table->unsignedInteger( 't3ver_wsid' )->default( 0 ) );

        $db = DB::connection( 'typo3' );
        $db->table( 'sys_file' )->insert( [
            ['uid' => 1, 'identifier' => '/user_upload/photo.png', 'name' => 'photo.png', 'mime_type' => 'image/png', 'extension' => 'png'],
            ['uid' => 2, 'identifier' => '/user_upload/draft.png', 'name' => 'draft.png', 'mime_type' => 'image/png', 'extension' => 'png'],
        ] );
        $db->table( 'sys_file_reference' )->insert( [
            // the draft reference comes first, so it would be used instead of the live one
            ['uid' => 1, 'uid_local' => 1, 'uid_foreign' => 40, 'tablenames' => 'tt_content', 'fieldname' => 'image', 't3ver_wsid' => 0, 'sorting_foreign' => 2],
            ['uid' => 2, 'uid_local' => 2, 'uid_foreign' => 40, 'tablenames' => 'tt_content', 'fieldname' => 'image', 't3ver_wsid' => 1, 'sorting_foreign' => 1],
        ] );
        $db->table( 'tt_content' )->insert( [
            ['uid' => 40, 'pid' => 6, 'CType' => 'image', 'bodytext' => null, 'fe_group' => '', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 1],
            ['uid' => 41, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Members only</p>', 'fe_group' => '1', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 2],
            ['uid' => 42, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Any login</p>', 'fe_group' => '-2', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 3],
            ['uid' => 43, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Expired</p>', 'fe_group' => '', 'starttime' => 0, 'endtime' => time() - 3600, 't3ver_wsid' => 0, 'sorting' => 4],
            ['uid' => 44, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Scheduled</p>', 'fe_group' => '', 'starttime' => time() + 86400, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 5],
            ['uid' => 45, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Workspace draft</p>', 'fe_group' => '', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 1, 'sorting' => 6],
            ['uid' => 46, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Hidden at login</p>', 'fe_group' => '-1', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 7],
            ['uid' => 47, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Guests and members</p>', 'fe_group' => '1,-1,2', 'starttime' => 0, 'endtime' => 0, 't3ver_wsid' => 0, 'sorting' => 8],
            ['uid' => 48, 'pid' => 6, 'CType' => 'text', 'bodytext' => '<p>Running</p>', 'fe_group' => null, 'starttime' => time() - 3600, 'endtime' => time() + 86400, 't3ver_wsid' => 0, 'sorting' => 9],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com', '--file-base' => 'http://127.0.0.1/fileadmin'] ) );
        $content = json_encode( PageView::where( 'domain', 'example.com' )->where( 'path', 'contact' )->firstOrFail()->content );

        // only live content visible for all visitors is imported
        foreach( ['Hidden at login', 'Guests and members', 'Running'] as $text ) {
            $this->assertStringContainsString( $text, (string) $content );
        }

        foreach( ['Members only', 'Any login', 'Expired', 'Scheduled', 'Workspace draft'] as $text ) {
            $this->assertStringNotContainsString( $text, (string) $content );
        }

        // file references of workspaces aren't imported
        $this->assertSame( ['photo.png'], File::orderBy( 'name' )->pluck( 'name' )->all() );
    }


    public function testTranslatedPagesSkipDefaultLanguageColumns(): void
    {
        $db = DB::connection( 'typo3' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:t3-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )
            ->filter( fn( $query ) => preg_match( '/^select .* from "pages" where .*"sys_language_uid" > \?/', $query['query'] ) === 1 );
        $selects = $queries->map( fn( $query ) => explode( ' from ', $query['query'] )[0] );
        $db->disableQueryLog();

        // only the translations of the imported pages which have translations are fetched together
        $this->assertStringContainsString( '"l10n_parent" in (?, ?)', $queries->last()['query'] ?? '' );
        $this->assertSame( [1, 2], array_slice( $queries->last()['bindings'] ?? [], -2 ) );

        // only the URL paths of all translations are fetched, the other columns only for the imported pages
        $this->assertCount( 2, $selects );
        $this->assertSame( 'select "uid", "slug", "sys_language_uid", "l10n_parent"', $selects->first() );

        // the columns only used in the default language aren't fetched for the translations
        $this->assertDoesNotMatchRegularExpression( '/\*|"(crdate|content_from_pid|backend_layout|backend_layout_next_level|TSconfig)"/', $selects->last() ?? '' );

        foreach( ['uid', 'pid', 'title', 'nav_title', 'seo_title', 'slug', 'doktype', 'hidden', 'nav_hide', 'shortcut', 'url', 'sorting', 'sys_language_uid', 'l10n_parent'] as $column ) {
            $this->assertStringContainsString( '"' . $column . '"', $selects->last() ?? '' );
        }

        $page = PageView::where( 'domain', 'example.com' )->where( 'path', 'about' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertSame( 'Willkommen', $de->content[0]->data->title ?? null );
        $this->assertSame( 'a-propos', PageView::language( 'fr' )->findOrFail( $page->id )->path );
    }

    /**
     * Returns the page UIDs of a query fetching the tt_content records of pages.
     *
     * @param array{query: string, bindings: array<int, mixed>} $query
     * @return array<int, mixed> Page UIDs or an empty array for other queries
     */
    protected function contentPids( array $query ): array
    {
        if( !preg_match( '/from "tt_content" where .*"pid" in \(([?, ]+)\) and "sys_language_uid"/', $query['query'], $match ) ) {
            return [];
        }

        // the bindings of the "deleted" and "hidden" conditions are first
        return array_slice( $query['bindings'], 2, substr_count( $match[1], '?' ) );
    }


    /**
     * Returns the logged queries with the identifiers quoted like in SQLite and PostgreSQL.
     *
     * @return Collection<int, array{query: string, bindings: array<mixed>, time: float|null}>
     */
    protected function queries( \Illuminate\Database\Connection $db ): Collection
    {
        return Collection::make( $db->getQueryLog() )->map( fn( $query ) => ['query' => $this->sql( $query['query'] )] + $query );
    }


    /**
     * Returns the SQL statement with the identifiers quoted like in SQLite and PostgreSQL.
     *
     * MariaDB and MySQL quote identifiers with backticks, SQL Server with brackets and uses "top" instead of "limit".
     */
    protected function sql( string $sql ): string
    {
        return (string) preg_replace( '/^select top \d+ /', 'select ', strtr( $sql, ['`' => '"', '[' => '"', ']' => '"'] ) );
    }


    /**
     * Returns the number of page queries containing the SQL fragment and one of the values.
     *
     * @param array<int, array{0: string, 1: array<int, mixed>}> $queries
     * @param array<int, string> $values
     */
    protected function pageQueries( array $queries, string $sql, array $values ): int
    {
        return count( array_filter( $queries, fn( $query ) => str_contains( $query[0], 'cms_page_view' )
            && str_contains( $query[0], $sql ) && array_intersect( $values, $query[1] ) ) );
    }
}
