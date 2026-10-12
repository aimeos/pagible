<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Jobs\IndexModels;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Query\PageBuilder;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Scout;
use Database\Seeders\TestSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Database\Query\Grammars\SqlServerGrammar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;


class PageAccessTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;
    private PageInvalidationSpy $invalidator;


    protected function setUp(): void
    {
        parent::setUp();
        Access::using( fn() => ['alpha', 'beta', 'denied', 'gamma', 'member'] );
        $this->invalidator = new PageInvalidationSpy();
        Event::listen( PageInvalidated::class, [$this->invalidator, 'handle'] );
    }


    public function testAccessRowsDoNotExpectAutoIncrementingIdentifiers(): void
    {
        $this->assertFalse( ( new PageAccess() )->getIncrementing() );
    }


    public function testAccessProjectionCompilesForSqlServer(): void
    {
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );
        $grammar = $connection->getQueryGrammar();

        try {
            $connection->setQueryGrammar( new SqlServerGrammar( $connection ) );
            $sql = strtolower( PageView::query()->select( 'id' )->withAccess( null )->toSql() );
        } finally {
            $connection->setQueryGrammar( $grammar );
        }

        $this->assertStringContainsString( 'select top 1 1', $sql );
        $this->assertStringNotContainsString( 'select exists', $sql );
    }


    public function testEmptyAccessValueListsRequireAuthentication(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, PageAccess::set( [$page->id], [] ) );
        $this->assertSame( '', PageAccess::where( 'page_id', $page->id )->firstOrFail()->value );
        $this->assertSame( '', DB::connection( config( 'cms.db', 'sqlite' ) )
            ->table( 'cms_page_access' )->where( 'page_id', $page->id )->value( 'value' ) );
    }


    public function testDatabaseRejectsAccessOwnedByAnotherTenant(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();

        $this->expectException( QueryException::class );

        DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_page_access' )->insert( [
            'page_id' => $page->id,
            'tenant_id' => 'other',
            'value' => '',
            'editor' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ] );
    }


    public function testRestrictionRequiresAvailableAccessConfiguration(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        Access::using( null );

        try {
            PageAccess::set( [$page->id], [] );
            $this->fail( 'Unconfigured access restrictions must be rejected.' );
        } catch( Exception $e ) {
            $this->assertSame( 'Frontend access restrictions are not available.', $e->getMessage() );
        }

        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
        $this->assertSame( [], $this->invalidator->events );
    }


    public function testRestrictionRequiresPageAccessPermissionForUsers(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $user = new \App\Models\User( ['cmsperms' => ['page:publish', 'access:view']] );

        try {
            PageAccess::set( [$page->id], [], $user );
            $this->fail( 'Expected page access changes to require page:access' );
        } catch( Exception $e ) {
            $this->assertSame( 'Insufficient permissions', $e->getMessage() );
        }

        $user = new \App\Models\User( ['cmsperms' => ['page:access']] );

        $this->assertSame( 1, PageAccess::set( [$page->id], [], $user ) );
    }


    public function testRestrictionsCanBeReleasedWhenAccessIsUnavailable(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], [] );
        Access::using( null );

        $this->assertSame( 1, PageAccess::set( [$page->id], null ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testRejectsMoreThanTwoHundredFiftyAccessValues(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $values = array_map( fn( $value ) => 'value-' . $value, range( 1, 251 ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'A page may not require more than 250 access values.' );

        PageAccess::set( [$page->id], $values );
    }


    public function testRestrictsAndReleasesPageDatabaseFirst(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, PageAccess::set( [$page->id], [' beta ', 'alpha', 'alpha'] ) );

        $this->assertSame(
            ['alpha', 'beta'],
            PageAccess::where( 'page_id', $page->id )->orderBy( 'value' )->pluck( 'value' )->all(),
        );
        $this->assertSame( ['test'], PageAccess::where( 'page_id', $page->id )->pluck( 'tenant_id' )->unique()->all() );
        $this->assertInvalidated( ['hidden'] );

        $this->invalidator->reset();
        $this->assertSame( 1, PageAccess::set( [$page->id], null ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
        $this->assertInvalidated( ['hidden'] );
    }


    public function testRefreshesExternalPageIndex(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $search = $this->searchEngine();

        PageAccess::set( [$page->id], ['alpha'] );
        $this->invalidator->reset();
        PageAccess::set( [$page->id], ['beta'] );
        $this->assertInvalidated( ['hidden'] );

        $this->invalidator->reset();
        PageAccess::set( [$page->id], null );

        $this->assertInvalidated( ['hidden'] );
        $this->assertSame( [[$page->id], [$page->id]], $search->updates );
    }


    public function testExternalPageIndexRefreshIsQueuedAfterCommit(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $this->searchEngine();
        config( ['scout.queue' => true] );
        Queue::fake();

        PageAccess::set( [$page->id], [] );

        Queue::assertPushed( IndexModels::class, fn( IndexModels $job ) =>
            $job->model === PageView::class && $job->ids === [$page->id] && $job->tenant === 'test'
        );
    }


    public function testCmsPageIndexRefreshIsNotQueued(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        config( ['scout.driver' => 'cms', 'scout.queue' => true] );
        Queue::fake();

        PageAccess::set( [$page->id], [] );

        Queue::assertNotPushed( IndexModels::class );
    }


    public function testExternalPageIndexJobsHaveBoundedPayloads(): void
    {
        config( ['scout.queue' => true] );
        $ids = array_map( strval(...), range( 0, 100 ) );

        $this->searchEngine();
        Queue::fake();
        Scout::reindex( PageView::class, $ids );

        $jobs = Queue::pushed( IndexModels::class );
        $this->assertCount( 3, $jobs );
        $this->assertSame( [50, 50, 1], $jobs->map( fn( IndexModels $job ) => count( $job->ids ) )->all() );
    }


    public function testExternalPageIndexJobSupportsNoTenancy(): void
    {
        \Aimeos\Cms\Tenancy::$callback = null;
        \Aimeos\Cms\Tenancy::set( '' );
        $node = new Page( ['source' => 'en'] );
        $node->save();

        $page = $node->variants()->create( [
            'lang' => 'en',
            'name' => 'No tenancy',
            'title' => 'No tenancy',
            'path' => 'no-tenancy',
            'status' => 1,
            'editor' => 'test',
        ] )->view;
        $search = $this->searchEngine();

        ( new IndexModels( PageView::class, [$page->id], '' ) )->handle();

        $this->assertSame( '', \Aimeos\Cms\Tenancy::value() );
        $this->assertSame( [[$page->id]], $search->updates );
    }


    public function testExternalPageIndexIsNotUpdatedAfterOuterRollback(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $public = PageView::where( 'path', 'blog' )->firstOrFail();
        $search = $this->searchEngine();
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );

        $connection->beginTransaction();

        try {
            PageAccess::set( [$page->id, $public->id], [] );
            $this->assertSame( [], $search->updates );
        } finally {
            $connection->rollBack();
        }

        $this->assertSame( [], $search->updates );
        $this->assertFalse( PageAccess::whereIn( 'page_id', [$page->id, $public->id] )->exists() );
    }


    public function testExternalPageIndexIsUpdatedAfterOuterCommit(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $search = $this->searchEngine();
        $connection = DB::connection( config( 'cms.db', 'sqlite' ) );

        $connection->beginTransaction();
        PageAccess::set( [$page->id], [] );

        $this->assertSame( [], $search->updates );

        $connection->commit();

        $this->assertSame( [[$page->id]], $search->updates );
    }


    public function testInvalidatesRoutesAfterDatabaseChanges(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $public = PageView::where( 'path', 'blog' )->firstOrFail();
        PageAccess::set( [$page->id, $public->id], [] );

        $this->assertSame( 2, PageAccess::whereIn( 'page_id', [$page->id, $public->id] )->count() );
        $this->assertInvalidated( ['hidden', 'blog'] );

        $this->invalidator->reset();
        PageAccess::set( [$page->id, $public->id], null );

        $this->assertSame( 0, PageAccess::whereIn( 'page_id', [$page->id, $public->id] )->count() );
        $this->assertInvalidated( ['hidden', 'blog'] );
    }


    public function testSubtreeDoesNotAcquirePageTreeLock(): void
    {
        $page = PageView::where( 'tag', 'root' )->firstOrFail();

        Cache::shouldReceive( 'lock' )->never();

        $this->assertGreaterThan( 1, PageAccess::set( [$page->id], [], descendants: true ) );
    }


    public function testIdempotentAccessChangesHaveNoSideEffects(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $public = PageView::where( 'path', 'blog' )->firstOrFail();
        $search = $this->searchEngine();

        $this->assertSame( 1, PageAccess::set( [$page->id], ['member'] ) );
        $search->updates = [];
        $this->invalidator->reset();
        $this->assertSame( 1, PageAccess::set( [$page->id], ['member'] ) );
        $this->assertSame( 1, PageAccess::set( [$public->id], null ) );
        $this->assertSame( [], $search->updates );
        $this->assertSame( [], $this->invalidator->events );
    }


    public function testMixedAccessChangesOnlyInvalidateAndReindexChangedPages(): void
    {
        $restricted = PageView::where( 'path', 'hidden' )->firstOrFail();
        $public = PageView::where( 'path', 'blog' )->firstOrFail();
        $search = $this->searchEngine();

        PageAccess::set( [$restricted->id], ['member'] );
        $search->updates = [];
        $this->invalidator->reset();

        $this->assertSame( 2, PageAccess::set( [$restricted->id, $public->id], ['member'] ) );
        $this->assertInvalidated( ['blog'] );
        $this->assertSame( [[$public->id]], $search->updates );
    }


    public function testRestrictionReplacesAllAccessRows(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();

        PageAccess::set( [$page->id], ['alpha', 'beta'] );
        PageAccess::set( [$page->id], ['gamma'] );

        $this->assertSame(
            ['gamma'],
            PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all(),
        );
    }


    public function testRestrictionRetryReplacesMoreThanOneChunk(): void
    {
        $template = (array) PageView::where( 'path', 'hidden' )->toBase()->first();
        $ids = $rows = [];

        for( $i = 0; $i <= PageAccess::CHUNK_SIZE; $i++ )
        {
            $id = Str::uuid7()->toString();
            $row = $template;
            $row['id'] = $id;
            $row['variant_id'] = $id;
            $row['path'] = 'access-bulk-' . $i;
            $row['_lft'] = 10000 + $i * 2;
            $row['_rgt'] = 10001 + $i * 2;
            $ids[] = $id;
            $rows[] = $row;
        }

        foreach( array_chunk( $rows, 50 ) as $chunk ) {
            $this->insertViewRows( $chunk );
        }

        $this->assertCount( PageAccess::CHUNK_SIZE + 1, $ids );
        $this->assertSame( count( $ids ), PageAccess::set( $ids, ['member'] ) );
        $this->assertSame( count( $ids ), PageAccess::set( $ids, ['member'] ) );
        $this->assertSame( count( $ids ), PageAccess::whereIn( 'page_id', $ids )->count() );
    }


    public function testRejectsMoreThanOneThousandBulkPages(): void
    {
        $ids = array_map( strval(...), range( 1, 1001 ) );

        try {
            PageAccess::set( $ids, [] );
            $this->fail( 'Access changes must be limited to 1,000 pages.' );
        } catch( Exception $e ) {
            $this->assertSame( 'No more than 1000 items may be changed at once.', $e->getMessage() );
        }

        $this->assertSame( [], $this->invalidator->events );
        $this->assertSame( 0, PageAccess::count() );
    }


    public function testRejectsMoreThanOneThousandAccessAssignments(): void
    {
        $ids = PageView::query()->limit( 5 )->pluck( 'id' )->map( strval(...) )->all();
        $values = array_map( fn( int $value ) => 'access-' . $value, range( 1, 250 ) );
        Access::using( fn() => $values );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No more than 1000 page access assignments may be changed at once.' );

        PageAccess::set( $ids, $values );
    }


    public function testConsumesAllIdsBeforeApplyingSideEffects(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        $second = PageView::where( 'path', 'blog' )->firstOrFail();
        $search = $this->searchEngine();

        $ids = function() use ( $page, $second, $search ) {
            for( $i = 0; $i < PageAccess::CHUNK_SIZE; $i++ ) {
                yield $page->id;
            }

            $this->assertSame( [], $search->updates );
            yield $second->id;
        };

        PageAccess::set( $ids(), [] );

        $this->assertCount( 1, $search->updates );
        $this->assertEqualsCanonicalizing( [$page->id, $second->id], $search->updates[0] );
    }


    public function testGenericAncestorsUseOneQuery(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $page->ancestors()->get();

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testSubtreeBulkOperationUsesConstantQueryCount(): void
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();

        PageAccess::set( [$root->id], [], descendants: true );

        $this->assertCount( 5, DB::getQueryLog() );

        $count = PageView::query()
            ->where( \Aimeos\Nestedset\NestedSet::LFT, '>=', $root->getLft() )
            ->where( \Aimeos\Nestedset\NestedSet::RGT, '<=', $root->getRgt() )
            ->count();
        DB::flushQueryLog();
        $this->assertSame( $count, PageAccess::set( [$root->id], [], descendants: true ) );
        $this->assertCount( 3, DB::getQueryLog() );
    }


    public function testSubtreeOperationRefreshesStaleRootBounds(): void
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        $count = PageView::query()
            ->where( \Aimeos\Nestedset\NestedSet::LFT, '>=', $root->getLft() )
            ->where( \Aimeos\Nestedset\NestedSet::RGT, '<=', $root->getRgt() )
            ->count();

        $root->setAttribute( \Aimeos\Nestedset\NestedSet::LFT, 999999 );
        $root->setAttribute( \Aimeos\Nestedset\NestedSet::RGT, 999999 );

        $this->assertSame( $count, PageAccess::set( [$root->id], [], descendants: true ) );
        $this->assertSame( $count, PageAccess::count() );
    }


    public function testRetryDoesNotInvalidateOrReindex(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $search = $this->searchEngine();
        $this->invalidator->reset();

        $this->assertSame( 1, PageAccess::set( [$page->id], ['member'] ) );
        $this->assertSame( [], $this->invalidator->events );
        $this->assertSame( [], $search->updates );
    }


    public function testRejectsUnknownAccessValues(): void
    {
        $page = PageView::where( 'path', 'hidden' )->firstOrFail();

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown frontend access value "unknown".' );

        PageAccess::set( [$page->id], ['unknown'] );
    }


    public function testRestrictSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::set( [$root->id], [], descendants: true );
    }


    public function testReleaseSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = PageView::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::set( [$root->id], null, descendants: true );
    }


    public function testAccessScopeTreatsUsersFromAnotherTenantAsGuests(): void
    {
        $restricted = PageView::where( 'path', 'hidden' )->firstOrFail();
        $public = PageView::where( 'path', 'blog' )->firstOrFail();
        PageAccess::set( [$restricted->id], [] );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'other';

        $pages = PageView::query()->access( $user )->whereKey( [$restricted->id, $public->id] )->pluck( 'id' );

        $this->assertNotContains( $restricted->id, $pages );
        $this->assertContains( $public->id, $pages );
    }


    /**
     * @param array<int, string> $paths
     */
    private function assertInvalidated( array $paths ) : void
    {
        $this->assertCount( count( $paths ), $this->invalidator->events );
        $this->assertEqualsCanonicalizing( $paths, array_column( $this->invalidator->events, 'path' ) );
    }


    private function searchEngine(): SearchEngineSpy
    {
        $engine = new SearchEngineSpy();
        $manager = app( EngineManager::class );

        $manager->extend( 'page-access-test', fn() => $engine );
        $manager->forgetDrivers();
        config( ['scout.driver' => 'page-access-test'] );

        return $engine;
    }


    /**
     * Inserts page rows in the format of the page view into the page and variant tables.
     *
     * @param array<int, array<string, mixed>> $rows Raw rows of the page view
     */
    protected function insertViewRows( array $rows ) : void
    {
        Page::query()->insert( array_map( fn( $row ) => PageBuilder::pageValues( $row ), $rows ) );
        PageVariant::query()->insert( array_map( fn( $row ) => PageBuilder::variantValues( $row, true )
            + ['page_id' => $row['id'], 'tenant_id' => $row['tenant_id']], $rows ) );
    }
}

class SearchEngineSpy extends NullEngine
{
    /** @var array<int, array<int, string>> */
    public array $updates = [];


    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, PageView> $models
     */
    public function update( $models ) : void
    {
        $this->updates[] = $models->modelKeys();
    }

}
