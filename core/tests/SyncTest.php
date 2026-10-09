<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\Translation;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Hashes;
use Aimeos\Cms\Publication;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Sync;
use Aimeos\Cms\Utils;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\Version;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;


class SyncTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;

    /** @var array<int, array<int, string>> Texts sent to the translate callback */
    protected array $sent = [];


    protected function setUp(): void
    {
        parent::setUp();

        $this->sent = [];
        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ]);
    }


    public function testTranslateCreatesVariant()
    {
        $page = $this->page();
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();
        $source = Page::with( 'latest' )->findOrFail( $page->id );

        $this->assertEquals( 'de', $de->lang );
        $this->assertEquals( Sync::EDITOR, $de->latest->editor );
        $this->assertEquals( 0, $variant->status );
        $this->assertFalse( $variant->stale );
        $this->assertEquals( Hashes::page( (array) $source->latest->data, $source->latest->aux->content,
            $source->latest->aux->meta, $source->latest->aux->config ), $variant->hashes );

        // draft only, with matching element IDs
        $this->assertEquals( [], (array) $variant->content );
        $this->assertEquals( ['el1', 'el2'], array_column( (array) $de->latest->aux->content, 'id' ) );
        $this->assertEquals( '[de] Hello', $de->latest->aux->content[0]->data->text );
        $this->assertEquals( '[de] Title', $de->latest->data->title );
        $this->assertEquals( '[de] Name', $de->latest->data->name );
        $this->assertEquals( 'de-' . $page->path, $variant->path );
        $this->assertEquals( 'de-' . $page->path, $de->latest->data->path );

        // the source is unchanged
        $this->assertEquals( 'Hello', Page::with( 'latest' )->findOrFail( $page->id )->latest->aux->content[0]->data->text );
    }


    public function testTranslateWatchEvent()
    {
        $page = $this->page();
        $events = [];

        Event::listen( Translation::class, function( $event ) use ( &$events ) { $events[] = $event; } );

        Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        Resource::translatePage( $page->id, 'fr', $this->user );
        Resource::ignoreChanges( $page->id, 'fr', $this->user );

        $this->assertEquals( ['added', 'added', 'ignored'], array_map( fn( $e ) => $e->action, $events ) );
        $this->assertEquals( [true, false, false], array_map( fn( $e ) => $e->ai, $events ) );
        $this->assertEquals( ['de'], $events[0]->langs );
    }


    public function testTranslateMergeOrder()
    {
        $page = $this->page( [
            ['id' => 'e1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'One']],
            ['id' => 'e2', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Two']],
            ['id' => 'e3', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Three']],
            ['id' => 'e4', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Four']],
        ] );
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        // the translation adds x1 after e2 and x0 at the start, edits e4 and deletes e3
        $content = array_column( (array) $de->latest->aux->content, null, 'id' );
        $content['e4']->data->text = 'Vier';
        Resource::savePage( $page->id, ['content' => [
            ['id' => 'x0', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Nur DE start']],
            $content['e1'], $content['e2'],
            ['id' => 'x1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Nur DE']],
            $content['e4'],
        ]], $this->user, lang: 'de' );

        // the source edits e1, deletes e2, adds n1 and moves e4 to the start of another group
        Resource::savePage( $page->id, ['content' => [
            ['id' => 'e4', 'type' => 'text', 'group' => 'footer', 'data' => ['text' => 'Four']],
            ['id' => 'e1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'One changed']],
            ['id' => 'n1', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'New']],
            ['id' => 'e3', 'type' => 'text', 'group' => 'main', 'data' => ['text' => 'Three']],
        ]], $this->user );

        $this->sent = [];
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        $content = (array) $de->latest->aux->content;

        // x1 followed e2 which is gone, so it stays after e1
        $this->assertEquals( ['x0', 'e4', 'e1', 'x1', 'n1'], array_column( $content, 'id' ) );
        $this->assertEquals( ['main', 'footer', 'main', 'main', 'main'], array_column( $content, 'group' ) );
        $this->assertEquals( 'Vier', $content[1]->data->text );
        $this->assertEquals( '[de] One changed', $content[2]->data->text );
        $this->assertEquals( 'Nur DE', $content[3]->data->text );
        $this->assertEquals( '[de] New', $content[4]->data->text );

        // only changed items are sent for translation
        $this->assertEquals( [['One changed', 'New']], $this->sent );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'stale' ) );
    }


    public function testTranslateLocalizedReference()
    {
        $el = fn( $name ) => Resource::addElement( ['type' => 'text', 'name' => $name, 'data' => ['text' => $name]], $this->user )->id;
        [$sharedEn, $sharedDe, $sharedNew, $otherEn, $otherNew] = array_map( $el, ['shared-en', 'shared-de', 'shared-new', 'other-en', 'other-new'] );

        $page = $this->page( [
            ['id' => 'r1', 'type' => 'reference', 'refid' => $sharedEn],
            ['id' => 'r2', 'type' => 'reference', 'refid' => $otherEn],
        ] );
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $content = (array) $de->latest->aux->content;
        $content[0]->refid = $sharedDe;
        $this->savePage( $page->id, ['content' => $content], 'de' );

        $this->savePage( $page->id, ['content' => [
            ['id' => 'r1', 'type' => 'reference', 'refid' => $sharedNew],
            ['id' => 'r2', 'type' => 'reference', 'refid' => $otherNew],
        ]] );

        $this->sent = [];
        $content = (array) Resource::translatePage( $page->id, 'de', $this->user, $this->translator() )->latest->aux->content;

        $this->assertEquals( $sharedDe, $content[0]->refid );
        $this->assertEquals( $otherNew, $content[1]->refid );
        $this->assertEquals( [], $this->sent );
    }


    public function testTranslateNotLinked()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        $this->savePage( $page->id, ['content' => [
            ['id' => 'imp1', 'type' => 'text', 'data' => ['text' => 'Importiert']],
        ]], 'de' );

        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['hashes' => '{}'] );

        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( ['el1', 'el2'], array_column( (array) $de->latest->aux->content, 'id' ) );
        $this->assertNotEmpty( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->hashes );
    }


    public function testTranslateUntranslatedCopy()
    {
        $page = $this->page();
        $de = Resource::translatePage( $page->id, 'de', $this->user );
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->assertTrue( $variant->stale );
        $this->assertSame( '', $variant->hashes['el:el1'] );
        $this->assertSame( '', $variant->hashes['page:title'] );
        $this->assertNotSame( '', $variant->hashes['page:order'] );
        $this->assertNotSame( '', $variant->hashes['page:type'] );
        $this->assertEquals( 'Hello', $de->latest->aux->content[0]->data->text );
        $this->assertEquals( 'editor@testbench', $de->latest->editor );
        $this->assertEquals( $page->path . '-de', $variant->path );

        // the next translation with AI translates the copied texts
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( '[de] Hello', $de->latest->aux->content[0]->data->text );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'stale' ) );
        $this->assertEquals( $page->path . '-de', PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'path' ) );
    }


    public function testTranslateSkipsDataFields()
    {
        $page = $this->page( [
            ['id' => 'p1', 'type' => 'pricing', 'data' => ['title' => 'Plans', 'items' => [
                ['id' => 'i1', 'name' => 'Basic', 'features' => '* Fast', 'prices' => [
                    ['id' => 'pr1', 'kind' => 'subscription', 'currency' => 'EUR', 'reference' => 'price_123',
                        'label' => 'Monthly', 'unit' => 'month'],
                ]],
            ]]],
            ['id' => 'c1', 'type' => 'code', 'data' => ['language' => 'php', 'text' => 'echo "Hello";']],
            ['id' => 'h1', 'type' => 'html', 'data' => ['text' => '<p>Raw</p>']],
            ['id' => 't1', 'type' => 'table', 'data' => ['title' => 'Table', 'table' => [['Head', ''], ['Cell', '42']]]],
        ] );

        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        $content = (array) $de->latest->aux->content;
        $item = $content[0]->data->items[0];

        $this->assertEquals( '[de] Basic', $item->name );
        $this->assertEquals( '[de] * Fast', $item->features );
        $this->assertEquals( '[de] Monthly', $item->prices[0]->label );
        $this->assertEquals( 'EUR', $item->prices[0]->currency );
        $this->assertEquals( 'price_123', $item->prices[0]->reference );
        $this->assertEquals( 'month', $item->prices[0]->unit );
        $this->assertEquals( 'echo "Hello";', $content[1]->data->text );
        $this->assertEquals( '<p>Raw</p>', $content[2]->data->text );
        $this->assertEquals( [['[de] Head', ''], ['[de] Cell', '[de] 42']], $content[3]->data->table );

        $this->assertNotContains( 'echo "Hello";', $this->sent[0] );
        $this->assertNotContains( '<p>Raw</p>', $this->sent[0] );
    }


    public function testTranslateRewritesLinks()
    {
        Route::get( '{path?}', fn() => '' )->where( 'path', '.*' )->name( 'cms.page' );
        Route::getRoutes()->refreshNameLookups();

        $about = $this->page();
        $other = $this->page();
        $de = Resource::translatePage( $about->id, 'de', $this->user, $this->translator() );
        $dePath = PageVariant::where( 'page_id', $about->id )->where( 'lang', 'de' )->value( 'path' );

        $page = $this->page( [
            ['id' => 'h1', 'type' => 'hero', 'data' => ['title' => 'Hero', 'buttons' => [
                ['label' => 'About', 'url' => '/' . $about->path . '?a=1#team'],
                ['label' => 'Other', 'url' => '/' . $other->path],
                ['label' => 'Absolute', 'url' => url( $about->path )],
                ['label' => 'External', 'url' => 'https://example.org/' . $about->path],
            ]]],
            ['id' => 't1', 'type' => 'text', 'data' => ['text' => "[About](/{$about->path}) and [Other](/{$other->path})\n\n[ref]: /{$about->path}"]],
        ], ['to' => '/' . $about->path] );

        $result = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        $content = (array) $result->latest->aux->content;
        $buttons = $content[0]->data->buttons;

        $this->assertNotEquals( $about->path, $dePath );
        $this->assertEquals( '/' . $dePath . '?a=1#team', $buttons[0]->url );
        $this->assertEquals( '/' . $other->path, $buttons[1]->url );
        $this->assertEquals( url( $dePath ), $buttons[2]->url );
        $this->assertEquals( 'https://example.org/' . $about->path, $buttons[3]->url );
        $this->assertEquals( "[de] [About](/{$dePath}) and [Other](/{$other->path})\n\n[ref]: /{$dePath}", $content[1]->data->text );
        $this->assertEquals( '/' . $dePath, $result->latest->data->to );
    }


    public function testTranslateMetaAndConfig()
    {
        $page = $this->page( null, [
            'meta' => [
                'meta-tags' => ['type' => 'meta-tags', 'data' => ['description' => 'About us', 'keywords' => 'cms'], 'files' => []],
                'canonical' => ['type' => 'canonical', 'data' => ['url' => 'https://example.com/about'], 'files' => []],
            ],
            'config' => ['website-title' => ['type' => 'website-title', 'data' => ['title' => 'My site'], 'files' => []]],
            'theme' => 'cms',
        ] );

        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( '[de] About us', $de->latest->aux->meta->{'meta-tags'}->data->description );
        $this->assertObjectNotHasProperty( 'canonical', $de->latest->aux->meta );
        $this->assertEquals( 'My site', $de->latest->aux->config->{'website-title'}->data->title );

        // the translation uses another theme and keeps it with "Ignore changes"
        $this->savePage( $page->id, ['theme' => 'other'], 'de' );
        $this->savePage( $page->id, ['theme' => 'new', 'config' => [
            'website-title' => ['type' => 'website-title', 'data' => ['title' => 'New title'], 'files' => []],
        ]] );
        Publication::publish( Page::class, [$page->id], $this->user );

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'stale' ) );

        $de = Resource::ignoreChanges( $page->id, 'de', $this->user );
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->assertFalse( $variant->stale );
        $this->assertFalse( $de->stale );
        $this->assertEquals( 'other', Page::language( 'de' )->with( 'latest' )->findOrFail( $page->id )->latest->data->theme );

        $this->savePage( $page->id, ['name' => 'Renamed'] );
        $this->sent = [];
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( 'other', $de->latest->data->theme );
        $this->assertEquals( '[de] Renamed', $de->latest->data->name );
        $this->assertEquals( [['Renamed']], $this->sent );
    }


    public function testTranslateKeepsPathAndDomain()
    {
        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        $this->savePage( $page->id, ['path' => 'eigener-pfad'], 'de' );

        $this->savePage( $page->id, ['title' => 'New title', 'path' => 'new-path'] );
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( '[de] New title', $de->latest->data->title );
        $this->assertEquals( 'eigener-pfad', $de->latest->data->path );
    }


    public function testTranslatePathPrefixAndCollision()
    {
        $parent = $this->page();
        $child = Resource::addPage( ['lang' => 'en', 'name' => 'Child', 'title' => 'Child', 'path' => 'child'],
            $this->user, parent: $parent->id );

        Resource::translatePage( $parent->id, 'de', $this->user, $this->translator() );
        $this->savePage( $parent->id, ['path' => 'eltern'], 'de' );
        Publication::publish( Page::class, [$parent->id], $this->user, lang: 'de' );

        PageVariant::forceCreate( ['page_id' => $this->root()->id, 'lang' => 'de', 'path' => 'eltern/de-child', 'domain' => '', 'tenant_id' => 'test'] );

        Resource::translatePage( $child->id, 'de', $this->user, $this->translator() );

        $this->assertEquals( 'eltern/de-child-de', PageVariant::where( 'page_id', $child->id )->where( 'lang', 'de' )->value( 'path' ) );
    }


    public function testTranslateScheduledVariant()
    {
        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        Publication::publish( Page::class, [$page->id], $this->user, now()->addDay()->toDateTimeString(), lang: 'de' );

        $scheduled = Page::language( 'de' )->findOrFail( $page->id )->latest_id;

        $this->savePage( $page->id, ['title' => 'Changed'] );
        $de = Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );

        $this->assertNotEquals( $scheduled, $de->latest_id );
        $this->assertNotNull( Version::findOrFail( $scheduled )->publish_at );
        $this->assertEquals( '[de] Changed', $de->latest->data->title );
    }


    public function testTranslateMergesEditorDraft()
    {
        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
        $this->savePage( $page->id, ['title' => 'Changed'] );

        // an editor saves while the translation runs
        $translate = function( array $texts, string $to ) use ( $page ) {
            $this->savePage( $page->id, ['name' => 'Von Hand'], 'de' );
            return array_map( fn( $text ) => "[$to] $text", $texts );
        };

        $de = Resource::translatePage( $page->id, 'de', $this->user, $translate );

        $this->assertEquals( '[de] Changed', $de->latest->data->title );
        $this->assertEquals( 'Von Hand', $de->latest->data->name );
    }


    public function testTranslateErrors()
    {
        $page = $this->page();

        $this->expectException( Exception::class );
        Resource::translatePage( $page->id, 'en', $this->user, $this->translator() );
    }


    public function testTranslateTrashedVariant()
    {
        $page = $this->page();
        Resource::addVariant( $page->id, 'de', $this->user );
        Resource::dropVariant( $page->id, 'de', $this->user );

        $this->expectException( Exception::class );
        Resource::translatePage( $page->id, 'de', $this->user, $this->translator() );
    }


    public function testSavePageRestore()
    {
        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user );
        Resource::ignoreChanges( $page->id, 'de', $this->user );

        $de = Resource::savePage( $page->id, ['title' => 'Alt'], $this->user, lang: 'de' );
        $this->assertFalse( (bool) $de->stale );

        $de = Resource::savePage( $page->id, ['title' => 'Restored'], $this->user, lang: 'de', restore: true );
        $variant = PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail();

        $this->assertTrue( (bool) $de->stale );
        $this->assertTrue( $variant->stale );
        $this->assertEquals( 'Restored', $de->latest->data->title );
    }


    public function testSavePageRestoreSource()
    {
        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user );
        Resource::ignoreChanges( $page->id, 'de', $this->user );

        $en = Resource::savePage( $page->id, ['title' => 'Restored'], $this->user, restore: true );

        $this->assertFalse( (bool) $en->stale );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->firstOrFail()->stale );
    }


    public function testIgnoreChangesSource()
    {
        $this->expectException( Exception::class );
        Resource::ignoreChanges( $this->page()->id, 'en', $this->user );
    }


    protected function page( ?array $content = null, array $input = [] ) : Page
    {
        return Resource::addPage( $input + [
            'lang' => 'en', 'name' => 'Name', 'title' => 'Title', 'path' => 'var-' . substr( md5( uniqid() ), 0, 8 ),
            'content' => $content ?? [
                ['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']],
                ['id' => 'el2', 'type' => 'heading', 'data' => ['title' => 'Heading', 'level' => 2]],
            ],
        ], $this->user, parent: $this->root()->id );
    }


    protected function root() : Page
    {
        return Page::where( 'tag', 'root' )->firstOrFail();
    }


    protected function savePage( string $id, array $input, ?string $lang = null ) : Page
    {
        return Resource::savePage( $id, $input, $this->user, lang: $lang );
    }


    protected function translator() : \Closure
    {
        return function( array $texts, string $to ) {
            $this->sent[] = $texts;
            return array_map( fn( $text ) => "[$to] $text", $texts );
        };
    }
}
