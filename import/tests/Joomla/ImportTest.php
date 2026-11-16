<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Joomla;

use Aimeos\Cms\Commands\JoomlaImport;
use Aimeos\Cms\Translation\Hashes;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


/**
 * Command-level tests importing Joomla sites end-to-end with the "cms:joomla-import" command.
 */
class ImportTest extends TestAbstract
{
    public function testCommandIsRegisteredByTheServiceProvider(): void
    {
        $this->assertArrayHasKey( 'cms:joomla-import', Artisan::all() );
        $this->assertInstanceOf( JoomlaImport::class, Artisan::all()['cms:joomla-import'] );
    }


    public function testImportTranslations(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Found 2 Joomla articles', $output );
        $this->assertStringContainsString( 'Found 2 Joomla article translations', $output );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $variants = PageVariant::where( 'page_id', $page->id )->get()->keyBy( 'lang' );

        $this->assertEquals( ['de', 'en', 'fr'], $variants->keys()->sort()->values()->all() );
        $this->assertSame( '2019-03-04 05:06:07', $page->created_at?->format( 'Y-m-d H:i:s' ) );
        $this->assertSame( 0, PageView::where( 'name', 'Über uns' )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'Nur Deutsch' )->count() );
        // empty MySQL dates aren't used as creation date
        $this->assertTrue( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->created_at?->isToday() );

        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $hashes = Hashes::page( $page->only( PageVariant::SYNC_FIELDS ), $page->content, $page->meta, $page->config );

        $this->assertSame( 'Über uns', $de->title );
        $this->assertSame( 'ueber-uns', $de->path );
        // the title is shown above the text like Joomla does by default
        $this->assertSame( ['heading', 1, 'Über uns'], [$de->content[0]->type ?? null, $de->content[0]->data->level ?? null, $de->content[0]->data->title ?? null] );
        $this->assertSame( 'Willkommen', $de->content[1]->data->title ?? null );
        $this->assertSame( array_column( (array) $page->content, 'id' ), array_column( (array) $de->content, 'id' ) );
        $this->assertFalse( $variants['de']->stale );
        $this->assertEquals( $hashes, $variants['de']->hashes );

        $fr = PageView::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( 'a-propos', $fr->path );
        $this->assertTrue( $variants['fr']->stale );
        $this->assertEmpty( $variants['fr']->hashes );

        // translations changed in Pagible aren't overwritten
        PageVariant::where( 'path', 'ueber-uns' )->update( ['editor' => 'someone'] );
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 2 )->update( ['introtext' => '<h2>Neu</h2><p>Hallo</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipped translation: Über uns [de] (changed in Pagible since the last import)', Artisan::output() );
        $this->assertSame( 'Willkommen', PageView::language( 'de' )->findOrFail( $page->id )->content[1]->data->title ?? null );
    }


    public function testReimportUpdatesPages(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        // articles moved to another category are moved below its page
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Changed</p>', 'catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: About (/about)', $output );
        $this->assertStringContainsString( 'Unchanged: Nur Deutsch (/nur-deutsch)', $output );
        $this->assertStringNotContainsString( 'Failed', $output );
        $this->assertStringContainsString( 'Import complete. 0 imported, 1 updated, 1 unchanged (2/2 articles processed).', $output );
        $this->assertSame( 1, PageView::where( 'name', 'About' )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'News' )->count() );
        $this->assertStringContainsString( 'Changed', (string) json_encode( PageView::findOrFail( $page->id )->content ) );
        $news = PageView::where( 'name', 'News' )->firstOrFail();
        $this->assertSame( $news->id, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->parent_id );
        $this->assertSame( $news->id, PageView::findOrFail( $page->id )->parent_id );
        $this->assertSame( 1, $news->status );
    }


    public function testSinceKeepsUnchangedArticles(): void
    {
        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => 'invalid'] ) );
        $this->assertStringContainsString( 'Invalid date for --since: invalid', Artisan::output() );
        $this->assertSame( 0, PageView::count() );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2] );
        $db->table( 'content' )->whereIn( 'id', [1, 2, 4] )->update( ['introtext' => '<p>Changed</p>'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2] );
        $db->table( 'content' )->whereIn( 'id', [2, 3] )->update( ['created' => '2019-01-01 00:00:00'] );
        $db->enableQueryLog();

        // articles created before without modification date aren't rendered again but moved below the parent page
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        $queries = Collection::make( $db->getQueryLog() );
        $db->flushQueryLog();

        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        // articles without modification and creation date are always imported
        $this->assertStringContainsString( 'Updated: Nur Deutsch (/nur-deutsch)', $output );
        $this->assertSame( [[4]], $queries->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) )->pluck( 'bindings' )->values()->all() );
        $this->assertStringNotContainsString( 'Changed', (string) json_encode( PageView::findOrFail( $page->id )->content ) );
        $this->assertSame( PageView::where( 'name', 'News' )->firstOrFail()->id, PageView::findOrFail( $page->id )->parent_id );

        // changed translations import the article and its translations again
        $db->table( 'content' )->where( 'id', 2 )->update( ['modified' => '2020-01-02 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $db->disableQueryLog();

        $this->assertStringContainsString( 'Updated: About (/about)', Artisan::output() );
        $this->assertStringContainsString( 'Changed', (string) json_encode( PageView::findOrFail( $page->id )->content ) );
        $this->assertStringContainsString( 'Changed', (string) json_encode( PageView::language( 'de' )->findOrFail( $page->id )->content ) );

        // modified articles are imported again
        $db->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Modified</p>', 'modified' => '2020-01-01 00:00:01'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01 00:00:01'] ) );
        $this->assertStringContainsString( 'Modified', (string) json_encode( PageView::findOrFail( $page->id )->content ) );
    }


    public function testImportsArticlesByCategory(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2, 'lft' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Blog', 'alias' => 'blog', 'level' => 2, 'lft' => 1],
        ] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'News one', 'alias' => 'news-one', 'language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'catid' => 2, 'ordering' => 1],
            ['id' => 6, 'title' => 'Blog one', 'alias' => 'blog-one', 'language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'catid' => 3, 'ordering' => 2],
            ['id' => 7, 'title' => 'News two', 'alias' => 'news-two', 'language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'catid' => 2, 'ordering' => 3],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // new pages are added at the end of the page tree if the articles are imported by category
        $positions = array_map( fn( $name ) => strpos( $output, "Imported: {$name} " ), ['Blog one', 'News one', 'News two', 'About'] );

        $this->assertNotContains( false, $positions );
        $this->assertSame( $positions, collect( $positions )->sort()->values()->all() );
        $this->assertSame( ['News one', 'News two'], PageView::where( 'name', 'News' )->firstOrFail()->children->pluck( 'name' )->all() );
    }


    public function testKeepsOnlyIdsOfImportedArticles(): void
    {
        $command = new class extends JoomlaImport {
            /** @var array<int|string, string> */
            public array $created = [];

            public function handle(): int
            {
                $result = parent::handle();
                $this->created = $this->createdPages->all();
                return $result;
            }
        };
        Artisan::registerCommand( $command );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the pages aren't looked up by article ID, so only the IDs of the imported and kept articles are stored
        $this->assertNotEmpty( $command->created );
        $this->assertSame( array_fill_keys( array_keys( $command->created ), '' ), $command->created );
    }


    public function testChangedTimesKeepUnknownTimesChanged(): void
    {
        $command = new class extends JoomlaImport {
            /**
             * @param array<int|string, int> $scheduled
             * @return array<int|string, int>
             */
            public function changed( \Aimeos\Cms\Import\Joomla\Articles $articles, array $scheduled ): array
            {
                return $this->changedTimes( $articles, $scheduled );
            }
        };
        $articles = new \Aimeos\Cms\Import\Joomla\Articles( categoryIds: [1 => 0, 2 => 0, 3 => 0], translations: [3 => [9]],
            times: [1 => 100, 3 => 100, 9 => 100], items: [], visible: [], routes: ['a' => [], 'ta' => []] );

        // passed publishing dates are later changes, but articles without known modification time stay changed
        $this->assertSame( [1 => 200, 2 => PHP_INT_MAX, 3 => 300], $command->changed( $articles, [1 => 200, 2 => 50, 9 => 300] ) );
    }


    public function testFetchesPagesAtOnce(): void
    {
        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $queries = $this->queries( $db )->pluck( 'query' );
        $db->disableQueryLog();

        // the pages using the URLs of the articles are fetched at once and the variants of the new pages not at all
        $this->assertSame( [], $queries->filter( fn( $query ) => preg_match( '/"path" in \(\?, \?\).* limit 1/', $query ) === 1 )->values()->all() );
        $this->assertSame( 1, $queries->filter( fn( $query ) => preg_match( '/"path" in \(\?, \?, \?, \?\)/', $query ) === 1 )->count() );
        $this->assertSame( 0, $queries->filter( fn( $query ) => str_ends_with( $query, 'where "cms_page_view"."id" = ? and "cms_page_view"."tenant_id" = ?' ) )->count() );
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
    }


    public function testSameAliasDoesntOverwritePages(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'About news', 'alias' => 'about', 'language' => 'en-GB', 'introtext' => '<p>News</p>', 'fulltext' => '', 'catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // the article with the lower ID keeps the alias, the other one gets its ID appended
        $this->assertStringContainsString( 'Imported: About news (/about-5)', $output );
        $this->assertStringContainsString( 'Imported: About (/about)', $output );
        $this->assertStringContainsString( 'Import complete. 3 imported, 0 updated, 0 unchanged (3/3 articles processed).', $output );
        $this->assertStringContainsString( 'News', (string) json_encode( PageView::where( 'path', 'about-5' )->firstOrFail()->content ) );
        $this->assertSame( 'About', PageView::where( 'path', 'about' )->firstOrFail()->name );

        // the next import uses the same URLs
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: About news (/about-5)', $output );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertSame( 1, PageView::where( 'name', 'About' )->count() );
    }


    public function testCategoryConflictImportsArticlesBelowParent(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Other news', 'alias' => 'news', 'level' => 2],
        ] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'First', 'alias' => 'first', 'language' => 'en-GB', 'introtext' => '<p>First</p>', 'fulltext' => '', 'catid' => 2],
            ['id' => 6, 'title' => 'Second', 'alias' => 'second', 'language' => 'en-GB', 'introtext' => '<p>Second</p>', 'fulltext' => '', 'catid' => 3],
            ['id' => 7, 'title' => 'Third', 'alias' => 'third', 'language' => 'en-GB', 'introtext' => '<p>Third</p>', 'fulltext' => '', 'catid' => 3],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // categories with the same alias get their ID appended
        $this->assertSame( 'news-3', PageView::where( 'name', 'Other news' )->firstOrFail()->path );
        $this->assertSame( PageView::where( 'name', 'Other news' )->firstOrFail()->id, PageView::where( 'name', 'Second' )->firstOrFail()->parent_id );

        PageView::where( 'name', 'Other news' )->firstOrFail()->variant->forceFill( ['editor' => 'someone', 'name' => 'Changed news'] )->save();

        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertSame( 1, substr_count( $output, 'Failed to import category [3] Other news' ) );
        $this->assertStringContainsString( 'Unchanged: Second (/second)', $output );
        $this->assertStringContainsString( 'Unchanged: Third (/third)', $output );

        $news = PageView::where( 'name', 'News' )->firstOrFail();
        $this->assertSame( $news->id, PageView::where( 'name', 'First' )->firstOrFail()->parent_id );
        $this->assertSame( PageView::where( 'name', 'About' )->firstOrFail()->parent_id, PageView::where( 'name', 'Second' )->firstOrFail()->parent_id );
        $this->assertSame( 'someone', PageView::where( 'name', 'Changed news' )->firstOrFail()->editor );
    }


    public function testNestedCategoryConflictImportsArticlesBelowParentCategory(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Company', 'alias' => 'company', 'parent_id' => 0, 'level' => 2, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 2, 'level' => 3, 'lft' => 2],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Other news', 'alias' => 'news', 'parent_id' => 2, 'level' => 3, 'lft' => 3],
        ] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'First', 'alias' => 'first', 'language' => 'en-GB', 'introtext' => '<p>First</p>', 'fulltext' => '', 'catid' => 3],
            ['id' => 6, 'title' => 'Second', 'alias' => 'second', 'language' => 'en-GB', 'introtext' => '<p>Second</p>', 'fulltext' => '', 'catid' => 4],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        PageView::where( 'name', 'Other news' )->firstOrFail()->variant->forceFill( ['editor' => 'someone', 'name' => 'Changed news'] )->save();
        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the articles of the conflicting category are imported below the page of its parent category, not the root page
        $this->assertSame( 1, substr_count( Artisan::output(), 'Failed to import category [4] Other news' ) );
        $this->assertSame( PageView::where( 'name', 'News' )->firstOrFail()->id, PageView::where( 'name', 'First' )->firstOrFail()->parent_id );
        $this->assertSame( PageView::where( 'name', 'Company' )->firstOrFail()->id, PageView::where( 'name', 'Second' )->firstOrFail()->parent_id );
    }


    public function testFetchesTheArticlesInChunksWithTheirTranslations(): void
    {
        $command = new class extends JoomlaImport {
            protected const TEXT_SIZE = 3;
        };
        Artisan::registerCommand( $command );

        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 2] );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $queries = Collection::make( $db->getQueryLog() )->pluck( 'bindings', 'query' );
        $db->disableQueryLog();

        $this->assertTrue( $queries->has( 'select "id", "catid", "language", "alias", "title" from "content" where "state" in (?, ?) order by "ordering" asc, "created" asc' ) );
        $this->assertTrue( $queries->has( 'select "id", "parent_id", "level", "title", "alias", "published" from "categories" where "extension" = ? order by "lft" asc' ) );
        // the published menu items of all menus are fetched once
        $this->assertSame( ['select "id", "menutype", "parent_id", "title", "alias", "link", "type", "lft", "level" from "menu" where "published" = ? and "client_id" = ? order by "lft" asc'],
            $queries->keys()->filter( fn( $query ) => str_contains( $query, ' from "menu"' ) )->values()->all() );

        // the other columns are fetched per chunk of articles in import order, each article followed by its translations
        $rows = $queries->filter( fn( $bindings, $query ) => str_starts_with( $query, 'select "id", "title", "alias", "catid", "created", "modified", "featured", "language", "metadesc" from "content" where "id" in' ) );
        $this->assertEquals( [[4, 5], [1, 2, 3]], $rows->values()->map( fn( $ids ) => Collection::make( $ids )->sort()->values()->all() )->all() );
        $this->assertStringContainsString( "Import complete. 3 imported, 0 updated, 0 unchanged (3/3 articles processed).\nFinished with 0 failures.", Artisan::output() );
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
        $this->assertSame( PageView::where( 'name', 'News' )->firstOrFail()->id, PageView::where( 'name', 'Other' )->firstOrFail()->parent_id );
    }


    public function testIndexFetchesTimesOnlyWithSince(): void
    {
        $db = DB::connection( 'joomla' );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $queries = Collection::make( $db->getQueryLog() )->pluck( 'query' );
        $db->disableQueryLog();

        $this->assertContains( 'select "id", "catid", "language", "alias", "title", "created", "modified" from "content" where "state" in (?, ?) order by "ordering" asc, "created" asc', $queries->all() );
        $this->assertStringContainsString( 'Unchanged: About (/about)', Artisan::output() );
    }


    public function testDryRunListsArticlesWithoutTranslations(): void
    {
        DB::connection( 'joomla' )->table( 'content' )->insert( ['id' => 5, 'title' => 'Featured', 'alias' => 'featured', 'language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'featured' => 1, 'ordering' => -1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        // the articles are listed by their position in their categories
        $this->assertStringContainsString( "Found 3 Joomla articles.\nFound 2 Joomla article translations.\n"
            . "  [5] Featured (featured) [featured]\n  [4] Nur Deutsch (nur-deutsch)\n  [1] About (about)\nDry run complete. No changes were made.", $output );
        $this->assertSame( 0, PageView::count() );
    }


    public function testDryRunListsArticlesOfAllChunks(): void
    {
        $rows = array_map( fn( $id ) => ['id' => $id, 'title' => "Article {$id}", 'alias' => "article-{$id}", 'language' => 'en-GB',
            'introtext' => '', 'fulltext' => '', 'ordering' => $id], range( 1001, 3001 ) );

        foreach( array_chunk( $rows, 200 ) as $chunk ) {
            DB::connection( 'joomla' )->table( 'content' )->insert( $chunk );
        }

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $output = Artisan::output();

        // the articles of the second chunk follow the ones of the first chunk in the order Joomla uses
        $this->assertStringContainsString( "  [1] About (about)\n  [1001] Article 1001 (article-1001)\n", $output );
        $this->assertStringContainsString( "  [2997] Article 2997 (article-2997)\n  [2998] Article 2998 (article-2998)\n", $output );
        $this->assertStringContainsString( "  [3000] Article 3000 (article-3000)\n  [3001] Article 3001 (article-3001)\nDry run complete.", $output );
        $this->assertSame( 2003, substr_count( $output, "\n  [" ) );
    }


    public function testReimportKeepsChangedPages(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        PageView::where( 'name', 'About' )->firstOrFail()->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $output = Artisan::output();

        $this->assertStringContainsString( "Failed to import [1] About: URL /about is used by a page which wasn't imported or was changed since", $output );
        $this->assertSame( 'someone', PageView::where( 'name', 'About' )->firstOrFail()->editor );
        // the failures are summarized at the end and the command fails
        $this->assertStringContainsString( "1 imports failed:\n  [1] About: URL /about is used by a page which wasn't imported or was changed since\nFinished with 1 failures.", $output );
    }


    public function testReimportRemovesLeadingSlash(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        // paths are stored like the ones of pages created in Pagible
        $this->assertSame( 'about', $page->path );
        $this->assertSame( '', PageView::where( 'tag', 'root' )->firstOrFail()->path );

        // pages of earlier imports have paths with leading slash
        $page->variant->forceFill( ['path' => '/about'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $this->assertStringContainsString( 'Updated: About (/about)', Artisan::output() );
        $this->assertSame( 'about', PageView::findOrFail( $page->id )->path );
        $this->assertSame( 1, PageView::where( 'name', 'About' )->count() );
    }


    public function testImportTranslationsLanguageOption(): void
    {
        $result = Artisan::call( 'cms:joomla-import', [
            '--domain' => 'example.com',
            '--language' => ['de-DE:de-AT:https://example.at/'],
        ] );

        $this->assertSame( 0, $result );

        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertSame( 'example.at', PageView::language( 'de-AT' )->findOrFail( $page->id )->domain );
        $this->assertSame( 'ueber-uns', PageView::language( 'de-AT' )->findOrFail( $page->id )->path );
        $this->assertNull( PageView::language( 'de' )->find( $page->id ) );
    }


    public function testImportWithoutSourceArticle(): void
    {
        DB::connection( 'joomla' )->table( 'associations' )->where( 'id', 1 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'name', 'Über uns' )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'À propos' )->count() );
    }


    public function testImportDisablesArticlesGuestsCantSee(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [
            ['id' => 1, 'title' => 'Public', 'rules' => '[1]'],
            ['id' => 2, 'title' => 'Registered', 'rules' => '[6,2,8]'],
            ['id' => 5, 'title' => 'Guest', 'rules' => '[9]'],
            ['id' => 6, 'title' => 'Visitors', 'rules' => '[13]'],
        ] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1], ['id' => 13, 'parent_id' => 1]] );
        $db->table( 'extensions' )->insert( ['element' => 'com_users', 'params' => '{"guest_usergroup":"13"}'] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members area', 'alias' => 'members-area', 'parent_id' => 1, 'level' => 2, 'lft' => 1, 'access' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 3, 'lft' => 2, 'access' => 1],
        ] );
        $article = ['language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'catid' => 0, 'access' => 1, 'publish_up' => null, 'publish_down' => null];
        $db->table( 'content' )->insert( [
            // guests don't see the intro text of articles they can't access by default
            ['id' => 5, 'title' => 'Members', 'alias' => 'members', 'access' => 2, 'introtext' => '<p>Intro</p>', 'fulltext' => '<p>Full</p>'] + $article,
            ['id' => 6, 'title' => 'Old', 'alias' => 'old', 'publish_down' => '2000-01-02 03:04:00'] + $article,
            ['id' => 7, 'title' => 'Soon', 'alias' => 'soon', 'publish_up' => '2999-01-01 00:00:00'] + $article,
            ['id' => 8, 'title' => 'Visitors only', 'alias' => 'visitors-only', 'access' => 6] + $article,
            ['id' => 9, 'title' => 'Zero dates', 'alias' => 'zero-dates', 'publish_up' => '0000-00-00 00:00:00', 'publish_down' => '0000-00-00 00:00:00'] + $article,
            ['id' => 10, 'title' => 'Inside', 'alias' => 'inside', 'catid' => 3] + $article,
            ['id' => 11, 'title' => 'Former guests', 'alias' => 'former-guests', 'access' => 5] + $article,
        ] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['publish_down' => '2000-01-01 00:00:00'] );
        $menu = ['menutype' => 'mainmenu', 'access' => 2, 'publish_up' => null, 'publish_down' => null];
        $db->table( 'menu' )->insert( [
            ['id' => 2, 'title' => 'Hidden heading', 'alias' => 'hidden-heading', 'link' => '', 'type' => 'heading', 'lft' => 1] + $menu,
            ['id' => 3, 'title' => 'About link', 'alias' => 'about-link', 'link' => 'index.php?option=com_content&view=article&id=1', 'type' => 'component', 'lft' => 2] + $menu,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $status = fn( $name ) => PageView::where( 'name', $name )->firstOrFail()->status;

        $this->assertStringContainsString( "Disabled: Members (/members) (it's restricted to Joomla access level Registered)", $output );
        $this->assertStringContainsString( 'Disabled: Old (/old) (it expired on ' . date( 'Y-m-d H:i', (int) strtotime( '2000-01-02 03:04:00 UTC' ) ), $output );
        $this->assertStringContainsString( "Disabled: Soon (/soon) (it's scheduled for " . date( 'Y-m-d H:i', (int) strtotime( '2999-01-01 00:00:00 UTC' ) ) . ', enable it then', $output );
        $this->assertStringContainsString( "Disabled: Members area (/members-area) (it's restricted to Joomla access level Registered)", $output );
        $this->assertStringContainsString( 'Disabled: Sub (/sub) (its category Members area is restricted to Joomla access level Registered)', $output );
        $this->assertStringContainsString( 'Disabled: Inside (/inside) (its category Members area is restricted to Joomla access level Registered)', $output );
        $this->assertStringContainsString( "Disabled: Hidden heading (/hidden-heading) (it's restricted to Joomla access level Registered)", $output );
        $this->assertStringContainsString( "Skipped menu item: [3] About link (it's restricted to Joomla access level Registered)", $output );
        $this->assertStringContainsString( 'Disabled translation: Über uns (/ueber-uns) [de] (it expired on', $output );
        $this->assertStringNotContainsString( 'Failed', $output );

        foreach( ['Members', 'Old', 'Soon', 'Members area', 'Sub', 'Inside', 'Former guests', 'Hidden heading'] as $name ) {
            $this->assertSame( 0, $status( $name ), $name );
        }

        // the language filter skips articles in other languages without translation
        foreach( ['About', 'Visitors only', 'Zero dates'] as $name ) {
            $this->assertSame( 1, $status( $name ), $name );
        }

        // articles of skipped menu items are imported below their category
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertNotSame( PageView::where( 'name', 'Hidden heading' )->firstOrFail()->id, $about->parent_id );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $about->id )->status );
        $this->assertSame( PageView::where( 'name', 'Sub' )->firstOrFail()->id, PageView::where( 'name', 'Inside' )->firstOrFail()->parent_id );

        // pages are published by the next import if guests can see the articles
        $db->table( 'content' )->whereIn( 'id', [2, 5] )->update( ['access' => 1, 'publish_down' => null] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: Members (/members)', $output );
        $this->assertSame( 1, $status( 'Members' ) );
        $this->assertSame( 1, PageView::language( 'de' )->findOrFail( $about->id )->status );
    }


    public function testDisabledArticlesDisableTheirTranslations(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['access' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // without view levels in the database, only the public level 1 is public
        $this->assertStringContainsString( "Disabled: About (/about) (it's restricted to Joomla access level 2)", Artisan::output() );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( 0, $page->status );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $page->id )->status );
    }


    public function testImportWithoutSinceRendersAllArticlesAgain(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['introtext' => '<p>Changed</p>'] );
        DB::connection( 'joomla' )->table( 'content' )->whereIn( 'id', [2, 3] )->update( ['created' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/about)', Artisan::output() );

        // the --since option of the previous run isn't used again by the same command
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Updated: About (/about)', Artisan::output() );
        $this->assertStringContainsString( 'Changed', json_encode( PageView::findOrFail( $page->id )->content ) ?: '' );
    }


    public function testImportWithoutSinceSkipsTheKeepChecks(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $cms = DB::connection();
        $urlQueries = function() use ( $cms ) {
            $queries = $this->queries( $cms )->filter( fn( $query ) => str_starts_with( $query['query'], 'select "id", "domain", "path" from "cms_page_variants"' ) );
            $cms->flushQueryLog();
            return $queries->count();
        };
        $textIds = function() use ( $db ) {
            $ids = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) )->pluck( 'bindings' )->flatten()->sort()->values()->all();
            $db->flushQueryLog();
            return $ids;
        };

        // the moved variant of the translation makes the keep checks look for other pages using its URL
        $db->table( 'content' )->where( 'id', 2 )->update( ['alias' => 'firma'] );
        $cms->enableQueryLog();
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertGreaterThan( 0, $urlQueries() );
        $textIds();

        // unchanged names and paths don't keep pages without --since, all texts are fetched and no URL is checked
        $db->table( 'content' )->where( 'id', 2 )->update( ['alias' => 'unternehmen'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $cms->disableQueryLog();
        $db->disableQueryLog();

        $this->assertSame( 0, $urlQueries() );
        $this->assertSame( [1, 2, 3, 4], $textIds() );
        $this->assertSame( 'unternehmen', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'About' )->firstOrFail()->id )->path );
    }


    public function testImportsArchivedAndSkipsHiddenCategories(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Archive', 'alias' => 'archive', 'published' => 2, 'parent_id' => 1, 'level' => 2, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Hidden', 'alias' => 'hidden', 'published' => 0, 'parent_id' => 1, 'level' => 2, 'lft' => 2],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Below hidden', 'alias' => 'below-hidden', 'published' => 1, 'parent_id' => 3, 'level' => 3, 'lft' => 3],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Trashed', 'alias' => 'trashed', 'published' => -2, 'parent_id' => 1, 'level' => 2, 'lft' => 4],
        ] );
        $article = ['language' => 'en-GB', 'introtext' => '<p>Text</p>', 'fulltext' => '', 'state' => 1, 'catid' => 0];
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Archived', 'alias' => 'archived', 'state' => 2, 'catid' => 2] + $article,
            ['id' => 6, 'title' => 'In hidden', 'alias' => 'in-hidden', 'catid' => 3] + $article,
            ['id' => 7, 'title' => 'Below', 'alias' => 'below', 'catid' => 4] + $article,
            ['id' => 8, 'title' => 'In trash', 'alias' => 'in-trash', 'catid' => 5] + $article,
            ['id' => 9, 'title' => 'Unpublished', 'alias' => 'unpublished', 'state' => 0] + $article,
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // Joomla shows archived articles and the articles of archived categories but not the ones of unpublished or trashed categories
        $archive = PageView::where( 'name', 'Archive' )->firstOrFail();
        $archived = PageView::where( 'name', 'Archived' )->firstOrFail();
        $this->assertSame( $archive->id, $archived->parent_id );
        // archived categories aren't shown themselves
        $this->assertSame( [0, 1], [$archive->status, $archived->status] );
        $this->assertStringContainsString( 'Disabled: Archive (/archive) (the category Archive is archived)', $output );
        $this->assertSame( 0, PageView::whereIn( 'name', ['In hidden', 'Below', 'In trash', 'Unpublished', 'Hidden', 'Below hidden', 'Trashed'] )->count() );
        $this->assertStringContainsString( 'Found 3 Joomla articles.', $output );
    }


    public function testLanguageFilterSkipsArticlesAndMenuItemsOfOtherLanguages(): void
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        // with language filter, Joomla only shows the French translation if French is a content language
        $db->table( 'languages' )->insert( ['lang_code' => 'fr-FR', 'sef' => 'fr'] );
        $db->table( 'menu' )->insert( [
            ['language' => 'de-DE'] + $this->menuItem( 101, 'Firma', 1, 1 ),
            ['language' => 'en-GB'] + $this->menuItem( 102, 'Company', 1, 1 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // Joomla shows the German article without associated English one only on the German site
        $this->assertStringContainsString( 'Skipping 1 Joomla articles in other languages without associated article in the import language', $output );
        $this->assertStringContainsString( 'Found 1 Joomla articles.', $output );
        $this->assertSame( 0, PageView::whereIn( 'name', ['Nur Deutsch', 'Firma'] )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'Company' )->count() );
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
    }


    public function testLanguageFilterSkipsTranslationsOfLanguagesMissingFromTheLanguagesTable(): void
    {
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        // only "en-GB" and "de-DE" are content languages, so Joomla never shows the "en-AU" and "fr-FR" articles
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'G\'day', 'alias' => 'gday', 'language' => 'en-AU', 'introtext' => '<p>Hi</p>', 'fulltext' => ''] );
        $db->table( 'associations' )->insert( ['id' => 5, 'context' => 'com_content.item', 'key' => 'abc'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();

        // the tag isn't guessed as the import language from its language part "en"
        $this->assertStringContainsString( 'Skipping article translations of unknown or unpublished Joomla languages [fr-FR, en-AU]', $output );
        $this->assertSame( 0, PageView::where( 'name', 'G\'day' )->count() );
        $this->assertSame( ['de', 'en'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
    }


    public function testUnpublishedLanguagesAreSkipped(): void
    {
        DB::connection( 'joomla' )->table( 'languages' )->where( 'lang_code', 'de-DE' )->update( ['published' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $this->assertStringContainsString( 'Skipping article translations of unknown or unpublished Joomla languages [de-DE]', $output );
        // without language filter, no articles of other languages are skipped
        $this->assertStringNotContainsString( 'Joomla articles in other languages', $output );
        $this->assertSame( ['en', 'fr'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
        // Joomla shows them without language filter like the articles without association
        $this->assertSame( 1, PageView::where( 'name', 'Über uns' )->count() );
    }


    public function testLanguageOptionImportsUnpublishedLanguages(): void
    {
        DB::connection( 'joomla' )->table( 'languages' )->where( 'lang_code', 'de-DE' )->update( ['published' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
        $this->assertSame( 0, PageView::where( 'name', 'Über uns' )->count() );
    }


    public function testPendingArticlesAreOnlyTheImportedOnesWithoutPage(): void
    {
        $command = new class extends JoomlaImport {
            /** @var array<int, bool> */
            public array $pending = [];

            public function handle(): int
            {
                $result = parent::handle();

                // the article with page and the unknown article aren't pending, so they aren't fetched and read from the chunk
                $this->prefetch( [1, 99] );
                $this->pending = [1 => $this->pendingArticle( 1 ) === null, 99 => $this->pendingArticle( 99 ) === null];
                return $result;
            }
        };
        Artisan::registerCommand( $command );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( [1 => true, 99 => true], $command->pending );
    }


    public function testRepeatedRunsDontReuseThePagesOfThePreviousRun(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( $this->menuItem( 101, 'First', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 102, 'Second', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 103, 'Child', 102, 2 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( PageView::where( 'name', 'First' )->firstOrFail()->id, PageView::where( 'name', 'Child' )->firstOrFail()->parent_id );

        // the article page of the previous run isn't the parent of the child items anymore if the article isn't imported
        $db->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => 0] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( PageView::where( 'tag', 'root' )->firstOrFail()->id, PageView::where( 'name', 'Child' )->firstOrFail()->parent_id );
    }


    public function testFailedArticlesAreTriedAndReportedOnce(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->insert( $this->menuItem( 101, 'First', 1, 1, 1 ) );
        $db->table( 'menu' )->insert( $this->menuItem( 102, 'Second', 1, 1, 1 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        PageView::where( 'name', 'First' )->firstOrFail()->variant?->forceFill( ['editor' => 'someone'] )->save();

        // the article fails for the first menu item, the second one and the category loop don't try it again
        $command = new class extends JoomlaImport {
            public function failures(): int
            {
                return $this->failed;
            }
        };
        Artisan::registerCommand( $command );

        $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertSame( 1, substr_count( $output, 'Failed to import [1] About' ) );
        $this->assertSame( 1, $command->failures() );
        $this->assertStringContainsString( 'Unchanged: Nur Deutsch (/nur-deutsch)', $output );
    }


    public function testTranslationsAreRestrictedByTheirCategories(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Members area', 'alias' => 'members-area', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'access' => 2, 'language' => '*', 'published' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 2, 'access' => 1, 'language' => 'en-GB', 'published' => 1],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'parent_id' => 2, 'level' => 2, 'lft' => 3, 'access' => 1, 'language' => 'de-DE', 'published' => 1],
            ['id' => 5, 'extension' => 'com_content', 'title' => 'Events', 'alias' => 'events', 'parent_id' => 1, 'level' => 2, 'lft' => 4, 'access' => 1, 'language' => 'en-GB', 'published' => 1],
            ['id' => 6, 'extension' => 'com_content', 'title' => 'Termine', 'alias' => 'termine', 'parent_id' => 1, 'level' => 2, 'lft' => 5, 'access' => 1, 'language' => 'de-DE', 'published' => 2],
        ] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 2] );
        $db->table( 'content' )->insert( [
            ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 3],
            ['id' => 6, 'title' => 'Event', 'alias' => 'event', 'language' => 'en-GB', 'introtext' => '<p>Event</p>', 'fulltext' => '', 'catid' => 5],
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'news'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'news'],
            ['id' => 5, 'context' => 'com_categories.item', 'key' => 'events'],
            ['id' => 6, 'context' => 'com_categories.item', 'key' => 'events'],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $de = fn( string $name ) => PageView::language( 'de' )->findOrFail( PageView::where( 'name', $name )->firstOrFail()->id )->status;

        // the categories of the translated articles and the parents of the translated categories restrict them too
        $this->assertStringContainsString( 'Disabled translation: Über uns (/ueber-uns) [de] (its category Members area is restricted to Joomla access level Registered)', $output );
        $this->assertStringContainsString( 'Disabled translation: Nachrichten (/nachrichten) [de] (its category Members area is restricted to Joomla access level Registered)', $output );
        $this->assertStringContainsString( 'Disabled translation: Termine (/termine) [de] (the category Termine is archived)', $output );
        $this->assertSame( [0, 0, 0], [$de( 'About' ), $de( 'News' ), $de( 'Events' )] );
    }


    public function testPublicTranslationsOfDisabledCategoriesAreDisabledWithoutWarning(): void
    {
        $this->accessColumns();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $db->table( 'categories' )->insert( [
            ['id' => 3, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'parent_id' => 1, 'level' => 2, 'lft' => 1, 'access' => 2, 'language' => 'en-GB', 'published' => 1],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Nachrichten', 'alias' => 'nachrichten', 'parent_id' => 1, 'level' => 2, 'lft' => 2, 'access' => 1, 'language' => 'de-DE', 'published' => 1],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 3] );
        $db->table( 'associations' )->insert( [['id' => 3, 'context' => 'com_categories.item', 'key' => 'news'], ['id' => 4, 'context' => 'com_categories.item', 'key' => 'news']] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $output = Artisan::output();
        $news = PageView::where( 'name', 'News' )->firstOrFail();

        // the public translation of the disabled category is disabled too, but it isn't the reason, so there's no warning
        $this->assertSame( 0, $news->status );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $news->id )->status );
        $this->assertStringNotContainsString( 'Nachrichten', $output );
    }


    public function testJoomla3EmptyDatesOfOtherDatabases(): void
    {
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['publish_down' => '1970-01-01 00:00:00', 'publish_up' => '1900-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['publish_down' => '1900-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // PostgreSQL and SQL Server use these dates as empty dates in Joomla 3
        $this->assertSame( [1, 1], PageView::whereIn( 'name', ['About', 'Nur Deutsch'] )->orderBy( 'name' )->pluck( 'status' )->all() );
        $this->assertStringNotContainsString( 'Disabled', Artisan::output() );
    }


    public function testRunsDontReuseTheRememberedValuesOfPreviousRuns(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'redirect_links' )->insert( ['id' => 1, 'old_url' => 'moved', 'new_url' => 'index.php/about', 'published' => 1] );

        // the plugins of the Joomla extensions are remembered per run, so a plugin enabled since is used by the next run
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}', 'enabled' => 0] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, PageView::where( 'path', 'moved' )->count() );

        $db->table( 'extensions' )->where( 'element', 'redirect' )->update( ['enabled' => 1] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'path', 'moved' )->count() );
    }


    public function testOnlyInstalledSiteLanguagesGuestsCanAccessAreUsed(): void
    {
        $this->extraTables();
        $this->languageFilter();
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'languages', fn( Blueprint $table ) => $table->unsignedInteger( 'access' )->default( 1 ) );
        $schema->create( 'viewlevels', function( Blueprint $table ) {
            $table->id();
            $table->string( 'title' );
            $table->string( 'rules' );
        } );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'languages' )->insert( ['lang_code' => 'fr-FR', 'sef' => 'fr', 'access' => 2] );
        $db->table( 'extensions' )->insert( [
            ['type' => 'language', 'element' => 'en-GB', 'params' => '{}', 'enabled' => 1],
            ['type' => 'language', 'element' => 'fr-FR', 'params' => '{}', 'enabled' => 1],
            ['type' => 'language', 'element' => 'de-DE', 'params' => '{}', 'enabled' => 0],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the German site language is disabled and the French content language is restricted
        $this->assertStringContainsString( 'Skipping article translations of unknown or unpublished Joomla languages [de-DE, fr-FR]', Artisan::output() );
        $this->assertSame( [], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->where( 'lang', '!=', 'en' )->pluck( 'lang' )->all() );

        // access level 0 is public too
        $db->table( 'languages' )->where( 'lang_code', 'fr-FR' )->update( ['access' => 0] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'Skipping article translations of unknown or unpublished Joomla languages [de-DE]', Artisan::output() );

        // without unknown languages, there's no warning
        $db->table( 'extensions' )->where( 'element', 'de-DE' )->update( ['enabled' => 1] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'translations of unknown', Artisan::output() );
    }


    public function testTranslationsWithTheSameLanguageCodeAreReported(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'languages' )->where( 'lang_code', 'de-DE' )->delete();
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Über uns AT', 'alias' => 'ueber-uns-at', 'language' => 'de-AT', 'introtext' => '<p>Servus</p>', 'fulltext' => ''] );
        $db->table( 'associations' )->insert( ['id' => 5, 'context' => 'com_content.item', 'key' => 'abc'] );

        // both guessed tags get "de", only the translation with the lowest ID is used and the other one is reported
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringContainsString( 'Skipping Joomla article translations [5 (de-AT, "de" used by 2)] because another translation', Artisan::output() );

        // tags whose guessed code is the one of a --language option of another tag aren't guessed
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-AT:de']] ) );
        $output = Artisan::output();
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        $this->assertStringContainsString( 'Skipping article translations of unknown or unpublished Joomla languages [de-DE]', $output );
        $this->assertStringNotContainsString( 'Skipping Joomla article translations', $output );
        $this->assertSame( 'ueber-uns-at', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testCategoryAndMenuItemTranslationsWithTheSameLanguageCodeAreReported(): void
    {
        $this->associatedFolders();
        $db = DB::connection( 'joomla' );
        $db->table( 'menu' )->where( 'id', 102 )->update( ['menutype' => 'mainmenu-at'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // the kind of the skipped translations is reported, so the IDs are unambiguous
        $this->assertStringContainsString( 'Skipping Joomla category translations [4 (de-DE, "de" used by 3)] because another translation', $output );
        $this->assertStringContainsString( 'Skipping Joomla menu item translations [103 (de-DE, "de" used by 102)] because another translation', $output );
        $this->assertStringNotContainsString( 'Skipping Joomla article translations', $output );
        $this->assertSame( 'nachrichten-at', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'News' )->firstOrFail()->id )->path );
        $this->assertSame( 'Firma AT', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'Company' )->firstOrFail()->id )->name );
    }


    public function testMenuItemTranslationsOfTheImportedMenuDontHideOtherTranslations(): void
    {
        $this->associatedFolders();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        // the menu item "Firma AT" of the imported menu is a page itself, so the other one in the same language is the translation
        $this->assertStringNotContainsString( 'Skipping Joomla menu item translations', $output );
        $this->assertSame( 'Firma DE', PageView::language( 'de' )->findOrFail( PageView::where( 'name', 'Company' )->firstOrFail()->id )->name );
        $this->assertSame( 1, PageView::where( 'name', 'Firma AT' )->count() );
    }


    /**
     * Adds associated categories and menu items in "en-GB", "de-AT" and "de-DE" whose languages are all guessed, so both German ones get "de".
     *
     * The category and the menu item in "de-AT" have the lower IDs, the menu item in "de-AT" is in the imported menu.
     */
    protected function associatedFolders(): void
    {
        $schema = Schema::connection( 'joomla' );
        $schema->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $schema->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'languages' )->where( 'lang_code', 'de-DE' )->delete();
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'language' => 'en-GB', 'parent_id' => 1, 'level' => 2, 'lft' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Nachrichten AT', 'alias' => 'nachrichten-at', 'language' => 'de-AT', 'parent_id' => 1, 'level' => 2, 'lft' => 2],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Nachrichten DE', 'alias' => 'nachrichten-de', 'language' => 'de-DE', 'parent_id' => 1, 'level' => 2, 'lft' => 3],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'language' => 'en-GB', 'introtext' => '<p>Other</p>', 'fulltext' => '', 'catid' => 2] );
        $db->table( 'menu' )->insert( [
            ['language' => 'en-GB'] + $this->menuItem( 101, 'Company', 1, 1 ),
            ['language' => 'de-AT'] + $this->menuItem( 102, 'Firma AT', 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de'] + $this->menuItem( 103, 'Firma DE', 1, 1 ),
        ] );
        $db->table( 'associations' )->insert( [
            ['id' => 2, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'cat'],
            ['id' => 101, 'context' => 'com_menus.item', 'key' => 'menu'],
            ['id' => 102, 'context' => 'com_menus.item', 'key' => 'menu'],
            ['id' => 103, 'context' => 'com_menus.item', 'key' => 'menu'],
        ] );
    }


    public function testAssociationsAreOnlyTranslationsWithLanguageFilter(): void
    {
        $this->extraTables();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );

        // without the language filter plugin, Joomla doesn't use the associations
        $this->assertSame( [], PageVariant::where( 'page_id', PageView::where( 'name', 'About' )->firstOrFail()->id )->where( 'lang', 'de' )->pluck( 'lang' )->all() );
        $this->assertSame( 1, PageView::where( 'name', 'Über uns' )->count() );
    }


    public function testArticlesExpiringNowAreShown(): void
    {
        $now = $this->freezeSecond();
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->where( 'id', 1 )->update( ['publish_down' => $now->copy()->utc()->format( 'Y-m-d H:i:s' )] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['publish_down' => $now->copy()->utc()->subSecond()->format( 'Y-m-d H:i:s' )] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // like Joomla, articles are shown until the second they expire
        $this->assertSame( 1, PageView::where( 'name', 'About' )->firstOrFail()->status );
        $this->assertSame( 0, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );
    }


    public function testSinceImportsArticlesWhosePublishingDatesPassed(): void
    {
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00'] );
        // expires and is published tomorrow, only the dates pass until the next run
        $db->table( 'content' )->where( 'id', 4 )->update( ['publish_down' => now()->utc()->addDay()->format( 'Y-m-d H:i:s' )] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['publish_up' => now()->utc()->addDay()->format( 'Y-m-d H:i:s' )] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );
        $this->assertSame( 0, PageView::where( 'name', 'Über uns' )->firstOrFail()->status );

        $since = now()->utc()->format( 'Y-m-d H:i:s' );
        $this->travel( 2 )->days();

        // articles whose publishing or expiry date passed since are changed although their modification date isn't
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => $since] ) );
        $output = Artisan::output();

        $this->assertSame( 0, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );
        $this->assertSame( 1, PageView::where( 'name', 'Über uns' )->firstOrFail()->status );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Updated: Über uns (/ueber-uns)', $output );
    }


    public function testSinceImportsArticlesWhoseMenuItemPublishingDatesPassed(): void
    {
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        // the menu item has the same title and alias as the article, so only its publishing date passes until the next run
        $db->table( 'menu' )->insert( ['alias' => 'nur-deutsch', 'publish_up' => now()->utc()->addDay()->format( 'Y-m-d H:i:s' )]
            + $this->menuItem( 101, 'Nur Deutsch', 1, 1, 4 ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $since = now()->utc()->format( 'Y-m-d H:i:s' );
        $this->travel( 2 )->days();
        $db->enableQueryLog();

        // the article of the menu item whose publishing date passed since is rendered again, the other ones are kept
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => $since] ) );
        $bindings = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) )->pluck( 'bindings' )->flatten();
        $db->disableQueryLog();
        $db->flushQueryLog();

        $this->assertContains( 4, $bindings->all() );
        $this->assertNotContains( 1, $bindings->all() );
        $this->assertStringContainsString( 'Unchanged: About (/about)', Artisan::output() );
    }


    public function testSinceImportsArticlesWhoseCategoryMenuItemPublishingDatesPassed(): void
    {
        $this->accessColumns();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 1, 'parent_id' => 1],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'level' => 2, 'parent_id' => 2],
        ] );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['catid' => 3] );
        $db->table( 'menu' )->insert( ['link' => 'index.php?option=com_content&view=category&layout=blog&id=2', 'type' => 'component',
            'publish_up' => now()->utc()->addDay()->format( 'Y-m-d H:i:s' )] + $this->menuItem( 101, 'News', 1, 1 ) );

        $command = $this->app?->make( JoomlaImport::class ) ?? throw new \RuntimeException( 'Application not initialized' );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $since = now()->getTimestamp();
        $this->travel( 2 )->days();

        // the publishing date of the category menu item counts for the articles of the category and its subcategories
        $reader = $this->reader();
        $wiring = \Aimeos\Cms\Import\Joomla\Wiring::read( $reader, \Aimeos\Cms\Import\Joomla\Host::forTests(), new \Aimeos\Cms\Import\Joomla\Origin( 'example.com' ), '', [], 'en' );
        ( new \ReflectionProperty( $command, 'wiring' ) )->setValue( $command, $wiring );
        $times = ( new \ReflectionMethod( $command, 'scheduledTimes' ) )->invoke( $command, $reader, $since, $wiring->articles( true ) );

        $this->assertArrayHasKey( 4, $times );
        $this->assertArrayNotHasKey( 1, $times );
    }


    public function testSinceIsInTheTimezoneOfTheSite(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // Joomla stores the dates in UTC, the --since dates are 11:00 and 10:00 UTC in the time zone of the site
        $db->table( 'content' )->where( 'id', 4 )->update( ['introtext' => '<p>Changed</p>', 'modified' => '2020-01-01 10:30:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01 12:00', '--timezone' => 'Europe/Berlin'] ) );
        $this->assertStringNotContainsString( 'Changed', (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01 11:00', '--timezone' => 'Europe/Berlin'] ) );
        $this->assertStringContainsString( 'Changed', (string) json_encode( PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->content ) );
    }


    public function testSincePrefetchesTheTextsOfUnchangedArticlesWithoutPage(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, PageView::where( 'name', 'Nur Deutsch' )->count() );

        // the unchanged article has no page yet, so it's rendered with the text fetched in the chunk of the articles
        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => 1] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01', '-vvv' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Imported: Nur Deutsch (/nur-deutsch)', $output );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringNotContainsString( 'on its own because', $output );
    }


    public function testFileBaseWithoutSchemeUsesHttps(): void
    {
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 1 )->update( ['images' => '{"image_fulltext":"/joomla/images/full.jpg"}'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--file-base' => 'example.com/joomla'] ) );

        // base URLs without scheme would be relative paths of the files
        $this->assertStringContainsString( 'Using https://example.com/joomla for --file-base without scheme.', Artisan::output() );
        $this->assertSame( ['https://example.com/joomla/images/full.jpg'], File::pluck( 'path' )->all() );
    }


    public function testDryRunWithSinceListsChangedArticlesAndMenuItems(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['created' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( $this->menuItem( 101, 'First', 1, 1, 1 ) );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( [
            ['id' => 1, 'old_url' => 'old.html', 'new_url' => 'index.php/new.html', 'published' => 1],
            ['id' => 2, 'old_url' => 'gone.html', 'new_url' => 'index.php?option=com_content&view=article&id=99', 'published' => 1],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $count = PageView::count();

        // the unchanged article of the renamed menu item is renamed like in a real run
        $db->table( 'menu' )->where( 'id', 101 )->update( ['title' => 'Erste'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true, '--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        // the unchanged articles aren't listed, the menu items and the redirects are because they are imported each time,
        // the unsupported redirects are skipped like by the import and the script name is removed from the targets
        $this->assertStringContainsString( "  [4] Nur Deutsch (nur-deutsch)\n  [1] About (about)\n  Menu item [101] Erste\n"
            . "  Skipped redirect: /gone.html (its target index.php?option=com_content&view=article&id=99 isn't supported)\n"
            . "  Redirect /old.html -> /new.html\nDry run", $output );
        $this->assertStringNotContainsString( '[2] ', $output );
        $this->assertSame( $count, PageView::count() );
    }


    public function testDryRunWithSinceSkipsTheMenuItemsOfKeptArticles(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( [$this->menuItem( 101, 'First', 1, 1, 1 ), $this->menuItem( 102, 'Second', 1, 1, 4 )] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $db->table( 'content' )->where( 'id', 4 )->update( ['modified' => '2020-02-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true, '--since' => '2020-01-01'] ) );
        $dryRun = Artisan::output();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $import = Artisan::output();

        // the kept article and its menu item are omitted like the import doesn't render the page again
        $this->assertStringContainsString( "  [4] Nur Deutsch (nur-deutsch)\n  Menu item [102] Second\nDry run", $dryRun );
        $this->assertStringNotContainsString( '[1] ', $dryRun );
        $this->assertStringNotContainsString( '[101] ', $dryRun );
        $this->assertStringContainsString( 'Unchanged: About (/first)', $import );
    }


    public function testDryRunPrefetchesTheSameChunksAsTheImport(): void
    {
        $command = new class extends JoomlaImport {
            protected const TEXT_SIZE = 2;

            /** @var list<list<int|string>> */
            public array $chunks = [];

            protected function prefetchArticles( array $ids ): array
            {
                $this->chunks[] = $ids;
                return parent::prefetchArticles( $ids );
            }
        };
        Artisan::registerCommand( $command );

        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'extension' => 'com_content', 'title' => 'Second', 'alias' => 'second', 'parent_id' => 1, 'level' => 1, 'lft' => 2],
            ['id' => 3, 'extension' => 'com_content', 'title' => 'First', 'alias' => 'first', 'parent_id' => 1, 'level' => 1, 'lft' => 1],
        ] );
        $db->table( 'content' )->insert( ['id' => 5, 'title' => 'Fifth', 'alias' => 'fifth', 'language' => 'en-GB', 'introtext' => '', 'fulltext' => '', 'catid' => 3] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $dryRun = $command->chunks;
        $output = Artisan::output();
        $command->chunks = [];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the articles are listed in the order of their categories in the tree like the import renders them
        $this->assertNotEmpty( $dryRun );
        $this->assertSame( $command->chunks, $dryRun );
        $this->assertStringContainsString( "  [5] Fifth (fifth)\n  [1] About (about)\n  [4] Nur Deutsch (nur-deutsch)\n", $output );
    }


    public function testReportsFailedCategoriesMenuItemsAndRedirects(): void
    {
        $this->extraTables();
        $db = DB::connection( 'joomla' );
        $db->table( 'categories' )->insert( ['id' => 2, 'extension' => 'com_content', 'title' => 'News', 'alias' => 'news', 'level' => 2] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2] );
        $db->table( 'menu' )->insert( ['id' => 102, 'menutype' => 'mainmenu', 'title' => 'Shop', 'alias' => 'shop', 'link' => 'https://shop.example.com/',
            'type' => 'url', 'parent_id' => 1, 'level' => 1, 'lft' => 2] );
        $db->table( 'extensions' )->insert( ['type' => 'plugin', 'folder' => 'system', 'element' => 'redirect', 'params' => '{}'] );
        $db->table( 'redirect_links' )->insert( ['id' => 1, 'old_url' => 'old.html', 'new_url' => 'index.php/new.html', 'published' => 1] );

        // the versions of the category, the menu item and the redirect fail
        $fail = true;
        \Illuminate\Support\Facades\Event::listen( 'eloquent.creating: ' . \Aimeos\Cms\Models\Version::class, function( \Aimeos\Cms\Models\Version $version ) use ( &$fail ) {
            $data = (string) json_encode( $version->data );

            if( $fail && ( str_contains( $data, '"News"' ) || str_contains( $data, 'shop.example.com' ) || str_contains( $data, 'new.html' ) ) ) {
                throw new \RuntimeException( 'broken' );
            }
        } );

        try
        {
            $this->assertSame( 1, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
            $output = Artisan::output();
        }
        finally
        {
            $fail = false;
        }

        // the failures of all record types are summarized at the end and the other records are imported anyway
        $this->assertStringContainsString( "3 imports failed:\n  menu item [102] Shop: broken\n  category [2] News: broken\n  redirect /old.html: broken\n", $output );
        $this->assertStringContainsString( 'Finished with 3 failures.', $output );
        $this->assertSame( 0, PageView::whereIn( 'name', ['News', 'Shop'] )->count() );
        $this->assertSame( 0, PageView::where( 'path', 'old.html' )->count() );
        $this->assertSame( 1, PageView::where( 'name', 'About' )->count() );
    }


    public function testSinceRenamesTheVariantsOfRenamedMenuItemsOfTranslations(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( [
            ['language' => 'en-GB', 'alias' => 'about-us'] + $this->menuItem( 101, 'About us', 1, 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de', 'lft' => 201] + $this->menuItem( 201, 'Firma', 1, 1, 2 ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de']] ) );
        $page = PageView::where( 'name', 'About us' )->firstOrFail();
        $this->assertSame( 'Firma', PageView::language( 'de' )->findOrFail( $page->id )->name );

        // Joomla doesn't change the translation if its menu item is renamed, so the names of the variants are compared too
        $db->table( 'menu' )->where( 'id', 201 )->update( ['title' => 'Unternehmen'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $this->assertSame( 'Unternehmen', PageView::language( 'de' )->findOrFail( $page->id )->name );

        // without further changes, the page is kept
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/', Artisan::output() );
    }


    public function testSinceMovesTheVariantsOfTranslationsWithChangedAlias(): void
    {
        $this->translatedMenuItems();
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About us' )->firstOrFail();
        $this->assertSame( 'firma', PageView::language( 'de' )->findOrFail( $page->id )->path );

        // Joomla doesn't change the translation if the alias of its menu item changes, so the URL paths of the variants are compared too
        DB::connection( 'joomla' )->table( 'menu' )->where( 'id', 201 )->update( ['alias' => 'unternehmen'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( 'unternehmen', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testSinceMovesTheVariantsOfTranslationsWithChangedCategoryPath(): void
    {
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->string( 'language' )->default( '*' );
            $table->text( 'params' )->nullable();
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'categories' )->insert( [
            ['id' => 3, 'extension' => 'com_content', 'title' => 'Local', 'alias' => 'local', 'parent_id' => 1, 'level' => 1, 'lft' => 1, 'language' => 'en-GB'],
            ['id' => 4, 'extension' => 'com_content', 'title' => 'Lokal', 'alias' => 'lokal', 'parent_id' => 1, 'level' => 1, 'lft' => 2, 'language' => 'de-DE'],
        ] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 3] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 4] );
        $db->table( 'associations' )->insert( [
            ['id' => 3, 'context' => 'com_categories.item', 'key' => 'local'],
            ['id' => 4, 'context' => 'com_categories.item', 'key' => 'local'],
        ] );
        $item = ['parent_id' => 1, 'level' => 1, 'type' => 'component', 'params' => '{}'];
        $db->table( 'menu' )->insert( [
            ['id' => 101, 'menutype' => 'mainmenu', 'title' => 'Local', 'alias' => 'local', 'language' => 'en-GB', 'lft' => 1, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=3'] + $item,
            ['id' => 201, 'menutype' => 'mainmenu-de', 'title' => 'Aktuell', 'alias' => 'aktuell', 'language' => 'de-DE', 'lft' => 201, 'link' => 'index.php?option=com_content&view=category&layout=blog&id=4'] + $item,
        ] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( 'aktuell/2-ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );

        // the URL paths of translations without own menu item change with the menu items of their categories
        $db->table( 'menu' )->where( 'id', 201 )->update( ['alias' => 'neuigkeiten'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( 'neuigkeiten/2-ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testConsecutiveSinceRunsCreateNoVersions(): void
    {
        $this->translatedMenuItems();
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        DB::connection( 'joomla' )->table( 'menu' )->where( 'id', 201 )->update( ['alias' => 'unternehmen', 'title' => 'Unternehmen'] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $count = DB::table( 'cms_versions' )->count();

        // the pages and variants saved by the first run are unchanged by the following ones
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/about-us)', Artisan::output() );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( $count, DB::table( 'cms_versions' )->count() );
    }


    public function testSinceKeepsTheVariantsChangedInPagibleOfRenamedMenuItems(): void
    {
        $this->translatedMenuItems();
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About us' )->firstOrFail();
        PageView::language( 'de' )->findOrFail( $page->id )->variant->forceFill( ['editor' => 'someone'] )->save();
        DB::connection( 'joomla' )->table( 'menu' )->where( 'id', 201 )->update( ['alias' => 'unternehmen', 'title' => 'Unternehmen'] );

        // the variant changed in Pagible isn't overwritten, so the article isn't rendered again only to skip it
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: About (/about-us)', $output );
        $this->assertStringNotContainsString( 'Skipped translation', $output );
        $this->assertSame( ['Firma', 'firma'], [PageView::language( 'de' )->findOrFail( $page->id )->name, PageView::language( 'de' )->findOrFail( $page->id )->path] );
    }


    public function testSinceKeepsArticlesWhoseMissingVariantUrlIsUsed(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the URL path of the missing variant is used by another page, so it can't be saved again
        PageVariant::where( 'lang', 'de' )->where( 'page_id', $page->id )->forceDelete();
        PageVariant::where( 'page_id', $root->id )->update( ['path' => 'ueber-uns'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        $queries = Collection::make( $db->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], '"introtext"' ) );
        $db->disableQueryLog();

        // the article isn't rendered again in every run only to skip its translation
        $this->assertSame( [], $queries->pluck( 'bindings' )->flatten()->all() );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringNotContainsString( 'Skipped translation', $output );
        $this->assertSame( 0, PageVariant::where( 'lang', 'de' )->where( 'page_id', $page->id )->count() );

        // without conflict, the missing variant is created again
        PageVariant::where( 'page_id', $root->id )->update( ['path' => ''] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testSinceRestoresTrashedVariantsOfKeptArticles(): void
    {
        DB::connection( 'joomla' )->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        // the page and its variant trashed in Pagible are both imported again although the article is unchanged
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->delete();
        PageView::whereKey( $page->id )->firstOrFail()->page->delete();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertFalse( PageView::withTrashed()->findOrFail( $page->id )->trashed() );
        $this->assertSame( 0, PageVariant::onlyTrashed()->where( 'page_id', $page->id )->count() );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );

        // the restored variant doesn't render the article again in the next run
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: About (/about)', Artisan::output() );
    }


    public function testSinceComparesUsedVariantUrlsLikeTheDatabase(): void
    {
        $command = $this->app?->make( JoomlaImport::class ) ?? throw new \RuntimeException( 'Application not initialized' );
        $key = new \ReflectionMethod( $command, 'urlKey' );
        $used = new \ReflectionMethod( $command, 'usedTranslationUrl' );
        $urls = new \ReflectionProperty( $command, 'usedTranslationUrls' );

        // databases with case- and accent-insensitive collations like MySQL return the rows with differently written URLs
        $this->assertSame( $key->invoke( $command, 'example.com', 'ueber-uns' ), $key->invoke( $command, 'Example.COM', 'Ueber-Uns' ) );
        $this->assertNotSame( $key->invoke( $command, 'example.com', 'ueber-uns' ), $key->invoke( $command, 'example.com', 'ueber-uns-2' ) );
        $this->assertNotSame( $key->invoke( $command, 'example.com', 'a' ), $key->invoke( $command, 'example.de', 'a' ) );

        $urls->setValue( $command, [$key->invoke( $command, 'example.com', 'Ueber-Uns' ) => ['v1']] );
        $this->assertTrue( $used->invoke( $command, 'example.com', 'ueber-uns', null ) );
        $this->assertFalse( $used->invoke( $command, 'example.com', 'kontakt', null ) );

        // like Variants::save(), the own variant whose path only differs in case doesn't use the URL
        $own = ( new PageView() )->forceFill( ['variant_id' => 'v1'] );
        $other = ( new PageView() )->forceFill( ['variant_id' => 'v2'] );
        $this->assertFalse( $used->invoke( $command, 'example.com', 'ueber-uns', $own ) );
        $this->assertTrue( $used->invoke( $command, 'example.com', 'ueber-uns', $other ) );

        // paths differing only in accents are the same with accent-insensitive collations, so a found row uses the URL
        if( class_exists( \Normalizer::class ) )
        {
            $this->assertSame( $key->invoke( $command, 'example.com', 'uber-uns' ), $key->invoke( $command, 'example.com', 'Über-Uns' ) );
            $urls->setValue( $command, [$key->invoke( $command, 'example.com', 'Über-Uns' ) => ['v2']] );
            $this->assertTrue( $used->invoke( $command, 'example.com', 'uber-uns', $own ) );
            $this->assertFalse( $used->invoke( $command, 'example.com', 'uber-uns', $other ) );
        }
    }


    public function testSinceRendersArticlesWhoseMissingVariantUrlOnlyDiffersInCase(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->string( 'language' )->default( '*' ) );
        $this->languageFilter();
        DB::connection( 'joomla' )->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the path differing in case isn't used for case-sensitive databases, so the missing variant is saved again
        PageVariant::where( 'lang', 'de' )->where( 'page_id', $page->id )->forceDelete();
        PageVariant::where( 'page_id', $root->id )->update( ['path' => 'Ueber-Uns'] );

        if( PageVariant::where( 'page_id', $root->id )->where( 'path', 'ueber-uns' )->exists() ) {
            $this->markTestSkipped( 'Paths differing in case are the same URL with case-insensitive collations' );
        }

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( 'ueber-uns', PageView::language( 'de' )->findOrFail( $page->id )->path );
    }


    public function testDeletedArticlesDisableTheirPages(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'Nur Deutsch' )->firstOrFail();
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $versions = $about->versions()->count();

        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 4 )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "  Disabled: /nur-deutsch [en] (its Joomla article, category or menu item isn't imported any more", $output );
        $this->assertStringContainsString( 'Disabled 1 pages of previous imports whose Joomla articles, categories or menu items aren\'t imported any more.', $output );
        $this->assertStringContainsString( 'Finished with 0 failures.', $output );
        $this->assertSame( 0, PageView::findOrFail( $page->id )->status );
        // the content is kept, only the status changes
        $this->assertStringContainsString( 'Text', (string) json_encode( PageView::findOrFail( $page->id )->content ) );
        // pages of imported articles and the root page aren't disabled and get no new version
        $this->assertSame( 1, PageView::findOrFail( $about->id )->status );
        $this->assertSame( $versions, PageView::findOrFail( $about->id )->versions()->count() );
        $this->assertSame( 1, PageView::findOrFail( $root->id )->status );

        // disabled pages aren't disabled again
        $versions = PageView::findOrFail( $page->id )->versions()->count();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Disabled', Artisan::output() );
        $this->assertSame( $versions, PageView::findOrFail( $page->id )->versions()->count() );
    }


    public function testVanishedPagesChangedInPagibleOrOfOtherImportsArentDisabled(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'Nur Deutsch' )->firstOrFail();
        $about = PageView::where( 'name', 'About' )->firstOrFail();

        // pages changed in Pagible and pages of other editors aren't disabled
        PageVariant::where( 'page_id', $page->id )->update( ['editor' => 'someone'] );
        DB::connection( 'joomla' )->table( 'content' )->whereIn( 'id', [4] )->delete();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Disabled', Artisan::output() );
        $this->assertSame( 1, PageView::findOrFail( $page->id )->status );

        // imports of other editors don't disable the pages of this one
        DB::connection( 'joomla' )->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => -2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--editor' => 'other'] ) );
        $this->assertStringNotContainsString( 'Disabled', Artisan::output() );
        $this->assertSame( 1, PageView::findOrFail( $about->id )->status );
    }


    public function testUnpublishedArticlesDisableTheirPagesAndVariants(): void
    {
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de', 'fr-FR:fr']];
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $status = fn( string $lang ) => PageView::language( $lang )->findOrFail( $page->id )->status;

        DB::connection( 'joomla' )->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => 0] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $output = Artisan::output();

        $this->assertStringContainsString( '  Disabled: /about [en] (', $output );
        $this->assertStringContainsString( '  Disabled: /ueber-uns [de] (', $output );
        $this->assertStringContainsString( '  Disabled: /a-propos [fr] (', $output );
        $this->assertStringContainsString( 'Disabled 1 pages of previous imports', $output );
        $this->assertSame( [0, 0, 0], [$status( 'en' ), $status( 'de' ), $status( 'fr' )] );
        $this->assertSame( 1, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );
    }


    public function testReappearingArticlesEnableTheirPagesAgain(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de', 'fr-FR:fr']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $status = fn( string $lang ) => PageView::language( $lang )->findOrFail( $page->id )->status;

        // unpublishing articles from the article list doesn't change their modification date, so --since still finds them
        $db->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => 0] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( [0, 0, 0], [$status( 'en' ), $status( 'de' ), $status( 'fr' )] );

        // publishing them again doesn't change their modification date either, but disabled pages aren't kept by --since
        $db->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => 1] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: About (/about)', $output );
        $this->assertStringNotContainsString( 'Disabled', $output );
        $this->assertSame( [1, 1, 1], [$status( 'en' ), $status( 'de' ), $status( 'fr' )] );

        // the same without --since
        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => -2] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $this->assertSame( 0, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );

        $db->table( 'content' )->where( 'id', 4 )->update( ['state' => 1] );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $this->assertStringContainsString( 'Updated: Nur Deutsch (/nur-deutsch)', Artisan::output() );
        $this->assertSame( 1, PageView::where( 'name', 'Nur Deutsch' )->firstOrFail()->status );
    }


    public function testSinceDoesntDisableTheKeptArticles(): void
    {
        $db = $this->sinceCategories();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // the kept articles aren't rendered, but their pages are still used by the import
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: Inside (/inside)', $output );
        $this->assertStringNotContainsString( 'Disabled', $output );
        $this->assertSame( 0, PageView::where( 'status', 0 )->count() );

        // the category page of an article which isn't imported any more stays enabled while the category is visible
        $db->table( 'content' )->where( 'id', 10 )->delete();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( '  Disabled: /inside [en] (', $output );
        $this->assertSame( 1, PageView::where( 'path', 'sub' )->firstOrFail()->status );
        $this->assertStringNotContainsString( 'Disabled: /sub [', $output );
        $this->assertSame( ['Inside'], PageView::where( 'status', 0 )->pluck( 'name' )->all() );
    }


    public function testDryRunListsThePagesWhichWouldBeDisabled(): void
    {
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de', 'fr-FR:fr']];
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $versions = $page->versions()->count();

        DB::connection( 'joomla' )->table( 'content' )->whereIn( 'id', [1, 2, 3] )->update( ['state' => -2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--dry-run' => true] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "  Disable /about [en] (its Joomla article, category or menu item isn't imported any more", $output );
        $this->assertStringContainsString( '  Disable /ueber-uns [de] (', $output );
        $this->assertStringContainsString( '  Disable /a-propos [fr] (', $output );
        $this->assertStringNotContainsString( 'nur-deutsch', substr( $output, (int) strpos( $output, '  Disable' ) ) );
        $this->assertSame( 1, PageView::findOrFail( $page->id )->status );
        $this->assertSame( $versions, PageView::findOrFail( $page->id )->versions()->count() );

        // the import disables the same pages
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $this->assertSame( 3, substr_count( Artisan::output(), '  Disabled: /' ) );
    }


    public function testFormerTranslationsAreReported(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        // translations which aren't imported any more, e.g. trashed ones or removed associations, don't delete the variants but are reported
        DB::connection( 'joomla' )->table( 'content' )->where( 'id', 3 )->update( ['state' => -2] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Former translation: About (/about) [fr] (no Joomla translation in this language is imported any more', $output );
        $this->assertStringNotContainsString( 'Former translation: About (/about) [de]', $output );
        $this->assertSame( 1, PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->count() );

        // variants changed in Pagible aren't reported
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->update( ['editor' => 'someone'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertStringNotContainsString( 'Former translation', Artisan::output() );
    }


    public function testFormerTranslationsOfKeptArticlesAreReported(): void
    {
        $db = DB::connection( 'joomla' );
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        // trashing the translation doesn't change the modification date, so the article is kept but the variant is reported
        $db->table( 'content' )->where( 'id', 3 )->update( ['state' => -2] );
        DB::enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        // the variants of the kept article are fetched once for the whole chunk, not again for each check
        $variantQueries = $this->queries( DB::connection() )->filter( fn( $query ) => str_contains( $query['query'], '"cms_page_view"."id" in' )
            && in_array( $page->id, $query['bindings'], true ) );
        DB::disableQueryLog();
        DB::flushQueryLog();

        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Former translation: About (/about) [fr] (no Joomla translation in this language is imported any more', $output );
        $this->assertStringNotContainsString( 'Former translation: About (/about) [de]', $output );
        $this->assertCount( 1, $variantQueries );
    }


    public function testDryRunWithoutRootPageWritesNothing(): void
    {
        $this->extraTables();
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['images' => '{"image_intro":"images/full.jpg"}'] );
        $db->table( 'menu' )->insert( [
            ['home' => 1, 'language' => 'en-GB', 'params' => '{}'] + $this->menuItem( 101, 'Start', 1, 1, 1 ),
            ['home' => 1, 'language' => 'de-DE', 'params' => '{}', 'menutype' => 'mainmenu-de'] + $this->menuItem( 201, 'Startseite', 1, 1, 2 ),
        ] );
        $tables = ['cms_pages', 'cms_versions', 'cms_files', 'cms_elements', 'cms_page_variants'];
        $counts = fn() => array_map( fn( $table ) => DB::table( $table )->count(), $tables );

        $this->assertSame( 0, PageView::count() );
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--language' => ['de-DE:de'], '--dry-run' => true] ) );

        $this->assertStringContainsString( "  Menu item [101] Start (root page)\nDry run", Artisan::output() );
        $this->assertSame( [0, 0, 0, 0, 0], $counts() );
    }


    public function testImportsOfOtherDomainsGetOwnRootPages(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'a.example.com'] ) );
        $rootA = PageView::where( 'tag', 'root' )->where( 'domain', 'a.example.com' )->firstOrFail();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'b.example.com'] ) );

        // the root page of the other domain is neither reused nor changed
        $this->assertStringContainsString( 'Created root page.', Artisan::output() );
        $this->assertSame( ['a.example.com', 'b.example.com'], PageView::where( 'tag', 'root' )->orderBy( 'domain' )->pluck( 'domain' )->all() );
        $this->assertSame( $rootA->id, PageView::where( 'name', 'About' )->where( 'domain', 'a.example.com' )->firstOrFail()->parent_id );
        $this->assertNotSame( $rootA->id, PageView::where( 'name', 'About' )->where( 'domain', 'b.example.com' )->firstOrFail()->parent_id );
        $this->assertSame( (string) $rootA->updated_at, (string) PageView::findOrFail( $rootA->id )->updated_at );
    }


    public function testDryRunUsesTheRootPageOfTheDomain(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', fn( Blueprint $table ) => $table->boolean( 'home' )->default( false ) );
        DB::connection( 'joomla' )->table( 'menu' )->insert( ['home' => 1] + $this->menuItem( 101, 'Start', 1, 1, 1 ) );

        // the root page of the other domain isn't one of the import, but the one of the domain would be created
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'other.example.com'] ) );
        PageView::where( 'tag', 'root' )->firstOrFail()->variant->forceFill( ['editor' => 'someone'] )->save();

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertStringContainsString( "  Menu item [101] Start (root page)\nDry run", Artisan::output() );
    }


    public function testSinceImportsArticlesWhoseParentCategoryWasRestricted(): void
    {
        $db = $this->sinceCategories();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 1, PageView::where( 'name', 'Inside' )->firstOrFail()->status );

        // Joomla updates the modification time of the category only, the articles below the subcategory are changed too
        $db->table( 'categories' )->where( 'id', 2 )->update( ['access' => 2, 'modified_time' => '2020-02-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: Outside (/outside)', Artisan::output() );
        $this->assertSame( 0, PageView::where( 'name', 'Inside' )->firstOrFail()->status );
        $this->assertSame( 1, PageView::where( 'name', 'Outside' )->firstOrFail()->status );
    }


    public function testSinceUsesCategoryTimesFromTheSinceDateUntilNow(): void
    {
        $db = $this->sinceCategories();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        // modification times in the future aren't changes yet like the publishing dates
        $db->table( 'categories' )->where( 'id', 3 )->update( ['access' => 2, 'modified_time' => '2099-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertStringContainsString( 'Unchanged: Inside (/', Artisan::output() );
        $this->assertSame( 1, PageView::where( 'name', 'Inside' )->firstOrFail()->status );

        // a modification time exactly at the --since date is a change
        $db->table( 'categories' )->where( 'id', 3 )->update( ['modified_time' => '2020-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $this->assertSame( 0, PageView::where( 'name', 'Inside' )->firstOrFail()->status );
    }


    public function testSinceSkipsArticlesWhoseCategoryWasUnpublishedLikeFullImports(): void
    {
        $db = $this->sinceCategories();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );

        $db->table( 'categories' )->where( 'id', 2 )->update( ['published' => 0, 'modified_time' => '2020-02-01 00:00:00'] );

        // Joomla doesn't show the articles below unpublished categories, so they aren't imported like by a full import
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $since = Artisan::output();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $full = Artisan::output();

        $this->assertStringNotContainsString( 'Inside', $full );
        $this->assertStringContainsString( 'Found 5 Joomla articles.', $since );
        // the pages of the articles and categories which aren't imported any more are disabled once, the same as by a full import
        $this->assertStringContainsString( '  Disabled: /inside [en] (', $since );
        $this->assertStringContainsString( '  Disabled: /sub [en] (', $since );
        $this->assertSame( $full, preg_replace( '/^.*Disabled.*\n/m', '', $since ) );
    }


    public function testSinceImportsArticlesWhoseCategoryWasRenamed(): void
    {
        $db = $this->sinceCategories();
        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 'Sub', PageView::findOrFail( PageView::where( 'name', 'Inside' )->firstOrFail()->parent_id )->name );

        $db->table( 'categories' )->where( 'id', 3 )->update( ['title' => 'Section', 'alias' => 'section', 'modified_time' => '2020-02-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        // the folder of the renamed category is a new page, the articles of the category are moved below it
        $this->assertSame( 'Section', PageView::findOrFail( PageView::where( 'name', 'Inside' )->firstOrFail()->parent_id )->name );
    }


    public function testSinceImportsTranslationsWhoseCategoryWasRestricted(): void
    {
        $db = $this->sinceCategories();
        $this->languageFilter();
        $db->table( 'categories' )->insert( ['id' => 4, 'extension' => 'com_content', 'title' => 'Bereich', 'alias' => 'bereich', 'parent_id' => 1, 'level' => 1, 'lft' => 4, 'access' => 1, 'modified_time' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 4, 'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( 1, PageView::language( 'de' )->findOrFail( $page->id )->status );

        // only the category of the translation changed
        $db->table( 'categories' )->where( 'id', 4 )->update( ['access' => 2, 'modified_time' => '2020-02-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $page->id )->status );
        $this->assertSame( 1, PageView::findOrFail( $page->id )->status );
    }


    public function testSinceImportsTranslationsWhoseCategoryOrItsParentWasSaved(): void
    {
        $db = $this->sinceCategories();
        $this->languageFilter();
        $category = ['extension' => 'com_content', 'access' => 1, 'modified_time' => '2019-01-01 00:00:00'];
        $db->table( 'categories' )->insert( [
            ['id' => 4, 'title' => 'Bereich', 'alias' => 'bereich', 'parent_id' => 1, 'level' => 1, 'lft' => 6] + $category,
            ['id' => 6, 'title' => 'Unterbereich', 'alias' => 'unterbereich', 'parent_id' => 4, 'level' => 2, 'lft' => 7] + $category,
        ] );
        $db->table( 'content' )->where( 'id', 2 )->update( ['catid' => 6, 'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'content' )->where( 'id', 1 )->update( ['catid' => 2, 'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $args = ['--domain' => 'example.com', '--language' => ['de-DE:de']];

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $status = fn() => [PageView::findOrFail( $page->id )->status, PageView::language( 'de' )->findOrFail( $page->id )->status];
        $this->assertSame( [1, 1], $status() );

        // the category of the translation was saved
        $db->table( 'categories' )->where( 'id', 6 )->update( ['access' => 2, 'modified_time' => '2020-02-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-01-01'] ) );
        $this->assertSame( [1, 0], $status() );

        $db->table( 'categories' )->where( 'id', 6 )->update( ['access' => 1, 'modified_time' => '2020-03-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-02-15'] ) );
        $this->assertSame( [1, 1], $status() );

        // only the parent of the category of the translation was saved, Joomla checks the parents of the category too
        $db->table( 'categories' )->where( 'id', 4 )->update( ['access' => 2, 'modified_time' => '2020-04-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-03-15'] ) );
        $this->assertSame( [1, 0], $status() );

        // without saved categories, the article and its translation are kept
        $db->table( 'content' )->where( 'id', 2 )->update( ['introtext' => '<p>Neu</p>'] );

        $this->assertSame( 0, Artisan::call( 'cms:joomla-import', $args + ['--since' => '2020-04-15'] ) );
        $this->assertStringNotContainsString( 'Neu', (string) json_encode( PageView::language( 'de' )->findOrFail( $page->id )->content ) );
    }


    /**
     * Adds the category "Area" with the subcategory "Sub" and the category "Other" with one article each, all unchanged since 2019.
     */
    protected function sinceCategories(): \Illuminate\Database\Connection
    {
        $this->accessColumns();
        Schema::connection( 'joomla' )->table( 'categories', fn( Blueprint $table ) => $table->string( 'modified_time' )->default( '0000-00-00 00:00:00' ) );
        $db = DB::connection( 'joomla' );
        $db->table( 'viewlevels' )->insert( [['id' => 1, 'title' => 'Public', 'rules' => '[1]'], ['id' => 2, 'title' => 'Registered', 'rules' => '[2]']] );
        $db->table( 'usergroups' )->insert( [['id' => 1, 'parent_id' => 0], ['id' => 2, 'parent_id' => 1]] );
        $category = ['extension' => 'com_content', 'access' => 1, 'modified_time' => '2019-01-01 00:00:00'];
        $db->table( 'categories' )->insert( [
            ['id' => 2, 'title' => 'Area', 'alias' => 'area', 'parent_id' => 1, 'level' => 1, 'lft' => 1] + $category,
            ['id' => 3, 'title' => 'Sub', 'alias' => 'sub', 'parent_id' => 2, 'level' => 2, 'lft' => 2] + $category,
            ['id' => 5, 'title' => 'Other', 'alias' => 'other', 'parent_id' => 1, 'level' => 1, 'lft' => 5] + $category,
        ] );
        $article = ['language' => 'en-GB', 'introtext' => '<p>x</p>', 'fulltext' => '', 'created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'];
        $db->table( 'content' )->insert( [
            ['id' => 10, 'title' => 'Inside', 'alias' => 'inside', 'catid' => 3] + $article,
            ['id' => 11, 'title' => 'Outside', 'alias' => 'outside', 'catid' => 5] + $article,
        ] );

        return $db;
    }


    /**
     * Adds an English menu item of the article and a German one of its translation, the articles are unchanged since 2019.
     */
    protected function translatedMenuItems(): void
    {
        Schema::connection( 'joomla' )->table( 'menu', function( Blueprint $table ) {
            $table->boolean( 'home' )->default( false );
            $table->string( 'language' )->default( '*' );
        } );
        $db = DB::connection( 'joomla' );
        $this->languageFilter();
        $db->table( 'content' )->update( ['created' => '2019-01-01 00:00:00', 'modified' => '2019-01-01 00:00:00'] );
        $db->table( 'menu' )->insert( [
            ['language' => 'en-GB', 'alias' => 'about-us'] + $this->menuItem( 101, 'About us', 1, 1, 1 ),
            ['language' => 'de-DE', 'menutype' => 'mainmenu-de', 'lft' => 201] + $this->menuItem( 201, 'Firma', 1, 1, 2 ),
        ] );
    }
}
