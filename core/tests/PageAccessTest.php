<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Events\PagesInvalidated;
use Aimeos\Cms\Jobs\SyncPages;
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
use Illuminate\Support\Testing\Fakes\QueueFake;
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
        Event::listen( PagesInvalidated::class, [$this->invalidator, 'handle'] );
    }


    public function testEmptyAccessValueListsRequireAuthentication(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, PageAccess::restrict( [$page->id], [] ) );
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
            PageAccess::restrict( [$page->id], null );
            $this->fail( 'Unconfigured access restrictions must be rejected.' );
        } catch( Exception $e ) {
            $this->assertSame( 'Frontend access restrictions are not available.', $e->getMessage() );
        }

        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
        $this->assertSame( [], $this->invalidator->batches );
    }


    public function testRestrictionsCanBeReleasedWhenAccessIsUnavailable(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], null );
        Access::using( null );

        $this->assertSame( 1, PageAccess::release( [$page->id] ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testRejectsMoreThanTwoHundredFiftyAccessValues(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $values = array_map( fn( $value ) => 'value-' . $value, range( 1, 251 ) );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'A page may not require more than 250 access values.' );

        PageAccess::restrict( [$page->id], $values );
    }


    public function testRestrictsAndReleasesPageDatabaseFirst(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, PageAccess::restrict( [$page->id], [' beta ', 'alpha', 'alpha'] ) );

        $this->assertSame(
            ['alpha', 'beta'],
            PageAccess::where( 'page_id', $page->id )->orderBy( 'value' )->pluck( 'value' )->all(),
        );
        $this->assertSame( ['test'], PageAccess::where( 'page_id', $page->id )->pluck( 'tenant_id' )->unique()->all() );
        $this->assertInvalidated( ['hidden'] );

        $this->invalidator->reset();
        $this->assertSame( 1, PageAccess::release( [$page->id] ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
        $this->assertInvalidated( ['hidden'] );
    }


    public function testRefreshesExternalPageIndex(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $search = $this->searchEngine();

        PageAccess::restrict( [$page->id], null );
        PageAccess::release( [$page->id] );

        $this->assertSame( [[$page->id], [$page->id]], $search->updates );
    }


    public function testExternalPageIndexRefreshIsQueuedAfterCommit(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $this->searchEngine();
        Queue::fake();

        PageAccess::restrict( [$page->id], null );

        Queue::assertPushed( SyncPages::class, fn( SyncPages $job ) =>
            $job->ids === [$page->id] && $job->tenant === 'test'
        );
    }


    public function testInvalidatesAllPagesBeforeIsolatedIndexEnqueues(): void
    {
        config( ['cms.chunksize' => 1] );
        $this->searchEngine();
        $actual = Queue::getFacadeRoot();
        $queue = new FailingQueueFake(
            app(),
            fn() => count( $this->invalidator->batches ),
            $actual,
        );
        Queue::swap( $queue );
        $this->withoutExceptionHandling();

        $first = Page::where( 'path', 'hidden' )->firstOrFail();
        $second = Page::where( 'path', 'blog' )->firstOrFail();

        PageAccess::restrict( [$first->id, $second->id], null );

        $this->assertCount( 1, $this->invalidator->batches );
        $this->assertSame( [1, 1], $queue->invalidationsAtPush );
        $this->assertSame( 2, $queue->attempts );
        Queue::assertPushedTimes( SyncPages::class, 1 );
    }


    public function testExternalPageIndexJobsHaveBoundedPayloads(): void
    {
        config( ['cms.chunksize' => 20] );
        $ids = array_map( strval(...), range( 0, 20 ) );

        $this->searchEngine();
        Queue::fake();
        Scout::syncPages( $ids );

        $jobs = Queue::pushed( SyncPages::class );
        $this->assertCount( 2, $jobs );
        $this->assertSame( [20, 1], $jobs->map( fn( SyncPages $job ) => count( $job->ids ) )->all() );
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

        ( new SyncPages( [$page->id], '' ) )->handle();

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
            PageAccess::restrict( [$page->id, $public->id], null );
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
        PageAccess::restrict( [$page->id], null );

        $this->assertSame( [], $search->updates );

        $connection->commit();

        $this->assertSame( [[$page->id]], $search->updates );
    }


    public function testInvalidatesBatchesAfterDatabaseChanges(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
        PageAccess::restrict( [$page->id, $public->id], null );

        $this->assertSame( 2, PageAccess::whereIn( 'page_id', [$page->id, $public->id] )->count() );
        $this->assertInvalidated( ['hidden', 'blog'] );

        $this->invalidator->reset();
        PageAccess::release( [$page->id, $public->id] );

        $this->assertSame( 0, PageAccess::whereIn( 'page_id', [$page->id, $public->id] )->count() );
        $this->assertInvalidated( ['hidden', 'blog'] );
    }


    public function testSideEffectsRunOutsidePageTreeLock(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $locked = false;
        $lock = \Mockery::mock( \Illuminate\Contracts\Cache\Lock::class );

        $lock->shouldReceive( 'block' )->once()->andReturnUsing(
            function( int $seconds, \Closure $callback ) use ( &$locked ) {
                $locked = true;
                $result = $callback();
                $locked = false;
                return $result;
            }
        );

        Cache::shouldReceive( 'lock' )->once()->andReturn( $lock );
        Event::listen( PagesInvalidated::class, function() use ( &$locked ) {
            $this->assertFalse( $locked );
        } );

        PageAccess::restrict( [$page->id], null );
    }


    public function testRetriesIdempotentRestrictionsAndPublicReleases(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $public = Page::where( 'path', 'blog' )->firstOrFail();
        $search = $this->searchEngine();

        $this->assertSame( 1, PageAccess::restrict( [$page->id], ['member'] ) );
        $search->updates = [];
        $this->invalidator->reset();
        $this->assertSame( 1, PageAccess::restrict( [$page->id], ['member'] ) );
        $this->assertSame( 1, PageAccess::release( [$public->id] ) );
        $this->assertSame( [[$page->id], [$public->id]], $search->updates );
        $this->assertSame(
            [['hidden'], ['blog']],
            array_map( fn( $batch ) => array_column( $batch, 'path' ), $this->invalidator->batches ),
        );
    }


    public function testRestrictionReplacesAllAccessRows(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        PageAccess::restrict( [$page->id], ['alpha', 'beta'] );
        PageAccess::restrict( [$page->id], ['gamma'] );

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
        $this->assertSame( count( $ids ), PageAccess::restrict( $ids, ['member'] ) );
        $this->assertSame( count( $ids ), PageAccess::restrict( $ids, ['member'] ) );
        $this->assertSame( count( $ids ), PageAccess::whereIn( 'page_id', $ids )->count() );
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

        PageAccess::restrict( $ids(), null );

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

        PageAccess::restrictSubtree( $root, null );

        $this->assertCount( 4, DB::getQueryLog() );

        $count = Page::query()
            ->where( \Aimeos\Nestedset\NestedSet::LFT, '>=', $root->getLft() )
            ->where( \Aimeos\Nestedset\NestedSet::RGT, '<=', $root->getRgt() )
            ->count();
        DB::flushQueryLog();
        $this->assertSame( $count, PageAccess::restrictSubtree( $root, null ) );
        $this->assertCount( 4, DB::getQueryLog() );
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

        $this->assertSame( $count, PageAccess::restrictSubtree( $root, null ) );
        $this->assertSame( $count, PageAccess::count() );
    }


    public function testRetryRepairsSideEffects(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::restrict( [$page->id], ['member'] );
        $search = $this->searchEngine();
        $this->invalidator->reset();

        $this->assertSame( 1, PageAccess::restrict( [$page->id], ['member'] ) );
        $this->assertInvalidated( ['hidden'] );
        $this->assertSame( [[$page->id]], $search->updates );
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

        PageAccess::restrict( [$page->id], ['unknown'] );
    }


    public function testRestrictSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::restrictSubtree( $root, null );
    }


    public function testReleaseSubtreeDoesNotFindForeignTenantRoot(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        app()->instance( \Aimeos\Cms\Tenancy::class, new \Aimeos\Cms\Tenancy( 'other' ) );

        $this->expectException( \Illuminate\Database\Eloquent\ModelNotFoundException::class );

        PageAccess::releaseSubtree( $root );
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
        PageAccess::restrict( [$restricted->id], null );
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
        $this->assertCount( 1, $this->invalidator->batches );
        $this->assertEqualsCanonicalizing( $paths, array_column( $this->invalidator->batches[0], 'path' ) );
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


class FailingQueueFake extends QueueFake
{
    public int $attempts = 0;
    /** @var list<int> */
    public array $invalidationsAtPush = [];


    public function __construct( $app, private \Closure $invalidations, $queue )
    {
        parent::__construct( $app, [], $queue );
    }


    public function push( $job, $data = '', $queue = null )
    {
        $this->attempts++;
        $this->invalidationsAtPush[] = ( $this->invalidations )();

        if( $this->attempts === 1 ) {
            throw new \RuntimeException( 'Queue unavailable' );
        }

        return parent::push( $job, $data, $queue );
    }
}
