<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Translation\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


/**
 * Shared setup and helpers of the translation tests.
 */
abstract class TranslateTestAbstract extends AiTestAbstract
{
    use CmsWithMigrations;
    use MakesGraphQLRequests;
    use RefreshDatabase;
    use RefreshesSchemaCache;

    protected $seeder = TestSeeder::class;
    private int $users = 0;


    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        $app['config']->set( 'auth.providers.users.model', \App\Models\User::class );
        $app['config']->set( 'lighthouse.schema_path', __DIR__ . '/default-schema.graphql' );
        $app['config']->set( 'lighthouse.namespaces.models', ['App\Models', 'Aimeos\\Cms\\Models'] );
        $app['config']->set( 'lighthouse.namespaces.mutations', ['Aimeos\\Cms\\GraphQL\\Mutations'] );
        $app['config']->set( 'lighthouse.namespaces.directives', ['Aimeos\\Cms\\GraphQL\\Directives'] );
    }


    protected function getPackageProviders( $app )
    {
        return array_merge( parent::getPackageProviders( $app ), [
            'Aimeos\Cms\McpServiceProvider',
        ] );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->bootRefreshesSchemaCache();

        $this->user = \App\Models\User::forceCreate( [
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
            'cmsperms' => \Aimeos\Cms\Permission::all(),
        ] );

        RateLimiter::clear( 'cms-translate:' . sha1( (string) Tenancy::value() ) );
    }


    protected function tearDown(): void
    {
        config( ['cms.ai.ratelimit' => 60, 'cms.locales' => ['en', 'de'], 'queue.default' => 'sync'] );
        parent::tearDown();
    }


    /**
     * Fakes the translation of the texts of the page.
     */
    protected function fake( PageView $page ) : \Aimeos\Prisma\Providers\Fake
    {
        return Prisma::fake( [TextResponse::fromTexts( $this->texts( $page ) )] );
    }


    protected function job( string $id, string $lang, ?\App\Models\User $user = null ) : TranslatePage
    {
        $job = new TranslatePage( $id, $lang, Tenancy::value(), $user ?? $this->user , 'web' );
        return $job->withFakeQueueInteractions();
    }


    protected function page( array $data = [] ) : PageView
    {
        return Pages::add( $data + [
            'lang' => 'en', 'name' => 'Name', 'title' => 'Title', 'path' => 'tr-' . substr( md5( uniqid() ), 0, 8 ),
            'content' => [
                ['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']],
                ['id' => 'el2', 'type' => 'heading', 'data' => ['title' => 'Heading', 'level' => 2]],
            ],
        ], $this->user, parent: PageView::where( 'tag', 'root' )->firstOrFail()->id );
    }


    /**
     * Returns the translated texts of the page as returned by the provider.
     *
     * @return array<int, string>
     */
    protected function texts( PageView $page ) : array
    {
        $texts = [];

        Sync::translate( PageView::with( 'latest' )->findOrFail( $page->id ), null, 'de', function( array $list ) use ( &$texts ) {
            return $texts = array_map( fn( $text ) => '[de] ' . $text, $list );
        } );

        return $texts;
    }


    protected function user( array $perms ) : \App\Models\User
    {
        return \App\Models\User::forceCreate( [
            'name' => 'No perms',
            'email' => 'noperms' . ( ++$this->users ) . '@testbench',
            'password' => 'secret',
            'cmsperms' => $perms,
        ] );
    }
}
