<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests\Statamic;

use Aimeos\Cms\Commands\StatamicImport;
use Aimeos\Cms\Jobs\IndexModels;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\Version;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;


/**
 * Command-level tests importing Statamic sites with the "cms:statamic-import" command.
 */
class ImportTest extends TestAbstract
{
    public function testCommandIsRegisteredByTheServiceProvider(): void
    {
        $this->assertInstanceOf( StatamicImport::class, Artisan::all()['cms:statamic-import'] ?? null );
    }


    public function testDryRunOnlyListsPublishedEntries(): void
    {
        $this->entries( [
            ['id' => 'published', 'slug' => 'published', 'order' => 1, 'data' => ['title' => 'Published entry']],
            ['id' => 'draft', 'slug' => 'draft', 'published' => false, 'data' => ['title' => 'Draft entry']],
            ['id' => 'untitled', 'order' => 2, 'data' => ['title' => 'No slug']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--dry-run' => true, '--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( "Found 2 Statamic entries.\n  [published] (pages) Published entry (/published)\n  [untitled] (pages) No slug (/no-slug)\n", $output );
        $this->assertStringContainsString( 'Dry run complete. No changes were made.', $output );
        $this->assertStringNotContainsString( 'Draft entry', $output );
        $this->assertSame( 0, PageView::count() );
    }


    public function testWarnsAboutMissingBlueprints(): void
    {
        $this->collections( ['pages' => ['routes' => '{slug}'], 'posts' => ['routes' => 'blog/{slug}']] );
        $this->blueprint( 'posts', 'post', ['image' => 'images'] );
        $this->entries( [
            ['id' => 'a', 'slug' => 'a', 'data' => ['title' => 'A']],
            ['id' => 'b', 'slug' => 'b', 'collection' => 'posts', 'data' => ['title' => 'B']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--dry-run' => true, '--collections' => 'pages,posts'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'The blueprints of the "pages" collection aren\'t stored in the database', $output );
        $this->assertStringNotContainsString( 'The blueprints of the "posts" collection', $output );
    }


    public function testNoEntries(): void
    {
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( 'No Statamic entries found.', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testInvalidLanguage(): void
    {
        $this->entries( [['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de']] ) );
        $this->assertStringContainsString( 'Invalid Statamic language: de', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testReimportUpdatesPages(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'order' => 1, 'data' => ['title' => 'About']],
            ['id' => 'contact', 'slug' => 'contact', 'order' => 2, 'data' => ['title' => 'Contact']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $page = PageView::where( 'name', 'About' )->firstOrFail();

        DB::connection( 'statamic' )->table( 'entries' )->where( 'id', 'about' )->update( ['data' => json_encode( ['title' => 'About us'] )] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Updated: About us (/about)', $output );
        $this->assertStringContainsString( 'Unchanged: Contact (/contact)', $output );
        $this->assertStringNotContainsString( 'Failed', $output );
        $this->assertSame( 2, PageView::where( 'tag', 'page' )->count() );
        $this->assertSame( 'About us', PageView::findOrFail( $page->id )->title );
        $this->assertStringContainsString( 'Import complete. 0 imported, 1 updated, 1 unchanged (2/2 entries processed).', $output );
    }


    public function testUsesUriAndImportsHomeEntryIntoRootPage(): void
    {
        $this->entries( [
            ['id' => 'home', 'slug' => 'home', 'uri' => '/', 'order' => 1, 'data' => ['title' => 'Welcome', 'body' => 'Home text']],
            ['id' => 'post', 'collection' => 'blog', 'slug' => 'first', 'uri' => '/blog/2024/first', 'order' => 2, 'data' => ['title' => 'First post']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'pages,blog'] ) );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();

        // the entry with the URI "/" is the root page and the URIs of the entries are their URLs
        $this->assertSame( 'Welcome', $root->name );
        $this->assertStringContainsString( 'Home text', (string) json_encode( $root->content ) );
        $this->assertSame( 1, PageView::count() - 1 );
        $this->assertSame( 'blog/2024/first', PageView::where( 'name', 'First post' )->firstOrFail()->path );
    }


    public function testExpectsRootUsesFirstTreeEntryAsHome(): void
    {
        $this->collections( ['pages' => ['structure' => ['root' => true, 'max_depth' => 3]]] );
        $this->tree( 'pages', [['entry' => 'start'], ['entry' => 'about']] );
        $this->entries( [
            ['id' => 'start', 'slug' => 'start', 'data' => ['title' => 'Start']],
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        $this->assertSame( 'Start', PageView::where( 'tag', 'root' )->firstOrFail()->name );
        $this->assertSame( 0, PageView::where( 'path', 'start' )->count() );
        $this->assertSame( 'about', PageView::where( 'name', 'About' )->firstOrFail()->path );
    }


    public function testHomeEntryRedirectsToNextTopLevelEntry(): void
    {
        $this->collections( ['pages' => ['structure' => ['root' => true, 'max_depth' => 3]]] );
        $this->tree( 'pages', [['entry' => 'start'], ['entry' => 'about', 'children' => [['entry' => 'team']]]] );
        $this->entries( [
            ['id' => 'start', 'slug' => 'start', 'data' => ['title' => 'Start', 'redirect' => '@child']],
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
            ['id' => 'team', 'slug' => 'team', 'uri' => '/about/team', 'data' => ['title' => 'Team']],
        ] );

        // the home page has no children and Statamic redirects "@child" to the next top level entry
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( ['/about', 1], [PageView::where( 'tag', 'root' )->firstOrFail()->to, PageView::where( 'tag', 'root' )->firstOrFail()->status] );
    }


    public function testHomeEntryDoesntOverwriteForeignRoot(): void
    {
        $this->entries( [['id' => 'home', 'uri' => '/', 'data' => ['title' => 'Welcome']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        PageVariant::where( 'page_id', PageView::where( 'tag', 'root' )->firstOrFail()->id )->update( ['editor' => 'someone'] );
        DB::connection( 'statamic' )->table( 'entries' )->update( ['data' => json_encode( ['title' => 'Changed'] )] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Skipped home page: Changed (/) (the root page wasn't imported or was changed since)", Artisan::output() );
        $this->assertSame( 'Welcome', PageView::where( 'tag', 'root' )->firstOrFail()->name );
    }


    public function testMountedCollectionsAreImportedBelowTheirMountEntry(): void
    {
        $this->collections( ['blog' => ['mount' => 'news'], 'pages' => []] );
        $this->entries( [
            ['id' => 'post', 'collection' => 'blog', 'slug' => 'post', 'uri' => '/news/post', 'data' => ['title' => 'Post']],
            ['id' => 'news', 'slug' => 'news', 'uri' => '/news', 'data' => ['title' => 'News']],
        ] );

        // the collection containing the mount entry is imported first
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'blog,pages'] ) );

        $news = PageView::where( 'name', 'News' )->firstOrFail();
        $post = PageView::where( 'name', 'Post' )->firstOrFail();

        $this->assertSame( $news->id, $post->parent_id );
        $this->assertSame( 'news/post', $post->path );
    }


    public function testRedirectEntries(): void
    {
        $this->tree( 'pages', [
            ['entry' => 'docs', 'children' => [['entry' => 'intro']]],
            ['entry' => 'external'], ['entry' => 'linked'], ['entry' => 'broken'], ['entry' => 'gone'], ['entry' => 'tag'],
            ['entry' => 'empty'], ['entry' => 'deleted'], ['entry' => 'elsewhere'],
        ] );
        $this->entries( [
            ['id' => 'docs', 'slug' => 'docs', 'data' => ['title' => 'Docs', 'redirect' => '@child']],
            ['id' => 'intro', 'slug' => 'intro', 'uri' => '/docs/intro', 'data' => ['title' => 'Intro']],
            ['id' => 'external', 'slug' => 'external', 'data' => ['title' => 'External', 'redirect' => ['url' => 'https://example.org/']]],
            ['id' => 'linked', 'slug' => 'linked', 'data' => ['title' => 'Linked', 'redirect' => 'entry::intro']],
            ['id' => 'broken', 'slug' => 'broken', 'data' => ['title' => 'Broken', 'redirect' => 'javascript:alert(1)']],
            ['id' => 'gone', 'slug' => 'gone', 'data' => ['title' => 'Gone', 'redirect' => ['url' => '404', 'status' => 301]]],
            ['id' => 'tag', 'slug' => 'tag', 'data' => ['title' => 'Tag', 'redirect' => 'term::tags::news']],
            ['id' => 'empty', 'slug' => 'empty', 'data' => ['title' => 'Empty', 'redirect' => '@child']],
            ['id' => 'deleted', 'slug' => 'deleted', 'data' => ['title' => 'Deleted', 'redirect' => 'entry::nothing']],
            ['id' => 'elsewhere', 'slug' => 'elsewhere', 'data' => ['title' => 'Elsewhere', 'redirect' => 'entry::other']],
            ['id' => 'other', 'collection' => 'news', 'slug' => 'other', 'data' => ['title' => 'Other']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $page = fn( string $path ) => PageView::where( 'path', $path )->firstOrFail();

        $this->assertSame( '/docs/intro', $page( 'docs' )->to );
        $this->assertSame( 'https://example.org/', $page( 'external' )->to );
        $this->assertSame( '/docs/intro', $page( 'linked' )->to );
        // the children of redirects are still imported below them
        $this->assertSame( $page( 'docs' )->id, $page( 'docs/intro' )->parent_id );
        // unresolvable redirects are imported as pages
        $this->assertSame( '', $page( 'broken' )->to );
        $this->assertStringContainsString( 'Imported as page: Broken (the redirect target "javascript:alert(1)" can\'t be resolved)', $output );
        $this->assertStringContainsString( 'Imported as page: Tag (taxonomy terms like "term::tags::news" aren\'t imported)', $output );
        // redirects to "404" show the not found page
        $this->assertSame( ['', 0], [$page( 'gone' )->to, $page( 'gone' )->status] );
        $this->assertStringContainsString( 'Disabled [gone] Gone because it redirects to "404"', $output );
        // redirects to targets which don't exist show the not found page, existing entries which aren't imported are kept
        $this->assertSame( ['', 0, '', 0], [$page( 'empty' )->to, $page( 'empty' )->status, $page( 'deleted' )->to, $page( 'deleted' )->status] );
        $this->assertStringContainsString( 'Disabled [empty] Empty because it redirects to its first child but has none', $output );
        $this->assertStringContainsString( 'Disabled [deleted] Deleted because its redirect target "entry::nothing" doesn\'t exist', $output );
        $this->assertSame( ['', 1], [$page( 'elsewhere' )->to, $page( 'elsewhere' )->status] );
        $this->assertStringContainsString( 'Imported as page: Elsewhere (the redirect target "entry::other" can\'t be resolved)', $output );

        // entries which aren't redirects any more replace their redirect pages
        DB::connection( 'statamic' )->table( 'entries' )->where( 'id', 'external' )->update( ['data' => json_encode( ['title' => 'External'] )] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( '', $page( 'external' )->to );
        $this->assertSame( 1, PageView::where( 'path', 'external' )->count() );
    }


    public function testChildRedirectsOfTranslationsAndEntriesOutsideTrees(): void
    {
        $this->tree( 'pages', [['entry' => 'docs', 'children' => [['entry' => 'intro']]], ['entry' => 'empty']] );
        $this->entries( [
            ['id' => 'docs', 'slug' => 'docs', 'data' => ['title' => 'Docs']],
            ['id' => 'docs-de', 'site' => 'de', 'origin_id' => 'docs', 'slug' => 'doku', 'data' => ['title' => 'Doku', 'redirect' => '@child']],
            ['id' => 'intro', 'slug' => 'intro', 'uri' => '/docs/intro', 'data' => ['title' => 'Intro']],
            ['id' => 'empty', 'slug' => 'empty', 'data' => ['title' => 'Empty']],
            ['id' => 'empty-de', 'site' => 'de', 'origin_id' => 'empty', 'slug' => 'leer', 'data' => ['title' => 'Leer', 'redirect' => '@child']],
            ['id' => 'loose', 'slug' => 'loose', 'data' => ['title' => 'Loose', 'redirect' => '@child']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de:de']] ) );
        $output = Artisan::output();
        $page = fn( string $path ) => PageView::where( 'path', $path )->firstOrFail();

        // translations use the child of their origin entry
        $this->assertSame( 1, PageView::language( 'de' )->findOrFail( $page( 'docs' )->id )->status );
        $this->assertSame( 0, PageView::language( 'de' )->findOrFail( $page( 'empty' )->id )->status );
        $this->assertStringContainsString( 'Disabled [empty-de] Leer because it redirects to its first child but has none', $output );
        // Statamic can't resolve "@child" redirects of entries which aren't in a tree
        $this->assertSame( 0, $page( 'loose' )->status );
        $this->assertStringContainsString( 'Disabled [loose] Loose because it redirects to its first child but isn\'t in a page tree', $output );
    }


    public function testTranslatedRedirectsLinksAndRoutes(): void
    {
        $this->collections( ['pages' => ['structure' => ['root' => false]], 'news' => ['routes' => ['en' => '/news/{slug}', 'de' => null]]] );
        $this->tree( 'pages', [['entry' => 'docs', 'children' => [['entry' => 'intro']]], ['entry' => 'about']] );
        $this->entries( [
            ['id' => 'docs', 'slug' => 'docs', 'uri' => '/docs', 'data' => ['title' => 'Docs', 'redirect' => '@child']],
            ['id' => 'docs-de', 'site' => 'de', 'origin_id' => 'docs', 'slug' => 'doku', 'uri' => '/doku', 'data' => ['title' => 'Doku']],
            ['id' => 'intro', 'slug' => 'intro', 'uri' => '/docs/intro', 'data' => ['title' => 'Intro']],
            ['id' => 'intro-de', 'site' => 'de', 'origin_id' => 'intro', 'slug' => 'einfuehrung', 'uri' => '/doku/einfuehrung',
                'data' => ['title' => 'Einführung', 'body' => '[Über uns](statamic://entry::about-de)']],
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About', 'body' => 'English']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'uri' => '/ueber-uns', 'data' => ['title' => 'Über uns', 'redirect' => 'entry::intro-de']],
            ['id' => 'post', 'collection' => 'news', 'slug' => 'post', 'uri' => '/news/post', 'data' => ['title' => 'Post', 'body' => 'English']],
            ['id' => 'post-de', 'collection' => 'news', 'site' => 'de', 'origin_id' => 'post', 'slug' => 'beitrag', 'data' => ['title' => 'Beitrag']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'pages,news', '--language' => ['de:de']] ) );
        $output = Artisan::output();
        $de = fn( string $path ) => PageView::language( 'de' )->findOrFail( PageView::where( 'path', $path )->firstOrFail()->id );

        // translations inherit the redirects of their origins and "@child" points to the localized child
        $this->assertSame( ['doku', '/doku/einfuehrung'], [$de( 'docs' )->path, $de( 'docs' )->to] );
        // translations can redirect on their own, also to localized entries
        $this->assertSame( ['ueber-uns', '/doku/einfuehrung'], [$de( 'about' )->path, $de( 'about' )->to] );
        // links to localized entries point to their translations
        $this->assertStringContainsString( '(</ueber-uns>)', (string) json_encode( $de( 'docs/intro' )->content, JSON_UNESCAPED_SLASHES ) );
        // translations of collections without route for their site have no URLs
        $this->assertSame( 0, PageView::language( 'de' )->where( 'path', 'beitrag' )->count() );
        $this->assertStringContainsString( 'Skipped the translations of the "news" collection in the "de" site because the collection has no route for the site', $output );
    }


    public function testLinkFieldsInSets(): void
    {
        $this->blueprint( 'pages', 'page', [
            'blocks' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => ['button' => ['fields' => [
                ['handle' => 'label', 'field' => ['type' => 'text']], ['handle' => 'link', 'field' => ['type' => 'link']],
            ]]]]]],
        ] );
        $this->tree( 'pages', [['entry' => 'docs', 'children' => [['entry' => 'intro']]], ['entry' => 'about']] );
        $this->entries( [
            ['id' => 'docs', 'slug' => 'docs', 'uri' => '/docs', 'blueprint' => 'page', 'data' => ['title' => 'Docs', 'blocks' => [
                ['type' => 'button', 'label' => 'Start', 'link' => '@child'],
                ['type' => 'button', 'label' => 'About', 'link' => 'entry::about'],
            ]]],
            ['id' => 'intro', 'slug' => 'intro', 'uri' => '/docs/intro', 'blueprint' => 'page', 'data' => ['title' => 'Intro']],
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'blueprint' => 'page', 'data' => ['title' => 'About']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        // "@child" links point to the first child of the entry, the labels are the link texts
        $content = (string) json_encode( PageView::where( 'path', 'docs' )->firstOrFail()->content, JSON_UNESCAPED_SLASHES );
        $this->assertStringContainsString( '[Start](</docs/intro>)', $content );
        $this->assertStringContainsString( '[About](</about>)', $content );
    }


    public function testLocalizedEntryLinks(): void
    {
        $this->blueprint( 'pages', 'page', [
            'body' => ['type' => 'markdown'],
            'blocks' => ['type' => 'replicator', 'sets' => ['main' => ['sets' => [
                'button' => ['fields' => [['handle' => 'label', 'field' => ['type' => 'text']], ['handle' => 'link', 'field' => ['type' => 'link']]]],
                'global' => ['fields' => [['handle' => 'label', 'field' => ['type' => 'text']], ['handle' => 'link', 'field' => ['type' => 'link', 'select_across_sites' => true]]]],
            ]]]],
        ] );
        $this->tree( 'pages', [['entry' => 'docs', 'children' => [['entry' => 'intro']]], ['entry' => 'about'], ['entry' => 'legacy']] );
        $this->entries( [
            ['id' => 'docs', 'slug' => 'docs', 'uri' => '/docs', 'blueprint' => 'page', 'data' => ['title' => 'Docs']],
            ['id' => 'docs-de', 'site' => 'de', 'origin_id' => 'docs', 'slug' => 'doku', 'uri' => '/doku', 'blueprint' => 'page', 'data' => ['title' => 'Doku',
                'body' => '[Über uns](statamic://entry::about)', 'blocks' => [
                    ['type' => 'button', 'label' => 'Mehr', 'link' => 'entry::about'],
                    ['type' => 'global', 'label' => 'English', 'link' => 'entry::about'],
                ]]],
            ['id' => 'intro', 'slug' => 'intro', 'uri' => '/docs/intro', 'blueprint' => 'page', 'data' => ['title' => 'Intro']],
            ['id' => 'intro-de', 'site' => 'de', 'origin_id' => 'intro', 'slug' => 'einfuehrung', 'uri' => '/doku/einfuehrung', 'blueprint' => 'page', 'data' => ['title' => 'Einführung']],
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'blueprint' => 'page', 'data' => ['title' => 'About', 'body' => '[Intro](statamic://entry::intro)']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'uri' => '/ueber-uns', 'blueprint' => 'page', 'data' => ['title' => 'Über uns', 'redirect' => 'entry::intro']],
            ['id' => 'legacy', 'slug' => 'legacy', 'uri' => '/legacy', 'blueprint' => 'page', 'data' => ['title' => 'Legacy', 'redirect' => 'entry::intro']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de:de']] ) );
        $page = fn( string $path ) => PageView::where( 'path', $path )->firstOrFail();
        $de = fn( string $path ) => PageView::language( 'de' )->findOrFail( $page( $path )->id );
        $content = (string) json_encode( $de( 'docs' )->content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        // links to origin entries in translations point to the translations of the entries like in Statamic
        $this->assertStringContainsString( '[Über uns](</ueber-uns>)', $content );
        $this->assertStringContainsString( '[Mehr](</ueber-uns>)', $content );
        $this->assertSame( '/doku/einfuehrung', $de( 'about' )->to );
        // fields selecting entries across sites link to the selected entries
        $this->assertStringContainsString( '[English](</about>)', $content );
        // links and redirects of the origin entries aren't localized
        $this->assertStringContainsString( '[Intro](</docs/intro>)', (string) json_encode( $page( 'about' )->content, JSON_UNESCAPED_SLASHES ) );
        $this->assertSame( '/docs/intro', $page( 'legacy' )->to );
    }


    public function testLocalizedLinksAcrossDomains(): void
    {
        $this->tree( 'pages', [['entry' => 'about'], ['entry' => 'legacy'], ['entry' => 'docs']] );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About', 'body' => '[Docs](statamic://entry::docs-de)']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'uri' => '/ueber-uns', 'data' => ['title' => 'Über uns']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about-de', 'slug' => 'a-propos', 'uri' => '/a-propos', 'data' => ['title' => 'À propos',
                'body' => '[Doku](statamic://entry::docs-de) [Legacy](statamic://entry::legacy)']],
            ['id' => 'legacy', 'slug' => 'legacy', 'uri' => '/legacy', 'data' => ['title' => 'Legacy']],
            ['id' => 'docs', 'slug' => 'docs', 'uri' => '/docs', 'data' => ['title' => 'Docs']],
            ['id' => 'docs-de', 'site' => 'de', 'origin_id' => 'docs', 'slug' => 'doku', 'uri' => '/doku', 'data' => ['title' => 'Doku']],
            ['id' => 'docs-fr', 'site' => 'fr', 'origin_id' => 'docs', 'slug' => 'documentation', 'uri' => '/documentation', 'data' => ['title' => 'Documentation']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de:de', 'fr:fr:https://example.fr/']] ) );
        $page = fn( string $path ) => PageView::where( 'path', $path )->firstOrFail();
        $content = (string) json_encode( PageView::language( 'fr' )->findOrFail( $page( 'about' )->id )->content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

        // links to localized entries of other sites point to the entries of the same origin in the current site like in Statamic
        $this->assertStringContainsString( '[Doku](<https://example.fr/documentation>)', $content );
        $this->assertStringContainsString( '[Docs](</docs>)', (string) json_encode( $page( 'about' )->content, JSON_UNESCAPED_SLASHES ) );
        // links in translations on an own domain to pages of the default site are absolute
        $this->assertStringContainsString( '[Legacy](<https://example.com/legacy>)', $content );
    }


    public function testTranslationsOfLocalizationsInOtherSites(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About', 'body' => 'English']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'uri' => '/ueber-uns', 'data' => ['title' => 'Über uns', 'body' => 'Deutsch']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about-de', 'slug' => 'a-propos', 'uri' => '/a-propos', 'data' => ['title' => 'À propos']],
            ['id' => 'docs', 'slug' => 'docs', 'uri' => '/docs', 'data' => ['title' => 'Docs']],
            ['id' => 'docs-fr', 'site' => 'fr', 'origin_id' => 'docs', 'slug' => 'documentation', 'uri' => '/documentation',
                'data' => ['title' => 'Documentation', 'body' => '[A](statamic://entry::about) [B](statamic://entry::about-de)']],
        ] );

        // only the French site is imported, its entries can originate from German entries
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['fr:fr']] ) );
        $output = Artisan::output();
        $fr = fn( string $path ) => PageView::language( 'fr' )->findOrFail( PageView::where( 'path', $path )->firstOrFail()->id );

        $this->assertStringNotContainsString( 'aren\'t localizations', $output );
        // translations inherit the values of their origins in the sites which aren't imported
        $this->assertSame( 'a-propos', $fr( 'about' )->path );
        $this->assertStringContainsString( 'Deutsch', (string) json_encode( $fr( 'about' )->content ) );
        // links to entries and to their localizations in other sites point to the French page
        $content = (string) json_encode( $fr( 'docs' )->content, JSON_UNESCAPED_SLASHES );
        $this->assertStringContainsString( '[A](</a-propos>)', $content );
        $this->assertStringContainsString( '[B](</a-propos>)', $content );
    }


    public function testDisablesProtectedEntries(): void
    {
        $this->collections( ['pages' => ['inject' => []], 'members' => ['inject' => ['protect' => 'logged_in']]] );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
            ['id' => 'secret', 'slug' => 'secret', 'data' => ['title' => 'Secret', 'protect' => 'password']],
            ['id' => 'news', 'collection' => 'members', 'slug' => 'news', 'data' => ['title' => 'News']],
            ['id' => 'open', 'collection' => 'members', 'slug' => 'open', 'data' => ['title' => 'Open', 'protect' => false]],
        ] );
        $args = ['--domain' => 'example.com', '--collections' => 'pages,members'];
        $status = fn() => PageView::where( 'tag', 'page' )->pluck( 'status', 'path' )->sortKeys()->all();

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Disabled [secret] Secret because it is protected by the "password" scheme', $output );
        $this->assertStringContainsString( 'Disabled [news] News because it is protected by the "logged_in" scheme', $output );
        $this->assertSame( ['about' => 1, 'news' => 0, 'open' => 1, 'secret' => 0], $status() );

        // entries without protection are enabled again
        DB::connection( 'statamic' )->table( 'entries' )->where( 'id', 'secret' )->update( ['data' => json_encode( ['title' => 'Secret'] )] );
        DB::connection( 'statamic' )->table( 'collections' )->where( 'handle', 'members' )->update( ['settings' => null] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $this->assertSame( ['about' => 1, 'news' => 1, 'open' => 1, 'secret' => 1], $status() );
    }


    public function testEntriesHiddenByDate(): void
    {
        $this->collections( [
            'events' => ['dated' => true, 'future_date_behavior' => 'private', 'past_date_behavior' => 'public'],
            'archive' => ['dated' => true, 'future_date_behavior' => 'public', 'past_date_behavior' => 'private'],
            'news' => ['dated' => true, 'future_date_behavior' => 'unlisted', 'past_date_behavior' => 'unlisted'],
            // the date of entries in collections which aren't dated is ignored
            'pages' => ['future_date_behavior' => 'private', 'past_date_behavior' => 'private'],
        ] );
        $future = gmdate( 'Y-m-d H:i:s', time() + 86400 );
        $entry = fn( string $id, string $collection, ?string $date ) => ['id' => $id, 'collection' => $collection, 'slug' => $id, 'date' => $date, 'data' => ['title' => ucfirst( $id )]];
        $this->entries( [
            $entry( 'concert', 'events', $future ),
            $entry( 'festival', 'events', '2020-01-01 10:00:00' ),
            $entry( 'undated', 'events', null ),
            $entry( 'old', 'archive', '2020-01-01 10:00:00' ),
            $entry( 'soon', 'archive', $future ),
            $entry( 'teaser', 'news', $future ),
            $entry( 'recap', 'news', '2020-01-01 10:00:00' ),
            $entry( 'plain', 'pages', $future ),
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'events,archive,news,pages'] ) );
        $output = Artisan::output();

        // "private" entries are disabled, "unlisted" ones are reachable by their URLs but hidden in the navigation
        $this->assertStringContainsString( 'Disabled [concert] Concert because it is scheduled for ' . substr( $future, 0, 16 ) . ' UTC, it\'s published then by "php artisan cms:publish"', $output );
        $this->assertStringContainsString( 'Disabled [old] Old because it expired on 2020-01-01 10:00 UTC', $output );
        $this->assertSame( 2, substr_count( $output, 'Disabled' ) );
        $this->assertSame( ['concert' => 0, 'festival' => 1, 'old' => 0, 'plain' => 1, 'recap' => 2, 'soon' => 1, 'teaser' => 2, 'undated' => 1],
            PageView::where( 'tag', 'page' )->pluck( 'status', 'path' )->sortKeys()->all() );

        // entries hidden until their date are published then and expiring entries are disabled then by a scheduled version,
        // entries hidden before and after their date don't change
        $scheduled = fn() => Version::where( 'published', false )->whereNotNull( 'publish_at' )->get()
            ->mapWithKeys( fn( $version ) => [$version->data->path => [$version->data->status, substr( (string) $version->publish_at, 0, 16 )]] )->sortKeys()->all();
        $this->assertEquals( ['concert' => [1, substr( $future, 0, 16 )], 'soon' => [0, substr( $future, 0, 16 )]], $scheduled() );

        // scheduled versions of entries which aren't hidden any more are removed
        DB::connection( 'statamic' )->table( 'entries' )->where( 'id', 'concert' )->update( ['date' => '2020-01-01 10:00:00'] );
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'events,archive,news,pages'] ) );
        $this->assertEquals( ['soon' => [0, substr( $future, 0, 16 )]], $scheduled() );
        $this->assertSame( 1, PageView::where( 'path', 'concert' )->firstOrFail()->status );
    }


    public function testSinceImportsEntriesOfChangedCollectionsAgain(): void
    {
        $old = '2019-01-01 00:00:00';
        $this->collections( ['pages' => [], 'members' => [], 'events' => ['dated' => true, 'future_date_behavior' => 'private']], $old );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'updated_at' => $old, 'data' => ['title' => 'About']],
            ['id' => 'news', 'collection' => 'members', 'slug' => 'news', 'updated_at' => $old, 'data' => ['title' => 'News']],
            ['id' => 'gig', 'collection' => 'events', 'slug' => 'gig', 'updated_at' => $old, 'data' => ['title' => 'Gig']],
        ] );
        $args = ['--domain' => 'example.com', '--collections' => 'pages,members,events'];

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $this->assertSame( 1, PageView::where( 'path', 'news' )->firstOrFail()->status );

        $db = DB::connection( 'statamic' );
        $db->table( 'collections' )->where( 'handle', 'members' )->update( ['settings' => json_encode( ['inject' => ['protect' => 'logged_in']] ), 'updated_at' => '2020-02-01 00:00:00'] );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args + ['--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        $fetched = $this->dataQueries( $db )->flatten()->sort()->values()->all();
        $db->disableQueryLog();

        // entries of changed collections and of collections hiding entries by date are imported again, the others are kept
        $this->assertSame( ['gig', 'news'], $fetched );
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Disabled [news] News because it is protected by the "logged_in" scheme', $output );
        $this->assertSame( 0, PageView::where( 'path', 'news' )->firstOrFail()->status );
    }


    public function testSinceKeepsUnchangedEntries(): void
    {
        $this->tree( 'pages', [['entry' => 'about', 'children' => [['entry' => 'team']]]] );
        $old = '2019-01-01 00:00:00';
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'order' => 1, 'updated_at' => $old, 'data' => ['title' => 'About']],
            ['id' => 'team', 'slug' => 'team', 'order' => 2, 'updated_at' => $old, 'data' => ['title' => 'Team']],
            ['id' => 'contact', 'slug' => 'contact', 'order' => 3, 'data' => ['title' => 'Contact']],
            ['id' => 'untitled', 'order' => 4, 'updated_at' => $old, 'data' => ['title' => 'No slug']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--since' => 'invalid'] ) );
        $this->assertStringContainsString( 'Invalid date for --since: invalid', Artisan::output() );
        $this->assertSame( 0, PageView::count() );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        $db = DB::connection( 'statamic' );
        $db->table( 'entries' )->whereIn( 'id', ['about', 'team', 'contact'] )->update( ['data' => json_encode( ['title' => 'Changed'] )] );
        $this->entries( [['id' => 'new', 'slug' => 'new', 'order' => 5, 'updated_at' => $old, 'data' => ['title' => 'New']]] );
        $db->enableQueryLog();

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();
        $fetched = $this->dataQueries( $db )->flatten()->sort()->values()->all();
        $db->disableQueryLog();

        // unchanged entries with slug aren't fetched and rendered again
        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Unchanged: Team (/team)', $output );
        // entries without modification time or slug and entries without previous page are imported
        $this->assertStringContainsString( 'Updated: Changed (/contact)', $output );
        $this->assertStringContainsString( 'Unchanged: No slug (/no-slug)', $output );
        $this->assertStringContainsString( 'Imported: New (/new)', $output );
        $this->assertSame( ['contact', 'new', 'untitled'], $fetched );
        $this->assertSame( PageView::where( 'name', 'About' )->firstOrFail()->id, PageView::where( 'name', 'Team' )->firstOrFail()->parent_id );

        // modified entries are imported again
        $db->table( 'entries' )->where( 'id', 'team' )->update( ['updated_at' => '2020-01-01 00:00:00'] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Unchanged: About (/about)', $output );
        $this->assertStringContainsString( 'Updated: Changed (/team)', $output );
    }


    public function testIndexFetchesTimesOnlyWithSince(): void
    {
        $this->entries( [['id' => 'one', 'slug' => 'one']] );

        $db = DB::connection( 'statamic' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--since' => '2020-01-01'] ) );
        $queries = Collection::make( $db->getQueryLog() )->pluck( 'query' );
        $db->disableQueryLog();

        $this->assertSame( 1, $queries->filter( fn( $query ) => str_starts_with( $query, 'select "id", "collection", "slug", "uri" from "entries"' ) )->count() );
        $this->assertSame( 1, $queries->filter( fn( $query ) => str_starts_with( $query, 'select "id", "collection", "slug", "uri", "updated_at" from "entries"' ) )->count() );
    }


    public function testDisablesVanishedPages(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
            ['id' => 'old', 'slug' => 'old', 'data' => ['title' => 'Old']],
            ['id' => 'away', 'slug' => 'away', 'data' => ['title' => 'Away', 'redirect' => 'https://example.org/']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        DB::connection( 'statamic' )->table( 'entries' )->whereIn( 'id', ['old', 'away'] )->update( ['published' => false] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--dry-run' => true] ) );
        $this->assertSame( 1, PageView::where( 'path', 'old' )->firstOrFail()->status );

        // pages and redirects of entries which aren't imported any more are disabled
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $this->assertStringContainsString( "Disabled 2 pages of previous imports whose Statamic entries aren't imported any more.", Artisan::output() );
        $this->assertSame( ['about' => 1, 'away' => 0, 'old' => 0], PageView::where( 'tag', 'page' )->pluck( 'status', 'path' )->sortKeys()->all() );
    }


    public function testImportsTranslations(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About', 'body' => 'English text', 'image' => 'team.jpg']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'data' => ['title' => 'Über uns', 'body' => 'Deutscher Text']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about-de', 'slug' => 'a-propos', 'data' => ['title' => 'À propos']],
            ['id' => 'about-it', 'site' => 'it', 'origin_id' => 'about', 'published' => false, 'slug' => 'chi-siamo', 'data' => ['title' => 'Chi siamo']],
            ['id' => 'only-de', 'site' => 'de', 'slug' => 'nur-deutsch', 'data' => ['title' => 'Nur Deutsch']],
        ] );
        $args = ['--domain' => 'example.com', '--file-base' => ['https://example.com/assets'], '--language' => ['de:de', 'fr:fr', 'it:it']];

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Translated: Über uns (/ueber-uns) [de]', $output );
        $this->assertStringContainsString( 'Translated: À propos (/a-propos) [fr]', $output );

        $page = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( ['de', 'en', 'fr'], PageVariant::where( 'page_id', $page->id )->orderBy( 'lang' )->pluck( 'lang' )->all() );
        // entries in other sites without origin aren't imported
        $this->assertSame( 0, PageView::where( 'name', 'Nur Deutsch' )->count() );
        $this->assertStringContainsString( 'Skipped 1 entries of the translated sites because they aren\'t localizations of entries in the "en" site: only-de', $output );

        $de = PageView::language( 'de' )->findOrFail( $page->id );
        $fr = PageView::language( 'fr' )->findOrFail( $page->id );

        $this->assertSame( ['Über uns', 'ueber-uns'], [$de->title, $de->path] );
        $this->assertStringContainsString( 'Deutscher Text', (string) json_encode( $de->content ) );
        // localized entries inherit the values of their origins
        $this->assertSame( 'a-propos', $fr->path );
        $this->assertStringContainsString( 'Deutscher Text', (string) json_encode( $fr->content ) );
        $this->assertSame( 1, File::count() );

        // translations changed in Pagible aren't overwritten
        PageVariant::where( 'path', 'ueber-uns' )->update( ['editor' => 'someone'] );
        DB::connection( 'statamic' )->table( 'entries' )->where( 'id', 'about-de' )->update( ['data' => json_encode( ['title' => 'Über'] )] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $this->assertSame( 'Über uns', PageView::language( 'de' )->findOrFail( $page->id )->title );
    }


    public function testIndexesImportedPagesTogether(): void
    {
        $this->entries( [
            ['id' => 'one', 'slug' => 'one', 'data' => ['title' => 'One']],
            ['id' => 'two', 'slug' => 'two', 'data' => ['title' => 'Two']],
            ['id' => 'three', 'slug' => 'three', 'data' => ['title' => 'Three']],
        ] );
        config( ['scout.driver' => 'cms', 'scout.queue' => true] );
        $queue = Queue::fake();

        try {
            $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        } finally {
            config( ['scout.driver' => 'collection', 'scout.queue' => false] );
        }

        $jobs = $queue->pushed( IndexModels::class, fn( IndexModels $job ) => $job->model === PageView::class );
        $variants = PageView::pluck( 'variant_id' )->map( strval( ... ) )->sort()->values()->all();
        $ids = $jobs->map( fn( IndexModels $job ) => $job->ids )->collapse()->sort()->values()->all();

        $this->assertCount( 1, $jobs );
        $this->assertSame( $variants, $ids );
    }


    public function testIndexesImportedPagesAfterEachBatch(): void
    {
        $this->entries( [
            ['id' => 'one', 'slug' => 'one', 'order' => 1, 'data' => ['title' => 'One']],
            ['id' => 'two', 'slug' => 'two', 'order' => 2, 'data' => ['title' => 'Two']],
        ] );
        Artisan::registerCommand( new class extends StatamicImport {
            protected int $batchSize = 1;
        } );
        config( ['scout.driver' => 'cms', 'scout.queue' => true] );
        $queue = Queue::fake();

        try {
            $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        } finally {
            config( ['scout.driver' => 'collection', 'scout.queue' => false] );
        }

        $jobs = $queue->pushed( IndexModels::class, fn( IndexModels $job ) => $job->model === PageView::class );

        // the pages of each committed batch are indexed before the next batch is imported
        $this->assertGreaterThan( 1, $jobs->count() );
        $this->assertContains( (string) PageView::where( 'name', 'One' )->firstOrFail()->variant_id, $jobs->firstOrFail()->ids );
        $this->assertNotContains( (string) PageView::where( 'name', 'Two' )->firstOrFail()->variant_id, $jobs->firstOrFail()->ids );
    }


    public function testImportFollowsTree(): void
    {
        $this->tree( 'pages', [
            ['entry' => 'about', 'children' => [['entry' => 'team'], ['children' => [['entry' => 'history']]]]],
            ['children' => [['entry' => 'contact']]],
            ['entry' => 'missing', 'children' => [['entry' => 'jobs']]],
            ['entry' => 'dup', 'children' => [['entry' => 'kid']]],
            ['entry' => 'about'],
        ] );
        $entry = fn( string $id, string $slug = '' ) => ['id' => $id, 'slug' => $slug ?: $id, 'data' => ['title' => ucfirst( $id )]];
        $this->entries( [
            $entry( 'about' ), $entry( 'team' ), $entry( 'contact' ), $entry( 'jobs' ), $entry( 'history' ),
            $entry( 'extra' ), $entry( 'other' ), $entry( 'dup', 'about' ), $entry( 'kid' ),
        ] );

        $db = DB::connection( 'statamic' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $output = Artisan::output();
        $queries = $this->dataQueries( $db );
        $db->disableQueryLog();

        // the data of the entries in the tree and of the remaining entries is fetched at once, children of failed entries again
        $this->assertSame( [['about', 'team', 'history', 'contact', 'jobs', 'dup', 'kid'], ['extra', 'other', 'kid']], $queries->all() );

        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $parent = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail()->parent_id;

        $this->assertSame( $root->id, $parent( 'About' ) );
        $this->assertSame( PageView::where( 'name', 'About' )->firstOrFail()->id, $parent( 'Team' ) );
        // children of tree nodes without entry are imported below the parent of the node
        $this->assertSame( PageView::where( 'name', 'About' )->firstOrFail()->id, $parent( 'History' ) );
        $this->assertSame( $root->id, $parent( 'Contact' ) );
        $this->assertSame( $root->id, $parent( 'Jobs' ) );
        // children of failed entries are imported below the root page
        $this->assertStringContainsString( 'Failed to import [dup] Dup: URL /about is used by another page of this import', $output );
        $this->assertSame( $root->id, $parent( 'Kid' ) );
        $this->assertSame( $root->id, $parent( 'Other' ) );
        $this->assertStringContainsString( '8 imported, 0 updated, 0 unchanged (8/9 entries processed)', $output );
    }


    public function testImportFollowsDeepTreeInBatches(): void
    {
        $this->tree( 'pages', [
            ['entry' => 'about', 'children' => [
                ['entry' => 'team', 'children' => [['entry' => 'lead', 'children' => [['entry' => 'alice'], ['entry' => 'bob']]]]],
                ['entry' => 'history'],
            ]],
            ['entry' => 'draft'],
        ] );
        $entry = fn( string $id, int $order, bool $published = true ) => ['id' => $id, 'slug' => $id, 'order' => $order, 'published' => $published, 'data' => ['title' => ucfirst( $id )]];
        $this->entries( [
            $entry( 'lead', 1 ), $entry( 'about', 2 ), $entry( 'extra', 3 ), $entry( 'team', 4 ), $entry( 'history', 5 ),
            $entry( 'alice', 6 ), $entry( 'bob', 7 ), $entry( 'draft', 8, false ),
        ] );
        $command = new class extends StatamicImport {
            protected const TREE_SIZE = 2;

            /** @var array<int|string, true> */
            public array $ids = [];
            /** @var array<int, array<int, int>> */
            public array $batches = [];
            /** @var array<int|string, string> */
            public array $pages = [];

            public function handle(): void
            {
                parent::handle();
                $this->ids = $this->entries;
                $this->pages = $this->createdPages->all();
            }

            protected function flatten( array $tree, bool $root = false ): \Generator
            {
                foreach( parent::flatten( $tree, $root ) as $batch )
                {
                    $this->batches[] = array_keys( $batch );
                    yield $batch;
                }
            }
        };
        Artisan::registerCommand( $command );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        // only the IDs of all published entries are kept in import order
        $this->assertSame( ['lead', 'about', 'extra', 'team', 'history', 'alice', 'bob'], array_keys( $command->ids ) );
        $this->assertSame( [[0, 1], [2, 3], [4, 5], [6]], $command->batches );
        $this->assertSame( ['about', 'alice', 'bob', 'extra', 'history', 'lead', 'team'], Collection::make( $command->pages )->keys()->sort()->values()->all() );

        $page = fn( string $name ) => PageView::where( 'name', $name )->firstOrFail();

        $this->assertSame( ['Team', 'History'], $page( 'About' )->children->pluck( 'name' )->all() );
        $this->assertSame( ['Alice', 'Bob'], $page( 'Lead' )->children->pluck( 'name' )->all() );
        $this->assertSame( $page( 'Team' )->id, $page( 'Lead' )->parent_id );
        $this->assertSame( PageView::where( 'tag', 'root' )->firstOrFail()->id, $page( 'Extra' )->parent_id );
        $this->assertSame( 0, PageView::where( 'name', 'Draft' )->count() );
        $this->assertStringContainsString( '7 imported, 0 updated, 0 unchanged (7/7 entries processed)', Artisan::output() );
    }


    public function testEntriesAreImportedInChunks(): void
    {
        $this->entries( array_map( fn( $num ) => ['id' => "entry-{$num}", 'slug' => "entry-{$num}", 'order' => $num, 'data' => ['title' => "Entry {$num}"]], range( 1, 150 ) ) );

        $db = DB::connection( 'statamic' );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $queries = $this->dataQueries( $db );
        $db->disableQueryLog();

        $this->assertSame( [100, 50], $queries->map( fn( $ids ) => count( $ids ) )->all() );
        $this->assertSame( 150, PageView::where( 'name', 'like', 'Entry %' )->count() );
    }


    public function testFetchesPagesAtOnce(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
            ['id' => 'team', 'slug' => 'team', 'data' => ['title' => 'Team']],
            ['id' => 'untitled', 'data' => ['title' => 'No slug']],
        ] );

        $db = DB::connection( config( 'cms.db', 'sqlite' ) );
        $db->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );
        $queries = Collection::make( $db->getQueryLog() )->pluck( 'query' );
        $db->disableQueryLog();

        // the pages using the URLs of the entries are fetched at once, entries without slug use the slug of their title
        $this->assertSame( 0, $queries->filter( fn( $query ) => preg_match( '/"path" in \(\?, \?\).* limit 1/', $query ) === 1 )->count() );
        // with and without leading slash
        $this->assertSame( 1, $queries->filter( fn( $query ) => preg_match( '/"path" in \((\?, ){5}\?\)/', $query ) === 1 )->count() );
        $this->assertSame( ['about', 'no-slug', 'team'], PageView::where( 'tag', 'page' )->orderBy( 'path' )->pluck( 'path' )->all() );
    }


    public function testSameSlugDoesntOverwritePages(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'order' => 1, 'data' => ['title' => 'About']],
            ['id' => 'news', 'collection' => 'blog', 'slug' => 'about', 'order' => 2, 'data' => ['title' => 'About news']],
            ['id' => 'untitled', 'order' => 3, 'data' => ['title' => '']],
            ['id' => 'symbols', 'order' => 4, 'data' => ['title' => '!!!']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'pages,blog'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Imported: About (/about)', $output );
        $this->assertStringContainsString( 'Imported: Untitled (/untitled)', $output );
        $this->assertStringContainsString( 'Failed to import [news] About news: URL /about is used by another page of this import', $output );
        // records without URL don't overwrite the root page
        $this->assertStringContainsString( 'Failed to import [symbols] !!!: URL / is used by another page of this import', $output );
        $this->assertSame( 0, PageView::where( 'name', 'About news' )->count() );
        $this->assertSame( 'Home', PageView::where( 'tag', 'root' )->firstOrFail()->name );
    }


    public function testImportsAssetsOfContainers(): void
    {
        $this->asset( 'assets', 'img/hero.png', ['title' => 'Hero image'] );
        $this->asset( 'assets', 'img/a.jpg', ['alt' => 'A image'] );
        $this->asset( 'media', 'img/a.jpg', ['title' => 'Media A'] );
        $body = [
            ['type' => 'image', 'attrs' => ['src' => 'asset::assets::img/a.jpg', 'alt' => '']],
            ['type' => 'image', 'attrs' => ['src' => 'asset::media::img/a.jpg', 'alt' => '']],
            ['type' => 'image', 'attrs' => ['src' => 'https://cdn.test/b.jpg', 'alt' => 'B']],
        ];
        $this->entries( [
            ['id' => 'home', 'slug' => 'home', 'data' => ['title' => 'Home', 'featured_image' => 'img/hero.png', 'body' => $body]],
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About', 'featured_image' => ['img/hero.png'], 'og_image' => 'img/a.jpg']],
        ] );
        $args = ['--domain' => 'example.com', '--file-base' => ['https://example.com/assets', 'media=https://cdn.example.com/media/']];

        $source = DB::connection( 'statamic' );
        $source->enableQueryLog();
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $meta = Collection::make( $source->getQueryLog() )->filter( fn( $query ) => str_contains( $query['query'], 'from "assets_meta"' ) );
        $source->disableQueryLog();

        // the assets are fetched at once and the containers use their own base URLs
        $this->assertCount( 1, $meta );
        $this->assertSame( [
            'https://cdn.example.com/media/img/a.jpg' => 'Media A',
            'https://cdn.test/b.jpg' => 'B',
            'https://example.com/assets/img/a.jpg' => 'A image',
            'https://example.com/assets/img/hero.png' => 'Hero image',
        ], File::orderBy( 'path' )->pluck( 'name', 'path' )->all() );
        $this->assertSame( 'image/png', File::where( 'name', 'Hero image' )->value( 'mime' ) );

        // the social media image is used in the meta data
        $about = PageView::where( 'name', 'About' )->firstOrFail();
        $this->assertSame( File::where( 'name', 'A image' )->value( 'id' ), $about->meta->{'social-media'}->data->file->id ?? null );

        // the files of the previous import are used again
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', $args ) );
        $this->assertSame( 4, File::count() );
    }


    public function testImportsSeoMetaData(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About', 'summary' => '<p>Short summary</p>', 'seo' => [
                'title' => 'About us | Example', 'description' => '@seo:summary', 'canonical_url' => 'https://example.org/about',
                'robots_indexing' => 'noindex', 'robots_following' => 'follow',
            ]]],
            ['id' => 'plain', 'slug' => 'plain', 'data' => ['title' => 'Plain', 'meta_title' => 'Plain title', 'meta_description' => 'Plain description']],
            ['id' => 'none', 'slug' => 'none', 'data' => ['title' => 'None', 'meta_description' => 'Ignored', 'seo' => false]],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        $about = PageView::where( 'path', 'about' )->firstOrFail();
        $plain = PageView::where( 'path', 'plain' )->firstOrFail();
        $none = PageView::where( 'path', 'none' )->firstOrFail();

        $this->assertSame( ['About', 'About us | Example'], [$about->name, $about->title] );
        $this->assertSame( 'Short summary', $about->meta->{'meta-tags'}->data->description ?? null );
        $this->assertSame( 'https://example.org/about', $about->meta->canonical->data->url ?? null );
        $this->assertStringContainsString( 'noindex', (string) json_encode( $about->meta->robots ?? null ) );
        $this->assertSame( ['Plain title', 'Plain description'], [$plain->title, $plain->meta->{'meta-tags'}->data->description ?? null] );
        $this->assertEmpty( (array) $none->meta );
    }


    public function testSiteAndLanguagesFromSites(): void
    {
        DB::connection( 'statamic' )->table( 'sites' )->delete();
        $this->site( 'main', 'de_DE', 'https://example.com/' );
        $this->site( 'fr', 'fr_FR', 'https://example.fr/' );
        $this->site( 'en', 'en_US', 'https://example.com/en/' );
        $this->entries( [
            ['id' => 'about', 'site' => 'main', 'slug' => 'ueber-uns', 'data' => ['title' => 'Über uns']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about', 'slug' => 'a-propos', 'data' => ['title' => 'À propos']],
            ['id' => 'about-en', 'site' => 'en', 'origin_id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
        ] );

        // the first site and its language are used by default, the language and domain of the translated sites are taken from the sites
        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['fr', 'en']] ) );

        $page = PageView::where( 'name', 'Über uns' )->firstOrFail();
        $this->assertSame( 'de', $page->lang );
        $this->assertSame( ['de' => 'example.com', 'en' => 'example.com', 'fr' => 'example.fr'], PageVariant::where( 'page_id', $page->id )->orderBy( 'lang' )->pluck( 'domain', 'lang' )->all() );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--site' => 'unknown'] ) );
        $this->assertStringContainsString( 'Statamic site "unknown" doesn\'t exist, available sites: main, fr, en', Artisan::output() );
    }


    public function testTranslationsWithSitePaths(): void
    {
        DB::connection( 'statamic' )->table( 'sites' )->delete();
        $this->site( 'en', 'en_US', '{{ config:app:url }}/' );
        $this->site( 'de', 'de_DE', '{{ config:app:url }}/de/' );
        $this->tree( 'pages', [['entry' => 'home'], ['entry' => 'team'], ['entry' => 'contact'], ['entry' => 'shop'], ['entry' => 'other']] );
        $this->entries( [
            ['id' => 'home', 'slug' => 'home', 'uri' => '/', 'data' => ['title' => 'Home']],
            ['id' => 'home-de', 'site' => 'de', 'origin_id' => 'home', 'slug' => 'home', 'uri' => '/', 'data' => ['title' => 'Start']],
            ['id' => 'team', 'slug' => 'team', 'uri' => '/team', 'data' => ['title' => 'Team']],
            ['id' => 'team-de', 'site' => 'de', 'origin_id' => 'team', 'slug' => 'team', 'uri' => '/team', 'data' => ['title' => 'Das Team']],
            ['id' => 'contact', 'slug' => 'contact', 'uri' => '/contact', 'data' => ['title' => 'Contact']],
            ['id' => 'contact-de', 'site' => 'de', 'origin_id' => 'contact', 'slug' => 'kontakt', 'uri' => '/kontakt', 'data' => ['title' => 'Kontakt']],
            // an English entry uses the URL of the German contact page, so the translation is skipped
            ['id' => 'shop', 'slug' => 'kontakt', 'uri' => '/de/kontakt', 'data' => ['title' => 'Shop']],
            ['id' => 'other', 'slug' => 'other', 'uri' => '/other', 'data' => ['title' => 'Other']],
            ['id' => 'other-de', 'site' => 'de', 'origin_id' => 'other', 'slug' => 'andere', 'uri' => '/andere', 'data' => ['title' => 'Andere',
                'body' => '[Team](statamic://entry::team) [Kontakt](statamic://entry::contact)']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de']] ) );
        $output = Artisan::output();
        $de = fn( string $path ) => PageView::language( 'de' )->find( PageView::where( 'path', $path )->firstOrFail()->id );

        // the URL paths of the sites prefix the URLs of the translations like in Statamic
        $this->assertSame( ['de', 'de/team'], [$de( '' )?->path, $de( 'team' )?->path] );
        $this->assertNull( $de( 'contact' ) );
        $this->assertStringContainsString( 'Skipped translation: Kontakt (/de/kontakt) [de]', $output );
        // links to skipped translations point to the origin page
        $content = (string) json_encode( $de( 'other' )?->content, JSON_UNESCAPED_SLASHES );
        $this->assertStringContainsString( '[Team](</de/team>)', $content );
        $this->assertStringContainsString( '[Kontakt](</contact>)', $content );
    }


    public function testTranslationsWithSitePathOption(): void
    {
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'À propos']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['fr:fr:/fr/']] ) );

        $page = PageView::where( 'path', 'about' )->firstOrFail();
        $this->assertSame( ['fr' => 'example.com/fr/about'], PageVariant::where( 'page_id', $page->id )->where( 'lang', 'fr' )->get()
            ->mapWithKeys( fn( $variant ) => [$variant->lang => $variant->domain . '/' . $variant->path] )->all() );
    }


    public function testTranslationsWithProtocolRelativeUrls(): void
    {
        $this->site( 'de', 'de_DE', '//de.example.com/' );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'Über uns']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'À propos']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de', 'fr:fr://fr.example.com/']] ) );

        $page = PageView::where( 'path', 'about' )->firstOrFail();
        $this->assertEquals( ['de' => 'de.example.com/about', 'fr' => 'fr.example.com/about'], PageVariant::where( 'page_id', $page->id )
            ->whereIn( 'lang', ['de', 'fr'] )->get()->mapWithKeys( fn( $variant ) => [$variant->lang => $variant->domain . '/' . $variant->path] )->all() );
    }


    public function testTranslationsWithAntlersHost(): void
    {
        $this->site( 'de', 'de_DE', 'https://{{ env:DE_HOST }}/' );
        $this->site( 'fr', 'fr_FR', '{{ config:app:url }}/fr/' );
        $this->site( 'es', 'es_ES', '{{ env:APP_URL }}/es/' );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About']],
            ['id' => 'about-de', 'site' => 'de', 'origin_id' => 'about', 'slug' => 'ueber-uns', 'uri' => '/ueber-uns', 'data' => ['title' => 'Über uns']],
            ['id' => 'about-es', 'site' => 'es', 'origin_id' => 'about', 'slug' => 'sobre', 'uri' => '/sobre', 'data' => ['title' => 'Sobre nosotros']],
            ['id' => 'about-fr', 'site' => 'fr', 'origin_id' => 'about', 'slug' => 'a-propos', 'uri' => '/a-propos', 'data' => ['title' => 'À propos']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['de', 'fr', 'es']] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'The host of the "de" site URL "https://{{ env:DE_HOST }}/" contains Antlers tags', $output );
        $this->assertStringContainsString( 'Use --language=de:de:https://', $output );
        $this->assertStringNotContainsString( 'The host of the "fr" site URL', $output );
        $this->assertStringNotContainsString( 'The host of the "es" site URL', $output );

        $page = PageView::where( 'path', 'about' )->firstOrFail();
        $this->assertEquals( ['de' => 'example.com/ueber-uns', 'es' => 'example.com/es/sobre', 'fr' => 'example.com/fr/a-propos'], PageVariant::where( 'page_id', $page->id )
            ->whereIn( 'lang', ['de', 'es', 'fr'] )->get()->mapWithKeys( fn( $variant ) => [$variant->lang => $variant->domain . '/' . $variant->path] )->all() );
    }


    public function testLanguageUrlWithoutScheme(): void
    {
        $this->entries( [['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--language' => ['fr:fr:example.com/fr/']] ) );
        $this->assertStringContainsString( 'Invalid language URL: example.com/fr/', Artisan::output() );
        $this->assertSame( 0, PageView::count() );
    }


    public function testSitePathPrefixes(): void
    {
        $cmd = new class extends \Aimeos\Cms\Commands\StatamicImport {
            public function test( string $url ): string
            {
                return $this->prefix( $url );
            }
        };

        $this->assertSame( 'de', $cmd->test( 'https://example.com/de/' ) );
        $this->assertSame( 'de', $cmd->test( 'https://example.com/de' ) );
        $this->assertSame( 'de', $cmd->test( 'https://{{ env:DE_HOST }}/de/' ) );
        $this->assertSame( 'de', $cmd->test( '//example.com/de/' ) );
        $this->assertSame( 'de', $cmd->test( '{{ config:app:url }}/de/' ) );
        $this->assertSame( 'de', $cmd->test( '{{ site:url }}de/' ) );
        $this->assertSame( 'de/at', $cmd->test( '/de/at/?x=1#top' ) );
        $this->assertSame( '', $cmd->test( 'https://example.com' ) );
        $this->assertSame( '', $cmd->test( '/' ) );
    }


    public function testSkipsEntriesWithoutRoute(): void
    {
        $this->collections( ['pages' => ['routes' => '{slug}'], 'authors' => ['routes' => null]] );
        $this->tree( 'pages', [['entry' => 'about']] );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'uri' => '/about', 'data' => ['title' => 'About']],
            ['id' => 'jane', 'collection' => 'authors', 'slug' => 'jane', 'data' => ['title' => 'Jane']],
            ['id' => 'john', 'collection' => 'authors', 'slug' => 'john', 'data' => ['title' => 'John']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'pages,authors'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'Skipped 2 entries of the "authors" collection because the collection has no route for the "en" site', $output );
        $this->assertSame( ['about'], PageView::where( 'tag', 'page' )->pluck( 'path' )->all() );
    }


    public function testWarnsAboutMissingSettingsAndTrees(): void
    {
        $this->collections( ['pages' => ['structure' => ['max_depth' => 3]]] );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About']],
            ['id' => 'post', 'collection' => 'blog', 'slug' => 'post', 'data' => ['title' => 'Post']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--collections' => 'pages,blog'] ) );
        $output = Artisan::output();

        $this->assertStringContainsString( 'The tree of the "pages" collection isn\'t stored in the database for the "en" site', $output );
        $this->assertStringContainsString( 'The settings of the "blog" collection aren\'t stored in the database', $output );
        $this->assertSame( 2, PageView::where( 'tag', 'page' )->count() );
    }


    public function testNestsEntriesByParentsWithoutTree(): void
    {
        $this->collections( ['pages' => ['structure' => ['max_depth' => 3]]] );
        $this->entries( [
            ['id' => 'about', 'slug' => 'about', 'order' => 1, 'data' => ['title' => 'About']],
            ['id' => 'team', 'slug' => 'team', 'uri' => '/about/team', 'order' => 2, 'data' => ['title' => 'Team', 'parent' => 'about']],
        ] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com'] ) );

        $this->assertStringContainsString( 'its entries are nested by their parents but not ordered like in the tree', Artisan::output() );
        $this->assertSame( PageView::where( 'path', 'about' )->firstOrFail()->id, PageView::where( 'path', 'about/team' )->firstOrFail()->parent_id );
    }


    public function testAssetsWithoutBaseUrlArentImported(): void
    {
        $this->asset( 'assets', 'img/a.jpg' );
        $this->asset( 'media', 'doc/file', [], 'application/pdf' );
        $this->entries( [['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About', 'image' => 'img/a.jpg', 'body' => [
            ['type' => 'set', 'attrs' => ['values' => ['type' => 'image', 'image' => 'asset::media::doc/file']]],
        ]]]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--file-base' => ['media=https://cdn.example.com/media']] ) );

        $this->assertStringContainsString( 'Assets of the "assets" container aren\'t imported because their URLs are unknown, use --file-base=assets=<URL> to import them', Artisan::output() );
        // the MIME type stored by Statamic is used for files without known extension
        $this->assertSame( ['https://cdn.example.com/media/doc/file' => 'application/pdf'], File::pluck( 'mime', 'path' )->all() );
    }


    public function testOnlyAssetContainerIsTheDefault(): void
    {
        \Illuminate\Support\Facades\Schema::connection( 'statamic' )->create( 'asset_containers', function( \Illuminate\Database\Schema\Blueprint $table ) {
            $table->string( 'handle' );
        } );
        DB::connection( 'statamic' )->table( 'asset_containers' )->insert( ['handle' => 'images'] );
        $this->asset( 'images', 'team.jpg', ['alt' => 'The team'] );
        $this->entries( [['id' => 'about', 'slug' => 'about', 'data' => ['title' => 'About', 'featured_image' => 'team.jpg']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--file-base' => ['images=https://example.com/images']] ) );

        // Statamic uses the only asset container for asset fields without container
        $file = File::firstOrFail();
        $this->assertSame( 'https://example.com/images/team.jpg', $file->path );
        $this->assertEquals( (object) ['en' => 'The team'], $file->description );
    }


    public function testBlueprintContainersOfAssetFields(): void
    {
        $this->blueprint( 'pages', 'page', ['hero_image' => 'images'] );
        $this->entries( [['id' => 'about', 'slug' => 'about', 'blueprint' => 'page', 'data' => ['title' => 'About', 'hero_image' => 'team.jpg']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--file-base' => ['images=https://example.com/images']] ) );

        $this->assertSame( ['https://example.com/images/team.jpg'], File::pluck( 'path' )->all() );
    }


    public function testBlueprintFeaturedImageAndAltText(): void
    {
        $this->asset( 'images', 'team.jpg', ['alt' => 'The team'] );
        $this->blueprint( 'pages', 'page', [
            'thumbnail' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1],
            'download' => ['type' => 'assets', 'container' => 'images', 'max_files' => 1],
        ] );
        $this->entries( [['id' => 'about', 'slug' => 'about', 'blueprint' => 'page', 'data' => ['title' => 'About', 'download' => 'doc.pdf', 'thumbnail' => 'team.jpg']]] );

        $this->assertSame( 0, Artisan::call( 'cms:statamic-import', ['--domain' => 'example.com', '--file-base' => ['images=https://example.com/images']] ) );

        $file = File::firstOrFail();
        $this->assertSame( 'https://example.com/images/team.jpg', $file->path );
        $this->assertEquals( (object) ['en' => 'The team'], $file->description );
        $this->assertStringContainsString( $file->id, (string) json_encode( PageView::where( 'name', 'About' )->firstOrFail()->content ) );
    }


    /**
     * Returns the IDs of the entries whose data is fetched in the logged queries.
     *
     * @return Collection<int, array<int, string>>
     */
    protected function dataQueries( \Illuminate\Database\Connection $db ): Collection
    {
        return Collection::make( $db->getQueryLog() )
            ->filter( fn( $query ) => preg_match( '/^select "id"(, "origin_id")?, "data" from "entries"/', $query['query'] ) === 1 )
            ->map( fn( $query ) => $query['bindings'] )
            ->values();
    }
}
