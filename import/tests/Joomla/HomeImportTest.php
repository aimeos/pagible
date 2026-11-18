<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Joomla;

use Aimeos\Cms\Import\Joomla;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Command-level tests importing the Joomla home menu items into the root page and the home pages of the languages.
 */
class HomeImportTest extends TestAbstract
{
    public function testFeaturedHomeMenuItemKeepsRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component'] + $this->menuItem( 101, 'Home', 1, 1 ),
            ['home' => 0] + $this->menuItem( 102, 'About', 101, 2, 1 ),
        ] );
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->text( 'params' )->nullable() );
        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"show_page_heading":"1","page_heading":"Welcome home"}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the page heading of the menu item is shown above the featured articles
        $this->assertSame( 'Welcome home', $root->content[0]->data->title ?? null );
        $this->assertCount( 1, (array) $root->content );
        $this->assertStringNotContainsString( 'Skipped menu item', $output );
        $this->assertSame( $root->id, PageView::where( 'name', 'About' )->firstOrFail()->parent_id );
        $this->assertSame( [$root->id], PageView::where( 'name', 'Home' )->pluck( 'id' )->all() );
    }


    public function testFeaturedHomeMenuItemsOfOtherLanguagesShowTheirPageHeading(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'menutype' => 'mainmenu-de', 'lft' => 1, 'link' => 'index.php?option=com_content&view=featured',
                'params' => '{"show_page_heading":"1","page_heading":"Willkommen"}'] + $this->menuItem( 201, 'Startseite', 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the German home page shows the page heading of its menu item like the featured articles of the root page
        $de = PageView::language( 'de' )->findOrFail( PageView::where( 'tag', 'root' )->firstOrFail()->id );
        $this->assertSame( ['de', 'Startseite', 'Willkommen'], [$de->path, $de->name, $de->content[0]->data->title ?? null] );
    }


    public function testFormerTranslationsOfTheRootPageAreReported(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', $root->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );

        // the translation of the home article which isn't imported any more doesn't delete the variant of the root page but is reported
        $db->table( 'content' )->where( 'id', 3 )->update( ['state' => -2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "Former translation: {$root->name} (/) [fr] (no Joomla translation in this language is imported any more", $output );
        $this->assertStringNotContainsString( '(/) [de]', $output );
    }


    public function testRestrictedHomeArticleIsImportedAsNormalPage(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'content' )->where( 'id', 1 )->update( ['access' => 5] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Imported home page as normal page: [1] About (it's restricted to Joomla access level 5)", Artisan::output() );
        $this->assertNotSame( 'About', PageView::where( 'tag', 'root' )->firstOrFail()->title );

        // the restriction is shown even if the root page was changed in Pagible
        PageView::where( 'tag', 'root' )->firstOrFail()->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Imported home page as normal page: [1] About (it's restricted to Joomla access level 5)", Artisan::output() );

        // the category of the home article is restricted
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'level' => 1, 'access' => 5] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['access' => 1, 'catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Imported home page as normal page: [1] About (its category Members is restricted to Joomla access level 5)', Artisan::output() );

        // the home menu item is restricted while the article isn't
        $db->table( 'categories' )->where( 'id', 2 )->update( ['access' => 1] );
        $db->table( 'menu' )->where( 'id', 101 )->update( ['access' => 5] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Imported home page as normal page: [1] About (it's restricted to Joomla access level 5)", Artisan::output() );
    }


    public function testHomeMenuItemImportsArticleIntoRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 0] + $this->menuItem( 102, 'Nur', 101, 2, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        $this->assertStringContainsString( 'Imported: About (/)', $output );
        $this->assertSame( 'Start', $root->name );
        $this->assertSame( 'About', $root->title );
        $this->assertStringContainsString( 'Welcome', (string) json_encode( $root->content ) );
        $this->assertSame( 0, PageView::where( 'name', 'About' )->count() );
        $this->assertSame( $root->id, PageView::where( 'name', 'Nur' )->firstOrFail()->parent_id );
        // the translations of the root page use the language code as URL path
        $this->assertSame( 'de', PageView::language( 'de' )->findOrFail( $root->id )->path );
        $this->assertSame( 'Willkommen', PageView::language( 'de' )->findOrFail( $root->id )->content[1]->data->title ?? null );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $this->assertStringContainsString( 'Unchanged: About (/)', Artisan::output() );

        // root pages changed in Pagible aren't overwritten
        $root->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $this->assertStringContainsString( 'Imported home page as normal page: [1] About (the root page already exists)', Artisan::output() );
        // the home menu item is still the menu item of the article
        $this->assertSame( 'Start', PageView::where( 'path', 'about' )->firstOrFail()->name );
    }


    public function testHomeArticleIsImportedBeforeItsOtherMenuItems(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( [
            ['home' => 0, 'lft' => 1] + $this->menuItem( 100, 'Intro', 1, 1, 1 ),
            ['home' => 1, 'lft' => 2] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Imported: About (/)', Artisan::output() );

        // the home article is imported into the root page even if another menu item of it comes first
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertSame( 'Start', $root->name );
        $this->assertStringContainsString( 'Welcome', (string) json_encode( $root->content ) );
        $this->assertSame( 0, PageView::whereIn( 'path', ['intro', 'about'] )->count() );
    }


    public function testSinceImportsTheHomeArticleIntoANewRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00'] );

        // the root page created by this run has no content yet, so the unchanged home article is imported into it
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Imported: About (/)', Artisan::output() );
        $this->assertStringContainsString( 'Welcome', (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/)', Artisan::output() );
    }


    public function testHomeMenuItemOfCategoryUsesRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Home', 'alias' => 'home', 'home' => 1,
            'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $this->assertSame( PageView::where( 'tag', 'root' )->firstOrFail()->id, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->parent_id );
        $this->assertSame( 0, PageView::whereIn( 'name', ['News', 'Home'] )->where( 'tag', '!=', 'root' )->count() );
    }


    public function testStrictRoutingDoesntUseTheHomeMenuItem(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sport', 'alias' => 'sport', 'parent_id' => 1, 'level' => 1, 'lft' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Home', 'alias' => 'home', 'home' => 1, 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'params' => '{}'] );

        // Joomla 4+ uses the home category blog for articles without menu item
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( '4-nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );

        // with strict routing of the SEF plugin of Joomla 5.2+, they have no URL path in Joomla
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'sef', 'params' => '{"strictrouting":"1"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Nur Deutsch', PageView::where( 'path', 'nur-deutsch' )->firstOrFail()->name );
    }


    public function testHomeMenuItemSeoOptions(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component',
            'params' => '{"page_title":"Welcome home","menu-meta_description":"Our site","robots":"noindex, follow","show_page_heading":"1"}'] + $this->menuItem( 101, 'Home', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the browser title and the meta data of featured and category home pages are used for the root page
        $this->assertSame( ['Home', 'Welcome home'], [$root->name, $root->title] );
        $this->assertSame( 'Our site', $root->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( ['index' => 'noindex', 'follow' => 'follow'], (array) ( $root->meta->robots->data ?? [] ) );
        // the page heading is shown above the content
        $this->assertSame( ['heading'], array_column( (array) json_decode( (string) json_encode( $root->content ), true ), 'type' ) );

        // root pages changed in Pagible aren't overwritten
        $root->variant->forceFill( ['editor' => 'someone'] )->save();
        DB::connection( 'joomla' )->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"page_title":"Other"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Welcome home', PageView::where( 'tag', 'root' )->firstOrFail()->title );
    }


    public function testJoomla3LegacyRoutesUseHomeMenuItemOfOtherMenus(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $this->setJoomlaVersion( 3 );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'menutype' => 'homemenu', 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'params' => '{}'] + $this->menuItem( 101, 'Home', 1, 1 ),
            ['home' => 0, 'menutype' => 'mainmenu', 'link' => '', 'type' => 'heading', 'params' => '{}'] + $this->menuItem( 102, 'Heading', 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the legacy router of Joomla 3 uses the home menu item of any menu
        $this->assertSame( '2-news/4-nur-deutsch', PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->path );
    }


    public function testLinksToTheHomeArticleUseTheHomePages(): void
    {
        $this->extraTables();
        $this->languageFilter();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'params' => '{}', 'menutype' => 'othermenu'] + $this->menuItem( 201, 'Other', 1, 1, 4 ),
        ] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['language' => '*', 'introtext' => '<p><a href="index.php?option=com_content&amp;view=article&amp;id=1">A</a> '
            . '<a href="index.php?option=com_content&amp;view=article&amp;id=2">B</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the links point to the root page and its variant, the home menu items of other menus are imported like the other ones
        $json = stripslashes( (string) json_encode( PageView::where( 'name', 'Other' )->firstOrFail()->content ) );
        $this->assertStringContainsString( '<a href="/">A</a> <a href="/de">B</a>', $json );
        $this->assertSame( 'About', PageView::where( 'tag', 'root' )->firstOrFail()->title );
    }


    public function testHomeMenuItemListingCategoriesKeepsTheRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'published' => 1] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'params' => '{"page_title":"Welcome"}',
            'link' => 'index.php?option=com_content&view=categories&id=2', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Welcome', PageView::where( 'tag', 'root' )->firstOrFail()->title );
        $this->assertSame( 0, PageView::where( 'name', 'Start' )->count() );
    }


    public function testHomeRootTitleUsesTheBrowserTitleOfTheMenuItemOnlyIfSet(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'params' => '{}',
            'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );

        // without browser title, the title of the menu item is used like Joomla does
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Start', PageView::where( 'tag', 'root' )->firstOrFail()->title );

        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"page_title":"Welcome"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Welcome', PageView::where( 'tag', 'root' )->firstOrFail()->title );

        // an empty browser title isn't used
        $db->table( 'menu' )->where( 'id', 101 )->update( ['params' => '{"page_title":""}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Start', PageView::where( 'tag', 'root' )->firstOrFail()->title );
    }


    public function testFailedHomeArticleIsNotImportedAgain(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        // another menu item links to the same article as the home menu item
        $db->table( 'menu' )->insert( [
            ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 0] + $this->menuItem( 102, 'Again', 1, 1, 1 ),
        ] );

        // the versions of the article fail, also if it would be imported as normal page
        $fail = true;
        \Illuminate\Support\Facades\Event::listen( 'eloquent.creating: ' . \Aimeos\Cms\Models\Version::class,
            fn( \Aimeos\Cms\Models\Version $version ) => $fail && ( $version->data->title ?? null ) === 'About' ? throw new \RuntimeException( 'broken' ) : null );

        try
        {
            $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            $output = Artisan::output();
        }
        finally
        {
            $fail = false;
        }

        // the failure is reported once and the article isn't imported again by its category or the other menu item
        $this->assertSame( 1, substr_count( $output, 'Failed to import' ) );
        $this->assertStringContainsString( 'Failed to import [1] About: broken', $output );
        $this->assertSame( 0, PageView::where( 'title', 'About' )->count() );
        $this->assertSame( 0, PageView::where( 'path', 'about' )->count() );
        $this->assertSame( 0, PageView::where( 'path', 'again' )->count() );
    }


    public function testHomeMenuItemOfUnpublishedCategoryIsSkipped(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->text( 'description' )->nullable() );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1,
            'published' => 0, 'description' => '<p>All news</p>'] );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Home', 'alias' => 'home', 'home' => 1,
            'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Skipped menu item: [101] Home (its category isn't shown by Joomla)", Artisan::output() );
        $this->assertStringNotContainsString( 'All news', (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) );
    }


    public function testHomeArticleOnlyChecksItsOwnCategory(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 5],
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 3, 'level' => 2, 'lft' => 2, 'access' => 1],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        // like Joomla, articles with own menu item don't check the access levels of the parent categories
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'About', PageView::where( 'tag', 'root' )->firstOrFail()->title );
    }


    public function testCategoryHomeMenuItemShowsTheCategoryInTheRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        Schema::connection( 'joomla' )->table( 'categories', function( Blueprint $table ) {
            $table->text( 'description' )->nullable();
            $table->string( 'language' )->default( '*' );
        } );
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1,
            'description' => '<p>All news</p>'] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Home', 'alias' => 'home', 'home' => 1, 'language' => '*',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Firma', 'alias' => 'firma', 'home' => 0, 'language' => 'de-DE',
                'link' => '', 'type' => 'heading', 'parent_id' => 1, 'level' => 1, 'lft' => 2],
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'home'],
            ['id' => 201, 'context' => 'com_menus.item', 'key' => 'home'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the root page shows the description of the category and gets the associated menu items as variants
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertStringContainsString( 'All news', (string) json_encode( $root->content ) );
        $this->assertSame( ['de', 'Firma'], [PageView::language( 'de' )->findOrFail( $root->id )->path, PageView::language( 'de' )->findOrFail( $root->id )->name] );
    }


    public function testHomeMenuItemOfTranslatedCategoryIsntItsMenuItem(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1, 'language' => 'en-GB'],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Neuigkeiten', 'alias' => 'neuigkeiten', 'parent_id' => 1, 'level' => 2, 'lft' => 2, 'language' => 'de-DE'],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Latest', 'alias' => 'latest', 'language' => 'en-GB', 'introtext' => '<p>New</p>', 'fulltext' => '', 'catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'language' => 'en-GB',
                'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'params' => '{}'],
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Startseite', 'alias' => 'startseite', 'home' => 1, 'language' => 'de-DE',
                'link' => 'index.php?option=com_content&view=category&layout=blog&id=3', 'type' => 'component', 'parent_id' => 1, 'level' => 1, 'lft' => 2,
                'params' => '{"page_title":"Willkommen"}'],
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 2, 'context' => 'com_categories.item', 'key' => 'news'],
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'news'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the home menu item shows the category on the German home page, so the translation of the category page uses the category
        $page = PageView::where( 'name', 'News' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $this->assertSame( ['Neuigkeiten', 'Neuigkeiten'], [$de->name, $de->title] );
    }


    public function testHomeMenuItemsOfOtherLanguagesAreTheirHomePages(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ),
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?Itemid=201">DE</a><a href="index.php?Itemid=101">EN</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the German home page shows the article of the German home menu item instead of the associated article
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        // links to the home menu items of other languages point to their home pages
        $this->assertStringContainsString( '<a href="/de">DE</a><a href="/">EN</a>', stripslashes( (string) json_encode( $root->content ) ) );
        $de = PageView::language( 'de' )->findOrFail( $root->id );
        $this->assertSame( ['About', 'de', 'Startseite'], [$root->title, $de->path, $de->name] );
        $this->assertStringContainsString( '<p>Text</p>', stripslashes( (string) json_encode( $de->content ) ) );
    }


    public function testHomeMenuItemsOfOtherLanguagesUseTheFetchedArticles(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Everywhere', 'alias' => 'everywhere', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => ''] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 0, 'language' => '*', 'params' => '{}'] + $this->menuItem( 102, 'Everywhere', 1, 1, 5 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 5 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the article of the German home page is fetched with the articles of the menu already
        $de = PageView::language( 'de' )->findOrFail( PageView::where( 'tag', 'root' )->firstOrFail()->id );
        $this->assertStringContainsString( '<p>All</p>', stripslashes( (string) json_encode( $de->content ) ) );
    }


    public function testHomeMenuItemsOfOtherLanguagesShareTheirArticle(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'languages' )->insert( ['lang_code' => 'fr-FR', 'sef' => 'fr'] );
        $this->languageFilter();
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Everywhere', 'alias' => 'everywhere', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => ''] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 5 ),
            ['home' => 1, 'language' => 'fr-FR', 'params' => '{}', 'menutype' => 'mainmenu-fr', 'lft' => 1] + $this->menuItem( 301, 'Accueil', 1, 1, 5 ),
        ] );

        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de', 'fr-FR:fr']] ) );
        $queries = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"id" in (?)' ) && str_contains( $query['query'], '"metadesc"' ) );
        $db->disableQueryLog();

        // the article for all languages is shown on the home pages of both languages and fetched once for them besides its own page
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $root->id );
        $fr = PageView::language( 'fr' )->findOrFail( $root->id );
        $this->assertSame( ['de', 'Startseite', 'fr', 'Accueil'], [$de->path, $de->name, $fr->path, $fr->name] );
        $this->assertStringContainsString( '<p>All</p>', stripslashes( (string) json_encode( $de->content ) ) );
        $this->assertStringContainsString( '<p>All</p>', stripslashes( (string) json_encode( $fr->content ) ) );
        $this->assertSame( 2, $queries->count() );
    }


    public function testSinceImportsChangedArticlesOfOtherHomeMenuItems(): void
    {
        $this->extraTables();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Everywhere', 'alias' => 'everywhere', 'language' => '*', 'introtext' => '<p>All</p>', 'fulltext' => '',
            'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 5 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // without changes, the root page is kept
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/)', Artisan::output() );

        // the changed article of the German home menu item imports the root page again
        $db->table( 'content' )->where( 'id', 5 )->update( ['introtext' => '<p>Changed</p>', 'modified' => '2020-01-02 00:00:00'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertStringContainsString( '<p>Changed</p>', stripslashes( (string) json_encode( PageView::language( 'de' )->findOrFail( $root->id )->content ) ) );
    }


    public function testSinceKeepsUnchangedRootWithHomeMenuItemsOfHiddenArticles(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => 0] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the article Joomla doesn't show isn't shown in the root page either, so its text isn't fetched and the rendered root page is unchanged
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $bindings = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) )->pluck( 'bindings' )->flatten();
        $db->disableQueryLog();
        $db->flushQueryLog();

        $this->assertStringContainsString( 'Unchanged: About (/)', Artisan::output() );
        $this->assertNotContains( 4, $bindings->all() );
    }


    public function testSinceSavesNewHomeMenuItemsOfOtherLanguagesOfUnchangedRoot(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertSame( 'Über uns', PageView::language( 'de' )->findOrFail( $root->id )->name );

        // the new home menu item of the other language shows an unchanged article, the root page itself is unchanged
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/)', Artisan::output() );
        $de = PageView::language( 'de' )->findOrFail( $root->id );
        $this->assertSame( ['de', 'Startseite'], [$de->path, $de->name] );
        $this->assertStringContainsString( '<p>Text</p>', stripslashes( (string) json_encode( $de->content ) ) );
    }


    public function testFirstMenuItemOfTheCategoryHomeLinksToTheRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1] );
        $item = ['menutype' => 'mainmenu', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component', 'parent_id' => 1, 'level' => 1];
        $db->table( 'menu' )->insert( [
            ['id' => 102, 'title' => 'Home', 'alias' => 'home', 'home' => 1, 'lft' => 1] + $item,
            ['id' => 101, 'title' => 'Blog', 'alias' => 'blog', 'home' => 0, 'lft' => 2] + $item,
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p><a href="index.php?option=com_content&amp;view=category&amp;id=2">C</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '<a href="/">C</a>', stripslashes( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) ) );
    }


    public function testWithoutLanguageFilterTheHomeMenuItemForAllLanguagesIsUsed(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component'] + $this->menuItem( 101, 'Neuigkeiten', 1, 1 ),
            ['home' => 1, 'language' => 'en-GB', 'link' => 'index.php?option=com_content&view=article&id=4', 'type' => 'component'] + $this->menuItem( 100, 'English', 1, 1 ),
            ['home' => 1, 'language' => '*'] + $this->menuItem( 102, 'Start', 1, 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the other home menu item is imported like the other menu items
        $this->assertSame( 'About', PageView::where( 'tag', 'root' )->firstOrFail()->title );
        $this->assertSame( 'Neuigkeiten', PageView::where( 'path', 'neuigkeiten' )->firstOrFail()->name );
    }


    public function testHomeMenuItemOfAnotherMenuIsUsedForTheRootPage(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        // Joomla doesn't use expired home menu items
        $db->table( 'menu' )->insert( ['home' => 1, 'publish_down' => '2000-01-01 00:00:00', 'lft' => 1] + $this->menuItem( 103, 'Old', 1, 1, 4 ) );
        $db->table( 'menu' )->insert( ['home' => 1, 'menutype' => 'hidden'] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 102, 'Nur', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--menu' => 'mainmenu'] ) );
        $output = Artisan::output();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        $this->assertStringContainsString( 'Imported: About (/)', $output );
        $this->assertStringContainsString( 'Skipped menu item: [103] Old (it expired', $output );
        $this->assertSame( ['Start', 'About'], [$root->name, $root->title] );
        $this->assertSame( 0, PageView::where( 'name', 'About' )->count() );
        $this->assertSame( $root->id, PageView::where( 'name', 'Nur' )->firstOrFail()->parent_id );
    }


    public function testRestrictedHomeMenuItemsOfOtherLanguagesAreTheirHomePages(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
            $table->text( 'params' )->nullable();
        } );
        $this->languageFilter();
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( ['id' => 2, 'title' => 'Registered', 'rules' => '[2]'] );
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'access' => 2] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // the German home page shows the login form in Joomla, so it's disabled instead of showing the associated article
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $root->id );
        $this->assertSame( ['About', 'Startseite', 0], [$root->title, $de->name, $de->status] );
    }


    public function testArticlesOfHomeMenuItemsOfOtherLanguagesAreRestrictedByTheirParentCategories(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
            $table->text( 'params' )->nullable();
        } );
        $this->languageFilter();
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( ['id' => 2, 'title' => 'Registered', 'rules' => '[2]'] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members', 'alias' => 'members', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2, 'access' => 1],
        ] );
        // the German home menu item isn't the own menu item of the article for all languages
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3, 'language' => '*'] );
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de'] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // without own menu item, the restricted parent of its category hides the article like its page in the import language
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $de = PageView::language( 'de' )->findOrFail( $root->id );
        $this->assertSame( ['Startseite', 0], [$de->name, $de->status] );
    }


    public function testRestrictedHomeMenuItemDoesntShowItsArticleInTheRootPage(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['home' => 1, 'access' => 2] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '<p><a href="index.php?option=com_content&amp;view=article&amp;id=1">A</a></p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '<a href="/about">A</a>', stripslashes( (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) ) );
    }


    public function testHomeMenuItemsOfOtherLanguagesWithUnpublishedArticlesAreSkipped(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => 0] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de'] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertStringNotContainsString( '<p>Text</p>', stripslashes( (string) json_encode( PageView::language( 'de' )->find( $root->id )?->content ) ) );
        $this->assertStringContainsString( "Skipped home page: [201] Startseite [de-DE] (its article [4] isn't shown by Joomla)", Artisan::output() );
    }


    public function testHomeMenuItemIsTheActiveMenuItemOfArticlesWithoutMenuItem(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Home', 'alias' => 'home', 'home' => 1, 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=featured', 'params' => '{"show_title":"0"}'] );
        $heading = fn() => str_contains( (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ), '"level":1,"title":"About"' );

        // Joomla uses the options of the home menu item for articles without menu item for their categories
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertFalse( $heading() );

        // with strict routing of Joomla 5.2+, the home menu item isn't the active one
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'sef', 'params' => '{"strictrouting":"1"}'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertTrue( $heading() );
    }


    public function testHomePageUsesTheMenuItemTitle(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'params' => '{}']
            + $this->menuItem( 101, 'Welcome', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like Joomla, the title of the menu item is the browser title if the menu item has none
        $this->assertSame( 'Welcome', PageView::where( 'tag', 'root' )->firstOrFail()->title );
    }


    public function testSinceRenamesThePagesOfRenamedMenuItems(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ), ['home' => 0] + $this->menuItem( 102, 'Nur', 1, 1, 4 )] );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Start', PageView::where( 'tag', 'root' )->firstOrFail()->name );

        // Joomla doesn't change the articles if their menu items are renamed, so the page names are compared
        $db->table( 'menu' )->where( 'id', 101 )->update( ['title' => 'Startseite'] );
        $db->table( 'menu' )->where( 'id', 102 )->update( ['title' => 'Nur2'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertSame( 'Startseite', PageView::where( 'tag', 'root' )->firstOrFail()->name );
        $this->assertSame( 'Nur2', PageView::where( 'path', 'nur' )->firstOrFail()->name );

        // without further changes, the pages are kept
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Unchanged: About (/)', $output );
        $this->assertStringContainsString( 'Unchanged: Nur Deutsch (/nur)', $output );
    }


    public function testTextsOfTheHomeArticlesOfAllLanguagesArePrefetched(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de', 'lft' => 1] + $this->menuItem( 201, 'Startseite', 1, 1, 4 ),
        ] );

        // the texts of the home article, its translations and the articles of the other home menu items are fetched with the root page
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '-vvv' => true] ) );
        $this->assertStringNotContainsString( 'on its own because', Artisan::output() );

        // also if only the home article changed and the other ones are unchanged
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Changed</p>', 'modified' => '2020-01-02 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01', '-vvv' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: About (/)', $output );
        $this->assertStringNotContainsString( 'on its own because', $output );
        $this->assertStringContainsString( '<p>Changed</p>', stripslashes( (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) ) );
    }


    public function testSinceImportsTheUnchangedArticleTheHomeMenuItemLinksToNow(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Five', 'alias' => 'five', 'language' => 'en-GB', 'introtext' => '<p>Fifth</p>', 'fulltext' => ''] );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( '<p>Hello</p>', stripslashes( (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) ) );

        // the home menu item with the same name shows another unchanged article, so the root page shows it now
        $db->table( 'menu' )->where( 'id', 101 )->update( ['link' => 'index.php?option=com_content&view=article&id=5'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $content = stripslashes( (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) );

        $this->assertStringContainsString( 'Updated: Five (/)', Artisan::output() );
        $this->assertStringContainsString( '<p>Fifth</p>', $content );
        $this->assertStringNotContainsString( '<p>Hello</p>', $content );

        // without further changes, the rendered root page keeps its version
        $latest = PageView::where( 'tag', 'root' )->firstOrFail()->latest_id;

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: Five (/)', Artisan::output() );
        $this->assertSame( $latest, PageView::where( 'tag', 'root' )->firstOrFail()->latest_id );
    }


    public function testSinceImportsTheUnchangedArticleTheFeaturedHomeMenuItemLinksToNow(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( ['home' => 1, 'type' => 'component', 'link' => 'index.php?option=com_content&view=featured'] + $this->menuItem( 101, 'Home', 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Home', PageView::where( 'tag', 'root' )->firstOrFail()->name );
        $this->assertStringNotContainsString( '<p>Hello</p>', stripslashes( (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) ) );

        // the root page has the name of the home menu item, which shows an unchanged article now
        $db->table( 'menu' )->where( 'id', 101 )->update( ['link' => 'index.php?option=com_content&view=article&id=1'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Updated: About (/)', Artisan::output() );
        $this->assertStringContainsString( '<p>Hello</p>', stripslashes( (string) json_encode( PageView::where( 'tag', 'root' )->firstOrFail()->content ) ) );
    }


    public function testFailedRootTranslationsRestoreTheRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( [
            ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 0] + $this->menuItem( 102, 'Nur', 101, 2, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the root page gets a new version before its German variant fails
        $db->table( 'menu' )->where( 'id', 101 )->update( ['title' => 'Begin'] );
        $db->table( 'content' )->whereIn( 'id', [1, 2] )->update( ['introtext' => '<p>Changed</p>'] );
        $fail = true;
        \Illuminate\Support\Facades\Event::listen( 'eloquent.saving: ' . \Aimeos\Cms\Models\PageVariant::class,
            function( \Aimeos\Cms\Models\PageVariant $variant ) use ( &$fail ) {
                if( $fail && $variant->lang === 'de' ) {
                    throw new \RuntimeException( 'broken' );
                }
            } );

        try
        {
            $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
            $output = Artisan::output();
        }
        finally
        {
            $fail = false;
        }

        // the root page keeps its values and the following records still use it
        $this->assertStringContainsString( 'Failed to import [1] About: broken', $output );
        $failed = PageView::where( 'tag', 'root' )->firstOrFail();
        $this->assertSame( $root->latest_id, $failed->latest_id );
        $this->assertSame( 'Start', $failed->name );
        $this->assertStringNotContainsString( 'Changed', (string) json_encode( $failed->content ) );
        $this->assertSame( $root->id, PageView::where( 'name', 'Nur' )->firstOrFail()->parent_id );

        // the root page object used by the following records gets back the values it had before the failed transaction
        $command = Artisan::all()['cms:joomla-import'] ?? throw new \RuntimeException( 'Command not registered' );
        $result = ( function() use ( $failed ) {
            return $this->rootTransaction( '[1] About', $failed, function() use ( $failed ) {
                $this->createVersion( $failed, $this->pageData( 'Changed', '', $failed->status, 'root' ), [], [] );
                throw new \RuntimeException( 'broken' );
            } );
        } )->call( $command );

        $this->assertNull( $result );
        $this->assertSame( $root->latest_id, $failed->latest_id );
        $this->assertSame( 'Start', $failed->name );
        $this->assertFalse( $failed->isDirty() );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $this->assertStringContainsString( 'Updated: About (/)', Artisan::output() );
        $this->assertSame( 'Begin', PageView::where( 'tag', 'root' )->firstOrFail()->name );
    }


    public function testFailedHomeRootIsReportedAsFailedImport(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( [
            ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component'] + $this->menuItem( 101, 'Home', 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $menuItem = DB::connection( 'joomla' )->table( 'menu' )->where( 'id', 101 )->firstOrFail();

        // rendering the root page fails within the transaction, so the failure is reported instead of aborting the import
        $command = Artisan::all()['cms:joomla-import'] ?? throw new \RuntimeException( 'Command not registered' );
        ( function() use ( $menuItem, $root ) {
            $this->failures = [];
            $this->updateHomeRoot( $menuItem, $root, new \stdClass() );
        } )->call( $command );

        $failures = ( fn() => $this->failures )->call( $command );

        $this->assertCount( 1, $failures );
        $this->assertStringStartsWith( '[101] Home: ', $failures[0] );
        $this->assertSame( $root->latest_id, PageView::where( 'tag', 'root' )->firstOrFail()->latest_id );
        $this->assertFalse( $root->isDirty() );
    }


    public function testDryRunListsTheMenuItemsLikeTheImport(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( [
            ['home' => 1, 'menutype' => 'hidden'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 0] + $this->menuItem( 102, 'Again', 1, 1, 1 ),
            ['home' => 0] + $this->menuItem( 103, 'Nur', 1, 1, 4 ),
            ['home' => 0] + $this->menuItem( 104, 'Nochmal', 1, 1, 4 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        // the home menu item of another menu updates the root page, the later menu items of the same articles reuse their pages
        $this->assertStringContainsString( "  Menu item [101] Start (root page)\n  Menu item [103] Nur\nDry run", $output );
        $this->assertSame( 0, PageView::count() );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, PageView::whereIn( 'name', ['Again', 'Nochmal'] )->count() );
    }


    public function testDryRunDoesntListTheArticleOfTheRootPageAsArticlePage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        // the article is imported into the root page, so it's only listed with the home menu item like by the import
        $this->assertStringContainsString( '  Menu item [101] Start (root page)', $output );
        $this->assertStringNotContainsString( '[1] About', $output );
        $this->assertStringContainsString( '[4] Nur Deutsch', $output );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, PageView::where( 'name', 'About' )->where( 'tag', '!=', 'root' )->count() );

        // the article is imported as normal page if the root page was changed in Pagible, so the dry run lists it then
        PageView::where( 'tag', 'root' )->firstOrFail()->variant->forceFill( ['editor' => 'someone'] )->save();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( '[1] About', $output );
        $this->assertStringNotContainsString( '(root page)', $output );
    }


    public function testHomeRootComparesIdsOfAllTypes(): void
    {
        $home = Joomla\HomeRoot::withArticle( (object) ['id' => '101'], 5 );
        $none = Joomla\HomeRoot::withoutArticle( (object) ['id' => 101] );

        // IDs from array keys and database rows can be integers or strings, missing IDs never match
        $this->assertTrue( $home->isArticle( 5 ) );
        $this->assertTrue( $home->isArticle( '5' ) );
        $this->assertFalse( $home->isArticle( 6 ) );
        $this->assertFalse( $home->isArticle( null ) );
        $this->assertFalse( $none->isArticle( null ) );
        $this->assertFalse( $none->isArticle( '' ) );
        $this->assertTrue( $home->is( (object) ['id' => 101] ) );
        $this->assertTrue( $none->is( (object) ['id' => '101'] ) );
        $this->assertFalse( $none->is( (object) ['id' => 102] ) );
        $this->assertFalse( Joomla\HomeRoot::withoutArticle( (object) [] )->is( (object) [] ) );
        $this->assertFalse( Joomla\HomeRoot::withoutArticle()->is( (object) ['id' => 101] ) );
    }


    public function testDryRunDoesntListTheFeaturedHomeMenuItemOfAChangedRootPage(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert(
            ['home' => 1, 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component'] + $this->menuItem( 101, 'Home', 1, 1 )
        );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringContainsString( "  Menu item [101] Home (root page)\n", Artisan::output() );

        // root pages changed in Pagible aren't updated by the featured home menu item
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        PageView::where( 'tag', 'root' )->firstOrFail()->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringNotContainsString( 'Menu item [101]', Artisan::output() );
    }
}
