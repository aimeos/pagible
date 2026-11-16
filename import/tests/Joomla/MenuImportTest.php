<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Joomla;

use Aimeos\Cms\Commands\JoomlaImport;
use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Command-level tests importing the Joomla menus with the URLs, links, menu item options and redirects of the pages.
 */
class MenuImportTest extends TestAbstract
{
    public function testImportsMenuFetchingTextsAtOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Company', 'alias' => 'company', 'link' => '', 'type' => 'heading', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'About', 'alias' => 'about', 'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'parent_id' => 101, 'level' => 2, 'lft' => 2],
            ['id' => 103, 'menutype' => 'mainmenu', 'title' => 'Nur', 'alias' => 'nur', 'link' => 'index.php?option=com_content&view=article&id=4', 'type' => 'component', 'parent_id' => 101, 'level' => 2, 'lft' => 3],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => ''] );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $cms->enableQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $queries = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) );
        $lookups = $this->queries( $cms )->filter( fn( $query ) => preg_match( '/"path" in \(\?, \?\).* limit 1/', $query['query'] ) === 1 );
        $db->disableQueryLog();
        $cms->disableQueryLog();

        // the texts of the menu items with their translations and of the remaining articles are fetched at once
        $this->assertSame( [[1, 2, 3, 4], [5]], $queries->map( fn( $query ) => array_map( 'intval', $query['bindings'] ) )->values()->all() );
        // only the page of the menu heading is looked up on its own, the ones of the articles are fetched at once
        $this->assertSame( [['company', '/company']], $lookups->map( fn( $query ) => array_slice( $query['bindings'], -3, 2 ) )->values()->all() );
        // the pages of the menu items use their titles as names
        $this->assertSame( ['About', 'Nur'], PageView::where( 'name', 'Company' )->firstOrFail()->children->pluck( 'name' )->all() );
        $this->assertSame( 'Willkommen', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'About' )->firstOrFail()->id )->content[1]->data->title ?? null );
        $this->assertStringContainsString( 'Other', (string) json_encode( PageView::where( 'name', 'Other' )->firstOrFail()->content ) );
    }


    public function testImportsDeepMenuInChunks(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Vision', 'alias' => 'vision', 'language' => 'en-GB', 'introtext' => '<p>Vision</p>', 'fulltext' => ''],
            ['id' => 6, 'title' => 'Values', 'alias' => 'values', 'language' => 'en-GB', 'introtext' => '<p>Values</p>', 'fulltext' => ''],
            ['id' => 7, 'title' => 'Jobs', 'alias' => 'jobs', 'language' => 'en-GB', 'introtext' => '<p>Jobs</p>', 'fulltext' => ''],
            ['id' => 8, 'title' => 'Contact', 'alias' => 'contact', 'language' => 'en-GB', 'introtext' => '<p>Contact</p>', 'fulltext' => ''],
        ] );
        $db->table( 'menu' )->insert( [
            $this->menuItem( 101, 'Company', 1, 1 ),
            $this->menuItem( 102, 'About', 101, 2, 1 ),
            $this->menuItem( 103, 'Team', 102, 3 ),
            $this->menuItem( 104, 'Nur', 103, 4, 4 ),
            $this->menuItem( 105, 'Vision', 103, 4, 5 ),
            $this->menuItem( 106, 'Values', 103, 4, 6 ),
            $this->menuItem( 107, 'Jobs', 101, 2, 7 ),
            $this->menuItem( 108, 'Contact', 1, 1, 8 ),
        ] );
        Artisan::registerCommand( new class extends JoomlaImport {
            protected const TEXT_SIZE = 4;
        } );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $cms->enableQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $texts = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) );
        $nodes = $this->queries( $cms )->filter( fn( $query ) => str_starts_with( $query['query'], 'select * from "cms_pages"' ) );
        $db->disableQueryLog();
        $cms->disableQueryLog();

        // the chunks of menu items contain the texts of at most four articles and translations
        $this->assertSame( [[1, 2, 3, 4], [5, 6, 7, 8]], $texts->map( fn( $query ) => array_map( 'intval', $query['bindings'] ) )->values()->all() );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $parent = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->parent_id;

        $this->assertSame( $root->id, $parent( 'Company' ) );
        $this->assertSame( $root->id, $parent( 'Contact' ) );
        $this->assertSame( PageView::where( 'name', 'Company' )->firstOrFail()->id, $parent( 'About' ) );
        $this->assertSame( PageView::where( 'name', 'About' )->firstOrFail()->id, $parent( 'Team' ) );
        $this->assertSame( ['Nur', 'Vision', 'Values'], PageView::where( 'name', 'Team' )->firstOrFail()->children->pluck( 'name' )->all() );
        // the parent of the next sibling is used again after the descendants of the previous sibling
        $this->assertSame( ['About', 'Jobs'], PageView::where( 'name', 'Company' )->firstOrFail()->children->pluck( 'name' )->all() );

        // the tree node of the parent page is loaded once for consecutive siblings
        $names = PageView::pluck( 'name', 'id' )->all();
        $loads = $nodes->countBy( fn( $query ) => $names[$query['bindings'][0]] ?? '' )->all();

        $this->assertSame( 1, $loads['Team'] ?? 0 );
        $this->assertSame( 1, $loads['About'] ?? 0 );
        $this->assertSame( 2, $loads['Company'] ?? 0 );
    }


    public function testReportsFailedMenuHeadingsAsMenuItems(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            $this->menuItem( 101, 'Company', 1, 1 ),
            $this->menuItem( 102, 'About', 101, 2, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        PageView::where( 'name', 'Company' )->firstOrFail()->variant->forceFill( ['editor' => 'someone', 'name' => 'Changed company'] )->save();
        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the failure names the menu item like the ones of the other menu item types
        $this->assertStringContainsString( 'Failed to import menu item [101] Company', Artisan::output() );
    }


    public function testImportsMenuPrefetchingPerChunk(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Vision', 'alias' => 'vision', 'language' => 'en-GB', 'introtext' => '<p>Vision</p>', 'fulltext' => ''],
            ['id' => 6, 'title' => 'Vision DE', 'alias' => 'vision-de', 'language' => 'de-DE', 'introtext' => '<p>Vision</p>', 'fulltext' => ''],
            ['id' => 7, 'title' => 'Jobs', 'alias' => 'jobs', 'language' => 'en-GB', 'introtext' => '<p>Jobs</p>', 'fulltext' => ''],
            ['id' => 8, 'title' => 'Jobs DE', 'alias' => 'jobs-de', 'language' => 'de-DE', 'introtext' => '<p>Jobs</p>', 'fulltext' => ''],
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 5, 'context' => 'com_content.item', 'key' => 'def'],
            ['id' => 6, 'context' => 'com_content.item', 'key' => 'def'],
            ['id' => 7, 'context' => 'com_content.item', 'key' => 'ghi'],
            ['id' => 8, 'context' => 'com_content.item', 'key' => 'ghi'],
        ] );
        $db->table( 'menu' )->insert( [
            $this->menuItem( 101, 'Company', 1, 1 ),
            $this->menuItem( 102, 'About', 101, 2, 1 ),
            $this->menuItem( 103, 'Team', 101, 2 ),
            $this->menuItem( 104, 'Vision', 103, 3, 5 ),
            $this->menuItem( 105, 'Jobs', 103, 3, 7 ),
            $this->menuItem( 106, 'Nur', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $db->table( 'content' )->update( ['introtext' => '<p>Changed</p>'] );

        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $cms->enableQueryLog();
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $texts = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) );
        $queries = $this->queries( $cms )->pluck( 'query' );
        $db->disableQueryLog();
        $cms->disableQueryLog();

        // the texts, pages and variants of all menu items at all levels are fetched at once
        $this->assertCount( 1, $texts );
        $this->assertEqualsCanonicalizing( range( 1, 8 ), array_map( 'intval', $texts->firstOrFail()['bindings'] ) );
        $this->assertSame( 1, $queries->filter( fn( $query ) => preg_match( '/"path" in \(\?(, \?){7}\)/', $query ) === 1 )->count() );
        $this->assertSame( 1, $queries->filter( fn( $query ) => str_contains( $query, 'where "cms_page_view"."id" in (?, ?, ?)' ) )->count() );
        $this->assertSame( 0, $queries->filter( fn( $query ) => str_contains( $query, 'where "cms_page_view"."id" = ?' ) )->count() );
        $this->assertStringContainsString( 'Changed', json_encode( PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'Jobs' )->firstOrFail()->id )->content ) ?: '' );
    }


    public function testCategoryUrlAndAliasMenuItems(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'published' => 1, 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Hidden', 'alias' => 'hidden', 'published' => 0, 'parent_id' => 1, 'level' => 1, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $item = ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Blog', 'alias' => 'blog', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component', 'lft' => 1] + $item,
            ['id' => 102, 'title' => 'Shop', 'alias' => 'shop', 'link' => 'https://shop.example.com/', 'type' => 'url', 'lft' => 2] + $item,
            ['id' => 103, 'title' => 'Old', 'alias' => 'old', 'link' => 'index.php?Itemid=101', 'type' => 'alias', 'lft' => 3] + $item,
            ['id' => 104, 'title' => 'About', 'alias' => 'about-item', 'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'parent_id' => 103, 'level' => 2, 'lft' => 4] + $item,
            ['id' => 105, 'title' => 'Secret', 'alias' => 'secret', 'link' => 'index.php?option=com_content&view=category&id=3', 'type' => 'component', 'lft' => 5] + $item,
            ['id' => 106, 'title' => 'Script', 'alias' => 'script', 'link' => 'javascript:alert(1)', 'type' => 'url', 'lft' => 6] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the articles of the category are imported below the page of the menu item
        $blog = PageView::where( 'name', 'Blog' )->firstOrFail();
        $this->assertSame( 'blog', $blog->path );
        $this->assertSame( $blog->id, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->parent_id );
        $this->assertSame( 0, PageView::where( 'name', 'News' )->count() );

        $shop = PageView::where( 'name', 'Shop' )->firstOrFail();
        $this->assertSame( 'https://shop.example.com/', $shop->to );
        $this->assertSame( $root->id, $shop->parent_id );

        // children of unsupported menu items are imported below the parent
        $this->assertStringContainsString( "Skipped menu item: [103] Old (its target page isn't supported, its child items", $output );
        $this->assertSame( $root->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );
        $this->assertStringContainsString( "Skipped menu item: [105] Secret (its category isn't shown by Joomla)", $output );
        $this->assertStringContainsString( "Skipped menu item: [106] Script (its URL isn't supported, its child items", $output );
        $this->assertSame( 0, PageView::whereIn( 'name', ['Old', 'Secret', 'Script'] )->count() );

        // link pages of previous imports are updated
        $db->table( 'menu' )->where( 'id', 102 )->update( ['link' => 'https://shop.example.org/'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Failed', Artisan::output() );
        $this->assertSame( 'https://shop.example.org/', PageView::findOrFail( $shop->id )->to );
        $this->assertSame( 1, PageView::where( 'name', 'Shop' )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'Blog' )->count() );

        // link pages don't overwrite other pages
        PageView::findOrFail( $shop->id )->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipped menu item: [102] Shop (/shop) (URL is used by another page)', $output );
        $this->assertStringNotContainsString( 'Failed', $output );
        $this->assertSame( 'https://shop.example.org/', PageView::findOrFail( $shop->id )->to );
    }


    public function testArticlesUseOptionsOfTheirMenuItems(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->text( 'params' )->nullable() );
        $schema->table( 'content', function( Blueprint $table ) {
            $table->string( 'metakey' )->default( '' );
            $table->text( 'metadata' )->nullable();
            $table->text( 'attribs' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Imprint', 'alias' => 'imprint', 'language' => 'en-GB', 'introtext' => '<p>Imprint</p>', 'fulltext' => '',
                'metadesc' => 'Who   we are', 'metakey' => 'legal, imprint', 'metadata' => '{"robots":"noindex, nofollow"}', 'attribs' => '{"article_page_title":"Imprint of the company"}'],
            ['id' => 6, 'title' => 'Privacy', 'alias' => 'privacy', 'language' => 'en-GB', 'introtext' => '<p>Privacy</p>', 'fulltext' => '',
                'metadesc' => '', 'metakey' => '', 'metadata' => '{"robots":""}', 'attribs' => '{"article_page_title":"Privacy of the article"}'],
        ] );
        $db->table( 'menu' )->insert( [
            ['params' => '{"page_title":"About the company","menu_show":"0","menu-meta_description":"All about us","menu-meta_keywords":"company","robots":"noindex"}']
                + array_replace( $this->menuItem( 101, 'About us', 1, 1, 1 ), ['alias' => 'about-us'] ),
            ['params' => '{"menu-meta_description":"Menu text","robots":"index, nofollow"}'] + $this->menuItem( 102, 'Legal', 1, 1, 5 ),
            ['params' => '{"page_title":"Privacy policy","menu_show":"1","robots":"index, follow"}', 'menutype' => 'footer', 'alias' => 'data-protection'] + $this->menuItem( 103, 'Data protection', 1, 1, 6 ),
            ['params' => '{}', 'menutype' => 'footer'] + array_replace( $this->menuItem( 104, 'Other', 1, 1, 1 ), ['alias' => 'other'] ),
            ['params' => '{"page_title":"Über die Firma"}', 'menutype' => 'footer'] + array_replace( $this->menuItem( 105, 'Firma', 1, 1, 2 ), ['alias' => 'firma'] ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $about = PageView::where( 'path', 'about-us' )->firstOrFail();

        // the URL, name, browser title, navigation status and meta data of the menu item are used
        $this->assertSame( ['About us', 'About the company', 2], [$about->name, $about->title, $about->status] );
        $this->assertSame( 'All about us', $about->meta->{'meta-tags'}->data->description ?? null );
        // sites of unknown versions are imported like Joomla 4+ which doesn't show the meta keywords
        $this->assertNull( $about->meta->{'meta-tags'}->data->keywords ?? null );
        $this->assertSame( ['index' => 'noindex'], (array) ( $about->meta->robots->data ?? [] ) );
        $this->assertSame( 0, PageView::where( 'path', 'about' )->count() );
        // the translations use their own menu items
        $de = PageView::language( 'de' )->findOrFail( $about->id );
        $this->assertSame( ['firma', 'Firma', 'Über die Firma'], [$de->path, $de->name, $de->title] );
        $this->assertSame( 'À propos', PageView::language( 'fr' )->findOrFail( $about->id )->title );

        // the meta data of the article is used before the one of the menu item, the browser title of the article after the menu item
        $legal = PageView::where( 'path', 'legal' )->firstOrFail();
        $this->assertSame( ['Legal', 'Imprint of the company', 1], [$legal->name, $legal->title, $legal->status] );
        $this->assertSame( 'Who we are', $legal->meta->{'meta-tags'}->data->description ?? null );
        $this->assertNull( $legal->meta->{'meta-tags'}->data->keywords ?? null );
        $this->assertSame( ['index' => 'noindex', 'follow' => 'nofollow'], (array) ( $legal->meta->robots->data ?? [] ) );

        // menu items of other menus define the URL and the options of the articles not in the imported menu
        $privacy = PageView::where( 'path', 'data-protection' )->firstOrFail();
        $this->assertSame( ['Data protection', 'Privacy policy', 1], [$privacy->name, $privacy->title, $privacy->status] );
        $this->assertSame( ['robots'], array_keys( (array) $privacy->meta ) );
        $this->assertSame( ['index' => 'index', 'follow' => 'follow'], (array) ( $privacy->meta->robots->data ?? [] ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/about-us)', Artisan::output() );

        // Joomla 3 shows the meta keywords of the article before the ones of the menu item
        $this->extensionsTable();
        // the translations stay translations, without the language filter plugin they would be pages of their own
        $this->languageFilter();
        $this->setJoomlaVersion( 3 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'company', PageView::where( 'path', 'about-us' )->firstOrFail()->meta->{'meta-tags'}->data->keywords ?? null );
        $this->assertSame( 'legal, imprint', PageView::where( 'path', 'legal' )->firstOrFail()->meta->{'meta-tags'}->data->keywords ?? null );
    }


    public function testAliasAndCategoriesMenuItems(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->text( 'params' )->nullable();
            $table->boolean( 'home' )->default( false );
        } );
        $db = DB::connection( 'joomla' );
        $item = ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1, 'params' => '{}', 'home' => 0];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Home', 'alias' => 'home', 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'lft' => 1, 'home' => 1] + $item,
            ['id' => 102, 'title' => 'Topics', 'alias' => 'topics', 'link' => 'index.php?option=com_content&view=categories&id=0', 'type' => 'component', 'lft' => 2, 'params' => '{"page_title":"All topics"}'] + $item,
            ['id' => 103, 'title' => 'About', 'alias' => 'about-us', 'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'parent_id' => 102, 'level' => 2, 'lft' => 3] + $item,
            ['id' => 104, 'title' => 'To about', 'alias' => 'to-about', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 4, 'params' => '{"aliasoptions":"103"}'] + $item,
            ['id' => 105, 'title' => 'To topics', 'alias' => 'to-topics', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 5, 'params' => '{"aliasoptions":"102","menu_show":"0"}'] + $item,
            ['id' => 106, 'title' => 'To home', 'alias' => 'to-home', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 6, 'params' => '{"aliasoptions":"101"}'] + $item,
            ['id' => 107, 'title' => 'To shop', 'alias' => 'to-shop', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 7, 'params' => '{"aliasoptions":"201"}'] + $item,
            ['id' => 108, 'title' => 'To nothing', 'alias' => 'to-nothing', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 8, 'params' => '{"aliasoptions":"999"}'] + $item,
            ['id' => 110, 'title' => 'To archive', 'alias' => 'to-archive', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 11, 'params' => '{"aliasoptions":"109"}'] + $item,
            ['id' => 111, 'title' => 'To itself', 'alias' => 'to-itself', 'link' => 'index.php?Itemid=', 'type' => 'alias', 'lft' => 12, 'params' => '{"aliasoptions":"111"}'] + $item,
            ['id' => 112, 'title' => 'Sections', 'alias' => 'sections', 'link' => 'index.php?option=com_content&view=categories&id=1', 'type' => 'component', 'lft' => 13, 'params' => '{"page_title":"0"}'] + $item,
            ['id' => 109, 'title' => 'Archive', 'alias' => 'archive', 'link' => 'index.php?option=com_content&view=archive', 'type' => 'component', 'lft' => 9] + $item,
            ['id' => 113, 'title' => 'Custom', 'alias' => 'custom', 'link' => 'index.php?option=com_content&view=archive', 'type' => 'custom', 'lft' => 14] + $item,
            ['id' => 201, 'title' => 'Shop', 'alias' => 'shop', 'link' => 'https://shop.example.com/', 'type' => 'url', 'lft' => 10, 'menutype' => 'footer'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $to = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->to;

        // the list of all categories is a page without content
        $topics = PageView::where( 'name', 'Topics' )->firstOrFail();
        $this->assertSame( ['topics', '', 'All topics'], [$topics->path, $topics->to, $topics->title] );
        $this->assertSame( $topics->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );
        // the root category is 1 in the database and 0 in most links
        $sections = PageView::where( 'name', 'Sections' )->firstOrFail();
        // page titles like "0" are used too
        $this->assertSame( ['sections', '', '0'], [$sections->path, $sections->to, $sections->title] );

        // alias menu items link to the pages of their targets
        $this->assertSame( ['/about-us', '/topics', '/', 'https://shop.example.com/'], array_map( $to, ['To about', 'To topics', 'To home', 'To shop'] ) );
        $this->assertSame( 2, PageView::where( 'name', 'To topics' )->firstOrFail()->status );
        $this->assertStringContainsString( "Skipped menu item: [108] To nothing (its target page isn't supported, its child items", $output );
        $this->assertStringContainsString( "Skipped menu item: [109] Archive (its Joomla view \"archive\" isn't supported, its child items", $output );
        // menu items of other types aren't views of the articles component even if their links are
        $this->assertStringContainsString( "Skipped menu item: [113] Custom (its type isn't supported, its child items", $output );
        // alias menu items to pages which aren't imported or to themselves are skipped
        $this->assertSame( 0, PageView::whereIn( 'name', ['To nothing', 'Archive', 'To archive', 'To itself'] )->count() );
    }


    public function testCategoryAndMenuAssociationsAreTranslations(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'language' => 'en-GB', 'parent_id' => 1, 'level' => 2, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'language' => 'de-DE', 'parent_id' => 1, 'level' => 2, 'lft' => 2],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['language' => 'en-GB'] + $this->menuItem( 101, 'Company', 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de'] + $this->menuItem( 102, 'Firma', 1, 1 ),
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 2, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'menu'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'menu'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        $news = PageView::where( 'name', 'News' )->firstOrFail();
        $company = PageView::where( 'name', 'Company' )->firstOrFail();

        $this->assertSame( 'nachrichten', PageView::language( 'de' )->findOrFail( $news->id )->path );
        $this->assertSame( 'Firma', PageView::language( 'de' )->findOrFail( $company->id )->name );
        $this->assertSame( 0, PageView::whereIn( 'name', ['Nachrichten', 'Firma'] )->count() );
    }


    public function testCategoryOptionsAndActiveMenuItem(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'categories', function( Blueprint $table ) {
            $table->text( 'description' )->nullable();
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'description' => '<p>All news</p>', 'params' => '{"image":"images/news.jpg"}'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2,
                'description' => '<p>Local news</p>', 'params' => '{"image":"images/local.jpg"}'],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Sport', 'alias' => 'sport', 'parent_id' => 1, 'level' => 1, 'lft' => 3,
                'description' => '<p>Sport news</p>', 'params' => '{"image":"images/sport.jpg"}'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 5, 'metadesc' => 'Own text', 'attribs' => '{"show_title":"0"}'] );
        $item = ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1, 'type' => 'component'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Blog', 'alias' => 'blog', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2',
                'params' => '{"show_description_image":"0","menu-meta_description":"Blog text","robots":"noindex"}'] + $item,
            ['id' => 102, 'title' => 'Sport', 'alias' => 'sport', 'lft' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5',
                'params' => '{"show_description":"0","show_category_title":"0","show_page_heading":"1","page_title":"Sport news","show_title":"1"}'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $types = fn( PageView $page ) => array_column( array_map( fn( $element ) => (array) $element, (array) $page->content ), 'type' );

        // the options of the menu item hide the image of the category and of the subcategories without menu item
        $blog = PageView::where( 'path', 'blog' )->firstOrFail();
        $this->assertSame( ['heading', 'html'], $types( $blog ) );
        $this->assertStringContainsString( 'All news', (string) json_encode( $blog->content ) );
        $local = PageView::where( 'name', 'Local' )->firstOrFail();
        $this->assertSame( 'blog/3-local', $local->path );
        $this->assertSame( ['heading', 'html'], $types( $local ) );
        $this->assertSame( 'Blog text', $local->meta->{'meta-tags'}->data->description ?? null );

        // the page heading is shown, the title and the description are hidden by the menu item
        $sport = PageView::where( 'path', 'sport' )->firstOrFail();
        $this->assertSame( ['heading', 'image'], $types( $sport ) );
        $this->assertSame( [1, 'Sport news'], [$sport->content[0]->data->level ?? null, $sport->content[0]->data->title ?? null] );
        $this->assertSame( ['images/sport.jpg'], File::pluck( 'path' )->all() );

        // articles without own menu item use the URL path and the meta data of the menu item of their category
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( ['blog/3-local/1-about', $local->id], [$about->path, $about->parent_id] );
        $this->assertSame( 'Blog text', $about->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( ['index' => 'noindex'], (array) ( $about->meta->robots->data ?? [] ) );
        $nur = PageView::where( 'name', 'Nur Deutsch' )->firstOrFail();
        $this->assertSame( 'sport/4-nur-deutsch', $nur->path );
        $this->assertSame( 'Own text', $nur->meta->{'meta-tags'}->data->description ?? null );
        // the options of the article are used before the ones of other menu items, the page heading of the menu item is shown
        $this->assertSame( ['heading', 'html'], $types( $nur ) );
        $this->assertSame( [1, 'Sport news'], [$nur->content[0]->data->level ?? null, $nur->content[0]->data->title ?? null] );
    }


    public function testArticleUrlsWithoutIds(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_ids":"1"}'] );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the "sef_ids" option of the articles component removes the IDs from the URL paths
        $this->assertSame( 'blog/nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
    }


    public function testImportsRedirects(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( [
            ['id' => 1, 'old_url' => 'https://example.com/index.php/old-about', 'new_url' => 'index.php?option=com_content&view=article&id=1', 'published' => 1],
            ['id' => 2, 'old_url' => '/old-shop', 'new_url' => 'https://shop.example.com/', 'published' => 1],
            ['id' => 3, 'old_url' => 'about', 'new_url' => 'somewhere', 'published' => 1],
            ['id' => 4, 'old_url' => 'search?q=old', 'new_url' => 'about', 'published' => 1],
            ['id' => 5, 'old_url' => 'gone', 'new_url' => 'index.php?option=com_content&view=article&id=99', 'published' => 1],
            ['id' => 6, 'old_url' => 'unpublished', 'new_url' => 'about', 'published' => 0],
            ['id' => 7, 'old_url' => 'old-shop', 'new_url' => 'other', 'published' => 1],
            ['id' => 8, 'old_url' => 'docs', 'new_url' => 'manual/', 'published' => 1],
            // URLs without scheme use the one of the page
            ['id' => 9, 'old_url' => '//Example.com/proto', 'new_url' => '//cdn.example.org/file', 'published' => 1],
            ['id' => 10, 'old_url' => '//other.example.net/elsewhere', 'new_url' => 'about', 'published' => 1],
            // relative targets of nested URLs are relative to their directory
            ['id' => 11, 'old_url' => 'guide/old', 'new_url' => 'new', 'published' => 1],
            // old URLs ending with a slash are directories like browsers resolve them
            ['id' => 13, 'old_url' => 'guide/dir/', 'new_url' => 'new', 'published' => 1],
            // numeric URL paths stay strings
            ['id' => 12, 'old_url' => '2024', 'new_url' => 'about', 'published' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $redirects = PageView::where( 'status', 2 )->orderBy( 'path' )->get();

        // the redirects are hidden link pages below the root page, the article without menu item has no URL path in Joomla, so the redirect is used
        $this->assertSame( [
            '2024' => '/about', 'about' => '/somewhere', 'docs' => '/manual/', 'guide/dir' => '/guide/dir/new', 'guide/old' => '/guide/new', 'old-about' => '/about-1',
            'old-shop' => 'https://shop.example.com/', 'proto' => 'https://cdn.example.org/file'
        ], $redirects->pluck( 'to', 'path' )->all() );
        $this->assertSame( '2024', $redirects->first()?->path );
        $this->assertSame( [$root->id], $redirects->pluck( 'parent_id' )->unique()->values()->all() );
        $this->assertSame( '', PageView::where( 'path', 'about-1' )->firstOrFail()->to );
        // redirects whose targets aren't imported are skipped by the URL assignment and don't reserve their URL paths
        $this->assertStringContainsString( 'Skipped redirect: /gone (its target index.php?option=com_content&view=article&id=99 isn\'t supported)', $output );
        $this->assertNull( PageView::where( 'path', 'gone' )->first() );
        $this->assertStringContainsString( 'Redirects: 8 imported, 0 updated, 0 unchanged (8/8 redirects processed).', $output );

        // the redirects of previous imports are updated, the pages using their URL paths are fetched at once
        $db->table( 'redirect_links' )->where( 'id', 2 )->update( ['new_url' => 'https://shop.example.org/'] );
        $cms = DB::connection( config( 'cms.db', 'sqlite' ) );
        $cms->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $paths = Collection::make( $cms->getQueryLog() )->pluck( 'bindings' )->filter( fn( $bindings ) => in_array( '/docs', $bindings, true ) );
        $cms->disableQueryLog();
        $this->assertSame( 1, $paths->count() );
        $this->assertContains( '/proto', $paths->first() );
        $this->assertStringContainsString( 'Redirects: 0 imported, 1 updated, 7 unchanged (8/8 redirects processed).', Artisan::output() );
        $this->assertSame( 'https://shop.example.org/', PageView::where( 'path', 'old-shop' )->sole()->to );
        $this->assertSame( 8, PageView::where( 'status', 2 )->count() );
    }


    public function testUnsupportedRedirectsOfPathsClaimedLaterAreOnlySkippedAsUsed(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( [
            // the article without menu item claims its alias after the redirects, so the redirect is skipped because its path is used
            ['id' => 1, 'old_url' => 'about', 'new_url' => 'javascript:alert(1)', 'published' => 1],
            ['id' => 2, 'old_url' => 'unused', 'new_url' => 'javascript:alert(1)', 'published' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringNotContainsString( 'Skipped redirect: /about ', $output );
        $this->assertStringContainsString( 'Skipped redirect: /unused (its target javascript:alert(1) isn\'t supported)', $output );
        $this->assertSame( '', PageView::where( 'path', 'about' )->sole()->to );
        $this->assertSame( 0, PageView::where( 'status', 2 )->count() );
    }


    public function testArticlesWithoutMenuItemDontTakeTheUrlsOfMenuItems(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( $this->menuItem( 101, 'About', 1, 1, 4 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // articles without menu item have no URL path in Joomla, so the menu item gets its alias
        $this->assertStringContainsString( 'Text', (string) json_encode( PageView::where( 'path', 'about' )->firstOrFail()->content ) );
        $this->assertStringContainsString( 'Hello', (string) json_encode( PageView::where( 'path', 'about-1' )->firstOrFail()->content ) );
    }


    public function testCategoriesWithoutImportedArticlesGetNoPagesAndLinks(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Company', 'alias' => 'company', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 2, 'level' => 2, 'lft' => 2],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Empty', 'alias' => 'empty', 'parent_id' => 2, 'level' => 2, 'lft' => 3],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3, 'introtext' => '<p><a href="index.php?option=com_content&amp;view=category&amp;id=5">Empty</a></p>'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // categories with imported articles get pages (top level ones don't), empty categories neither pages nor links
        $this->assertTrue( PageView::where( 'path', 'news' )->exists() );
        $this->assertFalse( PageView::where( 'name', 'Empty' )->exists() );
        $this->assertStringNotContainsString( 'href="/empty"', $json );
    }


    public function testLinksToCategoryListsAndMenuItemsOfOtherUrls(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'],
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Topics', 'alias' => 'topics', 'parent_id' => 1, 'level' => 1, 'lft' => 2,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=categories&id=0', 'params' => '{}'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>'
            . '<a href="index.php?option=com_content&amp;view=categories&amp;id=0">A</a> '
            . '<a href="index.php?option=com_content&amp;view=categories&amp;id=2">B</a> '
            . '<a href="index.php?option=com_content&amp;view=category&amp;layout=blog&amp;Itemid=101&amp;lang=en">C</a> '
            . '<a href="index.php?option=com_content&amp;view=category&amp;layout=list&amp;Itemid=101">D</a> '
            . '<a href="index.php?id=2&amp;Itemid=101">E</a> '
            . '<a href="//example.com/joomla/index.php?Itemid=102">F</a> '
            . '<a href="http://www.example.com/index.php?Itemid=101">G</a> '
            . '<a href="https://example.com/index.php?Itemid=101">H</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'www.example.com', '--file-base' => 'https://example.com/joomla/'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // links to category lists use their menu items or the pages of their categories, links with menu item ID only if the menu item shows the same
        $this->assertStringContainsString( '<a href="/topics">A</a> <a href="/blog">B</a> <a href="/blog">C</a> '
            . '<a href="index.php?option=com_content&amp;view=category&amp;layout=list&amp;Itemid=101">D</a> <a href="/blog">E</a> ', $json );
        // absolute links of the Joomla site with any scheme are internal links
        $this->assertStringContainsString( '<a href="/topics">F</a> <a href="/blog">G</a> <a href="https://example.com/index.php?Itemid=101">H</a>', $json );
    }


    public function testArticleMenuItemsOfTheSameLayoutDontReplaceEachOther(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            $this->menuItem( 101, 'First', 1, 1, 1 ),
            $this->menuItem( 102, 'Second', 1, 1, 1 ),
            $this->menuItem( 103, 'Default', 1, 1, 4 ),
            ['link' => 'index.php?option=com_content&view=article&layout=print&id=4'] + $this->menuItem( 104, 'Print', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like the menu rules of Joomla, the first menu item for all languages of a layout is used, the first one of another layout replaces it
        $this->assertStringContainsString( 'Hello', (string) json_encode( PageView::where( 'path', 'first' )->firstOrFail()->content ) );
        $this->assertStringContainsString( 'Text', (string) json_encode( PageView::where( 'path', 'print' )->firstOrFail()->content ) );
        $this->assertSame( 0, PageView::whereIn( 'path', ['second', 'default'] )->count() );
    }


    public function testArticleMenuItemsWithoutLayoutDontReplaceTheOnesWithLayout(): void
    {
        DB::connection( 'joomla' )->table( 'menu' )->insert( [
            ['link' => 'index.php?option=com_content&view=article&layout=print&id=1'] + $this->menuItem( 101, 'Print', 1, 1, 1 ),
            $this->menuItem( 102, 'Plain', 1, 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // later menu items for all languages without layout don't replace the first one of a layout
        $this->assertStringContainsString( 'Hello', (string) json_encode( PageView::where( 'path', 'print' )->firstOrFail()->content ) );
        $this->assertSame( 0, PageView::where( 'path', 'plain' )->count() );
    }


    public function testArticleMenuItemsInTheLanguageOfTheArticle(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'All', 'alias' => 'all', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => ''] );
        $db->table( 'menu' )->insert( [
            ['language' => '*', 'menutype' => 'footer'] + $this->menuItem( 101, 'Any', 1, 1, 1 ),
            ['language' => 'en-GB', 'menutype' => 'footer'] + $this->menuItem( 102, 'English', 1, 1, 1 ),
            ['language' => 'fr-FR', 'menutype' => 'footer'] + $this->menuItem( 103, 'French', 1, 1, 4 ),
            ['language' => 'de-DE', 'menutype' => 'footer'] + $this->menuItem( 104, 'German', 1, 1, 5 ),
            ['language' => 'en-GB', 'menutype' => 'footer'] + $this->menuItem( 105, 'Everyone', 1, 1, 5 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // without language filter, Joomla only uses the menu items for all languages
        $this->assertSame( 'any', PageView::where( 'name', 'Any' )->firstOrFail()->path );
        // the restricted parent category stops the route, the path of the article is used then
        $this->assertSame( 'Nur Deutsch', PageView::where( 'path', 'nur-deutsch' )->firstOrFail()->name );
        $this->assertSame( 'all', PageView::where( 'name', 'All' )->firstOrFail()->path );

        $this->extensionsTable();
        $this->languageFilter();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // menu items in the language of the article are used before the ones for all languages
        $this->assertSame( 'english', PageView::where( 'name', 'English' )->firstOrFail()->path );
        // articles for all languages use the menu items in the imported language
        $this->assertSame( 'everyone', PageView::where( 'name', 'Everyone' )->firstOrFail()->path );
    }


    /**
     * Creates the tables of the Joomla features used by the article pages.
     */
    public function testUseArticleOptionsOfCategoryMenuItems(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_title":"0"}'] );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->whereIn( 'id', [1, 4] )->update( ['catid' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['attribs' => '{"show_title":"1"}'] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{"show_title":"use_article"}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // options set to "use article" use the option of the article or of the articles component
        $this->assertStringContainsString( 'About', (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
        $this->assertStringNotContainsString( 'Nur Deutsch', (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );
    }


    public function testJoomla3LegacyRoutes(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 3 );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 3, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $db->table( 'menu' )->insert( ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'params' => '{}'] + $this->menuItem( 101, 'Home', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the legacy router of Joomla 3 uses the home menu item and adds the ID of the category to the first category only
        $this->assertSame( '3-news/local/4-nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
        $this->assertSame( '3-news/local', PageView::where( 'name', 'Local' )->firstOrFail()->path );

        // the modern router is used if enabled
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_advanced":"1","sef_ids":"1"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'path', 'nur-deutsch' )->count() );

        // the hidden "sef_advanced_link" option of the legacy router removes the IDs
        $db->table( 'extensions' )->where( 'element', 'com_content' )->update( ['params' => '{"sef_advanced_link":"1","sef_ids":"0"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'path', 'news/local/nur-deutsch' )->count() );
    }


    public function testUrlMenuItems(): void
    {
        $this->extraTables();
        $item = ['menutype' => 'mainmenu', 'type' => 'url', 'parent_id' => 1, 'level' => 1, 'params' => '{}'];
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Read', 'alias' => 'read', 'link' => 'index.php?option=com_content&view=article&id=1', 'lft' => 1] + $item,
            ['id' => 102, 'title' => 'Again', 'alias' => 'again', 'link' => '/index.php?Itemid=103', 'lft' => 2] + $item,
            ['id' => 103, 'title' => 'Text', 'alias' => 'text', 'link' => 'index.php?option=com_content&view=article&id=4', 'type' => 'component', 'lft' => 3] + $item,
            ['id' => 104, 'title' => 'Manual', 'alias' => 'manual', 'link' => 'docs/manual.pdf', 'lft' => 4] + $item,
            ['id' => 105, 'title' => 'More', 'alias' => 'more', 'link' => '#', 'lft' => 5] + $item,
            ['id' => 106, 'title' => 'About', 'alias' => 'about-us', 'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'parent_id' => 105, 'level' => 2, 'lft' => 6] + $item,
            ['id' => 107, 'title' => 'Empty', 'alias' => 'empty', 'link' => '', 'lft' => 7] + $item,
            ['id' => 108, 'title' => 'Anchor', 'alias' => 'anchor', 'link' => '#top', 'lft' => 8] + $item,
            ['id' => 109, 'title' => 'Self', 'alias' => 'self', 'link' => 'index.php?Itemid=109', 'lft' => 9] + $item,
            ['id' => 110, 'title' => 'Ping', 'alias' => 'ping', 'link' => 'index.php?Itemid=111', 'lft' => 10] + $item,
            ['id' => 111, 'title' => 'Pong', 'alias' => 'pong', 'link' => 'index.php?Itemid=110', 'lft' => 11] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // internal links are routed, relative links are relative to the site
        $this->assertSame( '/about-us', PageView::where( 'path', 'read' )->firstOrFail()->to );
        $this->assertSame( '/text', PageView::where( 'path', 'again' )->firstOrFail()->to );
        $this->assertSame( '/docs/manual.pdf', PageView::where( 'path', 'manual' )->firstOrFail()->to );
        // URL menu items without URL are folders of their child items
        $more = PageView::where( 'path', 'more' )->firstOrFail();
        $this->assertSame( '', $more->to );
        $this->assertSame( $more->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );
        $this->assertSame( '', PageView::where( 'path', 'empty' )->firstOrFail()->to );
        // anchors aren't supported
        $this->assertSame( 0, PageView::where( 'path', 'anchor' )->count() );
        // URL menu items linking to themselves directly or by other ones have no target and are skipped
        $this->assertSame( 0, PageView::whereIn( 'path', ['self', 'ping', 'pong'] )->count() );
        $this->assertStringContainsString( 'Skipped menu item: [111] Pong (its URL', Artisan::output() );
    }


    public function testArticleLinkTargets(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"targetb":"0","targetc":"2"}'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['urls' => '{"urla":"https://example.org/a","urlatext":"Link A","targeta":"1","urlb":"https://example.org/b","urlbtext":"Link B","targetb":"","urlc":"https://example.org/c"}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $text = implode( '', array_map( fn( $element ) => (string) ( $element->data->text ?? '' ), (array) PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // the links use their own texts and targets of the article or the default targets of the articles component, popups open in a new window
        $this->assertStringContainsString( '<a href="https://example.org/a" target="_blank" rel="noreferrer noopener">Link A</a>', $text );
        $this->assertStringContainsString( '<a href="https://example.org/b">Link B</a>', $text );
        $this->assertStringContainsString( '<a href="https://example.org/c" target="_blank" rel="noreferrer noopener">https://example.org/c</a>', $text );
    }


    public function testLastArticleMenuItemOfTheLanguageIsUsed(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $this->languageFilter();
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['language' => '*', 'menutype' => 'footer'] + $this->menuItem( 101, 'Any', 1, 1, 1 ),
            ['language' => '*', 'menutype' => 'footer'] + $this->menuItem( 102, 'Other', 1, 1, 1 ),
            ['language' => 'en-GB', 'menutype' => 'footer'] + $this->menuItem( 103, 'First', 1, 1, 1 ),
            ['language' => 'en-GB', 'menutype' => 'footer'] + $this->menuItem( 104, 'Last', 1, 1, 1 ),
            ['language' => '*', 'menutype' => 'footer'] + $this->menuItem( 105, 'Every', 1, 1, 4 ),
            ['language' => '*', 'menutype' => 'footer'] + $this->menuItem( 106, 'Later', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--lang' => 'en'] ) );

        // like Joomla, the last menu item in the language is used before the first one for all languages
        $this->assertSame( 'last', PageView::where( 'name', 'Last' )->firstOrFail()->path );
    }


    public function testCategoryMenuItemsOfOtherMenus(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->string( 'path' )->default( '' );
            $table->string( 'publish_down' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Events', 'alias' => 'events', 'parent_id' => 1, 'level' => 1, 'lft' => 3],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Tips', 'alias' => 'tips', 'parent_id' => 1, 'level' => 1, 'lft' => 4],
        ] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Event', 'alias' => 'event', 'language' => 'en-GB', 'introtext' => '<p>Event</p>', 'fulltext' => '', 'catid' => 4],
            ['id' => 6, 'title' => 'Tip', 'alias' => 'tip', 'language' => 'en-GB', 'introtext' => '<p>Tip</p>', 'fulltext' => '', 'catid' => 5],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'introtext' => '<p><a href="index.php?option=com_content&amp;view=category&amp;id=2">News</a></p>'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $item = ['menutype' => 'hiddenmenu', 'params' => '{}', 'publish_down' => null];
        $db->table( 'menu' )->insert( [
            ['params' => '{}', 'path' => 'company', 'publish_down' => null] + $this->menuItem( 101, 'Company', 1, 1 ),
            ['id' => 201, 'title' => 'Hidden', 'alias' => 'hidden', 'path' => 'hidden', 'link' => '', 'type' => 'heading', 'parent_id' => 1, 'level' => 1, 'lft' => 201] + $item,
            ['id' => 202, 'title' => 'News', 'alias' => 'news', 'path' => 'hidden/news', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2',
                'type' => 'component', 'parent_id' => 201, 'level' => 2, 'lft' => 202] + $item,
            // URL menu items aren't used by the Joomla router
            ['id' => 203, 'title' => 'Local', 'alias' => 'local-url', 'path' => 'local-url', 'link' => 'index.php?option=com_content&view=category&id=3',
                'type' => 'url', 'parent_id' => 1, 'level' => 1, 'lft' => 203] + $item,
            ['id' => 204, 'title' => 'All', 'alias' => 'all', 'path' => 'all', 'link' => 'index.php?option=com_content&view=categories&id=0',
                'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 204] + $item,
            // expired menu items aren't used either
            ['id' => 205, 'title' => 'Tips', 'alias' => 'tips', 'path' => 'tips', 'link' => 'index.php?option=com_content&view=category&id=5',
                'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 205] + ['publish_down' => '2000-01-01 00:00:00'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // Joomla uses the menu items of all menus for the URLs of the categories and articles
        $this->assertSame( 'hidden/news', PageView::where( 'name', 'News' )->firstOrFail()->path );
        $this->assertSame( 'hidden/news/3-local', PageView::where( 'name', 'Local' )->firstOrFail()->path );
        $this->assertSame( 'hidden/news/3-local/4-nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( ['hidden/news/1-about', PageView::where( 'name', 'News' )->firstOrFail()->id], [$about->path, $about->parent_id] );
        $this->assertStringContainsString( '<a href="/hidden/news">News</a>', stripslashes( (string) json_encode( $about->content ) ) );
        $this->assertSame( 0, PageView::where( 'path', 'local-url' )->count() );
        // the categories list is used for categories without menu item
        $this->assertSame( 'all/4-events/5-event', PageView::where( 'name', 'Event' )->firstOrFail()->path );
        $this->assertSame( 'all/5-tips/6-tip', PageView::where( 'name', 'Tip' )->firstOrFail()->path );
    }


    public function testJoomla3IgnoresThePublishingDatesOfMenuItems(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->string( 'publish_up' )->nullable();
            $table->string( 'publish_down' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 3 );
        $db->table( 'menu' )->insert( ['publish_down' => '2000-01-01 00:00:00'] + $this->menuItem( 101, 'Old', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'name', 'Old' )->value( 'status' ) );

        // Joomla 4+ doesn't show expired menu items
        $this->setJoomlaVersion( 4 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, PageView::where( 'name', 'Old' )->value( 'status' ) );
    }


    public function testLinksPositionOfTheArticleIsUsed(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Body</p>', 'urls' => '{"urla":"https://example.org/x","urlatext":"Link X","urls_position":"1"}'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '<p>Body</p>', 'urls' => '{"urla":"https://example.org/y","urlatext":"Link Y","urls_position":"0"}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $below = (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content );
        $above = (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content );
        $this->assertGreaterThan( strpos( $below, 'Body' ), strpos( $below, 'Link X' ) );
        $this->assertLessThan( strpos( $above, 'Body' ), strpos( $above, 'Link Y' ) );
    }


    public function testRelativeLinksWithSefPlugin(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="docs/manual.pdf">Manual</a></p>'] );
        $json = fn() => stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '<a href="docs/manual.pdf">Manual</a>', $json() );

        // the enabled "System - SEF" plugin makes the relative URLs absolute
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'sef', 'params' => '{}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '<a href="/docs/manual.pdf">Manual</a>', $json() );
    }


    public function testTranslationsUseMenuItemsOfTheirLanguage(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'language' => '*'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'language' => 'en-GB'],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Lokal', 'alias' => 'lokal', 'parent_id' => 2, 'level' => 2, 'lft' => 3, 'language' => 'de-DE'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 4] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'local'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'local'],
        ] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'language' => 'en-GB', 'lft' => 1] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Blog', 'alias' => 'blog-de', 'language' => 'de-DE', 'lft' => 201] + $item,
            ['id' => 301, 'menutype' => 'mainmenu-fr', 'title' => 'Blog', 'alias' => 'blog-fr', 'language' => 'fr-FR', 'lft' => 301] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the translations use the menu items of the categories in their language
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $local = PageView::where( 'name', 'Local' )->firstOrFail();
        $this->assertSame( ['blog/3-local/1-about', 'blog/3-local'], [$about->path, $local->path] );
        $this->assertSame( 'blog-de/4-lokal/2-ueber-uns', PageView::language( 'de' )->findOrFail( $about->id )->path );
        $this->assertSame( 'blog-de/4-lokal', PageView::language( 'de' )->findOrFail( $local->id )->path );
    }


    public function testTranslatedCategoryPathsDontConflict(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'language' => '*'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'language' => 'en-GB'],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Lokal', 'alias' => 'lokal', 'parent_id' => 2, 'level' => 2, 'lft' => 3, 'language' => 'de-DE'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 4] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'local'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'local'],
        ] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'language' => '*', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $item,
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Aktuell', 'alias' => 'aktuell', 'language' => '*', 'lft' => 2, 'link' => ''] + ['type' => 'heading'] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Aktuell', 'alias' => 'aktuell', 'language' => 'de-DE', 'lft' => 201, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=4'] + $item,
        ] );

        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_ids":"1"}'] );
        $db->table( 'categories' )->insert( ['id' => 5, 'extension' => 'com_content', 'title' => 'Lokal', 'alias' => 'lokal', 'parent_id' => 2, 'level' => 2, 'lft' => 4, 'language' => '*'] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => '*', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 5] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the path of the menu item of the translated category is already used by a page
        $this->assertSame( 'aktuell', PageView::where( 'name', 'Aktuell' )->firstOrFail()->path );
        $this->assertSame( 'aktuell-4', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'Local' )->firstOrFail()->id )->path );
        // the translated category doesn't claim its own path in the imported language
        $this->assertSame( 'blog/lokal', PageView::where( 'name', 'Lokal' )->firstOrFail()->path );
    }


    public function testJoomla4OnlyChecksTheCategoryOfArticlesWithMenuItem(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->unsignedInteger( 'access' )->default( 1 ) );
        $schema->table( 'content', fn( Blueprint $table ) => $table->string( 'metakey' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 4 );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2, 'published' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Public', 'alias' => 'public', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'access' => 1, 'published' => 1],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Old', 'alias' => 'old', 'parent_id' => 1, 'level' => 1, 'lft' => 3, 'access' => 1, 'published' => 0],
            ['id' => 6, 'extension' => 'com_content', 'title' => 'Current', 'alias' => 'current', 'parent_id' => 5, 'level' => 2, 'lft' => 4, 'access' => 1, 'published' => 1],
            ['id' => 7, 'extension' => 'com_content', 'title' => 'Private', 'alias' => 'private', 'parent_id' => 1, 'level' => 1, 'lft' => 5, 'access' => 2, 'published' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3, 'metadesc' => 'Text', 'metakey' => 'words'] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Inside', 'alias' => 'inside', 'language' => 'en-GB', 'introtext' => '<p>Inside</p>', 'fulltext' => '', 'catid' => 6],
            ['id' => 6, 'title' => 'Lost', 'alias' => 'lost', 'language' => 'en-GB', 'introtext' => '<p>Lost</p>', 'fulltext' => '', 'catid' => 6],
            ['id' => 7, 'title' => 'Secret', 'alias' => 'secret', 'language' => 'en-GB', 'introtext' => '<p>Secret</p>', 'fulltext' => '', 'catid' => 7],
        ] );
        $db->table( 'menu' )->insert( [
            ['params' => '{}'] + $this->menuItem( 101, 'Text', 1, 1, 4 ),
            ['params' => '{}', 'menutype' => 'othermenu'] + $this->menuItem( 102, 'Inside', 1, 1, 5 ),
            ['params' => '{}'] + $this->menuItem( 103, 'Secret', 1, 1, 7 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // Joomla 4+ only checks the category of articles with own menu item, articles without have no URL
        $this->assertSame( 1, PageView::where( 'name', 'Text' )->firstOrFail()->status );
        $inside = PageView::where( 'name', 'Inside' )->firstOrFail();
        // the categories of articles with own menu item of other menus aren't shown if their parents are hidden
        $this->assertSame( [1, 'inside', PageView::where( 'name', 'Text' )->firstOrFail()->parent_id], [$inside->status, $inside->path, $inside->parent_id] );
        $this->assertSame( 0, PageView::where( 'name', 'Lost' )->count() );
        $this->assertSame( 0, PageView::whereIn( 'name', ['Old', 'Current'] )->count() );
        $this->assertStringNotContainsString( 'Disabled: Nur Deutsch', $output );
        $this->assertStringContainsString( 'Disabled: Secret (/secret) (its category Private is restricted to Joomla access level 2)', $output );
        // Joomla 4+ doesn't show the meta keywords
        $meta = PageView::where( 'name', 'Text' )->firstOrFail()->meta;
        $this->assertSame( ['Text', null], [$meta->{'meta-tags'}->data->description ?? null, $meta->{'meta-tags'}->data->keywords ?? null] );

        // Joomla 3 also only checks the category of articles with own menu item but shows the meta keywords
        $this->setJoomlaVersion( 3 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringNotContainsString( 'Disabled: Nur Deutsch', $output );
        $this->assertStringContainsString( 'Unchanged: Inside (/inside)', $output );
        $this->assertSame( 'words', PageView::where( 'name', 'Text' )->firstOrFail()->meta->{'meta-tags'}->data->keywords ?? null );
    }


    public function testFirstCategoryMenuItemOfTheImportedMenuShowsTheCategory(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];
        $db->table( 'menu' )->insert( [
            ['id' => 201, 'menutype' => 'othermenu', 'title' => 'Other', 'alias' => 'other', 'lft' => 1] + $item,
            ['id' => 103, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'lft' => 5] + $item,
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Second', 'alias' => 'second', 'lft' => 6] + $item,
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?option=com_content&amp;view=category&amp;id=2">C</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the articles are imported below the page of the first menu item of the imported menu, the links point to it
        $blog = PageView::where( 'path', 'blog' )->firstOrFail();
        $this->assertSame( $blog->id, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->parent_id );
        $this->assertSame( 0, PageView::whereIn( 'name', ['News', 'Other'] )->count() );
        $this->assertStringContainsString( '<a href="/blog">C</a>', stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ) );
    }


    public function testComponentOptionsOverrideTheArticleForItsMenuItem(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"show_title":"0"}'] );
        $db->table( 'content' )->whereIn( 'id', [1, 4] )->update( ['attribs' => '{"show_title":"1"}'] );
        $db->table( 'menu' )->insert( ['params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // Joomla uses the options of the component before the ones of the article if the menu item links to the article
        $this->assertSame( 'Welcome', PageView::where( 'path', 'start' )->firstOrFail()->content[0]->data->title ?? null );
        $this->assertSame( 'Nur Deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content[0]->data->title ?? null );
    }


    public function testSuffixOptionRemovesTheSuffixOfRedirects(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( [
            ['id' => 1, 'old_url' => 'https://example.com/index.php/old-about.html', 'new_url' => 'about.html', 'published' => 1],
            ['id' => 2, 'old_url' => 'docs.html', 'new_url' => 'index.php/manual.html#top', 'published' => 1],
            ['id' => 3, 'old_url' => 'about.html', 'new_url' => 'somewhere', 'published' => 1],
            ['id' => 4, 'old_url' => 'guide/old.html', 'new_url' => 'new.html', 'published' => 1],
            ['id' => 5, 'old_url' => 'guide/start.html', 'new_url' => 'index.php/start.html', 'published' => 1],
            ['id' => 6, 'old_url' => '/root.html', 'new_url' => '/index.php/top.html', 'published' => 1],
            ['id' => 7, 'old_url' => 'site.html', 'new_url' => 'https://EXAMPLE.com/index.php?option=com_content&view=article&id=1', 'published' => 1],
            ['id' => 8, 'old_url' => 'https://other.example.org/foreign.html', 'new_url' => 'about.html', 'published' => 1],
            ['id' => 9, 'old_url' => 'away.html', 'new_url' => 'https://other.example.org/index.php/page.html', 'published' => 1],
            ['id' => 10, 'old_url' => 'guide/abs.html', 'new_url' => '/new.html', 'published' => 1],
            // the suffix is removed from the path before the query string and the fragment
            ['id' => 11, 'old_url' => 'guide/query.html', 'new_url' => 'page.html?x=1', 'published' => 1],
            ['id' => 12, 'old_url' => 'scripted.html', 'new_url' => 'index.php/page.html?x=1#a', 'published' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--suffix' => true] ) );

        // redirects from the URLs of other domains are skipped, relative new URLs are relative to the old ones
        $this->assertSame( [
            'about' => '/somewhere', 'away' => 'https://other.example.org/index.php/page.html', 'docs' => '/manual#top', 'guide/abs' => '/new', 'guide/old' => '/guide/new',
            'guide/query' => '/guide/page?x=1', 'guide/start' => '/start', 'old-about' => '/about', 'root' => '/top', 'scripted' => '/page?x=1#a', 'site' => '/about-1',
        ], PageView::where( 'status', 2 )->orderBy( 'path' )->pluck( 'to', 'path' )->all() );
    }


    public function testWithoutSuffixOptionRedirectsKeepTheSuffix(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( ['id' => 1, 'old_url' => 'old.html', 'new_url' => 'index.php/new.html', 'published' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        // the script name is removed always, the suffix only with the suffix option
        $this->assertSame( ['old.html' => '/new.html'], PageView::where( 'status', 2 )->pluck( 'to', 'path' )->all() );
    }


    public function testTranslationsUseTheMenuItemOfTheirCategoryInTheirLanguage(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->whereIn( 'id', [1, 2] )->update( ['catid' => 2, 'introtext' => '<p>{loadposition p}</p>'] );
        $schema->create( 'modules_menu', function( Blueprint $table ) {
            $table->unsignedInteger( 'moduleid' );
            $table->integer( 'menuid' );
        } );
        $db->table( 'modules' )->insert( ['id' => 1, 'title' => 'German', 'module' => 'mod_custom', 'position' => 'p', 'content' => '<p>German blog</p>', 'ordering' => 1] );
        $db->table( 'modules_menu' )->insert( ['moduleid' => 1, 'menuid' => 201] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'language' => 'en-GB', 'lft' => 1, 'params' => '{"menu-meta_description":"English"}'] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Blog', 'alias' => 'blog-de', 'language' => 'de-DE', 'lft' => 2, 'params' => '{"menu-meta_description":"Deutsch"}'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( 'English', $about->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'Deutsch', PageView::language( 'de' )->findOrFail( $about->id )->meta->{'meta-tags'}->data->description ?? null );
        // modules are selected by the menu item of the category in the language of the translation
        $this->assertStringNotContainsString( 'German blog', (string) json_encode( $about->content ) );
        $this->assertStringContainsString( 'German blog', (string) json_encode( PageView::language( 'de' )->findOrFail( $about->id )->content ) );
    }


    public function testJoomla3UsesMenuItemsForAllLanguagesForContentForAllLanguages(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'All', 'alias' => 'all', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => '', 'catid' => 2] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'othermenu', 'title' => 'Blog', 'alias' => 'blog', 'language' => '*', 'lft' => 1] + $item,
            ['id' => 102, 'menutype' => 'othermenu', 'title' => 'English', 'alias' => 'english', 'language' => 'en-GB', 'lft' => 2] + $item,
            ['id' => 103, 'menutype' => 'othermenu', 'title' => 'Own', 'alias' => 'own', 'language' => 'en-GB', 'lft' => 3,
                'link' => 'index.php?option=com_content&view=article&id=5'] + $item,
        ] );

        $db->table( 'content' )->insert( ['id' => 6, 'title' => 'Other', 'alias' => 'other', 'language' => '*', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Own', PageView::where( 'path', 'own' )->firstOrFail()->name );
        // Joomla 4+ uses the menu items in the imported language for the articles for all languages
        $this->assertSame( 'english/6-other', PageView::where( 'name', 'Other' )->firstOrFail()->path );

        // Joomla 3 only uses the menu items for all languages for the articles for all languages
        $this->setJoomlaVersion( 3 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'blog/5-all', PageView::where( 'name', 'All' )->firstOrFail()->path );
    }


    public function testArticleMenuItemsOfAllMenusAreUsedByPosition(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['params' => '{}', 'lft' => 10] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['params' => '{}', 'lft' => 1, 'menutype' => 'othermenu'] + $this->menuItem( 201, 'First', 1, 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // Joomla uses the first menu item of all menus for the URL
        $this->assertSame( ['first', 'First'], [PageView::where( 'path', 'first' )->firstOrFail()->path, PageView::where( 'path', 'first' )->firstOrFail()->name] );
    }


    public function testLinksToCategoryMenuItemsOfOtherMenus(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 201, 'menutype' => 'othermenu', 'title' => 'Other', 'alias' => 'other', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'params' => '{}', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=201">C</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the links to menu items of categories point to the pages of the categories
        $this->assertSame( 'other', PageView::where( 'name', 'News' )->firstOrFail()->path );
        $this->assertStringContainsString( '<a href="/other">C</a>', stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ) );
    }


    public function testFeaturedLinksOfTranslationsUseTheMenuItemsOfTheirLanguage(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $featured = ['link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'params' => '{}', 'home' => 1];
        $db->table( 'menu' )->insert( [
            ['language' => 'en-GB'] + $featured + $this->menuItem( 101, 'Start', 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de', 'lft' => 1] + $featured + $this->menuItem( 201, 'Startseite', 1, 1 ),
            ['language' => 'en-GB', 'params' => '{}', 'home' => 0] + $this->menuItem( 102, 'Team', 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de', 'lft' => 2, 'params' => '{}', 'home' => 0] + $this->menuItem( 202, 'Mannschaft', 1, 1 ),
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'team'],
            ['id' => 202, 'context' => 'com_menus.item', 'key' => 'team'],
        ] );
        $link = '<p><a href="index.php?option=com_content&amp;view=featured">Featured</a> <a href="index.php?Itemid=202">Team</a></p>';
        $db->table( 'content' )->whereIn( 'id', [1, 2] )->update( ['introtext' => $link] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // like Joomla, links to the featured articles use the menu item of the language of the article or translation
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $json = stripslashes( (string) json_encode( $page->content ) );
        $this->assertStringContainsString( '<a href="/">Featured</a>', $json );
        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $deJson = stripslashes( (string) json_encode( $de->content ) );
        $this->assertStringContainsString( '<a href="/de">Featured</a>', $deJson );
        // links to translated menu items use the variant of the page of the translated menu item
        $team = PageView::where( 'name', 'Team' )->firstOrFail();
        $variant = PageView::language( 'de' )->findOrFail( $team->id );
        $this->assertSame( 'mannschaft', $variant->path );
        $this->assertStringContainsString( '<a href="/mannschaft">Team</a>', $json );
        $this->assertStringContainsString( '<a href="/mannschaft">Team</a>', $deJson );
    }


    public function testChildrenOfOtherMenuItemsOfTheSameArticleAreBelowItsPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 100, 'Start', 1, 1, 4 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 101, 'First', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 102, 'Second', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 103, 'Child', 102, 2 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 104, 'Folder', 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 105, 'Home', 104, 2, 4 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 106, 'Below', 105, 3 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'First' )->firstOrFail();
        $this->assertSame( $page->id, PageView::where( 'name', 'Child' )->firstOrFail()->parent_id );
        $this->assertSame( PageView::where( 'tag', 'root' )->firstOrFail()->id, PageView::where( 'name', 'Below' )->firstOrFail()->parent_id );
    }


    public function testCategoriesGuestsCantSeeArentPartOfTheRoutes(): void
    {
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( ['id' => 2, 'title' => 'Registered', 'rules' => '[2]'] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'access' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $db->table( 'menu' )->insert( ['link' => 'index.php?option=com_content&view=categories&id=0', 'type' => 'component'] + $this->menuItem( 101, 'All', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        // the restricted parent category stops the route, the path of the article is used then
        $this->assertSame( 'Nur Deutsch', PageView::where( 'path', 'nur-deutsch' )->firstOrFail()->name );
    }


    public function testTranslationsUseTheMenuItemsForAllLanguages(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->extraTables();
        $this->languageFilter();
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->whereIn( 'id', [1, 2] )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'language' => '*', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( 'blog/2-ueber-uns', PageView::language( 'de' )->findOrFail( $about->id )->path );
    }


    public function testPageHeadingOptionOfTheMenusComponent(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_menus', 'params' => '{"show_page_heading":"1"}'] );
        $db->table( 'menu' )->insert( ['params' => '{"page_heading":"Our company"}'] + $this->menuItem( 101, 'Company', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Our company', (string) json_encode( PageView::where( 'path', 'company' )->firstOrFail()->content ) );
    }


    public function testMenuItemPathsAreUsedBeforeTheRoutesOfArticles(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'path' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'News', 'alias' => 'news', 'path' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
                'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'],
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Archive', 'alias' => '4-nur-deutsch', 'path' => 'news/4-nur-deutsch', 'parent_id' => 101, 'level' => 2, 'lft' => 2,
                'type' => 'heading', 'link' => '', 'params' => '{}'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like the Joomla router, the URL paths of the menu items win over the ones of the articles
        $this->assertSame( 'news/4-nur-deutsch', PageView::where( 'name', 'Archive' )->firstOrFail()->path );
        $this->assertSame( 'news/4-nur-deutsch-4', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
    }


    public function testLinksToCategoriesUseTheirCategoryListMenuItems(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?option=com_content&amp;view=category&amp;id=2">A</a> '
            . '<a href="index.php?option=com_content&amp;view=category&amp;id=3">B</a></p>'] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Topics', 'alias' => 'topics', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=categories&id=2', 'params' => '{}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // Joomla uses the menu item listing the subcategories for the category too
        $this->assertStringContainsString( '<a href="/topics">A</a> <a href="/topics/3-local">B</a>', $json );
        $this->assertSame( 'topics/3-local/4-nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
    }


    public function testCategoryMenuItemsCheckTheCategoryAndItsParents(): void
    {
        $this->accessColumns();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2, 'published' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'access' => 1, 'published' => 1],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Old', 'alias' => 'old', 'parent_id' => 1, 'level' => 1, 'lft' => 3, 'access' => 1, 'published' => 2],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Secret', 'alias' => 'secret', 'parent_id' => 1, 'level' => 1, 'lft' => 4, 'access' => 2, 'published' => 1],
            ['id' => 6, 'extension' => 'com_content', 'title' => 'Events', 'alias' => 'events', 'parent_id' => 2, 'level' => 2, 'lft' => 5, 'access' => 1, 'published' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 6] );
        $item = ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'home' => 0];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5'] + $item,
            ['id' => 102, 'title' => 'Local news', 'alias' => 'local-news', 'lft' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'] + $item,
            ['id' => 103, 'title' => 'Archive', 'alias' => 'archive', 'lft' => 3, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=4'] + $item,
            ['id' => 104, 'title' => 'Calendar', 'alias' => 'calendar', 'lft' => 4, 'menutype' => 'othermenu', 'params' => '{"page_title":"Upcoming events"}',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=6'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // like Joomla, category menu items below restricted or archived parent categories aren't shown
        $this->assertSame( 0, PageView::where( 'name', 'Local news' )->firstOrFail()->status );
        $this->assertStringContainsString( 'Disabled: Local news (/local-news) (its category Members is restricted to Joomla access level Registered)', $output );
        $this->assertSame( 0, PageView::where( 'name', 'Archive' )->firstOrFail()->status );
        $this->assertStringContainsString( 'the category Old is archived', $output );
        // the same applies to the category pages of the category menu items of other menus, which use their options
        $events = PageView::where( 'name', 'Events' )->firstOrFail();
        $this->assertSame( ['calendar', 0, 'Upcoming events'], [$events->path, $events->status, $events->title] );

        // the home menu item of a restricted category doesn't use the root page
        $this->assertSame( 0, PageView::where( 'name', 'Start' )->firstOrFail()->status );
        $this->assertStringNotContainsString( 'Secret', (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) );
    }


    public function testCategoryWithMenuItemBelowRestrictedParentIsRestrictedLikeTheParent(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->text( 'params' )->nullable() );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $category = ['extension' => 'com_content', 'access' => 1, 'published' => 1];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2] + $category,
            ['id' => 3, 'title' => 'Reports', 'alias' => 'reports', 'parent_id' => 2, 'level' => 2, 'lft' => 2] + $category,
            ['id' => 4, 'title' => 'Yearly', 'alias' => 'yearly', 'parent_id' => 3, 'level' => 3, 'lft' => 3] + $category,
            ['id' => 5, 'title' => 'Open', 'alias' => 'open', 'parent_id' => 1, 'level' => 1, 'lft' => 4] + $category,
        ] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Report', 'alias' => 'report', 'language' => 'en-GB', 'introtext' => '<p>Report</p>', 'fulltext' => '', 'catid' => 3],
            ['id' => 6, 'title' => 'Year', 'alias' => 'year', 'language' => 'en-GB', 'introtext' => '<p>Year</p>', 'fulltext' => '', 'catid' => 4],
            ['id' => 7, 'title' => 'Welcome', 'alias' => 'welcome', 'language' => 'en-GB', 'introtext' => '<p>Welcome</p>', 'fulltext' => '', 'catid' => 5],
        ] );
        $item = ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Reports', 'alias' => 'reports', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'] + $item,
            ['id' => 102, 'title' => 'Open', 'alias' => 'open', 'lft' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5'] + $item,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $reason = '(its category Members is restricted to Joomla access level Registered)';

        // like Joomla, the own menu item doesn't make the category public if its parent is restricted
        $this->assertSame( 0, PageView::where( 'name', 'Reports' )->firstOrFail()->status );
        $this->assertStringContainsString( "Disabled: Reports (/reports) {$reason}", $output );
        // its articles and subcategories are restricted like the parent too
        $this->assertSame( 0, PageView::where( 'name', 'Report' )->firstOrFail()->status );
        $this->assertStringContainsString( "Disabled: Report (/report) {$reason}", $output );
        $this->assertSame( 0, PageView::where( 'name', 'Year' )->firstOrFail()->status );
        // categories with menu item below public parents stay public
        $this->assertSame( 1, PageView::where( 'name', 'Open' )->firstOrFail()->status );
        $this->assertSame( 1, PageView::where( 'name', 'Welcome' )->firstOrFail()->status );
    }


    public function testTranslatedCategoryMenuItemsCheckTheCategoryAndItsParents(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $category = ['extension' => 'com_content', 'parent_id' => 1, 'level' => 1, 'access' => 1, 'published' => 1];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'language' => 'en-GB'] + $category,
            ['id' => 3, 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'lft' => 2, 'language' => 'de-DE', 'published' => 0] + $category,
            ['id' => 4, 'title' => 'Events', 'alias' => 'events', 'lft' => 3, 'language' => 'en-GB'] + $category,
            ['id' => 5, 'title' => 'Termine', 'alias' => 'termine', 'lft' => 4, 'language' => 'de-DE', 'access' => 2] + $category,
            ['id' => 6, 'title' => 'Members', 'alias' => 'members', 'lft' => 5, 'language' => '*', 'access' => 2] + $category,
            ['id' => 7, 'title' => 'Shop', 'alias' => 'shop', 'lft' => 6, 'language' => 'en-GB'] + $category,
            ['id' => 8, 'title' => 'Laden', 'alias' => 'laden', 'lft' => 7, 'language' => 'de-DE', 'parent_id' => 6, 'level' => 2] + $category,
            ['id' => 9, 'title' => 'Jobs', 'alias' => 'jobs', 'lft' => 8, 'language' => 'en-GB', 'access' => 2] + $category,
            ['id' => 10, 'title' => 'Stellen', 'alias' => 'stellen', 'lft' => 9, 'language' => 'de-DE'] + $category,
        ] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=category&id=2'] + $item,
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Events', 'alias' => 'events', 'lft' => 2, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=category&id=4'] + $item,
            ['id' => 103, 'menutype' => 'mainmenu', 'title' => 'Shop', 'alias' => 'shop', 'lft' => 3, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=category&id=7'] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'lft' => 4, 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&id=3'] + $item,
            ['id' => 202, 'menutype' => 'mainmenu-de', 'title' => 'Termine', 'alias' => 'termine', 'lft' => 5, 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&id=5'] + $item,
            ['id' => 203, 'menutype' => 'mainmenu-de', 'title' => 'Laden', 'alias' => 'laden', 'lft' => 6, 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&id=8'] + $item,
            ['id' => 104, 'menutype' => 'mainmenu', 'title' => 'Jobs', 'alias' => 'jobs', 'lft' => 7, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=category&id=9'] + $item,
            ['id' => 204, 'menutype' => 'mainmenu-de', 'title' => 'Stellen', 'alias' => 'stellen', 'lft' => 8, 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&id=10'] + $item,
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'news'],
            ['id' => 201, 'context' => 'com_menus.item', 'key' => 'news'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'events'],
            ['id' => 202, 'context' => 'com_menus.item', 'key' => 'events'],
            ['id' => 103, 'context' => 'com_menus.item', 'key' => 'shop'],
            ['id' => 203, 'context' => 'com_menus.item', 'key' => 'shop'],
            ['id' => 104, 'context' => 'com_menus.item', 'key' => 'jobs'],
            ['id' => 204, 'context' => 'com_menus.item', 'key' => 'jobs'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $de = fn( string $name ) => PageView::language( 'de' )->find( PageView::where( 'name', $name )->firstOrFail()->id );

        // menu items of unpublished categories aren't shown, the ones of restricted categories and below restricted parents are disabled
        $this->assertNull( $de( 'News' ) );
        $this->assertSame( 0, $de( 'Events' )?->status );
        $this->assertStringContainsString( 'Disabled translation: Termine (/termine) [de] (its category Termine is restricted to Joomla access level Registered)', $output );
        $this->assertSame( 0, $de( 'Shop' )?->status );
        $this->assertStringContainsString( 'Disabled translation: Laden (/laden) [de] (its category Members is restricted to Joomla access level Registered)', $output );
        // menu items show the category in their language
        $this->assertNotNull( $shop = $de( 'Shop' ) );
        $this->assertStringContainsString( 'Laden', (string) json_encode( $shop->content ) );
        // translations of disabled pages are disabled too
        $this->assertSame( 0, $de( 'Jobs' )?->status );
    }


    public function testTranslationsOfLinkMenuItemsAndSubcategories(): void
    {
        $this->extraTables();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->unsignedInteger( 'access' )->default( 1 );
        } );
        $schema->table( 'categories', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->text( 'description' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $category = ['extension' => 'com_content', 'parent_id' => 1, 'level' => 1, 'published' => 1, 'description' => ''];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'language' => 'en-GB'] + $category,
            ['id' => 3, 'title' => 'Local', 'alias' => 'local', 'lft' => 2, 'language' => 'en-GB', 'parent_id' => 2, 'level' => 2] + $category,
            ['id' => 4, 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'lft' => 3, 'language' => 'de-DE'] + $category,
            ['id' => 5, 'title' => 'Lokal', 'alias' => 'lokal', 'lft' => 4, 'language' => 'de-DE', 'parent_id' => 4, 'level' => 2, 'description' => '<p>Lokaltext</p>'] + $category,
            ['id' => 6, 'title' => 'Old', 'alias' => 'old', 'lft' => 5, 'language' => 'en-GB', 'parent_id' => 2, 'level' => 2, 'published' => 2] + $category,
            ['id' => 7, 'title' => 'Alt', 'alias' => 'alt', 'lft' => 6, 'language' => 'de-DE', 'parent_id' => 4, 'level' => 2] + $category,
            ['id' => 8, 'title' => 'Areas', 'alias' => 'areas', 'lft' => 7, 'language' => 'en-GB'] + $category,
            ['id' => 9, 'title' => 'Bereiche', 'alias' => 'bereiche', 'lft' => 8, 'language' => 'de-DE', 'description' => '<p>Bereichtext</p>'] + $category,
        ] );
        $article = ['language' => 'en-GB', 'introtext' => '<p>Text</p>', 'fulltext' => ''];
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Story', 'alias' => 'story', 'catid' => 3] + $article,
            ['id' => 6, 'title' => 'Memo', 'alias' => 'memo', 'catid' => 6] + $article,
        ] );
        $item = ['parent_id' => 1, 'level' => 1, 'params' => '{}'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'language' => 'en-GB', 'type' => 'component',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'lft' => 2, 'language' => 'de-DE', 'type' => 'component',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=4', 'params' => '{"show_description":"0"}'] + $item,
            ['id' => 105, 'menutype' => 'mainmenu', 'title' => 'Shop', 'alias' => 'shop', 'lft' => 3, 'language' => 'en-GB', 'type' => 'url', 'link' => 'https://example.org/en'] + $item,
            ['id' => 205, 'menutype' => 'mainmenu-de', 'title' => 'Laden', 'alias' => 'laden', 'lft' => 4, 'language' => 'de-DE', 'type' => 'url', 'link' => 'https://example.org/de'] + $item,
            ['id' => 106, 'menutype' => 'mainmenu', 'title' => 'Help', 'alias' => 'help', 'lft' => 5, 'language' => 'en-GB', 'type' => 'url', 'link' => 'https://example.org/help'] + $item,
            ['id' => 206, 'menutype' => 'mainmenu-de', 'title' => 'Hilfe', 'alias' => 'hilfe', 'lft' => 6, 'language' => 'de-DE', 'type' => 'url', 'link' => 'javascript:alert(1)'] + $item,
            ['id' => 107, 'menutype' => 'mainmenu', 'title' => 'Topics', 'alias' => 'topics', 'lft' => 7, 'language' => 'en-GB', 'type' => 'heading', 'link' => ''] + $item,
            ['id' => 207, 'menutype' => 'mainmenu-de', 'title' => 'Themen', 'alias' => 'themen', 'lft' => 8, 'language' => 'de-DE', 'type' => 'url', 'link' => '#'] + $item,
            ['id' => 108, 'menutype' => 'mainmenu', 'title' => 'Areas', 'alias' => 'areas', 'lft' => 9, 'language' => 'en-GB', 'type' => 'component',
                'link' => 'index.php?option=com_content&view=categories&id=8'] + $item,
            ['id' => 208, 'menutype' => 'mainmenu-de', 'title' => 'Bereiche', 'alias' => 'bereiche', 'lft' => 10, 'language' => 'de-DE', 'type' => 'component',
                'link' => 'index.php?option=com_content&view=categories&id=9'] + $item,
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'local'],
            ['id' => 5, 'context' => 'com_categories.item', 'key' => 'local'],
            ['id' => 6, 'context' => 'com_categories.item', 'key' => 'old'],
            ['id' => 7, 'context' => 'com_categories.item', 'key' => 'old'],
            ['id' => 105, 'context' => 'com_menus.item', 'key' => 'shop'],
            ['id' => 205, 'context' => 'com_menus.item', 'key' => 'shop'],
            ['id' => 106, 'context' => 'com_menus.item', 'key' => 'help'],
            ['id' => 206, 'context' => 'com_menus.item', 'key' => 'help'],
            ['id' => 107, 'context' => 'com_menus.item', 'key' => 'topics'],
            ['id' => 207, 'context' => 'com_menus.item', 'key' => 'topics'],
            ['id' => 108, 'context' => 'com_menus.item', 'key' => 'areas'],
            ['id' => 208, 'context' => 'com_menus.item', 'key' => 'areas'],
        ] );
        $db->table( 'menu' )->where( 'id', 205 )->update( ['access' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $de = fn( string $name ) => PageView::language( 'de' )->find( PageView::where( 'name', $name )->firstOrFail()->id );

        // link translations use their own target, unsupported targets are skipped and URL items without target are folders
        $this->assertSame( 'https://example.org/de', $de( 'Shop' )?->to );
        // like the link page in the import language, link translations have no content
        $this->assertEquals( [], $de( 'Shop' )?->content );
        // link translations guests can't see are disabled
        $this->assertSame( 0, $de( 'Shop' )?->status );
        $this->assertNull( $de( 'Help' ) );
        $this->assertSame( 'Themen', $de( 'Topics' )?->name );
        // like the page itself, translations of menu items listing the subcategories show the category
        $this->assertStringContainsString( 'Bereichtext', (string) json_encode( $de( 'Areas' )?->content ) );
        // subcategories without menu item use the options of the menu item of their parent category in their language
        $this->assertNotNull( $local = $de( 'Local' ) );
        $this->assertStringNotContainsString( 'Lokaltext', (string) json_encode( $local->content ) );
        // translations of disabled category pages are disabled too
        $this->assertSame( 0, $de( 'Old' )?->status );
    }


    public function testJoomla4UsesTheMenuItemTitleForArticles(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 4 );
        $db->table( 'content' )->where( 'id', 1 )->update( ['attribs' => '{"article_page_title":"About page"}'] );
        $db->table( 'menu' )->insert( ['params' => '{}'] + $this->menuItem( 101, 'Company', 1, 1, 1 ) );
        $title = fn() => PageView::where( 'path', 'company' )->firstOrFail()->title;

        // Joomla 3 and 4 use the title of the menu item linking to the article if it has no browser title
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Company', $title() );

        // Joomla 5 uses the browser title of the article then
        $this->setJoomlaVersion( 5 );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'About page', $title() );
    }


    public function testTranslatedCategoriesWithMenuItemBelowRestrictedParentsAreDisabled(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $category = ['extension' => 'com_content', 'level' => 1, 'access' => 1, 'published' => 1];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'Members area', 'alias' => 'members-area', 'parent_id' => 1, 'lft' => 1, 'access' => 2, 'language' => '*'] + $category,
            ['id' => 3, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'lft' => 2, 'level' => 2, 'language' => 'en-GB'] + $category,
            ['id' => 4, 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'parent_id' => 2, 'lft' => 3, 'level' => 2, 'language' => 'de-DE'] + $category,
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 3] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'news'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'news'],
        ] );
        $db->table( 'menu' )->insert( ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'language' => 'de-DE', 'lft' => 1,
            'parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'link' => 'index.php?option=com_content&view=category&id=4'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // like Joomla, translated categories below restricted parents are disabled also if a menu item links to them
        $this->assertStringContainsString( 'Disabled translation: Nachrichten (/nachrichten) [de] (its category Members area is restricted to Joomla access level Registered)', Artisan::output() );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'News' )->firstOrFail()->id )->status );
    }


    public function testLinksToFeaturedArticlesAndTheArchive(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $item = ['type' => 'component', 'params' => '{}', 'home' => 0];
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'link' => 'index.php?option=com_content&view=featured'] + $item + $this->menuItem( 101, 'Home', 1, 1 ),
            ['link' => 'index.php?option=com_content&view=archive'] + $item + $this->menuItem( 102, 'Archive', 1, 1 ),
            ['link' => 'index.php?option=com_content&view=featured&layout=list'] + $item + $this->menuItem( 103, 'List', 1, 1 ),
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?option=com_content&amp;view=featured">A</a> '
            . '<a href="index.php?option=com_content&amp;view=featured&amp;layout=blog">B</a> <a href="index.php?option=com_content&amp;view=archive">C</a> '
            . '<a href="index.php?option=com_content&amp;view=featured&amp;layout=list">D</a> <a href="index.php?option=com_content&amp;view=featured&amp;Itemid=102">E</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );

        // like Joomla, links without menu item use the menu item of the view, the archive and list menu items have no page
        // and links with menu item are only replaced if the menu item shows the same view
        $this->assertStringContainsString( '<a href="/">A</a> <a href="/">B</a> <a href="index.php?option=com_content&amp;view=archive">C</a> '
            . '<a href="index.php?option=com_content&amp;view=featured&amp;layout=list">D</a> <a href="index.php?option=com_content&amp;view=featured&amp;Itemid=102">E</a>', $json );

        // the menu items with the layout are used first, the first one for all languages or the last one of a language
        $items = [
            (object) ['id' => 1, 'language' => '*', 'link' => 'index.php?option=com_content&view=featured'],
            (object) ['id' => 2, 'language' => '*', 'link' => 'index.php?option=com_content&view=featured'],
            (object) ['id' => 3, 'language' => '*', 'link' => 'index.php?option=com_content&view=featured&layout=list'],
            (object) ['id' => 4, 'language' => '*', 'link' => 'index.php?option=com_content&view=archive'],
            (object) ['id' => 5, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=archive'],
            (object) ['id' => 6, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=archive'],
            (object) ['id' => 7, 'language' => '*', 'link' => 'index.php?option=com_content&view=form'],
            (object) ['id' => 8, 'language' => '*', 'link' => 'index.php?option=com_content&view=featured&layout=default'],
        ];
        $routes = $this->routes( collect(), $items );
        $this->assertSame( 1, $routes->viewItem( 'featured' )?->id );
        // like Joomla, the default layout uses the menu item without layout
        $this->assertSame( 1, $routes->viewItem( 'featured', 'default' )?->id );
        $this->assertSame( 1, $routes->viewItem( 'featured', 'blog' )?->id );
        $this->assertSame( 3, $routes->viewItem( 'featured', 'list' )?->id );
        $this->assertSame( 6, $routes->viewItem( 'archive' )?->id );
        $this->assertNull( $routes->viewItem( 'form' ) );

        // menu items with the default layout are the ones of the view without layout
        $routes = $this->routes( collect(), [$items[7], $items[2]] );
        $this->assertSame( 8, $routes->viewItem( 'featured' )?->id );
        $this->assertSame( 8, $routes->viewItem( 'featured', 'default' )?->id );
        $this->assertSame( 3, $routes->viewItem( 'featured', 'list' )?->id );
        // an empty layout is the same as no layout
        $empty = $this->routes( collect(), [(object) ['id' => 9, 'language' => '*', 'link' => 'index.php?option=com_content&view=featured&layout=']] );
        $this->assertSame( 9, $empty->viewItem( 'featured' )?->id );
        // without language filter, only the menu items for all languages are used
        $this->assertSame( 4, $this->routes( collect(), $items, rules: new Joomla\MenuRules( false ) )->viewItem( 'archive' )?->id );
    }


    public function testRestrictedHomeCategoryGetsADisabledPage(): void
    {
        $db = $this->restrictedLevels();
        $db->table( 'categories' )->insert( [
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Secret', 'alias' => 'secret', 'parent_id' => 1, 'level' => 1, 'lft' => 4, 'access' => 2, 'published' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['alias' => 'start'] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Failed', Artisan::output() );

        // guests can't see the category, so the home menu item gets a disabled page of its own and the claim of its path is respected
        $start = PageView::where( 'name', 'Start' )->firstOrFail();
        $this->assertSame( ['start', 0], [$start->path, $start->status] );
        $this->assertNotSame( 'start', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
    }


    public function testLinksToRestrictedCategoryMenuItemsArentResolved(): void
    {
        $db = $this->restrictedLevels();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?option=com_content&view=category&id=2">c</a> <a href="index.php?Itemid=102">i</a></p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'access' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 103, 'title' => 'Alias', 'alias' => 'alias', 'lft' => 2, 'access' => 1, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"102"}'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Skipped menu item: [102] News (it's restricted to Joomla access level Registered)", Artisan::output() );

        // restricted menu items get no page, so links and aliases to them don't point to a page of their path
        $this->assertSame( 0, PageView::where( 'path', 'news' )->count() );
        $this->assertStringNotContainsString( '/news', json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ?: '' );
        $this->assertNotSame( '/news', PageView::where( 'name', 'Alias' )->first()?->to );
    }


    public function testCategoryMenuItemsDuplicatingTheHomeCategoryLinkToTheRootPage(): void
    {
        $this->homeColumn();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=102">n</a> <a href="index.php?option=com_content&view=category&id=2">c</a></p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 101, 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'lft' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 103, 'title' => 'Alias', 'alias' => 'alias', 'lft' => 3, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"102"}'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the root page shows the category of the home menu item, so the other menu item of the category gets no page
        $this->assertSame( 0, PageView::where( 'name', 'News' )->count() );
        $this->assertSame( '/', PageView::where( 'name', 'Alias' )->firstOrFail()->to );
        $this->assertStringContainsString( '<a href=\\"\\/\\">n<\\/a> <a href=\\"\\/\\">c<\\/a>', json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ?: '' );
    }


    public function testFirstCategoryMenuItemGetsThePageOfTheCategory(): void
    {
        $this->homeColumn();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=101">n</a> <a href="index.php?option=com_content&view=category&id=2">c</a></p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 101, 'title' => 'Start', 'alias' => 'start', 'lft' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 103, 'title' => 'Alias', 'alias' => 'alias', 'lft' => 3, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"101"}'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // later menu items of the same category link to the page of the first one
        $this->assertSame( 'news', PageView::where( 'name', 'News' )->firstOrFail()->path );
        $this->assertSame( 0, PageView::where( 'name', 'Start' )->count() );
        $this->assertSame( '/news', PageView::where( 'name', 'Alias' )->firstOrFail()->to );
        $this->assertStringContainsString( '<a href=\\"\\/news\\">n<\\/a> <a href=\\"\\/news\\">c<\\/a>', json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ?: '' );
    }


    public function testLinkMenuItemsWithUnsupportedTargetsClaimNoUrls(): void
    {
        $this->homeColumn();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 4 )->update( ['alias' => 'js'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=103">b</a> <a href="index.php?Itemid=105">c</a></p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 103, 'title' => 'Broken', 'alias' => 'broken', 'lft' => 3, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"999"}'] + $this->componentItem(),
            ['id' => 104, 'title' => 'Js', 'alias' => 'js', 'lft' => 4, 'type' => 'url', 'link' => 'javascript:void(0)'] + $this->componentItem(),
            ['id' => 105, 'title' => 'Chain', 'alias' => 'chain', 'lft' => 5, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"104"}'] + $this->componentItem(),
            ['id' => 106, 'title' => 'Loop', 'alias' => 'loop', 'lft' => 6, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"107"}'] + $this->componentItem(),
            ['id' => 107, 'title' => 'Back', 'alias' => 'back', 'lft' => 7, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"106"}'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // menu items whose targets aren't supported get no pages, so their URL paths are left to the articles and links aren't replaced
        $this->assertSame( 0, PageView::whereIn( 'name', ['Broken', 'Js', 'Chain', 'Loop', 'Back'] )->count() );
        $this->assertSame( 'js', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
        $content = json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ?: '';
        $this->assertStringNotContainsString( '\\/broken', $content );
        $this->assertStringNotContainsString( '\\/chain', $content );
    }


    public function testArticleMenuItemsClaimTheirPathsBeforeCategories(): void
    {
        $this->homeColumn();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'path' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_ids":"1"}'] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3, 'language' => '*'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'language' => '*'] );
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'Blog', 'alias' => 'blog', 'path' => 'blog', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 103, 'title' => 'Sub item', 'alias' => 'sub', 'path' => 'blog/sub', 'parent_id' => 102, 'level' => 2, 'lft' => 2,
                'link' => 'index.php?option=com_content&view=article&id=1'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like the Joomla router, the URL path of the article menu item wins over the route of the subcategory
        $this->assertSame( 'blog/sub', PageView::where( 'name', 'Sub item' )->firstOrFail()->path );
        $this->assertSame( 'blog/sub-3', PageView::where( 'name', 'Sub' )->firstOrFail()->path );
    }


    public function testUrlMenuItemsWithUnresolvableInternalLinksClaimNoUrls(): void
    {
        $this->homeColumn();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 4 )->update( ['alias' => 'login'] );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( ['id' => 1, 'old_url' => 'contact', 'new_url' => 'https://example.org/contact', 'published' => 1] );
        $db->table( 'menu' )->insert( [
            ['id' => 104, 'title' => 'Login', 'alias' => 'login', 'lft' => 4, 'type' => 'url', 'link' => 'index.php?option=com_users&view=login'] + $this->componentItem(),
            ['id' => 105, 'title' => 'Contact', 'alias' => 'contact', 'lft' => 5, 'type' => 'url', 'link' => 'index.php?option=com_content&view=featured'] + $this->componentItem(),
            ['id' => 106, 'title' => 'Gone', 'alias' => 'gone', 'lft' => 6, 'type' => 'url', 'link' => 'index.php?Itemid=999'] + $this->componentItem(),
            ['id' => 107, 'title' => 'Loop', 'alias' => 'loop', 'lft' => 7, 'type' => 'url', 'link' => 'index.php?Itemid=107'] + $this->componentItem(),
            ['id' => 108, 'title' => 'Read', 'alias' => 'read', 'lft' => 8, 'type' => 'url', 'link' => 'index.php?option=com_content&view=article&id=1'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // links to other components, to views without menu item and to unknown menu items can't be resolved, so they claim no URL paths
        // the names are compared in PHP because case-insensitive collations match the name "contact" of the redirect page too
        $names = ['Login', 'Contact', 'Gone', 'Loop'];
        $this->assertSame( [], PageView::whereIn( 'name', $names )->pluck( 'name' )->intersect( $names )->values()->all() );
        $this->assertSame( 'login', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
        $this->assertSame( 'https://example.org/contact', PageView::where( 'path', 'contact' )->firstOrFail()->to );
        // internal links to articles are still supported
        $this->assertSame( '/about', PageView::where( 'name', 'Read' )->firstOrFail()->to );
    }


    public function testAliasesAndLinksToUrlFolders(): void
    {
        $this->homeColumn();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=101">a</a> <a href="index.php?Itemid=102">b</a></p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Group', 'alias' => 'group', 'lft' => 1, 'type' => 'url', 'link' => '#'] + $this->componentItem(),
            ['id' => 102, 'title' => 'Empty', 'alias' => 'empty', 'lft' => 2, 'type' => 'url', 'link' => ''] + $this->componentItem(),
            ['id' => 103, 'title' => 'Topics', 'alias' => 'topics', 'lft' => 3, 'type' => 'heading', 'link' => ''] + $this->componentItem(),
            ['id' => 104, 'title' => 'All', 'alias' => 'all', 'lft' => 4, 'link' => 'index.php?option=com_content&view=categories&id=0'] + $this->componentItem(),
            ['id' => 105, 'title' => 'To group', 'alias' => 'to-group', 'lft' => 5, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"101"}'] + $this->componentItem(),
            ['id' => 106, 'title' => 'To empty', 'alias' => 'to-empty', 'lft' => 6, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"102"}'] + $this->componentItem(),
            ['id' => 107, 'title' => 'To topics', 'alias' => 'to-topics', 'lft' => 7, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"103"}'] + $this->componentItem(),
            ['id' => 108, 'title' => 'To all', 'alias' => 'to-all', 'lft' => 8, 'type' => 'alias', 'link' => '', 'params' => '{"aliasoptions":"104"}'] + $this->componentItem(),
            ['id' => 109, 'title' => 'Via item', 'alias' => 'via-item', 'lft' => 9, 'type' => 'url', 'link' => 'index.php?Itemid=101'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $to = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->to;

        // URL menu items without link or with "#" are folders like headings, so aliases and links to them use their pages
        $this->assertSame( ['', ''], [$to( 'Group' ), $to( 'Empty' )] );
        $this->assertSame( ['/group', '/empty', '/topics', '/all', '/group'], array_map( $to, ['To group', 'To empty', 'To topics', 'To all', 'Via item'] ) );
        $content = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
        $this->assertStringContainsString( '<a href="/group">a</a> <a href="/empty">b</a>', $content );
    }


    public function testTranslatedAliasesLinkToTranslatedMenuItemsOfOtherMenus(): void
    {
        $this->extraTables();
        $this->languageFilter();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $item = ['parent_id' => 1, 'level' => 1, 'link' => ''];
        $en = ['menutype' => 'mainmenu', 'language' => 'en-GB'] + $item;
        $de = ['menutype' => 'mainmenu-de', 'language' => 'de-DE'] + $item;
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Topics', 'alias' => 'topics', 'lft' => 1, 'type' => 'heading', 'params' => '{}'] + $en,
            ['id' => 102, 'title' => 'To topics', 'alias' => 'to-topics', 'lft' => 2, 'type' => 'alias', 'params' => '{"aliasoptions":"101"}'] + $en,
            ['id' => 103, 'title' => 'To alias', 'alias' => 'to-alias', 'lft' => 3, 'type' => 'alias', 'params' => '{"aliasoptions":"102"}'] + $en,
            ['id' => 201, 'title' => 'Themen', 'alias' => 'themen', 'lft' => 4, 'type' => 'heading', 'params' => '{}'] + $de,
            ['id' => 202, 'title' => 'Zu Themen', 'alias' => 'zu-themen', 'lft' => 5, 'type' => 'alias', 'params' => '{"aliasoptions":"201"}'] + $de,
            ['id' => 203, 'title' => 'Zum Alias', 'alias' => 'zum-alias', 'lft' => 6, 'type' => 'alias', 'params' => '{"aliasoptions":"202"}'] + $de,
            // not associated with a menu item of the imported menu, so it has no page variant
            ['id' => 204, 'title' => 'Andere', 'alias' => 'andere', 'lft' => 7, 'type' => 'heading', 'params' => '{}'] + $de,
            ['id' => 205, 'title' => 'Zu Andere', 'alias' => 'zu-andere', 'lft' => 8, 'type' => 'alias', 'params' => '{"aliasoptions":"204"}'] + $de,
            ['id' => 104, 'title' => 'To other', 'alias' => 'to-other', 'lft' => 9, 'type' => 'heading', 'params' => '{}'] + $en,
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 201, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'm2'],
            ['id' => 202, 'context' => 'com_menus.item', 'key' => 'm2'],
            ['id' => 103, 'context' => 'com_menus.item', 'key' => 'm3'],
            ['id' => 203, 'context' => 'com_menus.item', 'key' => 'm3'],
            ['id' => 104, 'context' => 'com_menus.item', 'key' => 'm4'],
            ['id' => 205, 'context' => 'com_menus.item', 'key' => 'm4'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $de = fn( string $name ) => PageView::language( 'de' )->find( PageView::where( 'name', $name )->firstOrFail()->id );

        $this->assertSame( ['/topics', '/to-topics'], [PageView::where( 'name', 'To topics' )->firstOrFail()->to, PageView::where( 'name', 'To alias' )->firstOrFail()->to] );
        // like their sources, the translated aliases link to the page variants of the translated menu items of other menus
        $this->assertSame( ['Zu Themen', '/themen'], [$de( 'To topics' )?->name, $de( 'To topics' )?->to] );
        $this->assertSame( ['Zum Alias', '/zu-themen'], [$de( 'To alias' )?->name, $de( 'To alias' )?->to] );
        // translated menu items without page variant can't be linked to
        $this->assertNotSame( 'Zu Andere', $de( 'To other' )?->name );
    }


    public function testTranslatedArticleMenuItemsClaimTheirPathsBeforeTranslatedCategories(): void
    {
        $this->extraTables();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->string( 'path' )->default( '' );
            $table->boolean( 'home' )->default( false );
        } );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_ids":"1"}'] );
        $category = ['extension' => 'com_content', 'published' => 1];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'language' => 'en-GB'] + $category,
            ['id' => 3, 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'language' => 'en-GB'] + $category,
            ['id' => 12, 'title' => 'Blog DE', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 3, 'language' => 'de-DE'] + $category,
            ['id' => 13, 'title' => 'Sub DE', 'alias' => 'sub', 'parent_id' => 12, 'level' => 2, 'lft' => 4, 'language' => 'de-DE'] + $category,
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 12] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 13] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Story', 'alias' => 'story', 'language' => 'en-GB', 'catid' => 3, 'introtext' => '<p>x</p>', 'fulltext' => ''] );
        $item = ['type' => 'component', 'params' => '{}', 'level' => 1, 'parent_id' => 1];
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Blog', 'alias' => 'blog', 'path' => 'blog', 'lft' => 1, 'language' => 'en-GB',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $item,
            ['id' => 103, 'menutype' => 'mainmenu', 'title' => 'Sub item', 'alias' => 'sub', 'path' => 'blog/sub', 'lft' => 2, 'language' => 'en-GB',
                'link' => 'index.php?option=com_content&view=article&id=1', 'parent_id' => 102, 'level' => 2] + $item,
            ['id' => 202, 'menutype' => 'mainmenu-de', 'title' => 'Blog DE', 'alias' => 'blog', 'path' => 'blog', 'lft' => 3, 'language' => 'de-DE',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=12'] + $item,
            ['id' => 203, 'menutype' => 'mainmenu-de', 'title' => 'Sub-Eintrag', 'alias' => 'sub', 'path' => 'blog/sub', 'lft' => 4, 'language' => 'de-DE',
                'link' => 'index.php?option=com_content&view=article&id=2', 'parent_id' => 202, 'level' => 2] + $item,
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 2, 'context' => 'com_categories.item', 'key' => 'b'],
            ['id' => 12, 'context' => 'com_categories.item', 'key' => 'b'],
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 's'],
            ['id' => 13, 'context' => 'com_categories.item', 'key' => 's'],
            ['id' => 5, 'context' => 'com_content.item', 'key' => 'st'],
            ['id' => 4, 'context' => 'com_content.item', 'key' => 'st'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 202, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 103, 'context' => 'com_menus.item', 'key' => 'm2'],
            ['id' => 203, 'context' => 'com_menus.item', 'key' => 'm2'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de:https://de.example.com']] ) );
        $de = fn( string $name ) => PageView::language( 'de' )->find( PageView::where( 'name', $name )->firstOrFail()->id );

        // like in the imported language, the translated article menu item wins over the route of the translated subcategory
        $this->assertSame( ['blog/sub', 'blog/sub-3'], [PageView::where( 'name', 'Sub item' )->firstOrFail()->path, PageView::where( 'name', 'Sub' )->firstOrFail()->path] );
        $this->assertSame( ['Sub-Eintrag', 'blog/sub'], [$de( 'Sub item' )?->name, $de( 'Sub item' )?->path] );
        $this->assertSame( ['Sub DE', 'blog/sub-13'], [$de( 'Sub' )?->name, $de( 'Sub' )?->path] );
    }


    public function testArticleMenuItemsOfOtherMenusClaimTheirPathsBeforeCategories(): void
    {
        $this->homeColumn();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'path' )->default( '' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['element' => 'com_content', 'params' => '{"sef_ids":"1"}'] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3, 'language' => '*'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'language' => '*'] );
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'Blog', 'alias' => 'blog', 'path' => 'blog', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 202, 'menutype' => 'footer', 'title' => 'Footer', 'alias' => 'blog', 'path' => 'blog', 'lft' => 3, 'type' => 'heading', 'link' => ''] + $this->componentItem(),
            ['id' => 203, 'menutype' => 'footer', 'title' => 'Footer sub', 'alias' => 'sub', 'path' => 'blog/sub', 'parent_id' => 202, 'level' => 2, 'lft' => 4,
                'link' => 'index.php?option=com_content&view=article&id=1'] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like the Joomla router, which matches the paths of the menu items of all menus first, the article gets the path of its menu item
        $this->assertSame( 'blog/sub', PageView::where( 'name', 'Footer sub' )->firstOrFail()->path );
        $this->assertSame( 'blog/sub-3', PageView::where( 'name', 'Sub' )->firstOrFail()->path );
    }


    public function testTranslatedMenuItemsWithoutVariantClaimNoUrls(): void
    {
        $db = $this->restrictedLevels();
        $this->languageFilter();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 1, 'published' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'parent_id' => 1, 'level' => 1, 'lft' => 2, 'access' => 1, 'published' => 0],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=202">a</a> <a href="index.php?Itemid=203">c</a></p>'] );
        $en = ['menutype' => 'mainmenu', 'language' => 'en-GB', 'link' => '', 'access' => 1] + $this->componentItem();
        $de = ['menutype' => 'mainmenu-de', 'language' => 'de-DE', 'link' => '', 'access' => 1] + $this->componentItem();
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'Topics', 'alias' => 'topics', 'lft' => 1, 'type' => 'heading'] + $en,
            ['id' => 102, 'title' => 'Ext', 'alias' => 'ext', 'lft' => 2, 'type' => 'alias', 'params' => '{"aliasoptions":"101"}'] + $en,
            ['id' => 103, 'title' => 'News', 'alias' => 'news', 'lft' => 3, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $en,
            ['id' => 201, 'title' => 'Themen', 'alias' => 'themen', 'lft' => 4, 'type' => 'heading'] + $de,
            // translated alias to a missing menu item and translated category item of an unpublished category
            ['id' => 202, 'title' => 'Kaputt', 'alias' => 'kaputt', 'lft' => 5, 'type' => 'alias', 'params' => '{"aliasoptions":"999"}'] + $de,
            ['id' => 203, 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'lft' => 6, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'] + $de,
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 201, 'context' => 'com_menus.item', 'key' => 'm1'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'm2'],
            ['id' => 202, 'context' => 'com_menus.item', 'key' => 'm2'],
            ['id' => 103, 'context' => 'com_menus.item', 'key' => 'm3'],
            ['id' => 203, 'context' => 'com_menus.item', 'key' => 'm3'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // translated menu items without variant don't claim URL paths links could point to
        $this->assertNull( PageView::language( 'de' )->find( PageView::where( 'name', 'Ext' )->firstOrFail()->id ) );
        $this->assertNull( PageView::language( 'de' )->find( PageView::where( 'name', 'News' )->firstOrFail()->id ) );
        $this->assertSame( 0, PageView::withoutGlobalScopes()->whereIn( 'path', ['kaputt', 'de/kaputt', 'nachrichten', 'de/nachrichten'] )->count() );
        $content = stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
        $this->assertStringNotContainsString( 'kaputt"', $content );
        $this->assertStringNotContainsString( 'nachrichten"', $content );
    }


    public function testChildItemsOfRestrictedCategoryMenuItemsAreBelowTheCategoryPage(): void
    {
        $db = $this->restrictedLevels();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
        ] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'title' => 'News', 'alias' => 'news', 'lft' => 1, 'access' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 102, 'title' => 'Members', 'alias' => 'members', 'lft' => 3, 'access' => 2, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'] + $this->componentItem(),
            ['id' => 103, 'title' => 'Child', 'alias' => 'child', 'lft' => 4, 'access' => 1, 'parent_id' => 102, 'level' => 2, 'type' => 'heading', 'link' => ''] + $this->componentItem(),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped menu item: [102] Members', Artisan::output() );

        // like for public category menu items without own page, the child items are below the existing category page
        $news = PageView::where( 'path', 'news' )->firstOrFail();
        $this->assertSame( $news->id, PageView::where( 'name', 'Child' )->firstOrFail()->parent_id );
    }


    /**
     * Returns the columns of a Joomla component menu item of the main menu at the top level.
     *
     * @return array<string, mixed> Column values
     */
    protected function componentItem(): array
    {
        return ['menutype' => 'mainmenu', 'parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}', 'home' => 0];
    }


    /**
     * Adds the tables of the menu items and the column of the home menu items.
     */
    protected function homeColumn(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
    }


    /**
     * Adds the public access level and the access level "Registered" guests can't see.
     *
     * @return \Illuminate\Database\Connection Joomla database connection
     */
    protected function restrictedLevels(): \Illuminate\Database\Connection
    {
        $this->homeColumn();
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );

        return $db;
    }
}
