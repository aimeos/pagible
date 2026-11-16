<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Import\Html;
use Aimeos\Cms\Concerns\Joomla\Remembers;
use Aimeos\Cms\Import\Joomla;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Tests of reading the Joomla database: reader, schema, site settings, languages, extensions, plugins, access and view levels.
 */
class JoomlaReaderTest extends JoomlaTestAbstract
{
    public function testTextsAreFetchedInChunksIncludingTranslations(): void
    {
        $chunks = fn( array $translations ) => array_map( fn( $chunk ) => $chunk->keys()->all(), iterator_to_array(
            ( new Joomla\Chunks( array_map( fn( $count ) => array_fill( 0, $count, 0 ), $translations ), 4 ) )->articles( array_map( fn( $count ) => 0, $translations ) ), false
        ) );

        // articles with more texts than the chunk size are fetched on their own
        $this->assertSame( [[1, 2], [3], [4], [5, 6, 7]], $chunks( [1 => 1, 2 => 1, 3 => 2, 4 => 5, 5 => 0, 6 => 0, 7 => 1] ) );
        $this->assertSame( [], $chunks( [] ) );

        // menu items count the texts of their articles, the ones without article have none
        $items = ['a' => 1, 'b' => null, 'c' => 2, 'd' => 3];
        $split = ( new Joomla\Chunks( [1 => [11, 12], 2 => [21]], 4 ) )->items( $items, fn( $id ) => $id );
        $this->assertSame( [['a' => 1, 'b' => null], ['c' => 2, 'd' => 3]], array_map( fn( $chunk ) => $chunk->all(), iterator_to_array( $split, false ) ) );

        // chunks of menu items without article are limited by the number of items
        $split = ( new Joomla\Chunks( [], 2 ) )->items( array_fill( 0, 5, null ), fn( $id ) => $id );
        $this->assertSame( [2, 2, 1], array_map( fn( $chunk ) => $chunk->count(), iterator_to_array( $split, false ) ) );
    }


    public function testReaderViewLevels(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->create( 'viewlevels', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'rules' );
        } );
        $schema->create( 'usergroups', function( Blueprint $table ) {
            $table->id();
            $table->unsignedInteger( 'parent_id' );
        } );

        $db = DB::connection( 'joomla' );
        $reader = $this->reader( $db );
        $this->assertSame( '1', $reader->levels()->title( 1 ) );
        // the level 1 is public in Joomla even if it's not in the database
        $this->assertTrue( $reader->levels()->isPublic( 1 ) );
        $this->assertFalse( $reader->levels()->isPublic( 3 ) );

        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1], ['id' => 9, 'parent_id' => 1]] );
        $db->table( 'viewlevels' )->insert( [
            ['id' => 1, 'title' => 'Public', 'rules' => '[]'],
            ['id' => 2, 'title' => 'Registered', 'rules' => '[6,2,8]'],
            ['id' => 3, 'title' => 'Everyone', 'rules' => '[1]'],
            ['id' => 5, 'title' => 'Guest', 'rules' => '[9]'],
            ['id' => 6, 'title' => 'Broken', 'rules' => 'invalid'],
        ] );

        // the guest group is 1 if the users component doesn't configure it
        $levels = ( $this->reader( $db ) )->levels();
        $this->assertSame( ['Public', 'Registered', 'Everyone', 'Guest', 'Broken'], array_map( fn( $id ) => $levels->title( $id ), [1, 2, 3, 5, 6] ) );
        $this->assertSame( [true, false, true, false, false], $this->publicLevels( $levels, [1, 2, 3, 5, 6] ) );
        $this->assertTrue( $levels->isPublic( 3 ) );
        $this->assertFalse( $levels->isPublic( 2 ) );
        $this->assertFalse( $levels->isPublic( 4 ) );
        $this->assertSame( 'Registered', $levels->title( 2 ) );
        $this->assertSame( '4', $levels->title( 4 ) );

        // the view levels are fetched only once
        $this->assertSame( '2', $reader->levels()->title( 2 ) );
        $this->assertFalse( $reader->levels()->isPublic( 3 ) );
    }


    public function testPlugins(): void
    {
        $db = DB::connection( 'joomla' );
        $plugins = $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins;

        // without extensions table, nothing is replaced and the default module style is used
        $this->assertSame( [false, false, false, false, false, false, false, false, null],
            [$plugins->contentFields(), $plugins->systemFields(), $plugins->loadModule(), $plugins->pageBreak(), $plugins->emailCloak(),
            $plugins->mailto(), $plugins->sef(), $plugins->strictRouting(), $plugins->redirectParams()] );
        $this->assertSame( Joomla\Shortcodes::NO_STYLE, $plugins->style() );

        $this->accessColumns();
        $db->table( 'extensions' )->insert( [
            ['type' => 'plugin', 'folder' => 'content', 'element' => 'fields', 'params' => '{}'],
            ['type' => 'plugin', 'folder' => 'content', 'element' => 'loadmodule', 'params' => '{"style":"xhtml"}'],
            ['type' => 'plugin', 'folder' => 'content', 'element' => 'emailcloak', 'params' => '{"mode":"1"}'],
            ['type' => 'plugin', 'folder' => 'system', 'element' => 'sef', 'params' => '{"strictrouting":"1"}'],
            ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{"collect_urls":"1"}'],
        ] );
        $plugins = $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins;

        // the content and system plugins of the same name are checked separately
        $this->assertSame( [true, false, true, false, true, true, true, true, ['collect_urls' => '1']],
            [$plugins->contentFields(), $plugins->systemFields(), $plugins->loadModule(), $plugins->pageBreak(), $plugins->emailCloak(),
            $plugins->mailto(), $plugins->sef(), $plugins->strictRouting(), $plugins->redirectParams()] );
        $this->assertSame( 'xhtml', $plugins->style() );

        // the e-mail cloaking mode 0 shows the addresses as text and the SEF plugin doesn't route strictly by default
        $db->table( 'extensions' )->where( 'element', 'emailcloak' )->update( ['params' => '{"mode":"0"}'] );
        $db->table( 'extensions' )->where( 'element', 'sef' )->update( ['params' => '{}'] );
        $db->table( 'extensions' )->where( 'element', 'loadmodule' )->update( ['params' => '{"style":1}'] );
        $plugins = $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins;

        $this->assertSame( [true, false, false, true], [$plugins->emailCloak(), $plugins->mailto(), $plugins->strictRouting(), $plugins->sef()] );
        $this->assertSame( Joomla\Shortcodes::NO_STYLE, $plugins->style() );
    }


    public function testSiteUpTo(): void
    {
        // without extensions table, the version is unknown
        $this->assertFalse( $this->reader()->extensions()->version()->upTo( 5 ) );
        $this->setJoomlaVersion( 4 );
        $extensions = $this->reader()->extensions();
        $this->assertTrue( $extensions->version()->upTo( 4 ) );
        $this->assertFalse( $extensions->version()->upTo( 3 ) );

        // sites of unknown versions are handled like the current Joomla versions
        Schema::connection( 'joomla' )->drop( 'extensions' );
        $categories = $this->reader()->categories();
        $site = function( ?int $version ) use ( $categories ) {
            $this->setJoomlaVersion( $version );
            return $this->site( $categories );
        };
        $this->assertFalse( $site( null )->version->upTo( 5 ) );
        $this->assertTrue( $site( 3 )->version->upTo( 3 ) );
        $this->assertTrue( $site( 3 )->version->upTo( 4 ) );
        $this->assertFalse( $site( 4 )->version->upTo( 3 ) );
        $this->assertTrue( $site( 4 )->version->upTo( 4 ) );
        $this->assertTrue( ( new Joomla\Version( 2 ) )->upTo( 3 ) );
        $this->assertFalse( ( new Joomla\Version() )->upTo( 3 ) );
    }


    public function testReaderMatchesModulesByNameWithoutExtensionClient(): void
    {
        $this->extensionsTable( ['client_id'] );
        Schema::connection( 'joomla' )->create( 'modules', function( Blueprint $table ) {
            $table->id();
            $table->string( 'module' );
            $table->string( 'position' );
            $table->integer( 'ordering' );
            $table->integer( 'published' )->default( 1 );
            $table->unsignedInteger( 'client_id' )->default( 0 );
        } );
        DB::connection( 'joomla' )->table( 'extensions' )->insert( [['type' => 'module', 'element' => 'mod_custom', 'enabled' => 1], ['type' => 'module', 'element' => 'mod_menu', 'enabled' => 0]] );
        DB::connection( 'joomla' )->table( 'modules' )->insert( [['id' => 1, 'module' => 'mod_custom', 'position' => 'a', 'ordering' => 1], ['id' => 2, 'module' => 'mod_menu', 'position' => 'a', 'ordering' => 2], ['id' => 3, 'module' => 'mod_other', 'position' => 'a', 'ordering' => 3]] );
        $this->assertSame( [1], $this->reader()->modules()->pluck( 'id' )->map( fn( $id ) => (int) $id )->all() );
        // without extensions table, the module types are unknown and all modules are used
        Schema::connection( 'joomla' )->rename( 'extensions', 'extensions_old' );
        $this->assertSame( [1, 2, 3], $this->reader()->modules()->pluck( 'id' )->map( fn( $id ) => (int) $id )->all() );
    }


    public function testSiteUsesOnlyTheMenuItemsForAllLanguagesInJoomla3WithLanguageFilter(): void
    {
        $categories = $this->categories( [] );
        $site = function( int $version, bool $filter = false ) use ( $categories ) {
            $this->setJoomlaVersion( $version );
            $this->languageFilter( $filter );
            return $this->site( $categories );
        };

        $this->assertTrue( $site( 3, true )->allLanguageItemsOnly() );
        $this->assertFalse( $site( 3 )->allLanguageItemsOnly() );
        $this->assertFalse( $site( 4, true )->allLanguageItemsOnly() );
    }


    public function testReaderImages(): void
    {

        $this->assertSame( ['image_intro' => 'images/intro.jpg'], Joomla\Article::images( (object) ['images' => '{"image_intro":"images/intro.jpg"}'] ) );
        $this->assertSame( [], Joomla\Article::images( (object) ['images' => 'invalid'] ) );
        $this->assertSame( [], Joomla\Article::images( (object) [] ) );
    }


    public function testReaderLanguagesAreOrderedLikeInJoomla(): void
    {
        // without position, the content languages are ordered by ID, so the first one gets the primary language code
        DB::connection( 'joomla' )->table( 'languages' )->where( 'lang_code', 'en-GB' )->update( ['lang_id' => 10] );

        $this->assertSame( ['de-DE', 'en-GB'], $this->reader()->languages()->pluck( 'lang_code' )->all() );

        // Joomla orders the content languages by their position first, only the used columns are fetched
        Schema::connection( 'joomla' )->table( 'languages', function( Blueprint $table ) {
            $table->integer( 'ordering' )->default( 0 );
            $table->string( 'image' )->default( 'flag' );
        } );
        DB::connection( 'joomla' )->table( 'languages' )->where( 'lang_code', 'de-DE' )->update( ['ordering' => 2] );
        DB::connection( 'joomla' )->table( 'languages' )->insert( ['lang_id' => 5, 'lang_code' => 'fr-FR', 'sef' => 'fr', 'ordering' => 1] );
        $languages = $this->reader()->languages();

        $this->assertSame( ['en-GB', 'fr-FR', 'de-DE'], $languages->pluck( 'lang_code' )->all() );
        $this->assertSame( ['lang_id', 'lang_code', 'sef', 'published', 'ordering'], array_keys( (array) $languages->first() ) );
    }


    public function testTextsKeepOnlyRecentPrefetchedTexts(): void
    {
        $rows = array_map( fn( $id ) => ['id' => $id, 'title' => "Article {$id}", 'alias' => "article-{$id}", 'introtext' => "<p>{$id}</p>", 'fulltext' => ''], range( 1001, 1300 ) );
        DB::connection( 'joomla' )->table( 'content' )->insert( $rows );

        $texts = new Joomla\Texts( $this->reader(), new Joomla\Origin() );
        $texts->prefetch( range( 1001, 1150 ) );
        $texts->prefetch( range( 1151, 1300 ) );

        $db = DB::connection( 'joomla' );
        $db->enableQueryLog();
        $this->assertSame( '<p>1300</p>', $texts->text( 1300 )->introtext );
        $this->assertCount( 0, $db->getQueryLog() );

        // the texts of former chunks are dropped and fetched again if they are used later
        $this->assertSame( '<p>1001</p>', $texts->text( 1001 )->introtext );
        $this->assertCount( 1, $db->getQueryLog() );
        $db->disableQueryLog();
    }


    public function testTextsFetchTextsPerArticle(): void
    {
        $reader = $this->reader();
        $texts = new Joomla\Texts( $reader, new Joomla\Origin() );
        $articles = collect( $reader->articles( [1, 3] ) );

        // the texts aren't fetched with the articles because they can be large
        $this->assertSame( 'About', $articles->get( 1 )->title ?? null );
        $this->assertFalse( property_exists( $articles->firstOrFail( 'id', 1 ), 'introtext' ) );
        $this->assertFalse( property_exists( $articles->firstOrFail( 'id', 1 ), 'fulltext' ) );
        $this->assertEqualsCanonicalizing( ['id', 'title', 'alias', 'catid', 'created', 'modified', 'featured', 'language', 'metadesc'], array_keys( get_object_vars( $articles->firstOrFail( 'id', 1 ) ) ) );
        $this->assertEquals( new Joomla\Text( '<p>Bonjour</p>' ), $texts->text( 3 ) );
        $this->assertEquals( new Joomla\Text(), $texts->text( 99 ) );

        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 2 )->update( ['images' => '{"image_intro":"images/teaser.jpg","image_fulltext":"images/intro.jpg"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['fulltext' => '<p><img alt="A" src="images/a.png"></p><img src="images/intro.jpg#joomlaImage://local-images/intro.jpg?width=8">'] );
        $db->enableQueryLog();
        // the texts are prefetched at once, the missing article 99 was already fetched by text() before
        $texts->prefetch( [2, 99, 1, 2] );
        // the paths of the images of the prefetched texts are returned without duplicates, the intro images aren't shown on the article pages
        $this->assertSame( ['images/intro.jpg', 'images/a.png'], $texts->imagePaths( [2, 99, 1, 2] ) );
        $this->assertSame( ['images/intro.jpg'], $texts->imagePaths( [2] ) );

        // the prefetched texts are kept when they are read
        $this->assertEquals( new Joomla\Text( '<h2>Willkommen</h2><p>Hallo</p>', images: ['image_intro' => 'images/teaser.jpg', 'image_fulltext' => 'images/intro.jpg'] ), $texts->text( 2 ) );
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $texts->text( 1 )->introtext );
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $texts->text( 1 )->introtext );
        $this->assertSame( 1, count( $db->getQueryLog() ) );
        $this->assertSame( 'select "id", "introtext", "fulltext", "images" from "content" where "id" in (?, ?)', $db->getQueryLog()[0]['query'] );

        // prefetching the next chunk keeps only its texts, the others are fetched again on use
        $texts->prefetch( [2] );
        $this->assertSame( 1, count( $db->getQueryLog() ) );
        $this->assertSame( '<h2>Welcome</h2><p>Hello</p>', $texts->text( 1 )->introtext );
        $this->assertSame( 2, count( $db->getQueryLog() ) );
        $db->disableQueryLog();

        // full text images which aren't paths are skipped
        $db->table( 'content' )->where( 'id', 3 )->update( ['images' => '{"image_fulltext":["images/list.jpg"]}'] );
        $texts->prefetch( [3] );
        $this->assertSame( [], $texts->imagePaths( [3] ) );
    }


    public function testReaderStripsJoomlaImageSuffix(): void
    {
        $images = '{"image_intro":"images/a.jpg#joomlaImage://local-images/a.jpg?width=10&height=20","image_intro_alt":"A#b",'
            . '"image_fulltext":"images/b.jpg#joomlaImage://local-images/b.jpg?width=30&height=40"}';
        $expected = ['image_intro' => 'images/a.jpg', 'image_intro_alt' => 'A#b', 'image_fulltext' => 'images/b.jpg'];

        $this->assertSame( $expected, Joomla\Article::images( (object) ['images' => $images] ) );
    }


    public function testPendingArticlesMustBePrefetched(): void
    {
        $chunk = new Joomla\ChunkRows( [1, 2, 5], [1 => (object) ['id' => 1], 5 => (object) ['id' => 5], 6 => (object) ['id' => 6]] );

        $this->assertSame( 1, $chunk->article( 1 )?->id );
        // prefetched articles which don't exist any more have no row
        $this->assertNull( $chunk->article( 2 ) );
        // fetched translations are available as rows but aren't pending articles of the chunk
        $this->assertSame( 6, $chunk->row( 6 )?->id );
        $this->assertSame( [5, 1], array_column( $chunk->pick( [5, 3, 1] ), 'id' ) );

        $this->expectException( \LogicException::class );
        $chunk->article( 3 );
    }


    public function testReaderRedirectsOfTheEnabledPluginWithRedirectStatus(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'redirect_links' )->insert( [
            ['id' => 1, 'old_url' => 'moved', 'new_url' => 'about', 'header' => 302],
            ['id' => 2, 'old_url' => 'gone', 'new_url' => 'about', 'header' => 410],
            ['id' => 3, 'old_url' => 'plain', 'new_url' => 'about', 'header' => 0],
            ['id' => 4, 'old_url' => 'first', 'new_url' => 'about', 'header' => 300],
            ['id' => 5, 'old_url' => 'last', 'new_url' => 'about', 'header' => 399],
            ['id' => 6, 'old_url' => 'error', 'new_url' => 'about', 'header' => 400],
            ['id' => 7, 'old_url' => 'success', 'new_url' => 'about', 'header' => 299],
        ] );
        // the reader caches the plugin options, so a new one is used after each change
        $reader = fn() => $this->reader( $db );
        $redirects = function() use ( $reader ) {
            $instance = $reader();
            $site = $this->site( $instance->categories(), reader: $instance );
            return Joomla\Redirects::all( $instance, $site->plugins );
        };

        // the reader returns all published redirects, Joomla only redirects if the "System - Redirect" plugin is enabled
        $db->table( 'redirect_links' )->insert( ['id' => 8, 'old_url' => 'draft', 'new_url' => 'about', 'header' => 301, 'published' => 0] );
        $this->assertSame( [1, 2, 3, 4, 5, 6, 7], $reader()->redirects()->pluck( 'id' )->all() );
        $this->assertSame( [], $redirects()->pluck( 'id' )->all() );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'content', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}', 'enabled' => 0] );
        $this->assertCount( 0, $redirects() );

        // in the basic mode, Joomla redirects with status 301
        $db->table( 'extensions' )->where( 'folder', 'system' )->update( ['enabled' => 1] );
        $this->assertSame( [1, 2, 3, 4, 5, 6, 7], $redirects()->pluck( 'id' )->all() );

        // in the advanced mode, Joomla shows an error page for other status codes than 300-399
        $db->table( 'extensions' )->insert( ['element' => 'com_redirect', 'params' => '{"mode":"1"}'] );
        $this->assertSame( [1, 3, 4, 5], $redirects()->pluck( 'id' )->all() );

        // URLs matching the excluded terms aren't redirected, plain terms are case sensitive, regular expressions not and invalid ones never match
        $db->table( 'redirect_links' )->where( 'id', 4 )->update( ['old_url' => 'https://example.com/first?x=%C3%BC#top'] );
        $db->table( 'redirect_links' )->where( 'id', 5 )->update( ['old_url' => 'last0'] );
        $excludes = fn( string $term ) => ['exclude_urls' => [
            'exclude_urls0' => ['term' => $term, 'regexp' => '0'],
            'exclude_urls1' => ['term' => '^\\/PL.*n$', 'regexp' => '1'],
            'exclude_urls2' => ['term' => '(', 'regexp' => '1'],
            'exclude_urls3' => ['term' => 'first?x=ü#top', 'regexp' => '0'],
            'exclude_urls4' => ['term' => '0', 'regexp' => '1'],
            'exclude_urls5' => 'broken',
        ]];
        $db->table( 'extensions' )->where( 'element', 'redirect' )->where( 'folder', 'system' )->update( ['params' => json_encode( $excludes( 'MOV' ) )] );
        // the invalid regular expression neither raises warnings nor replaces the error handler of the caller
        $errors = [];
        $handler = function( int $no, string $msg ) use ( &$errors ) {
            $errors[] = $msg;
            return true;
        };
        set_error_handler( $handler );

        try {
            $result = $redirects();
            trigger_error( 'after', E_USER_NOTICE );
        } finally {
            restore_error_handler();
        }

        $this->assertSame( ['after'], $errors );
        // the redirects are numbered from zero again
        $this->assertSame( [0 => 1, 1 => 5], $result->pluck( 'id' )->all() );
        $db->table( 'extensions' )->where( 'element', 'redirect' )->where( 'folder', 'system' )->update( ['params' => json_encode( $excludes( 'mov' ) )] );
        $this->assertSame( [5], $redirects()->pluck( 'id' )->all() );
    }


    public function testPrefetchReusesTheRowsOfTheHomeArticle(): void
    {
        $home = (object) ( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $article = (object) ['id' => 1, 'title' => 'About', 'alias' => 'about', 'language' => 'en-GB', 'catid' => 0];
        $translation = (object) ['id' => 2, 'title' => 'Über uns', 'alias' => 'ueber-uns', 'language' => 'de-DE', 'catid' => 0];
        $articles = new Joomla\Articles( categoryIds: [1 => 0, 4 => 0], translations: [1 => [2]], times: [], items: [], visible: [],
            routes: [Joomla\Kind::ARTICLE => [], Joomla\Kind::ARTICLE_TRANSLATION => []] );
        $run = $this->importRun( $this->site( $this->categories( [] ) ), articles: $articles,
            home: Joomla\HomeRoot::withArticle( $home, 1, [$translation], $article ) );

        $db = DB::connection( 'joomla' );
        $db->flushQueryLog();
        $db->enableQueryLog();

        try {
            // the home article and its translations are reused also if other articles are fetched in the same chunk
            $run->prefetch( [4, 1] );
            // the schema queries for the columns aren't counted
            $queries = array_values( array_filter( $db->getQueryLog(), fn( $query ) => $query['bindings'] !== [] ) );
        } finally {
            $db->disableQueryLog();
        }

        $this->assertSame( $article, $run->article( 1 ) );
        $this->assertSame( $translation, $run->row( 2 ) );
        $this->assertSame( 'Nur Deutsch', $run->article( 4 )->title ?? null );
        $this->assertCount( 1, $queries );
        $this->assertSame( [4], array_values( array_filter( $queries[0]['bindings'], 'is_int' ) ) );

        // only the home article is fetched without query
        $db->flushQueryLog();
        $db->enableQueryLog();

        try {
            $run->prefetch( [1] );
            $this->assertSame( [], array_filter( $db->getQueryLog(), fn( $query ) => $query['bindings'] !== [] ) );
        } finally {
            $db->disableQueryLog();
        }

        $this->assertSame( $article, $run->article( 1 ) );

        // IDs passed as strings, e.g. from array keys or database rows, are the same articles
        $db->flushQueryLog();
        $db->enableQueryLog();

        try {
            $run->prefetch( ['4', '1'] );
            $queries = array_values( array_filter( $db->getQueryLog(), fn( $query ) => $query['bindings'] !== [] ) );
        } finally {
            $db->disableQueryLog();
        }

        $this->assertSame( $article, $run->article( '1' ) );
        $this->assertCount( 1, $queries );
        $this->assertNotContains( 1, $queries[0]['bindings'] );
        $this->assertNotContains( '1', $queries[0]['bindings'] );
    }


    public function testReaderPluginsGuestsCanUse(): void
    {
        $db = DB::connection( 'joomla' );
        // without extensions table, the associations are used
        $this->assertTrue( $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->itemAssociations() );

        $schema = Schema::connection( 'joomla' );
        $this->extensionsTable();
        $schema->create( 'viewlevels', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'rules' );
        } );
        $db->table( 'viewlevels' )->insert( [['id' => 2, 'title' => 'Registered', 'rules' => '[2]'], ['id' => 3, 'title' => 'Guests', 'rules' => '[1]']] );

        // Joomla uses the associations only with the language filter
        $this->assertFalse( $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->itemAssociations() );

        $this->languageFilter();
        $this->assertTrue( $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->itemAssociations() );

        foreach( ['{"item_associations":"0"}' => false, '{"item_associations":0}' => false, '{"item_associations":"1"}' => true, '{"item_associations":""}' => true] as $params => $expected )
        {
            $db->table( 'extensions' )->update( ['params' => $params] );
            $this->assertSame( $expected, $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->itemAssociations(), $params );
        }

        // trashed plugins and plugins guests can't use aren't used
        foreach( [[-1, 1, false], [1, 1, true], [0, 2, false], [0, 3, true], [0, 4, false]] as [$state, $access, $expected] )
        {
            $db->table( 'extensions' )->update( ['state' => $state, 'access' => $access] );
            $this->assertSame( $expected, $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->languageFilter(), "state {$state}, access {$access}" );
        }
    }


    public function testReaderPluginsWithoutAccessLevelArePublic(): void
    {
        $db = DB::connection( 'joomla' );
        $this->extensionsTable();
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'languagefilter', 'params' => '{}', 'access' => null] );

        $this->assertEquals( new Joomla\Plugin( Joomla\Levels::PUBLIC_LEVEL, [] ), ( $this->reader( $db ) )->extensions()->plugin( 'system', 'languagefilter' ) );
        $this->assertTrue( $this->site( $this->categories( [] ), reader: $this->reader( $db ) )->plugins->languageFilter() );
    }


    public function testReaderTagsWithoutLanguageColumn(): void
    {
        $db = DB::connection( 'joomla' );
        $schema = Schema::connection( 'joomla' );
        $schema->create( 'tags', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->integer( 'published' );
            $table->unsignedInteger( 'access' );
        } );
        $schema->create( 'contentitem_tag_map', function( Blueprint $table ) {
            $table->string( 'type_alias' );
            $table->unsignedInteger( 'content_item_id' );
            $table->unsignedInteger( 'tag_id' );
        } );
        $db->table( 'tags' )->insert( ['id' => 2, 'title' => 'News', 'published' => 1, 'access' => 1] );
        $db->table( 'contentitem_tag_map' )->insert( ['type_alias' => Joomla\Reader::ARTICLE_TAGS, 'content_item_id' => 1, 'tag_id' => 2] );

        // tags of Joomla versions without the language column are fetched without it
        $tags = ( $this->reader( $db ) )->tags( [1], Joomla\Reader::ARTICLE_TAGS );
        $this->assertSame( [1 => [['content_item_id' => 1, 'title' => 'News', 'access' => 1]]], array_map( fn( $rows ) => array_map( fn( $row ) => (array) $row, $rows ), $tags ) );
    }


    public function testReaderGuestGroupOfTheUsersComponent(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->create( 'viewlevels', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'rules' );
        } );
        $schema->create( 'usergroups', function( Blueprint $table ) {
            $table->id();
            $table->unsignedInteger( 'parent_id' );
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_users', 'params' => '{"guest_usergroup":"13"}'] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 12, 'parent_id' => 1], ['id' => 13, 'parent_id' => 12], ['id' => 14, 'parent_id' => 13]] );
        $db->table( 'viewlevels' )->insert( [
            ['id' => 1, 'title' => 'Public', 'rules' => '[1]'],
            ['id' => 2, 'title' => 'Parent', 'rules' => '[12]'],
            ['id' => 3, 'title' => 'Guest', 'rules' => '[13]'],
            ['id' => 4, 'title' => 'Child', 'rules' => '[14]'],
        ] );

        // the guest group and its parents are public
        $this->assertSame( [true, true, true, false], $this->publicLevels( ( $this->reader( $db ) )->levels(), [1, 2, 3, 4] ) );

        // Joomla uses the public group if the guest group doesn't exist
        $db->table( 'extensions' )->where( 'element', 'com_users' )->update( ['params' => '{"guest_usergroup":"99"}'] );
        $db->table( 'viewlevels' )->insert( ['id' => 5, 'title' => 'Root', 'rules' => '[1]'] );
        $this->assertSame( [true, false, false, false, true], $this->publicLevels( ( $this->reader( $db ) )->levels(), [1, 2, 3, 4, 5] ) );
    }


    public function testLevelsOfGuestGroups(): void
    {
        $rows = [(object) ['id' => 2, 'title' => 'Guest', 'rules' => '[13]'], (object) ['id' => 3, 'title' => 'Parent', 'rules' => '["12"]']];

        // without user groups, the configured guest group is used even if it doesn't exist
        $this->assertSame( [true, false], $this->publicLevels( Joomla\Levels::read( $rows, null, 13 ), [2, 3] ) );
        // the guest group is 1 if it's not configured
        $this->assertSame( [false, false], $this->publicLevels( Joomla\Levels::read( $rows ), [2, 3] ) );
        $this->assertSame( [true], $this->publicLevels( Joomla\Levels::read( [(object) ['id' => 4, 'title' => 'Root', 'rules' => '[1]']], [1 => 0, 13 => 1] ), [4] ) );
        // groups which are their own parents don't loop
        $this->assertSame( [true, true], $this->publicLevels( Joomla\Levels::read( $rows, [13 => 12, 12 => 12], 13 ), [2, 3] ) );
        // Joomla doesn't use a missing guest group itself but the public root group, so only the levels of group 1 are public
        $missing = [...$rows, (object) ['id' => 4, 'title' => 'Root', 'rules' => '[1]']];
        $this->assertSame( [false, false, true], $this->publicLevels( Joomla\Levels::read( $missing, [1 => 0, 12 => 1], 13 ), [2, 3, 4] ) );
        $this->assertSame( [false, false, true], $this->publicLevels( Joomla\Levels::read( $missing, [1 => 0, 12 => 1], 0 ), [2, 3, 4] ) );
        $this->assertTrue( ( new Joomla\Levels() )->isPublic( Joomla\Levels::PUBLIC_LEVEL ) );
    }


    public function testPageHeadingOptionOfTheArticlesComponentOverridesTheMenusComponent(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_menus', 'params' => '{"show_page_heading":"1"}'] );
        $reader = $this->reader( $db );

        $this->assertSame( '1', Joomla\Site::read( $reader, [], 'en' )->defaults['show_page_heading'] ?? null );

        // unset and empty options of the menus component aren't used
        foreach( ['{}', '{"show_page_heading":""}'] as $params )
        {
            $db->table( 'extensions' )->where( 'element', 'com_menus' )->update( ['params' => $params] );
            $this->assertArrayNotHasKey( 'show_page_heading', Joomla\Site::read( $this->reader( $db ), [], 'en' )->defaults );
        }

        $db->table( 'extensions' )->where( 'element', 'com_content' )->delete();
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_page_heading":"0","show_title":""}'] );
        $defaults = Joomla\Site::read( $this->reader( $db ), [], 'en' )->defaults;

        $this->assertSame( '0', $defaults['show_page_heading'] ?? null );
        $this->assertArrayNotHasKey( 'show_title', $defaults );
    }


    public function testAccessChecksCategoriesAndDates(): void
    {
        $categories = $this->categories( [
            2 => (object) ['id' => 2, 'title' => 'News', 'parent_id' => 3, 'published' => 1],
            3 => (object) ['id' => 3, 'title' => 'Old', 'parent_id' => 4, 'published' => 2, 'access' => 5],
            4 => (object) ['id' => 4, 'title' => 'Hidden', 'parent_id' => 4, 'published' => 0],
            5 => (object) ['id' => 5, 'title' => 'Sub', 'parent_id' => 6, 'published' => 1],
            6 => (object) ['id' => 6, 'title' => 'Members', 'parent_id' => 0, 'published' => 1, 'access' => 5],
        ] );
        $access = new Joomla\Access( $this->reader()->levels(), $categories );

        // only published categories guests can see are visible, their parents aren't checked
        $this->assertTrue( $access->categoryPublic( (object) ['id' => 5, 'parent_id' => 6, 'published' => 1] ) );
        $this->assertTrue( $access->categoryPublic( (object) ['id' => 7, 'access' => 1] ) );
        $this->assertFalse( $access->categoryPublic( (object) ['id' => 3, 'published' => 2] ) );
        $this->assertFalse( $access->categoryPublic( (object) ['id' => 4, 'published' => 0] ) );
        $this->assertFalse( $access->categoryPublic( (object) ['id' => 6, 'published' => 1, 'access' => 5] ) );

        // categories which are their own parents don't loop, unknown categories are visible
        $this->assertTrue( $access->categoryHidden( 4 ) );
        $this->assertTrue( $access->categoryHidden( 2 ) );
        $this->assertTrue( $access->categoryHidden( 3 ) );
        $this->assertFalse( $access->categoryHidden( 99 ) );

        // articles with own menu item only check their category itself
        $this->assertFalse( $access->articleHidden( 2, true ) );
        $this->assertTrue( $access->articleHidden( 2, false ) );
        $this->assertTrue( $access->articleHidden( 4, true ) );

        // menu items to categories of archived parent categories aren't shown
        $this->assertSame( 'the category Old is archived', $access->categoryItemRestriction( (object) [], 2 ) );
        // the access level of the category itself is checked first
        $this->assertSame( 'its category Old is restricted to Joomla access level 5', $access->categoryItemRestriction( (object) [], 3 ) );
        $this->assertNull( $access->categoryItemRestriction( (object) [], 4 ) );

        // view levels which aren't known are restricted and use their ID as title
        $this->assertNull( $access->restriction( (object) ['access' => 1] ) );
        $this->assertNull( $access->restriction( (object) [] ) );
        $this->assertSame( "it's restricted to Joomla access level 5", $access->restriction( (object) ['access' => '5'] ) );
        $restricted = 'its category Members is restricted to Joomla access level 5';
        $own = "it's restricted to Joomla access level 5";

        // articles with own menu item only check their category itself, the article and the menu item showing it before
        $this->assertSame( $restricted, $access->articleRestriction( (object) ['catid' => 5], false ) );
        $this->assertNull( $access->articleRestriction( (object) ['catid' => 5], true ) );
        $this->assertSame( $restricted, $access->articleRestriction( (object) ['catid' => 6], true ) );
        $this->assertSame( 'its category Old is restricted to Joomla access level 5', $access->articleRestriction( (object) ['catid' => 2], false ) );
        $this->assertNull( $access->articleRestriction( (object) ['catid' => 4], false ) );
        $this->assertSame( $own, $access->articleRestriction( (object) ['catid' => 6, 'access' => 5], true ) );
        $this->assertSame( $own, $access->articleRestriction( (object) ['catid' => 6], true, (object) ['access' => 5] ) );
        $this->assertNull( $access->articleRestriction( (object) ['catid' => 5], true, (object) [] ) );

        // like Joomla, categories check themselves, their archived parents and the access levels of their parents
        $this->assertSame( $restricted, $access->categoryRestriction( (object) ['id' => 5, 'parent_id' => 6] ) );
        $this->assertSame( $own, $access->categoryRestriction( (object) ['id' => 6, 'parent_id' => 0, 'access' => 5] ) );
        $this->assertSame( 'the category Old is archived', $access->categoryRestriction( (object) ['id' => 2, 'parent_id' => 3] ) );
        $this->assertNull( $access->categoryRestriction( (object) ['id' => 7, 'parent_id' => 0] ) );

        // menu items of categories check the access level of the category itself before its archived parents
        $this->assertSame( $own, $access->categoryItemRestriction( (object) ['access' => 5], 6 ) );
        $this->assertSame( $restricted, $access->categoryItemRestriction( (object) [], 6 ) );
        // like Joomla, categories with menu item below restricted parents are restricted like the parent
        $this->assertSame( $restricted, $access->categoryItemRestriction( (object) [], 5 ) );
        $this->assertSame( $restricted, $access->itemCategoryRestriction( 5 ) );
        $this->assertNull( $access->itemCategoryRestriction( 99 ) );

        // the archived states are fetched once per category like the other restrictions
        $categories->get( 3 )->published = 1;
        $this->assertSame( 'the category Old is archived', $access->categoryItemRestriction( (object) [], 2 ) );
        $this->assertSame( 'its category Old is restricted to Joomla access level 5', $access->categoryItemRestriction( (object) [], 3 ) );
        $this->assertSame( 'the category Old is archived', $access->categoryItemRestriction( (object) [], 2 ) );
        $this->assertNull( $access->categoryItemRestriction( (object) [], null ) );
        $this->assertSame( "it's restricted to Joomla access level 5", $access->restriction( (object) ['access' => 5] ) );

        $this->assertNull( $access->dateRestriction( (object) ['publish_up' => '0000-00-00 00:00:00', 'publish_down' => '1970-01-01 00:00:00'] ) );
        $this->assertSame( 'it expired on 2020-01-02 03:04', $access->dateRestriction( (object) ['publish_down' => '2020-01-02 03:04:05'] ) );
        // the dates are stored and shown in UTC independent of the PHP timezone
        $timezone = date_default_timezone_get();
        date_default_timezone_set( 'Europe/Berlin' );
        try {
            $this->assertSame( 'it expired on 2020-01-02 03:04', $access->dateRestriction( (object) ['publish_down' => '2020-01-02 03:04:05'] ) );
        } finally {
            date_default_timezone_set( $timezone );
        }
        $this->assertSame( "it's scheduled for 2999-01-02 03:04, enable it then", $access->restriction( (object) ['publish_up' => '2999-01-02 03:04:05'] ) );

        // the publishing dates are compared with the same time for the whole run
        $fixed = new Joomla\Access( new Joomla\Levels(), collect(), 1577934245 );
        $this->assertSame( 1577934245, $fixed->now );
        $this->assertNull( $fixed->dateRestriction( (object) ['publish_up' => '2020-01-02 03:04:05', 'publish_down' => '2020-01-02 03:04:05'] ) );
        $this->assertSame( "it's scheduled for 2020-01-02 03:04, enable it then", $fixed->dateRestriction( (object) ['publish_up' => '2020-01-02 03:04:06'] ) );
        $this->assertSame( 'it expired on 2020-01-02 03:04', $fixed->dateRestriction( (object) ['publish_down' => '2020-01-02 03:04:04'] ) );
        $this->assertLessThanOrEqual( now()->getTimestamp(), $access->now );
    }


    public function testDateTimesAreUtc(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set( 'Europe/Berlin' );

        try
        {
            // Joomla stores the dates in UTC, so the server time zone isn't used
            $this->assertSame( 1577934245, Joomla\Date::time( null, '0000-00-00 00:00:00', '2020-01-02 03:04:05' ) );
            $this->assertNull( Joomla\Date::time( '', null ) );
        }
        finally
        {
            date_default_timezone_set( $timezone );
        }
    }


    public function testAccessRestricted(): void
    {
        $access = new Joomla\Access( new Joomla\Levels( [2 => ['title' => 'Registered', 'public' => false], 3 => ['title' => 'Guests', 'public' => true]] ), collect() );

        $this->assertSame( [false, false, false, true, false, true], array_map( fn( $level ) => $access->restricted( $level ), [null, 'x', 1, '2', 3, 9] ) );
        // access level 0 of languages is public
        $this->assertSame( [true, true, true, false], array_map( fn( $level ) => $access->languagePublic( (object) ['access' => $level] ), [0, '0', 3, 2] ) );
        $this->assertTrue( $access->languagePublic( (object) [] ) );
    }


    public function testValues(): void
    {
        $this->assertSame( '2020-01-02 03:04:05', Joomla\Date::first( null, '0000-00-00 00:00:00', '1970-01-01 00:00:00', '', '2020-01-02 03:04:05' ) );
        $this->assertNull( Joomla\Date::first( '1900-01-01 00:00:00' ) );
        $this->assertNull( Joomla\Date::first( '0000-00-00 00:00:00.000' ) );
        $this->assertNull( Joomla\Date::first( '0000-00-00' ) );
        $this->assertSame( ['a' => '0', 'c' => false], Joomla\Value::filled( ['a' => '0', 'b' => '', 'c' => false, 'd' => null] ) );
        $this->assertSame( 1, Joomla\Row::state( (object) [] ) );
        $this->assertSame( 2, Joomla\Row::state( (object) ['published' => '2'] ) );

        $this->assertTrue( Joomla\Value::enabled( [], 'show' ) );
        $this->assertFalse( Joomla\Value::enabled( [], 'show', false ) );
        $this->assertFalse( Joomla\Value::enabled( ['show' => '0'], 'show' ) );
        $this->assertTrue( Joomla\Value::enabled( ['show' => '1'], 'show', false ) );

        $this->assertSame( 'text', Joomla\Value::string( ' text ' ) );
        $this->assertSame( '5', Joomla\Value::string( 5 ) );
        $this->assertSame( '', Joomla\Value::string( ['text'] ) );
        $this->assertSame( '', Joomla\Value::string( null ) );
        $this->assertTrue( Joomla\Row::published( (object) [] ) );
        $this->assertTrue( Joomla\Row::published( (object) ['published' => '1'] ) );
        $this->assertFalse( Joomla\Row::published( (object) ['published' => 2] ) );
        $this->assertFalse( Joomla\Row::published( (object) ['published' => 0] ) );
        // articles store their state in the "state" column
        $this->assertTrue( Joomla\Row::published( (object) ['state' => 1] ) );
        $this->assertFalse( Joomla\Row::published( (object) ['state' => 2] ) );

        $this->assertSame( 'de-DE', Joomla\Row::language( (object) ['language' => 'de-DE'] ) );
        $this->assertSame( '*', Joomla\Row::language( (object) [] ) );
        $this->assertSame( '*', Joomla\Row::language( null ) );

        // IDs 0 and 1 are the root category or no parent at all
        $this->assertTrue( Joomla\Row::isRootId( 0 ) );
        $this->assertTrue( Joomla\Row::isRootId( 1 ) );
        $this->assertFalse( Joomla\Row::isRootId( 2 ) );
        // the root rows are found by their level or without level by their ID
        $this->assertTrue( Joomla\Row::isRootRow( (object) ['id' => 5, 'level' => 0] ) );
        $this->assertFalse( Joomla\Row::isRootRow( (object) ['id' => 1, 'level' => 1] ) );
        $this->assertTrue( Joomla\Row::isRootRow( (object) ['id' => 1] ) );
        $this->assertFalse( Joomla\Row::isRootRow( (object) ['id' => 2] ) );
        $this->assertFalse( Joomla\Row::isRootRow( (object) [] ) );

        // the lineage stops at the first unknown parent and categories which are their own parents
        $categories = collect( [
            2 => (object) ['id' => 2, 'parent_id' => 1],
            3 => (object) ['id' => 3, 'parent_id' => 2],
            4 => (object) ['id' => 4, 'parent_id' => 9],
            5 => (object) ['id' => 5, 'parent_id' => 5],
        ] );
        $lineage = fn( int $catId ) => Joomla\CategoryTree::lineage( $categories, $catId );
        $this->assertSame( [[3, 2], 1, true], [$lineage( 3 )->ids(), $lineage( 3 )->stop, $lineage( 3 )->reachesRoot()] );
        $this->assertSame( [[4], 9, false], [$lineage( 4 )->ids(), $lineage( 4 )->stop, $lineage( 4 )->reachesRoot()] );
        $this->assertSame( [[5], 5, false], [$lineage( 5 )->ids(), $lineage( 5 )->stop, $lineage( 5 )->reachesRoot()] );
        $this->assertSame( [[], 7], [$lineage( 7 )->categories, $lineage( 7 )->stop] );
        $this->assertSame( $categories->get( 2 ), $lineage( 2 )->categories[2] );

        // the path contains the ID where the walk stopped once, also if the parents are cyclic
        $cyclic = collect( [
            6 => (object) ['id' => 6, 'parent_id' => 7],
            7 => (object) ['id' => 7, 'parent_id' => 6],
        ] );
        $this->assertSame( [3, 2, 1], $lineage( 3 )->pathIds() );
        $this->assertSame( [4, 9], $lineage( 4 )->pathIds() );
        $this->assertSame( [5], $lineage( 5 )->pathIds() );
        $this->assertSame( [6, 7], Joomla\CategoryTree::lineage( $cyclic, 6 )->pathIds() );
        $this->assertSame( 6, Joomla\CategoryTree::lineage( $cyclic, 6 )->stop );

        $this->assertSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'heading'] ) );
        $this->assertSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'separator'] ) );
        $this->assertSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'url', 'link' => ' # '] ) );
        $this->assertSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'url'] ) );
        $this->assertNotSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'url', 'link' => '#top'] ) );
        $this->assertNotSame( Joomla\MenuLink::KIND_FOLDER, $this->kindOf( (object) ['type' => 'component', 'link' => ''] ) );
    }


    public function testSiteTagIsTheFirstImportedLanguageWithLanguageFilter(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        Schema::connection( 'joomla' )->table( 'languages', fn( Blueprint $table ) => $table->string( 'metadesc' )->default( '' ) );
        $db->table( 'languages' )->where( 'lang_code', 'en-GB' )->update( ['metadesc' => 'English site'] );
        // the pseudo language for all languages isn't the site language even if a language row uses it
        $db->table( 'languages' )->insert( ['lang_id' => 0, 'lang_code' => '*', 'sef' => 'all', 'published' => 1, 'metadesc' => 'All'] );

        // pages for all languages use the description of the site language
        $this->assertSame( 'English site', Joomla\Site::read( $this->reader( $db ), [], 'en' )->description( '*' ) );
    }


    public function testSiteShowsTheModulesAndFieldsOfTheLanguage(): void
    {
        $categories = $this->categories( [] );
        $this->languageFilter();
        $filtered = $this->site( $categories );
        $this->languageFilter( false );
        $all = $this->site( $categories );

        // with language filter, only the ones of the language and for all languages are shown, the import language for content for all languages
        $this->assertTrue( $filtered->shows( '*', 'de-DE' ) );
        $this->assertTrue( $filtered->shows( 'de-DE', 'de-DE' ) );
        $this->assertFalse( $filtered->shows( 'en-GB', 'de-DE' ) );
        $this->assertTrue( $filtered->shows( 'en-GB', '*' ) );
        $this->assertFalse( $filtered->shows( 'de-DE', '*' ) );
        $this->assertTrue( $all->shows( 'en-GB', 'de-DE' ) );

        // shows() is inPageLanguage() with language filter, without it the content in the imported language is in the page language
        $this->assertTrue( $filtered->inPageLanguage( 'de-DE', 'de-DE' ) );
        $this->assertFalse( $filtered->inPageLanguage( 'en-GB', 'de-DE' ) );
        $this->assertTrue( $all->inPageLanguage( 'en-GB', 'de-DE' ) );
        $this->assertFalse( $all->inPageLanguage( 'de-DE', 'de-DE' ) );
        $this->assertTrue( $all->inPageLanguage( '*', 'de-DE' ) );

        // only the language filter shows content on the sites of its language
        $this->assertTrue( $filtered->onSite( 'en-GB' ) );
        $this->assertFalse( $filtered->onSite( 'de-DE' ) );
        $this->assertTrue( $all->onSite( 'de-DE' ) );
        $this->assertTrue( $filtered->onOtherSite( 'de-DE' ) );
        $this->assertFalse( $filtered->onOtherSite( 'en-GB' ) );
        $this->assertFalse( $filtered->onOtherSite( '1-XX' ) );
        $this->assertFalse( $all->onOtherSite( 'de-DE' ) );
    }


    public function testSiteDescriptionOfTheLanguage(): void
    {
        $db = DB::connection( 'joomla' );
        Schema::connection( 'joomla' )->table( 'languages', fn( Blueprint $table ) => $table->string( 'metadesc' )->default( '' ) );
        $db->table( 'languages' )->where( 'lang_code', 'en-GB' )->update( ['metadesc' => 'English'] );
        $db->table( 'languages' )->where( 'lang_code', 'de-DE' )->update( ['metadesc' => 'Deutsch'] );
        $categories = $this->categories( [] );
        $site = function( bool $filter, string $lang = 'en' ) use ( $categories ) {
            $this->languageFilter( $filter );
            return $this->site( $categories, lang: $lang );
        };

        // with language filter, the description of the content language, for all languages the one of the site
        $this->assertSame( 'Deutsch', $site( true )->description( 'de-DE' ) );
        $this->assertSame( 'English', $site( true )->description( '*' ) );
        $this->assertSame( '', $site( true )->description( 'fr-FR' ) );
        // without language filter, always the one of the default site language
        $this->assertSame( 'English', $site( false )->description( 'de-DE' ) );
        // with language filter but without usable site language for the imported language, only the content languages have one
        $this->assertSame( '', $site( true, 'fr' )->description( '*' ) );
        $this->assertSame( 'Deutsch', $site( true, 'fr' )->description( 'de-DE' ) );
    }


    public function testRowValueAndTextHelpers(): void
    {
        $this->assertSame( [], Joomla\Row::params( null ) );
        $this->assertSame( [], Joomla\Row::params( (object) [] ) );
        $this->assertSame( ['a' => 1], Joomla\Row::params( (object) ['params' => '{"a":1}'] ) );
        $this->assertSame( '', Joomla\Row::storedAlias( null ) );
        $this->assertSame( '', Joomla\Row::storedAlias( (object) ['title' => 'About'] ) );
        $this->assertSame( 'about', Joomla\Row::storedAlias( (object) ['alias' => 'about'] ) );
        // rows without language and no row at all are for all languages
        $this->assertTrue( Joomla\Row::allLanguages( null ) );
        $this->assertTrue( Joomla\Row::allLanguages( (object) [] ) );
        $this->assertTrue( Joomla\Row::allLanguages( (object) ['language' => '*'] ) );
        $this->assertFalse( Joomla\Row::allLanguages( (object) ['language' => 'de-DE'] ) );

        // like Joomla, IDs can contain the alias
        $this->assertSame( 5, Joomla\Uri::id( '5:about' ) );
        $this->assertSame( 7, Joomla\Uri::id( 7 ) );
        $this->assertSame( 0, Joomla\Uri::id( null ) );
        $this->assertSame( 0, Joomla\Uri::id( ['5'] ) );
        $this->assertSame( 0, Joomla\Uri::id( 'abc' ) );

        $this->assertTrue( Joomla\MenuItem::home( (object) ['home' => 1] ) );
        $this->assertFalse( Joomla\MenuItem::home( (object) ['home' => '0'] ) );
        $this->assertFalse( Joomla\MenuItem::home( (object) [] ) );
        $this->assertMenuLink( 'article', 5, (object) ['type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=5:about'] );

        // without extensions table, the installed extensions are unknown
        $this->assertFalse( $this->reader()->extensions()->known() );
    }


    public function testUriQueriesAreParsedLikePhp(): void
    {
        $this->assertSame( ['a' => '1', 'b' => ['2']], Joomla\Uri::query( 'a=1&b[]=2' ) );
        $this->assertSame( [], Joomla\Uri::query( '' ) );
    }


    public function testRemembersKeepsNullValues(): void
    {
        // NULL values are cached too
        $memo = new class {
            use Remembers;

            /** @var array<array-key, mixed> */
            public array $cache = [];

            public function get( int|string $key, \Closure $fetch ): mixed
            {
                return $this->remember( $this->cache, $key, $fetch );
            }
        };
        $calls = 0;
        $fetch = function() use ( &$calls ) { $calls++; return null; };
        $this->assertNull( $memo->get( 'a', $fetch ) );
        $this->assertNull( $memo->get( 'a', $fetch ) );
        $this->assertSame( 'b', $memo->get( 5, fn() => 'b' ) );
        $this->assertSame( 1, $calls );
        $this->assertSame( ['a' => null, 5 => 'b'], $memo->cache );
    }


    public function testLanguagesDescribeOnlyTheUsableLanguages(): void
    {
        // only the published languages guests can use have descriptions, unknown and unusable languages are guessed or unknown
        $rows = $this->items( [
            (object) ['lang_code' => 'en-GB', 'metadesc' => 'English'],
            (object) ['lang_code' => 'de-DE', 'published' => 0, 'metadesc' => 'Deutsch'],
            (object) ['lang_code' => 'fr-FR', 'metadesc' => 'Francais'],
            (object) ['lang_code' => 'it-IT'],
        ] );
        $usable = new Joomla\Languages( $rows, [], 'en', fn( $row ) => $row->lang_code !== 'fr-FR' );
        $this->assertSame( ['English', '', ''], [$usable->description( 'en-GB' ), $usable->description( 'it-IT' ), $usable->description( 'fr-FR' )] );
        $this->assertSame( ['en-GB', 'it-IT'], $usable->tags() );
        $this->assertNull( $usable->language( 'fr-FR' ) );
        $this->assertSame( 'es', $usable->language( 'es-ES' )['lang'] ?? null );

        // with language filter, tags missing from a non-empty languages table are unknown, only without languages table they are guessed
        $filtered = new Joomla\Languages( $rows, [], 'en', null, true );
        $this->assertNull( $filtered->language( 'es-ES' ) );
        $this->assertNull( $filtered->language( 'en-AU' ) );
        $this->assertFalse( $filtered->source( 'en-AU' ) );
        $this->assertSame( 'en', $filtered->language( 'en-GB' )['lang'] ?? null );
        $this->assertSame( 'es', ( new Joomla\Languages( $this->items( [] ), [], 'en', null, true ) )->language( 'es-ES' )['lang'] ?? null );
        $this->assertSame( 'Francais', ( new Joomla\Languages( $rows, [], 'en' ) )->description( 'fr-FR' ) );
    }


    public function testLanguagesOtherThanTheImportedOne(): void
    {
        $languages = new Joomla\Languages( $this->items( [
            (object) ['lang_code' => 'en-GB', 'sef' => 'en'],
            (object) ['lang_code' => 'de-DE', 'sef' => 'de'],
            (object) ['lang_code' => 'fr-FR', 'sef' => 'fr', 'published' => 0],
        ] ), [], 'en' );
        // other languages are known languages except the imported one and the one for all languages
        $this->assertTrue( $languages->other( 'de-DE' ) );
        $this->assertFalse( $languages->other( 'en-GB' ) );
        $this->assertFalse( $languages->other( '*' ) );
        $this->assertFalse( $languages->other( 'fr-FR' ) );
    }


    public function testGroupKeepsTheFirstTranslationOfEachLanguage(): void
    {
        $languages = new Joomla\Languages( $this->items( [
            (object) ['lang_code' => 'en-GB', 'sef' => 'en'],
            (object) ['lang_code' => 'de-DE', 'sef' => 'de'],
        ] ), [], 'en' );
        $groups = $languages->group( [1 => 'en-GB', 2 => 'de-DE', 3 => 'de-DE', 4 => 'de-DE'], [[4, 1, 3, 2]] );

        // pages have one variant per language, so the other translations in the same language are skipped and reported
        $this->assertSame( [1 => [2]], $groups->translations );
        $this->assertSame( [3 => ['lang' => 'de', 'tag' => 'de-DE', 'used' => 2], 4 => ['lang' => 'de', 'tag' => 'de-DE', 'used' => 2]], $groups->duplicates );
    }


    public function testArticleNameIsTheNameOfTheArticlePage(): void
    {
        // the import keeps the pages of unchanged articles by comparing their names with the ones the pages are built with
        $items = [1 => (object) ['id' => 101, 'title' => 'About us', 'params' => '{}'], 2 => (object) ['id' => 201, 'title' => '', 'params' => '{}']];
        $articles = new Joomla\Articles( categoryIds: [1 => 0], translations: [1 => [2]], times: [], items: $items, visible: [1 => true, 2 => true],
            routes: [Joomla\Kind::ARTICLE => [], Joomla\Kind::ARTICLE_TRANSLATION => []] );
        $run = $this->importRun( $this->site( Collection::make() ), $articles );
        $run->prefetchTexts( [1, 2, 3] );
        $routes = $this->routes( Collection::make(), [] );
        $rows = $this->reader()->articles( [1, 2, 3] );

        foreach( $rows as $row ) {
            $this->assertSame( $run->pages->articlePage( $row, 'path', $routes )->data['name'] ?? null, $run->pages->articleName( $row ) );
        }

        $this->assertSame( ['About us', 'Über uns', 'À propos'], array_values( array_map( $run->pages->articleName( ... ), $rows ) ) );
    }


    public function testGuessedLanguagesDontUseTheCodesOfLanguageOptions(): void
    {
        $rows = $this->items( [(object) ['lang_code' => 'en-GB', 'sef' => 'en']] );
        $languages = new Joomla\Languages( $rows, ['de-AT:de'], 'en' );

        // "de-DE" would replace the translations of "de-AT", so it's unknown instead of guessed as "de"
        $this->assertSame( 'de', $languages->language( 'de-AT' )['lang'] ?? null );
        $this->assertNull( $languages->language( 'de-DE' ) );
        $this->assertSame( 'fr', $languages->language( 'fr-FR' )['lang'] ?? null );

        $groups = $languages->group( [1 => 'en-GB', 2 => 'de-AT', 3 => 'de-DE'], [[1, 2, 3]] );

        $this->assertSame( [1 => [2]], $groups->translations );
        $this->assertSame( ['de-DE' => 'de-DE'], $groups->unknown );
        $this->assertSame( [], $groups->duplicates );

        // guessed tags with the same code as each other are reported as duplicates
        $groups = ( new Joomla\Languages( $rows, [], 'en' ) )->group( [1 => 'en-GB', 2 => 'de-AT', 3 => 'de-DE'], [[1, 2, 3]] );

        $this->assertSame( [1 => [2]], $groups->translations );
        $this->assertSame( [3 => ['lang' => 'de', 'tag' => 'de-DE', 'used' => 2]], $groups->duplicates );
    }


    public function testLanguageOptionsMustUseOwnCodes(): void
    {
        $rows = $this->items( [
            (object) ['lang_code' => 'en-GB', 'sef' => 'en'],
            (object) ['lang_code' => 'de-DE', 'sef' => 'de'],
            (object) ['lang_code' => 'fr-FR', 'sef' => 'fr', 'published' => 0],
        ] );

        // options replacing the derived language of the same tag or using the code of an unknown language are fine
        $this->assertSame( 'de', ( new Joomla\Languages( $rows, ['de-DE:de', 'fr-CA:fr'], 'en' ) )->language( 'de-DE' )['lang'] ?? null );
        $this->assertSame( 'de-AT', ( new Joomla\Languages( $rows, ['de-AT:de-AT'], 'en' ) )->language( 'de-AT' )['lang'] ?? null );

        // two tags with the same code would replace the translations of each other
        foreach( [['de-AT:de'], ['de-AT:de-at', 'de-CH:de-at'], ['en-US:en']] as $options )
        {
            try {
                new Joomla\Languages( $rows, $options, 'en' );
                $this->fail( 'No exception for ' . implode( ' ', $options ) );
            } catch( \InvalidArgumentException $e ) {
                $this->assertStringContainsString( 'use the same language code', $e->getMessage() );
            }
        }

        $this->expectExceptionMessage( 'Joomla languages de-AT, de-DE use the same language code "de", map each one to its own code with --language' );
        new Joomla\Languages( $rows, ['de-AT:de'], 'en' );
    }


    public function testRowLanguageOriginAndMenuItemHelpers(): void
    {
        $a = (object) ['id' => 1];
        $c = (object) ['id' => 3];
        // rows are returned in the order of the IDs, missing ones are skipped
        $this->assertSame( [$c, $a], Joomla\Row::pick( [1 => $a, 3 => $c], [3, 2, '1'] ) );
        $this->assertSame( [], Joomla\Row::pick( [1 => $a], [] ) );

        $categories = Collection::make( [
            2 => (object) ['id' => 2, 'parent_id' => 1, 'published' => 2],
            3 => (object) ['id' => 3, 'parent_id' => 2, 'published' => 1],
            4 => (object) ['id' => 4, 'parent_id' => 3, 'published' => 2],
        ] );
        $archived = fn( $category ) => (int) $category->published === 2;
        // the category itself is tested first, then its parents
        $this->assertSame( 4, Joomla\CategoryTree::firstAncestor( $categories, 4, $archived )?->id );
        $this->assertSame( 2, Joomla\CategoryTree::firstAncestor( $categories, 3, $archived )?->id );
        $this->assertNull( Joomla\CategoryTree::firstAncestor( $categories, 3, fn() => false ) );
        $this->assertNull( Joomla\CategoryTree::firstAncestor( $categories, 99, $archived ) );

        // a plus sign in the path is no encoded space
        $this->assertSame( '/a+b', Joomla\Uri::decodedPath( 'a+b' ) );

        // only the lower case shortcode disables the e-mail cloaking plugin like in Joomla, all shortcodes are removed then
        $this->assertSame( 'a  b', Joomla\Emails::cloakOff( 'a {emailcloak=off} b' ) );
        $this->assertSame( 'a  b ', Joomla\Emails::cloakOff( 'a {emailcloak=off} b {EmailCloak=Off}' ) );
        $this->assertNull( Joomla\Emails::cloakOff( '{EMAILCLOAK=OFF}' ) );
        $this->assertSame( "\"a' & b\u{a0}<", Html::decode( '&quot;a&#039; &amp; b&nbsp;&lt;' ) );
        $this->assertSame( "'", Html::decode( '&apos;' ) );

        $home = (object) ['home' => 1];
        $item = (object) ['home' => 0];
        $this->assertSame( $item, Joomla\MenuItem::withoutHome( $item ) );
        $this->assertNull( Joomla\MenuItem::withoutHome( $home ) );
        $this->assertNull( Joomla\MenuItem::withoutHome( null ) );
        $this->assertSame( 'article', Joomla\MenuItem::queryView( ['option' => 'com_content', 'view' => 'article'] ) );
        $this->assertNull( Joomla\MenuItem::queryView( ['option' => 'com_contact', 'view' => 'article'] ) );
        $this->assertNull( Joomla\MenuItem::queryView( ['option' => 'com_content', 'view' => ['article']] ) );
        $this->assertNull( Joomla\MenuItem::queryView( ['option' => 'com_content'] ) );
    }


    public function testReaderFetchesTheMenuItemsOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $db->getSchemaBuilder()->table( 'menu', fn( Blueprint $table ) => $table->integer( 'home' )->default( 0 ) );
        $item = fn( int $id, string $menu, string $link, int $lft, array $more = [] ) => $more
            + ['id' => $id, 'menutype' => $menu, 'title' => 'Item ' . $id, 'alias' => 'item-' . $id, 'link' => $link, 'type' => 'component', 'lft' => $lft,
            'level' => 1, 'home' => 0, 'published' => 1, 'client_id' => 0];
        $db->table( 'menu' )->insert( [
            $item( 1, '', '', 0, ['level' => 0, 'home' => 1] ),
            $item( 10, 'main', 'index.php?option=com_content&view=article&id=1', 3 ),
            $item( 11, 'main', 'index.php?option=com_content&view=category&id=2', 2 ),
            $item( 12, 'main', 'index.php?option=com_content&view=featured', 1, ['home' => 1] ),
            $item( 13, 'footer', 'index.php?option=com_content&view=categories&id=0', 4 ),
            $item( 14, 'footer', 'index.php?option=com_content&view=archive', 5, ['published' => 0] ),
            $item( 15, 'main', 'index.php?option=com_content&view=article&id=2', 6, ['client_id' => 1] ),
            $item( 16, 'footer', 'index.php?option=com_contact&view=category&id=2', 7 ),
            $item( 17, 'footer', 'index.php?option=com_users&view=login', 8, ['home' => 1] ),
            $item( 18, 'footer', 'index.php?option=com_content&view=archive', 9 ),
            // only the views of the articles component itself are used, not ones with similar names
            $item( 19, 'extra', 'index.php?option=com_content&view=articles', 10 ),
            $item( 20, 'extra', 'index.php?option=com_contentx&view=article&id=1', 11 ),
            $item( 21, 'extra', 'index.php?option=com_content&view=categoryx&id=2', 12 ),
            $item( 22, 'extra', 'index.php?option=com_content&view=featuredx', 13 ),
        ] );
        $reader = $this->reader( $db );
        $ids = fn( Collection $items ) => $items->pluck( 'id' )->all();

        $db->enableQueryLog();
        // only the published menu items of the site below the root item are used, ordered by their position
        $this->assertSame( [12, 11, 10, 13, 16, 17, 18, 19, 20, 21, 22], $ids( $reader->menuItems() ) );
        $this->assertSame( [12, 11, 10, 13, 16, 17, 18, 19, 20, 21, 22], $reader->menuItems()->keys()->all() );
        // the lists of the articles component and the home menu items of all components
        $this->assertSame( [12, 11, 13, 17, 18], $ids( $reader->menuItems()->filter( Joomla\MenuItem::routing( ... ) ) ) );
        $this->assertSame( [11, 10], $ids( $reader->menuItems()->only( [10, '11', 14, 15, 99] ) ) );
        // the root menu item isn't a menu item of the site
        $this->assertNull( $reader->menuItems()->get( 1 ) );
        $this->assertSame( 1, count( Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], ' from "menu"' ) ) ) );
        $db->disableQueryLog();
    }


    public function testComponentParamsAreFetchedOnce(): void
    {
        $db = DB::connection( 'joomla' );
        // the options of a database without extensions table are empty
        $this->assertSame( [], ( $this->reader( $db ) )->extensions()->componentParams( Joomla\Extensions::COMPONENT_CONTENT ) );
        $this->extensionsTable();
        $db->table( 'extensions' )->insert( [
            ['type' => 'component', 'element' => 'com_content', 'params' => '{"show_intro":"0"}'],
            ['type' => 'component', 'element' => 'com_users', 'params' => '{"guest_usergroup":"13"}'],
            // only components have component options
            ['type' => 'plugin', 'element' => 'com_tags', 'params' => '{"tag_list_limit":"5"}'],
        ] );
        $reader = $this->reader( $db );

        $db->enableQueryLog();
        $this->assertSame( ['show_intro' => '0'], $reader->extensions()->componentParams( Joomla\Extensions::COMPONENT_CONTENT ) );
        $this->assertSame( ['guest_usergroup' => '13'], $reader->extensions()->componentParams( 'com_users' ) );
        // missing components are only looked up once too
        $this->assertSame( [], $reader->extensions()->componentParams( 'com_missing' ) );
        $this->assertSame( [], $reader->extensions()->componentParams( Joomla\Extensions::COMPONENT_TAGS ) );
        $this->assertCount( 0, $reader->extensions()->componentParams( 'com_missing' ) );
        $this->assertSame( 4, count( Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], ' from "extensions"' ) ) ) );
        $db->disableQueryLog();
    }


    public function testSiteLanguagesAreFetchedOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $this->extensionsTable();
        $db->table( 'extensions' )->insert( [
            ['type' => 'language', 'element' => 'en-GB', 'client_id' => 0, 'enabled' => 1],
            ['type' => 'language', 'element' => 'de-DE', 'client_id' => 0, 'enabled' => 0],
            ['type' => 'language', 'element' => 'fr-FR', 'client_id' => 1, 'enabled' => 1],
        ] );
        $extensions = ( $this->reader( $db ) )->extensions();

        $db->enableQueryLog();
        $this->assertSame( ['en-GB'], $extensions->siteLanguages() );
        $this->assertSame( ['en-GB'], $extensions->siteLanguages() );
        $this->assertSame( 1, count( Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], ' from "extensions"' ) ) ) );
        $db->disableQueryLog();
    }


    public function testReaderChecksTablesAndPluginsOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $this->extensionsTable();
        $db->table( 'extensions' )->insert( [
            ['type' => 'plugin', 'folder' => 'system', 'element' => 'sef', 'params' => '{"strictrouting":"1"}', 'manifest_cache' => null],
            ['type' => 'file', 'folder' => '', 'element' => 'joomla', 'params' => '{}', 'manifest_cache' => '{"version":"5.2.1"}'],
        ] );
        $reader = $this->reader( $db );

        $db->enableQueryLog();
        $this->assertEquals( new Joomla\Plugin( 1, ['strictrouting' => '1'] ), $reader->extensions()->plugin( 'system', 'sef' ) );
        $this->assertNull( $reader->extensions()->plugin( 'content', 'pagebreak' ) );
        $count = count( $db->getQueryLog() );

        // the plugins, also the disabled ones, and the existence of the tables are only fetched once
        $this->assertEquals( new Joomla\Plugin( 1, ['strictrouting' => '1'] ), $reader->extensions()->plugin( 'system', 'sef' ) );
        $this->assertNull( $reader->extensions()->plugin( 'content', 'pagebreak' ) );
        $this->assertSame( $count, count( $db->getQueryLog() ) );
        $this->assertTrue( $reader->extensions()->version()->upTo( 5 ) );
        $this->assertFalse( $reader->extensions()->version()->upTo( 4 ) );
        $reader->extensions()->version();
        $this->assertSame( $count + 1, count( $db->getQueryLog() ) );
        // without tags table, only its existence is checked once
        $reader->tags( [1], Joomla\Reader::ARTICLE_TAGS );
        $this->assertSame( [], $reader->tags( [1], Joomla\Reader::ARTICLE_TAGS ) );
        $this->assertSame( $count + 2, count( $db->getQueryLog() ) );
        $db->disableQueryLog();
    }


    public function testSchemaPrefixesAndQualifiesNames(): void
    {
        $db = DB::connection( 'joomla' );
        $schema = new Joomla\Schema( $db, 'jos_' );

        $this->assertSame( 'jos_content', $schema->table( 'content' ) );
        $this->assertSame( 'SELECT * FROM ' . $db->getTablePrefix() . 'jos_content', $schema->sql( 'SELECT * FROM #__content' ) );
        // the table prefix of the connection is added in raw SQL
        $prefix = $db->getTablePrefix();
        $db->setTablePrefix( 'x_' );

        try {
            $this->assertSame( 'SELECT * FROM x_jos_content', $schema->sql( 'SELECT * FROM #__content' ) );
        } finally {
            $db->setTablePrefix( $prefix );
        }
        $this->assertNull( $schema->tableIfExists( 'content' ) );
        $this->assertSame( 'content', ( new Joomla\Schema( $db, '' ) )->tableIfExists( 'content' ) );
        $this->assertSame( ['id', 'title'], ( new Joomla\Schema( $db, '' ) )->existingColumns( 'content', ['id', 'unknown', 'title'] ) );
        $this->assertTrue( ( new Joomla\Schema( $db, '' ) )->hasColumn( 'content', 'title' ) );
        $this->assertFalse( ( new Joomla\Schema( $db, '' ) )->hasColumn( 'content', 'unknown' ) );
    }


    public function testSiteReadUsesAllCategoriesOfTheReader(): void
    {
        DB::connection( 'joomla' )->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'published' => 0],
            ['id' => 3, 'extension' => 'com_contact', 'title' => 'Contacts', 'alias' => 'contacts', 'published' => 1],
        ] );
        $site = Joomla\Site::read( $this->reader(), [], 'en' );

        // the article categories of all states are read, so the unpublished ones hide their articles
        $this->assertSame( [2], $site->categories->keys()->all() );
        $this->assertNull( $site->category( 2 ) );
    }


    public function testIndexSelectsArticlesTranslationsAndFolders(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'languages' )->insert( ['lang_code' => 'fr-FR', 'sef' => 'fr', 'published' => 0] );
        $db->table( 'languages' )->insert( ['lang_code' => 'es-ES', 'sef' => 'es', 'published' => 1] );
        $db->table( 'menu' )->insert( ['id' => 11, 'menutype' => 'menu-de', 'title' => 'Hallo', 'alias' => 'hallo', 'link' => '', 'type' => 'heading', 'language' => 'de-DE'] );
        $db->table( 'associations' )->insert( [
            ['id' => 2, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 10, 'context' => 'com_menus.item', 'key' => 'menu'],
            ['id' => 11, 'context' => 'com_menus.item', 'key' => 'menu'],
            ['id' => 12, 'context' => 'com_menus.item', 'key' => 'menu'],
        ] );
        $categories = $this->categories( [
            2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1, 'language' => 'en-GB'],
            3 => (object) ['id' => 3, 'title' => 'Neues', 'alias' => 'neues', 'parent_id' => 1, 'level' => 1, 'published' => 1, 'language' => 'de-DE'],
            4 => (object) ['id' => 4, 'title' => 'Hidden', 'alias' => 'hidden', 'parent_id' => 1, 'level' => 1, 'published' => 0, 'language' => 'es-ES'],
        ] );
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $categories );
        $warnings = [];
        $host = Joomla\Host::forTests( warn: function( string $msg ) use ( &$warnings ) { $warnings[] = $msg; } );
        $index = $this->wiring( $site, host: $host );

        $result = $index->articles( true );

        // articles in other languages are only translations, the ones of unknown languages and without source article are skipped
        $this->assertSame( [1 => 0], $result->categoryIds );
        $this->assertSame( [1 => [2]], $result->translations );
        // articles without valid time have none, so they are always changed
        $this->assertEquals( [1 => strtotime( '2019-03-04 05:06:07' )], $result->times );
        $this->assertSame( [
            'Skipping article translations of unknown or unpublished Joomla languages [fr-FR], add --language=<tag>:<code> to import them.',
            'Skipping 2 Joomla articles in other languages without associated article in the import language because Joomla only shows them on the sites of their languages.',
        ], $warnings );
        // all articles Joomla shows are visible, also the skipped ones and the translations
        $this->assertEquals( [1 => true, 2 => true, 3 => true, 4 => true], $result->visible );
        $this->assertSame( [], $index->articles()->times );

        // hidden categories and menu items of the imported menu aren't translations, associated menu items of other menus are
        $item = fn( int $id, string $lang ) => (object) ['id' => $id, 'type' => 'heading', 'title' => 'Item', 'alias' => 'item-' . $id, 'link' => '', 'parent_id' => 1, 'language' => $lang];
        $menuItems = $this->items( [$item( 10, 'en-GB' ), $item( 12, 'de-DE' )] );
        $folders = $this->wiring( $site, menu: $menuItems, host: $host )->folders();
        $this->assertSame( [2 => [3]], array_map( fn( $rows ) => array_column( $rows, 'id' ), $folders->categoryTranslations ) );
        $this->assertSame( [10 => [11]], array_map( fn( $rows ) => array_column( $rows, 'id' ), $folders->menuTranslations ) );
        $this->assertSame( [2], array_keys( $folders->categories ) );

        // the URL paths of the articles and translations are returned, translated categories don't claim paths of their own
        $this->assertEquals( [Joomla\Kind::ARTICLE => [1 => new Joomla\RoutePath( null, 'about' )], Joomla\Kind::ARTICLE_TRANSLATION => [2 => new Joomla\TranslationRoute( 'ueber-uns', 'de-DE' )]], $result->routes );
        $this->assertSame( [], $result->items );
        $links = $this->importRun( $this->wiring( $site, menu: $menuItems, origin: new Joomla\Origin( 'example.com' ) ), articles: $result, folders: $folders )->links;
        $article = fn( int $id ) => $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => $id . ':alias'], Joomla\Languages::ALL );
        $this->assertSame( '/about', $article( 1 ) );
        $this->assertSame( '/ueber-uns', $article( 2 ) );
        $this->assertNull( $article( 4 ) );
        $this->assertSame( '/item-10', $links->resolve( ['Itemid' => '10'], Joomla\Languages::ALL ) );
        // links of other components aren't article links even with the same view
        $this->assertNull( $links->resolve( ['option' => 'com_contact', 'view' => 'article', 'id' => '1:alias'], Joomla\Languages::ALL ) );
    }


    public function testDatesAreParsedAndFormattedInUtc(): void
    {
        $date = Joomla\Date::parse( '2020-01-02 03:04:05' );

        $this->assertSame( 1577934245, $date?->getTimestamp() );
        $this->assertNull( Joomla\Date::parse( 'invalid' ) );
        $this->assertSame( '2020-01-02 03:04', Joomla\Date::format( 1577934245 ) );
        $this->assertSame( '2020-01-02', Joomla\Date::format( 1577934245, Joomla\Date::DATE_FORMAT ) );
        // dates in other time zones are shown in UTC unless a time zone is given
        $this->assertSame( '2020-01-02 03:04', Joomla\Date::format( new \DateTimeImmutable( '2020-01-02 04:04:05', new \DateTimeZone( 'Europe/Berlin' ) ) ) );
        $this->assertSame( '2020-01-02 04:04', Joomla\Date::format( 1577934245, Joomla\Date::DATETIME_FORMAT, new \DateTimeZone( 'Europe/Berlin' ) ) );
    }


    public function testRowsAndMenuItemsOfTheLanguage(): void
    {
        $this->assertTrue( Joomla\Row::inLanguage( (object) ['language' => '*'], 'de-DE' ) );
        $this->assertTrue( Joomla\Row::inLanguage( (object) ['language' => 'de-DE'], 'de-DE' ) );
        $this->assertFalse( Joomla\Row::inLanguage( (object) ['language' => 'en-GB'], 'de-DE' ) );
        // unlike Joomla\Site::shows(), rows of a language don't match the tag for all languages
        $this->assertFalse( Joomla\Row::inLanguage( (object) ['language' => 'en-GB'], '*' ) );

        $categories = $this->categories( [] );
        $site = function( int $version, bool $filter ) use ( $categories ) {
            $this->languageFilter( $filter );
            $this->setJoomlaVersion( $version );
            return $this->site( $categories );
        };

        // Joomla 3 with language filter uses only the menu items for all languages for articles for all languages
        $this->assertFalse( $site( 3, true )->itemShows( 'en-GB', '*' ) );
        $this->assertTrue( $site( 3, true )->itemShows( '*', '*' ) );
        $this->assertTrue( $site( 3, true )->itemShows( '*', 'de-DE' ) );
        $this->assertTrue( $site( 4, true )->itemShows( 'en-GB', '*' ) );
        $this->assertTrue( $site( 3, false )->itemShows( 'en-GB', '*' ) );
        $this->assertFalse( $site( 4, true )->itemShows( 'en-GB', 'de-DE' ) );
    }


    public function testSiteDerivesTheLanguageFilterFromItsPlugins(): void
    {
        // without extensions table, the plugins are unknown, so the language filter is disabled but the associations are used
        $site = $this->site( $this->categories( [] ) );
        $this->assertFalse( $site->languageFilter );
        $this->assertTrue( $site->plugins->itemAssociations() );

        // the language filter of the site is the plugin of the Joomla database guests can see
        $this->languageFilter();
        $site = $this->site( $this->categories( [] ) );
        $this->assertTrue( $site->languageFilter );
        $this->assertTrue( $site->plugins->languageFilter() );
        DB::connection( 'joomla' )->table( 'extensions' )->where( 'element', 'languagefilter' )->update( ['enabled' => 0] );
        $site = Joomla\Site::read( $this->reader(), [], 'en' );
        $this->assertFalse( $site->languageFilter );
        $this->assertFalse( $site->plugins->itemAssociations() );
    }


    public function testLanguageTagsKeepTheirOrder(): void
    {
        $rows = $this->items( [(object) ['lang_code' => 'de-DE', 'metadesc' => 'Deutsch'], (object) ['lang_code' => 'en-GB']] );
        $languages = new Joomla\Languages( $rows, [], 'en' );

        $this->assertSame( ['de-DE', 'en-GB'], $languages->tags() );
        $this->assertSame( ['Deutsch', ''], [$languages->description( 'de-DE' ), $languages->description( 'en-GB' )] );
    }
}
