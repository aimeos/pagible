<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Database\Seeders\TestSeeder;
use Aimeos\Cms\Scout\CmsEngine;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Filter;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Resource;
use Aimeos\Nestedset\NestedSet;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;


class CmsEngineTest extends SearchTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $seeder = TestSeeder::class;
    protected $connectionsToTruncate = ['testing'];


    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }


    protected function setUp(): void
    {
        parent::setUp();
        $this->waitIndex();
    }


    protected function waitIndex()
    {
        $conn = DB::connection( config( 'cms.db' ) );

        if( $conn->getDriverName() === 'sqlsrv' )
        {
            $conn->statement( 'ALTER FULLTEXT INDEX ON cms_index START FULL POPULATION' );

            for( $i = 0; $i < 10; $i++ )
            {
                sleep( 1 );
                if( !$conn->scalar( "SELECT FULLTEXTCATALOGPROPERTY('cms_index_catalog', 'PopulateStatus')" ) ) {
                    break;
                }
            }
        }
    }


    public function testPages(): void
    {
        // full-text search draft
        $result = Page::search( 'Home' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        $this->assertTrue( $result->contains( fn( $p ) => $p->name === 'Home' ) );

        // full-text search published content
        $result = Page::search( 'Home' )->searchFields( 'content' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // empty search returns all
        $all = Page::search( '' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $all->count() );

        // no match
        $result = Page::search( 'xyznonexistent' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertEquals( 0, $result->count() );

        // multiple words
        $result = Page::search( 'Laravel CMS' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // special characters don't cause errors
        $result = Page::search( 'test+word "quoted"' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertInstanceOf( \Illuminate\Database\Eloquent\Collection::class, $result );

        // limit
        $result = Page::search( '' )->searchFields( 'draft' )->take( 2 )->get();
        $this->assertLessThanOrEqual( 2, $result->count() );

        // order by
        $result = Page::search( '' )->searchFields( 'draft' )->orderBy( NestedSet::LFT, 'asc' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 2, $result->count() );
        $lfts = $result->pluck( NestedSet::LFT )->toArray();
        $sorted = $lfts;
        sort( $sorted );
        $this->assertEquals( $sorted, $lfts );

        // query callback eager-loads relation
        $result = Page::search( 'Home' )->searchFields( 'draft' )
            ->query( fn( $q ) => $q->with( 'latest' ) )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        $this->assertTrue( $result->first()->relationLoaded( 'latest' ) );

        // filter by tag
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['tag' => 'root'] );
        $result = $search->get();
        $this->assertEquals( 1, $result->count() );
        $this->assertEquals( 'root', $result->first()->tag );

        // filter by domain
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['domain' => 'mydomain.tld'] );
        $this->assertEquals( 1, $search->get()->count() );

        // filter by status
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['status' => 0] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter by editor
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['editor' => 'seeder'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter by lang
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['lang' => 'en'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter by path
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['path' => 'blog'] );
        $this->assertEquals( 1, $search->get()->count() );

        // filter by cache
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['cache' => 5] );
        $this->assertEquals( 1, $search->get()->count() );

        // filter by parent_id
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['parent_id' => $root->id] );
        $result = $search->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        foreach( $result as $page ) {
            $this->assertEquals( $root->id, $page->parent_id );
        }

        // filter by IDs (whereIn)
        $pages = Page::take( 2 )->get();
        $ids = $pages->pluck( 'id' )->toArray();
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['id' => $ids] );
        $this->assertEquals( count( $ids ), $search->get()->count() );

        // filter published
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['publish' => 'PUBLISHED'] );
        foreach( $search->get() as $page ) {
            $this->assertTrue( (bool) ( $page->latest?->published ?? false ) );
        }

        // filter draft
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['publish' => 'DRAFT'] );
        $result = $search->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        foreach( $result as $page ) {
            $this->assertFalse( (bool) ( $page->latest?->published ?? true ) );
        }

        // filter scheduled
        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['publish' => 'SCHEDULED'] );
        $result = $search->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        foreach( $result as $page ) {
            $this->assertNotNull( $page->latest?->publish_at );
        }

        // paginate
        $result = Page::search( '' )->searchFields( 'draft' )->paginate( 2 );
        $this->assertLessThanOrEqual( 2, $result->count() );
        $this->assertGreaterThanOrEqual( 1, $result->total() );

        // simple paginate
        $result = Page::search( '' )->searchFields( 'draft' )->simplePaginate( 2 );
        $this->assertLessThanOrEqual( 2, $result->count() );

        // trashed only
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $page->delete();

        $search = Page::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::pages( $search, ['trashed' => 'only'] );
        $result = $search->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        $this->assertTrue( $result->first()->trashed() );
    }


    public function testElements(): void
    {
        // full-text draft
        $result = Element::search( 'footer' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        $this->assertTrue( $result->contains( fn( $e ) => $e->type === 'footer' ) );

        // full-text published
        $result = Element::search( 'footer' )->searchFields( 'content' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // empty search
        $result = Element::search( '' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // filter by type
        $search = Element::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::elements( $search, ['type' => 'footer'] );
        $result = $search->get();
        $this->assertEquals( 1, $result->count() );
        $this->assertEquals( 'footer', $result->first()->type );

        // filter by editor
        $search = Element::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::elements( $search, ['editor' => 'seeder'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter by lang
        $search = Element::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::elements( $search, ['lang' => 'en'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter draft
        $search = Element::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::elements( $search, ['publish' => 'DRAFT'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter scheduled
        $search = Element::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::elements( $search, ['publish' => 'SCHEDULED'] );
        $result = $search->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );
        foreach( $result as $element ) {
            $this->assertNotNull( $element->latest?->publish_at );
        }
    }


    public function testFiles(): void
    {
        // full-text draft
        $result = File::search( 'image' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // full-text published
        $result = File::search( 'image' )->searchFields( 'content' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // empty search
        $result = File::search( '' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 1, $result->count() );

        // filter by mime
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['mime' => 'image/tiff'] );
        $this->assertEquals( 1, $search->get()->count() );

        // filter by editor
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['editor' => 'seeder'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter by lang
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['lang' => 'en'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter published
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['publish' => 'PUBLISHED'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter draft
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['publish' => 'DRAFT'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // filter scheduled
        $search = File::search( '' )->searchFields( 'draft' )->take( 25 );
        Filter::files( $search, ['publish' => 'SCHEDULED'] );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );

        // sort by usage (byversions_count)
        $search = File::search( '' )->searchFields( 'draft' )
            ->query( fn( $q ) => $q->addSelect( ['byversions_count' => DB::table( 'cms_version_file' )
                ->selectRaw( 'count(*)' )
                ->whereColumn( 'file_id', 'cms_files.id' )] ) )
            ->orderBy( 'byversions_count', 'asc' )->take( 25 );
        $this->assertGreaterThanOrEqual( 1, $search->get()->count() );
    }


    public function testFileDraftSearchableAfterAdd(): void
    {
        // mirrors the add-file flow: the model is saved (and indexed) before its
        // version exists, so the draft (latest=true) index row must be written by
        // the re-index after the version is created and the relation is set.
        $versionId = ( new \Aimeos\Cms\Models\Version )->newUniqueId();

        $file = new File();
        $file->tenant_id = \Aimeos\Cms\Tenancy::value();
        $file->name = 'Zphraseunique draft media';
        $file->mime = 'image/png';
        $file->path = 'cms/test/zphraseunique.png';
        $file->latest_id = $versionId;
        $file->editor = 'test';
        $file->save();

        $version = $file->versions()->forceCreate( [
            'id' => $versionId,
            'lang' => 'en',
            'editor' => 'test',
            'data' => ['name' => $file->name, 'mime' => $file->mime, 'path' => $file->path],
        ] );

        $file->setRelation( 'latest', $version )->searchable();

        if( DB::connection( config( 'cms.db' ) )->getDriverName() === 'sqlsrv' ) {
            sleep( 5 );
        }

        $result = File::search( 'zphraseunique' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertTrue( $result->contains( 'id', $file->id ) );
    }


    public function testCjkSubstringSearch(): void
    {
        // CJK runs tokenize as a single FTS token, so interior substrings are unmatchable
        // by full-text search; the engine uses LIKE substring matching for CJK on all drivers.
        $versionId = ( new \Aimeos\Cms\Models\Version )->newUniqueId();

        $file = new File();
        $file->tenant_id = \Aimeos\Cms\Tenancy::value();
        $file->name = '产品搜索测试'; // "product search test"
        $file->mime = 'image/png';
        $file->path = 'cms/test/cjk.png';
        $file->latest_id = $versionId;
        $file->editor = 'test';
        $file->save();

        $version = $file->versions()->forceCreate( [
            'id' => $versionId,
            'lang' => 'zh',
            'editor' => 'test',
            'data' => ['name' => $file->name, 'mime' => $file->mime, 'path' => $file->path],
        ] );

        $file->setRelation( 'latest', $version )->searchable();

        if( DB::connection( config( 'cms.db' ) )->getDriverName() === 'sqlsrv' ) {
            sleep( 5 );
        }

        // interior substring matches
        $result = File::search( '搜索测' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertTrue( $result->contains( 'id', $file->id ) );

        // leading substring matches
        $result = File::search( '产品搜索' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertTrue( $result->contains( 'id', $file->id ) );

        // short 2-char substring matches
        $result = File::search( '搜索' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertTrue( $result->contains( 'id', $file->id ) );

        // non-contiguous characters must not match - substring matching stays selective
        $result = File::search( '产搜测' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertFalse( $result->contains( 'id', $file->id ) );

        // unrelated CJK term must not match
        $result = File::search( '新闻' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertFalse( $result->contains( 'id', $file->id ) );
    }


    public function testCjkDetectionCoversAllScripts(): void
    {
        // space-free scripts beyond the BMP CJK block (halfwidth katakana, Bopomofo) must
        // also route to substring matching, otherwise interior matches silently fail
        foreach( ['ｱｲｳｴｵ', 'ㄅㄆㄇㄈ'] as $i => $name )
        {
            $versionId = ( new \Aimeos\Cms\Models\Version )->newUniqueId();

            $file = new File();
            $file->tenant_id = \Aimeos\Cms\Tenancy::value();
            $file->name = $name;
            $file->mime = 'image/png';
            $file->path = "cms/test/script{$i}.png";
            $file->latest_id = $versionId;
            $file->editor = 'test';
            $file->save();

            $version = $file->versions()->forceCreate( [
                'id' => $versionId,
                'lang' => 'ja',
                'editor' => 'test',
                'data' => ['name' => $name, 'mime' => $file->mime, 'path' => $file->path],
            ] );

            $file->setRelation( 'latest', $version )->searchable();

            // interior substring (not a prefix) - only matchable via the LIKE path
            $interior = mb_substr( $name, 1, 2 );
            $result = File::search( $interior )->searchFields( 'draft' )->take( 25 )->get();
            $this->assertTrue( $result->contains( 'id', $file->id ), "interior '{$interior}' of '{$name}' should match" );
        }
    }


    public function testEngine(): void
    {
        $engine = new CmsEngine();
        $builder = Page::search( '' )->searchFields( 'draft' );
        $results = $engine->search( $builder );

        // getTotalCount
        $this->assertGreaterThanOrEqual( 1, $engine->getTotalCount( $results ) );

        // mapIds
        $ids = $engine->mapIds( $results );
        $this->assertGreaterThanOrEqual( 1, $ids->count() );
        $this->assertContainsOnlyString( $ids->toArray() );

        // lazyMap
        $lazy = $engine->lazyMap( $builder, $results, new Page() );
        $this->assertInstanceOf( \Illuminate\Support\LazyCollection::class, $lazy );
        $this->assertGreaterThanOrEqual( 1, $lazy->count() );

        // update empty / delete empty
        $engine->update( new \Illuminate\Database\Eloquent\Collection() );
        $engine->delete( new \Illuminate\Database\Eloquent\Collection() );

        // update re-indexes
        $count = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )->count();
        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $page->name = 'Updated home';
        $page->save();

        if( DB::connection( config( 'cms.db' ) )->getDriverName() === 'sqlsrv' ) {
            sleep( 5 );
        }

        $this->assertEquals( $count, DB::connection( config( 'cms.db' ) )->table( 'cms_index' )->count() );

        // delete removes from index
        $indexBefore = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )
            ->where( 'indexable_id', $page->id )->count();
        $this->assertGreaterThan( 0, $indexBefore );

        $page->unsearchable();

        $indexAfter = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )
            ->where( 'indexable_id', $page->id )->count();
        $this->assertEquals( 0, $indexAfter );

        // flush
        $engine->flush( new Page() );
        $count = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )
            ->where( 'indexable_type', Page::class )->count();
        $this->assertEquals( 0, $count );
    }


    public function testBulkReindexesSavedPages(): void
    {
        $user = new \App\Models\User( [
            'name' => 'editor', 'email' => 'editor@testbench',
            'password' => 'secret', 'cmsperms' => Permission::all(),
        ] );

        $page = Page::where( 'tag', 'root' )->firstOrFail();

        // bulk suppresses Scout's per-item sync and reindexes the saved pages once afterwards
        Resource::bulkPage( [$page->id], ['name' => 'ztqbulkterm'], $user );

        // the draft (latest=true) index row was refreshed with the new content
        $draft = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )
            ->where( 'indexable_id', $page->id )->where( 'latest', true )->value( 'content' );

        $this->waitIndex();

        $this->assertNotNull( $draft );
        $this->assertStringContainsString( 'ztqbulkterm', $draft );

        // and it is findable via full-text search on the draft
        $found = Page::search( 'ztqbulkterm' )->searchFields( 'draft' )->take( 25 )->get();
        $this->assertCount( 1, $found );
        $this->assertEquals( $page->id, $found->first()->id );
    }


    public function testBulkReindexesTrashedPages(): void
    {
        $user = new \App\Models\User( [
            'name' => 'editor', 'email' => 'editor@testbench',
            'password' => 'secret', 'cmsperms' => Permission::all(),
        ] );

        $page = Page::where( 'tag', 'root' )->firstOrFail();
        $page->delete();

        // the reindex must refresh a soft-deleted item's draft row despite the SoftDeletes scope
        Resource::bulkPage( [$page->id], ['name' => 'zttrashterm'], $user );

        $draft = DB::connection( config( 'cms.db' ) )->table( 'cms_index' )
            ->where( 'indexable_id', $page->id )->where( 'latest', true )->value( 'content' );

        $this->assertNotNull( $draft );
        $this->assertStringContainsString( 'zttrashterm', $draft );
    }
}
