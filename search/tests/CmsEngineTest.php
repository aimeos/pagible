<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Tests;

use Database\Seeders\CmsSeeder;
use Aimeos\Cms\Scout\CmsEngine;
use Aimeos\Cms\Models\Element;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Filter;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;


class CmsEngineTest extends SearchTestAbstract
{
    use CmsWithMigrations;
    use DatabaseTruncation;

    protected $connectionsToTruncate = ['testing'];


    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->seed( CmsSeeder::class );
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
        $result = Page::search( '' )->searchFields( 'draft' )->orderBy( '_lft', 'asc' )->take( 25 )->get();
        $this->assertGreaterThanOrEqual( 2, $result->count() );
        $lfts = $result->pluck( '_lft' )->toArray();
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
}
