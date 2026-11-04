<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Commands\DrupalImport;
use Aimeos\Cms\Facades\Pages as PageResource;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Import\Drupal8;
use Aimeos\Cms\Import\DrupalAccess;
use Aimeos\Cms\Import\DrupalContent;
use Aimeos\Cms\Import\DrupalData;
use Aimeos\Cms\Import\DrupalDomains;
use Aimeos\Cms\Import\DrupalLanguages;
use Aimeos\Cms\Import\DrupalLayout;
use Aimeos\Cms\Import\DrupalPages;
use Aimeos\Cms\Import\DrupalPrune;
use Aimeos\Cms\Import\DrupalRedirects;
use Aimeos\Cms\Import\DrupalSite;
use Aimeos\Cms\Import\DrupalUrls;
use Aimeos\Cms\Import\DrupalViews;
use Aimeos\Cms\Import\Pages;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Translation\Hashes;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;


class DrupalImportTest extends ImportTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'database.connections.drupal', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'foreign_key_constraints' => true,
        ] );
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


    protected function setUp(): void
    {
        parent::setUp();

        $schema = Schema::connection( 'drupal' );

        $schema->create( 'node_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'type' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->boolean( 'status' )->default( true );
            $table->unsignedInteger( 'created' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $schema->create( 'node__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' )->default( 'basic_html' );
        } );

        $schema->create( 'path_alias', function( Blueprint $table ) {
            $table->id();
            $table->string( 'path' );
            $table->string( 'alias' );
            $table->string( 'langcode' );
            $table->boolean( 'status' )->default( true );
        } );

        $schema->create( 'file_managed', function( Blueprint $table ) {
            $table->id( 'fid' );
            $table->string( 'filename' );
            $table->string( 'filemime' );
            $table->string( 'uri' );
            $table->boolean( 'status' )->default( true );
        } );

        $db = DB::connection( 'drupal' );

        $db->table( 'node_field_data' )->insert( [
            ['nid' => 1, 'type' => 'page', 'langcode' => 'en', 'title' => 'About', 'default_langcode' => 1],
            ['nid' => 1, 'type' => 'page', 'langcode' => 'de', 'title' => 'Über uns', 'default_langcode' => 0],
            ['nid' => 1, 'type' => 'page', 'langcode' => 'fr', 'title' => 'À propos', 'default_langcode' => 0],
            ['nid' => 1, 'type' => 'page', 'langcode' => 'zxx', 'title' => 'Not applicable', 'default_langcode' => 0],
        ] );

        $db->table( 'node__body' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'body_value' => '<h2>Welcome</h2><p>Hello</p>'],
            ['entity_id' => 1, 'langcode' => 'de', 'body_value' => '<h2>Willkommen</h2><p>Hallo</p>'],
            ['entity_id' => 1, 'langcode' => 'fr', 'body_value' => '<p>Bonjour</p>'],
        ] );

        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/1', 'alias' => '/about', 'langcode' => 'en'],
            ['path' => '/node/1', 'alias' => '/ueber-uns', 'langcode' => 'de'],
        ] );

        $app = $this->app ?? throw new \RuntimeException( 'Application not initialized' );
        Artisan::registerCommand( $app->make( DrupalImport::class ) );
    }


    public function testImportTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Found 2 Drupal node translations', $output );
        $this->assertStringContainsString( 'unknown Drupal languages [zxx]', $output );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $variants = PageVariant::where( 'page_id', $page->id )->get()->keyBy( 'lang' );

        $this->assertEquals( ['de', 'en', 'fr'], $variants->keys()->sort()->values()->all() );
        $this->assertSame( 'Welcome', $page->content[0]->data->title ?? null );

        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $hashes = Hashes::page( $page->only( PageVariant::SYNC_FIELDS ), $page->content, $page->meta, $page->config );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'ueber-uns', $de->path );
        $this->assertSame( 'example.com', $de->domain );
        $this->assertSame( 'Willkommen', $de->content[0]->data->title ?? null );
        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $de->content, 'id' ) );
        $this->assertFalse( $variants['de']?->stale );
        $this->assertEquals( $hashes, $variants['de']->hashes );

        $fr = PageView::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( 'à-propos', $fr->path );
        $this->assertCount( 1, (array) $fr->content );
        $this->assertTrue( $variants['fr']?->stale );
        $this->assertEmpty( $variants['fr']->hashes );
    }


    public function testFetchesTranslationsOfNextNodesInChunks(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( array_merge(
            array_map( fn( $nid ) => ['nid' => $nid, 'type' => 'page', 'langcode' => 'en', 'title' => "Page {$nid}", 'default_langcode' => 1], range( 2, 140 ) ),
            array_map( fn( $nid ) => ['nid' => $nid, 'type' => 'page', 'langcode' => 'de', 'title' => "Seite {$nid}", 'default_langcode' => 0], range( 2, 130 ) ),
        ) );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $sizes = $this->queries( $db )->pluck( 'query' )
            ->filter( fn( $query ) => preg_match( '/ from "node_field_data" where "default_langcode" = \? .*"nid" in \(([?, ]+)\)/', $query ) === 1 )
            ->map( fn( $query ) => preg_match( '/"nid" in \(([?, ]+)\)/', $query, $match ) ? substr_count( $match[1], '?' ) : 0 )->values()->all();
        $db->disableQueryLog();

        // all translations are read once for their titles, then again for the next translated nodes imported
        $this->assertSame( [140, 100, 30], $sizes, $output );
        $this->assertStringContainsString( 'Found 131 Drupal node translations', $output );
        $this->assertSame( 129, PageView::language( 'de' )->where( 'title', 'like', 'Seite %' )->count(), $output );
        $this->assertSame( 'Über uns', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'About' )->firstOrFail()->id )->title );
    }


    public function testDatesAndVersionsAreOnlyWrittenIfNeeded(): void
    {
        Schema::connection( 'drupal' )->table( 'node_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );
        DB::connection( 'drupal' )->table( 'node_field_data' )->update( ['created' => 1600000000, 'changed' => 1700000000] );

        $run = function(): array {
            $db = DB::connection( config( 'cms.db', 'sqlite' ) );
            $db->enableQueryLog();
            $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
            $queries = $this->queries( $db )->pluck( 'query' );
            $db->flushQueryLog();
            $db->disableQueryLog();

            return [
                $queries->filter( fn( $query ) => str_starts_with( $query, 'UPDATE "cms_page_variants" SET "created_at"' ) )->count(),
                $queries->filter( fn( $query ) => str_contains( $query, 'from "cms_versions"' ) && str_contains( $query, '"versionable_id" = ?' ) )->count(),
                $queries->filter( fn( $query ) => str_starts_with( $query, 'select distinct "versionable_id" from "cms_versions"' ) )->count(),
            ];
        };

        // the dates are only written if they differ, all at once, and the pending versions are fetched at once
        $this->assertSame( [1, 0, 1], $run() );
        $this->assertSame( [0, 0, 1], $run() );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        foreach( PageVariant::where( 'page_id', $page->id )->get() as $variant )
        {
            $this->assertSame( '2020-09-13 12:26:40', $variant->created_at?->format( 'Y-m-d H:i:s' ) );
            $this->assertSame( '2023-11-14 22:13:20', $variant->updated_at?->format( 'Y-m-d H:i:s' ) );
        }

        // pending versions of previous imports are still removed
        $version = PageVariant::whereKey( $page->variant_id )->firstOrFail()->versions()->firstOrFail()->replicate();
        $version->published = false;
        $version->editor = 'drupal-import';
        $version->save();

        $this->assertSame( [0, 2, 1], $run() );
        $this->assertSame( 0, PageVariant::whereKey( $page->variant_id )->firstOrFail()->versions()->where( 'published', false )->count() );
    }


    public function testImportTranslationsOfSameLanguage(): void
    {
        DB::connection( 'drupal' )->table( 'node_field_data' )->insert( ['nid' => 1, 'type' => 'page', 'langcode' => 'DE', 'title' => 'Über uns neu', 'default_langcode' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // later translations of the same language update the variant saved before
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 1, PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->count() );
        $this->assertContains( PageView::language( 'de' )->findOrFail( $page->id )->title, ['Über uns', 'Über uns neu'] );
    }


    public function testEmbedRequiresRenderer(): void
    {
        $embeds = null;
        $method = new \ReflectionMethod( DrupalContent::class, 'embed' );
        $embed = $method->invokeArgs( null, [&$embeds] );

        $this->assertInstanceOf( \Closure::class, $embed );
        $this->expectException( \LogicException::class );
        $this->expectExceptionMessage( 'Embedded entities are rendered before the renderer is created' );

        $embed( '<drupal-media data-entity-uuid="x"></drupal-media>', 'en' );
    }


    public function testImportTranslationsLanguageOption(): void
    {
        // links to the domain of the translation are local links
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'langcode', 'de' )->update( ['body_value' => '<p><a href="https://example.de/node/1">Hallo</a> <a href="https://example.com/node/1">EN</a></p>'] );
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p><a href="https://example.de/node/1">DE</a></p>'] );
        DB::connection( 'drupal' )->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'und', 'title' => 'Neutral', 'default_langcode' => 1] );
        DB::connection( 'drupal' )->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'und', 'body_value' => '<p><a href="https://example.de/node/1">DE</a></p>'] );

        $result = Artisan::call( 'cms:drupal-import', [
            '--domain' => 'example.com',
            '--language' => ['de:de:https://example.de/', 'fr:fr-CA:https://example.ca/'],
        ] );

        $this->assertSame( 0, $result );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 'example.de', PageView::language( 'de' )->findOrFail( $page->id )->domain );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
        $this->assertStringContainsString( 'href=\\"\\/ueber-uns\\">Hallo', (string) json_encode( PageView::language( 'de' )->findOrFail( $page->id )->content ) );
        // links to the domains of other languages stay absolute
        $this->assertStringContainsString( 'href=\\"https:\\/\\/example.com\\/node\\/1\\">EN', (string) json_encode( PageView::language( 'de' )->findOrFail( $page->id )->content ) );
        $this->assertStringContainsString( 'href=\\"https:\\/\\/example.de\\/node\\/1\\">DE', (string) json_encode( $page->content ) );
        $this->assertStringContainsString( 'href=\\"https:\\/\\/example.de\\/node\\/1\\">DE', (string) json_encode( PageView::where( 'name', 'Neutral' )->firstOrFail()->content ) );
        $this->assertSame( 'à-propos', PageView::language( 'fr-CA' )->findOrFail( $page->id )->path );
    }


    public function testImportTranslationsInvalidLanguage(): void
    {
        $this->expectException( \InvalidArgumentException::class );
        Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--language' => ['de:Deutsch']] );
    }


    public function testImportAliasNewest(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'path_alias' )->insert( ['path' => '/node/1', 'alias' => '/about-us', 'langcode' => 'en'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, 'from "path_alias" where "status" = ? and "path" in' ) );
        $db->disableQueryLog();

        // only the used columns of the aliases are fetched
        $this->assertNotEmpty( $queries );
        $this->assertSame( ['select "path", "alias", "langcode"'], $queries->map( fn( $query ) => explode( ' from ', $query )[0] )->unique()->values()->all() );
        $this->assertSame( 'about-us', PageView::where( 'name', 'About' )->firstOrFail()->path );
    }


    public function testImportUrlAlias(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->drop( 'path_alias' );
        $schema->create( 'url_alias', function( Blueprint $table ) {
            $table->id( 'pid' );
            $table->string( 'source' );
            $table->string( 'alias' );
            $table->string( 'langcode' );
        } );

        DB::connection( 'drupal' )->table( 'url_alias' )->insert( [
            ['source' => '/node/1', 'alias' => '/about', 'langcode' => 'en'],
            ['source' => '/node/1', 'alias' => '/ueber-uns', 'langcode' => 'de'],
        ] );

        $db = DB::connection( 'drupal' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, 'from "url_alias" where "source" in' ) );
        $db->disableQueryLog();

        // only the used columns of the aliases are fetched
        $this->assertNotEmpty( $queries );
        $this->assertSame( ['select "source" as "path", "alias", "langcode"'], $queries->map( fn( $query ) => explode( ' from ', $query )[0] )->unique()->values()->all() );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 'about', $page->path );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testImportPlainText(): void
    {
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'langcode', 'en' )
            ->update( ['body_value' => "Line 1\nLine 2\n\n<b>Para</b> (see https://example.com/a?b=1&c=2). Mail info@example.com or www.example.org!", 'body_format' => 'plain_text'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        $this->assertStringContainsString( 'Line 1<br', $content[0]->data->text ?? '' );
        $text = $content[1]->data->text ?? '';

        $this->assertStringContainsString( '&lt;b&gt;Para&lt;/b&gt;', $text );
        $this->assertStringContainsString( '(see <a href="https://example.com/a?b=1&amp;c=2">https://example.com/a?b=1&amp;c=2</a>).', $text );
        $this->assertStringContainsString( '<a href="mailto:info@example.com">info@example.com</a>', $text );
        $this->assertStringContainsString( '<a href="http://www.example.org">www.example.org</a>!', $text );
    }


    public function testImportMedia(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'media' );
        $schema->create( 'media__field_media_image', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_media_image_target_id' );
            $table->string( 'field_media_image_alt' )->default( '' );
        } );
        $this->tables( 'media__field_media_document' );

        $db = DB::connection( 'drupal' );
        $db->table( 'media' )->insert( [
            ['mid' => 1, 'bundle' => 'image', 'uuid' => 'img-uuid'],
            ['mid' => 2, 'bundle' => 'document', 'uuid' => 'doc-uuid'],
            ['mid' => 3, 'bundle' => 'image', 'uuid' => 'tmp-uuid'],
        ] );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'photo.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://photo.jpg', 'status' => 1],
            ['fid' => 2, 'filename' => 'doc.pdf', 'filemime' => 'application/pdf', 'uri' => 'private://doc.pdf', 'status' => 1],
            ['fid' => 3, 'filename' => 'temp.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://temp.jpg', 'status' => 0],
        ] );
        $db->table( 'media__field_media_image' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_media_image_target_id' => 1, 'field_media_image_alt' => 'Photo'],
            ['entity_id' => 3, 'langcode' => 'en', 'field_media_image_target_id' => 3, 'field_media_image_alt' => 'Temporary'],
        ] );
        $db->table( 'media__field_media_document' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'field_media_document_target_id' => 2] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Intro</p>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="img-uuid" data-caption="A &lt;em&gt;photo&lt;/em&gt;"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="doc-uuid"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="missing"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="tmp-uuid"></drupal-media>'
            . '<img src="/drupal/sites/default/files/inline-images/inline.png" />'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com:8080/drupal/sites/default/files'] ) );

        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped embedded media missing', $output );
        $this->assertStringContainsString( 'Private Drupal files', $output );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        $this->assertCount( 4, $content );
        $this->assertSame( 'image-text', $content[1]->type );
        $this->assertSame( 'A *photo*', $content[1]->data->text ?? null );
        $this->assertSame( 'file', $content[2]->type );
        $this->assertSame( 'https://example.com:8080/drupal/system/files/doc.pdf', File::where( 'id', $content[2]->data->file->id )->firstOrFail()->path );
        $this->assertSame( 'https://example.com:8080/drupal/sites/default/files/photo.jpg', File::where( 'id', $content[1]->data->file->id )->firstOrFail()->path );
        $this->assertSame( 'https://example.com:8080/drupal/sites/default/files/inline-images/inline.png', File::where( 'id', $content[3]->data->file->id )->firstOrFail()->path );
        $this->assertNull( File::where( 'name', 'like', '%temp%' )->first() );
    }


    public function testImportMediaEmbedsAtOnce(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'media' );
        $schema->create( 'media__field_media_image', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_media_image_target_id' );
            $table->string( 'field_media_image_alt' )->default( '' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'media' )->insert( [
            ['mid' => 1, 'bundle' => 'image', 'uuid' => 'one-uuid'],
            ['mid' => 2, 'bundle' => 'image', 'uuid' => 'two-uuid'],
            ['mid' => 3, 'bundle' => 'image', 'uuid' => 'three-uuid'],
        ] );
        $db->table( 'file_managed' )->insert( array_map( fn( $id ) => ['fid' => $id, 'filename' => "photo{$id}.jpg", 'filemime' => 'image/jpeg', 'uri' => "public://photo{$id}.jpg", 'status' => 1], [1, 2, 3] ) );
        $db->table( 'media__field_media_image' )->insert( array_map( fn( $id ) => ['entity_id' => $id, 'langcode' => 'en', 'field_media_image_target_id' => $id, 'field_media_image_alt' => "Photo {$id}"], [1, 2, 3] ) );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Second', 'default_langcode' => 1] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Intro</p>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="one-uuid"></drupal-media>'
            . '<drupal-entity data-entity-type="media" data-entity-id="2"></drupal-entity>'] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p>Second</p>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="three-uuid"></drupal-media>'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/files'] ) );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        // the media embedded into the texts of all nodes and their fields are fetched at once
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_contains( $query, 'from "media" where "uuid"' ) ) );
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_contains( $query, 'from "media" where "mid"' ) ) );
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_contains( $query, 'from "media__field_media_image"' ) ) );

        $first = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;
        $second = (array) PageView::where( 'name', 'Second' )->firstOrFail()->content;
        $this->assertSame( ['image', 'image'], array_slice( array_column( $first, 'type' ), 1 ) );
        $this->assertSame( ['image'], array_slice( array_column( $second, 'type' ), 1 ) );
        $this->assertSame( 'https://example.com/files/photo3.jpg', File::where( 'id', $second[1]->data->file->id )->firstOrFail()->path );
    }


    public function testImportMenu(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Jobs', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/3', 'alias' => '/jobs', 'langcode' => 'en'] );
        $db->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'a', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'internal:/about', 'parent' => null, 'default_langcode' => 1],
            ['uuid' => 'a', 'langcode' => 'de', 'title' => 'Über', 'menu_name' => 'main', 'link__uri' => 'entity:node/2', 'parent' => null, 'default_langcode' => 0],
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'Our team', 'menu_name' => 'main', 'link__uri' => 'route:entity.node.canonical;node=2', 'parent' => 'menu_link_content:a', 'default_langcode' => 1],
            ['uuid' => 'c', 'langcode' => 'en', 'title' => 'Jobs', 'menu_name' => 'main', 'link__uri' => 'internal:/jobs', 'parent' => 'menu_link_content:a', 'default_langcode' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $about = PageView::where( 'name', 'About' )->firstOrFail();

        $team = PageView::where( 'title', 'Team' )->firstOrFail();

        $this->assertSame( 'Our team', $team->name );
        $this->assertSame( $about->id, $team->parent_id );
        $this->assertSame( $about->id, PageView::where( 'name', 'Jobs' )->firstOrFail()->parent_id );
        $this->assertSame( 'Über', PageView::language( 'de' )->findOrFail( $about->id )->name );
        $this->assertSame( 'À propos', PageView::language( 'fr' )->findOrFail( $about->id )->name );
    }


    public function testImportMediaTypeConfig(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config', 'media' );
        $schema->create( 'media__field_media_file', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_media_file_target_id' );
        } );
        $longField = 'field_media_remote_video_from_external_provider';
        $schema->create( 'media__' . substr( hash( 'sha256', 'storage-uuid' ), 0, 10 ), function( Blueprint $table ) use ( $longField ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( $longField . '_value' );
        } );
        $this->tables( 'media__field_media_oembed_video' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'media.type.pdf', 'data' => serialize( ['id' => 'pdf', 'source' => 'file', 'source_configuration' => ['source_field' => 'field_media_file']] )],
            ['name' => 'media.type.remote_video', 'data' => serialize( ['id' => 'remote_video', 'source' => 'oembed:video', 'source_configuration' => ['source_field' => 'field_media_oembed_video']] )],
            ['name' => 'media.type.vimeo', 'data' => serialize( ['id' => 'vimeo', 'source' => 'oembed:video', 'source_configuration' => ['source_field' => $longField]] )],
            ['name' => 'field.storage.media.' . $longField, 'data' => serialize( ['uuid' => 'storage-uuid', 'field_name' => $longField] )],
        ] );
        $db->table( 'media' )->insert( [
            ['mid' => 1, 'bundle' => 'pdf', 'uuid' => 'pdf-uuid'],
            ['mid' => 2, 'bundle' => 'remote_video', 'uuid' => 'video-uuid'],
            ['mid' => 3, 'bundle' => 'unknown', 'uuid' => 'unknown-uuid'],
            ['mid' => 4, 'bundle' => 'vimeo', 'uuid' => 'vimeo-uuid'],
            ['mid' => 5, 'bundle' => 'remote_video', 'uuid' => 'other-uuid'],
        ] );
        $db->table( 'media__' . substr( hash( 'sha256', 'storage-uuid' ), 0, 10 ) )
            ->insert( ['entity_id' => 4, 'langcode' => 'en', $longField . '_value' => 'https://vimeo.com/123'] );
        $db->table( 'file_managed' )->insert( ['fid' => 1, 'filename' => 'doc.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://doc.pdf'] );
        $db->table( 'media__field_media_file' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_media_file_target_id' => 1] );
        $db->table( 'media__field_media_oembed_video' )->insert( [
            ['entity_id' => 2, 'langcode' => 'en', 'field_media_oembed_video_value' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=10'],
            ['entity_id' => 5, 'langcode' => 'en', 'field_media_oembed_video_value' => 'https://video.example.org/watch/5'],
        ] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => ''
            . '<drupal-media data-entity-type="media" data-entity-uuid="pdf-uuid"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="video-uuid"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="unknown-uuid"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="vimeo-uuid"></drupal-media>'
            . '<drupal-media data-entity-type="media" data-entity-uuid="other-uuid"></drupal-media>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );
        $this->assertStringContainsString( 'Unsupported Drupal media type "unknown"', Artisan::output() );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        $this->assertCount( 4, $content );
        $this->assertSame( 'file', $content[0]->type );
        $this->assertSame( 'https://example.com/sites/default/files/doc.pdf', File::where( 'id', $content[0]->data->file->id )->firstOrFail()->path );
        $this->assertStringContainsString( '<iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"', $content[1]->data->text );
        // remote videos are embedded
        $this->assertStringContainsString( '<iframe src="https://player.vimeo.com/video/123"', $content[2]->data->text );
        // other remote videos are linked
        $this->assertSame( '<p><a href="https://video.example.org/watch/5">https://video.example.org/watch/5</a></p>', $content[3]->data->text );
    }


    public function testImportNoDrupal(): void
    {
        Schema::connection( 'drupal' )->drop( 'node_field_data' );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'only Drupal 7 and newer are supported', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testImportMissingFileBase(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'No --file-base given', Artisan::output() );
    }


    public function testImportRestrictedHtmlCaption(): void
    {
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'langcode', 'en' )->update( [
            'body_value' => "Visit https://example.com today\n\n<img src=\"/sites/default/files/a.png\" alt=\"A\" data-caption=\"A &lt;em&gt;nice&lt;/em&gt; view\" />",
            'body_format' => 'restricted_html',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;
        $this->assertCount( 2, $content );
        $this->assertStringContainsString( '<a href="https://example.com">https://example.com</a> today', $content[0]->data->text ?? '' );
        $this->assertSame( 'image-text', $content[1]->type );
        $this->assertSame( 'A *nice* view', $content[1]->data->text ?? null );
    }


    public function testImportAlignedImages(): void
    {
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'langcode', 'en' )->update( [
            'body_value' => '<p><img src="/sites/default/files/a.png" alt="A" data-align="left"></p><p>Beside <strong>A</strong></p>'
                . '<img src="/sites/default/files/b.png" alt="B" data-align="right" data-caption="Caption B" />'
                . '<p><img src="/sites/default/files/c.png" alt="C" class="align-center"></p><p>Below C</p>'
                . '<img src="/sites/default/files/d.png" alt="D" style="float: left" /><h2>Title</h2><img src="/sites/default/files/e.png" alt="E" class="align-right">'
                . "\n\n<p><img src=\"/sites/default/files/f.png\" alt=\"F\" style=\"float:right\"></p>\n\n<p> </p>\n\n<p>Beside F</p>"
                . '<img src="/sites/default/files/g.png" alt="G" data-align="left" /><pre><code>echo 1;</code></pre>',
            'body_format' => 'full_html',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $content = array_values( (array) json_decode( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ), true ) );

        // left and right aligned images are next to the following text, images followed by other elements stay images
        $this->assertSame( ['image-text', 'image-text', 'image', 'html', 'image', 'heading', 'image', 'image-text', 'image', 'code'], array_column( $content, 'type' ) );
        $this->assertSame( ['start', 'Beside **A**'], [$content[0]['data']['position'], $content[0]['data']['text']] );
        $this->assertSame( ['end', 'Caption B'], [$content[1]['data']['position'], $content[1]['data']['text']] );
        $this->assertSame( File::where( 'path', 'like', '%/a.png' )->value( 'id' ), $content[0]['data']['file']['id'] );
        $this->assertStringContainsString( 'Below C', $content[3]['data']['text'] );

        // empty paragraphs between image and text are skipped
        $this->assertSame( ['end', 'Beside F'], [$content[7]['data']['position'], $content[7]['data']['text']] );
    }


    public function testImportNestedAliasUnpublished(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'status' => 1, 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Draft', 'status' => 0, 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/about/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/about/team/draft', 'langcode' => 'en'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--unpublished' => true] ) );

        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $team = PageView::where( 'name', 'Team' )->firstOrFail();
        $draft = PageView::where( 'name', 'Draft' )->firstOrFail();

        $this->assertSame( 'about/team', $team->path );
        $this->assertSame( $about->id, $team->parent_id );
        $this->assertSame( $team->id, $draft->parent_id );
        $this->assertSame( 0, (int) $draft->status );
    }


    public function testImportFailedParentOnce(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'status' => 1, 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'News', 'status' => 1, 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/about/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/about/news', 'langcode' => 'en'],
        ] );

        // the page of the parent node changed in Pagible can't be imported
        PageVariant::whereKey( PageView::where( 'name', 'About' )->firstOrFail()->variant_id )->update( ['editor' => 'someone'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // the failed parent node isn't imported again for each of its children
        $this->assertSame( 1, substr_count( $output, 'Failed to import [1] About' ) );
        $this->assertStringContainsString( '1 imports failed', $output );
        $this->assertTrue( PageView::where( 'name', 'Team' )->exists() );
        $this->assertTrue( PageView::where( 'name', 'News' )->exists() );
    }


    public function testImportFailedMenuNodeOnce(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        DB::connection( 'drupal' )->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'a', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'weight' => 0],
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'About us', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'weight' => 1],
        ] );

        // the page of the node changed in Pagible can't be imported
        PageVariant::whereKey( PageView::where( 'name', 'About' )->firstOrFail()->variant_id )->update( ['editor' => 'someone'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--menu' => 'main'] ) );

        // the failed node isn't imported again for its second menu link
        $this->assertSame( 1, substr_count( Artisan::output(), 'Failed to import [1] About' ) );
    }


    public function testImportUnpublishedSkipped(): void
    {
        DB::connection( 'drupal' )->table( 'node_field_data' )
            ->insert( ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Draft', 'status' => 0, 'default_langcode' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertNull( PageView::where( 'name', 'Draft' )->first() );
    }


    public function testImportUnpublishedTranslation(): void
    {
        DB::connection( 'drupal' )->table( 'node_field_data' )->where( 'langcode', 'fr' )->update( ['status' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertEquals( ['de', 'en'], PageVariant::where( 'page_id', $page->id )->pluck( 'lang' )->sort()->values()->all() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--unpublished' => true] ) );

        $this->assertEquals( ['de', 'en', 'fr'], PageVariant::where( 'page_id', $page->id )->pluck( 'lang' )->sort()->values()->all() );
        $this->assertSame( 0, (int) PageView::language( 'fr' )->findOrFail( $page->id )->status );
    }


    public function testImportMenuOption(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'a', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'footer', 'link__uri' => 'entity:node/1', 'parent' => null],
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'Team', 'menu_name' => 'footer', 'link__uri' => 'entity:node/2', 'parent' => 'menu_link_content:a'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--menu' => 'footer'] ) );

        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( $about->id, PageView::where( 'name', 'Team' )->firstOrFail()->parent_id );
    }


    public function testImportRedirects(): void
    {
        Schema::connection( 'drupal' )->create( 'redirect', function( Blueprint $table ) {
            $table->id( 'rid' );
            $table->string( 'redirect_source__path' );
            $table->string( 'redirect_redirect__uri' );
            $table->string( 'language' )->default( 'und' );
            $table->integer( 'status_code' )->default( 301 );
            $table->boolean( 'enabled' )->default( true );
        } );

        DB::connection( 'drupal' )->table( 'redirect' )->insert( [
            ['redirect_source__path' => 'old-about', 'redirect_redirect__uri' => 'entity:node/1', 'enabled' => 1],
            ['redirect_source__path' => 'external', 'redirect_redirect__uri' => 'https://example.org/x', 'enabled' => 1],
            ['redirect_source__path' => 'anchor', 'redirect_redirect__uri' => 'entity:node/1?a=b#top', 'enabled' => 1],
            ['redirect_source__path' => 'disabled', 'redirect_redirect__uri' => 'internal:/about', 'enabled' => 0],
            ['redirect_source__path' => 'about', 'redirect_redirect__uri' => 'internal:/elsewhere', 'enabled' => 1],
            ['redirect_source__path' => 'home', 'redirect_redirect__uri' => 'internal:/<front>', 'enabled' => 1],
            ['redirect_source__path' => 'legacy', 'redirect_redirect__uri' => 'internal:/about', 'enabled' => 1],
        ] );
        // pages with a leading slash in their path are found too
        Pages::create( ['name' => 'Legacy', 'title' => 'Legacy', 'path' => '/legacy', 'tag' => 'page', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [] );

        $db = DB::connection( 'drupal' );
        $db->enableQueryLog();
        DB::enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--node-redirects' => true] ) );
        $selects = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "redirect"' ) );
        $paths = Collection::make( DB::getQueryLog() )->filter( fn( $query ) => str_contains( $this->sql( $query['query'] ), 'from "cms_page_view" where "domain" = ? and "path" in' ) && !preg_match( '/^select top 1 |limit 1$/', $query['query'] ) );
        DB::disableQueryLog();
        $db->disableQueryLog();

        // the pages of the redirect sources are fetched at once
        $this->assertCount( 1, $paths );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped redirect: /about', $output );
        $this->assertStringContainsString( 'Skipped redirect: /legacy', $output );

        // only the used columns of the redirects are fetched in chunks
        $this->assertSame( ['select "rid", "language", "redirect_source__path", "redirect_redirect__uri" from "redirect" where "enabled" = ? and "rid" is not null order by "rid" asc limit 1000'], $selects->values()->all() );

        $old = PageView::where( 'path', 'old-about' )->firstOrFail();

        $this->assertSame( '/about', $old->to );
        $this->assertSame( 2, (int) $old->status );
        $this->assertSame( 'https://example.org/x', PageView::where( 'path', 'external' )->firstOrFail()->to );
        $this->assertSame( '/about?a=b#top', PageView::where( 'path', 'anchor' )->firstOrFail()->to );
        $this->assertSame( '/about', PageView::where( 'path', 'node/1' )->firstOrFail()->to );
        $this->assertSame( '/', PageView::where( 'path', 'home' )->firstOrFail()->to );
        $this->assertNull( PageView::where( 'path', 'disabled' )->first() );
    }


    public function testImportMeta(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->table( 'node__body', fn( Blueprint $table ) => $table->text( 'body_summary' )->nullable() );
        $schema->create( 'node__field_tags', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_tags_target_id' );
        } );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'node__field_meta_tags' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->update( ['changed' => 1700000000] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_summary' => '<p>Summary of <b>about</b></p>'] );
        $db->table( 'node__field_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_tags_target_id' => 1],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_tags_target_id' => 2],
        ] );
        $db->table( 'taxonomy_term_field_data' )->insert( [
            ['tid' => 1, 'langcode' => 'en', 'name' => 'Company'],
            ['tid' => 2, 'langcode' => 'en', 'name' => 'Team'],
        ] );
        $db->table( 'node__field_meta_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'de', 'field_meta_tags_value' => json_encode( ['description' => 'Über die Firma', 'keywords' => '[node:url]'] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 'Summary of about', $page->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'Company, Team', $page->meta->{'meta-tags'}->data->keywords ?? null );
        $this->assertSame( '2023-11-14 22:13:20', $page->variant?->updated_at?->format( 'Y-m-d H:i:s' ) );

        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über die Firma', $de->meta->{'meta-tags'}->data->description ?? null );
        $this->assertObjectNotHasProperty( 'keywords', $de->meta->{'meta-tags'}->data );
    }


    public function testImportParagraphs(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'node__field_content', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_content_target_id' );
            $table->unsignedInteger( 'field_content_target_revision_id' );
        } );
        $schema->create( 'node__field_subtitle', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( 'field_subtitle_value' );
        } );
        $schema->create( 'paragraphs_item_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'type' );
            $table->string( 'langcode' );
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $schema->create( 'paragraph__field_text', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'field_text_value' );
            $table->string( 'field_text_format' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_subtitle', 'data' => serialize( ['type' => 'string'] )],
            ['name' => 'field.storage.node.field_content', 'data' => serialize( ['type' => 'entity_reference_revisions', 'settings' => ['target_type' => 'paragraph']] )],
            ['name' => 'field.storage.paragraph.field_text', 'data' => serialize( ['type' => 'text_long'] )],
            ['name' => 'field.field.paragraph.text.field_text', 'data' => serialize( ['field_name' => 'field_text'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 2], 'field_content' => ['weight' => 1], 'field_subtitle' => ['weight' => 0], 'links' => ['weight' => 3],
            ]] )],
        ] );
        $db->table( 'node__field_subtitle' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_subtitle_value' => 'Sub <title>'] );
        $db->table( 'node__field_content' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_content_target_id' => 1, 'field_content_target_revision_id' => 1],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_content_target_id' => 2, 'field_content_target_revision_id' => 2],
        ] );
        $db->table( 'paragraphs_item_field_data' )->insert( [
            ['id' => 1, 'type' => 'text', 'langcode' => 'en', 'status' => 1],
            ['id' => 2, 'type' => 'text', 'langcode' => 'en', 'status' => 0],
        ] );
        $db->table( 'paragraph__field_text' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_text_value' => '<p>Paragraph one</p>', 'field_text_format' => 'basic_html'],
            ['entity_id' => 2, 'langcode' => 'en', 'field_text_value' => '<p>Unpublished</p>', 'field_text_format' => 'basic_html'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;
        $texts = array_map( fn( $el ) => json_encode( $el->data, JSON_THROW_ON_ERROR ), $content );

        $this->assertCount( 4, $content );
        $this->assertStringContainsString( 'Sub &lt;title&gt;', $content[0]->data->text ?? '' );
        $this->assertStringContainsString( 'Paragraph one', $texts[1] );
        $this->assertStringContainsString( 'Welcome', $texts[2] );
        $this->assertStringNotContainsString( 'Unpublished', implode( '', $texts ) );
    }


    public function testImportLayoutBuilder(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->createBlockTables();
        $schema->create( 'block_content_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.field.block_content.basic.body', 'data' => serialize( ['field_name' => 'body'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( [
                'content' => ['body' => ['weight' => 0]],
                'third_party_settings' => ['layout_builder' => ['enabled' => true, 'allow_custom' => false, 'sections' => [[
                    'layout_id' => 'layout_twocol_section',
                    'components' => [
                        'c1' => ['region' => 'second', 'weight' => 0, 'configuration' => ['id' => 'field_block:node:page:body']],
                        'c2' => ['region' => 'first', 'weight' => 0, 'configuration' => ['id' => 'inline_block:basic', 'block_revision_id' => 7]],
                        'c3' => ['region' => 'first', 'weight' => 1, 'configuration' => ['id' => 'inline_block:basic', 'block_revision_id' => 8]],
                        'c4' => ['region' => 'first', 'weight' => 2, 'configuration' => ['id' => 'block_content:uuid-5']],
                        'c5' => ['region' => 'first', 'weight' => 3, 'configuration' => ['id' => 'block_content:uuid-6']],
                    ],
                ]]]],
            ] )],
        ] );
        $db->table( 'block_content_revision__body' )->insert( [
            ['entity_id' => 3, 'revision_id' => 7, 'langcode' => 'en', 'body_value' => '<p>Inline block</p>', 'body_format' => 'basic_html'],
            ['entity_id' => 4, 'revision_id' => 8, 'langcode' => 'en', 'body_value' => '<p>Second block</p>', 'body_format' => 'basic_html'],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-5', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 6, 'uuid' => 'uuid-6', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Reusable block</p>'],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Other block</p>'],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;
        $texts = (string) json_encode( $content );

        $this->assertCount( 6, $content );
        $this->assertStringContainsString( 'Inline block', $content[0]->data->text ?? '' );
        $this->assertSame( 'Welcome', $content[4]->data->title ?? null );

        foreach( ['Second block', 'Reusable block', 'Other block'] as $text ) {
            $this->assertStringContainsString( $text, $texts );
        }

        // the blocks of the layout are fetched at once
        foreach( ['block_content_revision__body', 'block_content__body', 'block_content'] as $table ) {
            $this->assertSame( 1, $queries->filter( fn( $query ) => preg_match( '/ from "' . $table . '" /', $query ) === 1 )->count(), $table );
        }
    }


    public function testImportCustomLayoutBlocksOfAllNodes(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->createBlockTables();
        $schema->create( 'block_content_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' );
        } );
        $schema->create( 'node__layout_builder__layout', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'layout_builder__layout_section' );
        } );

        $section = fn( int $revision, string $uuid ) => serialize( ['layout_id' => 'layout_onecol', 'components' => [
            'c1' => ['region' => 'content', 'weight' => 0, 'configuration' => ['id' => 'inline_block:basic', 'block_revision_id' => $revision]],
            'c2' => ['region' => 'content', 'weight' => 1, 'configuration' => ['id' => 'block_content:' . $uuid]],
        ]] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.field.block_content.basic.body', 'data' => serialize( ['field_name' => 'body'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( [
                'third_party_settings' => ['layout_builder' => ['enabled' => true, 'allow_custom' => true, 'sections' => []]],
            ] )],
        ] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );
        $db->table( 'node__layout_builder__layout' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'layout_builder__layout_section' => $section( 7, 'uuid-5' )],
            ['entity_id' => 2, 'langcode' => 'en', 'layout_builder__layout_section' => $section( 8, 'uuid-6' )],
        ] );
        $db->table( 'block_content_revision__body' )->insert( [
            ['entity_id' => 3, 'revision_id' => 7, 'langcode' => 'en', 'body_value' => '<p>Inline about</p>', 'body_format' => 'basic_html'],
            ['entity_id' => 4, 'revision_id' => 8, 'langcode' => 'en', 'body_value' => '<p>Inline team</p>', 'body_format' => 'basic_html'],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-5', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 6, 'uuid' => 'uuid-6', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Reusable about</p>'],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Reusable team</p>'],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        $about = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );
        $team = (string) json_encode( PageView::where( 'name', 'Team' )->firstOrFail()->content );

        $this->assertStringContainsString( 'Inline about', $about );
        $this->assertStringContainsString( 'Reusable about', $about );
        $this->assertStringContainsString( 'Inline team', $team );
        $this->assertStringContainsString( 'Reusable team', $team );

        // the blocks of the custom layouts of all nodes are fetched at once instead of node by node
        foreach( ['block_content_revision__body', 'block_content__body', 'block_content'] as $table ) {
            $this->assertSame( 1, $queries->filter( fn( $query ) => preg_match( '/ from "' . $table . '" /', $query ) === 1 )->count(), $table );
        }
    }


    public function testImportLanguagePrefixLinks(): void
    {
        $this->tables( 'config', 'node', 'node__field_link' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( ['nid' => 2, 'uuid' => 'node-2', 'type' => 'page', 'langcode' => 'en'] );
        $db->table( 'node__field_link' )->insert( ['entity_id' => 1, 'langcode' => 'de', 'field_link_uri' => 'entity:node/2', 'field_link_title' => 'Link'] );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_link', 'data' => serialize( ['type' => 'link'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => ['body' => ['weight' => 0], 'field_link' => ['weight' => 1]]] )],
            ['name' => 'language.negotiation', 'data' => serialize( ['url' => ['source' => 'path_prefix', 'prefixes' => ['en' => '', 'de' => 'de', 'fr' => 'fr']]] )],
        ] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1],
            ['nid' => 2, 'type' => 'page', 'langcode' => 'de', 'title' => 'Team DE', 'default_langcode' => 0],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'de'],
        ] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>'
            . '<a href="/node/2">A</a> <a href="https://example.com/de/node/2#top">B</a> <a href="/node/99">C</a> <a href="/node/2/edit">D</a></p>'] );
        $db->table( 'node__body' )->where( 'langcode', 'de' )->update( ['body_value' => '<p><a href="/node/2?x=1">E</a></p>'
            . '<drupal-entity data-entity-type="node" data-entity-uuid="node-2" data-entity-embed-display="entity_reference:entity_reference_label"></drupal-entity>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $team = PageView::where( 'name', 'Team' )->firstOrFail();

        $this->assertSame( 'about', $page->path );
        $this->assertSame( 'de/ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
        $this->assertSame( 'fr/à-propos', PageView::language( 'fr' )->findOrFail( $page->id )->path );
        $this->assertSame( 'de/team', PageView::language( 'de' )->findOrFail( $team->id )->path );

        $text = $page->content[0]->data->text ?? '';

        $this->assertStringContainsString( 'href="/team"', $text );
        $this->assertStringContainsString( 'href="/de/team#top"', $text );
        $this->assertStringContainsString( 'href="/node/99"', $text );
        $this->assertStringContainsString( 'href="/node/2/edit"', $text );
        $this->assertStringContainsString( 'href="/de/team?x=1"', PageView::language( 'de' )->findOrFail( $page->id )->content[0]->data->text ?? '' );

        // embedded entities and link fields link to the translation of the embedding page
        $texts = array_map( fn( $item ) => $item->data->text ?? '', (array) PageView::language( 'de' )->findOrFail( $page->id )->content );
        $this->assertContains( '<p><a href="/de/team">Team DE</a></p>', $texts );
        $this->assertContains( '<p><a href="/de/team">Link</a></p>', $texts );
    }


    public function testImportLinkAndUnsupportedFields(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config', 'node__field_link' );
        $schema->create( 'node__field_geo', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( 'field_geo_value' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_link', 'data' => serialize( ['type' => 'link'] )],
            ['name' => 'field.storage.node.field_geo', 'data' => serialize( ['type' => 'geolocation'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 0], 'field_link' => ['weight' => 1], 'field_geo' => ['weight' => 2],
            ]] )],
        ] );
        $db->table( 'node__field_link' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_link_uri' => 'entity:node/1', 'field_link_title' => 'Self',
                'field_link_options' => serialize( ['attributes' => ['target' => '_top', 'title' => ' ']] )],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_link_uri' => 'https://example.org', 'field_link_title' => '',
                'field_link_options' => serialize( ['attributes' => ['target' => '_blank', 'title' => 'Example site']] )],
        ] );
        $db->table( 'node__field_geo' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_geo_value' => '2024-01-01'] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )
            ->update( ['body_value' => '<p>Text <drupal-entity data-entity-type="node" data-entity-uuid="x"></drupal-entity></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped Drupal fields of type "geolocation" (e.g. node.field_geo)', $output );
        $this->assertStringContainsString( 'Skipped embedded node x (not found)', $output );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        // several links of a field are one list
        $this->assertCount( 2, $content );
        $this->assertStringContainsString( '<ul><li><a href="/about">Self</a></li><li><a href="https://example.org" target="_blank" title="Example site" rel="noreferrer noopener">https://example.org</a></li></ul>', $content[1]->data->text ?? '' );
    }


    public function testImportAddressVideoUserFields(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'users_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'uid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
        } );

        $fields = [
            'field_address' => ['given_name', 'family_name', 'organization', 'address_line1', 'postal_code', 'locality', 'country_code'],
            'field_video' => ['value'],
            'field_author' => ['target_id'],
        ];

        foreach( $fields as $field => $columns )
        {
            $schema->create( 'node__' . $field, function( Blueprint $table ) use ( $field, $columns ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );

                foreach( $columns as $column ) {
                    $table->string( $field . '_' . $column )->default( '' );
                }
            } );
        }

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_address', 'data' => serialize( ['type' => 'address'] )],
            ['name' => 'field.storage.node.field_video', 'data' => serialize( ['type' => 'video_embed_field'] )],
            ['name' => 'field.storage.node.field_author', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'user']] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 0], 'field_address' => ['weight' => 1], 'field_video' => ['weight' => 2], 'field_author' => ['weight' => 3],
            ]] )],
        ] );
        $db->table( 'users_field_data' )->insert( ['uid' => 5, 'langcode' => 'en', 'name' => 'jdoe'] );
        $db->table( 'node__field_address' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_address_given_name' => 'Jane', 'field_address_family_name' => 'Doe',
            'field_address_organization' => 'ACME', 'field_address_address_line1' => 'Main St 1', 'field_address_postal_code' => '12345',
            'field_address_locality' => 'Springfield', 'field_address_country_code' => 'US'] );
        $db->table( 'node__field_video' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_video_value' => 'https://vimeo.com/12345'] );
        $db->table( 'node__field_author' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_author_target_id' => '5'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content, JSON_UNESCAPED_SLASHES );

        $this->assertStringContainsString( 'Jane Doe<br />ACME<br />Main St 1<br />12345 Springfield<br />US', $content );
        $this->assertStringContainsString( 'player.vimeo.com/video/12345', $content );
        $this->assertStringContainsString( 'jdoe', $content );
        $this->assertLessThan( strpos( $content, 'jdoe' ), strpos( $content, 'vimeo' ) );
    }


    public function testImportValueFields(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );

        $fields = [
            'field_date' => ['value'], 'field_range' => ['value', 'end_value'], 'field_count' => ['value'],
            'field_flag' => ['value'], 'field_mail' => ['value'], 'field_color' => ['value'], 'field_ref' => ['target_id'],
        ];

        foreach( $fields as $field => $columns )
        {
            $schema->create( 'node__' . $field, function( Blueprint $table ) use ( $field, $columns ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );

                foreach( $columns as $column ) {
                    $table->string( $field . '_' . $column );
                }
            } );
        }

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'status' => 1, 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Draft', 'status' => 0, 'default_langcode' => 1],
        ] );
        $db->table( 'config' )->insert( [
            ['name' => 'system.date', 'data' => serialize( ['timezone' => ['default' => 'Europe/Berlin']] )],
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_date', 'data' => serialize( ['type' => 'datetime'] )],
            ['name' => 'field.storage.node.field_range', 'data' => serialize( ['type' => 'daterange'] )],
            ['name' => 'field.storage.node.field_count', 'data' => serialize( ['type' => 'integer'] )],
            ['name' => 'field.storage.node.field_flag', 'data' => serialize( ['type' => 'boolean'] )],
            ['name' => 'field.storage.node.field_mail', 'data' => serialize( ['type' => 'email'] )],
            ['name' => 'field.storage.node.field_color', 'data' => serialize( ['type' => 'list_string', 'settings' => ['allowed_values' => [
                ['value' => 'r', 'label' => 'Red'], ['value' => 'g', 'label' => 'Green'],
            ]]] )],
            ['name' => 'field.storage.node.field_ref', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'node']] )],
            ['name' => 'field.field.node.page.field_date', 'data' => serialize( ['label' => 'Date'] )],
            ['name' => 'field.field.node.page.field_count', 'data' => serialize( ['label' => 'Count'] )],
            ['name' => 'field.field.node.page.field_flag', 'data' => serialize( ['label' => 'Open', 'settings' => ['on_label' => 'Yes', 'off_label' => 'No']] )],
            ['name' => 'field.field.node.page.field_ref', 'data' => serialize( ['label' => 'Related'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 0, 'label' => 'hidden'], 'field_date' => ['weight' => 1, 'label' => 'inline'],
                'field_range' => ['weight' => 2], 'field_count' => ['weight' => 3, 'label' => 'hidden'],
                'field_flag' => ['weight' => 4], 'field_mail' => ['weight' => 5], 'field_color' => ['weight' => 6],
                'field_ref' => ['weight' => 7],
            ]] )],
        ] );
        $db->table( 'config' )->insert( [
            ['collection' => 'language.de', 'name' => 'field.field.node.page.field_date', 'data' => serialize( ['label' => 'Datum'] )],
            ['collection' => 'language.de', 'name' => 'field.storage.node.field_color', 'data' => serialize( ['settings' => ['allowed_values' => [
                ['label' => 'Rot'], ['label' => 'Grün'],
            ]]] )],
        ] );

        foreach( ['en', 'de'] as $lang )
        {
            $db->table( 'node__field_date' )->insert( ['entity_id' => 1, 'langcode' => $lang, 'field_date_value' => '2024-01-01T10:00:00'] );
            $db->table( 'node__field_color' )->insert( [
                ['entity_id' => 1, 'langcode' => $lang, 'delta' => 0, 'field_color_value' => 'r'],
                ['entity_id' => 1, 'langcode' => $lang, 'delta' => 1, 'field_color_value' => 'g'],
            ] );
        }

        $db->table( 'node__field_range' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_range_value' => '2024-01-01', 'field_range_end_value' => '2024-01-31'] );
        $db->table( 'node__field_count' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_count_value' => '42'] );
        $db->table( 'node__field_flag' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_flag_value' => '1'] );
        $db->table( 'node__field_mail' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_mail_value' => 'info@example.com'] );
        $db->table( 'node__field_ref' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_ref_target_id' => '2'],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_ref_target_id' => '3'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--language' => ['de:de']] ) );
        $this->assertStringNotContainsString( 'Skipped Drupal fields', Artisan::output() );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $texts = array_map( fn( $element ) => $element->data->text ?? '', array_slice( (array) $page->content, 2 ) );

        $this->assertSame( [
            '<p><strong>Date:</strong> 2024-01-01 11:00</p>',
            '<p>2024-01-01 – 2024-01-31</p>',
            '<p>42</p>',
            '<p><strong>Open:</strong> Yes</p>',
            '<p><a href="mailto:info@example.com">info@example.com</a></p>',
            '<p>Red, Green</p>',
            '<p><strong>Related:</strong> <a href="/team">Team</a></p>',
        ], array_map( 'trim', $texts ) );

        $de = array_map( fn( $element ) => trim( $element->data->text ?? '' ), (array) PageView::language( 'de' )->findOrFail( $page->id )->content );
        $this->assertContains( '<p><strong>Datum:</strong> 2024-01-01 11:00</p>', $de );
        $this->assertContains( '<p>Rot, Grün</p>', $de );
    }


    public function testImportMenuLinks(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );
        $db->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'a', 'langcode' => 'en', 'title' => 'Company', 'menu_name' => 'main', 'link__uri' => 'route:<nolink>', 'parent' => null, 'weight' => 0],
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'parent' => 'menu_link_content:a', 'weight' => 0],
            ['uuid' => 'c', 'langcode' => 'en', 'title' => 'GitHub', 'menu_name' => 'main', 'link__uri' => 'https://github.com/example', 'parent' => 'menu_link_content:b', 'weight' => 0],
            ['uuid' => 'd', 'langcode' => 'en', 'title' => 'Products', 'menu_name' => 'main', 'link__uri' => 'internal:/products', 'parent' => null, 'weight' => 1],
            ['uuid' => 'e', 'langcode' => 'en', 'title' => 'Team', 'menu_name' => 'main', 'link__uri' => 'entity:node/2', 'parent' => 'menu_link_content:d', 'weight' => 0],
            ['uuid' => 'f', 'langcode' => 'en', 'title' => 'Shop', 'menu_name' => 'main', 'link__uri' => 'https://shop.example.com', 'parent' => null, 'weight' => 2],
            ['uuid' => 'g', 'langcode' => 'en', 'title' => 'Members', 'menu_name' => 'main', 'link__uri' => 'https://shop.example.com/members', 'parent' => 'menu_link_content:f', 'weight' => 0],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped menu link: Products (internal:/products), target isn\'t imported', $output );
        $this->assertStringContainsString( 'Menu links: 3 imported, 0 updated, 0 unchanged (3/4 menu links processed).', $output );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $github = PageView::where( 'name', 'GitHub' )->firstOrFail();
        $shop = PageView::where( 'name', 'Shop' )->firstOrFail();
        $members = PageView::where( 'name', 'Members' )->firstOrFail();

        $this->assertSame( $root->id, $about->parent_id );
        $this->assertSame( $root->id, PageView::where( 'name', 'Team' )->firstOrFail()->parent_id );
        $this->assertSame( $about->id, $github->parent_id );
        $this->assertSame( ['about/github', 'https://github.com/example', 1], [$github->path, $github->to, $github->status] );
        $this->assertSame( ['shop', 'https://shop.example.com', 1], [$shop->path, $shop->to, $shop->status] );
        $this->assertSame( ['shop/members', 'https://shop.example.com/members', $shop->id], [$members->path, $members->to, $members->parent_id] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();
        $this->assertStringContainsString( 'Menu links: 0 imported, 0 updated, 3 unchanged (3/4 menu links processed).', $output );
        $this->assertStringNotContainsString( 'previous imports', $output );
        $this->assertSame( 3, PageView::where( 'to', '!=', '' )->count() );
    }


    public function testImportFrontPage(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->table( 'node_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/about']] )],
            ['name' => 'language.negotiation', 'data' => serialize( ['url' => ['source' => 'path_prefix', 'prefixes' => ['en' => '', 'de' => 'de', 'fr' => 'fr']]] )],
        ] );
        $db->table( 'node_field_data' )->where( 'langcode', 'de' )->update( ['created' => 1600000000, 'changed' => 1700000000] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/about/team', 'langcode' => 'en'] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p><a href="/node/1">Home</a></p>'
            . '<img src="/sites/default/files/styles/large/public/photo.jpg?itok=abc" alt="Photo" />'] );

        $args = ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'];
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        // the root page created by the import counts as imported
        $this->assertStringContainsString( 'Imported: About (/)', Artisan::output() );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringNotContainsString( 'as normal page', Artisan::output() );
        $this->assertSame( 1, File::where( 'path', 'https://example.com/sites/default/files/photo.jpg' )->count() );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $team = PageView::where( 'name', 'Team' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $root->id );

        $this->assertSame( 'About', $root->name );
        $this->assertSame( 'Welcome', $root->content[0]->data->title ?? null );
        $this->assertNull( PageView::where( 'tag', 'page' )->where( 'name', 'About' )->first() );
        $this->assertSame( $root->id, $team->parent_id );
        $this->assertSame( 'de', $de->path );
        $this->assertSame( '2020-09-13 12:26:40', $de->variant?->created_at?->format( 'Y-m-d H:i:s' ) );
        $this->assertSame( '2023-11-14 22:13:20', $de->variant->updated_at?->format( 'Y-m-d H:i:s' ) );
        $this->assertStringContainsString( 'href="/"', $team->content[0]->data->text ?? '' );
        $this->assertSame( 'https://example.com/sites/default/files/photo.jpg', File::where( 'id', $team->content[1]->data->file->id ?? '' )->firstOrFail()->path );
    }


    public function testImportFrontPageExistingRoot(): void
    {
        $this->tables( 'config' );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/node/1']] )],
        ] );

        DB::connection( 'drupal' )->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        DB::connection( 'drupal' )->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p><a href="/node/1">About</a></p>'] );

        Pages::create( ['name' => 'Home', 'title' => 'Home', 'path' => '/', 'tag' => 'root', 'domain' => 'example.com', 'lang' => 'en', 'status' => 1, 'editor' => 'test'], [] );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'as normal page because the root page already exists', Artisan::output() );
        $this->assertSame( 'about', PageView::where( 'name', 'About' )->firstOrFail()->path );
        // links to the front page point to its normal page
        $this->assertStringContainsString( 'href="/about"', PageView::where( 'name', 'Team' )->firstOrFail()->content[0]->data->text ?? '' );
    }


    public function testImportFrontPageFailed(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'node_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'langcode' )->default( '' );
            $table->boolean( 'fallback' )->default( true );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
            $table->unsignedInteger( 'grant_view' )->default( 1 );
        } );
        $this->tables( 'key_value' );

        $db = DB::connection( 'drupal' );
        $db->table( 'key_value' )->insert( ['collection' => 'state', 'name' => 'content_access_roles_gids',
            'value' => serialize( ['anonymous' => 1, 'authenticated' => 2, 'premium' => 3] )] );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/node/1']] )],
            ['name' => 'user.role.premium', 'data' => serialize( ['id' => 'premium', 'is_admin' => false, 'permissions' => []] )],
        ] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p><a href="/node/1">About</a></p>'] );
        $db->table( 'node_access' )->insert( [
            ['nid' => 1, 'langcode' => 'en', 'gid' => 3, 'realm' => 'content_access_rid'],
            ['nid' => 2, 'langcode' => 'en', 'gid' => 1, 'realm' => 'content_access_rid'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Check the access of the root page', Artisan::output() );
        // links to the front page which couldn't be imported into the root page don't point to the root page
        $this->assertStringContainsString( 'href="/about"', PageView::where( 'name', 'Team' )->firstOrFail()->content[0]->data->text ?? '' );
    }


    public function testImportFrontPageDuplicateAlias(): void
    {
        $this->tables( 'config' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/home']] )],
        ] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Old home', 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'New home', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/home', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/home', 'langcode' => 'en'],
        ] );

        // the last node with the alias is used
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'New home', PageView::where( 'tag', 'root' )->firstOrFail()->name );
    }


    public function testImportRerunUpdates(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--node-redirects' => true] ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->where( 'langcode', 'en' )->update( ['title' => 'About us'] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Hello again</p>'] );
        $db->table( 'node__body' )->where( 'langcode', 'de' )->update( ['body_value' => '<p>Hallo nochmal</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--node-redirects' => true] ) );
        $output = Artisan::output();

        $this->assertStringNotContainsString( 'Failed', $output );
        $this->assertStringNotContainsString( 'Skipped redirect', $output );
        $this->assertStringNotContainsString( "weren't updated", $output );
        $this->assertStringContainsString( 'Updated: About us (/about)', $output );
        $this->assertStringContainsString( '0 imported, 1 updated, 0 unchanged (1/1 nodes processed)', $output );
        $this->assertStringContainsString( 'Redirects: 0 imported, 0 updated, 1 unchanged (1/1 redirects processed).', $output );

        $page = PageView::where( 'path', 'about' )->sole();
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'About us', $page->title );
        $this->assertStringContainsString( 'Hello again', $page->content[0]->data->text ?? '' );
        $this->assertStringContainsString( 'Hallo nochmal', $de->content[0]->data->text ?? '' );
        $this->assertSame( 1, PageView::where( 'path', 'node/1' )->count() );

        // unchanged pages and translations don't get new versions
        $versions = $page->versions()->count();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--node-redirects' => true] ) );
        $this->assertStringContainsString( 'Unchanged: About us (/about)', Artisan::output() );
        $this->assertSame( $versions, $page->versions()->count() );

        // translations changed in Pagible aren't overwritten
        PageVariant::where( 'path', 'ueber-uns' )->update( ['editor' => 'someone'] );
        $db->table( 'node__body' )->where( 'langcode', 'de' )->update( ['body_value' => '<p>Hallo zum dritten Mal</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped translation: Über uns [de] (changed in Pagible since the last import)', Artisan::output() );
        $this->assertStringContainsString( 'Hallo nochmal', PageView::language( 'de' )->findOrFail( $page->id )->content[0]->data->text ?? '' );
        PageVariant::where( 'path', 'ueber-uns' )->update( ['editor' => 'drupal-import'] );

        // translations removed in Drupal are reported but not deleted
        $db->table( 'node_field_data' )->where( 'langcode', 'de' )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--node-redirects' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "1 pages of previous imports weren't updated", $output );
        $this->assertStringContainsString( '/ueber-uns [de]', $output );
        $this->assertNotNull( PageView::language( 'de' )->find( $page->id ) );

        // pages changed in Pagible aren't overwritten
        PageVariant::whereKey( $page->variant_id )->update( ['editor' => 'someone'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "URL /about is used by a page which wasn't imported or was changed since", Artisan::output() );
        $this->assertStringContainsString( 'Hello again', PageView::where( 'path', 'about' )->sole()->content[0]->data->text ?? '' );
    }


    public function testImportDryRun(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Found 1 Drupal nodes.', $output );
        $this->assertStringContainsString( '  [1] About (page)', $output );
        $this->assertStringContainsString( 'Dry run complete. No changes were made.', $output );
        $this->assertStringNotContainsString( 'Imported:', $output );
        $this->assertSame( 0, PageView::withTrashed()->count() );
        $this->assertSame( 0, PageVariant::withTrashed()->count() );
        $this->assertSame( 0, File::count() );
        $this->assertSame( 0, Element::count() );
    }


    public function testImportFileRename(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Text</p><img src="/sites/default/files/photo.jpg" alt="Photo" />'] );

        $args = ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'];
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertSame( 'Photo', File::where( 'path', 'https://example.com/sites/default/files/photo.jpg' )->sole()->name );

        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Text</p><img src="/sites/default/files/photo.jpg" alt="New photo" />'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertSame( 'New photo', File::where( 'path', 'https://example.com/sites/default/files/photo.jpg' )->sole()->name );

        // files changed in Pagible keep their name
        File::where( 'path', 'https://example.com/sites/default/files/photo.jpg' )->update( ['editor' => 'someone', 'name' => 'Mine'] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Text</p><img src="/sites/default/files/photo.jpg" alt="Other" />'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertSame( 'Mine', File::where( 'path', 'https://example.com/sites/default/files/photo.jpg' )->sole()->name );
    }


    public function testImportMetatags(): void
    {
        $this->tables( 'node__field_meta_tags' );

        DB::connection( 'drupal' )->table( 'node__field_meta_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_meta_tags_value' => json_encode( [
                'title' => 'About our company',
                'description' => 'All about us',
                'og_title' => 'Meet us',
                'og_image' => 'https://example.com/sites/default/files/share.png, https://example.com/other.png',
                'canonical_url' => 'https://example.org/about',
                'robots' => 'noindex, follow',
            ] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $file = File::where( 'path', 'https://example.com/sites/default/files/share.png' )->sole();
        $social = $page->meta->{'social-media'}->data ?? null;

        $this->assertSame( 'About our company', $page->title );
        $this->assertSame( 'All about us', $page->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( ['Meet us', 'All about us', (string) $file->id], [$social->title ?? null, $social->description ?? null, $social->file->id ?? null] );
        $this->assertSame( 'https://example.org/about', $page->meta->canonical->data->url ?? null );
        $this->assertEquals( (object) ['index' => 'noindex', 'follow' => 'follow'], $page->meta->robots->data ?? null );
        $this->assertContains( (string) $file->id, $page->versions()->firstOrFail()->files()->pluck( 'id' )->map( fn( $id ) => (string) $id )->all() );

        // translations without meta tags don't get them
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertObjectNotHasProperty( 'social-media', $de->meta );

        // the names of existing files aren't changed by meta images
        $file->forceFill( ['name' => 'Shared'] )->saveQuietly();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Shared', $file->refresh()->name );
    }


    public function testImportFrontPagePrefix(): void
    {
        $this->tables( 'config' );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/node/1']] )],
            ['name' => 'language.negotiation', 'data' => serialize( ['url' => ['source' => 'path_prefix', 'prefixes' => ['en' => 'en', 'de' => 'de', 'fr' => 'fr']]] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Redirects: 1 imported, 0 updated, 0 unchanged (1/1 redirects processed).', Artisan::output() );
        $this->assertSame( '/', PageView::whereIn( 'path', ['en', '/en'] )->sole()->to );
    }


    public function testImportMenuLinkTranslations(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        DB::connection( 'drupal' )->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'parent' => null, 'default_langcode' => 1],
            ['uuid' => 'c', 'langcode' => 'en', 'title' => 'GitHub', 'menu_name' => 'main', 'link__uri' => 'https://github.com/example', 'parent' => 'menu_link_content:b', 'default_langcode' => 1],
            ['uuid' => 'c', 'langcode' => 'de', 'title' => 'Quellcode', 'menu_name' => 'main', 'link__uri' => 'https://github.com/example', 'parent' => 'menu_link_content:b', 'default_langcode' => 0],
            ['uuid' => 'f', 'langcode' => 'en', 'title' => 'Shop', 'menu_name' => 'main', 'link__uri' => 'https://shop.example.com', 'parent' => null, 'default_langcode' => 1],
            ['uuid' => 'f', 'langcode' => 'de', 'title' => 'Laden', 'menu_name' => 'main', 'link__uri' => 'https://shop.example.com', 'parent' => null, 'default_langcode' => 0],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $github = PageView::where( 'name', 'GitHub' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $github->id );
        $fr = PageView::language( 'fr' )->findOrFail( $github->id );
        $shop = PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'Shop' )->firstOrFail()->id );

        $this->assertSame( ['Quellcode', 'ueber-uns/quellcode', 'https://github.com/example', 1], [$de->name, $de->path, $de->to, $de->status] );
        $this->assertSame( ['GitHub', 'à-propos/github'], [$fr->name, $fr->path] );
        $this->assertSame( ['Laden', 'laden', 'https://shop.example.com'], [$shop->name, $shop->path, $shop->to] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'previous imports', Artisan::output() );

        // the existing translations of updated menu links are updated
        DB::connection( 'drupal' )->table( 'menu_link_content_data' )->where( 'uuid', 'c' )->update( ['link__uri' => 'https://github.com/other'] );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $de = PageView::language( 'de' )->findOrFail( $github->id );
        $this->assertStringNotContainsString( 'Failed', $output );
        $this->assertSame( ['Quellcode', 'ueber-uns/quellcode', 'https://github.com/other'], [$de->name, $de->path, $de->to] );
        $this->assertSame( 1, PageView::language( 'de' )->withTrashed()->where( 'path', 'ueber-uns/quellcode' )->count() );
    }


    public function testImportBlocks(): void
    {
        $this->createBlockTables();

        $block = fn( string $region, string $uuid, array $visibility = [] ) => serialize( [
            'status' => true, 'theme' => 'olivero', 'region' => $region, 'weight' => 0, 'plugin' => 'block_content:' . $uuid, 'visibility' => $visibility,
        ] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => $block( 'footer_top', 'uuid-1', [
                'request_path' => ['id' => 'request_path', 'negate' => true, 'pages' => "/team\n/blog/*"],
            ] )],
            ['name' => 'block.block.olivero_banner', 'data' => $block( 'header', 'uuid-2' )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/node/2']] )],
            ['name' => 'block.block.olivero_welcome', 'data' => $block( 'header', 'uuid-1', [
                'request_path' => ['id' => 'request_path', 'pages' => '<front>'],
            ] )],
            ['name' => 'block.block.olivero_members', 'data' => $block( 'sidebar', 'uuid-1', [
                'user_role' => ['id' => 'user_role', 'roles' => ['authenticated' => 'authenticated']],
            ] )],
            ['name' => 'block.block.claro_contact', 'data' => serialize( ['theme' => 'claro', 'region' => 'footer', 'plugin' => 'block_content:uuid-1'] )],
            ['name' => 'block.block.olivero_promo', 'data' => $block( 'content', 'uuid-1', ['webform' => ['id' => 'webform', 'webforms' => ['contact' => 'contact']]] )],
            ['name' => 'block.block.olivero_themed', 'data' => $block( 'footer', 'uuid-1', [
                'current_theme' => ['id' => 'current_theme', 'theme' => 'claro'],
            ] )],
            ['name' => 'block.block.olivero_terms', 'data' => $block( 'footer', 'uuid-1', [
                'entity_bundle:taxonomy_term' => ['id' => 'entity_bundle:taxonomy_term', 'bundles' => ['tags' => 'tags']],
            ] )],
            ['name' => 'block.block.olivero_main', 'data' => serialize( ['status' => true, 'theme' => 'olivero', 'region' => 'content', 'plugin' => 'system_main_block'] )],
            ['name' => 'block.block.olivero_recent', 'data' => serialize( ['status' => true, 'theme' => 'olivero', 'region' => 'sidebar', 'plugin' => 'views_block:content_recent-block_1'] )],
        ] );
        $db->table( 'block_content' )->insert( ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1],
            ['id' => 5, 'langcode' => 'de', 'info' => 'Kontakt', 'default_langcode' => 0],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'],
            ['entity_id' => 5, 'langcode' => 'de', 'body_value' => '<p>Rufen Sie an</p>'],
        ] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Skipped custom block olivero_banner (not found)', $output );
        $this->assertStringContainsString( 'Skipped block olivero_promo, its "webform" visibility condition is not supported', $output );
        $this->assertStringContainsString( 'Skipped 1 blocks which are not custom blocks, add their content in Pagible if needed: olivero_recent (views_block:content_recent-block_1)', $output );

        $element = Element::where( 'lang', 'en' )->where( 'name', 'Contact' )->sole();
        $elementDe = Element::where( 'lang', 'de' )->where( 'name', 'Kontakt' )->sole();
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $refs = array_values( array_filter( (array) $page->content, fn( $el ) => ( $el->type ?? '' ) === 'reference' ) );
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        // untranslated blocks are shown in the default language like in Drupal
        $this->assertSame( ['de' => 'Kontakt', 'en' => 'Contact', 'fr' => 'Contact'], Element::orderBy( 'lang' )->pluck( 'name', 'lang' )->all() );
        $this->assertStringContainsString( 'Call us', (string) json_encode( $element->data ) );
        $this->assertStringContainsString( 'Rufen Sie an', (string) json_encode( $elementDe->data ) );
        $this->assertSame( [[(string) $element->id, 'footer']], array_map( fn( $el ) => [$el->refid ?? null, $el->group ?? null], $refs ) );
        $this->assertContains( (string) $elementDe->id, array_column( (array) json_decode( (string) json_encode( $de->content ), true ), 'refid' ) );
        // blocks for the front page are only shown there
        $this->assertSame( [[(string) $element->id, 'main']], array_values( array_map( fn( $el ) => [$el->refid ?? null, $el->group ?? null],
            array_filter( (array) PageView::where( 'name', 'Team' )->firstOrFail()->content, fn( $el ) => ( $el->type ?? '' ) === 'reference' ) ) ) );

        // unchanged blocks don't get new versions
        $versions = $element->versions()->count();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( $versions, $element->versions()->count() );
        $this->assertStringContainsString( 'Unchanged: About (/about)', Artisan::output() );
    }


    public function testImportBlocksSameName(): void
    {
        $this->createBlockTables();

        $block = fn( string $uuid ) => serialize( [
            'status' => true, 'theme' => 'olivero', 'region' => 'footer', 'weight' => 0, 'plugin' => 'block_content:' . $uuid,
        ] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => $block( 'uuid-1' )],
            ['name' => 'block.block.olivero_contact_2', 'data' => $block( 'uuid-3' )],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 7, 'uuid' => 'uuid-3', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1],
            ['id' => 7, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'],
            ['entity_id' => 7, 'langcode' => 'en', 'body_value' => '<p>Write us</p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // blocks with the same description don't overwrite each other
        $this->assertSame( ['Contact (5)', 'Contact (7)'], Element::where( 'lang', 'en' )->orderBy( 'name' )->pluck( 'name' )->all() );
        $this->assertStringContainsString( 'Call us', (string) json_encode( Element::where( 'lang', 'en' )->where( 'name', 'Contact (5)' )->sole()->data ) );
        $this->assertStringContainsString( 'Write us', (string) json_encode( Element::where( 'lang', 'en' )->where( 'name', 'Contact (7)' )->sole()->data ) );

        // shared elements of removed blocks are reported
        $db->table( 'config' )->where( 'name', 'block.block.olivero_contact_2' )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();
        $this->assertStringContainsString( "shared elements of previous imports weren't updated", $output );
        $this->assertStringContainsString( 'Contact (7) [en]', $output );
        $this->assertStringNotContainsString( 'Contact (5) [en]', $output );
    }


    public function testImportBlocksWithoutInlineBlockNames(): void
    {
        $this->createBlockTables();
        Schema::connection( 'drupal' )->table( 'block_content_field_data', function( Blueprint $table ) {
            $table->boolean( 'reusable' )->default( true );
        } );

        $block = fn( string $uuid ) => serialize( [
            'status' => true, 'theme' => 'olivero', 'region' => 'footer', 'weight' => 0, 'plugin' => 'block_content:' . $uuid,
        ] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => $block( 'uuid-1' )],
            ['name' => 'block.block.olivero_footer', 'data' => $block( 'uuid-3' )],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 6, 'uuid' => 'uuid-2', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 7, 'uuid' => 'uuid-3', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1, 'reusable' => 1],
            ['id' => 6, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1, 'reusable' => 0],
            ['id' => 7, 'langcode' => 'en', 'info' => 'Footer', 'default_langcode' => 1, 'reusable' => 0],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'],
            ['entity_id' => 7, 'langcode' => 'en', 'body_value' => '<p>Bottom</p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // inline blocks don't count for the names of the shared elements but get their ID added themselves
        $this->assertSame( ['Contact', 'Footer (7)'], Element::where( 'lang', 'en' )->orderBy( 'name' )->pluck( 'name' )->all() );
    }


    public function testImportRedirectsLanguageDomain(): void
    {
        Schema::connection( 'drupal' )->create( 'redirect', function( Blueprint $table ) {
            $table->id( 'rid' );
            $table->string( 'redirect_source__path' );
            $table->string( 'redirect_redirect__uri' );
            $table->string( 'language' )->default( 'und' );
        } );

        DB::connection( 'drupal' )->table( 'redirect' )->insert( [
            ['redirect_source__path' => 'alt', 'redirect_redirect__uri' => 'internal:/ueber-uns', 'language' => 'de'],
            ['redirect_source__path' => 'old', 'redirect_redirect__uri' => 'internal:/about', 'language' => 'en'],
        ] );

        $args = ['--domain' => 'example.com', '--language' => ['de:de:https://example.de/']];

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );

        $de = PageView::withoutGlobalScopes()->where( 'domain', 'example.de' )->where( 'path', 'alt' )->sole();

        $this->assertSame( ['de', '/ueber-uns'], [$de->lang, $de->to] );
        $this->assertNull( PageView::where( 'domain', 'example.com' )->where( 'path', 'alt' )->first() );
        $this->assertSame( '/about', PageView::where( 'domain', 'example.com' )->where( 'path', 'old' )->sole()->to );

        // repeated imports update the redirects in the language domain
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringNotContainsString( 'Skipped redirect', Artisan::output() );
        $this->assertSame( 1, PageView::withoutGlobalScopes()->where( 'domain', 'example.de' )->where( 'path', 'alt' )->count() );
    }


    public function testImportSiteImageUrls(): void
    {
        $this->tables( 'node__field_meta_tags' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node__field_meta_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_meta_tags_value' => json_encode( ['og_image' => 'http://www.example.com/sites/default/files/share.png'] )],
        ] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Intro</p><img src="//example.com/sites/default/files/share.png" />'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $file = File::where( 'path', 'https://example.com/sites/default/files/share.png' )->sole();

        // the same image with another scheme or host name is imported only once
        $this->assertSame( 1, File::count() );
        $this->assertSame( (string) $file->id, $page->meta->{'social-media'}->data->file->id ?? null );
    }


    public function testImportEntityEmbeds(): void
    {
        $this->tables( 'node' );
        Schema::connection( 'drupal' )->table( 'file_managed', function( Blueprint $table ) {
            $table->string( 'uuid' )->default( '' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node' )->insert( [
            ['nid' => 1, 'uuid' => 'node-1', 'type' => 'page', 'langcode' => 'en'],
            ['nid' => 2, 'uuid' => 'node-2', 'type' => 'page', 'langcode' => 'en'],
        ] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'uuid' => 'file-1', 'filename' => 'logo.png', 'filemime' => 'image/png', 'uri' => 'public://logo.png'],
            ['fid' => 2, 'uuid' => 'file-2', 'filename' => 'terms.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://terms.pdf'],
        ] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Intro</p>'
            . '<drupal-entity data-entity-type="node" data-entity-uuid="node-2" data-entity-embed-display="entity_reference:entity_reference_label"></drupal-entity>'
            . '<drupal-entity data-entity-type="file" data-entity-uuid="file-1" alt="Logo"></drupal-entity>'
            . '<drupal-entity data-entity-type="file" data-entity-id="2"></drupal-entity>'
            . '<drupal-entity data-entity-type="file" data-entity-uuid="missing"></drupal-entity>'
            . '<drupal-entity data-entity-type="webform" data-entity-id="contact"></drupal-entity>'
            . '<drupal-entity data-entity-type="node" data-entity-uuid="node-2" data-entity-embed-display="view_mode:node.teaser"></drupal-entity>'
            . '<drupal-entity data-entity-type="node" data-entity-uuid="node-9" data-entity-embed-display="entity_reference:entity_reference_label"></drupal-entity>'
            . '<drupal-entity data-entity-type="node" data-entity-id="1" data-entity-embed-display="entity_reference:entity_reference_label"></drupal-entity>'
            . '<drupal-entity data-entity-type="file" data-entity-id="9"></drupal-entity>'] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p>Our <b>people</b></p>'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/files'] ) );
        $queries = $this->queries( $db );
        $db->disableQueryLog();

        // the embedded nodes and files of the texts are fetched at once
        $bindings = fn( string $query ) => $queries->filter( fn( $entry ) => str_contains( $entry['query'], $query ) )->pluck( 'bindings' )->values()->all();
        $this->assertSame( [['file-1', 'missing']], $bindings( 'from "file_managed" where "uuid" in' ) );
        $this->assertSame( [[2, 9]], $bindings( 'from "file_managed" where "fid" in' ) );
        $this->assertSame( [['node-2', 'node-9']], $bindings( 'from "node" where "uuid" in' ) );
        $this->assertSame( [[1, 2]], $bindings( 'from "node_field_data" where "nid" in' ) );

        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped embedded Drupal webform entities', $output );
        $this->assertStringContainsString( 'Skipped embedded file missing (not found)', $output );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        $this->assertSame( ['html', 'html', 'image', 'file', 'html', 'html'], array_column( $content, 'type' ) );
        $this->assertStringContainsString( '<a href="/team">Team</a></h3><p>Our people</p>', $content[4]->data->text );
        $this->assertStringContainsString( '<a href="/team">Team</a>', $content[1]->data->text );
        $this->assertSame( 'https://example.com/files/logo.png', File::where( 'id', $content[2]->data->file->id )->firstOrFail()->path );
        $this->assertSame( 'https://example.com/files/terms.pdf', File::where( 'id', $content[3]->data->file->id )->firstOrFail()->path );
    }


    public function testImportMetatagDefaults(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'node__field_image', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_image_target_id' );
            $table->string( 'field_image_alt' )->default( '' );
        } );
        $this->tables( 'node__field_meta_tags' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_image', 'data' => serialize( ['type' => 'image'] )],
            ['name' => 'field.storage.node.field_meta_tags', 'data' => serialize( ['type' => 'metatag'] )],
            ['name' => 'field.field.node.page.body', 'data' => serialize( ['field_name' => 'body'] )],
            ['name' => 'field.field.node.page.field_image', 'data' => serialize( ['field_name' => 'field_image'] )],
            ['name' => 'field.field.node.page.field_meta_tags', 'data' => serialize( ['field_name' => 'field_meta_tags'] )],
            ['name' => 'system.site', 'data' => serialize( ['name' => 'Example', 'slogan' => ''] )],
            ['name' => 'metatag.metatag_defaults.global', 'data' => serialize( ['tags' => [
                'title' => '[current-page:title] | [site:name]', 'canonical_url' => '[current-page:url]', 'robots' => 'index, follow',
            ]] )],
            ['name' => 'metatag.metatag_defaults.node', 'data' => serialize( ['tags' => [
                'title' => '[node:title] | [site:name]', 'description' => '[node:summary]', 'og_image' => '[node:field_image:entity:url]',
            ]] )],
            ['name' => 'metatag.metatag_defaults.node__page', 'data' => serialize( ['tags' => ['robots' => 'noindex']] )],
        ] );
        $db->table( 'config' )->insert( [
            'collection' => 'language.de', 'name' => 'system.site', 'data' => serialize( ['name' => 'Beispiel'] ),
        ] );
        $db->table( 'file_managed' )->insert( ['fid' => 1, 'filename' => 'team.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://team.jpg'] );
        $db->table( 'node__field_image' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_image_target_id' => 1, 'field_image_alt' => 'Team'] );
        $db->table( 'node__field_meta_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'de', 'field_meta_tags_value' => json_encode( ['description' => 'Über die Firma'] )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $file = File::where( 'path', 'https://example.com/sites/default/files/team.jpg' )->sole();

        // node defaults override the global ones, content type defaults the node ones
        $this->assertSame( 'About | Example', $page->title );
        $this->assertSame( 'Welcome Hello', $page->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( (string) $file->id, $page->meta->{'social-media'}->data->file->id ?? null );
        $this->assertEquals( (object) ['index' => 'noindex'], $page->meta->robots->data ?? null );
        $this->assertObjectNotHasProperty( 'canonical', $page->meta );

        // translated site names and node values override the defaults
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( 'Über uns | Beispiel', $de->title );
        $this->assertSame( 'Über die Firma', $de->meta->{'meta-tags'}->data->description ?? null );
    }


    public function testImportFileLinks(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( ['fid' => 1, 'filename' => 'prices.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://docs/prices.pdf'] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( ['body_value' => '<p>See <a href="/drupal/sites/default/files/terms.pdf">terms</a>'
            . ' and <a href="http://www.example.com/drupal/node/2#members">team</a>'
            . ' or <a href="https://example.org/node/2">other</a>.</p>'
            . '<p><a href="/drupal/sites/default/files/docs/prices.pdf">Price list</a></p>'
            . '<p><a href="https://example.com/drupal/sites/default/files/other.pdf">Other document</a></p>'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/drupal/sites/default/files'] ) );
        $bindings = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "file_managed" where "uri"' ) )->pluck( 'bindings' );
        $db->disableQueryLog();

        // the files linked in the texts are fetched at once
        $this->assertEquals( [['public://terms.pdf', 'public://docs/prices.pdf', 'public://other.pdf']], $bindings->values()->all() );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;

        // links in text point to the files of the Drupal site and to the imported pages
        $this->assertSame( ['html', 'file', 'file'], array_column( $content, 'type' ) );
        $this->assertStringContainsString( '<a href="https://example.com/drupal/sites/default/files/terms.pdf">terms</a>', $content[0]->data->text );
        $this->assertStringContainsString( '<a href="/team#members">team</a>', $content[0]->data->text );
        $this->assertStringContainsString( '<a href="https://example.org/node/2">other</a>', $content[0]->data->text );

        // paragraphs with a file link only become file elements
        $prices = File::where( 'id', $content[1]->data->file->id )->firstOrFail();
        $other = File::where( 'id', $content[2]->data->file->id )->firstOrFail();

        $this->assertSame( ['Price list', 'https://example.com/drupal/sites/default/files/docs/prices.pdf', 'application/pdf'], [$prices->name, $prices->path, $prices->mime] );
        $this->assertSame( ['Other document', 'https://example.com/drupal/sites/default/files/other.pdf'], [$other->name, $other->path] );
    }

    /**
     * Creates the tables of custom blocks and the configuration in the Drupal database.
     */
    public function testImportBlockRegions(): void
    {
        $this->createBlockTables();

        $block = fn( string $region, string $plugin, int $weight = 0 ) => serialize( [
            'status' => true, 'theme' => 'olivero', 'region' => $region, 'weight' => $weight, 'plugin' => $plugin,
        ] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_content', 'data' => $block( 'content', 'system_main_block' )],
            ['name' => 'block.block.olivero_contact', 'data' => $block( 'footer_top', 'block_content:uuid-1' )],
            ['name' => 'block.block.olivero_links', 'data' => $block( 'sidebar', 'block_content:uuid-2' )],
            ['name' => 'block.block.olivero_outro', 'data' => $block( 'content', 'block_content:uuid-3', 5 )],
            ['name' => 'block.block.olivero_intro', 'data' => $block( 'content', 'block_content:uuid-4', -5 )],
            ['name' => 'block.block.olivero_banner', 'data' => $block( 'header', 'block_content:uuid-5' )],
            ['name' => 'block.block.olivero_promo', 'data' => $block( 'top_bar', 'block_content:uuid-6' )],
        ] );

        foreach( ['Contact', 'Links', 'Outro', 'Intro', 'Banner', 'Promo'] as $idx => $name )
        {
            $id = $idx + 1;
            $db->table( 'block_content' )->insert( ['id' => $id, 'uuid' => 'uuid-' . $id, 'type' => 'basic', 'langcode' => 'en'] );
            $db->table( 'block_content_field_data' )->insert( ['id' => $id, 'langcode' => 'en', 'info' => $name, 'default_langcode' => 1] );
            $db->table( 'block_content__body' )->insert( ['entity_id' => $id, 'langcode' => 'en', 'body_value' => "<p>{$name} text</p>"] );
        }

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $names = Element::where( 'lang', 'en' )->pluck( 'name', 'id' )->all();
        $elements = (array) json_decode( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ), true );
        $content = array_map( fn( $el ) => isset( $el['refid'] ) ? $names[$el['refid']] . ':' . $el['group'] : $el['type'], $elements );

        // header regions and lighter blocks of the content region are shown before the node content
        $this->assertSame( ['Banner:main', 'Promo:main', 'Intro:main'], array_slice( $content, 0, 3 ) );
        $this->assertSame( ['Outro:main', 'Links:footer', 'Contact:footer'], array_slice( $content, -3 ) );
        $this->assertNotContains( 'reference', array_slice( $content, 3, -3 ) );
    }


    public function testImportNodeAccess(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'langcode' )->default( '' );
            $table->boolean( 'fallback' )->default( true );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
            $table->unsignedInteger( 'grant_view' )->default( 1 );
        } );
        $this->tables( 'key_value' );

        $db = DB::connection( 'drupal' );
        $db->table( 'key_value' )->insert( ['collection' => 'state', 'name' => 'content_access_roles_gids',
            'value' => serialize( ['anonymous' => 1, 'authenticated' => 2, 'premium' => 3, 'administrator' => 4, 'editor' => 5] )] );
        $db->table( 'config' )->insert( [
            ['name' => 'user.role.administrator', 'data' => serialize( ['id' => 'administrator', 'is_admin' => true, 'permissions' => []] )],
            ['name' => 'user.role.editor', 'data' => serialize( ['id' => 'editor', 'is_admin' => false, 'permissions' => ['bypass node access']] )],
            ['name' => 'user.role.premium', 'data' => serialize( ['id' => 'premium', 'is_admin' => false, 'permissions' => []] )],
        ] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Members', 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Premium', 'default_langcode' => 1],
            ['nid' => 4, 'type' => 'page', 'langcode' => 'en', 'title' => 'Private', 'default_langcode' => 1],
        ] );
        $db->table( 'node_access' )->insert( [
            ['nid' => 1, 'langcode' => 'en', 'gid' => 1, 'realm' => 'content_access_rid'],
            ['nid' => 1, 'langcode' => 'en', 'gid' => 2, 'realm' => 'content_access_rid'],
            ['nid' => 2, 'langcode' => 'en', 'gid' => 2, 'realm' => 'content_access_rid'],
            ['nid' => 3, 'langcode' => 'en', 'gid' => 3, 'realm' => 'content_access_rid'],
            ['nid' => 3, 'langcode' => 'de', 'gid' => 1, 'realm' => 'content_access_rid'],
            ['nid' => 4, 'langcode' => 'en', 'gid' => 7, 'realm' => 'content_access_author'],
        ] );

        // restricted nodes aren't imported as public pages
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Failed to import [3] Premium: Access is restricted in Drupal but frontend access restrictions aren't set up", Artisan::output() );
        $this->assertFalse( PageView::where( 'name', 'Premium' )->exists() );

        Access::using( fn() => ['administrator', 'gold'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Failed to import [3] Premium: Unknown access values "premium"', Artisan::output() );

        // invalid roles stop the import before any node is imported
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--role' => ['premium']] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Invalid Drupal role: premium', $output );
        $this->assertStringNotContainsString( 'Premium', $output );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--role' => ['premium:gold']] ) );
        $grants = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "node_access" where "nid" in' ) );
        $db->disableQueryLog();
        $this->assertStringContainsString( 'Restricted [4] Private to admin roles', Artisan::output() );

        // only the used columns of the grants are fetched
        $this->assertCount( 1, $grants );
        $this->assertStringStartsWith( 'select "nid", "langcode", "gid", "realm" from', (string) $grants->first() );

        $access = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->accessValues();

        $this->assertNull( $access( 'About' ) );
        $this->assertSame( [], $access( 'Members' ) );
        $this->assertSame( ['administrator', 'gold'], $access( 'Premium' ) );
        $this->assertSame( ['administrator'], $access( 'Private' ) );

        // pages become public again if Drupal grants access to everyone
        $db->table( 'node_access' )->where( 'nid', 3 )->delete();
        $db->table( 'node_access' )->insert( ['nid' => 3, 'langcode' => 'en', 'gid' => 0, 'realm' => 'all'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertNull( $access( 'Premium' ) );
        $this->assertSame( ['administrator'], $access( 'Private' ) );

        // public pages whose nodes become restricted but fail to import are disabled
        $db->table( 'node_access' )->where( 'nid', 3 )->delete();
        $db->table( 'node_access' )->insert( ['nid' => 3, 'langcode' => 'en', 'gid' => 3, 'realm' => 'content_access_rid'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--prune' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Nothing moved to the trash because 1 imports failed.', $output );

        $this->assertStringContainsString( 'Disabled /premium [en] because its Drupal node is restricted but failed to import', $output );
        $this->assertStringContainsString( '1 imports failed:', $output );
        $this->assertStringContainsString( 'Finished with 1 failures', $output );
        $this->assertSame( 0, PageView::where( 'name', 'Premium' )->firstOrFail()->status );
        $this->assertNull( $access( 'Premium' ) );

        Access::using( null );
    }


    public function testAccessOfPageChangedTwice(): void
    {
        Access::using( fn() => ['gold'] );

        $page = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => '', 'lang' => 'en'], [] );
        $command = $this->app?->make( DrupalImport::class );
        $method = new \ReflectionMethod( DrupalImport::class, 'setAccess' );

        // the known access values are updated, so later changes of the same page aren't skipped
        $method->invoke( $command, $page, ['gold'] );
        $method->invoke( $command, $page, null );
        $this->assertNull( PageView::findOrFail( $page->id )->accessValues() );

        $method->invoke( $command, $page, ['gold'] );
        $this->assertSame( ['gold'], PageView::findOrFail( $page->id )->accessValues() );

        Access::using( null );
    }


    public function testImportPermissionsByTermWithoutLanguage(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'langcode' )->default( '' );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
            $table->unsignedInteger( 'grant_view' )->default( 1 );
        } );
        $this->tables( 'taxonomy_index' );
        $schema->create( 'permissions_by_term_role', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'rid' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( ['name' => 'user.role.administrator', 'data' => serialize( ['id' => 'administrator', 'is_admin' => true] )] );
        $db->table( 'node_access' )->insert( ['nid' => 1, 'langcode' => 'en', 'gid' => 1, 'realm' => 'permissions_by_term'] );
        $db->table( 'taxonomy_index' )->insert( ['nid' => 1, 'tid' => 7] );
        $db->table( 'permissions_by_term_role' )->insert( ['tid' => 7, 'rid' => 'gold'] );

        Access::using( fn() => ['administrator', 'gold'] );

        // permissions without language apply to all languages
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertEqualsCanonicalizing( ['administrator', 'gold'], PageView::where( 'name', 'About' )->firstOrFail()->accessValues() );

        Access::using( null );
    }


    public function testImportPermissionsByTerm(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'langcode' )->default( '' );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
            $table->unsignedInteger( 'grant_view' )->default( 1 );
        } );
        $this->tables( 'taxonomy_index' );
        $schema->create( 'permissions_by_term_role', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'rid' );
            $table->string( 'langcode' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( ['name' => 'user.role.administrator', 'data' => serialize( ['id' => 'administrator', 'is_admin' => true] )] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        $db->table( 'node_access' )->insert( [
            ['nid' => 1, 'langcode' => 'en', 'gid' => 1, 'realm' => 'permissions_by_term'],
            ['nid' => 2, 'langcode' => 'en', 'gid' => 1, 'realm' => 'permissions_by_term'],
        ] );
        $db->table( 'taxonomy_index' )->insert( [['nid' => 1, 'tid' => 7], ['nid' => 1, 'tid' => 8], ['nid' => 1, 'tid' => 9], ['nid' => 2, 'tid' => 8]] );
        $db->table( 'permissions_by_term_role' )->insert( [
            ['tid' => 7, 'rid' => 'premium', 'langcode' => 'en'],
            ['tid' => 7, 'rid' => 'gold', 'langcode' => 'en'],
            ['tid' => 8, 'rid' => 'gold', 'langcode' => 'en'],
            ['tid' => 8, 'rid' => 'silver', 'langcode' => 'de'],
        ] );

        Access::using( fn() => ['administrator', 'premium', 'gold'] );

        $queries = ['permissions_by_term_role' => [], 'cms_page_access' => []];
        DB::listen( function( $query ) use ( &$queries ) {
            foreach( ['permissions_by_term_role', 'cms_page_access'] as $table ) {
                str_contains( $this->sql( $query->sql ), '"' . $table . '"' ) && $queries[$table][] = strtok( $this->sql( $query->sql ), ' ' );
            }
        } );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'realm, not supported', Artisan::output() );

        $access = fn( $name = 'About' ) => PageView::where( 'name', $name )->firstOrFail()->accessValues();
        $this->assertEqualsCanonicalizing( ['administrator', 'premium', 'gold'], $access() );
        $this->assertEqualsCanonicalizing( ['administrator', 'gold'], $access( 'Team' ) );
        // the roles of all nodes are fetched by one query
                $this->assertCount( 1, $queries['permissions_by_term_role'] );

        // unchanged access isn't set again
        $queries = ['permissions_by_term_role' => [], 'cms_page_access' => []];
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( ['select'], $queries['cms_page_access'] );

        // only roles allowed to view all restricted terms
        $db->table( 'config' )->insert( ['name' => 'permissions_by_term.settings', 'data' => serialize( ['require_all_terms_granted' => true] )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertEqualsCanonicalizing( ['administrator', 'gold'], $access() );
        $this->assertEqualsCanonicalizing( ['administrator', 'gold'], $access( 'Team' ) );

        Access::using( null );
    }


    public function testFetchesDraftsTogether(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'vid' )->nullable() );
        $schema->create( 'node_field_revision', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
        } );
        $schema->create( 'node_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' )->default( 'basic_html' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->where( 'nid', 1 )->update( ['vid' => 1] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'vid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'status' => 1, 'default_langcode' => 1],
            ['nid' => 3, 'vid' => 5, 'type' => 'page', 'langcode' => 'en', 'title' => 'Jobs', 'status' => 1, 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/jobs', 'langcode' => 'en'],
        ] );
        $db->table( 'node_field_revision' )->insert( [
            ['nid' => 1, 'vid' => 3, 'langcode' => 'en', 'title' => 'About draft'],
            ['nid' => 2, 'vid' => 4, 'langcode' => 'en', 'title' => 'Team draft'],
            ['nid' => 3, 'vid' => 6, 'langcode' => 'en', 'title' => 'Jobs draft'],
        ] );
        $db->table( 'node_revision__body' )->insert( [
            ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en', 'body_value' => '<p>About draft text</p>'],
            ['entity_id' => 2, 'revision_id' => 4, 'langcode' => 'en', 'body_value' => '<p>Team draft text</p>'],
            ['entity_id' => 3, 'revision_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Jobs draft text</p>'],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], ' from "node_revision__body" ' ) );
        $db->disableQueryLog();

        // the drafts of all nodes are fetched at once
        $this->assertCount( 1, $queries );
        $this->assertEqualsCanonicalizing( [3, 4, 6], array_values( array_filter( $queries->first()['bindings'] ?? [] ) ) );

        foreach( ['About' => 'About draft text', 'Team' => 'Team draft text', 'Jobs' => 'Jobs draft text'] as $name => $text )
        {
            $draft = PageView::where( 'name', $name )->firstOrFail()->versions()->where( 'published', false )->sole();
            $this->assertStringContainsString( $text, (string) json_encode( $draft->aux ), $name );
        }
    }


    public function testImportScheduledAndDrafts(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'vid' )->nullable();
            $table->unsignedInteger( 'publish_on' )->nullable();
            $table->unsignedInteger( 'unpublish_on' )->nullable();
        } );
        $schema->create( 'node_field_revision', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->boolean( 'revision_translation_affected' )->default( true );
        } );
        $schema->create( 'node_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' )->default( 'basic_html' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->where( 'nid', 1 )->where( 'langcode', 'en' )->update( ['vid' => 1, 'unpublish_on' => time() + 86400] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'vid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Soon', 'status' => 0,
            'default_langcode' => 1, 'publish_on' => time() + 3600] );
        $db->table( 'node_field_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'langcode' => 'en', 'title' => 'About'],
            ['nid' => 1, 'vid' => 3, 'langcode' => 'en', 'title' => 'About draft'],
            ['nid' => 1, 'vid' => 3, 'langcode' => 'de', 'title' => 'Über uns'],
        ] );
        $db->table( 'node_revision__body' )->insert( ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en', 'body_value' => '<p>Draft text</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $pending = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->versions()->where( 'published', false )
            ->reorder()->orderBy( 'created_at' )->orderBy( 'id' )->get()->all();

        // scheduled nodes are imported without --unpublished
        $soon = PageView::where( 'name', 'Soon' )->firstOrFail();
        $versions = $pending( 'Soon' );

        $this->assertSame( 0, $soon->status );
        $this->assertCount( 1, $versions );
        $this->assertSame( 1, $versions[0]->data->status );
        $this->assertSame( $versions[0]->id, $soon->latest_id );
        $this->assertNotNull( $versions[0]->publish_at );

        // the draft is the latest version before the unpublication
        $versions = $pending( 'About' );

        $this->assertCount( 2, $versions );
        $this->assertNull( $versions[0]->publish_at );
        $this->assertSame( 'About draft', $versions[0]->data->title );
        $this->assertStringContainsString( 'Draft text', (string) json_encode( $versions[0]->aux ) );
        $this->assertNotNull( $versions[1]->publish_at );
        $this->assertSame( 0, $versions[1]->data->status );
        $this->assertStringContainsString( 'Hello', (string) json_encode( $versions[1]->aux ) );
        $this->assertStringContainsString( 'Hello', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // unchanged pending versions aren't replaced
        $ids = array_map( fn( $version ) => $version->id, $versions );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( $ids, array_map( fn( $version ) => $version->id, $pending( 'About' ) ) );

        $db->table( 'node_field_data' )->where( 'nid', 1 )->update( ['unpublish_on' => null] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $versions = $pending( 'About' );

        $this->assertCount( 1, $versions );
        $this->assertSame( 'About draft', $versions[0]->data->title );
        $this->assertSame( $versions[0]->id, PageView::where( 'name', 'About' )->firstOrFail()->latest_id );

        // scheduled versions are published by cms:publish
        $this->travel( 2 )->hours();
        Artisan::call( 'cms:publish' );

        $this->assertSame( 1, PageView::where( 'name', 'Soon' )->firstOrFail()->status );
    }


    public function testImportSince(): void
    {
        Schema::connection( 'drupal' )->table( 'node_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->update( ['changed' => strtotime( '2026-01-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $db->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Changed</p>'] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'New', 'default_langcode' => 1, 'changed' => strtotime( '2026-03-01' )] );

        // dry runs only list the nodes changed since
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01', '--dry-run' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( '  [2] New (page)', $output );
        $this->assertStringNotContainsString( '[1] About', $output );
        $this->assertFalse( PageView::where( 'name', 'New' )->exists() );

        DB::enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );
        $queries = $this->queries( DB::connection() )->pluck( 'query' );
        DB::disableQueryLog();
        $output = Artisan::output();

        // the variants of the pages of unchanged nodes are prefetched together with the pages of the next nodes
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_contains( $query, ' from "cms_page_view" where "cms_page_view"."id" in' ) ) );
        $this->assertCount( 0, $queries->filter( fn( $query ) => str_contains( $query, ' from "cms_page_view" where "cms_page_view"."id" = ' ) ) );
        $this->assertCount( 0, $queries->filter( fn( $query ) => str_starts_with( $query, 'select "id", "lang", "editor" from "cms_page_variants"' ) ) );

        // nodes changed before aren't rendered again and their translations aren't stale
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Imported: New (/new)', $output );
        $this->assertStringNotContainsString( 'previous imports weren\'t updated', $output );
        $this->assertStringContainsString( 'Hello', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => 'invalid date'] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Invalid date for --since: invalid date', $output );
        $this->assertStringNotContainsString( 'About', $output );
    }


    public function testDryRunSinceListsNodesOfChangedAliasMenuLinks(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->update( ['changed' => strtotime( '2026-01-01' )] );
        $db->table( 'menu_link_content_data' )->insert( [
            'uuid' => 'link-1', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'internal:/about', 'changed' => strtotime( '2026-03-01' ),
        ] );

        // menu links to aliases are resolved to their nodes before the dry run
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01', '--dry-run' => true] ) );
        $this->assertStringContainsString( '  [1] About (page)', Artisan::output() );
    }


    public function testImportSinceWithoutModificationDates(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        DB::connection( 'drupal' )->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Changed</p>'] );

        // all nodes are rendered again if the modification dates are unknown
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'No modification dates of the Drupal nodes found, all nodes are imported.', $output );
        $this->assertStringContainsString( 'Updated: About (/about)', $output );
        $this->assertStringContainsString( 'Changed', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
    }


    public function testImportSinceReferences(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->table( 'block_content_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );
        $this->tables( 'media' );
        $schema->create( 'media_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'mid' );
            $table->string( 'langcode' );
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );
        $schema->create( 'node__field_media', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->unsignedInteger( 'field_media_target_id' );
        } );
        $schema->create( 'paragraphs_item_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'langcode' );
            $table->string( 'parent_id' );
            $table->string( 'parent_type' );
        } );
        $schema->create( 'paragraph__field_text', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->text( 'field_text_value' );
        } );

        $old = strtotime( '2026-01-01' );
        $new = strtotime( '2026-03-01' );
        $args = ['--domain' => 'example.com', '--since' => '2026-02-01'];

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => serialize( ['status' => true, 'theme' => 'olivero', 'region' => 'footer', 'plugin' => 'block_content:uuid-1'] )],
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_media', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'media']] )],
            ['name' => 'field.storage.paragraph.field_text', 'data' => serialize( ['type' => 'text_long'] )],
        ] );
        $db->table( 'block_content' )->insert( ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'] );
        $db->table( 'block_content_field_data' )->insert( ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1, 'changed' => $old] );
        $db->table( 'block_content__body' )->insert( ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'] );
        $db->table( 'node_field_data' )->update( ['changed' => $old] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1, 'changed' => $old] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p>Our team</p>'] );
        $db->table( 'menu_link_content_data' )->insert( ['uuid' => 'a', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'changed' => $old] );
        $db->table( 'media' )->insert( [['mid' => 7, 'bundle' => 'image', 'uuid' => 'media-7'], ['mid' => 8, 'bundle' => 'image', 'uuid' => 'media-8']] );
        $db->table( 'media_field_data' )->insert( [['mid' => 7, 'langcode' => 'en', 'changed' => $old], ['mid' => 8, 'langcode' => 'en', 'changed' => $old]] );
        $db->table( 'node__field_media' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_media_target_id' => 7] );
        $db->table( 'paragraphs_item_field_data' )->insert( [
            ['id' => 11, 'langcode' => 'en', 'parent_id' => '10', 'parent_type' => 'paragraph'],
            ['id' => 10, 'langcode' => 'en', 'parent_id' => '2', 'parent_type' => 'node'],
        ] );
        $db->table( 'paragraph__field_text' )->insert( ['entity_id' => 11, 'langcode' => 'en', 'field_text_value' => '<drupal-media data-entity-uuid="media-8"></drupal-media>'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $content = fn( string $name ) => (string) json_encode( PageView::where( 'name', $name )->firstOrFail()->content );
        $body = fn( int $nid, string $text ) => $db->table( 'node__body' )->where( 'entity_id', $nid )->where( 'langcode', 'en' )->update( ['body_value' => "<p>{$text}</p>"] );

        // media referenced by a node field
        $body( 1, 'Media field' );
        $db->table( 'media_field_data' )->where( 'mid', 7 )->update( ['changed' => $new] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringContainsString( 'Media field', $content( 'About' ) );

        // menu link of the node
        $body( 1, 'Menu link' );
        $db->table( 'media_field_data' )->where( 'mid', 7 )->update( ['changed' => $old] );
        $db->table( 'menu_link_content_data' )->update( ['changed' => $new] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringContainsString( 'Menu link', $content( 'About' ) );

        // media embedded in a nested paragraph
        $body( 2, 'Embedded' );
        $db->table( 'menu_link_content_data' )->update( ['changed' => $old] );
        $db->table( 'media_field_data' )->where( 'mid', 8 )->update( ['changed' => $new] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringContainsString( 'Embedded', $content( 'Team' ) );
        $this->assertStringContainsString( 'Menu link', $content( 'About' ) );

        // texts embedding one of many changed media are searched without one database search per UUID
        $body( 2, 'Many media' );
        $db->table( 'media' )->insert( array_map( fn( $mid ) => ['mid' => $mid, 'bundle' => 'image', 'uuid' => "other-{$mid}"], range( 100, 160 ) ) );
        $db->table( 'media_field_data' )->insert( array_map( fn( $mid ) => ['mid' => $mid, 'langcode' => 'en', 'changed' => $new], range( 100, 160 ) ) );
        $db->table( 'paragraph__field_text' )->update( ['field_text_value' => '<drupal-media data-entity-uuid="MEDIA-8"></drupal-media>'] );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringContainsString( 'Many media', $content( 'Team' ) );
        $this->assertStringContainsString( 'Menu link', $content( 'About' ) );
        $this->assertFalse( $this->queries( $db )->contains( fn( $query ) => str_contains( $query['query'], '"field_text_value" like' ) ) );
        $db->disableQueryLog();

        // custom blocks are updated without rendering the pages
        $body( 1, 'Not rendered' );
        $db->table( 'media_field_data' )->update( ['changed' => $old] );
        $db->table( 'block_content__body' )->update( ['body_value' => '<p>Call now</p>'] );
        $db->table( 'block_content_field_data' )->update( ['changed' => $new] );

        // only shared elements of previous imports are updated
        Element::where( 'lang', 'fr' )->update( ['name' => 'Contact FR'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );
        $this->assertStringContainsString( 'Menu link', $content( 'About' ) );
        $this->assertStringContainsString( 'Call now', (string) json_encode( Element::where( 'lang', 'en' )->where( 'name', 'Contact' )->sole()->data ) );
        $this->assertStringContainsString( 'Call now', (string) json_encode( Element::where( 'lang', 'de' )->where( 'name', 'Contact' )->sole()->data ) );
        $this->assertFalse( Element::where( 'lang', 'fr' )->where( 'name', 'Contact' )->exists() );
    }


    public function testImportViewsPages(): void
    {
        $this->tables( 'config' );
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $view = fn( string $label, string $path, bool $status = true, array $options = [] ) => serialize( ['status' => $status, 'label' => $label, 'display' => [
            'default' => ['display_plugin' => 'default', 'display_options' => []],
            'page_1' => ['display_plugin' => 'page', 'display_options' => ['path' => $path] + $options],
            'feed_1' => ['display_plugin' => 'feed', 'display_options' => ['path' => $path . '/rss.xml']],
        ]] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'language.negotiation', 'data' => serialize( ['url' => ['source' => 'path_prefix', 'prefixes' => ['en' => '', 'de' => 'de', 'fr' => 'fr']]] )],
            ['name' => 'views.view.news', 'data' => $view( 'News', 'news' )],
            ['name' => 'views.view.events', 'data' => $view( 'Events', 'events' )],
            ['name' => 'views.view.blog', 'data' => $view( 'Blog', 'blog' )],
            ['name' => 'views.view.archive', 'data' => $view( 'Archive', 'archive', false )],
            ['name' => 'views.view.old', 'data' => $view( 'Old', 'old', true, ['enabled' => false] )],
            ['name' => 'views.view.taxonomy_term', 'data' => $view( 'Taxonomy term', 'taxonomy/term/%' )],
            ['name' => 'views.view.content', 'data' => $view( 'Content', 'admin/content' )],
            ['name' => 'views.view.frontpage', 'data' => $view( 'Frontpage', 'node' )],
        ] );
        $db->table( 'menu_link_content_data' )->insert( [
            ['uuid' => 'n', 'langcode' => 'en', 'title' => 'Latest news', 'menu_name' => 'main', 'link__uri' => 'route:view.news.page_1', 'parent' => null],
            ['uuid' => 'a', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'parent' => 'menu_link_content:n'],
            ['uuid' => 'b', 'langcode' => 'en', 'title' => 'Blog', 'menu_name' => 'main', 'link__uri' => 'internal:/blog?page=1', 'parent' => null],
            ['uuid' => 'n', 'langcode' => 'de', 'title' => 'Neuigkeiten', 'menu_name' => 'main', 'link__uri' => 'route:view.news.page_1', 'parent' => null],
        ] );
        $db->table( 'menu_link_content_data' )->where( 'langcode', 'de' )->update( ['default_langcode' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Created disabled placeholder page: Latest news (/news) for the Views page "news"', $output );
        $this->assertStringContainsString( 'Created disabled placeholder page: Blog (/blog) for the Views page "blog"', $output );
        $this->assertStringContainsString( 'Skipped 1 Views pages which are not in the menu, add their content in Pagible if needed: /events (events)', $output );
        $this->assertStringContainsString( 'Menu links: 2 imported, 0 updated, 0 unchanged (2/2 menu links processed).', $output );
        // summaries of records which don't exist aren't shown
        $this->assertStringNotContainsString( 'Term pages:', $output );
        $this->assertStringNotContainsString( 'Redirects:', $output );

        $news = PageView::where( 'name', 'Latest news' )->firstOrFail();

        $this->assertSame( 0, $news->status );
        $this->assertSame( 'news', $news->path );
        $this->assertSame( $news->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );

        // translations use the translated menu link titles and the language prefix
        $de = PageView::language( 'de' )->findOrFail( $news->id );
        $fr = PageView::language( 'fr' )->findOrFail( $news->id );

        $this->assertSame( ['de', 'Neuigkeiten', 'de/news', 0], [$de->lang, $de->name, ltrim( (string) $de->path, '/' ), $de->status] );
        $this->assertSame( ['fr', 'Latest news', 'fr/news', 0], [$fr->lang, $fr->name, ltrim( (string) $fr->path, '/' ), $fr->status] );

        // placeholders aren't overwritten by later imports
        $news->variant?->forceFill( ['status' => 1] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--prune' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged placeholder page: Latest news (/news)', $output );
        $this->assertStringContainsString( 'Menu links: 0 imported, 0 updated, 2 unchanged (2/2 menu links processed).', $output );
        $this->assertStringNotContainsString( 'to the trash', $output );
        $this->assertSame( 1, PageView::where( 'name', 'Latest news' )->sole()->status );
        $this->assertSame( $news->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );
    }


    public function testImportTerms(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );
        $db->table( 'taxonomy_term_field_data' )->insert( [
            ['tid' => 7, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'News', 'description__value' => '<p>All news</p>', 'description__format' => 'basic_html', 'default_langcode' => 1],
            ['tid' => 8, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Other', 'description__value' => null, 'description__format' => null, 'default_langcode' => 1],
            ['tid' => 9, 'vid' => 'category', 'langcode' => 'en', 'name' => 'Category', 'description__value' => null, 'description__format' => null, 'default_langcode' => 1],
            ['tid' => 7, 'vid' => 'tags', 'langcode' => 'de', 'name' => 'Neuigkeiten', 'description__value' => '<p>Alle <a href="/node/1">Neuigkeiten</a></p>', 'description__format' => 'basic_html', 'default_langcode' => 0],
            ['tid' => 7, 'vid' => 'tags', 'langcode' => 'fr', 'name' => 'Actualités', 'description__value' => null, 'description__format' => null, 'default_langcode' => 0],
        ] );
        $db->table( 'taxonomy_index' )->insert( [['nid' => 1, 'tid' => 7], ['nid' => 2, 'tid' => 7]] );
        $db->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( ['body_value' => '<p>Hello <a href="/taxonomy/term/8">other</a> <a href="/taxonomy/term/7">news</a></p>'] );
        // nodes which fail to import aren't linked
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1] );
        Pages::create( ['name' => 'Manual', 'title' => 'Manual', 'path' => '/team', 'tag' => 'page', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/7', 'alias' => '/tags/news', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/7', 'alias' => '/schlagworte/neuigkeiten', 'langcode' => 'de'],
            ['path' => '/taxonomy/term/9', 'alias' => '/category', 'langcode' => 'en'],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $log = $this->queries( $db );
        $selects = $log->pluck( 'query' )->filter( fn( $query ) => str_starts_with( $query, 'select' ) && str_contains( $query, ' from "taxonomy_index" ' ) );
        $db->disableQueryLog();
        $output = Artisan::output();

        // only the IDs of the terms are fetched for the term pages and the term URLs before the terms are fetched in chunks
        $terms = $log->filter( fn( $query ) => str_contains( $query['query'], ' from "taxonomy_term_field_data" ' ) );
        $this->assertCount( 2, $terms->filter( fn( $query ) => str_starts_with( $query['query'], 'select "tid" from' ) ) );
        $this->assertSame( [[1, 7, 8]], $terms->filter( fn( $query ) => str_contains( $query['query'], 'where "default_langcode" = ? and "tid" in (?, ?) order by' ) )->pluck( 'bindings' )->values()->all() );
        // the linked terms are fetched for their URLs at once
        $this->assertSame( [[8, 7]], $terms->filter( fn( $query ) => str_contains( $query['query'], 'where "tid" in' ) )->pluck( 'bindings' )->values()->all() );
        // only the used node columns are fetched, the translations again for the nodes imported next
        $nodes = $log->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "node_field_data" where "default_langcode" = ?' ) );
        $this->assertCount( 3, $nodes );
        $this->assertCount( 3, $nodes->filter( fn( $query ) => str_starts_with( $query, 'select "nid", "type", "langcode", "title", "status", "created", "default_langcode" from' ) ) );

        // only the used columns of the terms are fetched
        $this->assertContains( 'select "tid", "langcode", "name", "default_langcode", "status" from "taxonomy_term_field_data" where "tid" in (?, ?)',
            $terms->pluck( 'query' )->all() );

        // only the term and node IDs of the term index are used
        $this->assertContains( 'select "tid", "nid" from "taxonomy_index"', $selects->map( fn( $query ) => explode( ' where ', $query )[0] )->all() );
        // the aliases of the terms are fetched only once
        $aliases = $log->filter( fn( $query ) => str_contains( $query['query'], ' from "path_alias" ' ) && in_array( '/taxonomy/term/7', $query['bindings'], true ) );
        $this->assertCount( 1, $aliases );
        $this->assertStringContainsString( 'Term pages: 1 imported, 0 updated, 0 unchanged (1/1 term pages processed).', $output );
        // without language prefix, translations with the URL of their source term are skipped
        $this->assertStringContainsString( 'Skipped translation: Actualités (/tags/news) [fr] (URL is used by another page)', $output );

        $page = PageView::where( 'name', 'News' )->firstOrFail();
        $content = (string) json_encode( $page->content );

        $this->assertSame( 2, $page->status );
        $this->assertSame( 'tags/news', $page->path );
        $this->assertStringContainsString( 'All news', $content );
        $this->assertStringContainsString( 'href=\\"\\/about\\"', $content );
        $this->assertStringNotContainsString( 'team', $content );
        $this->assertFalse( PageView::where( 'name', 'Other' )->exists() );

        // translations use their own alias and the translated node URLs
        $de = PageView::language( 'de' )->findOrFail( $page->id );

        $this->assertSame( ['Neuigkeiten', 'schlagworte/neuigkeiten', 2], [$de->name, $de->path, $de->status] );
        $this->assertStringContainsString( 'Alle <a href=\\"\\/ueber-uns\\">Neuigkeiten', (string) json_encode( $de->content ) );
        $this->assertStringContainsString( 'href=\\"\\/ueber-uns\\">\u00dcber uns', (string) json_encode( $de->content ) );
        $this->assertNull( PageView::language( 'fr' )->find( $page->id ) );
        $this->assertFalse( PageView::where( 'name', 'Category' )->exists() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $this->assertStringContainsString( 'Term pages: 0 imported, 0 updated, 1 unchanged (1/1 term pages processed).', Artisan::output() );
    }


    public function testTermLinksAreOnlySanitizedIfNeeded(): void
    {
        $titles = [1 => 'Tom & "Jerry" <b>', 2 => "Tab\tbed", 3 => 'Plain', 4 => 'External'];
        $nodes = $this->rows( array_map( fn( $title ) => (object) ['langcode' => 'en', 'title' => $title], $titles ) );
        $paths = [1 => "/a/b?x=1&y=%202&z='a'#top", 2 => '/tab', 3 => '/über uns', 4 => '//example.com/a'];
        $html = new \Aimeos\Cms\Import\DrupalHtml( fn() => null, fn() => null, fn() => null, fn() => null );

        $urls = $this->createStub( DrupalUrls::class );
        $urls->method( 'node' )->willReturnCallback( fn( int $nid ) => $paths[$nid] );
        $content = $this->createMock( DrupalContent::class );
        $content->expects( $this->exactly( 5 ) )->method( 'html' )->with( 'en' )->willReturn( $html );

        $terms = new \Aimeos\Cms\Import\DrupalTerms( $this->createStub( \Aimeos\Cms\Import\DrupalReader::class ), $this->createStub( DrupalData::class ),
            $this->createStub( DrupalLanguages::class ), $urls, $content, $nodes, [], fn() => null );
        $term = (object) ['name' => 'News', 'langcode' => 'en'];
        $list = fn( array $nids ) => '<ul>' . implode( '', array_map( fn( $nid ) => '<li><a href="' . e( $paths[$nid] ) . '">' . e( $titles[$nid] ) . '</a></li>', $nids ) ) . '</ul>';

        // plain URLs and titles are added like the HTML sanitizer returns them, only lists with control characters, unencoded or protocol-relative URLs are sanitized
        foreach( [[1], [2], [3], [4], [1, 1], [1, 2], [3, 1]] as $nids )
        {
            $result = $terms->content( $term, $nids );

            $this->assertSame( [], $result['fileIds'] );
            $this->assertCount( 1, $result['elements'] );
            $this->assertSame( ['type' => 'html', 'group' => 'main', 'data' => ['text' => trim( Utils::html( $list( $nids ) ) )]],
                array_diff_key( $result['elements'][0], ['id' => 1] ) );
        }

        $this->assertSame( ['elements' => [], 'fileIds' => []], $terms->content( $term, [] ) );
    }


    public function testTermLinksAreLimitedToNewestNodes(): void
    {
        $nodes = $this->rows( array_map( fn( $nid ) => (object) ['langcode' => 'en', 'title' => "Node {$nid}"], array_combine( range( 1, 5 ), range( 1, 5 ) ) ) );
        $resolved = [];

        $urls = $this->createStub( DrupalUrls::class );
        $urls->method( 'node' )->willReturnCallback( function( int $nid ) use ( &$resolved ) {
            $resolved[] = $nid;
            return $nid === 2 ? null : "/node-{$nid}";
        } );

        $terms = new class( $this->createStub( \Aimeos\Cms\Import\DrupalReader::class ), $this->createStub( DrupalData::class ),
            $this->createStub( DrupalLanguages::class ), $urls, $this->createStub( DrupalContent::class ), $nodes, [], fn() => null ) extends \Aimeos\Cms\Import\DrupalTerms {
            protected const LINK_SIZE = 2;
        };

        // nodes without URL don't count and no links beyond the limit are resolved
        $result = $terms->content( (object) ['name' => 'News', 'langcode' => 'en'], [1, 2, 3, 4, 5] );

        $this->assertSame( '<ul><li><a href="/node-1">Node 1</a></li><li><a href="/node-3">Node 3</a></li></ul>', $result['elements'][0]['data']['text'] ?? null );
        $this->assertSame( [1, 2, 3], $resolved );
    }


    public function testAliasesAreReducedPerLanguage(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/1', 'alias' => '/about-us', 'langcode' => 'en'],
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'und'],
            ['path' => '/taxonomy/term/7', 'alias' => '/tags/old-news', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/7', 'alias' => '/tags/news', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/8', 'alias' => '/schlagworte/andere', 'langcode' => 'de'],
        ] );

        $data = new DrupalData( $reader = new Drupal8( $db, fn() => null ) );
        $languages = new DrupalLanguages( $data, [] );
        $nodes = $this->rows( [1 => (object) ['nid' => 1], 2 => (object) ['nid' => 2]] );
        $urls = new DrupalUrls( $reader, $data, $languages, 'en', $nodes, [], fn() => null );

        // the newest alias of each language wins, language neutral aliases are used for all languages
        $this->assertSame( '/about-us', $urls->alias( 1, 'en' ) );
        $this->assertSame( '/ueber-uns', $urls->alias( 1, 'de' ) );
        $this->assertNull( $urls->alias( 1, 'fr' ) );
        $this->assertSame( '/about-us', $urls->alias( 1, 'fr', true ) );
        $this->assertSame( '/team', $urls->alias( 2, 'de' ) );
        // older aliases of the same language still resolve to their nodes
        $this->assertSame( 1, $urls->linkNid( (object) ['link__uri' => 'internal:/about'] ) );
        $this->assertSame( 1, $urls->linkNid( (object) ['link__uri' => 'internal:/about-us'] ) );
        $this->assertSame( 2, $urls->linkNid( (object) ['link__uri' => 'internal:/team'] ) );

        Schema::connection( 'drupal' )->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $db->table( 'taxonomy_term_field_data' )->insert( [['tid' => 7, 'vid' => 'tags'], ['tid' => 8, 'vid' => 'tags'], ['tid' => 9, 'vid' => 'tags']] );
        $data = new DrupalData( $reader = new Drupal8( $db, fn() => null ) );
        $urls = new DrupalUrls( $reader, $data, new DrupalLanguages( $data, [] ), 'en', Collection::make(), [], fn() => null, '', ['tags'] );

        $this->assertSame( '/tags/news', $urls->termAlias( 7, 'en' ) );
        $this->assertNull( $urls->termAlias( 8, 'en' ) );
        $this->assertSame( '/schlagworte/andere', $urls->termAlias( 8, 'en', true ) );
        $this->assertNull( $urls->termAlias( 9, 'en', true ) );
    }


    public function testTermIndexIsStreamedAndLimited(): void
    {
        $rows = [[7, 1], [8, 1], [7, 99], [7, 2], [7, 3], [8, 2], [7, 4]];
        $consumed = 0;

        $reader = $this->createMock( \Aimeos\Cms\Import\DrupalReader::class );
        $reader->expects( $this->once() )->method( 'termIndex' )->with( [7, 8], 2 )->willReturn( LazyCollection::make( function() use ( $rows, &$consumed ) {
            foreach( $rows as [$tid, $nid] ) {
                $consumed++;
                yield (object) ['tid' => $tid, 'nid' => $nid];
            }
        } ) );
        $data = $this->createStub( DrupalData::class );
        $data->method( 'hasTable' )->willReturn( true );
        $nodes = $this->rows( array_map( fn( $nid ) => (object) ['nid' => $nid], array_combine( range( 1, 4 ), range( 1, 4 ) ) ) );

        $terms = new class( $reader, $data, $this->createStub( DrupalLanguages::class ), $this->createStub( DrupalUrls::class ),
            $this->createStub( DrupalContent::class ), $nodes, [], fn() => null ) extends \Aimeos\Cms\Import\DrupalTerms {
            protected const INDEX_SIZE = 2;

            /**
             * @param array<int, int> $tids
             * @return array<int, array<int, bool>>
             */
            public function tagged( array $tids ): array
            {
                return $this->index( $tids );
            }
        };

        // the newest imported nodes are kept in the order of the index up to the limit per term
        $this->assertSame( [7 => [1 => true, 2 => true], 8 => [1 => true, 2 => true]], $terms->tagged( [7, 8] ) );
        $this->assertSame( count( $rows ), $consumed );
    }


    public function testTermIndexLimitsRowsPerTerm(): void
    {
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );
        $db->table( 'taxonomy_index' )->insert( [
            ['nid' => 1, 'tid' => 7, 'sticky' => 0, 'created' => 100],
            ['nid' => 2, 'tid' => 7, 'sticky' => 0, 'created' => 300],
            ['nid' => 3, 'tid' => 7, 'sticky' => 1, 'created' => 50],
            ['nid' => 4, 'tid' => 7, 'sticky' => 0, 'created' => 200],
            ['nid' => 5, 'tid' => 8, 'sticky' => 0, 'created' => 100],
        ] );
        $reader = new Drupal8( $db, fn() => null );
        $rows = fn( ?int $limit ) => $reader->termIndex( [7, 8, 7], $limit )->map( fn( $row ) => [(int) $row->tid, (int) $row->nid] )->values()->all();

        // only the newest rows of each term are fetched in the order of the term pages
        $this->assertSame( [[7, 3], [7, 2], [8, 5]], $rows( 2 ) );
        // all rows are fetched without limit
        $this->assertSame( [[7, 3], [7, 2], [7, 4], [7, 1], [8, 5]], $rows( null ) );
    }


    public function testMenuLinkTranslationsReuseParentVariants(): void
    {
        Schema::connection( 'drupal' )->create( 'menu_link_content_data', function( Blueprint $table ) {
            $table->id();
            $table->string( 'uuid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
            $table->string( 'menu_name' );
            $table->string( 'link__uri' );
            $table->string( 'parent' )->nullable();
            $table->boolean( 'enabled' )->default( true );
            $table->integer( 'weight' )->default( 0 );
            $table->boolean( 'default_langcode' )->default( true );
        } );

        $links = [['uuid' => 'b', 'langcode' => 'en', 'title' => 'About', 'menu_name' => 'main', 'link__uri' => 'entity:node/1', 'parent' => null, 'default_langcode' => 1]];

        foreach( ['c' => 'GitHub', 'd' => 'GitLab', 'e' => 'Codeberg'] as $uuid => $title )
        {
            $links[] = ['uuid' => $uuid, 'langcode' => 'en', 'title' => $title, 'menu_name' => 'main', 'link__uri' => "https://{$uuid}.example.com", 'parent' => 'menu_link_content:b', 'default_langcode' => 1];
            $links[] = ['uuid' => $uuid, 'langcode' => 'de', 'title' => "{$title} DE", 'menu_name' => 'main', 'link__uri' => "https://{$uuid}.example.com", 'parent' => 'menu_link_content:b', 'default_langcode' => 0];
        }

        DB::connection( 'drupal' )->table( 'menu_link_content_data' )->insert( $links );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $about = fn() => PageView::where( 'name', 'About' )->firstOrFail();
        $variants = fn( array $ids ) => $this->queries( $cms )->filter( fn( $query ) => str_contains( $query['query'], ' from "cms_page_view" where "cms_page_view"."id" = ?' ) )
            ->pluck( 'bindings' )->flatten()->filter( fn( $id ) => in_array( $id, $ids, true ) )->countBy()->all();

        // the variants of the parent page are fetched once for its translations and once for its menu links, new pages have no variants yet
        $cms->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $cms->disableQueryLog();

        $ids = PageView::whereIn( 'name', ['GitHub', 'GitLab', 'Codeberg'] )->pluck( 'id' )->map( fn( $id ) => (string) $id )->all();
        $this->assertSame( [(string) $about()->id => 2], $variants( [(string) $about()->id, ...$ids] ) );
        $this->assertEqualsCanonicalizing( ['ueber-uns/github-de', 'ueber-uns/gitlab-de', 'ueber-uns/codeberg-de'],
            array_map( fn( $id ) => PageView::language( 'de' )->findOrFail( $id )->path, $ids ) );

        $cms->flushQueryLog();
        $cms->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $cms->disableQueryLog();

        // existing pages fetch their own variants, the prefetched variants of the parent page are used for its translations
        $this->assertEquals( [(string) $about()->id => 1] + array_fill_keys( $ids, 1 ), $variants( [(string) $about()->id, ...$ids] ) );
        $this->assertStringNotContainsString( 'previous imports', Artisan::output() );
    }


    public function testImportPrune(): void
    {
        $this->createBlockTables();

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => serialize( [
                'status' => true, 'theme' => 'olivero', 'region' => 'footer', 'weight' => 0, 'plugin' => 'block_content:uuid-1',
            ] )],
        ] );
        $db->table( 'block_content' )->insert( ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'] );
        $db->table( 'block_content_field_data' )->insert( ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1] );
        $db->table( 'block_content__body' )->insert( ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Old', 'default_langcode' => 1],
            ['nid' => 4, 'type' => 'page', 'langcode' => 'en', 'title' => 'Gone', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/3', 'alias' => '/old', 'langcode' => 'en'],
            ['path' => '/node/4', 'alias' => '/gone', 'langcode' => 'en'],
        ] );
        $db->table( 'node__body' )->insert( ['entity_id' => 4, 'langcode' => 'en', 'body_value' => '<p>Gone</p><img src="/sites/default/files/gone.jpg" alt="Gone" />'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );
        $this->assertTrue( File::where( 'path', 'https://example.com/sites/default/files/gone.jpg' )->exists() );

        // pages added in Pagible below imported pages keep their parent pages
        $old = PageView::where( 'name', 'Old' )->firstOrFail();
        Pages::create( ['name' => 'Manual', 'title' => 'Manual', 'path' => '/old/manual', 'tag' => 'page', 'domain' => 'example.com',
            'lang' => 'en', 'status' => 1, 'editor' => 'admin'], [], $old );

        $db->table( 'node_field_data' )->whereIn( 'nid', [3, 4] )->delete();
        $db->table( 'node_field_data' )->where( 'langcode', 'fr' )->delete();
        $db->table( 'config' )->where( 'name', 'block.block.olivero_contact' )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files', '--prune' => true, '--dry-run' => true] ) );
        $this->assertStringNotContainsString( 'to the trash', Artisan::output() );
        $this->assertTrue( PageView::where( 'name', 'Gone' )->exists() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files', '--prune' => true] ) );
        $output = Artisan::output();

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertStringContainsString( 'Kept /old [en] (used by translations or child pages which are kept)', $output );
        $this->assertStringContainsString( 'Kept Contact [en] (used by pages which are kept)', $output );
        $this->assertStringContainsString( 'Moved 2 pages and translations, 2 shared elements and 1 files to the trash', $output );
        $this->assertFalse( File::where( 'path', 'https://example.com/sites/default/files/gone.jpg' )->exists() );
        $this->assertFalse( PageView::where( 'name', 'Gone' )->exists() );
        $this->assertTrue( PageView::withTrashed()->where( 'name', 'Gone' )->exists() );
        $this->assertTrue( PageView::where( 'name', 'Old' )->exists() );
        $this->assertNull( PageView::language( 'fr' )->find( $page->id ) );
        $this->assertNotNull( PageView::language( 'de' )->find( $page->id ) );
        $this->assertSame( ['en'], Element::orderBy( 'lang' )->pluck( 'lang' )->all() );

        // later imports restore the pages if the nodes are available again
        $db->table( 'node_field_data' )->insert( ['nid' => 4, 'type' => 'page', 'langcode' => 'en', 'title' => 'Gone', 'default_langcode' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files', '--prune' => true] ) );
        $this->assertTrue( PageView::where( 'name', 'Gone' )->exists() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files', '--prune' => true, '--since' => '2026-01-01'] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'The --since option can not be used together with --prune', $output );
        $this->assertStringNotContainsString( 'Gone', $output );
    }


    public function testImportDomainAccess(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'node__field_domain_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( 'field_domain_access_target_id' );
        } );
        $schema->create( 'node__field_domain_all_affiliates', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->boolean( 'field_domain_all_affiliates_value' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/node/1']] )],
            ['name' => 'domain.record.site_a', 'data' => serialize( ['id' => 'site_a', 'hostname' => 'a.example.com'] )],
            ['name' => 'domain.record.site_b', 'data' => serialize( ['id' => 'site_b', 'hostname' => 'b.example.com'] )],
            ['name' => 'domain.record.site_d', 'data' => serialize( ['id' => 'site_d'] )],
            ['name' => 'domain.config.site_b.system.site', 'data' => serialize( ['page' => ['front' => '/node/2']] )],
        ] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Legal', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/legal', 'langcode' => 'en'],
        ] );
        $db->table( 'node__field_domain_access' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_domain_access_target_id' => 'site_a'],
            ['entity_id' => 2, 'langcode' => 'en', 'field_domain_access_target_id' => 'site_b'],
        ] );
        $db->table( 'node__field_domain_all_affiliates' )->insert( ['entity_id' => 3, 'langcode' => 'en', 'field_domain_all_affiliates_value' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringContainsString( 'Drupal uses the Domain Access module, the nodes of all domains are imported into one site', Artisan::output() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_c'] ) );
        $this->assertStringContainsString( 'Domain Access domain "site_c" not found.', Artisan::output() );
        $this->assertSame( 0, PageView::count() );

        // domains without hostname don't replace the missing --domain
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_d', '--dry-run' => true] ) );
        $this->assertStringNotContainsString( 'Using the hostname', Artisan::output() );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_a'] ) );
        $this->assertStringContainsString( 'Using the hostname of the Domain Access domain as --domain=a.example.com', Artisan::output() );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_b', '--domain' => 'b.example.com'] ) );

        // each domain has its own root page and front page
        $names = fn( string $domain, string $tag ) => PageView::where( 'domain', $domain )->where( 'tag', $tag )->pluck( 'name' )->all();

        $this->assertSame( ['About'], $names( 'a.example.com', 'root' ) );
        $this->assertSame( ['Team'], $names( 'b.example.com', 'root' ) );
        $this->assertSame( ['Legal'], $names( 'a.example.com', 'page' ) );
        $this->assertSame( ['Legal'], $names( 'b.example.com', 'page' ) );

        // pruning one domain keeps the pages of the other ones
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_a', '--prune' => true] ) );
        $this->assertStringNotContainsString( 'to the trash', Artisan::output() );
        $this->assertSame( ['Team'], $names( 'b.example.com', 'root' ) );
        $this->assertSame( ['Legal'], $names( 'b.example.com', 'page' ) );
    }


    public function testImportDraftLayoutBuilder(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'vid' )->nullable() );
        $schema->table( 'node__body', fn( Blueprint $table ) => $table->text( 'body_summary' )->nullable() );
        $schema->create( 'node_field_revision', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
        } );
        $schema->create( 'node_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->text( 'body_summary' )->nullable();
            $table->string( 'body_format' )->default( 'basic_html' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( [
                'content' => ['body' => ['weight' => 0]],
                'third_party_settings' => ['layout_builder' => ['enabled' => true, 'allow_custom' => false, 'sections' => [[
                    'layout_id' => 'layout_onecol',
                    'components' => ['c1' => ['region' => 'content', 'weight' => 0, 'configuration' => ['id' => 'field_block:node:page:body']]],
                ]]]],
            ] )],
        ] );
        $db->table( 'node_field_data' )->where( 'nid', 1 )->update( ['vid' => 1] );
        $db->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( ['body_summary' => 'Published summary'] );
        $db->table( 'node_field_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'langcode' => 'en', 'title' => 'About'],
            ['nid' => 1, 'vid' => 3, 'langcode' => 'en', 'title' => 'About draft'],
        ] );
        $db->table( 'node_revision__body' )->insert( ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en', 'body_value' => '<p>Draft text</p>', 'body_summary' => 'Draft summary'] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Skipped draft', Artisan::output() );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $versions = $page->versions()->where( 'published', false )->get()->all();
        $aux = (string) json_encode( $versions[0]->aux ?? null );

        $this->assertStringContainsString( 'Hello', (string) json_encode( $page->content ) );
        $this->assertStringContainsString( 'Published summary', (string) json_encode( $page->meta ) );
        $this->assertCount( 1, $versions );
        $this->assertSame( 'About draft', $versions[0]->data->title ?? null );
        $this->assertStringContainsString( 'Draft text', $aux );
        $this->assertStringContainsString( 'Draft summary', $aux );
        $this->assertStringNotContainsString( 'Published summary', $aux );
    }


    public function testImportDraftCustomLayout(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'vid' )->nullable() );
        $schema->create( 'node_field_revision', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
        } );

        foreach( ['node__layout_builder__layout' => 'entity_id', 'node_revision__layout_builder__layout' => 'revision_id', 'node_revision__body' => 'revision_id'] as $name => $column )
        {
            $schema->create( $name, function( Blueprint $table ) use ( $name, $column ) {
                $table->unsignedInteger( 'entity_id' );
                $column === 'revision_id' ? $table->unsignedInteger( 'revision_id' ) : null;
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $name === 'node_revision__body' ? $table->text( 'body_value' ) : $table->text( 'layout_builder__layout_section' );
                $name === 'node_revision__body' ? $table->string( 'body_format' )->default( 'basic_html' ) : null;
            } );
        }

        $component = fn( string $id ) => [$id => ['region' => 'content', 'weight' => 0, 'configuration' => ['id' => 'field_block:node:page:body']]];
        $section = fn( array $components ) => serialize( ['layout_id' => 'layout_onecol', 'components' => $components] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( [
                'content' => ['body' => ['weight' => 0]],
                'third_party_settings' => ['layout_builder' => ['enabled' => true, 'allow_custom' => true, 'sections' => []]],
            ] )],
        ] );
        $db->table( 'node_field_data' )->where( 'nid', 1 )->update( ['vid' => 1] );
        $db->table( 'node_field_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'langcode' => 'en', 'title' => 'About'],
            ['nid' => 1, 'vid' => 3, 'langcode' => 'en', 'title' => 'About draft'],
        ] );
        $db->table( 'node_revision__body' )->insert( ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en', 'body_value' => '<p>Draft text</p>'] );
        // the draft uses its own custom layout showing the body twice
        $db->table( 'node__layout_builder__layout' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'layout_builder__layout_section' => $section( $component( 'c1' ) )] );
        $db->table( 'node_revision__layout_builder__layout' )->insert( ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en',
            'layout_builder__layout_section' => $section( $component( 'c1' ) + $component( 'c2' ) )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $aux = (string) json_encode( $page->versions()->where( 'published', false )->firstOrFail()->aux );

        $this->assertSame( 1, substr_count( (string) json_encode( $page->content ), 'Hello' ) );
        $this->assertSame( 2, substr_count( $aux, 'Draft text' ) );
    }


    public function testSiteHosts(): void
    {
        $site = new DrupalSite( 'http://www.drupal.test:8080/sites/default/files', 'example.com', ['example.de'] );

        // host names with and without "www." and with port are the same site
        $this->assertTrue( $site->host( 'drupal.test' ) );
        $this->assertTrue( $site->host( 'WWW.Example.com:443' ) );
        $this->assertTrue( $site->host( 'example.de' ) );
        $this->assertFalse( $site->host( 'other.com' ) );
        $this->assertFalse( $site->host( '' ) );

        // the domains of the languages only belong to the content of all domains
        $this->assertTrue( $site->host( 'drupal.test', '' ) );
        $this->assertTrue( $site->host( 'example.com', '' ) );
        $this->assertFalse( $site->host( 'example.de', '' ) );

        // content of a language domain only belongs to that domain
        $this->assertTrue( $site->host( 'www.example.de', 'example.de' ) );
        $this->assertFalse( $site->host( 'example.com', 'example.de' ) );
        $this->assertFalse( $site->host( 'drupal.test', 'example.de' ) );
    }


    public function testSiteUrls(): void
    {
        $site = new DrupalSite( 'http://drupal.test:8080/drupal/sites/default/files', 'example.com' );

        $this->assertSame( 'http://drupal.test:8080', $site->origin() );
        $this->assertSame( 'http://drupal.test:8080/drupal', $site->base() );
        $this->assertSame( 'http://drupal.test:8080/drupal/sites/default/files/a.jpg', $site->file( '/a.jpg' ) );
        $this->assertSame( 'https://cdn.test/a.jpg', $site->file( 'https://cdn.test/a.jpg' ) );

        // image style derivatives are replaced by the original images
        $this->assertSame( 'http://drupal.test:8080/drupal/sites/default/files/photo.jpg?a=1',
            $site->image( '/drupal/sites/default/files/styles/large/public/photo.jpg?itok=abc&a=1' ) );
        $this->assertSame( 'http://drupal.test:8080/x.png', $site->image( 'https://drupal.test/x.png' ) );
        $this->assertSame( 'http://drupal.test:8080/x.png', $site->image( '//drupal.test/x.png' ) );
        $this->assertSame( 'https://other.test/x.png', $site->image( 'https://other.test/x.png' ) );
        $this->assertSame( 'http://drupal.test:8080/drupal/sites/default/files/x.png', $site->image( 'x.png' ) );

        // without "/sites/" in the file base, the origin is the base
        $this->assertSame( 'https://drupal.test', ( new DrupalSite( 'https://drupal.test/files', '' ) )->base() );
        $this->assertSame( '', ( new DrupalSite( 'files', '' ) )->base() );
    }


    public function testRedirectsAreReadOneByOne(): void
    {
        Schema::connection( 'drupal' )->create( 'redirect', function( Blueprint $table ) {
            $table->id( 'rid' );
            $table->string( 'redirect_source__path' );
            $table->string( 'redirect_redirect__uri' );
            $table->string( 'language' )->default( 'und' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'redirect' )->insert( [
            ['redirect_source__path' => 'old-about', 'redirect_redirect__uri' => 'entity:node/1'],
            ['redirect_source__path' => 'node/1', 'redirect_redirect__uri' => 'internal:/elsewhere'],
            ['redirect_source__path' => 'node/2', 'redirect_redirect__uri' => 'internal:/other'],
        ] );

        $data = new DrupalData( $reader = new Drupal8( $db, fn() => null ) );
        $languages = new DrupalLanguages( $data, [] );
        $urls = new DrupalUrls( $reader, $data, $languages, 'en', Collection::make(), [], fn() => null );
        $redirects = new DrupalRedirects( $reader, $data, $languages, $urls, Collection::make() );

        $all = $redirects->all( $this->paths( [1 => 'about'] ), [1], true );

        // the redirects aren't collected in memory before they are returned
        $this->assertInstanceOf( \Generator::class, $all );

        // the redirects of the paths of imported nodes replace the ones of the Redirect module
        $this->assertSame( [
            ['domain' => '', 'path' => 'old-about', 'to' => '/about', 'language' => null],
            ['domain' => '', 'path' => 'node/2', 'to' => '/other', 'language' => null],
            ['domain' => '', 'path' => 'node/1', 'to' => '/about', 'language' => null],
        ], iterator_to_array( $all, false ) );
    }


    public function testRedirectsTargetOfPages(): void
    {
        $data = new DrupalData( $reader = new Drupal8( DB::connection( 'drupal' ), fn() => null ) );
        $languages = new DrupalLanguages( $data, [] );
        $urls = new DrupalUrls( $reader, $data, $languages, 'en', Collection::make(), [], fn() => null );
        $redirects = new DrupalRedirects( $reader, $data, $languages, $urls, Collection::make() );
        $pages = $this->paths( [42 => 'about'] );

        // node targets only resolve to the pages passed
        $this->assertSame( '/about?a=1', $redirects->target( 'entity:node/42?a=1', $pages ) );
        $this->assertSame( '/about', $redirects->target( 'internal:/node/42', $pages ) );
        $this->assertSame( '', $redirects->target( 'entity:node/42', $this->paths( [] ) ) );
        $this->assertSame( '', $redirects->target( 'entity:node/43', $pages ) );
        // the page with the empty path is the home page
        $this->assertSame( '/', $redirects->target( 'entity:node/7', $this->paths( [7 => ''] ) ) );
        $this->assertSame( '/', $redirects->target( 'route:<front>', $pages ) );
        $this->assertSame( '/contact', $redirects->target( 'internal:/contact', $pages ) );
        $this->assertSame( 'https://example.org', $redirects->target( 'https://example.org', $pages ) );
        $this->assertSame( '', $redirects->target( 'mailto:a@example.org', $pages ) );
    }


    public function testUrlsKeepOnlyFirstAliasPerLanguage(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/1', 'alias' => '/about-us', 'langcode' => 'en'],
            ['path' => '/node/1', 'alias' => '/neutral', 'langcode' => 'zxx'],
            ['path' => '/node/3', 'alias' => '/kontakt', 'langcode' => 'de'],
        ] );

        $nodes = $this->rows( [
            1 => (object) ['nid' => 1, 'title' => 'About', 'langcode' => 'en', 'type' => 'page'],
            2 => (object) ['nid' => 2, 'title' => 'Team', 'langcode' => 'en', 'type' => 'page'],
            3 => (object) ['nid' => 3, 'title' => 'Contact', 'langcode' => 'en', 'type' => 'page'],
        ] );
        $data = new DrupalData( $reader = new Drupal8( $db, fn() => null ) );
        $urls = new DrupalUrls( $reader, $data, new DrupalLanguages( $data, [] ), 'en', $nodes, [], fn() => null );

        // the newest alias of the language wins, then the one without language and of any language if allowed
        $this->assertSame( '/about-us', $urls->alias( 1, 'en' ) );
        $this->assertSame( '/ueber-uns', $urls->alias( 1, 'de' ) );
        $this->assertSame( '/neutral', $urls->alias( 1, 'fr' ) );
        $this->assertNull( $urls->alias( 2, 'en', true ) );
        $this->assertNull( $urls->alias( 3, 'en' ) );
        $this->assertSame( '/kontakt', $urls->alias( 3, 'en', true ) );

        // older aliases still resolve menu links to the node
        $this->assertSame( 1, $urls->linkNid( (object) ['link__uri' => 'internal:/about'] ) );
        $this->assertSame( 1, $urls->linkNid( (object) ['link__uri' => 'internal:/about-us'] ) );

        // only the alias strings of the first records per language are kept
        $this->assertSame( [3 => ['de' => '/kontakt'], 1 => ['zxx' => '/neutral', 'en' => '/about-us', 'de' => '/ueber-uns']],
            ( new \ReflectionProperty( DrupalUrls::class, 'aliases' ) )->getValue( $urls ) );
    }


    public function testPlacementsCompilePathPatternsOnce(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/Blog/post', 'langcode' => 'en'],
            ['path' => '/node/4', 'alias' => '/home', 'langcode' => 'en'],
            ['path' => '/node/6', 'alias' => '/blog', 'langcode' => 'en'],
        ] );

        $nodes = $this->rows( array_map( fn( $nid ) => (object) ['nid' => $nid, 'title' => "Node {$nid}", 'langcode' => 'en', 'type' => 'page'], array_combine( range( 1, 6 ), range( 1, 6 ) ) ) );
        $data = new DrupalData( $reader = new Drupal8( $db, fn() => null ) );
        $urls = new DrupalUrls( $reader, $data, new DrupalLanguages( $data, [] ), 'en', $nodes, [], fn() => 4 );
        $placements = new \Aimeos\Cms\Import\DrupalPlacements( $data, $urls, '', '', fn() => null, fn() => null );

        $block = ['key' => 'promo', 'visibility' => ['request_path' => ['id' => 'request_path', 'pages' => "/TEAM\r\n\n/blog/*\n<front>\n/node/5"]]];
        $visible = array_filter( range( 1, 6 ), fn( $nid ) => $placements->visible( $block, $nodes->get( $nid ), 'en' ) );

        // patterns match case-insensitively with wildcards, the front page and the system paths
        $this->assertSame( [2, 3, 4, 5], array_values( $visible ) );
        // the patterns are compiled once for all pages
        $this->assertSame( ["/TEAM\r\n\n/blog/*\n<front>\n/node/5"], array_keys( ( new \ReflectionProperty( \Aimeos\Cms\Import\DrupalPlacements::class, 'patterns' ) )->getValue( $placements ) ) );
    }


    public function testDataFetchesFieldValuesInBatches(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node__body' )->insert( [
            ['entity_id' => 2, 'langcode' => 'en', 'body_value' => 'Two'],
            ['entity_id' => 3, 'langcode' => 'en', 'body_value' => 'Three'],
            ['entity_id' => 4, 'langcode' => 'en', 'body_value' => 'Four'],
        ] );

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $data->sequence( [1, 2, 3, 4], fn( int $nid ) => $nid === 3 );
        $value = fn( int $nid ) => $data->fieldValues( 'node', 'body', $nid, 'en' )->first()->body_value ?? null;
        $queries = fn() => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "node__body"' ) )->count();

        $db->enableQueryLog();

        // the next nodes in import order are fetched together except the skipped ones
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $value( 1 ) );
        $this->assertSame( ['Two', 'Four'], [$value( 2 ), $value( 4 )] );
        $this->assertSame( 1, $queries() );
        $this->assertSame( 'Three', $value( 3 ) );
        $this->assertSame( 2, $queries() );

        // values of the imported nodes are fetched again but not together with other imported nodes
        $data->forget( 1 );
        $data->forget( 2 );
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $value( 1 ) );
        $this->assertSame( 3, $queries() );
        $this->assertSame( 'Two', $value( 2 ) );
        $this->assertSame( 4, $queries() );

        $db->disableQueryLog();
    }


    public function testDataFetchesFieldValuesByContentType(): void
    {
        $this->tables( 'config' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.field.node.page.body', 'data' => serialize( ['label' => 'Body'] )],
            ['name' => 'field.field.node.article.field_tags', 'data' => serialize( ['label' => 'Tags'] )],
        ] );
        $db->table( 'node__body' )->insert( [
            ['entity_id' => 2, 'langcode' => 'en', 'body_value' => 'Two'],
            ['entity_id' => 3, 'langcode' => 'en', 'body_value' => 'Three'],
        ] );

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $data->sequence( [1, 2, 3, 4, 5], null, [1 => 'page', 2 => 'article', 3 => 'page', 4 => 'article', 5 => 'blog'] );
        $value = fn( int $nid ) => $data->fieldValues( 'node', 'body', $nid, 'en' )->first()->body_value ?? null;
        $queries = fn() => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "node__body"' ) )
            ->map( fn( $query ) => $query['bindings'] )->values()->all();

        $db->enableQueryLog();

        // nodes of content types with the field and without according to the configuration are fetched separately
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $value( 1 ) );
        $this->assertSame( 'Three', $value( 3 ) );
        $this->assertSame( [[0, 1, 3, 5]], $queries() );
        $this->assertSame( 'Two', $value( 2 ) );
        $this->assertNull( $value( 4 ) );
        $this->assertSame( [[0, 1, 3, 5], [0, 2, 4]], $queries() );

        // rendered nodes, e.g. embedded ones, are fetched alone
        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $data->sequence( [1, 2, 3, 4, 5] );
        $data->forget( 2 );
        $this->assertSame( 'Two', $data->fieldValues( 'node', 'body', 2, 'en' )->first()->body_value ?? null );
        $this->assertSame( [[0, 1, 3, 5], [0, 2, 4], [0, 2]], $queries() );

        $db->disableQueryLog();
    }


    public function testDataTrimsFetchedBatches(): void
    {
        foreach( ['field_media_image', 'field_media_document'] as $field )
        {
            Schema::connection( 'drupal' )->create( 'media__' . $field, function( Blueprint $table ) use ( $field ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->unsignedInteger( $field . '_target_id' );
            } );
        }

        $db = DB::connection( 'drupal' );
        $db->table( 'media__field_media_image' )->insert( ['entity_id' => 601, 'langcode' => 'en', 'field_media_image_target_id' => 7] );

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $ids = fn( string $table ) => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], "from \"{$table}\"" ) )
            ->map( fn( $query ) => count( $query['bindings'] ) - 1 )->values()->all();

        $db->enableQueryLog();

        $data->prefetchMedia( [], range( 1, 600 ) );
        $data->fieldValues( 'media', 'field_media_image', 1, 'en' );
        $this->assertSame( [500, 100], $ids( 'media__field_media_image' ) );

        // entities fetched from all field tables are removed from the batch, the others are fetched together
        $data->prefetchMedia( [], [601, 602] );
        $this->assertSame( 7, $data->fieldValues( 'media', 'field_media_image', 601, 'en' )->first()->field_media_image_target_id ?? null );
        $this->assertSame( [500, 100, 2], $ids( 'media__field_media_image' ) );

        // field tables used later don't fetch the values of the removed entities together
        $data->fieldValues( 'media', 'field_media_document', 5, 'en' );
        $this->assertSame( [1], $ids( 'media__field_media_document' ) );

        $data->fieldValues( 'media', 'field_media_document', 601, 'en' );
        $this->assertSame( [1, 2], $ids( 'media__field_media_document' ) );

        $db->disableQueryLog();
    }


    public function testDataSkipsBatchesOfRarelyUsedTables(): void
    {
        foreach( ['field_media_image', 'field_media_document'] as $field )
        {
            Schema::connection( 'drupal' )->create( 'media__' . $field, function( Blueprint $table ) use ( $field ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->unsignedInteger( $field . '_target_id' );
            } );
        }

        $db = DB::connection( 'drupal' );
        $data = new class( new Drupal8( $db, fn() => null ) ) extends DrupalData {
            protected const CACHE_SIZE = 4;
        };
        $ids = fn( string $table ) => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], "from \"{$table}\"" ) )
            ->map( fn( $query ) => count( $query['bindings'] ) - 1 )->values()->all();

        $db->enableQueryLog();

        $data->prefetchMedia( [], [1] );
        $data->fieldValues( 'media', 'field_media_document', 1, 'en' );
        $data->prefetchMedia( [], range( 2, 601 ) );
        $data->fieldValues( 'media', 'field_media_image', 2, 'en' );

        // rarely used field tables only fetch the last entities of the batch together
        $data->fieldValues( 'media', 'field_media_document', 601, 'en' );
        $this->assertSame( [1, 4], $ids( 'media__field_media_document' ) );

        $db->disableQueryLog();
    }


    public function testDataLimitsCachedRecords(): void
    {
        Schema::connection( 'drupal' )->create( 'media_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'mid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'media_field_data' )->insert( array_map( fn( $id ) => ['mid' => $id, 'langcode' => 'en', 'name' => "Media {$id}"], range( 1, 10 ) ) );
        $db->table( 'file_managed' )->insert( array_map( fn( $id ) => ['fid' => $id, 'filename' => "photo{$id}.jpg", 'filemime' => 'image/jpeg', 'uri' => "public://photo{$id}.jpg"], range( 1, 5 ) ) );
        $this->createBlockTables();
        $db->table( 'block_content__body' )->insert( array_map( fn( $id ) => ['entity_id' => $id, 'langcode' => 'en', 'body_value' => "<p>Block {$id}</p>"], range( 1, 5 ) ) );

        $data = new class( new Drupal8( $db, fn() => null ) ) extends DrupalData {
            protected const CACHE_SIZE = 4;
        };
        $queries = fn( string $table ) => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], "from \"{$table}\"" ) )->count();

        $db->enableQueryLog();

        // the oldest records are removed if there are too many, the last added ones are kept
        $data->prefetch( 'media_field_data', 'mid', [1, 2, 3] );
        $data->prefetch( 'media_field_data', 'mid', [4, 5] );
        $this->assertSame( 'Media 5', $data->entity( 'media_field_data', 'mid', 5 )->name ?? null );
        $this->assertSame( 'Media 4', $data->entity( 'media_field_data', 'mid', 4 )->name ?? null );
        $this->assertSame( 2, $queries( 'media_field_data' ) );
        $this->assertSame( 'Media 3', $data->entity( 'media_field_data', 'mid', 3 )->name ?? null );
        $this->assertSame( 3, $queries( 'media_field_data' ) );

        // all records of one prefetch are kept even if there are more than the cache size
        $data->prefetch( 'media_field_data', 'mid', [6, 7, 8, 9, 10] );
        $this->assertSame( 'Media 6', $data->entity( 'media_field_data', 'mid', 6 )->name ?? null );
        $this->assertSame( 4, $queries( 'media_field_data' ) );

        // only the used columns of the files are cached
        $data->prefetchFiles( [1, 2, 3] );
        $data->prefetchFiles( [4, 5] );
        $this->assertEquals( (object) ['fid' => 5, 'uri' => 'public://photo5.jpg', 'filename' => 'photo5.jpg', 'filemime' => 'image/jpeg'], $data->fileRow( 5 ) );
        $this->assertSame( 2, $queries( 'file_managed' ) );
        $this->assertSame( 'photo1.jpg', $data->fileRow( 1 )->filename ?? null );
        $this->assertSame( 3, $queries( 'file_managed' ) );

        // field values of blocks are shared by the nodes but limited too
        foreach( range( 1, 5 ) as $id ) {
            $this->assertSame( "<p>Block {$id}</p>", $data->fieldValues( 'block_content', 'body', $id, 'en' )->first()->body_value ?? null );
        }

        $this->assertSame( '<p>Block 5</p>', $data->fieldValues( 'block_content', 'body', 5, 'en' )->first()->body_value ?? null );
        $this->assertSame( 5, $queries( 'block_content__body' ) );
        $this->assertSame( '<p>Block 1</p>', $data->fieldValues( 'block_content', 'body', 1, 'en' )->first()->body_value ?? null );
        $this->assertSame( 6, $queries( 'block_content__body' ) );

        $db->disableQueryLog();
    }


    public function testDataReleasesParagraphsOfImportedNodes(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );

        foreach( ['node__field_content' => 'field_content', 'paragraph__field_items' => 'field_items'] as $name => $field )
        {
            $schema->create( $name, function( Blueprint $table ) use ( $field ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->unsignedInteger( $field . '_target_id' );
            } );
        }
        $schema->create( 'paragraphs_item_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'type' );
            $table->string( 'langcode' );
        } );
        $schema->create( 'paragraph__field_text', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'field_text_value' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node__field_content' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_content_target_id' => 1],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_content_target_id' => 2],
            ['entity_id' => 2, 'langcode' => 'en', 'delta' => 0, 'field_content_target_id' => 3],
        ] );
        $db->table( 'paragraph__field_items' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_items_target_id' => 4] );
        $db->table( 'paragraphs_item_field_data' )->insert( array_map( fn( $id ) => ['id' => $id, 'type' => 'text', 'langcode' => 'en'], [1, 2, 3, 4] ) );
        $db->table( 'paragraph__field_text' )->insert( array_map( fn( $id ) => ['entity_id' => $id, 'langcode' => 'en', 'field_text_value' => "Text {$id}"], [1, 2, 3, 4] ) );

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $data->sequence( [1, 2] );
        $text = fn( int $id ) => $data->fieldValues( 'paragraph', 'field_text', $id, 'en' )->first()->field_text_value ?? null;
        $queries = fn( string $table ) => $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], "from \"{$table}\"" ) )
            ->map( fn( $query ) => array_values( array_filter( $query['bindings'], fn( $value ) => is_int( $value ) && $value > 0 ) ) )->values()->all();

        $db->enableQueryLog();

        $data->fieldValues( 'node', 'field_content', 1, 'en' );
        $data->prefetchTargets( 'node__field_content', 'field_content', 'paragraph' );
        $data->prefetchTargets( 'node__field_content', 'field_content', 'paragraph' );

        // the paragraphs of all nodes fetched together are fetched at once
        $this->assertSame( 'Text 1', $text( 1 ) );
        $this->assertSame( [[1, 2, 3]], $queries( 'paragraph__field_text' ) );
        $this->assertSame( [[1, 2, 3]], $queries( 'paragraphs_item_field_data' ) );

        // nested paragraphs added later are fetched without the ones fetched before
        $data->fieldValues( 'paragraph', 'field_items', 1, 'en' );
        $data->prefetchTargets( 'paragraph__field_items', 'field_items', 'paragraph' );
        $this->assertSame( 'Text 4', $text( 4 ) );
        $this->assertSame( [[1, 2, 3], [4]], $queries( 'paragraph__field_text' ) );

        // the paragraphs of the imported node and their nested ones are removed, the others are kept
        $data->forget( 1 );
        $this->assertSame( 'Text 3', $text( 3 ) );
        $this->assertSame( 'Text 2', $text( 2 ) );
        $this->assertSame( 'Text 4', $text( 4 ) );
        $this->assertSame( [[1, 2, 3], [4], [2], [4]], $queries( 'paragraph__field_text' ) );
        $this->assertSame( 'text', $data->entity( 'paragraphs_item_field_data', 'id', 3 )->type ?? null );
        $this->assertSame( 'text', $data->entity( 'paragraphs_item_field_data', 'id', 1 )->type ?? null );
        $this->assertSame( [[1, 2, 3], [4], [1]], $queries( 'paragraphs_item_field_data' ) );

        $db->disableQueryLog();
    }


    public function testPruneWithManySavedRecords(): void
    {
        $data = fn( string $name ) => ['name' => $name, 'title' => $name, 'path' => strtolower( $name ), 'domain' => 'example.com', 'lang' => 'en', 'status' => 1, 'editor' => 'importer'];
        $saved = Pages::create( $data( 'Saved' ), [] );
        $stale = Pages::create( $data( 'Stale' ), [] );
        $element = fn( string $name ) => Element::forceCreate( ['lang' => 'en', 'type' => 'text', 'name' => $name, 'data' => ['type' => 'text', 'text' => 'Text'], 'editor' => 'importer'] );
        $used = $element( 'Used' );
        $unused = $element( 'Unused' );
        // more IDs than the database allows in one query
        $many = array_map( fn( $num ) => 'id-' . $num, range( 1, 40000 ) );

        $prune = new DrupalPrune( 'importer', fn() => null );
        $variants = $prune->stale( ['example.com'], array_fill_keys( array_map( DrupalPrune::key( ... ), [...$many, (string) $saved->variant_id] ), true ) );
        $elements = $prune->elements( ['example.com'], [...$many, (string) $used->id], false );

        $this->assertSame( [(string) $stale->variant_id], $variants->pluck( 'id' )->map( strval( ... ) )->all() );
        $this->assertSame( [(string) $unused->id], $elements->pluck( 'id' )->map( strval( ... ) )->all() );

        $trash = $prune->trashable( $variants, $elements, [], 0 );

        $this->assertSame( [(string) $stale->id], $trash['pages'] ?? null );
        $this->assertSame( [(string) $unused->id], $trash['elements'] ?? null );
    }


    public function testImportPruneTrashedPageFiles(): void
    {
        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( ['nid' => 4, 'type' => 'page', 'langcode' => 'en', 'title' => 'Gone', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/4', 'alias' => '/gone', 'langcode' => 'en'] );
        $db->table( 'node__body' )->insert( ['entity_id' => 4, 'langcode' => 'en', 'body_value' => '<p>Gone</p><img src="/sites/default/files/gone.jpg" alt="Gone" />'] );

        $args = ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'];

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );

        // the page was trashed by an earlier import which didn't trash its file
        $id = (string) PageView::where( 'name', 'Gone' )->firstOrFail()->id;
        Permission::system( fn() => PageResource::dropMany( [$id] ) );
        $db->table( 'node_field_data' )->where( 'nid', 4 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args + ['--prune' => true] ) );
        $output = Artisan::output();

        // trashed pages aren't reported as stale again
        $this->assertStringNotContainsString( '/gone [en]', $output );
        $this->assertStringContainsString( 'Moved 0 pages and translations, 0 shared elements and 1 files to the trash', $output );
        $this->assertFalse( File::where( 'path', 'https://example.com/sites/default/files/gone.jpg' )->exists() );
    }


    public function testImportBlocksSameTranslatedName(): void
    {
        $this->createBlockTables();

        $block = fn( string $uuid ) => serialize( [
            'status' => true, 'theme' => 'olivero', 'region' => 'footer', 'weight' => 0, 'plugin' => 'block_content:' . $uuid,
        ] );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => $block( 'uuid-1' )],
            ['name' => 'block.block.olivero_phone', 'data' => $block( 'uuid-3' )],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 7, 'uuid' => 'uuid-3', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1],
            ['id' => 5, 'langcode' => 'de', 'info' => 'Kontakt', 'default_langcode' => 0],
            ['id' => 7, 'langcode' => 'en', 'info' => 'Phone', 'default_langcode' => 1],
            ['id' => 7, 'langcode' => 'de', 'info' => 'Kontakt', 'default_langcode' => 0],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'],
            ['entity_id' => 5, 'langcode' => 'de', 'body_value' => '<p>Rufen Sie an</p>'],
            ['entity_id' => 7, 'langcode' => 'en', 'body_value' => '<p>Phone us</p>'],
            ['entity_id' => 7, 'langcode' => 'de', 'body_value' => '<p>Telefon</p>'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // translated descriptions are unique per language across all custom blocks
        $names = fn( string $lang ) => Element::where( 'lang', $lang )->orderBy( 'name' )->pluck( 'name' )->all();

        $this->assertSame( ['Contact', 'Phone'], $names( 'en' ) );
        $this->assertSame( ['Kontakt (5)', 'Kontakt (7)'], $names( 'de' ) );
        $this->assertStringContainsString( 'Telefon', (string) json_encode( Element::where( 'lang', 'de' )->where( 'name', 'Kontakt (7)' )->sole()->data ) );
    }


    public function testImportDomainAccessBlocksAndPaths(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node__field_domain_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( 'field_domain_access_target_id' );
        } );
        $schema->create( 'domain_path', function( Blueprint $table ) {
            $table->id();
            $table->string( 'domain_id' );
            $table->string( 'language' );
            $table->string( 'source' );
            $table->string( 'alias' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'domain.record.site_a', 'data' => serialize( ['id' => 'site_a', 'hostname' => 'a.example.com'] )],
            ['name' => 'domain.record.site_b', 'data' => serialize( ['id' => 'site_b', 'hostname' => 'b.example.com'] )],
            ['name' => 'block.block.olivero_b', 'data' => serialize( [
                'status' => true, 'theme' => 'olivero', 'region' => 'footer', 'weight' => 0, 'plugin' => 'block_content:uuid-1',
                'visibility' => ['domain' => ['id' => 'domain', 'domains' => ['site_b' => 'site_b']]],
            ] )],
        ] );
        $db->table( 'block_content' )->insert( ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'] );
        $db->table( 'block_content_field_data' )->insert( ['id' => 5, 'langcode' => 'en', 'info' => 'Site B', 'default_langcode' => 1] );
        $db->table( 'block_content__body' )->insert( ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Only on B</p>'] );
        $db->table( 'node_field_data' )->insert( ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Legal', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/node/3', 'alias' => '/legal', 'langcode' => 'en'] );
        $db->table( 'domain_path' )->insert( ['domain_id' => 'site_b', 'language' => 'en', 'source' => '/node/3', 'alias' => '/b-legal'] );
        $db->table( 'node__field_domain_access' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_domain_access_target_id' => 'site_a'],
            ['entity_id' => 3, 'langcode' => 'en', 'delta' => 0, 'field_domain_access_target_id' => 'site_a'],
            ['entity_id' => 3, 'langcode' => 'en', 'delta' => 1, 'field_domain_access_target_id' => 'site_b'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_a'] ) );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_b'] ) );

        // Domain Path aliases are used for their domain only
        $path = fn( string $domain ) => PageView::where( 'domain', $domain )->where( 'name', 'Legal' )->value( 'path' );

        $this->assertSame( 'legal', $path( 'a.example.com' ) );
        $this->assertSame( 'b-legal', $path( 'b.example.com' ) );

        // blocks of other domains aren't stale
        $this->assertTrue( Element::where( 'name', 'Site B' )->exists() );
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--drupal-domain' => 'site_a'] ) );
        $this->assertStringNotContainsString( 'Site B', Artisan::output() );
    }


    public function testImportDraftMetaTitle(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'vid' )->nullable() );
        $schema->create( 'node_field_revision', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'title' );
        } );
        foreach( ['node__field_meta_tags' => 'entity_id', 'node_revision__field_meta_tags' => 'revision_id'] as $name => $column )
        {
            $schema->create( $name, function( Blueprint $table ) use ( $column ) {
                $table->unsignedInteger( 'entity_id' );
                $column === 'revision_id' ? $table->unsignedInteger( 'revision_id' ) : null;
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->text( 'field_meta_tags_value' );
            } );
        }

        $tags = json_encode( ['title' => '[node:title] | Example', 'og_title' => '[node:title] | Example'] );
        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->where( 'nid', 1 )->update( ['vid' => 1] );
        $db->table( 'node_field_revision' )->insert( [
            ['nid' => 1, 'vid' => 1, 'langcode' => 'en', 'title' => 'About'],
            ['nid' => 1, 'vid' => 3, 'langcode' => 'en', 'title' => 'About draft'],
        ] );
        $db->table( 'node__field_meta_tags' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_meta_tags_value' => $tags] );
        $db->table( 'node_revision__field_meta_tags' )->insert( ['entity_id' => 1, 'revision_id' => 3, 'langcode' => 'en', 'field_meta_tags_value' => $tags] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // tokens of drafts use the title of the draft
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $version = $page->versions()->where( 'published', false )->sole();

        $this->assertSame( 'About | Example', $page->meta->{'social-media'}->data->title ?? null );
        $this->assertSame( 'About draft | Example', $version->aux->meta->{'social-media'}->data->title ?? null );
        $this->assertSame( 'About | Example', $page->title );
        $this->assertSame( 'About draft | Example', $version->data->title ?? null );
    }


    public function testImportSinceBlockSplit(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->table( 'block_content_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_contact', 'data' => serialize( ['status' => true, 'theme' => 'olivero', 'region' => 'footer', 'plugin' => 'block_content:uuid-1'] )],
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
        ] );
        $db->table( 'block_content' )->insert( ['id' => 5, 'uuid' => 'uuid-1', 'type' => 'basic', 'langcode' => 'en'] );
        $db->table( 'block_content_field_data' )->insert( ['id' => 5, 'langcode' => 'en', 'info' => 'Contact', 'default_langcode' => 1, 'changed' => strtotime( '2026-01-01' )] );
        $db->table( 'block_content__body' )->insert( ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Call us</p>'] );
        $db->table( 'node_field_data' )->update( ['changed' => strtotime( '2026-01-01' )] );

        $args = ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'];

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args ) );

        // the block now consists of several elements so the pages showing it are rendered again
        $db->table( 'block_content__body' )->update( ['body_value' => '<p>Call us</p><img src="/sites/default/files/map.jpg" alt="Map" />'] );
        $db->table( 'block_content_field_data' )->update( ['changed' => strtotime( '2026-03-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', $args + ['--since' => '2026-02-01'] ) );

        $ids = Element::where( 'lang', 'en' )->where( 'name', 'like', 'Contact %' )->orderBy( 'name' )->pluck( 'id' )->map( fn( $id ) => (string) $id )->all();
        $refs = array_column( (array) json_decode( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ), true ), 'refid' );

        $this->assertCount( 2, $ids );
        $this->assertSame( $ids, array_values( array_intersect( $refs, $ids ) ) );
        $this->assertStringContainsString( 'Updated: About (/about)', Artisan::output() );
    }


    public function testImportBookOutline(): void
    {
        Schema::connection( 'drupal' )->create( 'book', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->unsignedInteger( 'bid' );
            $table->unsignedInteger( 'pid' );
            $table->integer( 'weight' )->default( 0 );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'book', 'langcode' => 'en', 'title' => 'Manual', 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'book', 'langcode' => 'en', 'title' => 'Alpha', 'default_langcode' => 1],
            ['nid' => 4, 'type' => 'book', 'langcode' => 'en', 'title' => 'Zeta', 'default_langcode' => 1],
            ['nid' => 5, 'type' => 'book', 'langcode' => 'en', 'title' => 'Details', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/manual', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/alpha', 'langcode' => 'en'],
            ['path' => '/node/4', 'alias' => '/zeta', 'langcode' => 'en'],
            ['path' => '/node/5', 'alias' => '/details', 'langcode' => 'en'],
        ] );
        $db->table( 'book' )->insert( [
            ['nid' => 2, 'bid' => 2, 'pid' => 0, 'weight' => 0],
            ['nid' => 3, 'bid' => 2, 'pid' => 2, 'weight' => 5],
            ['nid' => 4, 'bid' => 2, 'pid' => 2, 'weight' => 0],
            ['nid' => 5, 'bid' => 2, 'pid' => 3, 'weight' => 0],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // pages are imported in the hierarchy and order of the book outline
        $page = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail();
        $manual = $page( 'Manual' );

        $this->assertSame( ['Zeta', 'Alpha'], PageView::where( 'parent_id', $manual->id )->orderBy( '_lft' )->pluck( 'name' )->all() );
        $this->assertSame( $page( 'Alpha' )->id, $page( 'Details' )->parent_id );
        $this->assertSame( 'details', $page( 'Details' )->path );
    }


    public function testImportTwitterCards(): void
    {
        $this->tables( 'node__field_meta_tags' );

        DB::connection( 'drupal' )->table( 'node__field_meta_tags' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_meta_tags_value' => json_encode( [
            'description' => 'All about us',
            'twitter_cards_title' => 'Meet us',
            'twitter_cards_description' => 'Our story',
            'twitter_cards_image' => 'https://example.com/sites/default/files/card.png',
        ] )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // Twitter card tags are used without Open Graph tags
        $social = PageView::where( 'name', 'About' )->firstOrFail()->meta->{'social-media'}->data ?? null;
        $file = File::where( 'path', 'https://example.com/sites/default/files/card.png' )->sole();

        $this->assertSame( ['Meet us', 'Our story', (string) $file->id], [$social->title ?? null, $social->description ?? null, $social->file->id ?? null] );
    }


    public function testImportSinceFieldBatches(): void
    {
        Schema::connection( 'drupal' )->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->update( ['changed' => strtotime( '2026-01-01' )] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'default_langcode' => 1, 'changed' => strtotime( '2026-01-01' )],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Jobs', 'default_langcode' => 1, 'changed' => strtotime( '2026-01-01' )],
            ['nid' => 4, 'type' => 'page', 'langcode' => 'en', 'title' => 'News', 'default_langcode' => 1, 'changed' => strtotime( '2026-01-01' )],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        $db->table( 'node_field_data' )->where( 'nid', 3 )->update( ['changed' => strtotime( '2026-03-01' )] );
        $db->table( 'node__body' )->insert( ['entity_id' => 3, 'langcode' => 'en', 'body_value' => '<p>Join us</p>'] );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );

        // only the field values of the changed nodes are fetched, not of the unchanged nodes after them
        $ids = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "node__body"' ) )
            ->flatMap( fn( $query ) => array_slice( $query['bindings'], 1 ) )->unique()->values()->all();

        $db->disableQueryLog();

        $this->assertSame( [3], $ids );
        $this->assertStringContainsString( 'Join us', (string) json_encode( PageView::where( 'name', 'Jobs' )->firstOrFail()->content ) );
    }


    public function testImportRemovesIframesNotAllowedByTextFormat(): void
    {
        $this->tables( 'config' );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'filter.format.basic_html', 'data' => serialize( ['filters' => [
                'filter_html' => ['status' => true, 'settings' => ['allowed_html' => '<p> <a href> <em>']],
            ]] )],
            ['name' => 'filter.format.video_html', 'data' => serialize( ['filters' => [
                'filter_html' => ['status' => true, 'settings' => ['allowed_html' => '<p> <iframe src width height title>']],
            ]] )],
            ['name' => 'filter.format.unfiltered_html', 'data' => serialize( ['filters' => [
                'filter_html' => ['status' => false, 'settings' => ['allowed_html' => '<p>']],
            ]] )],
        ] );
        $import = function( string $format ) {
            DB::connection( 'drupal' )->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( [
                'body_format' => $format,
                'body_value' => '<p>Before</p><IFRAME src="https://attacker.example/login" title="Login"></IFRAME><p>After</p>',
            ] );

            $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );
            return (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );
        };

        // Drupal removes the iframes of text formats which don't allow them, also without configuration
        foreach( ['basic_html', 'restricted_html'] as $format )
        {
            $content = $import( $format );
            $this->assertStringNotContainsString( 'iframe', $content, $format );
            $this->assertStringContainsString( 'Before', $content, $format );
            $this->assertStringContainsString( 'After', $content, $format );
        }

        foreach( ['video_html', 'unfiltered_html'] as $format ) {
            $this->assertStringContainsString( '<iframe src=\\"https:\/\/attacker.example\/login\\"', $import( $format ), $format );
        }
    }


    public function testImportEmbeds(): void
    {
        DB::connection( 'drupal' )->table( 'node__body' )->where( 'entity_id', 1 )->where( 'langcode', 'en' )->update( [
            'body_format' => 'full_html',
            'body_value' => '<p>Intro</p><p><iframe src="//www.youtube.com/embed/dQw4w9WgXcQ" width="560" height="315" title="Video" onload="alert(1)"></iframe></p>'
                . '<iframe src="https://vimeo.com/123"></iframe><iframe src="javascript:alert(1)"></iframe>'
                . '<video controls><source src="/sites/default/files/clip.mp4" type="video/mp4"></video>'
                . '<audio src="https://cdn.example.org/talk.mp3"></audio>'
                . '<script src="https://widgets.example.org/embed.js"></script><form action="/subscribe"><input name="mail"></form>',
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--file-base' => 'https://example.com/sites/default/files'] ) );
        $output = Artisan::output();

        $content = array_values( array_filter( (array) PageView::where( 'name', 'About' )->firstOrFail()->content, fn( $el ) => ( $el->type ?? '' ) !== 'reference' ) );
        $path = fn( int $idx ) => File::where( 'id', $content[$idx]->data->file->id ?? '' )->value( 'path' );

        // iframes with web URLs are created again without other attributes
        $this->assertSame( ['html', 'html', 'html', 'video', 'audio'], array_map( fn( $el ) => $el->type ?? null, $content ) );
        $this->assertSame( '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" title="Video" width="560" height="315" loading="lazy" allow="fullscreen; picture-in-picture" allowfullscreen></iframe>', $content[1]->data->text ?? null );
        // video pages can't be embedded and are replaced by their player URLs
        $this->assertStringStartsWith( '<iframe src="https://player.vimeo.com/video/123" ', $content[2]->data->text ?? '' );
        $this->assertSame( 'https://example.com/sites/default/files/clip.mp4', $path( 3 ) );
        $this->assertSame( 'https://cdn.example.org/talk.mp3', $path( 4 ) );
        $this->assertSame( ['video/mp4', 'audio/mpeg'], [File::where( 'path', $path( 3 ) )->value( 'mime' ), File::where( 'path', $path( 4 ) )->value( 'mime' )] );

        // removed markup is reported
        $this->assertStringContainsString( 'Removed unsupported HTML from [1] About [en]: <script>, <form>, <iframe>', $output );
        // the clean translations aren't reported
        $this->assertSame( 1, substr_count( $output, 'Removed unsupported HTML' ) );
    }


    public function testImportFieldFilesAndTags(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $fields = [
            'field_gallery' => ['target_id', 'alt', 'title'],
            'field_files' => ['target_id', 'display', 'description'],
            'field_tags' => ['target_id'],
            'field_video' => ['target_id'],
        ];
        foreach( $fields as $field => $columns )
        {
            $schema->create( 'node__' . $field, function( Blueprint $table ) use ( $field, $columns ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                foreach( $columns as $column ) {
                    $table->string( $field . '_' . $column )->default( '' );
                }
            } );
        }
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'media' );
        $schema->create( 'media_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'mid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'media__field_media_oembed_video' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_gallery', 'data' => serialize( ['type' => 'image'] )],
            ['name' => 'field.storage.node.field_files', 'data' => serialize( ['type' => 'file', 'settings' => ['display_field' => true]] )],
            ['name' => 'field.storage.node.field_tags', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'taxonomy_term']] )],
            ['name' => 'field.storage.node.field_video', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'media']] )],
            ['name' => 'field.storage.media.field_media_oembed_video', 'data' => serialize( ['type' => 'string'] )],
            ['name' => 'media.type.remote_video', 'data' => serialize( ['source' => 'oembed:video', 'source_configuration' => ['source_field' => 'field_media_oembed_video']] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 0], 'field_gallery' => ['weight' => 1], 'field_files' => ['weight' => 2],
                'field_video' => ['weight' => 3], 'field_tags' => ['weight' => 4, 'label' => 'hidden'],
            ]] )],
        ] );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'filename' => 'front.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://front.jpg'],
            ['fid' => 2, 'filename' => 'back.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://back.jpg'],
            ['fid' => 4, 'filename' => 'report_v3.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://report_v3.pdf'],
            ['fid' => 5, 'filename' => 'secret.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://secret.pdf'],
        ] );
        $db->table( 'node__field_gallery' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_gallery_target_id' => 1, 'field_gallery_alt' => 'Front', 'field_gallery_title' => ''],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_gallery_target_id' => 2, 'field_gallery_alt' => '', 'field_gallery_title' => 'Back side'],
        ] );
        $db->table( 'node__field_files' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_files_target_id' => 4, 'field_files_display' => '1', 'field_files_description' => 'Annual report'],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_files_target_id' => 5, 'field_files_display' => '0', 'field_files_description' => ''],
        ] );
        $db->table( 'taxonomy_term_field_data' )->insert( [
            ['tid' => 7, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'News', 'default_langcode' => 1],
            ['tid' => 9, 'vid' => 'category', 'langcode' => 'en', 'name' => 'Category', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/taxonomy/term/7', 'alias' => '/tags/news', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/9', 'alias' => '/category', 'langcode' => 'en'],
        ] );
        $db->table( 'node__field_tags' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_tags_target_id' => 7],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_tags_target_id' => 9],
        ] );
        $db->table( 'media' )->insert( ['mid' => 3, 'bundle' => 'remote_video', 'uuid' => 'uuid-3'] );
        $db->table( 'media_field_data' )->insert( [
            ['mid' => 3, 'langcode' => 'en', 'name' => 'Product tour', 'default_langcode' => 1],
            ['mid' => 3, 'langcode' => 'de', 'name' => 'Produkttour', 'default_langcode' => 0],
        ] );
        $db->table( 'media__field_media_oembed_video' )->insert( ['entity_id' => 3, 'langcode' => 'en', 'field_media_oembed_video_value' => 'https://example.org/videos/1'] );
        $db->table( 'node__field_video' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'field_video_target_id' => 3],
            ['entity_id' => 1, 'langcode' => 'de', 'field_video_target_id' => 3],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', [
            '--domain' => 'example.com', '--file-base' => 'https://example.com/files', '--terms' => 'tags',
        ] ) );

        $content = (array) PageView::where( 'name', 'About' )->firstOrFail()->content;
        $json = (string) json_encode( $content );
        $file = fn( string $id ) => File::where( 'id', $id )->firstOrFail();

        // several images of a field become a slideshow, the title is the name without alternative text
        $slideshows = array_values( array_filter( $content, fn( $item ) => ( $item->type ?? '' ) === 'slideshow' ) );
        $this->assertCount( 1, $slideshows );
        $this->assertSame( ['Front', 'Back side'], array_map( fn( $ref ) => $file( $ref->id )->name, $slideshows[0]->data->files ) );

        // hidden files are skipped, the description is the file name
        $files = array_values( array_filter( $content, fn( $item ) => ( $item->type ?? '' ) === 'file' ) );
        $this->assertCount( 1, $files );
        $this->assertSame( ['https://example.com/files/report_v3.pdf', 'Annual report'], [
            $file( $files[0]->data->file->id )->path, $file( $files[0]->data->file->id )->name,
        ] );
        $this->assertFalse( File::where( 'path', 'https://example.com/files/secret.pdf' )->exists() );

        // remote media are labeled with their name
        $this->assertStringContainsString( 'Product tour<\/a>', $json );
        $this->assertStringContainsString( 'Produkttour<\/a>', (string) json_encode( PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'About' )->firstOrFail()->id )->content ) );

        // terms with pages are linked, others are skipped
        $this->assertStringContainsString( '<a href=\"\/tags\/news\">News<\/a>', $json );
        $this->assertStringNotContainsString( 'Category', $json );
    }


    public function testImportLinkTargetsAndBlockReferences(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node__field_links', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->string( 'field_links_uri' );
            $table->string( 'field_links_title' )->default( '' );
        } );
        $schema->create( 'node__field_cta', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->unsignedInteger( 'field_cta_target_id' );
        } );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'media', 'media__field_media_document' );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_links', 'data' => serialize( ['type' => 'link'] )],
            ['name' => 'field.storage.node.field_cta', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'block_content']] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.media.field_media_document', 'data' => serialize( ['type' => 'file'] )],
            ['name' => 'media.type.document', 'data' => serialize( ['source' => 'file', 'source_configuration' => ['source_field' => 'field_media_document']] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => [
                'body' => ['weight' => 0], 'field_links' => ['weight' => 1], 'field_cta' => ['weight' => 2],
            ]] )],
        ] );
        $db->table( 'taxonomy_term_field_data' )->insert( ['tid' => 7, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'News', 'default_langcode' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/taxonomy/term/7', 'alias' => '/tags/news', 'langcode' => 'en'] );
        $db->table( 'node__body' )->where( 'langcode', 'en' )->update( [
            'body_value' => '<p>Read <a href="/taxonomy/term/7?page=2">news</a> or <a href="/taxonomy/term/99">gone</a></p>',
            'body_format' => 'full_html',
        ] );
        $db->table( 'media' )->insert( ['mid' => 3, 'bundle' => 'document', 'uuid' => 'uuid-3'] );
        $db->table( 'file_managed' )->insert( ['fid' => 4, 'filename' => 'prices.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://prices.pdf'] );
        $db->table( 'media__field_media_document' )->insert( ['entity_id' => 3, 'langcode' => 'en', 'field_media_document_target_id' => 4] );
        $db->table( 'node__field_links' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_links_uri' => 'entity:taxonomy_term/7', 'field_links_title' => 'News'],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_links_uri' => 'entity:media/3', 'field_links_title' => 'Prices'],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 2, 'field_links_uri' => 'entity:media/99', 'field_links_title' => 'Missing'],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-5', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 6, 'uuid' => 'uuid-6', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Call to action', 'default_langcode' => 1, 'status' => 1],
            ['id' => 6, 'langcode' => 'en', 'info' => 'Hidden', 'default_langcode' => 1, 'status' => 0],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Sign up today</p>'],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Unpublished block</p>'],
        ] );
        $db->table( 'node__field_cta' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 0, 'field_cta_target_id' => 5],
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 1, 'field_cta_target_id' => 6],
        ] );

        // the terms linked by the texts and link fields of all nodes are fetched at once
        $db->table( 'taxonomy_term_field_data' )->insert( [
            ['tid' => 8, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Events', 'default_langcode' => 1],
            ['tid' => 10, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Jobs', 'default_langcode' => 1],
            ['tid' => 11, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Press', 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/taxonomy/term/8', 'alias' => '/tags/events', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/10', 'alias' => '/tags/jobs', 'langcode' => 'en'],
            ['path' => '/taxonomy/term/11', 'alias' => '/tags/press', 'langcode' => 'en'],
        ] );
        $db->table( 'node_field_data' )->insert( ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Contact', 'default_langcode' => 1] );
        $db->table( 'node__body' )->insert( ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p><a href="/taxonomy/term/8">events</a></p>', 'body_format' => 'full_html'] );
        $db->table( 'node__field_links' )->insert( [
            ['entity_id' => 1, 'langcode' => 'en', 'delta' => 3, 'field_links_uri' => 'internal:/taxonomy/term/10', 'field_links_title' => 'Jobs'],
            ['entity_id' => 2, 'langcode' => 'en', 'delta' => 0, 'field_links_uri' => 'entity:taxonomy_term/11', 'field_links_title' => 'Press'],
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', [
            '--domain' => 'example.com', '--file-base' => 'https://example.com/files', '--terms' => 'tags',
        ] ) );
        $terms = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], 'from "taxonomy_term_field_data" where "tid" in' ) );
        $db->disableQueryLog();
        $this->assertSame( [[7, 99, 8], [10, 11]], $terms->pluck( 'bindings' )->values()->all() );
        $this->assertStringNotContainsString( 'entity_reference:block_content', Artisan::output() );
        $this->assertStringContainsString( '<a href=\"\/tags\/press\">Press<\/a>', (string) json_encode( PageView::where( 'name', 'Contact' )->firstOrFail()->content ) );

        $json = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $content = (array) $page->content;

        // links to terms point to the term pages, unavailable targets are skipped
        $this->assertStringContainsString( '<p><a href=\"\/tags\/news\">News<\/a><\/p>', $json );
        $this->assertStringNotContainsString( 'Missing', $json );
        $this->assertStringContainsString( '<a href=\"\/tags\/news?page=2\">news<\/a>', $json );
        $this->assertStringContainsString( '<a href=\"\/taxonomy\/term\/99\">gone<\/a>', $json );

        // links to media become elements with the managed files named by the link text
        $files = array_values( array_filter( $content, fn( $item ) => ( $item->type ?? '' ) === 'file' ) );
        $this->assertCount( 1, $files );
        $file = File::where( 'id', $files[0]->data->file->id )->firstOrFail();
        $this->assertSame( ['https://example.com/files/prices.pdf', 'Prices'], [$file->path, $file->name] );

        // published blocks referenced by fields are shared elements referenced by the page
        $refs = array_values( array_filter( $content, fn( $item ) => ( $item->type ?? '' ) === 'reference' ) );
        $this->assertCount( 1, $refs );
        $element = Element::where( 'id', $refs[0]->refid )->firstOrFail();
        $this->assertSame( ['Call to action', '<p>Sign up today</p>'], [$element->name, $element->data->text ?? null] );
        $this->assertSame( [$element->id], $page->latest?->elements()->pluck( 'id' )->all() );
        $this->assertStringNotContainsString( 'Unpublished block', $json );
        $this->assertFalse( Element::where( 'name', 'Hidden' )->exists() );

        // a second import reuses the shared element and keeps the page
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', [
            '--domain' => 'example.com', '--file-base' => 'https://example.com/files', '--terms' => 'tags',
        ] ) );
        $this->assertStringContainsString( 'Unchanged: About', Artisan::output() );
        $this->assertSame( 1, Element::where( 'name', 'Call to action' )->count() );
    }


    public function testImportNestedAndChangedBlockReferences(): void
    {
        $this->createBlockTables();

        $schema = Schema::connection( 'drupal' );
        $schema->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        $schema->table( 'block_content_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );
        foreach( ['node__field_cta', 'block_content__field_cta'] as $name )
        {
            $schema->create( $name, function( Blueprint $table ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->unsignedInteger( 'field_cta_target_id' );
            } );
        }

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( [
            ['name' => 'field.storage.node.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.node.field_cta', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'block_content']] )],
            ['name' => 'field.storage.block_content.body', 'data' => serialize( ['type' => 'text_with_summary'] )],
            ['name' => 'field.storage.block_content.field_cta', 'data' => serialize( ['type' => 'entity_reference', 'settings' => ['target_type' => 'block_content']] )],
            ['name' => 'core.entity_view_display.node.page.default', 'data' => serialize( ['content' => ['body' => ['weight' => 0], 'field_cta' => ['weight' => 1]]] )],
            ['name' => 'core.entity_view_display.block_content.basic.default', 'data' => serialize( ['content' => ['body' => ['weight' => 0], 'field_cta' => ['weight' => 1]]] )],
            ['name' => 'system.theme', 'data' => serialize( ['default' => 'olivero'] )],
            ['name' => 'block.block.olivero_footer', 'data' => serialize( [
                'status' => true, 'theme' => 'olivero', 'region' => 'content', 'weight' => 0, 'plugin' => 'block_content:uuid-5',
            ] )],
        ] );
        $db->table( 'block_content' )->insert( [
            ['id' => 5, 'uuid' => 'uuid-5', 'type' => 'basic', 'langcode' => 'en'],
            ['id' => 6, 'uuid' => 'uuid-6', 'type' => 'basic', 'langcode' => 'en'],
        ] );
        $db->table( 'block_content_field_data' )->insert( [
            ['id' => 5, 'langcode' => 'en', 'info' => 'Footer', 'default_langcode' => 1, 'status' => 1, 'changed' => strtotime( '2026-01-01' )],
            ['id' => 6, 'langcode' => 'en', 'info' => 'Offer', 'default_langcode' => 1, 'status' => 1, 'changed' => strtotime( '2026-01-01' )],
        ] );
        $db->table( 'block_content__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<p>Footer text</p>'],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Special offer</p>'],
        ] );
        $db->table( 'block_content__field_cta' )->insert( ['entity_id' => 5, 'langcode' => 'en', 'field_cta_target_id' => 6] );
        $db->table( 'node__field_cta' )->insert( ['entity_id' => 1, 'langcode' => 'en', 'field_cta_target_id' => 6] );
        $db->table( 'node_field_data' )->update( ['changed' => strtotime( '2026-01-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com'] ) );

        // blocks referenced within shared elements are added to their content
        $footer = Element::where( 'name', 'Footer 2' )->firstOrFail();
        $this->assertSame( ['html', '<p>Special offer</p>'], [$footer->type, $footer->data->text ?? null] );

        // pages reference the shared element of the block
        $offer = Element::where( 'name', 'Offer' )->firstOrFail();
        $this->assertContains( $offer->id, PageView::where( 'name', 'About' )->firstOrFail()->latest?->elements()->pluck( 'id' )->all() ?? [] );

        // pages referencing changed blocks are imported again with --since
        $db->table( 'block_content__body' )->where( 'entity_id', 6 )->update( ['body_value' => '<p>New offer</p>'] );
        $db->table( 'block_content_field_data' )->where( 'id', 6 )->update( ['changed' => strtotime( '2026-03-01' )] );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-02-01'] ) );
        $this->assertSame( '<p>New offer</p>', Element::where( 'id', $offer->id )->firstOrFail()->data->text ?? null );

        // shared elements of pages which aren't rendered again aren't reported as stale
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--since' => '2026-04-01'] ) );
        $this->assertStringNotContainsString( "shared elements of previous imports weren't updated", Artisan::output() );
    }


    public function testLayoutComponentsOrder(): void
    {
        $layout = new DrupalLayout( new DrupalData( new Drupal8( DB::connection( 'drupal' ), fn() => null ) ) );
        $components = $layout->components( [
            ['components' => [
                'a' => ['uuid' => 'a', 'region' => 'second', 'weight' => -5],
                'b' => ['uuid' => 'b', 'region' => 'first', 'weight' => 3],
                'c' => ['uuid' => 'c', 'region' => 'first', 'weight' => 1],
                'd' => ['uuid' => 'd', 'region' => 'custom', 'weight' => -9],
            ]],
            ['components' => ['e' => ['uuid' => 'e', 'region' => 'top']]],
        ] );

        $this->assertSame( ['c', 'b', 'a', 'd', 'e'], array_column( $components, 'uuid' ) );
    }


    public function testViewsAndPagesOfPreviousImports(): void
    {
        $view = fn( string $label, string $path, bool $status = true, array $options = [] ) => serialize( ['status' => $status, 'label' => $label, 'display' => [
            'default' => ['display_plugin' => 'default', 'display_options' => []],
            'page_1' => ['display_plugin' => 'page', 'display_options' => ['path' => $path] + $options],
        ]] );

        $this->tables( 'config' );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'views.view.news', 'data' => $view( 'News', '/news/' )],
            ['name' => 'views.view.events', 'data' => $view( 'Events', 'events' )],
            ['name' => 'views.view.off', 'data' => $view( 'Off', 'off', false )],
            ['name' => 'views.view.hidden', 'data' => $view( 'Hidden', 'hidden', true, ['enabled' => false] )],
            ['name' => 'views.view.term', 'data' => $view( 'Term', 'taxonomy/term/%' )],
            ['name' => 'views.view.admin', 'data' => $view( 'Admin', 'admin/content' )],
            ['name' => 'views.view.frontpage', 'data' => $view( 'Front', 'node' )],
        ] );

        $views = new DrupalViews( new DrupalData( new Drupal8( DB::connection( 'drupal' ), fn() => null ) ) );
        $news = ['id' => 'news', 'display' => 'page_1', 'label' => 'News', 'path' => 'news'];

        $this->assertSame( ['events', 'news'], array_keys( $views->all() ) );
        $this->assertSame( $news, $views->all()['news'] );
        $this->assertSame( $news, $views->find( 'route:view.news.page_1' ) );
        $this->assertSame( $news, $views->find( 'internal:/news?page=1' ) );
        $this->assertNull( $views->find( 'route:view.news.page_2' ) );
        $this->assertNull( $views->find( 'route:view.off.page_1' ) );
        $this->assertNull( $views->find( 'internal:/off' ) );
        $this->assertNull( $views->find( 'entity:node/1' ) );

        $page = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.com'], [] );
        $slashed = Pages::create( ['name' => 'Team', 'path' => '/team', 'domain' => 'example.com'], [] );
        $other = Pages::create( ['name' => 'About', 'path' => 'about', 'domain' => 'example.org'], [] );
        $slashed->page->delete();

        $pages = new DrupalPages( 'example.com' );

        $this->assertSame( $page->id, $pages->path( '/about/' )?->id );
        $this->assertSame( $other->id, $pages->path( 'about', 'example.org' )?->id );
        // trashed pages are returned but not restored
        $this->assertSame( $slashed->id, $pages->path( 'team' )?->id );
        $this->assertTrue( (bool) $pages->path( 'team' )?->trashed() );
        $this->assertNull( $pages->path( 'missing' ) );
        $this->assertNull( $pages->path( 'about', 'example.net' ) );
    }


    public function testLanguagesAndUrlsOfImportedNodes(): void
    {
        $this->tables( 'config' );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'system.site', 'data' => serialize( ['page' => ['front' => '/about-us']] )],
            ['name' => 'language.negotiation', 'data' => serialize( ['url' => ['source' => 'path_prefix', 'prefixes' => ['en' => '', 'de' => 'de/', 'fr' => 'fr']]] )],
        ] );
        DB::connection( 'drupal' )->table( 'path_alias' )->insert( [
            ['path' => '/node/1', 'alias' => '/about-us', 'langcode' => 'en'],
            ['path' => '/node/1', 'alias' => '/ueber', 'langcode' => 'de'],
        ] );

        $data = new DrupalData( $reader = new Drupal8( DB::connection( 'drupal' ), fn() => null ) );
        $languages = new DrupalLanguages( $data, ['fr' => ['lang' => 'fr', 'domain' => 'example.fr'], 'it' => null, 'es' => ['lang' => 'es', 'domain' => 'example.fr']] );

        $this->assertSame( ['example.fr'], $languages->domains() );
        $this->assertSame( 'de', $languages->language( 'de' )['lang'] ?? null );
        $this->assertSame( 'example.fr', $languages->language( 'fr' )['domain'] ?? null );
        $this->assertNull( $languages->language( 'it' ) );
        $this->assertNull( $languages->language( 'und' ) );
        $this->assertSame( 'de', $languages->prefix( 'de' ) );
        $this->assertSame( '', $languages->prefix( 'en' ) );
        // languages with their own domain don't get a prefix
        $this->assertSame( '', $languages->prefix( 'fr' ) );

        $nodes = $this->rows( [
            1 => (object) ['nid' => 1, 'title' => 'About Us', 'langcode' => 'en', 'type' => 'page'],
            2 => (object) ['nid' => 2, 'title' => 'Contact', 'langcode' => 'en', 'type' => 'page'],
        ] );
        $translations = [1 => ['de' => 'Über']];
        $front = null;
        $urls = new DrupalUrls( $reader, $data, $languages, 'en', $nodes, $translations, function() use ( &$front ): ?int {
            return $front;
        } );

        // the front page is found by its alias
        $this->assertSame( 1, $urls->frontNode() );
        $this->assertNull( $urls->front() );
        $this->assertSame( '/about-us', $urls->node( 1, 'en' ) );
        $this->assertSame( '/de/ueber', $urls->node( 1, 'de' ) );
        $this->assertSame( '/contact', $urls->node( 2, 'de' ) );
        $this->assertNull( $urls->node( 3, 'en' ) );

        // the front page imported into the root page uses the root URL
        $front = 1;

        $this->assertSame( 1, $urls->front() );
        $this->assertSame( '/', $urls->node( 1, 'en' ) );
        $this->assertSame( '/de', $urls->node( 1, 'de' ) );
        $this->assertSame( '/contact', $urls->node( 2, 'en' ) );
    }


    public function testDomainAccessHostnames(): void
    {
        $data = new DrupalData( new Drupal8( DB::connection( 'drupal' ), fn() => null ) );
        $domains = new DrupalDomains( $data );

        $this->assertFalse( $domains->used() );

        try {
            $domains->hostname( 'site_a' );
            $this->fail( 'Domains without Domain Access module must be rejected' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( "The --drupal-domain option requires the Domain Access module but its table \"node__field_domain_access\" doesn't exist.", $e->getMessage() );
        }

        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'node__field_domain_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
        } );
        DB::connection( 'drupal' )->table( 'config' )->insert( [
            ['name' => 'domain.record.site_a', 'data' => serialize( ['id' => 'site_a', 'hostname' => 'a.example.com'] )],
            ['name' => 'domain.record.site_b', 'data' => serialize( ['id' => 'site_b'] )],
        ] );

        $data = new DrupalData( new Drupal8( DB::connection( 'drupal' ), fn() => null ) );
        $domains = new DrupalDomains( $data );

        $this->assertTrue( $domains->used() );
        $this->assertSame( 'a.example.com', $domains->hostname( 'site_a' ) );
        $this->assertSame( '', $domains->hostname( 'site_b' ) );

        $this->expectExceptionMessage( 'Domain Access domain "site_c" not found.' );
        $domains->hostname( 'site_c' );
    }


    /**
     * Returns the URL paths of the pages like the ones created by the import.
     *
     * @param array<int, string> $paths URL paths by node ID
     * @return \Closure(int): ?string
     */
    private function paths( array $paths ): \Closure
    {
        return fn( int $nid ) => $paths[$nid] ?? null;
    }


    public function testPruneKey(): void
    {
        $bin = pack( 'H*', '0190a5b2c3d47e8f9a0b1c2d3e4f5a6b' );

        // UUIDs are packed into 16 bytes with or without dashes in any case
        $this->assertSame( 16, strlen( DrupalPrune::key( '0190a5b2-c3d4-7e8f-9a0b-1c2d3e4f5a6b' ) ) );
        $this->assertSame( $bin, DrupalPrune::key( '0190a5b2-c3d4-7e8f-9a0b-1c2d3e4f5a6b' ) );
        $this->assertSame( $bin, DrupalPrune::key( '0190A5B2-C3D4-7E8F-9A0B-1C2D3E4F5A6B' ) );
        $this->assertSame( $bin, DrupalPrune::key( '0190a5b2c3d47e8f9a0b1c2d3e4f5a6b' ) );

        // other IDs are prefixed and never collide with packed UUIDs
        $this->assertSame( '#42', DrupalPrune::key( '42' ) );
        $this->assertSame( '#0190a5b2-c3d4-7e8f-9a0b-1c2d3e4f5a6g', DrupalPrune::key( '0190a5b2-c3d4-7e8f-9a0b-1c2d3e4f5a6g' ) );
        $this->assertSame( '#0190a5b2c3d47e8f9a0b1c2d3e4f5a6', DrupalPrune::key( '0190a5b2c3d47e8f9a0b1c2d3e4f5a6' ) );
        $this->assertSame( '#' . $bin, DrupalPrune::key( $bin ) );

        // the saved variants are compared by their keys
        $data = fn( string $name ) => ['name' => $name, 'title' => $name, 'path' => strtolower( $name ), 'domain' => 'example.com', 'lang' => 'en', 'status' => 1, 'editor' => 'importer'];
        $saved = Pages::create( $data( 'Saved' ), [] );
        $stale = Pages::create( $data( 'Stale' ), [] );

        $variants = ( new DrupalPrune( 'importer', fn() => null ) )->stale( ['example.com'], [DrupalPrune::key( strtoupper( (string) $saved->variant_id ) ) => true] );
        $this->assertSame( [(string) $stale->variant_id], $variants->pluck( 'id' )->map( strval( ... ) )->all() );
    }


    public function testPrefetchEmbedsFetchesFilesAtOnce(): void
    {
        Schema::connection( 'drupal' )->table( 'file_managed', fn( Blueprint $table ) => $table->string( 'uuid' )->default( '' ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'file_managed' )->insert( [
            ['fid' => 1, 'uuid' => 'uuid-1', 'filename' => 'a.jpg', 'filemime' => 'image/jpeg', 'uri' => 'public://a.jpg', 'status' => 1],
            ['fid' => 2, 'uuid' => 'uuid-2', 'filename' => 'b.pdf', 'filemime' => 'application/pdf', 'uri' => 'public://b.pdf', 'status' => 1],
            ['fid' => 3, 'uuid' => 'uuid-3', 'filename' => 'c.png', 'filemime' => 'image/png', 'uri' => 'public://c.png', 'status' => 1],
            ['fid' => 4, 'uuid' => 'uuid-4', 'filename' => 'd.png', 'filemime' => 'image/png', 'uri' => 'public://d.png', 'status' => 0],
        ] );

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        $files = function() use ( $db ): Collection {
            $queries = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], ' from "file_managed" where "status" = ?' ) );
            $db->flushQueryLog();
            return $queries->values();
        };

        $db->enableQueryLog();
        $data->prefetchEmbeds( [], [], ['uuid-1', 'uuid-4'], [3], ['public://b.pdf', 'public://a.jpg'] );

        // the file records found by UUID, ID and URI are fetched by one query
        $queries = $files();
        $this->assertCount( 1, $queries );
        $this->assertEqualsCanonicalizing( [1, 1, 2, 3, 4], $queries->first()['bindings'] ?? [] );
        $this->assertEqualsCanonicalizing( ['public://a.jpg', 'public://b.pdf', 'public://c.png'], $data->fetchedUris() );

        // the files aren't fetched again while importing
        foreach( [1 => 'public://a.jpg', 2 => 'public://b.pdf', 3 => 'public://c.png'] as $fid => $uri ) {
            $this->assertSame( $uri, $data->fileRow( $fid )->uri ?? null );
        }

        $this->assertNull( $data->fileRow( 4 ) );
        $this->assertCount( 0, $files() );

        $db->disableQueryLog();
    }


    public function testForgetKeepsDraftsOfLaterNodes(): void
    {
        Schema::connection( 'drupal' )->create( 'node_revision__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'revision_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' )->default( 'basic_html' );
        } );

        $db = DB::connection( 'drupal' );

        foreach( range( 1, 4 ) as $nid ) {
            $db->table( 'node_revision__body' )->insert( ['entity_id' => $nid, 'revision_id' => 10 + $nid, 'langcode' => 'en', 'body_value' => "<p>Draft {$nid}</p>"] );
        }

        $data = new DrupalData( new Drupal8( $db, fn() => null ) );
        // returns the revision IDs fetched by each query to get the draft text
        $body = function( int $vid ) use ( $db, &$data ): array {
            $db->flushQueryLog();
            $this->assertSame( "<p>Draft " . ( $vid - 10 ) . "</p>", $data->fieldValues( 'node', 'body', $vid, 'en', false, true )->first()->body_value ?? null );

            return $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], ' from "node_revision__body" ' ) )
                ->map( fn( $query ) => array_values( array_filter( $query['bindings'], fn( $value ) => $value > 10 ) ) )->values()->all();
        };
        $init = function( array $sequence, array $drafts ) use ( $db, &$data ) {
            $data = new DrupalData( new Drupal8( $db, fn() => null ) );
            $data->sequence( $sequence );
            $data->drafts( $drafts );
        };
        $drafts = [1 => [11], 2 => [12], 3 => [13], 4 => [14]];

        $db->enableQueryLog();

        // drafts of later nodes are kept, the earlier and own ones are removed
        $init( [1, 2, 3, 4], $drafts );
        $this->assertSame( [[11, 12, 13, 14]], $body( 11 ) );
        $data->forget( 1 );
        $this->assertSame( [], $body( 12 ) );
        $this->assertSame( [], $body( 14 ) );
        $this->assertSame( [[11]], $body( 11 ) );

        // the kept drafts are ordered again after new rows were fetched
        $data->forget( 2 );
        $this->assertSame( [], $body( 13 ) );
        $this->assertSame( [[11]], $body( 11 ) );
        $this->assertSame( [[12]], $body( 12 ) );
        $data->forget( 3 );
        $this->assertSame( [], $body( 14 ) );
        $this->assertSame( [[13]], $body( 13 ) );

        // a new import order resets the kept drafts
        $init( [1, 2, 3], $drafts );
        $this->assertSame( [[11, 12, 13]], $body( 11 ) );
        $data->forget( 1 );
        $data->sequence( [1, 3, 2] );
        $data->forget( 3 );
        $this->assertSame( [], $body( 12 ) );
        $this->assertSame( [[13]], $body( 13 ) );

        // new drafts reset the kept drafts
        $init( [1, 2, 3], $drafts );
        $this->assertSame( [[11, 12, 13]], $body( 11 ) );
        $data->forget( 1 );
        $data->drafts( [1 => [11], 2 => [13], 3 => [12]] );
        $data->forget( 2 );
        $this->assertSame( [], $body( 12 ) );
        $this->assertSame( [[13]], $body( 13 ) );

        // nodes which aren't part of the import order remove all drafts
        $init( [1, 2], $drafts );
        $this->assertSame( [[11, 12]], $body( 11 ) );
        $data->forget( 1 );
        $data->forget( 99 );
        $this->assertSame( [[12]], $body( 12 ) );

        $db->disableQueryLog();
    }


    public function testDatesAreWrittenWithEachBatch(): void
    {
        Schema::connection( 'drupal' )->table( 'node_field_data', fn( Blueprint $table ) => $table->unsignedInteger( 'changed' )->default( 0 ) );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->update( ['created' => 1600000000, 'changed' => 1700000000] );
        // translations have their own dates
        $db->table( 'node_field_data' )->where( 'langcode', 'de' )->update( ['created' => 1610000000, 'changed' => 1710000000] );
        $db->table( 'node_field_data' )->insert( [
            ['nid' => 2, 'type' => 'page', 'langcode' => 'en', 'title' => 'Team', 'created' => 1620000000, 'changed' => 1720000000, 'default_langcode' => 1],
            ['nid' => 3, 'type' => 'page', 'langcode' => 'en', 'title' => 'Jobs', 'created' => 1630000000, 'changed' => 1730000000, 'default_langcode' => 1],
        ] );
        $db->table( 'path_alias' )->insert( [
            ['path' => '/node/2', 'alias' => '/team', 'langcode' => 'en'],
            ['path' => '/node/3', 'alias' => '/jobs', 'langcode' => 'en'],
        ] );

        $command = new class extends DrupalImport {
            protected int $batchSize = 1;
        };
        $command->setLaravel( app() );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $level = $cms->transactionLevel();
        $commits = [];
        // the variants of the imported nodes and the ones with their dates when a batch is committed
        Event::listen( TransactionCommitted::class, function( $event ) use ( $level, &$commits ) {
            if( $event->connection->transactionLevel() === $level ) {
                $variants = PageVariant::where( 'tag', '!=', 'root' );
                $commits[] = [$variants->count(), $variants->whereNotNull( 'created_at' )->where( 'created_at', '<', '2022-01-01' )->count()];
            }
        } );

        $cms->enableQueryLog();
        $this->assertSame( 0, $command->run( new ArrayInput( ['--domain' => 'example.com'] ), new BufferedOutput() ) );
        $updates = $this->queries( $cms )->pluck( 'query' )->filter( fn( $query ) => str_starts_with( $query, 'UPDATE "cms_page_variants" SET ' ) );
        $cms->disableQueryLog();

        // the dates are written before each batch is committed
        $this->assertGreaterThan( 2, count( $commits ) );

        foreach( $commits as [$count, $dated] ) {
            $this->assertSame( $count, $dated );
        }

        // one query per column for each batch
        $this->assertSame( $updates->filter( fn( $query ) => str_contains( $query, 'SET "created_at"' ) )->count(), $updates->filter( fn( $query ) => str_contains( $query, 'SET "updated_at"' ) )->count() );
        $this->assertSame( 3, $updates->filter( fn( $query ) => str_contains( $query, 'SET "created_at"' ) )->count() );

        $dates = fn( string $name, string $lang ) => [
            PageView::language( $lang )->where( 'name', $name )->firstOrFail()->created_at?->format( 'Y-m-d H:i:s' ),
            PageView::language( $lang )->where( 'name', $name )->firstOrFail()->updated_at?->format( 'Y-m-d H:i:s' ),
        ];

        $this->assertSame( ['2020-09-13 12:26:40', '2023-11-14 22:13:20'], $dates( 'About', 'en' ) );
        $this->assertSame( ['2021-01-07 06:13:20', '2024-03-09 16:00:00'], $dates( 'Über uns', 'de' ) );
        $this->assertSame( ['2021-05-03 00:00:00', '2024-07-03 09:46:40'], $dates( 'Team', 'en' ) );
        $this->assertSame( ['2021-08-26 17:46:40', '2024-10-27 03:33:20'], $dates( 'Jobs', 'en' ) );
    }


    public function testPruneUnusedFiles(): void
    {
        $data = fn( string $name ) => ['name' => $name, 'title' => $name, 'path' => strtolower( $name ), 'domain' => 'example.com', 'lang' => 'en', 'status' => 1, 'editor' => 'importer'];
        $kept = Pages::create( $data( 'Kept' ), [] );
        $stale = Pages::create( $data( 'Stale' ), [] );
        $file = fn( string $name ) => File::forceCreate( ['lang' => 'en', 'mime' => 'image/png', 'name' => $name, 'path' => $name . '.png', 'editor' => 'importer'] );
        $element = fn( string $name ) => Element::forceCreate( ['lang' => 'en', 'type' => 'text', 'name' => $name, 'data' => ['type' => 'text', 'text' => 'Text'], 'editor' => 'importer'] );
        $files = [];

        foreach( ['kept', 'stale', 'element', 'trashed', 'pending', 'published', 'unused'] as $name ) {
            $files[$name] = (string) $file( $name )->id;
        }

        $used = $element( 'Used' );
        $trashed = $element( 'Trashed' );
        $trashed->delete();
        $pending = $used->versions()->forceCreate( ['lang' => 'en', 'editor' => 'importer', 'data' => ['type' => 'text', 'text' => 'Text'], 'published' => false] );
        $published = $used->versions()->forceCreate( ['lang' => 'en', 'editor' => 'importer', 'data' => ['type' => 'text', 'text' => 'Text'], 'published' => true] );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->table( 'cms_page_file' )->insert( [
            ['variant_id' => $kept->variant_id, 'file_id' => $files['kept']],
            ['variant_id' => $stale->variant_id, 'file_id' => $files['stale']],
        ] );
        $db->table( 'cms_element_file' )->insert( [
            ['element_id' => $used->id, 'file_id' => $files['element']],
            ['element_id' => $trashed->id, 'file_id' => $files['trashed']],
        ] );
        $db->table( 'cms_version_file' )->insert( [
            ['version_id' => $pending->id, 'file_id' => $files['pending']],
            ['version_id' => $published->id, 'file_id' => $files['published']],
        ] );

        $prune = new DrupalPrune( 'importer', fn() => null );
        $variants = $prune->stale( ['example.com'], [DrupalPrune::key( (string) $kept->variant_id ) => true] );

        $db->enableQueryLog();
        $trash = $prune->trashable( $variants, Collection::make(), [], 0 );
        $queries = $this->queries( $db )->pluck( 'query' )->filter( fn( $query ) => preg_match( '/ from "cms_(page|element|version)_file" /', $query ) === 1 )->values();
        $db->disableQueryLog();

        // files used by kept variants, elements which aren't trashed and pending versions are kept
        $this->assertSame( [(string) $stale->id], $trash['pages'] ?? null );
        $this->assertEqualsCanonicalizing( [$files['stale'], $files['trashed'], $files['published'], $files['unused']], $trash['files'] ?? null );

        // the records using the files are looked up by correlated subqueries
        $this->assertCount( 3, $queries );

        foreach( $queries as $query )
        {
            $this->assertStringContainsString( ' and exists (select ', $query );
            $this->assertStringNotContainsString( ' in (select ', $query );
        }
    }


    public function testEmbeddingWithManyUuids(): void
    {
        $reader = new class( DB::connection( 'drupal' ), fn() => null ) extends Drupal8 {
            /**
             * @param array<int, string> $uuids
             * @return array<int, mixed>
             */
            public function embedded( array $uuids ): array
            {
                return $this->embedding( 'node__body', 'body', $uuids );
            }
        };

        $uuids = array_map( fn( $num ) => sprintf( '%08x-abcd-4ef0-8abc-%012x', $num, $num ), range( 1, 60 ) );
        $uuids[30] = strtoupper( $uuids[30] );

        DB::connection( 'drupal' )->table( 'node__body' )->insert( [
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => '<drupal-media data-entity-uuid="' . strtoupper( $uuids[55] ) . '"></drupal-media>'],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Text</p><drupal-media data-entity-uuid="' . $uuids[0] . '"></drupal-media>'],
            ['entity_id' => 7, 'langcode' => 'en', 'body_value' => '<drupal-media data-entity-uuid="' . sprintf( '%08x-abcd-4ef0-8abc-%012x', 99, 99 ) . '"></drupal-media>'],
            ['entity_id' => 8, 'langcode' => 'en', 'body_value' => '<drupal-media data-entity-uuid="' . strtolower( $uuids[30] ) . '"></drupal-media>'],
        ] );

        // more UUIDs than LIKE conditions are looked up in the words of the texts in any case
        $this->assertGreaterThan( 50, count( $uuids ) );
        $this->assertEqualsCanonicalizing( [5, 6, 8], $reader->embedded( $uuids ) );
        // like the LIKE conditions for less UUIDs
        $this->assertEqualsCanonicalizing( [5, 6], $reader->embedded( [$uuids[0], $uuids[55]] ) );
        $this->assertSame( [], $reader->embedded( [] ) );
    }


    public function testEmbeddingReadsTextsOfFewEntitiesAtOnce(): void
    {
        $reader = new class( DB::connection( 'drupal' ), fn() => null ) extends Drupal8 {
            protected const TEXT_SIZE = 2;

            /**
             * @param array<int, string> $uuids
             * @return array<int, mixed>
             */
            public function embedded( array $uuids ): array
            {
                return $this->embedding( 'node__body', 'body', $uuids );
            }
        };

        $uuids = array_map( fn( $num ) => sprintf( '%08x-abcd-4ef0-8abc-%012x', $num, $num ), range( 1, 60 ) );
        $media = fn( int $idx ) => '<drupal-media data-entity-uuid="' . $uuids[$idx] . '"></drupal-media>';

        $db = DB::connection( 'drupal' );
        $db->table( 'node__body' )->insert( [
            ['entity_id' => 2, 'langcode' => 'en', 'body_value' => '<p>Text</p>', 'deleted' => 0],
            ['entity_id' => 2, 'langcode' => 'de', 'body_value' => $media( 1 ), 'deleted' => 0],
            ['entity_id' => 3, 'langcode' => 'en', 'body_value' => $media( 2 ), 'deleted' => 0],
            ['entity_id' => 4, 'langcode' => 'en', 'body_value' => $media( 3 ), 'deleted' => 1],
            ['entity_id' => 5, 'langcode' => 'en', 'body_value' => $media( 4 ), 'deleted' => 0],
            ['entity_id' => 5, 'langcode' => 'de', 'body_value' => $media( 5 ), 'deleted' => 0],
            ['entity_id' => 6, 'langcode' => 'en', 'body_value' => '<p>Text</p>', 'deleted' => 0],
        ] );

        $db->flushQueryLog();
        $db->enableQueryLog();
        $ids = $reader->embedded( $uuids );
        $db->disableQueryLog();

        // texts of deleted fields are skipped and entities with several matching texts are returned once
        $this->assertSame( [2, 3, 5], $ids );

        // the texts of not more than two entities (1+2, 3+5, 6) are read at once, all texts of an entity together
        $queries = array_filter( $this->queries( $db )->pluck( 'query' )->all(), fn( $query ) => str_contains( $query, 'from "node__body"' ) );
        $texts = array_values( array_filter( $queries, fn( $query ) => str_contains( $query, '"body_value"' ) ) );

        $this->assertCount( 6, $queries );
        $this->assertCount( 3, $texts );

        foreach( $texts as $query ) {
            $this->assertStringContainsString( '"entity_id" in (', $query );
        }
    }


    public function testAccessFetchesGrantsByWindow(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'node_access', function( Blueprint $table ) {
            $table->unsignedInteger( 'nid' );
            $table->string( 'langcode' )->default( '' );
            $table->unsignedInteger( 'gid' );
            $table->string( 'realm' );
            $table->unsignedInteger( 'grant_view' )->default( 1 );
        } );
        $this->tables( 'config', 'taxonomy_index' );
        $schema->create( 'permissions_by_term_role', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'rid' );
        } );

        $db = DB::connection( 'drupal' );
        $db->table( 'config' )->insert( ['name' => 'user.role.administrator', 'data' => serialize( ['id' => 'administrator', 'is_admin' => true] )] );
        $db->table( 'permissions_by_term_role' )->insert( ['tid' => 7, 'rid' => 'gold'] );

        foreach( range( 1, 5 ) as $nid )
        {
            $db->table( 'node_access' )->insert( ['nid' => $nid, 'langcode' => 'en', 'gid' => 1, 'realm' => 'permissions_by_term'] );
            $db->table( 'taxonomy_index' )->insert( ['nid' => $nid, 'tid' => 7] );
        }

        Access::using( fn() => ['administrator', 'gold'] );

        $known = new class extends Access {
            /** @var array<int, array<int, mixed>> */
            public array $calls = [];

            #[\Override]
            public function known( iterable $values ): array
            {
                $this->calls[] = is_array( $values ) ? $values : iterator_to_array( $values );
                return parent::known( $values );
            }
        };
        $reader = new Drupal8( $db, fn() => null );
        // the grants of the node and the next one are fetched together
        $access = new DrupalAccess( $reader, new DrupalData( $reader ), $known, [], fn( int $nid ) => array_values( array_filter( [$nid, $nid + 1], fn( $id ) => $id <= 5 ) ),
            fn() => null, fn() => null, fn() => null );

        $db->enableQueryLog();

        foreach( [1, 2, 3, 4, 5, 1] as $nid ) {
            $this->assertSame( ['gold', 'administrator'], $access->node( (object) ['nid' => $nid, 'title' => "Node {$nid}", 'langcode' => 'en'] ) );
        }

        $log = $this->queries( $db );
        $db->disableQueryLog();

        // nodes inside the window don't fetch the grants and term roles again, nodes outside do
        $windows = [[1, 2], [3, 4], [5], [1, 2]];
        $grants = $log->filter( fn( $query ) => str_contains( $query['query'], ' from "node_access" where "nid" in' ) );
        $terms = $log->filter( fn( $query ) => str_contains( $query['query'], ' from "permissions_by_term_role" ' ) );

        $this->assertSame( $windows, $grants->map( fn( $query ) => array_slice( $query['bindings'], 0, -1 ) )->values()->all() );
        $this->assertSame( $windows, $terms->pluck( 'bindings' )->values()->all() );

        // the admin roles are only checked once
        $this->assertCount( 1, array_filter( $known->calls, fn( $values ) => in_array( 'administrator', $values, true ) ) );

        Access::using( null );
    }


    /**
     * @return array<string, array{int}>
     */
    public static function termCounts(): array
    {
        return ['few terms' => [2], 'more terms' => [6]];
    }


    public function testTermIndexOnlyOfTermPages(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );
        $db->table( 'taxonomy_term_field_data' )->insert( [
            ['tid' => 1, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Tag 1', 'default_langcode' => 1],
            ['tid' => 2, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Tag 2', 'default_langcode' => 1],
        ] );
        $db->table( 'taxonomy_index' )->insert( [['nid' => 99, 'tid' => 1], ['nid' => 1, 'tid' => 1], ['nid' => 1, 'tid' => 2]] );
        // only the first term has an URL alias and gets a page
        $db->table( 'path_alias' )->insert( ['path' => '/taxonomy/term/1', 'alias' => '/tags/tag-1', 'langcode' => 'en'] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $queries = $this->queries( $db )->filter( fn( $query ) => str_contains( $query['query'], ' from "taxonomy_index" where "tid" in' ) );
        $db->disableQueryLog();

        $this->assertStringContainsString( 'Term pages: 1 imported', Artisan::output() );
        $this->assertSame( '<ul><li><a href="/about">About</a></li></ul>', PageView::where( 'name', 'Tag 1' )->firstOrFail()->content[0]->data->text ?? null );

        // the tagged nodes are only fetched for the terms with page
        $this->assertSame( [[1]], $queries->pluck( 'bindings' )->values()->all() );
    }


    public function testReferencingNodesAreFetchedDistinct(): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );
        $schema->create( 'block_content_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->unsignedInteger( 'changed' )->default( 0 );
        } );
        $schema->create( 'node__field_blocks', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->unsignedInteger( 'field_blocks_target_id' );
        } );
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );
        $db->table( 'taxonomy_term_field_data' )->insert( [['tid' => 1, 'changed' => 3000], ['tid' => 2, 'changed' => 3000], ['tid' => 3, 'changed' => 1000]] );
        $db->table( 'taxonomy_index' )->insert( [['nid' => 1, 'tid' => 1], ['nid' => 1, 'tid' => 2], ['nid' => 2, 'tid' => 2], ['nid' => 2, 'tid' => 2], ['nid' => 4, 'tid' => 3]] );
        $db->table( 'block_content_field_data' )->insert( [['id' => 5, 'changed' => 3000], ['id' => 6, 'changed' => 3000]] );
        $db->table( 'node__field_blocks' )->insert( [
            ['entity_id' => 2, 'field_blocks_target_id' => 5], ['entity_id' => 3, 'field_blocks_target_id' => 5], ['entity_id' => 3, 'field_blocks_target_id' => 6],
        ] );

        $db->enableQueryLog();
        $nids = ( new Drupal8( $db, fn() => null ) )->referencing( 2000, ['node__field_blocks' => ['node', 'field_blocks', 'block_content']] );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        // the nodes referencing several changed terms or blocks are only fetched and returned once
        $this->assertEqualsCanonicalizing( [1, 2, 3], $nids );
        $this->assertCount( 3, $nids );
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_starts_with( $query, 'select distinct "nid" from "taxonomy_index"' ) ) );
        $this->assertCount( 1, $queries->filter( fn( $query ) => str_starts_with( $query, 'select distinct "entity_id" from "node__field_blocks"' ) ) );
    }


    #[DataProvider( 'termCounts' )]
    public function testTermPagesAreFetchedAtOnce( int $count ): void
    {
        $schema = Schema::connection( 'drupal' );
        $schema->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );

        foreach( range( 1, $count ) as $tid )
        {
            $db->table( 'taxonomy_term_field_data' )->insert( [
                ['tid' => $tid, 'vid' => 'tags', 'langcode' => 'en', 'name' => "Tag {$tid}", 'default_langcode' => 1],
                ['tid' => $tid, 'vid' => 'tags', 'langcode' => 'de', 'name' => "Schlagwort {$tid}", 'default_langcode' => 0],
            ] );
            $db->table( 'taxonomy_index' )->insert( ['nid' => 1, 'tid' => $tid] );
            $db->table( 'path_alias' )->insert( [
                ['path' => "/taxonomy/term/{$tid}", 'alias' => "/tags/tag-{$tid}", 'langcode' => 'en'],
                ['path' => "/taxonomy/term/{$tid}", 'alias' => "/schlagworte/tag-{$tid}", 'langcode' => 'de'],
            ] );
        }

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $this->assertStringContainsString( "Term pages: {$count} imported", Artisan::output() );

        // the existing term pages and their variants are fetched at once
        $cms->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $queries = $this->queries( $cms )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "cms_page_view" where ' ) );
        $cms->disableQueryLog();

        $this->assertStringContainsString( "Term pages: 0 imported, 0 updated, {$count} unchanged", Artisan::output() );
        // one query for the node pages and one for the term pages, the same for their variants
        $this->assertCount( 2, $queries->filter( fn( $query ) => str_contains( $query, '"path"' ) ) );
        $this->assertCount( 2, $queries->filter( fn( $query ) => str_contains( $query, '"cms_page_view"."id" in' ) ) );
        $this->assertCount( 5, $queries );
    }


    public function testVariantsOfUntranslatedPagesAreNotFetched(): void
    {
        Schema::connection( 'drupal' )->create( 'taxonomy_term_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'tid' );
            $table->string( 'vid' );
            $table->string( 'langcode' );
            $table->string( 'name' );
            $table->text( 'description__value' )->nullable();
            $table->string( 'description__format' )->nullable();
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $this->tables( 'taxonomy_index' );

        $db = DB::connection( 'drupal' );
        $db->table( 'node_field_data' )->where( 'default_langcode', 0 )->delete();
        $db->table( 'taxonomy_term_field_data' )->insert( ['tid' => 1, 'vid' => 'tags', 'langcode' => 'en', 'name' => 'Tag 1', 'default_langcode' => 1] );
        $db->table( 'taxonomy_index' )->insert( ['nid' => 1, 'tid' => 1] );
        $db->table( 'path_alias' )->insert( ['path' => '/taxonomy/term/1', 'alias' => '/tags/tag-1', 'langcode' => 'en'] );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );

        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );

        $cms->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:drupal-import', ['--domain' => 'example.com', '--terms' => 'tags'] ) );
        $queries = $this->queries( $cms )->pluck( 'query' )->filter( fn( $query ) => str_contains( $query, ' from "cms_page_view" where ' ) );
        $cms->disableQueryLog();

        $this->assertStringContainsString( 'Term pages: 0 imported, 0 updated, 1 unchanged', Artisan::output() );
        // the variants are only fetched for the pages of translated nodes and terms
        $this->assertCount( 0, $queries->filter( fn( $query ) => str_contains( $query, '"cms_page_view"."id" in' ) ) );
    }


    /**
     * Returns the rows like the ones read from the Drupal database.
     *
     * @param array<int|string, mixed> $rows Rows by key
     * @return Collection<int|string, mixed>
     */
    private function rows( array $rows ): Collection
    {
        return new Collection( $rows );
    }


    private function createBlockTables(): void
    {
        $schema = Schema::connection( 'drupal' );
        $this->tables( 'config' );
        $schema->create( 'block_content', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'uuid' );
            $table->string( 'type' );
            $table->string( 'langcode' );
        } );
        $schema->create( 'block_content_field_data', function( Blueprint $table ) {
            $table->unsignedInteger( 'id' );
            $table->string( 'langcode' );
            $table->string( 'info' );
            $table->boolean( 'status' )->default( true );
            $table->boolean( 'default_langcode' )->default( true );
        } );
        $schema->create( 'block_content__body', function( Blueprint $table ) {
            $table->unsignedInteger( 'entity_id' );
            $table->string( 'langcode' );
            $table->boolean( 'deleted' )->default( false );
            $table->unsignedInteger( 'delta' )->default( 0 );
            $table->text( 'body_value' );
            $table->string( 'body_format' )->default( 'basic_html' );
        } );
    }


    /**
     * Creates the Drupal tables used by several tests.
     */
    private function tables( string ...$names ): void
    {
        $columns = [
            'config' => function( Blueprint $table ) {
                $table->string( 'collection' )->default( '' );
                $table->string( 'name' );
                $table->binary( 'data' );
            },
            'key_value' => function( Blueprint $table ) {
                $table->string( 'collection' );
                $table->string( 'name' );
                $table->binary( 'value' );
            },
            'media' => function( Blueprint $table ) {
                $table->id( 'mid' );
                $table->string( 'bundle' );
                $table->string( 'uuid' );
            },
            'media__field_media_document' => function( Blueprint $table ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->unsignedInteger( 'field_media_document_target_id' );
            },
            'media__field_media_oembed_video' => function( Blueprint $table ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->string( 'field_media_oembed_video_value' );
            },
            'node' => function( Blueprint $table ) {
                $table->unsignedInteger( 'nid' );
                $table->string( 'uuid' );
                $table->string( 'type' );
                $table->string( 'langcode' );
            },
            'node__field_link' => function( Blueprint $table ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->string( 'field_link_uri' );
                $table->string( 'field_link_title' );
                $table->text( 'field_link_options' )->nullable();
            },
            'node__field_meta_tags' => function( Blueprint $table ) {
                $table->unsignedInteger( 'entity_id' );
                $table->string( 'langcode' );
                $table->boolean( 'deleted' )->default( false );
                $table->unsignedInteger( 'delta' )->default( 0 );
                $table->text( 'field_meta_tags_value' );
            },
            'taxonomy_index' => function( Blueprint $table ) {
                $table->unsignedInteger( 'nid' );
                $table->unsignedInteger( 'tid' );
                $table->boolean( 'sticky' )->default( false );
                $table->unsignedInteger( 'created' )->default( 0 );
            },
        ];

        foreach( $names as $name ) {
            Schema::connection( 'drupal' )->create( $name, $columns[$name] );
        }
    }
}
