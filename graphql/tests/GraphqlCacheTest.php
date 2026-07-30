<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;
use Aimeos\Cms\Models\Page;
use Aimeos\Nestedset\NestedSet;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;


class GraphqlCacheTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;

    protected $seeder = TestSeeder::class;


    protected function setUp(): void
    {
        parent::setUp();

        $this->bootRefreshesSchemaCache();

        $this->user = new \App\Models\User( [
            'name' => 'Cache editor',
            'email' => 'cache@testbench',
            'password' => 'secret',
            'cmsperms' => ['cache:clear'],
        ] );
    }


    public function testClearsPageSubtree(): void
    {
        $root = Page::where( 'tag', 'disabled' )->firstOrFail();
        Page::query()
            ->where( NestedSet::LFT, '>', $root->getLft() )
            ->where( NestedSet::RGT, '<', $root->getRgt() )
            ->firstOrFail()
            ->update( ['domain' => 'other.example'] );
        $pages = Page::query()
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->get( ['domain', 'path'] );

        Event::fake( [PageInvalidated::class] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: ID!) {
                clearCache(id: $id)
            }
        ', ['id' => $root->id] )->assertExactJson( [
            'data' => ['clearCache' => $pages->count()],
        ] );

        foreach( $pages->groupBy( 'domain' ) as $domain => $items ) {
            Event::assertDispatched( PageInvalidated::class, fn( PageInvalidated $event ) =>
                $event->domain === (string) $domain
                && collect( $event->paths )->sort()->values()->all()
                    === $items->pluck( 'path' )->sort()->values()->all()
            );
        }

        Event::assertDispatchedTimes( PageInvalidated::class, $pages->pluck( 'domain' )->unique()->count() );
    }


    public function testRequiresClearPermission(): void
    {
        $page = Page::where( 'tag', 'disabled' )->firstOrFail();
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        $this->actingAs( $user )->graphQL( '
            mutation($id: ID!) {
                clearCache(id: $id)
            }
        ', ['id' => $page->id] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }
}
