<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Access;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageAccess;
use Aimeos\Cms\Permission;
use Aimeos\Nestedset\NestedSet;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class GraphqlPageAccessTest extends GraphqlTestAbstract
{
    use CmsWithMigrations;
    use RefreshDatabase;
    use MakesGraphQLRequests;
    use RefreshesSchemaCache;

    protected $seeder = TestSeeder::class;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->bootRefreshesSchemaCache();
        Access::using( fn() => ['alpha', 'beta', 'member'] );

        $this->user = new \App\Models\User( [
            'name' => 'Test publisher',
            'email' => 'publisher@testbench',
            'password' => 'secret',
            'cmsperms' => Permission::all(),
        ] );
    }


    public function testAccessFieldsReturnCanonicalStates(): void
    {
        $public = Page::where( 'tag', 'root' )->firstOrFail();
        $authenticated = Page::where( 'path', 'hidden' )->firstOrFail();
        $restricted = Page::where( 'path', 'blog' )->firstOrFail();

        PageAccess::set( [$authenticated->id], [] );
        PageAccess::set( [$restricted->id], ['beta', 'alpha'] );

        $response = $this->actingAs( $this->user )->graphQL( '
            query($id: [ID!]) {
                pages(filter: {id: $id}) {
                    data { id access restricted }
                }
            }
        ', ['id' => [$public->id, $authenticated->id, $restricted->id]] );

        $response->assertGraphQLErrorFree();
        $states = collect( $response->json( 'data.pages.data' ) )->keyBy( 'id' );

        $this->assertNull( $states[(string) $public->id]['access'] );
        $this->assertFalse( $states[(string) $public->id]['restricted'] );
        $this->assertSame( [], $states[(string) $authenticated->id]['access'] );
        $this->assertTrue( $states[(string) $authenticated->id]['restricted'] );
        $this->assertSame( ['alpha', 'beta'], $states[(string) $restricted->id]['access'] );
        $this->assertTrue( $states[(string) $restricted->id]['restricted'] );
    }


    public function testAccessValuesRequireAccessViewPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $this->user->cmsperms = ['page:view'];

        $response = $this->actingAs( $this->user )->graphQL( '
            query($id: ID!) {
                page(id: $id) { id access restricted }
            }
        ', ['id' => $page->id] );

        $response->assertGraphQLErrorMessage( 'Insufficient permissions' );
        $this->assertTrue( $response->json( 'data.page.restricted' ) );
        $this->assertNull( $response->json( 'data.page.access' ) );
    }


    public function testRestrictedFieldDoesNotRequireAccessViewPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        PageAccess::set( [$page->id], ['member'] );
        $this->user->cmsperms = ['page:view'];

        $response = $this->actingAs( $this->user )->graphQL( '
            query($id: ID!) {
                page(id: $id) { id restricted }
            }
        ', ['id' => $page->id] );

        $response->assertGraphQLErrorFree();
        $this->assertTrue( $response->json( 'data.page.restricted' ) );
        $this->assertArrayNotHasKey( 'access', $response->json( 'data.page' ) );
    }


    public function testMutationAppliesAllAccessStates(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->assertSame( 1, $this->setAccess( [$page->id], [] ) );
        $this->assertSame( [''], PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all() );

        $this->assertSame( 1, $this->setAccess( [$page->id], ['member'] ) );
        $this->assertSame( ['member'], PageAccess::where( 'page_id', $page->id )->pluck( 'value' )->all() );

        $this->assertSame( 1, $this->setAccess( [$page->id], null ) );
        $this->assertFalse( PageAccess::where( 'page_id', $page->id )->exists() );
    }


    public function testMutationAppliesSelectedPages(): void
    {
        $pages = Page::whereIn( 'path', ['hidden', 'blog'] )->get();

        $this->assertSame( 2, $this->setAccess( $pages->modelKeys(), ['member'] ) );
        $this->assertSame( 2, PageAccess::whereIn( 'page_id', $pages->modelKeys() )->count() );
    }


    public function testMutationAppliesOneSubtree(): void
    {
        $root = Page::where( 'tag', 'root' )->firstOrFail();
        $count = Page::query()
            ->where( NestedSet::LFT, '>=', $root->getLft() )
            ->where( NestedSet::RGT, '<=', $root->getRgt() )
            ->count();

        $this->assertSame( $count, $this->setAccess( [$root->id], [], true ) );
        $this->assertSame( $count, PageAccess::count() );
    }


    public function testMutationRejectsMissingAccessArgument(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) {
                setPageAccess(id: $id)
            }
        ', ['id' => [$page->id]] )->assertGraphQLErrorMessage(
            'The access value must be provided explicitly.'
        );
    }


    public function testMutationRejectsMultipleRecursiveRoots(): void
    {
        $pages = Page::whereIn( 'path', ['hidden', 'blog'] )->get();

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!]) {
                setPageAccess(id: $id, access: $access, descendants: true)
            }
        ', ['id' => $pages->modelKeys(), 'access' => []] )->assertGraphQLErrorMessage(
            'Descendant access changes require exactly one root page.'
        );
    }


    public function testMutationRejectsOversizedLists(): void
    {
        $ids = array_map( strval(...), range( 1, Page::MAX_BULK + 1 ) );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!]) {
                setPageAccess(id: $id, access: $access)
            }
        ', ['id' => $ids, 'access' => []] );

        $response->assertGraphQLErrorMessage( 'Validation failed for the field [setPageAccess].' );
        $this->assertSame(
            'The id field must not have more than 1000 items.',
            $response->json( 'errors.0.extensions.validation.id.0' ),
        );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!]) {
                setPageAccess(id: $id, access: $access)
            }
        ', [
            'id' => [Page::where( 'path', 'hidden' )->firstOrFail()->id],
            'access' => array_map( fn( int $idx ) => 'access-' . $idx, range( 1, 251 ) ),
        ] );

        $response->assertGraphQLErrorMessage( 'Validation failed for the field [setPageAccess].' );
        $this->assertSame(
            'The access field must not have more than 250 items.',
            $response->json( 'errors.0.extensions.validation.access.0' ),
        );
    }


    public function testMutationRequiresAccessViewPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $this->user->cmsperms = ['page:view', 'page:publish'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!]) {
                setPageAccess(id: $id, access: $access)
            }
        ', ['id' => [$page->id], 'access' => []] )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testMutationRequiresPublishPermission(): void
    {
        $page = Page::where( 'path', 'hidden' )->firstOrFail();
        $this->user->cmsperms = ['page:view', 'access:view'];

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!]) {
                setPageAccess(id: $id, access: $access)
            }
        ', ['id' => [$page->id], 'access' => []] )
            ->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    /**
     * @param list<string> $ids
     * @param list<string>|null $access
     */
    private function setAccess( array $ids, ?array $access, bool $descendants = false ) : int
    {
        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $access: [String!], $descendants: Boolean) {
                setPageAccess(id: $id, access: $access, descendants: $descendants)
            }
        ', ['id' => $ids, 'access' => $access, 'descendants' => $descendants] );

        $response->assertGraphQLErrorFree();

        return (int) $response->json( 'data.setPageAccess' );
    }
}
