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
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Scout;
use Database\Seeders\TestSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
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


    public function testEmptyAccessValueListsRequireAuthentication(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, PageAccess::set( [$page->id], [] ) );
        $this->assertSame( '', PageAccess::where( 'page_id', $page->id )->firstOrFail()->value );
        $this->assertSame( '', DB::connection( config( 'cms.db', 'sqlite' ) )
            ->table( 'cms_page_access' )->where( 'page_id', $page->id )->value( 'value' ) );
    }


    public function testDatabaseRejectsAccessOwnedByAnotherTenant(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
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


    public function testRestrictionsCanBeReleasedWhenAccessIsUnavailable(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], [] );
        Access::using( null );

        $this->assertSame( 1, PageAccess::set( [$page->id], null ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testRejectsMoreThanTwoHundredFiftyAccessValues(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $values = array_map( fn( $value ) => 'value-' . $value, range( 1, 251 ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'A page may not require more than 250 access values.' );

        PageAccess::set( [$page->id], $values );
    }


    public function testRestrictsAndReleasesPageDatabaseFirst(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $this->searchEngine();
        config( ['scout.queue' => true] );
        Queue::fake();

        PageAccess::set( [$page->id], [] );

        Queue::assertPushed( IndexModels::class, fn( IndexModels $job ) =>
            $job->model === Page::class && $job->ids === [$page->id] && $job->tenant === 'test'
        );
    }


    public function testCmsPageIndexRefreshIsNotQueued(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
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
        Scout::reindex( Page::class, $ids );

        $jobs = Queue::pushed( IndexModels::class );
        $this->assertCount( 3, $jobs );
        $this->assertSame( [50, 50, 1], $jobs->map( fn( IndexModels $job ) => count( $job->ids ) )->all() );
    }


    public function testExternalPageIndexJobSupportsNoTenancy(): void
    {
        \Aimeos\Cms\Tenancy::$callback = null;
        \Aimeos\Cms\Tenancy::set( '' );
        $page = Page::forceCreate( [
            'lang' => 'en',
            'name' => 'No tenancy',
            'title' => 'No tenancy',
            'path' => 'no-tenancy',
            'status' => 1,
            'editor' => 'test',
        ] );
        $search = $this->searchEngine();

        ( new IndexModels( Page::class, [$page->id], '' ) )->handle();

        $this->assertSame( '', \Aimeos\Cms\Tenancy::value() );
        $this->assertSame( [[$page->id]], $search->updates );
    }


    public function testExternalPageIndexIsNotUpdatedAfterOuterRollback(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
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
        $page = Page::where( 'tag', 'root' )->firstOrFail();

        Cache::shouldReceive( 'lock' )->never();

        $this->assertGreaterThan( 1, PageAccess::set( [$page->id], [], descendants: true ) );
    }


    public function testIdempotentAccessChangesHaveNoSideEffects(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
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
        $restricted = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        PageAccess::set( [$page->id], ['alpha', 'beta'] );
        PageAccess::set( [$page->id], ['gamma'] );

        $this->assertSame(
            ['gamma'],
            PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all(),
        );
    }


    public function testRestrictionRetryReplacesMoreThanOneChunk(): void
    {
        $template = (array) DB::connection( config( 'cms.db', 'sqlite' ) )
            ->table( 'cms_pages' )->where( 'path', 'hidden' )->first();
        $ids = $rows = [];

        for( $i = 0; $i <= PageAccess::CHUNK_SIZE; $i++ )
        {
            $id = Str::uuid7()->toString();
            $row = $template;
            $row['id'] = $id;
            $row['path'] = 'access-bulk-' . $i;
            $row['_lft'] = 10000 + $i * 2;
            $row['_rgt'] = 10001 + $i * 2;
            $ids[] = $id;
            $rows[] = $row;
        }

        $table = DB::connection( config( 'cms.db', 'sqlite' ) )->table( 'cms_pages' );

        foreach( array_chunk( $rows, 50 ) as $chunk ) {
            $table->insert( $chunk );
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
        $ids = Page::query()->limit( 5 )->pluck( 'id' )->map( strval(...) )->all();
        $values = array_map( fn( int $value ) => 'access-' . $value, range( 1, 250 ) );
        Access::using( fn() => $values );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'No more than 1000 page access assignments may be changed at once.' );

        PageAccess::set( $ids, $values );
    }


    public function testConsumesAllIdsBeforeApplyingSideEffects(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $second = Page::where( 'path', 'blog' )->firstOrFail();
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $page->ancestors()->get();

        $this->assertCount( 1, DB::getQueryLog() );
    }


    public function testSubtreeBulkOperationUsesConstantQueryCount(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        DB::flushQueryLog();
        DB::enableQueryLog();

        PageAccess::set( [$root->id], [], descendants: true );

        $this->assertCount( 5, DB::getQueryLog() );

        $count = Page::query()
            ->where( \Aimeos\Nestedset\NestedSet::LFT, '>=', $root->getLft() )
            ->where( \Aimeos\Nestedset\NestedSet::RGT, '<=', $root->getRgt() )
            ->count();
        DB::flushQueryLog();
        $this->assertSame( $count, PageAccess::set( [$root->id], [], descendants: true ) );
        $this->assertCount( 3, DB::getQueryLog() );
    }


    public function testSubtreeOperationRefreshesStaleRootBounds(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $count = Page::query()
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
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $search = $this->searchEngine();
        $this->invalidator->reset();

        $this->assertSame( 1, PageAccess::set( [$page->id], ['member'] ) );
        $this->assertSame( [], $this->invalidator->events );
        $this->assertSame( [], $search->updates );
    }


    public function testAllowsUsesGlobalGateValues(): void
    {
        $calls = 0;
        Gate::define( 'member', function() use ( &$calls ) {
            $calls++;
            return true;
        } );

        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $access = new PageAccess( ['value' => 'member'] );

        $this->assertTrue( PageAccess::allows( [$access], $user ) );
        $this->assertSame( 1, $calls );
    }


    public function testAllowsChecksOnlyPageValuesOncePerRequest(): void
    {
        $calls = 0;
        Gate::before( function() use ( &$calls ) {
            $calls++;
            return null;
        } );
        Gate::define( 'member', fn() => true );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $access = [new PageAccess( ['value' => 'member'] )];

        $this->assertTrue( PageAccess::allows( $access, $user ) );
        $this->assertTrue( PageAccess::allows( $access, $user ) );
        $this->assertSame( 1, $calls );
    }


    public function testAllowsAnyAccessValue(): void
    {
        Gate::define( 'denied', fn() => false );
        Gate::define( 'member', fn() => true );

        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $access = [
            new PageAccess( ['value' => 'denied'] ),
            new PageAccess( ['value' => 'member'] ),
        ];

        $this->assertTrue( PageAccess::allows( $access, $user ) );
    }


    public function testRejectsUnknownAccessValues(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Unknown frontend access value "unknown".' );

        PageAccess::set( [$page->id], ['unknown'] );
    }


    public function testRestrictSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::set( [$root->id], [], descendants: true );
    }


    public function testReleaseSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::set( [$root->id], null, descendants: true );
    }


    public function testAuthenticationOnlyRulesRequireAuthentication(): void
    {
        $access = new PageAccess( ['value' => ''] );

        $this->assertFalse( PageAccess::allows( [$access], null ) );
        $this->assertTrue( PageAccess::allows( [], null ) );

        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'test';
        $this->assertTrue( PageAccess::allows( [$access], $user ) );
    }


    public function testRestrictedRulesRejectUsersFromAnotherTenant(): void
    {
        Gate::define( 'member', fn() => true );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'other';

        $this->assertFalse( PageAccess::allows( [new PageAccess( ['value' => ''] )], $user ) );
        $this->assertFalse( PageAccess::allows( [new PageAccess( ['value' => 'member'] )], $user ) );
    }


    public function testRestrictedRulesRejectUnresolvedConfiguredTenant(): void
    {
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = '';
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( '' ) );

        $this->assertFalse( PageAccess::allows( [new PageAccess( ['value' => ''] )], $user ) );
    }


    public function testAccessScopeTreatsUsersFromAnotherTenantAsGuests(): void
    {
        $restricted = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
        PageAccess::set( [$restricted->id], [] );
        $user = new \App\Models\User();
        $user->id = 42;
        $user->tenant_id = 'other';

        $pages = Page::query()->access( $user )->whereKey( [$restricted->id, $public->id] )->pluck( 'id' );

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
}

class SearchEngineSpy extends NullEngine
{
    /** @var array<int, array<int, string>> */
    public array $updates = [];


    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, Page> $models
     */
    public function update( $models ) : void
    {
        $this->updates[] = $models->modelKeys();
    }
}
