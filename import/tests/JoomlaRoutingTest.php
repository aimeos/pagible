<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Import\JoomlaAccess;
use Aimeos\Cms\Import\JoomlaCategoryItems;
use Aimeos\Cms\Import\JoomlaClaims;
use Aimeos\Cms\Import\JoomlaFolders;
use Aimeos\Cms\Import\JoomlaKind;
use Aimeos\Cms\Import\JoomlaLanguages;
use Aimeos\Cms\Import\JoomlaLevels;
use Aimeos\Cms\Import\JoomlaLinks;
use Aimeos\Cms\Import\JoomlaMenu;
use Aimeos\Cms\Import\JoomlaMenuItem;
use Aimeos\Cms\Import\JoomlaMenuLink;
use Aimeos\Cms\Import\JoomlaMenuNode;
use Aimeos\Cms\Import\JoomlaMenuRules;
use Aimeos\Cms\Import\JoomlaOrigin;
use Aimeos\Cms\Import\JoomlaKeptRedirect;
use Aimeos\Cms\Import\JoomlaReader;
use Aimeos\Cms\Import\JoomlaRedirects;
use Aimeos\Cms\Import\JoomlaRoutePath;
use Aimeos\Cms\Import\JoomlaRow;
use Aimeos\Cms\Import\JoomlaRouteOptions;
use Aimeos\Cms\Import\JoomlaHtmlUrls;
use Aimeos\Cms\Import\JoomlaAssignedUrls;
use Aimeos\Cms\Import\JoomlaHomeRoot;
use Aimeos\Cms\Import\JoomlaLanguageDomains;
use Aimeos\Cms\Import\JoomlaTranslationRoute;
use Aimeos\Cms\Import\JoomlaUrlAssignment;
use Aimeos\Cms\Import\JoomlaUrlClaim;
use Aimeos\Cms\Import\JoomlaUrls;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Tests of the Joomla routes, menus, URL paths, URL claims, links and redirects.
 */
class JoomlaRoutingTest extends JoomlaTestAbstract
{
    public function testMenuRulesSelectFirstItemPerTarget(): void
    {
        $all = (object) ['language' => '*'];
        $de = (object) ['language' => 'de-DE'];
        $select = fn( JoomlaMenuRules $rules, string $target, \stdClass $item, string $layout = '' ): bool => $rules->take( $target, $item, $layout );

        // items for all languages are used once per target and layout, the first one also claims the default layout
        $rules = new JoomlaMenuRules( true );
        $this->assertTrue( $select( $rules, 'article1', $all, 'blog' ) );
        $this->assertFalse( $select( $rules, 'article1', $all, 'blog' ) );
        $this->assertFalse( $select( $rules, 'article1', $all ) );
        $this->assertTrue( $select( $rules, 'article1', $all, 'list' ) );
        $this->assertTrue( $select( $rules, 'article2', $all ) );
        $this->assertFalse( $select( $rules, 'article2', $all ) );

        // without layout, the layout of the link of the menu item is used
        $blog = (object) ['language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'];
        $this->assertTrue( $rules->take( 'article3', $blog ) );
        $this->assertFalse( $rules->take( 'article3', $blog ) );
        $this->assertTrue( $rules->take( 'article3', $all, 'list' ) );

        // language specific items always replace the selected ones if languages are filtered and are ignored otherwise
        $this->assertTrue( $select( $rules, 'article2', $de ) );
        $this->assertTrue( $select( $rules, 'article2', $de ) );
        $this->assertFalse( $select( new JoomlaMenuRules( false ), 'article1', $de ) );
    }


    public function testMenu(): void
    {
        $root = (object) ['id' => 2, 'parent_id' => 1];
        $child = (object) ['id' => 3, 'parent_id' => 2];
        $menu = new JoomlaMenu( $this->items( [$root, $child, (object) ['id' => 4, 'parent_id' => 99], (object) ['id' => 5, 'parent_id' => 2]] ), new JoomlaAccess( new JoomlaLevels(), collect() ) );

        $roots = array_filter( iterator_to_array( $menu->tree() ), fn( JoomlaMenuNode $node ) => $node->parent === null );

        $this->assertSame( [2, 4], array_values( array_map( fn( JoomlaMenuNode $node ) => $node->item->id, $roots ) ) );
        $this->assertSame( [3, 5], array_column( $menu->children( 2 ), 'id' ) );
        $this->assertSame( [], $menu->children( 3 ) );
        $this->assertSame( 42, JoomlaMenuItem::article( (object) ['link' => 'index.php?option=com_content&view=article&id=42'] ) );
        $this->assertNull( JoomlaMenuItem::article( (object) ['link' => 'index.php?option=com_content&view=category&id=42'] ) );
        $this->assertNull( JoomlaMenuItem::article( (object) ['link' => 'index.php?option=com_contact&view=article&id=42'] ) );
        // menu items showing a category or listing its subcategories show the category, lists of the root category none
        $this->assertSame( 42, JoomlaMenuItem::category( (object) ['link' => 'index.php?option=com_content&view=category&id=42'] ) );
        $this->assertSame( 42, JoomlaMenuItem::category( (object) ['link' => 'index.php?option=com_content&view=categories&id=42'] ) );
        $this->assertNull( JoomlaMenuItem::category( (object) ['link' => 'index.php?option=com_content&view=categories&id=1'] ) );
        $this->assertNull( JoomlaMenuItem::category( (object) ['link' => 'index.php?option=com_content&view=article&id=42'] ) );
        $this->assertSame( ['option' => 'com_content', 'view' => 'category', 'layout' => 'blog'], JoomlaMenuItem::query( (object) ['link' => 'index.php?option=com_content&view=category&layout=blog'] ) );
        $this->assertSame( [], JoomlaMenuItem::query( (object) [] ) );
        $this->assertSame( [], JoomlaMenuItem::query( null ) );
    }


    public function testMenuTreeIsInPreOrder(): void
    {
        $item = fn( int $id, int $parentId ) => (object) ['id' => $id, 'parent_id' => $parentId];
        $menu = new JoomlaMenu( $this->items( [$item( 2, 1 ), $item( 3, 2 ), $item( 4, 99 ), $item( 5, 2 ), $item( 6, 3 ), $item( 7, 6 )] ), new JoomlaAccess( new JoomlaLevels(), collect() ) );
        $tree = array_map( fn( JoomlaMenuNode $node ) => [$node->item->id, $node->parent], iterator_to_array( $menu->tree() ) );

        // each item is directly followed by its descendants and refers to the position of its parent item
        $this->assertSame( [[2, null], [3, 0], [6, 1], [7, 2], [5, 0], [4, null]], $tree );
        $this->assertSame( [], iterator_to_array( ( new JoomlaMenu( $this->items( [] ), new JoomlaAccess( new JoomlaLevels(), collect() ) ) )->tree() ) );
    }


    public function testUrlsSelectTheHomeMenuItems(): void
    {
        $categories = $this->categories( [] );
        $home = fn( int $id, string $tag, array $more = [] ) => (object) ( $more + ['id' => $id, 'home' => 1, 'language' => $tag] );
        $items = $this->items( [
            $home( 20, 'en-GB', ['publish_down' => '2020-01-01 00:00:00'] ),
            $home( 21, '*' ),
            $home( 22, 'de-DE' ),
            (object) ['id' => 23, 'home' => 0, 'language' => 'en-GB'],
            $home( 24, 'en-GB' ),
            $home( 25, 'de-DE' ),
            $home( 26, '1-XX' ),
        ] );

        // with language filter, the home menu item of the import language is used before the one for all languages
        $this->languageFilter();
        $homes = $this->wiring( $this->site( $categories ), $items )->homes;
        $this->assertSame( 24, $homes->home()->id ?? null );
        $this->assertSame( ['de-DE' => 22], array_map( fn( $item ) => $item->id, $homes->otherHomes() ) );

        $homes = $this->wiring( $this->site( $categories ), $this->items( [$home( 22, 'de-DE' )] ) )->homes;
        $this->assertNull( $homes->home() );

        $this->languageFilter( false );
        $homes = $this->wiring( $this->site( $categories ), $items )->homes;
        $this->assertSame( 21, $homes->home()->id ?? null );
        $this->assertSame( [], $homes->otherHomes() );
    }


    public function testUrlsLinkTranslationsOnOwnDomainsToTheirHomePages(): void
    {
        $categories = $this->categories( [] );
        $home = fn( int $id, string $tag, array $more = [] ) => (object) ( $more + ['id' => $id, 'home' => 1, 'language' => $tag] );
        // the home menu item of German shows the translation of the article of the root page
        $article = ['type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=2'];
        $items = $this->items( [$home( 21, '*' ), $home( 22, 'de-DE', $article ), $home( 24, 'en-GB' )] );

        $this->languageFilter();
        $links = $this->importRun( $this->wiring( $this->site( $categories, ['de-DE:de:https://example.de'] ), $items, origin: new JoomlaOrigin( 'example.com' ) ),
            articles: $this->articles( translations: [2 => new JoomlaTranslationRoute( 'ueber-uns', 'de-DE' )], translated: [1 => [2]] ),
            home: new JoomlaHomeRoot( $home( 24, 'en-GB' ), 1, [(object) ['id' => 2, 'language' => 'de-DE']] ),
        )->links;
        $this->assertSame( 'https://example.de/', $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '2'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/', $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '1'], JoomlaLanguages::ALL ) );
    }


    public function testUrlsDontLinkTranslationsOfTheRootArticleReplacedByTheHomeMenuItemOfTheirLanguage(): void
    {
        $categories = $this->categories( [] );
        $home = fn( int $id, string $tag ) => (object) ['id' => $id, 'home' => 1, 'language' => $tag];
        // the home menu item of German shows no article, so the root page variant shows it instead of the translated article
        $items = $this->items( [$home( 22, 'de-DE' ), $home( 24, 'en-GB' )] );

        $this->languageFilter();
        $run = $this->importRun( $this->wiring( $this->site( $categories, ['de-DE:de:https://example.de'] ), $items, origin: new JoomlaOrigin( 'example.com' ) ),
            articles: $this->articles( [1 => new JoomlaRoutePath( null, 'about' ), 3 => new JoomlaRoutePath( null, 'about' )],
                [2 => new JoomlaTranslationRoute( 'ueber-uns', 'de-DE' )], [1 => 0, 3 => 0], [1 => [2]] ),
            home: new JoomlaHomeRoot( $home( 24, 'en-GB' ), 1, [(object) ['id' => 2, 'language' => 'de-DE']] ),
        );
        $this->assertNull( $run->links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '2'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/', $run->links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '1'], JoomlaLanguages::ALL ) );
        // the article of the root page and its translations claim no URL paths, so other rows keep their aliases
        $this->assertSame( '/about', $run->links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '3'], JoomlaLanguages::ALL ) );
    }


    public function testMenuLinkTargets(): void
    {
        $target = fn( string $kind, ?int $id, string $link, string $type = 'component' ) => $this->assertMenuLink( $kind, $id, (object) ['type' => $type, 'link' => $link] );

        $target( 'article', 42, 'index.php?option=com_content&view=article&catid=7&id=42' );
        $target( 'article', 42, 'index.php?option=com_content&id=42&view=article' );
        $target( 'article', null, 'index.php?option=com_content&view=article&id=abc' );
        $target( 'article', null, 'index.php?option=com_content&view=article' );
        $target( 'article', null, 'index.php?option=com_content&view=article&id=0' );
        $target( 'article', 1, 'index.php?option=com_content&view=article&id=1' );
        $target( 'category', null, 'index.php?option=com_content&view=category&id=0' );
        $target( 'category', 7, 'index.php?option=com_content&view=category&layout=blog&id=7' );
        $target( 'category', 1, 'index.php?option=com_content&view=category&id=1' );
        $target( '', null, 'index.php?option=com_contact&view=category&id=7' );
        $target( '', null, 'index.php?option=com_content' );
        // lists of the root category have no ID
        $target( 'categories', 2, 'index.php?option=com_content&view=categories&id=2' );
        $target( 'categories', null, 'index.php?option=com_content&view=categories&id=1' );
        $target( 'categories', null, 'index.php?option=com_content&view=categories' );
        // other views like the featured articles are no kinds, their view is returned by view()
        $target( '', null, 'index.php?option=com_content&view=featured&id=3' );
        $target( '', null, 'index.php?option=com_content&view=archive' );
        $target( '', null, 'index.php?option=com_content&view=form&id=3' );
        // the view and the layout are parsed separately from the kind
        $link = fn( string $link ) => JoomlaMenuLink::of( (object) ['type' => 'component', 'link' => $link] );
        $this->assertEquals( new JoomlaMenuLink( JoomlaMenuLink::KIND_OTHER, null, 'featured', 'blog' ), $link( 'index.php?option=com_content&view=featured&layout=blog&id=3' ) );
        $this->assertEquals( new JoomlaMenuLink( JoomlaMenuLink::KIND_CATEGORY, 7, 'category' ), $link( 'index.php?option=com_content&view=category&layout=default&id=7' ) );
        $this->assertEquals( new JoomlaMenuLink( JoomlaMenuLink::KIND_LINK ), JoomlaMenuLink::of( (object) ['type' => 'url', 'link' => 'index.php?option=com_content&view=article&id=42'] ) );
        $this->assertSame( 'featured', JoomlaMenuItem::view( (object) ['link' => 'index.php?option=com_content&view=featured&id=3'] ) );
        // menu items of other types aren't views even if their links are
        $this->assertNull( JoomlaMenuItem::view( (object) ['type' => 'url', 'link' => 'index.php?option=com_content&view=category&id=3'] ) );
        $this->assertNull( JoomlaMenuItem::view( null ) );
        // Joomla builds the URLs from the lists of the articles component and the home menu items
        $this->assertTrue( JoomlaMenuItem::routing( (object) ['link' => 'index.php?option=com_content&view=category&id=3'] ) );
        $this->assertTrue( JoomlaMenuItem::routing( (object) ['link' => 'index.php?option=com_content&view=categories&id=1'] ) );
        $this->assertTrue( JoomlaMenuItem::routing( (object) ['link' => 'index.php?option=com_content&view=archive'] ) );
        $this->assertTrue( JoomlaMenuItem::routing( (object) ['link' => 'index.php?option=com_users&view=login', 'home' => 1] ) );
        $this->assertFalse( JoomlaMenuItem::routing( (object) ['link' => 'index.php?option=com_content&view=article&id=3'] ) );
        $this->assertFalse( JoomlaMenuItem::routing( (object) ['type' => 'url', 'link' => 'index.php?option=com_content&view=category&id=3'] ) );
        // the default layout is the same as none
        $this->assertSame( '', JoomlaMenuItem::layout( (object) ['link' => 'index.php?option=com_content&view=category&layout=default&id=3'] ) );
        $this->assertSame( 'blog', JoomlaMenuItem::layout( (object) ['link' => 'index.php?option=com_content&view=category&layout=blog&id=3'] ) );
        $this->assertSame( '', JoomlaMenuItem::layout( null ) );
        $this->assertSame( 'blog', JoomlaMenuItem::normalLayout( 'blog' ) );
        // URL menu items linking to articles aren't articles, items without type are components
        $target( 'link', null, 'index.php?option=com_content&view=article&id=42', 'url' );
        $target( 'link', null, '', 'alias' );
        $target( 'folder', null, '#', 'url' );
        $target( 'folder', null, '', 'heading' );
        $target( '', null, 'index.php?option=com_content&view=article&id=42', 'container' );
        $this->assertMenuLink( 'article', 42, (object) ['link' => 'index.php?option=com_content&view=article&id=42'] );
        // only URL and alias menu items with URL are links
        $this->assertSame( JoomlaMenuLink::KIND_LINK, $this->kindOf( (object) ['type' => 'url', 'link' => 'https://example.com'] ) );
        $this->assertSame( JoomlaMenuLink::KIND_LINK, $this->kindOf( (object) ['type' => 'alias', 'link' => ''] ) );
        $this->assertNotSame( JoomlaMenuLink::KIND_LINK, $this->kindOf( (object) ['type' => 'url', 'link' => '#'] ) );
        $this->assertNotSame( JoomlaMenuLink::KIND_LINK, $this->kindOf( (object) ['type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=42'] ) );
    }


    public function testRoutes(): void
    {
        $categories = $this->categories( [
            1 => (object) ['id' => 1, 'alias' => 'root', 'parent_id' => 0],
            2 => (object) ['id' => 2, 'alias' => 'news', 'parent_id' => 1],
            3 => (object) ['id' => 3, 'alias' => 'local', 'parent_id' => 2],
            4 => (object) ['id' => 4, 'alias' => '', 'parent_id' => 3],
            5 => (object) ['id' => 5, 'alias' => 'sport', 'parent_id' => 1],
            6 => (object) ['id' => 6, 'alias' => 'loop', 'parent_id' => 7],
            7 => (object) ['id' => 7, 'alias' => 'pool', 'parent_id' => 6],
        ] );
        $items = [
            (object) ['id' => 101, 'path' => 'company/blog', 'alias' => 'blog', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'],
            (object) ['id' => 102, 'path' => 'blog-de', 'alias' => 'blog-de', 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=category&id=2'],
            (object) ['id' => 103, 'path' => 'blog-fr', 'alias' => 'blog-fr', 'language' => 'fr-FR', 'link' => 'index.php?option=com_content&view=category&id=2'],
            (object) ['id' => 104, 'path' => '/topics/', 'alias' => 'topics', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=0'],
            (object) ['id' => 105, 'home' => 1, 'path' => 'home', 'alias' => 'home', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=1'],
        ];
        $routes = $this->routes( $categories, [$items[0], $items[2], $items[1], $items[4]] );
        // with strict routing, the active menu item is the one of the categories without the home menu item
        $strict = $this->routes( $categories, [$items[0], $items[2], $items[1], $items[4]], new JoomlaRouteOptions( noIds: false, strict: true ) );

        // the last menu item in a specific language is used before the ones for all languages
        $this->assertSame( 102, $routes->categoryItem( 2 )?->id );
        $this->assertNull( $routes->categoryItem( 3 ) );
        $this->assertSame( 102, $strict->active( 3 )?->id );
        $this->assertSame( 'blog-de/3-local/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertSame( 'blog-de/3-local', $routes->category( 3 ) );
        $this->assertSame( 'blog-de/8-top', $routes->article( (object) ['id' => 8, 'alias' => 'top', 'catid' => 2] ) );
        // categories without alias have no segment
        $this->assertSame( 'blog-de/3-local/9-deep', $routes->article( (object) ['id' => 9, 'alias' => 'deep', 'catid' => 4] ) );
        $this->assertNull( $routes->article( (object) ['id' => 10, 'alias' => '', 'catid' => 3] ) );
        $this->assertNull( $routes->category( 4 ) );
        $this->assertNull( $routes->category( 99 ) );
        // the list of all categories is used for categories without menu item, the home item has no path
        $this->assertSame( 105, $strict->active( 5 )?->id );
        $this->assertSame( '5-sport/11-run', $routes->article( (object) ['id' => 11, 'alias' => 'run', 'catid' => 5] ) );
        $this->assertSame( 105, $strict->active( 99 )?->id );
        // parent category loops don't hang
        $this->assertSame( 105, $strict->active( 6 )?->id );

        // without language filter, Joomla uses only the menu items for all languages
        $routes = $this->routes( $categories, [$items[1], $items[0], $items[2]], rules: new JoomlaMenuRules( false ) );
        $this->assertSame( 101, $routes->categoryItem( 2 )?->id );
        $this->assertSame( 'company/blog/3-local', $routes->category( 3 ) );
        $routes = $this->routes( $categories, [$items[1], (object) ['id' => 106, 'alias' => 'themen', 'language' => 'de-DE', 'link' => 'index.php?option=com_content&view=categories&id=0']], rules: new JoomlaMenuRules( false ) );
        $this->assertNull( $routes->categoryItem( 2 ) );
        $this->assertNull( $routes->active( 3 ) );
        $this->assertNull( $routes->category( 3 ) );

        $routes = $this->routes( $categories, array_slice( $items, 0, 1 ), new JoomlaRouteOptions( noIds: true, strict: false ) );
        $this->assertSame( 'company/blog/local/about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertNull( $routes->active( 5 ) );

        $routes = $this->routes( $categories, [$items[3]] );
        $this->assertSame( 'topics/2-news/3-local/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        // menu items of the same category showing it or listing its subcategories don't replace each other
        $list = (object) ['id' => 107, 'path' => 'news-list', 'alias' => 'news-list', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=2'];
        $routes = $this->routes( $categories, [$items[0], $list] );
        $this->assertSame( 101, $routes->categoryItem( 2 )?->id );
        $this->assertSame( 107, $routes->listItem( 2 )?->id );

        // the root category isn't in the categories read from the database
        $routes = $this->routes( $categories->except( [1] ), [$items[3]] );
        $this->assertSame( 'topics/5-sport', $routes->category( 5 ) );

        // the legacy router of Joomla 3 adds the ID of the category to the first category segment only
        $legacy = new JoomlaRouteOptions( noIds: false, strict: false, legacy: true );
        $routes = $this->routes( $categories, [$items[0]], $legacy );
        $this->assertSame( 'company/blog/4-local/9-deep', $routes->article( (object) ['id' => 9, 'alias' => 'deep', 'catid' => 4] ) );
        $this->assertSame( 'company/blog/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 2] ) );
        $this->assertSame( 'company/blog/3-local', $routes->category( 3 ) );
        $this->assertNull( $routes->article( (object) ['id' => 11, 'alias' => 'run', 'catid' => 5] ) );
        $this->assertNull( $routes->category( 99 ) );
        // the home menu item is used if there's no menu item for the categories, the root category has no segment
        $home = (object) ['id' => 107, 'home' => 1, 'alias' => 'home', 'language' => '*', 'link' => 'index.php?option=com_content&view=featured'];
        $routes = $this->routes( $categories, [$home, $items[0]], $legacy );
        $this->assertSame( '5-sport/11-run', $routes->article( (object) ['id' => 11, 'alias' => 'run', 'catid' => 5] ) );
        $this->assertSame( 'company/blog/3-local', $routes->category( 3 ) );
        $routes = $this->routes( $categories, [$home], $legacy );
        $this->assertSame( '3-news/local/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertSame( '3-news/local', $routes->category( 3 ) );
        // categories whose parents don't lead to the root category aren't routed
        $this->assertNull( $routes->category( 6 ) );
        // the hidden "sef_advanced_link" option removes the IDs
        $routes = $this->routes( $categories, [$home], new JoomlaRouteOptions( noIds: true, strict: false, legacy: true ) );
        $this->assertSame( 'news/local/about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        // home menu items of other components aren't used
        $routes = $this->routes( $categories, [(object) ( ['link' => 'index.php?option=com_contact&view=featured'] + (array) $home )], $legacy );
        $this->assertSame( 'component/content/article/3-news/local/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertSame( 'component/content/category/3-news/local', $routes->category( 3 ) );
        // the category ID of menu items of other components doesn't stop the categories
        $routes = $this->routes( $categories, [(object) ( ['link' => 'index.php?option=com_contact&view=category&id=2'] + (array) $home )], $legacy );
        $this->assertSame( 'component/content/article/3-news/local/7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );

        // like the menu rules of Joomla, a later category blog replaces a category list but not the other way round
        $list = (object) ['id' => 108, 'path' => 'list', 'alias' => 'list', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&id=5'];
        $blog = (object) ['id' => 109, 'path' => 'blog', 'alias' => 'blog', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5'];
        $this->assertSame( 109, $this->routes( $categories, [$list, $blog] )->categoryItem( 5 )?->id );
        $this->assertSame( 109, $this->routes( $categories, [$blog, $list] )->categoryItem( 5 )?->id );
        $this->assertSame( 108, $this->routes( $categories, [$list, $list, (object) ( ['id' => 110] + (array) $list )] )->categoryItem( 5 )?->id );

        // categories below categories Joomla doesn't route, e.g. archived ones, get no URL path
        $routes = $this->routes( $categories->except( [2] ), [$items[3]] );
        $this->assertNull( $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertNull( $routes->category( 3 ) );
        $this->assertSame( 'topics/5-sport/11-run', $routes->article( (object) ['id' => 11, 'alias' => 'run', 'catid' => 5] ) );

        // Joomla 4+ uses the home category blog or list for articles without menu item and adds only their segment
        $start = (object) ['id' => 111, 'home' => 1, 'alias' => 'start', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=5'];
        $routes = $this->routes( $categories, [$start] );
        $this->assertSame( '7-about', $routes->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        $this->assertSame( '11-run', $routes->article( (object) ['id' => 11, 'alias' => 'run', 'catid' => 5] ) );
        $this->assertNull( $routes->category( 3 ) );
        $this->assertNull( $this->routes( $categories, [$home] )->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );
        // with strict routing of Joomla 5.2+, the home menu item isn't used
        $this->assertNull( $this->routes( $categories, [$start], new JoomlaRouteOptions( noIds: false, strict: true ) )->article( (object) ['id' => 7, 'alias' => 'about', 'catid' => 3] ) );

        // the legacy router of Joomla 3.7+ uses the menu rules too, so the first menu item of a layout replaces the existing ones
        $this->assertSame( 109, $this->routes( $categories, [$list, $blog], $legacy )->categoryItem( 5 )?->id );
        $this->assertSame( 109, $this->routes( $categories, [$blog, $list], $legacy )->categoryItem( 5 )?->id );

        // the menu items listing the categories are used for the lists of their parent categories, the root category by 0 or 1
        $routes = $this->routes( $categories, [$items[3]] );
        $this->assertSame( 104, $routes->listItem( 0 )?->id );
        $this->assertSame( 104, $routes->listItem( 1 )?->id );
        $this->assertNull( $routes->listItem( 2 ) );

        $this->assertSame( 'blog', JoomlaMenuItem::path( (object) ['alias' => 'blog'] ) );
        $this->assertSame( 'my-blog', JoomlaMenuItem::path( (object) ['title' => 'My blog'] ) );
        $this->assertSame( '', JoomlaMenuItem::path( (object) ['home' => 1, 'alias' => 'home'] ) );
        $this->assertSame( 'company/blog', JoomlaMenuItem::path( (object) ['path' => '/company/blog/', 'alias' => 'blog'] ) );
    }


    public function testRedirectsPerformed(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'extensions' )->insert( ['element' => 'com_redirect', 'params' => '{}'] );
        // returns the old URLs of the performed redirects in the basic or advanced mode with the options of the redirect plugin
        $performed = function( array $rows, bool $advanced = false, array $params = [] ) use ( $db ): array {
            $db->table( 'redirect_links' )->delete();
            $db->table( 'redirect_links' )->insert( array_map( fn( $row, $id ) => $row + ['id' => $id + 1, 'new_url' => 'about'], $rows, array_keys( $rows ) ) );
            $db->table( 'extensions' )->where( 'element', 'com_redirect' )->update( ['params' => json_encode( ['mode' => $advanced ? '1' : '0'] )] );
            $db->table( 'extensions' )->where( 'element', 'redirect' )->update( ['params' => json_encode( $params )] );
            $reader = $this->reader( $db );

            return JoomlaRedirects::all( $reader, $this->site( $reader->categories(), reader: $reader )->plugins )->pluck( 'old_url' )->all();
        };
        $row = fn( int $header, string $url = 'old' ) => [['header' => $header, 'old_url' => $url]];

        $this->assertSame( ['old'], $performed( $row( 410 ) ) );
        $this->assertSame( [], $performed( $row( 410 ), true ) );
        $this->assertSame( [], $performed( $row( 299 ), true ) );
        $this->assertSame( ['old'], $performed( $row( 301 ), true ) );
        $this->assertSame( ['old'], $performed( $row( 0 ), true ) );
        // the exclude terms are compared with the decoded path, query and fragment of the old URL
        $this->assertSame( [], $performed( $row( 301, 'https://x.test/a%20b?x#y' ), false, ['exclude_urls' => [['term' => '/a b?x#y']]] ) );
        $this->assertSame( ['old'], $performed( $row( 301 ), false, ['exclude_urls' => 'broken'] ) );
        // unparsable URLs aren't excluded
        $this->assertSame( ['http:///old'], $performed( $row( 301, 'http:///old' ), false, ['exclude_urls' => [['term' => 'old']]] ) );

        // the performed redirects keep their order and are numbered again
        $rows = [['header' => 410, 'old_url' => 'a'], ['header' => 301, 'old_url' => 'b'], ['header' => 302, 'old_url' => 'c']];
        $this->assertSame( [0 => 'b', 1 => 'c'], $performed( $rows, true ) );
    }


    public function testLinksToViewsWithoutIdUseTheMenuItemsOfTheLanguageOfTheContent(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $item = fn( int $id, string $tag, string $view ) => (object) ['id' => $id, 'type' => 'component', 'title' => $view, 'alias' => $view . $id, 'language' => $tag,
            'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=' . $view, 'home' => 0];
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $router = $this->wiring( $this->site( $this->categories( [] ), ['de-DE:de'] ), $this->items( [$item( 101, 'en-GB', 'featured' ), $item( 103, 'en-GB', 'archive' ), $item( 201, 'de-DE', 'featured' ), $item( 203, 'de-DE', 'archive' )] ) )->router;

        // links in content of other languages use the menu items of their language, the imported pages the ones of the imported language
        $this->assertSame( 203, $router->linkRoutes( 'de-DE' )->viewItem( 'archive' )?->id );
        $this->assertSame( 201, $router->linkRoutes( 'de-DE' )->viewItem( 'featured' )?->id );
        $this->assertSame( 103, $router->linkRoutes( 'en-GB' )->viewItem( 'archive' )?->id );
        $this->assertSame( 103, $router->linkRoutes( JoomlaLanguages::ALL )->viewItem( 'archive' )?->id );
        $this->assertSame( 103, $router->contentRoutes( 'de-DE' )->viewItem( 'archive' )?->id );
        // the routes of each language select only the menu items of the language and for all languages
        $this->assertSame( 203, $router->languageRoutes( 'de-DE' )->viewItem( 'archive' )?->id );
        $this->assertNull( $router->languageRoutes( 'fr-FR' )->viewItem( 'archive' ) );
    }


    public function testRoutesOfCategoryListsAndTheActiveHomeMenuItem(): void
    {
        $categories = $this->categories( [
            2 => (object) ['id' => 2, 'alias' => 'news', 'parent_id' => 1],
            3 => (object) ['id' => 3, 'alias' => 'local', 'parent_id' => 2],
            5 => (object) ['id' => 5, 'alias' => 'sport', 'parent_id' => 1],
        ] );
        $list = (object) ['id' => 101, 'path' => 'topics', 'alias' => 'topics', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=2'];
        $blog = (object) ['id' => 102, 'path' => 'blog', 'alias' => 'blog', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];
        $sub = (object) ['id' => 103, 'path' => 'local', 'alias' => 'local', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=3'];
        $home = (object) ['id' => 104, 'home' => 1, 'path' => 'home', 'alias' => 'home', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories&id=5'];
        $all = (object) ['id' => 105, 'path' => 'all', 'alias' => 'all', 'language' => '*', 'link' => 'index.php?option=com_content&view=categories'];

        // Joomla lists the subcategories of the root category if the link has no ID
        $this->assertSame( 105, $this->routes( $categories, [$all] )->listItem( 0 )?->id );

        // the menu item listing the subcategories is used for the category itself unless a parent has a nearer category menu item
        $routes = $this->routes( $categories, [$list] );
        $this->assertSame( 'topics', $routes->category( 2 ) );
        $this->assertSame( 'topics/3-local', $routes->category( 3 ) );
        $this->assertSame( 'blog/3-local', $this->routes( $categories, [$sub, $blog] )->category( 3 ) );
        $this->assertSame( 'local', $this->routes( $categories, [$sub] )->category( 3 ) );

        // Joomla 4+ also uses a home menu item listing categories for articles without menu item
        $article = (object) ['id' => 7, 'alias' => 'about', 'catid' => 3];
        $this->assertSame( '7-about', $this->routes( $categories, [$home] )->article( $article ) );
        $this->assertNull( $this->routes( $categories, [$home], new JoomlaRouteOptions( noIds: false, strict: true ) )->article( $article ) );

        // the home menu item is the active menu item if there's none for the categories, except with strict routing
        $this->assertSame( 104, $this->routes( $categories, [$home] )->active( 3 )?->id );
        $this->assertSame( 102, $this->routes( $categories, [$home, $blog] )->active( 3 )?->id );
        $this->assertNull( $this->routes( $categories, [$home], new JoomlaRouteOptions( noIds: false, strict: true ) )->active( 3 ) );
        $this->assertNull( $this->routes( $categories, [] )->active( 3 ) );
    }


    public function testLegacyRoutesOfCategoriesShownByTheHomeMenuItem(): void
    {
        $categories = $this->categories( [
            2 => (object) ['id' => 2, 'alias' => 'news', 'parent_id' => 1],
            3 => (object) ['id' => 3, 'alias' => 'local', 'parent_id' => 2],
            4 => (object) ['id' => 4, 'alias' => '', 'parent_id' => 1],
            5 => (object) ['id' => 5, 'alias' => 'sport', 'parent_id' => 4],
        ] );
        $home = (object) ['id' => 104, 'home' => 1, 'path' => 'home', 'alias' => 'home', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&id=3'];
        $blog = (object) ['id' => 102, 'path' => 'blog', 'alias' => 'blog', 'language' => '*', 'link' => 'index.php?option=com_content&view=category&layout=blog&id=2'];

        // both routers use the menu item of the parent category instead of the home menu item showing the category itself
        foreach( [false, true] as $legacy )
        {
            $options = new JoomlaRouteOptions( noIds: false, strict: false, legacy: $legacy );
            $this->assertNull( $this->routes( $categories, [$home], $options )->category( 3 ) );
            $this->assertSame( 'blog/3-local', $this->routes( $categories, [$blog, $home], $options )->category( 3 ) );
        }

        // parent categories without alias add no empty segment, the ID is added to the first category with alias
        $this->assertSame( '5-sport', $this->routes( $categories, [$home], new JoomlaRouteOptions( noIds: false, strict: false, legacy: true ) )->category( 5 ) );
        $this->assertSame( 'sport', $this->routes( $categories, [$home], new JoomlaRouteOptions( noIds: true, strict: false, legacy: true ) )->category( 5 ) );
    }


    public function testClaimsAppendIdsToUsedPaths(): void
    {
        $claims = new JoomlaClaims();
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 1, 'about' ) );
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 5, 'about' ) );
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::CATEGORY, 5, 'about' ) );
        $claims->claim( JoomlaUrlClaim::translation( JoomlaKind::ARTICLE, 7, 'about', 'de' ) );
        $claims->claim( JoomlaUrlClaim::translation( JoomlaKind::ARTICLE, 8, 'about', 'de' ) );

        // the paths are claimed per language domain, later rows get their ID and a counter appended, only the changed paths are kept
        $this->assertSame( ['' => ['a5' => 'about-5', 'c5' => 'about-5-2'], 'de' => ['ta8' => 'about-8']], $claims->slugs() );

        $this->assertFalse( $claims->claimPath( 'about-5' ) );
        $this->assertTrue( $claims->claimPath( 'about-5', 'de' ) );
        $this->assertFalse( $claims->claimPath( 'about-5', 'de' ) );
    }


    public function testClaimsComparePathsCaseInsensitive(): void
    {
        $claims = new JoomlaClaims();
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 1, 'About' ) );
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 5, 'about' ) );

        // the unique index of the page paths is case insensitive in MySQL, so paths differing only in case are the same, the claimed ones keep their case
        $this->assertSame( ['' => ['a5' => 'about-5']], $claims->slugs() );
        $this->assertFalse( $claims->claimPath( 'ABOUT' ) );
        $this->assertTrue( $claims->claimPath( 'ABOUT', 'de' ) );
    }


    public function testClaimsComparePathsAccentInsensitive(): void
    {
        $claims = new JoomlaClaims();
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 1, 'café' ) );
        $claims->claim( JoomlaUrlClaim::row( JoomlaKind::ARTICLE, 5, 'cafe' ) );

        // the unique index of the page paths in MySQL also ignores accents, the claimed paths keep them
        $this->assertSame( ['' => ['a5' => 'cafe-5']], $claims->slugs() );
        $this->assertFalse( $claims->claimPath( 'CAFÉ' ) );
        $this->assertTrue( $claims->claimPath( 'cafè', 'de' ) );
    }


    public function testKindKeys(): void
    {
        $this->assertSame( 'a5', JoomlaKind::key( JoomlaKind::ARTICLE, 5 ) );
        $this->assertSame( 'tc7', JoomlaKind::translationKey( JoomlaKind::CATEGORY, '7' ) );
        $this->assertSame( JoomlaKind::ARTICLE_TRANSLATION . '3', JoomlaKind::translationKey( JoomlaKind::ARTICLE, 3 ) );
    }


    public function testUrlsUseTheCategoryMenuItemsOfTheMenu(): void
    {
        $categories = $this->categories( [
            2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1],
            3 => (object) ['id' => 3, 'title' => 'Local', 'alias' => 'local', 'parent_id' => 2, 'level' => 2, 'published' => 1],
        ] );
        $site = $this->site( $categories );
        $item = ['type' => 'component', 'parent_id' => 1, 'language' => '*'];
        // the menu items of other menus are fetched once when the import is set up and passed to the wiring
        $local = (object) ['id' => 22, 'menutype' => 'footer', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=categories&id=3', 'published' => 1, 'language' => '*'];
        $run = $this->importRun( $this->wiring( $site, origin: new JoomlaOrigin( 'example.com' ), items: $this->items( [$local] ), menu: $this->items( [
            // the home menu item shows the article with the same ID as the category
            (object) ( ['id' => 20, 'home' => 1, 'title' => 'Home', 'alias' => 'home', 'link' => 'index.php?option=com_content&view=article&id=2'] + $item ),
            (object) ( ['id' => 21, 'title' => 'Topics', 'alias' => 'topics', 'link' => 'index.php?option=com_content&view=categories&id=2'] + $item ),
        ] ) ), articles: $this->articles( categoryIds: [9 => 3] ), folders: new JoomlaFolders( categories: [3 => $categories[3]] ) );
        $urls = $run->urls;
        $links = $run->links;

        $this->assertSame( '/topics', $urls->categoryUrl( 2 ) );
        // menu items of other menus listing the subcategories link to the page of their category
        $this->assertNotNull( $local = $urls->categoryUrl( 3 ) );
        $this->assertSame( $local, $links->resolve( ['Itemid' => '22'], JoomlaLanguages::ALL ) );
        // links with menu item ID use it only if it shows the same, IDs with alias are the same as their numeric part like in Joomla
        $this->assertSame( $local, $links->resolve( ['option' => 'com_content', 'view' => 'categories', 'id' => '3:local', 'Itemid' => '22'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['option' => 'com_content', 'view' => 'categories', 'id' => '4', 'Itemid' => '22'], JoomlaLanguages::ALL ) );
        // the IDs are compared as integers like Joomla does
        $this->assertSame( $local, $links->resolve( ['option' => 'com_content', 'view' => 'categories', 'id' => '03', 'Itemid' => '022'], JoomlaLanguages::ALL ) );
        $this->assertSame( $local, $links->resolve( ['option' => 'com_content', 'view' => 'categories', 'id' => '3abc', 'Itemid' => '22'], JoomlaLanguages::ALL ) );

        // the menu items of other menus are taken from the published menu items fetched at once
        $db = DB::connection( 'joomla' );
        $db->enableQueryLog();
        $this->assertNull( $links->resolve( ['option' => 'com_content', 'view' => 'featured', 'Itemid' => '22'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['Itemid' => '99'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['option' => 'com_content', 'view' => 'featured', 'Itemid' => '99'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/topics', $links->resolve( ['Itemid' => '21'], JoomlaLanguages::ALL ) );
        $this->assertSame( [], $db->getQueryLog() );
        $db->disableQueryLog();

        // categories shown by menu items of the menu don't claim their aliases, so later rows keep them
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $topics = (object) ( ['id' => 21, 'title' => 'Topics', 'alias' => 'topics', 'link' => 'index.php?option=com_content&view=category&id=2'] + $item );
        $links = $this->importRun( $this->wiring( $site, menu: $this->items( [$topics] ), origin: new JoomlaOrigin( 'example.com' ) ),
            articles: $this->articles( translations: [5 => new JoomlaTranslationRoute( 'news', 'de-DE' )] ),
            folders: new JoomlaFolders( categories: [2 => $news] ),
        )->links;
        $this->assertSame( '/news', $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => '5'], JoomlaLanguages::ALL ) );
    }


    public function testMenuSelectsThePagesAndCategoryItems(): void
    {
        $category = fn( int $id ) => (object) ['id' => $id, 'title' => 'Cat ' . $id, 'alias' => 'cat-' . $id, 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $site = $this->site( $this->categories( [2 => $category( 2 ), 3 => $category( 3 ), 4 => $category( 4 )] ) );
        $item = fn( int $id, string $link, array $more = [] ) => (object) ( $more + ['id' => $id, 'type' => 'component', 'parent_id' => 1, 'link' => $link] );
        $list = $item( 9, 'index.php?option=com_content&view=categories&id=2' );
        $cat = $item( 7, 'index.php?option=com_content&view=category&id=2' );
        $restricted = $item( 5, 'index.php?option=com_content&view=category&id=3', ['access' => 2] );
        $other = $item( 6, 'index.php?option=com_content&view=categories&id=3' );
        $home = $item( 4, 'index.php?option=com_content&view=category&id=4', ['home' => 1] );
        $items = $this->items( [
            $list, $cat, $restricted, $other, $home,
            $item( 3, 'index.php?option=com_content&view=article&id=1' ),
            $item( 2, '', ['type' => 'heading'] ),
            $item( 8, 'https://example.org', ['type' => 'url'] ),
            $item( 10, 'index.php?option=com_content&view=category&id=0' ),
            $item( 11, 'index.php?option=com_content&view=categories&id=1' ),
            $item( 12, 'index.php?option=com_content&view=category&id=99' ),
            $item( 13, 'index.php?option=com_content&view=featured' ),
        ] );
        $wiring = $this->wiring( $site, $items, $items );
        $menu = $wiring->menu;
        $categoryItems = $wiring->categoryItems;

        // folders, links, lists of the root category and the menu items showing a category first get own pages, except the home menu item
        // and restricted menu items, unknown categories and the menu items whose category is already shown by another one
        $this->assertSame( [2, 6, 7, 8, 11], array_keys( $categoryItems->menuPages() ) );
        $this->assertTrue( $categoryItems->hasMenuPage( 7 ) );
        $this->assertFalse( $categoryItems->hasMenuPage( 9 ) );
        $this->assertFalse( $categoryItems->hasMenuPage( 5 ) );
        $this->assertFalse( $categoryItems->hasMenuPage( 4 ) );
        $this->assertFalse( $categoryItems->hasMenuPage( 3 ) );
        $this->assertFalse( $categoryItems->hasMenuPage( 12 ) );
        $this->assertNotNull( $menu->item( 3 ) );
        $this->assertSame( $home, $menu->item( 4 ) );
        $this->assertNull( $menu->item( 99 ) );

        // category menu items are used before the lists of the subcategories, restricted ones aren't used and the home menu item shows its category
        $this->assertSame( $cat, $categoryItems->categoryPageItem( 2 ) );
        $this->assertSame( $other, $categoryItems->categoryPageItem( 3 ) );
        $this->assertSame( $home, $categoryItems->categoryPageItem( 4 ) );
        $this->assertNull( $categoryItems->categoryPageItem( 1 ) );
        $this->assertNull( $categoryItems->categoryPageItem( 99 ) );

        // only the home menu item of the site uses the root page, also one of another menu, other home menu items are imported like the other ones
        $start = $item( 30, 'index.php?option=com_content&view=category&id=3', ['home' => 1, 'menutype' => 'footer'] );
        $selected = new JoomlaCategoryItems( $site, $wiring->router, $menu, $items->keyBy( 'id' ), $start );
        $this->assertSame( $start, $selected->categoryPageItem( 3 ) );
        $this->assertSame( $cat, $selected->categoryPageItem( 2 ) );
        $this->assertSame( [2, 4, 7, 8, 11], array_keys( $selected->menuPages() ) );
    }


    public function testCategoriesGetPagesOnlyIfArticlesAreImportedBelowThem(): void
    {
        $item = fn( int $id, string $link, array $more = [] ) => (object) ( $more + ['id' => $id, 'menutype' => 'main', 'type' => 'component',
            'title' => 'Item ' . $id, 'alias' => 'item-' . $id, 'parent_id' => 1, 'level' => 1, 'published' => 1, 'language' => '*', 'link' => $link] );
        $category = fn( int $id, int $parent, int $level ) => (object) ['id' => $id, 'title' => 'Cat ' . $id, 'alias' => 'cat-' . $id,
            'parent_id' => $parent, 'level' => $level, 'published' => 1];
        $home = $item( 20, 'index.php?option=com_content&view=category&layout=blog&id=4', ['home' => 1] );
        $public = $item( 21, 'index.php?option=com_content&view=article&id=6' );
        $restricted = $item( 22, 'index.php?option=com_content&view=article&id=7', ['access' => 2] );

        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [2 => $category( 2, 1, 1 ), 3 => $category( 3, 2, 2 ), 4 => $category( 4, 2, 2 ),
            5 => $category( 5, 2, 2 ), 6 => $category( 6, 3, 3 ), 7 => $category( 7, 4, 3 )] ) );
        $wiring = $this->wiring( $site, $this->items( [$home, $public, $restricted] ), $this->items( [$home, $public, $restricted] ) );
        $categoryItems = $wiring->categoryItems;

        // only articles of public menu items of the imported menu are imported by them
        $this->assertTrue( $wiring->menu->importsArticle( 6 ) );
        $this->assertFalse( $wiring->menu->importsArticle( 7 ) );
        $this->assertFalse( $wiring->menu->importsArticle( 8 ) );

        // the category shown by the public home menu item uses the root page, other home menu items show none
        $this->assertSame( 4, $categoryItems->homeCategory( $home ) );
        $this->assertSame( 4, $categoryItems->homeCatId() );
        $this->assertNull( $categoryItems->homeCategory( $public ) );
        $this->assertNull( $categoryItems->homeCategory( $item( 23, 'index.php?option=com_content&view=category&id=99', ['home' => 1] ) ) );

        // the categories of the articles and their parents below the top level get pages, not the ones of the menu items (4)
        // and the categories without articles (2 is top level, 5 is empty) or with articles of public menu items only (cat 3 for article 6)
        $this->assertSame( [6 => true, 3 => true], $categoryItems->pages( [1 => 6, 2 => 4, 6 => 3] ) );
        $this->assertSame( [3 => true], $categoryItems->pages( [6 => 3, 7 => 3] ) );
        $this->assertSame( [], $categoryItems->pages( [6 => 3] ) );
        // the article of the root page isn't imported below its category and the category of the root page ends the chain of parents
        $this->assertSame( [], $categoryItems->pages( [8 => 5], 8 ) );
        $this->assertSame( [5 => true], $categoryItems->pages( [8 => 5], 1 ) );
        $this->assertSame( [7 => true], $categoryItems->pages( [9 => 7] ) );
        $this->assertSame( [], $categoryItems->pages( [] ) );
    }


    public function testPathsUseTheMenuItemsOfTheMenuFirst(): void
    {
        $categories = $this->categories( [
            2 => $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1],
            3 => $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 1, 'level' => 1, 'published' => 1],
            4 => $homeCategory = (object) ['id' => 4, 'title' => 'Home', 'alias' => 'home', 'parent_id' => 1, 'level' => 1, 'published' => 1],
            6 => (object) ['id' => 6, 'title' => 'Misc', 'alias' => 'misc', 'parent_id' => 1, 'level' => 1, 'published' => 1],
            7 => (object) ['id' => 7, 'title' => 'Old', 'alias' => 'old', 'parent_id' => 3, 'level' => 2, 'published' => 0],
        ] );
        $site = $this->site( $categories );
        $item = fn( int $id, string $alias, string $link, array $more = [] ) => (object) ( $more + ['id' => $id, 'type' => 'component', 'parent_id' => 1, 'level' => 1,
            'title' => $alias, 'alias' => $alias, 'path' => $alias, 'language' => '*', 'link' => 'index.php?option=com_content&' . $link] );
        $inMenu = $item( 10, 'topics', 'view=category&id=2' );
        $otherNews = $item( 20, 'other-news', 'view=category&id=2' );
        $otherBlog = $item( 21, 'other-blog', 'view=category&id=3' );
        $home = $item( 22, 'start', 'view=category&id=4', ['home' => 1] );
        $list = $item( 23, 'all', 'view=categories&id=0' );
        $team = $item( 24, 'team', 'view=article&id=1' );
        $wiring = $this->wiring( $site, $this->items( [$otherNews, $otherBlog, $home, $list, $inMenu, $team] ), $this->items( [$inMenu] ) );
        // the article in the category "Blog" is imported below its page
        $urls = $wiring->assemble( articles: $this->articles( categoryIds: [8 => 3] ), folders: new JoomlaFolders( [3 => $blog] ), home: new JoomlaHomeRoot(), redirects: [] )->urls;
        $paths = $wiring->urlContext->paths;
        $pages = $wiring->categoryItems;

        // the menu items of the imported menu are used before the ones of other menus
        $this->assertSame( '/topics', $urls->categoryUrl( 2 ) );
        $this->assertNull( $urls->categoryUrl( 5 ) );
        $this->assertSame( $list, $wiring->router->categoryRoutes( 1 )->listItem( 1 ) );
        $this->assertNull( $wiring->router->categoryRoutes( 2 )->listItem( 2 ) );
        // categories of other menu items get their paths unless the menu shows them or it's the home menu item
        $this->assertNull( $pages->otherItem( $news ) );
        $this->assertSame( $otherBlog, $pages->otherItem( $blog ) );
        $this->assertNull( $pages->otherItem( $homeCategory ) );
        // top-level categories only get own pages if a menu item of another menu shows them
        $this->assertNull( $pages->category( 2 ) );
        $this->assertSame( $blog, $pages->category( 3 ) );
        $this->assertNull( $pages->category( 4 ) );
        $this->assertNull( $pages->category( 5 ) );
        $this->assertNull( $pages->category( 7 ) );
        // top-level categories are shown, unpublished and unknown ones aren't
        $this->assertSame( $news, $site->category( 2 ) );
        $this->assertNull( $site->category( 7 ) );
        $this->assertNull( $site->category( 5 ) );
        // categories without own page and menu item of the imported menu have no URL
        $this->assertNull( $urls->categoryUrl( 6 ) );
        $this->assertNull( $urls->categoryUrl( 7 ) );
        $this->assertSame( '/other-blog', $urls->categoryUrl( 3 ) );
        $this->assertSame( 'other-blog', $paths->categoryRoute( $blog )->path() );

        // articles use the paths of their menu items except of the home menu item
        $article = (object) ['id' => 1, 'catid' => 0, 'alias' => 'about', 'language' => '*'];
        $this->assertSame( 'team', $paths->articleRoute( $article, $team )->path() );
        $this->assertEquals( $paths->articleRoute( $article ), $paths->articleRoute( $article, $home ) );
        $this->assertSame( 'team', $paths->articleTranslationRoute( $article, $team )->path() );
        $this->assertSame( 'topics', $paths->menuTranslationRoute( $inMenu )->path() );
        // home menu items use their alias as path for the pages of their translations
        $this->assertSame( 'home', $paths->itemPath( (object) ['id' => 9, 'home' => 1, 'alias' => 'home', 'path' => 'ignored'] ) );
    }


    public function testUrlsAssignPathsAndResolveLinks(): void
    {
        $categories = $this->categories( [2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1]] );
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $categories );
        $heading = (object) ['id' => 10, 'type' => 'heading', 'title' => 'About', 'alias' => 'about', 'link' => '', 'parent_id' => 1];
        $articles = $this->articles(
            [1 => new JoomlaRoutePath( null, 'about' ), 5 => new JoomlaRoutePath( 'company/about', 'about' ), 6 => new JoomlaRoutePath( null, 'company/about' )],
            [2 => new JoomlaTranslationRoute( 'ueber-uns', 'de-DE' ), 4 => new JoomlaTranslationRoute( 'ueber-uns', 'de-DE' )]
        );
        $redirects = [
            (object) ['old_url' => 'company/about.html', 'new_url' => 'https://example.org/'],
            (object) ['old_url' => 'old-page.html', 'new_url' => 'index.php?Itemid=10'],
            (object) ['old_url' => 'https://example.org/other', 'new_url' => '/'],
            (object) ['old_url' => 'index.php/old-page', 'new_url' => '/'],
        ];
        $origin = new JoomlaOrigin( 'example.com', 'https://Example.com/joomla/', true );
        $wiring = $this->wiring( $site, menu: $this->items( [$heading] ), origin: $origin );
        $run = $wiring->assemble( articles: $articles, folders: new JoomlaFolders(), home: new JoomlaHomeRoot(), redirects: $redirects );
        $urls = $run->urls;
        $links = $run->links;
        $paths = $wiring->urlContext->paths;
        $menu = $wiring->menu;
        $router = $wiring->router;

        // menu items and URL paths Joomla uses are claimed first, later rows with the same path get their ID appended
        $article = fn( int $id ) => $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => $id . ':alias'], JoomlaLanguages::ALL );
        $this->assertSame( '/about-1', $article( 1 ) );
        $this->assertSame( '/company/about', $article( 5 ) );
        $this->assertSame( '/company/about-6', $article( 6 ) );
        $this->assertSame( '/ueber-uns', $article( 2 ) );
        $this->assertSame( '/ueber-uns-4', $article( 4 ) );
        $this->assertNull( $article( 3 ) );
        $this->assertSame( '/about', $links->resolve( ['Itemid' => '10'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['Itemid' => '10', 'view' => 'article'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['Itemid' => 'x'], JoomlaLanguages::ALL ) );
        // like Joomla, the menu item ID is the integer at the start
        $this->assertSame( '/about', $links->resolve( ['Itemid' => '10:about'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/about', $links->resolve( ['Itemid' => 10], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['Itemid' => '0'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['Itemid' => ['10']], JoomlaLanguages::ALL ) );
        $this->assertNotNull( $menu->item( 10 ) );
        $this->assertNull( $menu->item( 11 ) );
        $this->assertSame( 'about-1', $urls->articlePath( (object) ['id' => 1, 'alias' => 'about'] ) );
        // redirects from URL paths of imported pages, of other domains or already redirected ones are skipped
        $this->assertEquals( [new JoomlaKeptRedirect( 'old-page', 'index.php?Itemid=10' )], $urls->redirects() );
        $this->assertSame( 'other', $urls->articlePath( (object) ['id' => 7, 'alias' => 'other'] ) );
        $this->assertSame( 'company/about-6', $urls->articlePath( (object) ['id' => 6, 'alias' => 'company/about', 'catid' => 2] ) );

        // translations on the domain of the imported pages link to the home pages of their languages
        $home = new JoomlaHomeRoot( articleId: 1, translations: [(object) ['id' => 2, 'language' => 'de-DE'], (object) ['id' => 4, 'language' => '1-XX']] );
        $run = $this->wiring( $site, menu: $this->items( [$heading] ), origin: $origin )->assemble( articles: $articles, folders: new JoomlaFolders(), home: $home, redirects: $redirects );
        $urls = $run->urls;
        $links = $run->links;
        $article = fn( int $id ) => $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => $id . ':alias'], JoomlaLanguages::ALL );
        $this->assertSame( '/', $article( 1 ) );
        $this->assertSame( '/de', $article( 2 ) );
        $this->assertSame( '/ueber-uns-4', $article( 4 ) );

        // absolute URLs of the site (case insensitive host, default port only) are URL paths without the suffix, relative ones are relative to the directory
        $this->assertSame( '/page', $links->urlTarget( 'https://EXAMPLE.com:443/page.html', JoomlaLanguages::ALL ) );
        $this->assertSame( 'https://EXAMPLE.com:8080/page.html', $links->urlTarget( 'https://EXAMPLE.com:8080/page.html', JoomlaLanguages::ALL ) );
        $this->assertSame( 'https://other.org/x', $links->urlTarget( '//other.org/x', JoomlaLanguages::ALL ) );
        $this->assertSame( 'https://other.org/', $links->urlTarget( ' https://other.org/ ', JoomlaLanguages::ALL ) );
        $this->assertSame( 'mailto:a@example.org', $links->urlTarget( 'mailto:a@example.org', JoomlaLanguages::ALL ) );
        $this->assertSame( '/old/new#top', $links->urlTarget( 'new.html#top', JoomlaLanguages::ALL, 'old' ) );
        $this->assertSame( '/new', $links->urlTarget( 'index.php/new.html', JoomlaLanguages::ALL, 'old' ) );
        $this->assertSame( '/about', $links->urlTarget( 'index.php?Itemid=10', JoomlaLanguages::ALL ) );
        $this->assertNull( $links->urlTarget( '', JoomlaLanguages::ALL ) );
        $this->assertNull( $links->urlTarget( " \t", JoomlaLanguages::ALL ) );

        // menu items of other menus are looked up in the menu items the reader fetched once, also if they don't exist
        $db = DB::connection( 'joomla' );
        $this->assertNull( $links->resolve( ['Itemid' => '99'], JoomlaLanguages::ALL ) );
        $db->enableQueryLog();
        $this->assertNull( $links->resolve( ['Itemid' => '99'], JoomlaLanguages::ALL ) );
        $this->assertCount( 0, $db->getQueryLog() );
        $db->disableQueryLog();
        $db->flushQueryLog();

        $this->assertNull( $links->menuItemLink( (object) ['type' => 'url', 'link' => 'javascript:alert(1)'] ) );
        $this->assertNull( $links->menuItemLink( (object) ['type' => 'url', 'link' => '#top'] ) );
        $this->assertSame( 'tel:+123', $links->menuItemLink( (object) ['type' => 'url', 'link' => 'tel:+123'] ) );
        $this->assertSame( '/about', $links->menuItemLink( (object) ['id' => 12, 'type' => 'alias', 'params' => '{"aliasoptions":"10"}'] ) );
        $this->assertNull( $links->menuItemLink( (object) ['id' => 12, 'type' => 'alias', 'params' => '{"aliasoptions":"12"}'] ) );

        $this->assertSame( ['example.com/joomla', 'example.com'], $origin->sites() );
        $this->assertSame( 'old/page', $origin->redirectPath( 'https://example.com/index.php/old/page.HTML' ) );
        $this->assertNull( $origin->redirectPath( 'https://other.org/page' ) );
        $this->assertNull( $origin->redirectPath( 'page?id=1' ) );
        $this->assertNull( $origin->redirectPath( 'index.php' ) );
        // the suffix is only removed if Joomla adds it to the URLs
        $this->assertSame( 'page.html', ( new JoomlaOrigin( 'example.com' ) )->redirectPath( 'https://example.com/page.html' ) );
        // the script name is removed from the targets without suffix option too, which keeps the suffix
        $this->assertSame( 'page.html', ( new JoomlaOrigin() )->targetPath( 'index.php/page.html' ) );
        $this->assertSame( 'page', ( new JoomlaOrigin( '', '', true ) )->targetPath( 'index.php/page.html' ) );
        $this->assertSame( 'about', ( new JoomlaOrigin() )->targetPath( 'index.php/about' ) );
        $this->assertSame( 'https://any.org/x', ( new JoomlaOrigin() )->local( 'https://any.org/x' ) );
        $this->assertSame( '/x', $origin->local( '//user@example.com:80/x' ) );
        // the path of Joomla sites in subdirectories is removed, URLs outside are only local if the host is the domain too
        $subdir = new JoomlaOrigin( 'new.org', 'https://Old.ORG/joomla/' );
        $this->assertSame( '/x', $subdir->local( 'https://old.org/joomla/x' ) );
        $this->assertSame( '/', $subdir->local( 'https://old.org/joomla' ) );
        // URL paths are case sensitive, so the directory is compared case sensitive too
        $this->assertNull( $subdir->local( 'https://old.org/Joomla' ) );
        $this->assertSame( '/?id=1', $subdir->local( 'https://old.org/joomla?id=1' ) );
        $this->assertNull( $subdir->local( 'https://old.org/x' ) );
        $this->assertNull( $subdir->local( 'https://old.org/joomla2/x' ) );
        $this->assertSame( '/joomla/x', $subdir->local( 'https://new.org/joomla/x' ) );
        $this->assertSame( 'old/page', $subdir->redirectPath( 'https://old.org/joomla/index.php/old/page' ) );
        // URL paths of the site start with the directory too, other URL paths and relative URLs are kept
        $this->assertSame( '/x', $subdir->local( '/joomla/x' ) );
        $this->assertSame( '/#top', $subdir->local( '/joomla#top' ) );
        $this->assertSame( '/Joomla#top', $subdir->local( '/Joomla#top' ) );
        $this->assertSame( '/joomla2/x', $subdir->local( '/joomla2/x' ) );
        $this->assertSame( 'joomla/x', $subdir->local( 'joomla/x' ) );
        $this->assertSame( '/joomla/x', ( new JoomlaOrigin( 'new.org', 'https://old.org' ) )->local( '/joomla/x' ) );
        $this->assertSame( '/joomla/x', $origin->local( 'https://example.com/joomla/joomla/x' ) );
        $this->assertSame( '/x', $origin->local( 'https://example.com/x' ) );
        $this->assertSame( '/x', ( new JoomlaOrigin( 'new.org', 'https://Old.ORG' ) )->local( 'https://old.org/x' ) );
        // Joomla 3 with language filter only uses the menu items for all languages for the articles for all languages
        $this->assertSame( $router->contentRoutes( 'en-GB' ), $router->contentRoutes( JoomlaLanguages::ALL ) );
        $this->assertSame( '/', $urls->articleUrl( 1 ) );
        $this->assertSame( '/company/about', $urls->articleUrl( 5 ) );
        $this->assertNull( $urls->articleUrl( 3 ) );
        $this->assertSame( '/about', $urls->menuUrl( $heading ) );
        $item = (object) ['id' => 11, 'type' => 'component', 'title' => 'Team', 'alias' => 'team', 'link' => 'index.php?option=com_content&view=article&id=6'];
        $this->assertNull( $urls->menuUrl( $item ) );
        $this->assertSame( 'about', JoomlaRow::aliasOrSlug( (object) ['alias' => 'about', 'title' => 'Other'] ) );
        $this->assertSame( 'about-us', JoomlaRow::aliasOrSlug( (object) ['title' => 'About us'] ) );
        $this->assertSame( 'team', $paths->itemPath( $item ) );
        $this->assertSame( 'start', $paths->itemPath( (object) ['home' => 1, 'alias' => 'start', 'path' => 'start'] ) );
        $this->assertSame( 'company/team', $paths->itemPath( (object) ['path' => 'company/team', 'alias' => 'team'] ) );
    }


    public function testJoomla3RoutesTheContentForAllLanguagesWithTheMenuItemsForAllLanguages(): void
    {
        $this->languageFilter();
        $this->setJoomlaVersion( 3 );
        $legacy = $this->wiring( $this->site( $this->categories( [] ) ) )->router;

        // Joomla 3 with language filter only uses the menu items for all languages for the articles for all languages
        $this->assertSame( $legacy->languageRoutes( JoomlaLanguages::ALL ), $legacy->contentRoutes( JoomlaLanguages::ALL ) );
        $this->assertNotSame( $legacy->languageRoutes( JoomlaLanguages::ALL ), $legacy->contentRoutes( 'en-GB' ) );
    }


    public function testJoomla3CategoryItemsForAllLanguagesUseTheMenuItemsForAllLanguages(): void
    {
        $categories = $this->categories( [2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1, 'language' => '*']] );
        $item = (object) ['id' => 201, 'type' => 'component', 'title' => 'News', 'alias' => 'news', 'language' => 'en-GB', 'parent_id' => 1, 'level' => 1,
            'menutype' => 'othermenu', 'link' => 'index.php?option=com_content&view=category&id=2'];
        $items = function( int $version ) use ( $categories, $item ) {
            $this->languageFilter();
            $this->setJoomlaVersion( $version );
            return $this->wiring( $this->site( $categories ), $this->items( [$item] ) )->categoryItems;
        };

        // with language filter, Joomla 3 shows categories for all languages only in menu items for all languages
        $this->assertSame( 201, $items( 4 )->otherItem( $categories[2] )?->id );
        $this->assertNull( $items( 3 )->otherItem( $categories[2] ) );
    }


    public function testUrlsUseTheClaimedPathsOfCategoriesAndTranslations(): void
    {
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $kategorie = (object) ['id' => 4, 'title' => 'Blog', 'alias' => 'blog-de', 'parent_id' => 2, 'level' => 2, 'published' => 1, 'language' => 'de-DE'];
        $ueber = (object) ['id' => 16, 'type' => 'heading', 'title' => 'Hello', 'alias' => 'hello', 'language' => 'de-DE', 'link' => '', 'parent_id' => 1];
        $about = (object) ['id' => 10, 'type' => 'heading', 'title' => 'About', 'alias' => 'about', 'link' => '', 'parent_id' => 1];
        $categories = $this->categories( [2 => $news, 3 => $blog, 4 => $kategorie] );
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $categories, ['de-DE:de:https://de.example.com'] );
        $urls = $this->importRun( $this->wiring( $site, menu: $this->items( [$about] ), origin: new JoomlaOrigin( 'example.com' ) ),
            articles: $this->articles( categoryIds: [8 => 3] ),
            folders: new JoomlaFolders( [3 => $blog], [3 => [$kategorie]], [10 => [$ueber]] ),
        )->urls;

        // the categories and translations use the URL paths they claimed, the translations the ones of their language domain
        $this->assertSame( 'blog', $urls->categoryPath( $blog ) );
        $this->assertSame( '/blog', $urls->categoryUrl( 3 ) );
        $this->assertSame( 'blog-de', $urls->categoryTranslationPath( $kategorie ) );
        $this->assertSame( 'hello', $urls->menuTranslationPath( $ueber ) );
    }


    public function testUrlsLinkTranslationsOfCategoriesShownByMenuItemsToTheTranslatedMenuItems(): void
    {
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $kategorie = (object) ['id' => 4, 'title' => 'Blog', 'alias' => 'blog-de', 'parent_id' => 2, 'level' => 2, 'published' => 1, 'language' => 'de-DE'];
        $menu = (object) ['id' => 20, 'type' => 'component', 'title' => 'Blog', 'alias' => 'blog-menu', 'parent_id' => 1, 'level' => 1,
            'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'];
        $menuDe = (object) ['id' => 21, 'type' => 'component', 'title' => 'Blog', 'alias' => 'blog-menu-de', 'language' => 'de-DE', 'parent_id' => 1, 'level' => 1,
            'link' => 'index.php?option=com_content&view=category&layout=blog&id=4'];

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = fn() => $this->site( $this->categories( [2 => $news, 3 => $blog, 4 => $kategorie] ), ['de-DE:de:https://de.example.com'] );
        $run = fn( array $menuTranslations ) => $this->importRun( $this->wiring( $site(), menu: $this->items( [$menu] ), origin: new JoomlaOrigin( 'example.com' ) ),
            folders: new JoomlaFolders( [3 => $blog], [3 => [$kategorie]], $menuTranslations ),
        )->urls;

        // the page of the menu item shows the category, so its translation uses the variant of the translated menu item
        $urls = $run( [20 => [$menuDe]] );
        $this->assertSame( '/blog-menu', $urls->categoryUrl( 3 ) );
        $this->assertSame( 'https://de.example.com/blog-menu-de', $urls->categoryUrl( 4 ) );
        $this->assertSame( 'https://de.example.com/blog-menu-de', $urls->listUrl( 4 ) );
        // without translated menu item, the page has no variant in the language of the translation, so there's no page to link to
        $urls = $run( [] );
        $this->assertNull( $urls->categoryUrl( 4 ) );
        $this->assertNull( $urls->listUrl( 4 ) );
    }


    public function testUrlsLinkTranslatedCategoriesAndReserveTheRootPaths(): void
    {
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $kategorie = (object) ['id' => 4, 'title' => 'Hallo', 'alias' => 'hallo', 'parent_id' => 2, 'level' => 2, 'published' => 1, 'language' => 'de-DE'];
        $hallo = (object) ['id' => 16, 'type' => 'heading', 'title' => 'Hallo', 'alias' => 'hallo', 'language' => 'de-DE', 'link' => '', 'parent_id' => 1];
        $about = (object) ['id' => 10, 'type' => 'heading', 'title' => 'About', 'alias' => 'about', 'link' => '', 'parent_id' => 1];
        $fr = (object) ['id' => 12, 'type' => 'heading', 'title' => 'France', 'alias' => 'fr', 'link' => '', 'parent_id' => 1];
        // menu item of another menu showing the translated category
        $footer = (object) ['id' => 40, 'menutype' => 'footer', 'title' => 'Hallo', 'alias' => 'hallo', 'parent_id' => 1, 'level' => 1, 'lft' => 1,
            'type' => 'component', 'link' => 'index.php?option=com_content&view=category&id=4', 'published' => 1, 'language' => '*'];

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [2 => $news, 3 => $blog, 4 => $kategorie] ), ['de-DE:de:https://de.example.com', 'fr-FR:fr:https://example.com'] );
        $homeItem = (object) ['id' => 50, 'type' => 'component', 'home' => 1, 'language' => '*', 'link' => 'index.php?option=com_content&view=article&id=1'];
        $run = $this->importRun( $this->wiring( $site, $this->items( [$footer] ), $this->items( [$about, $fr] ), new JoomlaOrigin( 'example.com' ) ),
            articles: $this->articles( categoryIds: [8 => 3] ),
            folders: new JoomlaFolders( [3 => $blog], [3 => [$kategorie]], [10 => [$hallo]] ),
            home: new JoomlaHomeRoot( $homeItem, 1, [(object) ['id' => 7, 'language' => 'fr-FR']] ),
        );
        $urls = $run->urls;
        $links = $run->links;

        // the translated menu items claim their paths in the language domain before the translated categories
        $this->assertSame( 'hallo', $urls->menuTranslationPath( $hallo ) );
        $this->assertSame( 'hallo-4', $urls->categoryTranslationPath( $kategorie ) );
        // links to translated categories and to menu items of other menus showing them use the page variant in the language domain
        $this->assertSame( 'https://de.example.com/hallo-4', $urls->categoryUrl( 4 ) );
        $this->assertSame( 'https://de.example.com/hallo-4', $urls->listUrl( 4 ) );
        $this->assertSame( 'https://de.example.com/hallo-4', $links->resolve( ['option' => 'com_content', 'view' => 'category', 'id' => '4'], JoomlaLanguages::ALL ) );
        $this->assertSame( 'https://de.example.com/hallo-4', $links->resolve( ['Itemid' => '40'], JoomlaLanguages::ALL ) );
        // the root path of the home page variant of a language on the imported domain is reserved before the menu items
        $this->assertSame( '/fr', $urls->homeUrl( 'fr-FR' ) );
        $this->assertSame( '/fr-12', $urls->menuUrl( $fr ) );
        $this->assertSame( '/about', $urls->menuUrl( $about ) );

        // host names are case insensitive, so language domains differing only in case are the domain of the imported pages
        $domains = new JoomlaLanguageDomains( $this->site( $this->categories( [] ), ['de-DE:de:https://DE.Example.com', 'fr-FR:fr:https://Example.COM'] )->languages, new JoomlaOrigin( 'example.com' ) );
        $this->assertSame( 'de.example.com', $domains->domain( 'de-DE' ) );
        $this->assertSame( '', $domains->domain( 'fr-FR' ) );
        $this->assertSame( '/fr', $domains->homeUrl( 'fr-FR' ) );

        // like JoomlaOrigin, the --domain value is compared without whitespace and default port
        $domains = new JoomlaLanguageDomains( $this->site( $this->categories( [] ), ['de-DE:de:https://DE.example.com:8080', 'fr-FR:fr:https://Example.COM:443/'] )->languages, new JoomlaOrigin( ' Example.com:443 ' ) );
        $this->assertSame( 'de.example.com', $domains->domain( 'de-DE' ) );
        $this->assertSame( '', $domains->domain( 'fr-FR' ) );
        $this->assertSame( '/fr', $domains->homeUrl( 'fr-FR' ) );
    }


    public function testUrlsLinkTranslationsOfTheCategoryOfTheHomeMenuItemToTheHomePagesOfTheirLanguages(): void
    {
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $kategorie = (object) ['id' => 4, 'title' => 'Blog', 'alias' => 'blog-de', 'parent_id' => 2, 'level' => 2, 'published' => 1, 'language' => 'de-DE'];
        $home = (object) ['id' => 20, 'menutype' => 'main', 'type' => 'component', 'title' => 'Home', 'alias' => 'home', 'home' => 1, 'published' => 1,
            'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'];
        $homeDe = (object) ['id' => 21, 'menutype' => 'de', 'type' => 'component', 'title' => 'Start', 'alias' => 'start', 'home' => 1, 'published' => 1,
            'language' => 'de-DE', 'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=4'];

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [2 => $news, 3 => $blog, 4 => $kategorie] ), ['de-DE:de:https://de.example.com'] );
        $run = $this->importRun( $this->wiring( $site, $this->items( [$home, $homeDe] ), $this->items( [$home] ), new JoomlaOrigin( 'example.com' ) ),
            folders: new JoomlaFolders( [3 => $blog], [3 => [$kategorie]], [20 => [$homeDe]] ),
            home: new JoomlaHomeRoot( $home ),
        );

        // the category shown by the home menu item is the root page and its translation the root page variant of its language
        $this->assertSame( '/', $run->urls->categoryUrl( 3 ) );
        $this->assertSame( 'https://de.example.com/', $run->urls->categoryUrl( 4 ) );
        $this->assertSame( 'https://de.example.com/', $run->links->resolve( ['option' => 'com_content', 'view' => 'category', 'id' => '4'], 'de-DE' ) );
    }


    public function testUrlsReserveTheRootPathsOfHomeMenuItemsOfOtherLanguages(): void
    {
        $fr = (object) ['id' => 12, 'type' => 'heading', 'title' => 'France', 'alias' => 'fr', 'link' => '', 'parent_id' => 1];
        $accueil = (object) ['id' => 31, 'menutype' => 'fr', 'type' => 'component', 'title' => 'Accueil', 'alias' => 'accueil', 'home' => 1,
            'language' => 'fr-FR', 'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=featured'];

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $home = (object) ['id' => 30, 'menutype' => 'main', 'type' => 'component', 'title' => 'Home', 'alias' => 'home', 'home' => 1,
            'language' => '*', 'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=featured'];
        $site = $this->site( $this->categories( [] ), ['fr-FR:fr:https://example.com'] );
        $run = $this->importRun( $this->wiring( $site, $this->items( [$home, $accueil] ), $this->items( [$fr] ), new JoomlaOrigin( 'example.com' ) ),
            home: new JoomlaHomeRoot( $home ) );

        // with language filter, the home menu items of other languages are root page variants, so their paths are reserved too
        $this->assertSame( '/fr', $run->urls->homeUrl( 'fr-FR' ) );
        $this->assertSame( '/fr-12', $run->urls->menuUrl( $fr ) );
    }


    public function testUrlsReserveNoRootPathsIfTheRootPageGetsNoVariants(): void
    {
        $fr = (object) ['id' => 12, 'type' => 'heading', 'title' => 'France', 'alias' => 'fr', 'link' => '', 'parent_id' => 1];
        $accueil = (object) ['id' => 31, 'menutype' => 'fr', 'type' => 'component', 'title' => 'Accueil', 'alias' => 'accueil', 'home' => 1,
            'language' => 'fr-FR', 'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=featured'];
        $home = (object) ['id' => 30, 'menutype' => 'main', 'type' => 'component', 'title' => 'Home', 'alias' => 'home', 'home' => 1,
            'language' => '*', 'parent_id' => 1, 'level' => 1, 'link' => 'index.php?option=com_content&view=article&id=1'];
        $homeFr = (object) ['id' => 32, 'type' => 'component', 'title' => 'Accueil', 'alias' => 'accueil', 'language' => 'fr-FR', 'parent_id' => 1,
            'link' => 'index.php?option=com_content&view=article&id=2'];

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [] ), ['fr-FR:fr:https://example.com'] );
        $urls = fn( JoomlaHomeRoot $root, array $items = [] ) => $this->importRun( $this->wiring( $site, $this->items( [$home, ...$items] ), $this->items( [$fr] ),
            new JoomlaOrigin( 'example.com' ) ), folders: new JoomlaFolders( menuTranslations: [30 => [$homeFr]] ), home: $root )->urls;

        // without home menu item of the site, the home pages of the other languages aren't imported as root page variants
        $this->assertSame( '/fr', $urls( new JoomlaHomeRoot(), [$accueil] )->menuUrl( $fr ) );
        // home menu items whose article isn't imported into the root page are imported like the other ones, the root page gets no variants
        $this->assertSame( '/fr', $urls( new JoomlaHomeRoot( $home ) )->menuUrl( $fr ) );
        // the variants of the root page are the translations of its article, not the ones of the home menu item
        $this->assertSame( '/fr', $urls( new JoomlaHomeRoot( $home, 1 ) )->menuUrl( $fr ) );
        $this->assertSame( '/fr-12', $urls( new JoomlaHomeRoot( $home, 1, [(object) ['id' => 3, 'language' => 'fr-FR']] ) )->menuUrl( $fr ) );
    }


    public function testUrlsClaimPathsPerLanguageDomain(): void
    {
        $reader = $this->redirectReader();
        // the menu items of all menus including the home menu items of the other languages
        $item = ['parent_id' => 1, 'level' => 1, 'published' => 1];
        $others = $this->items( [
            (object) ( ['id' => 30, 'menutype' => 'de', 'title' => 'Start', 'alias' => 'start', 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'home' => 1, 'language' => 'de-DE'] + $item ),
            (object) ( ['id' => 31, 'menutype' => 'fr', 'title' => 'Accueil', 'alias' => 'accueil', 'link' => 'index.php?option=com_content&view=featured', 'type' => 'component', 'home' => 1, 'language' => 'fr-FR'] + $item ),
            (object) ( ['id' => 16, 'menutype' => 'de', 'title' => 'Hello', 'alias' => 'hello', 'link' => '', 'type' => 'heading', 'home' => 0, 'language' => 'de-DE'] + $item ),
        ] );
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $categories = $this->categories( [2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1], 3 => $blog] );
        $languages = ['de-DE:de:https://de.example.com', 'fr-FR:fr:https://example.com'];
        $ueber = (object) ['id' => 16, 'type' => 'heading', 'title' => 'Hello', 'alias' => 'hello', 'language' => 'de-DE', 'link' => '', 'parent_id' => 1];
        $restricted = (object) ['id' => 14, 'type' => 'component', 'title' => 'Blog', 'alias' => 'blog-menu', 'access' => 2, 'parent_id' => 1,
            'link' => 'index.php?option=com_content&view=category&id=3'];
        $headings = array_map( fn( int $id, string $alias ) => (object) ['id' => $id, 'type' => 'heading', 'title' => $alias, 'alias' => $alias, 'link' => '', 'parent_id' => 1],
            [10, 13, 15], ['about', 'about-1', 'hallo'] );

        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $articles = $this->articles( [
            1 => new JoomlaRoutePath( null, 'about' ),
            8 => new JoomlaRoutePath( null, 'blog' ),
            9 => new JoomlaRoutePath( null, 'hello' ),
            11 => new JoomlaRoutePath( null, 'willkommen' ),
        ], [
            2 => new JoomlaTranslationRoute( 'hallo', 'de-DE' ),
            4 => new JoomlaTranslationRoute( 'hallo', 'fr-FR' ),
            5 => new JoomlaTranslationRoute( 'hello', 'fr-FR' ),
            12 => new JoomlaTranslationRoute( 'willkommen', 'de-DE' ),
        ], [8 => 3] );
        $run = $this->importRun( $this->wiring( $this->site( $categories, $languages ), $others, $this->items( [...$headings, $restricted] ), new JoomlaOrigin( 'example.com' ) ),
            articles: $articles,
            folders: new JoomlaFolders( categories: [3 => $blog], menuTranslations: [10 => [$ueber]] ),
            redirects: $reader->redirects(),
        );
        $urls = $run->urls;
        $links = $run->links;

        $article = fn( int $id ) => $links->resolve( ['option' => 'com_content', 'view' => 'article', 'id' => $id . ':alias'], JoomlaLanguages::ALL );
        // the ID is appended to the alias until the path is unused, articles are claimed before categories and translations
        $this->assertSame( '/about-1-2', $article( 1 ) );
        $this->assertSame( '/blog', $article( 8 ) );
        $this->assertSame( '/hello', $article( 9 ) );
        $this->assertSame( '/hello-5', $article( 5 ) );
        $this->assertNull( $article( 3 ) );
        // translations of languages with own domains don't conflict with the paths of the imported domain and link to their domain
        $this->assertSame( 'https://de.example.com/hallo', $article( 2 ) );
        $this->assertSame( '/hallo-4', $article( 4 ) );
        // the same URL path can be used on the imported domain and in a language domain
        $this->assertSame( '/willkommen', $article( 11 ) );
        $this->assertSame( 'https://de.example.com/willkommen', $article( 12 ) );
        $this->assertSame( 'hello', $urls->menuTranslationPath( $ueber ) );
        // the home pages of languages on the imported domain use the language code, the ones on own domains the root
        $this->assertSame( 'https://de.example.com/', $urls->homeUrl( 'de-DE' ) );
        $this->assertSame( '/fr', $urls->homeUrl( 'fr-FR' ) );
        $this->assertNull( $urls->homeUrl( 'x1-YY' ) );
        // the home menu items of the other languages link to their home pages, the ones on own domains to the domain
        $this->assertSame( 'https://de.example.com/', $links->resolve( ['Itemid' => '30'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/fr', $links->resolve( ['Itemid' => '31'], JoomlaLanguages::ALL ) );
        // links to translated menu items use the variant of the page of the translated menu item
        $this->assertSame( 'https://de.example.com/hello', $links->resolve( ['Itemid' => '16'], 'de-DE' ) );
        $this->assertSame( 'https://de.example.com/hello', $links->urlTarget( 'index.php?Itemid=16', 'de-DE' ) );
        $this->assertSame( 'https://de.example.com/hello', $urls->menuUrl( $ueber ) );
        // restricted menu items don't show the category
        $this->assertSame( '/blog-3', $urls->categoryUrl( 3 ) );
        $this->assertSame( [], $urls->redirects() );
    }


    public function testUrlsKeepRedirectsOfAbsoluteOldUrlsWithoutDomain(): void
    {
        $reader = $this->redirectReader();
        $blog = (object) ['id' => 3, 'title' => 'Blog', 'alias' => 'blog', 'parent_id' => 2, 'level' => 2, 'published' => 1];
        $categories = $this->categories( [2 => (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1], 3 => $blog] );

        // without domain and base URL of the files, the absolute old URLs of the redirects are URLs of the site
        $assigned = $this->importRun( $this->site( $categories ), redirects: $reader->redirects() )->urls->redirects();
        $this->assertEquals( [new JoomlaKeptRedirect( 'moved', 'about' )], $assigned );
    }


    public function testUrlsKeepOnlyRedirectsWhoseTargetsAreImported(): void
    {
        $site = $this->site( $this->categories( [] ) );
        $articles = $this->articles( [7 => new JoomlaRoutePath( null, 'about' )] );
        $gone = (object) ['old_url' => 'about', 'new_url' => 'index.php?option=com_content&view=article&id=99'];
        $assign = fn( array $redirects ) => $this->importRun( $site, articles: $articles, redirects: $redirects )->urls;

        // redirects to articles which aren't imported don't reserve their old URL paths, so the alias of the article is used
        $urls = $assign( [$gone] );
        $this->assertSame( [], $urls->redirects() );
        $this->assertSame( 'about', $urls->articlePath( (object) ['id' => 7, 'alias' => 'about'] ) );
        // later redirects of the same old URL path with imported targets are kept
        $urls = $assign( [$gone, (object) ['old_url' => 'about', 'new_url' => 'https://example.org/']] );
        $this->assertEquals( [new JoomlaKeptRedirect( 'about', 'https://example.org/' )], $urls->redirects() );
        $this->assertSame( 'about-7', $urls->articlePath( (object) ['id' => 7, 'alias' => 'about'] ) );
    }


    public function testUrlsSkipRedirectsWithUnsupportedTargets(): void
    {
        $site = $this->site( $this->categories( [] ) );
        $articles = $this->articles( [7 => new JoomlaRoutePath( 'about', 'about' )] );
        $redirects = [
            (object) ['old_url' => 'ftp', 'new_url' => 'ftp://files.example.com/x'],
            (object) ['old_url' => 'script', 'new_url' => 'javascript:alert(1)'],
            (object) ['old_url' => 'anchor', 'new_url' => '#top'],
            (object) ['old_url' => 'blank', 'new_url' => '  '],
            (object) ['old_url' => 'mail', 'new_url' => 'mailto:a@example.com'],
            (object) ['old_url' => 'web', 'new_url' => 'https://example.org/x'],
            // URL paths aren't checked against the imported pages because they can point to pages outside of Joomla
            (object) ['old_url' => 'shop', 'new_url' => '/shop/cart'],
            // the old URL path is used by the article, so the redirect is skipped silently like ones with supported targets
            (object) ['old_url' => 'about', 'new_url' => 'javascript:void(0)'],
        ];
        $run = $this->importRun( $site, articles: $articles, redirects: $redirects );
        $targets = [];

        foreach( $run->urls->redirects() as $redirect ) {
            $targets[$redirect->path] = $run->links->redirectTarget( $redirect );
        }

        $this->assertSame( ['mail' => 'mailto:a@example.com', 'web' => 'https://example.org/x', 'shop' => '/shop/cart'], $targets );
        $this->assertSame( ['ftp', 'script', 'anchor', 'blank'], array_map( fn( JoomlaKeptRedirect $redirect ) => $redirect->path, $run->urls->unsupportedRedirects() ) );
        $this->assertSame( '/about', $run->urls->articleUrl( 7 ) );
        // the same rules as for URL menu items
        $this->assertNull( $run->links->urlTarget( 'ftp://files.example.com/x', JoomlaLanguages::ALL ) );
        $this->assertNull( $run->links->urlTarget( '#top', JoomlaLanguages::ALL ) );
        $this->assertSame( '/page#top', $run->links->urlTarget( 'page#top', JoomlaLanguages::ALL ) );
    }


    public function testUrlTargetsKeepFragmentsAndQueriesLikeJoomla(): void
    {
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [] ) );
        $heading = (object) ['id' => 10, 'type' => 'heading', 'title' => 'Team', 'alias' => 'team', 'link' => '', 'parent_id' => 1];
        $articles = $this->articles( [7 => new JoomlaRoutePath( null, 'about' )] );
        $wiring = $this->wiring( $site, menu: $this->items( [$heading] ), origin: new JoomlaOrigin( 'example.com', '', true ) );
        $links = $this->importRun( $wiring, articles: $articles )->links;
        $target = fn( string $url ) => $links->urlTarget( $url, JoomlaLanguages::ALL, 'a/b' );

        // the fragments of the routed Joomla URLs are kept, also of absolute URLs of the site
        $this->assertSame( '/about#top', $target( 'index.php?option=com_content&view=article&id=7#top' ) );
        $this->assertSame( '/about#c', $target( 'https://example.com/index.php?option=com_content&view=article&id=7#c' ) );
        // the suffix is removed from the path before the query string and the fragment
        $this->assertSame( '/a/b/page?x=1', $target( 'page.html?x=1' ) );
        $this->assertSame( '/page?x=1#a', $target( 'index.php/page.html?x=1#a' ) );
        // the script without query parameters shows the home page, the one of the language with language filter
        $this->assertSame( '/', $target( 'index.php?' ) );
        $this->assertSame( '/#top', $target( '/index.php?#top' ) );
        $this->assertSame( '/', $target( 'index.php' ) );
        $this->assertSame( '/de', $links->urlTarget( 'index.php?', 'de-DE' ) );
        // relative query strings are routed by the script like "index.php?Itemid=10"
        $this->assertSame( '/team', $target( '?Itemid=10' ) );
        $this->assertSame( '/team#x', $target( '?Itemid=10#x' ) );
        $this->assertNull( $target( '?Itemid=99' ) );
        // the query strings relative to the root are routed by the script too, also the ones of absolute URLs of the site
        $this->assertSame( '/team', $target( '/?Itemid=10' ) );
        $this->assertSame( '/team#x', $target( 'https://example.com/?Itemid=10#x' ) );
        $this->assertSame( '/team', $target( 'https://example.com?Itemid=10' ) );
        // the language parameter alone shows the home page of the language like the language switcher links, unknown ones the one of the content
        $this->assertSame( '/de', $target( 'index.php?lang=de' ) );
        $this->assertSame( '/de', $links->resolve( ['lang' => 'DE-de'], JoomlaLanguages::ALL ) );
        $this->assertSame( '/', $links->resolve( ['lang' => 'en'], 'de-DE' ) );
        $this->assertSame( '/de', $links->resolve( ['lang' => 'xx'], 'de-DE' ) );
        $this->assertSame( '/', $links->resolve( ['lang' => 'xx'], JoomlaLanguages::ALL ) );
        $this->assertNull( $links->resolve( ['lang' => 'de', 'Itemid' => '99'], JoomlaLanguages::ALL ) );
        // dot segments are removed before the script name and the suffix like browsers resolve them before Joomla routes the URLs
        $this->assertSame( '/page', $target( '../../index.php/page.html' ) );
        $this->assertSame( '/team', $target( '../../index.php?Itemid=10' ) );
        $this->assertSame( '/', $target( '../../index.php' ) );
        $this->assertSame( '/a/index.php/page', $target( '../index.php/page' ) );
        $this->assertSame( '/a/b/index.php/x', $target( './index.php/x' ) );
        // dot segments are removed like browsers do, parents above the root are dropped
        $this->assertSame( '/a/up', $target( '../up' ) );
        $this->assertSame( '/up?x=1', $target( '../../../up.html?x=1' ) );
        $this->assertSame( '/a/b/c', $target( './c' ) );
        $this->assertSame( '/a/', $target( '..' ) );
        $this->assertSame( '/x/z', $target( '/x/y/../z' ) );

        // the URL menu items are supported by the same rules
        $item = fn( string $link ) => (object) ['id' => 20, 'type' => 'url', 'link' => $link, 'language' => '*'];
        $linkable = fn( string $link ) => JoomlaLinks::linkable( $item( $link ), $wiring->menu, $this->items( [] ), $wiring->router );
        $this->assertTrue( $linkable( 'index.php?' ) );
        $this->assertTrue( $linkable( '?Itemid=10' ) );
        $this->assertTrue( $linkable( '/?Itemid=10' ) );
        $this->assertTrue( $linkable( 'index.php' ) );
        $this->assertFalse( $linkable( '?Itemid=99' ) );
        $this->assertSame( '/team', $links->menuItemLink( $item( '?Itemid=10' ) ) );
        $this->assertSame( '/about#top', $links->menuItemLink( $item( 'index.php?option=com_content&view=article&id=7#top' ) ) );
    }


    public function testUrlsReportUnsupportedRedirectsOnlyIfTheirPathsAreUnused(): void
    {
        $site = $this->site( $this->categories( [] ) );
        $articles = $this->articles( [7 => new JoomlaRoutePath( 'routed', 'routed' ), 8 => new JoomlaRoutePath( null, 'aliased' )] );
        $redirects = [
            (object) ['old_url' => 'routed', 'new_url' => 'javascript:x'],
            // the alias is claimed after the redirects but used by the article in the end
            (object) ['old_url' => 'aliased', 'new_url' => 'javascript:x'],
            // the path is used by the kept redirect
            (object) ['old_url' => 'dup', 'new_url' => 'https://example.org/'],
            (object) ['old_url' => 'dup', 'new_url' => 'javascript:x'],
            (object) ['old_url' => 'unused', 'new_url' => 'javascript:x'],
        ];
        $urls = $this->importRun( $site, articles: $articles, redirects: $redirects )->urls;

        $this->assertSame( ['unused'], array_map( fn( JoomlaKeptRedirect $redirect ) => $redirect->path, $urls->unsupportedRedirects() ) );
        $this->assertEquals( [new JoomlaKeptRedirect( 'dup', 'https://example.org/' )], $urls->redirects() );
        $this->assertSame( '/aliased', $urls->articleUrl( 8 ) );
        $this->assertSame( '/routed', $urls->articleUrl( 7 ) );
    }


    public function testTranslationPagedLikeTheImportedMenuItems(): void
    {
        $categoryItems = $this->wiring( $this->site( $this->categories( [] ) ) )->categoryItems;
        $variant = fn( int|string $id ) => false;
        $item = fn( string $link, int $home = 0 ) => (object) ['id' => 30, 'type' => 'component', 'link' => $link, 'home' => $home, 'language' => 'fr-FR'];

        // like selectPages(), category menu items without category get no page, the ones listing all categories do
        $this->assertFalse( $categoryItems->translationPaged( $item( 'index.php?option=com_content&view=category' ), $variant ) );
        $this->assertFalse( $categoryItems->translationPaged( $item( 'index.php?option=com_content&view=category&id=0' ), $variant ) );
        $this->assertTrue( $categoryItems->translationPaged( $item( 'index.php?option=com_content&view=categories' ), $variant ) );
        $this->assertTrue( $categoryItems->translationPaged( $item( 'index.php?option=com_content&view=category', 1 ), $variant ) );
        $this->assertTrue( $categoryItems->translationPaged( (object) ['id' => 31, 'type' => 'heading', 'link' => '', 'language' => 'fr-FR'], $variant ) );
    }


    public function testHostsAreNormalizedTheSameWay(): void
    {
        $this->assertSame( 'example.com', JoomlaOrigin::host( ' https://user@Example.COM:443/path?x#y ' ) );
        $this->assertSame( 'example.com:8080', JoomlaOrigin::host( 'Example.com:8080' ) );
        $this->assertSame( 'example.com', JoomlaOrigin::host( 'example.com:' ) );
        $this->assertSame( 'example.com', JoomlaOrigin::host( 'user@example.com:80' ) );
        // the domain of the imported pages may be given with scheme like the domains of the languages
        $this->assertSame( '/x', ( new JoomlaOrigin( 'https://Example.com/' ) )->local( 'https://example.com:443/x' ) );
        $domains = new JoomlaLanguageDomains( $this->site( $this->categories( [] ), ['fr-FR:fr:example.com:443'] )->languages, new JoomlaOrigin( 'https://Example.com/' ) );
        $this->assertSame( '', $domains->domain( 'fr-FR' ) );
    }


    public function testUrlsKeepTheDirectoriesOfTheOldUrlsOfRedirects(): void
    {
        $redirects = [
            (object) ['old_url' => 'https://example.com/old/page', 'new_url' => 'https://example.org/a'],
            (object) ['old_url' => 'nested/page#x/', 'new_url' => 'https://example.org/b'],
            (object) ['old_url' => 'query/page?x=1/', 'new_url' => 'https://example.org/c'],
        ];
        $kept = fn( JoomlaOrigin $origin ) => $this->importRun( $this->wiring( $this->site( $this->categories( [] ) ), origin: $origin ),
            redirects: $redirects )->urls->redirects();

        // the directory of absolute nested URLs of the site is the one of their path, slashes in the fragment don't make old URLs directories
        // and old URLs with query aren't kept
        $expected = [new JoomlaKeptRedirect( 'old/page', 'https://example.org/a', 'old' ), new JoomlaKeptRedirect( 'nested/page', 'https://example.org/b', 'nested' )];
        $this->assertEquals( $expected, $kept( new JoomlaOrigin() ) );
        $this->assertEquals( $expected, $kept( new JoomlaOrigin( 'example.com' ) ) );
    }


    public function testUrlsGiveNoVariantsToTranslationsPointingToDroppedOnes(): void
    {
        $this->languageFilter();
        $this->setJoomlaVersion( 4 );
        $site = $this->site( $this->categories( [] ), ['de-DE:de:https://de.example.com'] );
        $heading = fn( int $id ) => (object) ['id' => $id, 'type' => 'heading', 'title' => "Item {$id}", 'alias' => "item-{$id}", 'link' => '', 'parent_id' => 1];
        $translation = fn( int $id, array $data ) => (object) ( $data + ['id' => $id, 'menutype' => 'main-de', 'title' => "Eintrag {$id}", 'alias' => "eintrag-{$id}",
            'parent_id' => 1, 'level' => 1, 'lft' => $id, 'published' => 1, 'language' => 'de-DE', 'link' => '', 'params' => '{}'] );
        // aliases pointing over two levels to a translated URL menu item whose target isn't supported
        $first = $translation( 20, ['type' => 'alias', 'params' => '{"aliasoptions":"21"}'] );
        $second = $translation( 21, ['type' => 'alias', 'params' => '{"aliasoptions":"22"}'] );
        $script = $translation( 22, ['type' => 'url', 'link' => 'javascript:alert(1)'] );
        $folder = $translation( 23, ['type' => 'heading'] );
        // translations of folders showing an article or a view without ID
        $article = $translation( 24, ['type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=1'] );
        $featured = $translation( 25, ['type' => 'component', 'link' => 'index.php?option=com_content&view=featured'] );
        $rows = [$first, $second, $script, $folder, $article, $featured];
        $menu = $this->items( array_map( fn( int $id ) => $heading( $id ), [10, 11, 12, 13, 14, 15] ) );
        $folders = new JoomlaFolders( menuTranslations: array_combine( [10, 11, 12, 13, 14, 15], array_map( fn( $row ) => [$row], $rows ) ) );
        $urls = $this->importRun( $this->wiring( $site, $this->items( $rows ), $menu, new JoomlaOrigin( 'example.com' ) ), folders: $folders )->urls;

        // only the translated folder gets a page variant and claims a URL path, the others aren't imported
        $this->assertSame( [20 => false, 21 => false, 22 => false, 23 => true, 24 => false, 25 => false],
            array_combine( array_column( $rows, 'id' ), array_map( fn( $row ) => $urls->menuVariant( $row->id ), $rows ) ) );
        $this->assertSame( [false, false, false, true, false, false], array_map( fn( $row ) => $urls->menuTranslated( $row ), $rows ) );
    }


    public function testRedirectsExcludeTermsIncludeTheBaseDirectory(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => json_encode( ['exclude_urls' => [['term' => '/joomla/old']]] )] );
        $db->table( 'redirect_links' )->insert( [
            ['id' => 1, 'old_url' => 'old', 'new_url' => 'about', 'published' => 1],
            ['id' => 2, 'old_url' => '/old', 'new_url' => 'about', 'published' => 1],
            ['id' => 3, 'old_url' => 'https://example.com/joomla/old', 'new_url' => 'about', 'published' => 1],
            ['id' => 4, 'old_url' => 'other', 'new_url' => 'about', 'published' => 1],
            // URL paths starting with the base directory don't get it added again
            ['id' => 5, 'old_url' => '/joomla/old', 'new_url' => 'about', 'published' => 1],
        ] );
        $reader = $this->reader( $db );
        $plugins = $this->site( $reader->categories(), reader: $reader )->plugins;
        $performed = fn( ?JoomlaOrigin $origin ) => JoomlaRedirects::all( $reader, $plugins, null, $origin )->pluck( 'old_url' )->values()->all();

        // like the redirect plugin, the exclude terms are compared with the request URI including the base directory of the site
        $this->assertSame( ['other'], $performed( new JoomlaOrigin( 'example.com', 'https://example.com/joomla' ) ) );
        // without the host of the base URL, the base directory is unknown
        $this->assertSame( ['old', '/old', 'other'], $performed( new JoomlaOrigin( 'example.com' ) ) );
        $this->assertSame( ['old', '/old', 'other'], $performed( null ) );
    }


    public function testUrlsTopLevelCategoriesAndMenuItemsWithoutPageDontClaimPaths(): void
    {
        $reader = $this->redirectReader();

        // top level categories and menu items without own page and their translations don't use URL paths
        $top = (object) ['id' => 2, 'title' => 'Moved', 'alias' => 'moved', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $topFr = (object) ['id' => 6, 'title' => 'Moved', 'alias' => 'moved', 'parent_id' => 1, 'level' => 1, 'published' => 1, 'language' => 'fr-FR'];
        $itemFr = (object) ['id' => 18, 'type' => 'heading', 'title' => 'Moved', 'alias' => 'moved', 'language' => 'fr-FR', 'link' => '', 'parent_id' => 1];
        $topCategories = $this->categories( [2 => $top, 6 => $topFr] );
        // French is imported into the domain of the imported pages, which has no domain name here
        $topSite = fn() => $this->site( $topCategories, ['de-DE:de:https://de.example.com', 'fr-FR:fr'] );
        $folders = new JoomlaFolders( [2 => $top], [2 => [$topFr]], [17 => [$itemFr]] );
        $assigned = $this->importRun( $topSite(), folders: $folders, redirects: $reader->redirects() )->urls->redirects();
        $this->assertEquals( [new JoomlaKeptRedirect( 'moved', 'about' )], $assigned );
        // the translations of menu items with own page use them
        $folder = (object) ['id' => 17, 'type' => 'heading', 'title' => 'Folder', 'alias' => 'folder', 'link' => '', 'parent_id' => 1];
        $site = $topSite();
        $wiring = $this->wiring( $site, menu: $this->items( [$folder] ) );
        $this->assertSame( [], $wiring->assemble( articles: $this->articles(), folders: $folders, home: new JoomlaHomeRoot(), redirects: $reader->redirects() )->urls->redirects() );
    }


    public function testUrlAssignmentsStartAgainAndKeepTheEarlierUrls(): void
    {
        $reader = $this->redirectReader();
        $wiring = $this->wiring( $this->site( $this->categories( [] ) ) );

        // each assignment starts again and returns new URL paths, the earlier ones aren't changed
        $linker = fn( JoomlaAssignedUrls $assigned ) => new JoomlaLinks( $wiring->site, collect(), new JoomlaUrls( $wiring->urlContext, $assigned ),
            $wiring->router, $wiring->menu, new JoomlaOrigin() );
        $assignment = new JoomlaUrlAssignment( $wiring->urlContext, new JoomlaOrigin(), $linker );
        $articleUrl = fn( JoomlaAssignedUrls $assigned, int $id ) => ( new JoomlaUrls( $wiring->urlContext, $assigned ) )->articleUrl( $id );
        $first = $assignment->assign( $this->articles( [7 => new JoomlaRoutePath( 'seven', 'seven' )] ), new JoomlaFolders(), new JoomlaHomeRoot(), $reader->redirects() );
        $this->assertEquals( [new JoomlaKeptRedirect( 'moved', 'about' )], $first->redirects() );
        $this->assertSame( '/seven', $articleUrl( $first, 7 ) );
        $second = $assignment->assign( $this->articles(), new JoomlaFolders(), new JoomlaHomeRoot(), [] );
        $this->assertSame( [], $second->redirects() );
        $this->assertNull( $articleUrl( $second, 7 ) );
        $this->assertSame( '/seven', $articleUrl( $first, 7 ) );
    }


    public function testPathsDontRouteUnpublishedCategories(): void
    {
        // Joomla doesn't route unpublished categories
        $news = (object) ['id' => 2, 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 1, 'published' => 1];
        $old = (object) ['id' => 3, 'title' => 'Old', 'alias' => 'old', 'parent_id' => 2, 'level' => 2, 'published' => 0];
        $categories = $this->categories( [2 => $news, 3 => $old] );
        $item = (object) ['id' => 20, 'type' => 'component', 'title' => 'News', 'alias' => 'news-menu', 'parent_id' => 1, 'link' => 'index.php?option=com_content&view=category&id=2'];
        $paths = $this->wiring( $this->site( $categories ), $this->items( [$item] ) )->urlContext->paths;
        $this->assertSame( 'news-menu', $paths->categoryRoute( $news )->route );
        $this->assertNull( $paths->categoryRoute( $old )->route );
    }


    /**
     * Adds the tables of the redirects with an absolute old URL and returns the reader of the Joomla database.
     */
    protected function redirectReader(): JoomlaReader
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( ['id' => 1, 'old_url' => 'https://old.example.org/moved', 'new_url' => 'about', 'published' => 1] );

        return $this->reader( $db );
    }


    public function testMenuRuleTargets(): void
    {
        $this->assertSame( 'article/5', JoomlaMenuRules::target( 'article', 5 ) );
        $this->assertSame( 'category/7', JoomlaMenuRules::target( 'category', '7' ) );
        $this->assertSame( 'featured:', JoomlaMenuRules::layoutTarget( 'featured' ) );
        $this->assertSame( 'category:', JoomlaMenuRules::layoutTarget( 'category', 'default' ) );
        $this->assertSame( 'category:blog', JoomlaMenuRules::layoutTarget( 'category', 'blog' ) );
    }


    public function testMenuItemKindIsComputedAgainIfTheLinkOrTypeChanges(): void
    {
        $item = (object) ['id' => 101, 'type' => 'component', 'link' => 'index.php?option=com_content&view=article&id=5'];
        $other = (object) ['id' => 102, 'type' => 'component', 'link' => 'index.php?option=com_content&view=category&id=5'];
        $this->assertMenuLink( JoomlaMenuLink::KIND_ARTICLE, 5, $item );
        $this->assertMenuLink( JoomlaMenuLink::KIND_CATEGORY, 5, $other );

        // the kind is derived from the current row, no state outlives the change of the menu item
        $item->link = 'index.php?option=com_content&view=category&id=7';
        $this->assertMenuLink( JoomlaMenuLink::KIND_CATEGORY, 7, $item );
        $this->assertNull( JoomlaMenuItem::article( $item ) );

        $item->type = 'url';
        $this->assertMenuLink( JoomlaMenuLink::KIND_LINK, null, $item );

        $item->link = '#';
        $this->assertMenuLink( JoomlaMenuLink::KIND_FOLDER, null, $item );
    }


    public function testJoomlaLinksOfDifferentSitesUseTheirOwnPatterns(): void
    {
        $resolve = fn( array $query ) => '/page-' . ( $query['id'] ?? '' );
        $html = '<a href="https://a.com/index.php?id=1">A</a><a href="https://b.com/index.php?id=2">B</a>';

        $this->assertSame( '<a href="/page-1">A</a><a href="https://b.com/index.php?id=2">B</a>', JoomlaHtmlUrls::links( $html, $resolve, ['a.com'] ) );
        $this->assertSame( '<a href="https://a.com/index.php?id=1">A</a><a href="/page-2">B</a>', JoomlaHtmlUrls::links( $html, $resolve, ['b.com'] ) );
        $this->assertSame( '<a href="/page-1">A</a><a href="/page-2">B</a>', JoomlaHtmlUrls::links( $html, $resolve, ['a.com', 'b.com'] ) );
    }


    public function testHtmlLinksAreRoutedLikeUrlTargets(): void
    {
        $resolve = fn( array $query ) => '/page-' . ( $query['Itemid'] ?? 'home' );

        // query strings relative to the script or the root and the script without query string are routed like by urlTarget()
        $this->assertSame( '<a href="/page-10">A</a><a href="/page-10#x">B</a><a href="/page-home">C</a><a href=\'/page-home#top\'>D</a>',
            JoomlaHtmlUrls::links( '<a href="?Itemid=10">A</a><a href="/?Itemid=10#x">B</a><a href="index.php">C</a><a href=\'index.php#top\'>D</a>', $resolve ) );
        $this->assertSame( '<a href="/page-10">A</a><a href="/page-10">B</a><a href="/page-10">C</a>',
            JoomlaHtmlUrls::links( '<a href="https://a.com/?Itemid=10">A</a><a href="https://a.com?Itemid=10">B</a><a href="/joomla?Itemid=10">C</a>', $resolve, ['a.com', 'a.com/joomla'] ) );
        // other URL paths starting like the script, other sites and URLs without query string are kept
        $kept = '<a href="index.php/about">A</a><a href="index.phpx">B</a><a href="/">C</a><a href="https://b.com/?Itemid=10">D</a><a href="https://a.comx?Itemid=10">E</a><a href="#top">F</a>';
        $this->assertSame( $kept, JoomlaHtmlUrls::links( $kept, $resolve, ['a.com'] ) );

        $site = $this->site( $this->categories( [] ) );
        $heading = (object) ['id' => 10, 'type' => 'heading', 'title' => 'Team', 'alias' => 'team', 'link' => '', 'parent_id' => 1];
        $articles = $this->articles( [7 => new JoomlaRoutePath( null, 'about' )] );
        $wiring = $this->wiring( $site, menu: $this->items( [$heading] ), origin: new JoomlaOrigin( 'example.com', '', true ) );
        $links = $this->importRun( $wiring, articles: $articles )->links;

        $this->assertSame( '<a href="/team">x</a> <a href="/team">y</a> <a href="/">z</a> <a href="about.html">s</a> <a href="?Itemid=99">u</a>',
            $links->replace( '<a href="?Itemid=10">x</a> <a href="/?Itemid=10">y</a> <a href="index.php">z</a> <a href="about.html">s</a> <a href="?Itemid=99">u</a>', JoomlaLanguages::ALL ) );
    }
}
