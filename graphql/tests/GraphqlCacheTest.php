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
        $root2 = Page::where( 'tag', 'hidden' )->firstOrFail();
        Page::query()
            ->where( NestedSet::LFT, '>', $root->getLft() )
            ->where( NestedSet::RGT, '<', $root->getRgt() )
            ->firstOrFail()
            ->update( ['domain' => 'other.example'] );
        $root2->update( ['domain' => 'another.example'] );
        $ids = [$root->id, $root2->id];
        $roots = Page::query()
            ->withTrashed()
            ->select( 'id', 'tenant_id', NestedSet::LFT, NestedSet::RGT )
            ->whereIn( 'id', $ids )
            ->get();

        $pages = collect();
        foreach( $roots as $node ) {
            $pages = $pages->merge( Page::query()
                ->withTrashed()
                ->whereBetween( NestedSet::LFT, [(int) $node->getAttribute( NestedSet::LFT ), (int) $node->getAttribute( NestedSet::RGT )] )
                ->get( ['domain', 'path'] )
            );
        }

        $all = $pages->unique( fn( $page ) => $page->getAttribute( 'domain' ) . '|' . $page->getAttribute( 'path' ) );

        Event::fake( [PageInvalidated::class] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($ids: [ID!]!) {
                clearCache(ids: $ids)
            }
        ', ['ids' => [$root->id, $root2->id]] )->assertExactJson( [
            'data' => ['clearCache' => $all->count()],
        ] );

        foreach( $all->groupBy( 'domain' ) as $domain => $items ) {
            Event::assertDispatched( PageInvalidated::class, fn( PageInvalidated $event ) =>
                $event->domain === (string) $domain
                && collect( $event->paths )->sort()->values()->all()
                    === $items->pluck( 'path' )->sort()->values()->all()
            );
        }

        Event::assertDispatchedTimes( PageInvalidated::class, $all->pluck( 'domain' )->unique()->count() );
    }


    public function testRequiresClearPermission(): void
    {
        $page = Page::where( 'tag', 'disabled' )->firstOrFail();
        $user = new \App\Models\User( ['cmsperms' => ['page:view']] );

        $this->actingAs( $user )->graphQL( '
            mutation($ids: [ID!]!) {
                clearCache(ids: $ids)
            }
        ', ['ids' => [$page->id]] )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }
}
