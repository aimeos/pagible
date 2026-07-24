<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\Version;
use Aimeos\Cms\Permission;
use Aimeos\Cms\SearchBuilder;
use Aimeos\Cms\Scopes\Status;
use Aimeos\Cms\Tenancy;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Laravel\Scout\Builder as ScoutBuilder;


class TenancyTest extends CoreTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;

    protected function tearDown(): void
    {
        Tenancy::$callback = fn() => 'test';
        Tenancy::$access = null;
        app()->forgetScopedInstances();

        Permission::canUsing( null );

        parent::tearDown();
    }


    public function testAllowsRejectsAnonymousAndUnresolvedConfiguredTenant(): void
    {
        $called = false;
        $user = new \App\Models\User();
        $user->tenant_id = '';
        Tenancy::$callback = fn() => '';
        Tenancy::$access = function() use ( &$called ) {
            $called = true;
            return true;
        };

        $this->assertFalse( Tenancy::allows( null, '' ) );
        $this->assertFalse( Tenancy::allows( $user, '' ) );
        $this->assertFalse( $called );
    }


    public function testAllowsEmptyTenantWithoutTenancyConfiguration(): void
    {
        $user = new \App\Models\User();
        $user->tenant_id = '';
        Tenancy::$callback = null;

        $this->assertTrue( Tenancy::allows( $user, '' ) );
    }


    public function testTenancyScopeApply()
    {
        app()->forgetInstance( Tenancy::class );

        $pages = Page::all();

        $this->assertGreaterThan( 0, $pages->count() );

        foreach( $pages as $page ) {
            $this->assertEquals( 'test', $page->tenant_id );
        }
    }


    public function testCrossTenantIsolation()
    {

        $countBefore = Page::withoutTenancy()->count();
        $this->assertGreaterThan( 0, $countBefore );

        Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        $pages = Page::all();
        $this->assertEquals( 0, $pages->count() );
    }


    public function testSearchTenantFilterSurvivesExistingSearchFieldsMacro(): void
    {
        $property = new \ReflectionProperty( ScoutBuilder::class, 'macros' );
        $macros = $property->getValue();

        try
        {
            ScoutBuilder::macro( 'searchFields', function() {
                $this->wheres = [];
                return $this;
            } );

            $builder = Page::search()->searchFields( 'draft' )->take( 1 );

            $this->assertInstanceOf( SearchBuilder::class, $builder );
            $this->assertSame( [], $builder->wheres );

            $builder->get();

            $tenant = array_values( array_filter(
                $builder->wheres,
                fn( $where ) => ( $where['field'] ?? null ) === 'tenant_id',
            ) );

            $this->assertCount( 1, $tenant );
            $this->assertSame( 'test', $tenant[0]['value'] );
        }
        finally {
            $property->setValue( null, $macros );
        }
    }


    public function testStanclLifecycleKeepsTenantServicesContextAware(): void
    {
        $this->stanclFakes();
        Access::using( fn() => Tenancy::value() === '' ? [] : [Tenancy::value()] );
        $access = app( Access::class );
        $event = new StanclEventFake( new StanclTenantFake( 'other' ) );

        $this->assertSame( ['test'], $access->list() );

        Tenancy::stancl();
        Event::dispatch( 'Stancl\\Tenancy\\Events\\InitializingTenancy', [$event] );

        $this->assertSame( 'other', Tenancy::value() );
        $this->assertSame( $access, app( Access::class ) );
        $this->assertSame( ['other'], $access->list() );

        Event::dispatch( 'Stancl\\Tenancy\\Events\\TenancyEnded', [$event] );

        $this->assertSame( '', Tenancy::value() );
        $this->assertSame( [], $access->list() );
    }


    public function testRunUsesExistingTenantContext(): void
    {
        $access = app( Access::class );

        $result = Tenancy::run( 'test', fn() => app( Access::class ) );

        $this->assertSame( $access, $result );
        $this->assertSame( 'test', Tenancy::value() );
    }


    public function testRunSwitchesAndRestoresGenericTenantContext(): void
    {
        $result = Tenancy::run( 'other', fn() => Tenancy::value() );

        $this->assertSame( 'other', $result );
        $this->assertSame( 'test', Tenancy::value() );
    }


    public function testRunRestoresGenericTenantContextAfterFailure(): void
    {
        try {
            Tenancy::run( 'other', function() {
                $this->assertSame( 'other', Tenancy::value() );
                throw new \RuntimeException( 'failed' );
            } );

            $this->fail( 'The tenant operation should fail.' );
        } catch( \RuntimeException $e ) {
            $this->assertSame( 'failed', $e->getMessage() );
        }

        $this->assertSame( 'test', Tenancy::value() );
    }


    public function testAccessUsePropagatesTenantActivationFailure(): void
    {
        $access = app( Access::class );
        $calls = 0;
        $property = new \ReflectionProperty( Access::class, 'activateCallback' );
        $property->setValue( null, function( string $tenant ) use ( &$calls ) {
            $calls++;
            throw new \RuntimeException( "Failed to activate {$tenant}" );
        } );

        Tenancy::set( 'other' );
        $this->assertSame( 'other', Tenancy::value() );
        $this->assertSame( $access, app( Access::class ) );

        for( $i = 0; $i < 2; $i++ )
        {
            try {
                $access->list();
                $this->fail( 'Using Access should retry the failed activation.' );
            } catch( \RuntimeException $e ) {
                $this->assertSame( 'Failed to activate other', $e->getMessage() );
            }
        }

        $this->assertSame( 2, $calls );
    }


    public function testWithoutTenancyMacro()
    {

        Tenancy::$callback = fn() => 'other';
        app()->forgetScopedInstances();

        $pages = Page::withoutTenancy()->get();
        $this->assertGreaterThan( 0, $pages->count() );
    }


    public function testTenancyAutoSetsOnCreate()
    {
        $page = Page::forceCreate( [
            'name' => 'Tenant Test Page',
            'title' => 'Tenant Test',
            'path' => 'tenant-test',
            'tag' => 'page',
            'to' => '',
            'domain' => '',
            'lang' => 'en',
            'type' => '',
            'theme' => '',
            'cache' => 0,
            'status' => 1,
            'editor' => 'test',
            'meta' => [],
            'config' => [],
            'content' => [],
        ] );

        $this->assertEquals( 'test', $page->tenant_id );
    }


    public function testPublishRejectsVersionOwnedByAnotherModel(): void
    {
        $page = Page::with( 'latest' )->whereNotNull( 'latest_id' )->firstOrFail();
        $other = Page::with( 'latest' )->whereNotNull( 'latest_id' )
            ->whereKeyNot( $page->getKey() )->firstOrFail();
        $version = $other->latest ?? throw new \RuntimeException( 'Missing other page version.' );
        $name = $page->name;

        try {
            $page->publish( $version );
            $this->fail( 'Publishing a version owned by another model must fail.' );
        } catch( \LogicException $e ) {
            $this->assertSame( 'CMS version does not belong to the model.', $e->getMessage() );
        }

        $this->assertSame( $name, Page::findOrFail( $page->id )->name );
    }


    public function testVersionTenancyAutoSetsOnCreate()
    {
        $page = Page::firstOrFail();
        $version = $page->versions()->forceCreate( [
            'editor' => 'test',
            'data' => (object) [],
        ] );

        $this->assertInstanceOf( Version::class, $version );
        $this->assertSame( 'test', $version->tenant_id );
    }


    public function testStatusScopeWithoutPermission()
    {
        Auth::shouldReceive( 'user' )->andReturn( null );

        $this->createPage( 'Draft', 'draft', 0 );
        $this->createPage( 'Published', 'published', 1 );
        $this->createPage( 'Review', 'review', 2 );

        $pages = Page::withGlobalScope( 'status', new Status )->get();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertNotContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
        $this->assertContains( 2, $statuses );
    }


    public function testStatusScopeWithPermission()
    {
        $user = new \App\Models\User( [
            'name' => 'Editor',
            'email' => 'editor@tenancy-test',
            'password' => 'secret',
            'cmsperms' => ['page:view'],
        ] );
        $user->tenant_id = 'test';

        Auth::shouldReceive( 'user' )->andReturn( $user );

        $this->createPage( 'Draft', 'draft2', 0 );
        $this->createPage( 'Published', 'published2', 1 );
        $this->createPage( 'Review', 'review2', 2 );

        $pages = Page::withGlobalScope( 'status', new Status )->get();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
        $this->assertContains( 2, $statuses );
    }


    public function testStatusScopeRejectsPermissionFromAnotherTenant()
    {
        $user = new \App\Models\User( [
            'name' => 'Other tenant editor',
            'email' => 'other-editor@tenancy-test',
            'password' => 'secret',
            'cmsperms' => ['page:view'],
        ] );
        $user->tenant_id = 'other';

        Auth::shouldReceive( 'user' )->andReturn( $user );

        $this->createPage( 'Draft', 'draft-other-tenant', 0 );
        $this->createPage( 'Published', 'published-other-tenant', 1 );

        $statuses = Page::withGlobalScope( 'status', new Status )
            ->whereIn( 'path', ['draft-other-tenant', 'published-other-tenant'] )
            ->pluck( 'status' );

        $this->assertNotContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
    }


    public function testStatusScopeWithoutMacro()
    {
        Auth::shouldReceive( 'user' )->andReturn( null );

        $this->createPage( 'Draft', 'draft3', 0 );
        $this->createPage( 'Published', 'published3', 1 );

        // Without the status scope, all pages are visible regardless of status
        $pages = Page::all();
        $statuses = $pages->pluck( 'status' )->unique()->sort()->values()->toArray();

        $this->assertContains( 0, $statuses );
        $this->assertContains( 1, $statuses );
    }


    protected function createPage( string $name, string $path, int $status ): Page
    {
        return Page::forceCreate( [
            'name' => $name,
            'title' => $name,
            'path' => $path,
            'tag' => 'page',
            'to' => '',
            'domain' => '',
            'lang' => 'en',
            'type' => '',
            'theme' => '',
            'cache' => 0,
            'status' => $status,
            'editor' => 'test',
            'meta' => [],
            'config' => [],
            'content' => [],
        ] );
    }


    private function stanclFakes(): void
    {
        $initializing = 'Stancl\\Tenancy\\Events\\InitializingTenancy';
        $ended = 'Stancl\\Tenancy\\Events\\TenancyEnded';

        if( !class_exists( $initializing ) ) {
            class_alias( StanclEventFake::class, $initializing );
            class_alias( StanclEventFake::class, $ended );
        }
    }


}


final class StanclEventFake
{
    public object $tenancy;


    public function __construct( object $tenant )
    {
        $this->tenancy = (object) ['tenant' => $tenant];
    }
}


final class StanclTenantFake
{
    public function __construct( private readonly string $id )
    {
    }


    public function getTenantKey(): string
    {
        return $this->id;
    }
}
