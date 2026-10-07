<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Ai;
use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\Page;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Resource;
use Aimeos\Cms\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Exceptions\OverloadedException;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Exceptions\RateLimitException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Database\Seeders\TestSeeder;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Nuwave\Lighthouse\Testing\MakesGraphQLRequests;
use Nuwave\Lighthouse\Testing\RefreshesSchemaCache;


class TranslatePageTest extends AiTestAbstract
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

        RateLimiter::clear( 'cms-translate:' . Tenancy::value() );
    }


    protected function tearDown(): void
    {
        config( ['cms.ai.ratelimit' => 60, 'queue.default' => 'sync'] );
        parent::tearDown();
    }


    public function testChunks()
    {
        $chunks = Ai::chunks( array_fill( 0, 120, 'text' ) );

        $this->assertSame( [50, 50, 20], array_map( 'count', $chunks ) );
        $this->assertSame( [2, 1], array_map( 'count', Ai::chunks( ['aa', 'bb', 'cc'], 50, 4 ) ) );
        $this->assertSame( [1, 1], array_map( 'count', Ai::chunks( ['aaaaa', 'b'], 50, 4 ) ) );
        $this->assertSame( [], Ai::chunks( [] ) );
    }


    public function testTranslateChunked()
    {
        $texts = array_map( fn( $i ) => 'text ' . $i, range( 1, 120 ) );
        $fake = Prisma::fake( array_map( fn( $chunk ) => TextResponse::fromTexts( array_map( 'strtoupper', $chunk ) ), Ai::chunks( $texts ) ) );

        $result = Ai::translate( $texts, 'de', 'en' );

        $this->assertCount( 3, $fake->calls() );
        $this->assertSame( array_map( 'strtoupper', $texts ), $result );
    }


    public function testTranslator()
    {
        $this->assertInstanceOf( \Closure::class, Ai::translator( $this->user ) );
        $this->assertNull( Ai::translator( $this->user( ['page:save', 'page:add'] ) ) );

        config( ['cms.ai.translate.provider' => null] );
        $this->assertNull( Ai::translator( $this->user ) );
        config( ['cms.ai.translate.provider' => 'deepl'] );
    }


    public function testJob()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $de = Page::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertCount( 1, $fake->calls() );
        $this->assertEquals( Sync::EDITOR, $de->latest->editor );
        $this->assertEquals( '[de] Title', $de->latest->data->title );
        $this->assertFalse( (bool) $de->stale );
    }


    public function testJobSameAsEditor()
    {
        $page = $this->page();
        $other = $this->page();

        $this->fake( $page );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->fake( $other );
        $preview = Resource::translation( $other->id, 'de', Ai::translator( $this->user ) );

        $de = Page::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertEquals( $preview['aux']['content'], $de->latest->aux->content );
        $this->assertEquals( $preview['hashes'], (array) $de->hashes );
    }


    public function testJobUntranslatedCopy()
    {
        $page = $this->page();
        $user = $this->user( ['page:save', 'page:add', 'page:view'] );
        $fake = Prisma::fake( [] );

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $user->id );

        $de = Page::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertCount( 0, $fake->calls() );
        $this->assertEquals( 'Title', $de->latest->data->title );
        $this->assertEquals( 'noperms1@testbench', $de->latest->editor );
        $this->assertTrue( (bool) $de->stale );
    }


    public function testJobPermissions()
    {
        $page = $this->page();

        // translating requires page:add to create the variant
        $job = $this->job( $page->id, 'de', $this->user( ['page:save', 'text:translate'] ) );
        $job->handle();

        $job->assertFailed();
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );

        $job = $this->job( $page->id, 'de', $this->user( ['page:add', 'text:translate'] ) );
        $job->handle();

        $job->assertFailed();

        // deleted users can't translate
        $job = new TranslatePage( $page->id, 'de', Tenancy::value(), 'unknown' );
        $job->withFakeQueueInteractions()->handle();

        $job->assertFailed();
    }


    public function testJobTenant()
    {
        $page = $this->page();
        $this->fake( $page );

        Tenancy::run( 'other', fn() => TranslatePage::dispatch( $page->id, 'de', 'test', $this->user->id ) );

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
        $this->assertEquals( 'test', PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->value( 'tenant_id' ) );
    }


    public function testJobRetries()
    {
        $page = $this->page();
        Prisma::fake( array_fill( 0, 4, new RateLimitException( 'Too many requests' ) ) );

        foreach( TranslatePage::BACKOFF as $delay )
        {
            $job = $this->job( $page->id, 'de' );
            $job->handle();
            $job->assertReleased( $delay );
        }

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertFailed();
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobRetriesConnection()
    {
        $page = $this->page();
        $error = new ConnectException( 'Timeout', new Request( 'POST', 'https://api.deepl.com' ) );
        $texts = $this->texts( $page );

        Prisma::fake( [$error, new OverloadedException( 'Overloaded' ), TextResponse::fromTexts( $texts )] );

        $job = $this->job( $page->id, 'de' );
        $job->handle();
        $job->assertReleased( TranslatePage::BACKOFF[0] );

        $job = $this->job( $page->id, 'de' );
        $job->handle();
        $job->assertReleased( TranslatePage::BACKOFF[1] );

        $job = $this->job( $page->id, 'de' );
        $job->handle();
        $job->assertNotFailed();
        $job->assertNotReleased();

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobFailsAtOnce()
    {
        $page = $this->page();
        Prisma::fake( [new PrismaException( 'Invalid API key' )] );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertFailed();
        $job->assertNotReleased();
    }


    public function testJobFailsInline()
    {
        $page = $this->page();
        Prisma::fake( [new RateLimitException( 'Too many requests' )] );

        $this->expectException( RateLimitException::class );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );
    }


    public function testJobThrottled()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $page = $this->page();
        $fake = $this->fake( $page );

        RateLimiter::hit( 'cms-translate:' . Tenancy::value(), 60 );
        RateLimiter::hit( 'cms-translate:' . Tenancy::value(), 60 );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertReleased();
        $job->assertNotFailed();
        $this->assertCount( 0, $fake->calls() );

        RateLimiter::clear( 'cms-translate:' . Tenancy::value() );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertNotReleased();
        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 1, RateLimiter::attempts( 'cms-translate:' . Tenancy::value() ) );
    }


    public function testJobUnique()
    {
        Queue::fake();
        $page = $this->page();

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );
        TranslatePage::dispatch( $page->id, 'fr', Tenancy::value(), $this->user->id );

        Queue::assertPushed( TranslatePage::class, 2 );
    }


    public function testJobProgress()
    {
        $page = $this->page();
        $this->fake( $page );

        TranslatePage::batch( 'batch1', 2 );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id, 'batch1' );

        $job = new TranslatePage( $page->id, 'xx', Tenancy::value(), $this->user->id, 'batch1' );
        $job->withFakeQueueInteractions()->handle();

        $this->assertSame( ['total' => 2, 'done' => 1, 'failed' => 1], TranslatePage::progress( 'batch1' ) );
        $this->assertNull( TranslatePage::progress( 'unknown' ) );
    }


    public function testGraphqlTranslatePage()
    {
        $page = $this->page();
        $this->fake( $page );

        $response = $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!, $lang: [String!]!) {
                translatePage(id: $id, lang: $lang) { id total }
            }
        ', ['id' => [$page->id], 'lang' => ['de']] )->assertGraphQLErrorFree();

        $batch = $response->json( 'data.translatePage.id' );
        $this->assertSame( 1, $response->json( 'data.translatePage.total' ) );

        $this->actingAs( $this->user )->graphQL( '
            query($batch: ID!) { translateProgress(batch: $batch) { total done failed } }
        ', ['batch' => $batch] )->assertJson( ['data' => ['translateProgress' => ['total' => 1, 'done' => 1, 'failed' => 0]]] );

        $this->assertEquals( '[de] Title', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testGraphqlTranslatePagePermission()
    {
        $this->actingAs( $this->user( ['page:view'] ) )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
    }


    public function testGraphqlTranslation()
    {
        $page = $this->page();
        $this->fake( $page );

        $response = $this->actingAs( $this->user )->graphQL( '
            query($id: ID!) { translation(id: $id, lang: "de") { data aux hashes translated latestId } }
        ', ['id' => $page->id] )->assertGraphQLErrorFree();

        $data = json_decode( $response->json( 'data.translation.data' ) );
        $aux = json_decode( $response->json( 'data.translation.aux' ) );

        $this->assertTrue( $response->json( 'data.translation.translated' ) );
        $this->assertEquals( '[de] Title', $data->title );
        $this->assertEquals( '[de] Hello', $aux->content[0]->data->text );
        $this->assertNull( $response->json( 'data.translation.latestId' ) );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testGraphqlTranslateFiles()
    {
        $file = File::firstOrFail();
        $file->forceFill( ['description' => ['en' => 'A test image']] )->saveQuietly();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "en"]) { id } }
        ', ['id' => [$file->id]] )->assertJson( ['data' => ['translateFiles' => [['id' => $file->id]]]] );

        $file = File::with( 'latest' )->findOrFail( $file->id );

        $this->assertCount( 1, $fake->calls() );
        $this->assertEquals( 'Ein Testbild', $file->latest->aux->description->de );
        $this->assertEquals( 'A test image', $file->latest->aux->description->en );
    }


    public function testMcpTranslatePage()
    {
        $page = $this->page();
        $this->fake( $page );

        $response = CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, [
            'id' => $page->id,
            'lang' => ['de'],
        ] );

        $response->assertOk()->assertSee( ['"done":1'] );
        $this->assertEquals( '[de] Title', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testMcpTranslatePagePermission()
    {
        $response = CmsServer::actingAs( $this->user( ['page:view'] ) )->tool( \Aimeos\Cms\Tools\TranslatePage::class, [
            'id' => $this->page()->id,
            'lang' => ['de'],
        ] );

        $response->assertHasErrors();
    }


    /**
     * Fakes the translation of the texts of the page.
     */
    protected function fake( Page $page ) : \Aimeos\Prisma\Providers\Fake
    {
        return Prisma::fake( [TextResponse::fromTexts( $this->texts( $page ) )] );
    }


    protected function job( string $id, string $lang, ?\App\Models\User $user = null ) : TranslatePage
    {
        $job = new TranslatePage( $id, $lang, Tenancy::value(), ( $user ?? $this->user )->id );
        return $job->withFakeQueueInteractions();
    }


    protected function page() : Page
    {
        return Resource::addPage( [
            'lang' => 'en', 'name' => 'Name', 'title' => 'Title', 'path' => 'tr-' . substr( md5( uniqid() ), 0, 8 ),
            'content' => [
                ['id' => 'el1', 'type' => 'text', 'data' => ['text' => 'Hello']],
                ['id' => 'el2', 'type' => 'heading', 'data' => ['title' => 'Heading', 'level' => 2]],
            ],
        ], $this->user, parent: Page::where( 'tag', 'root' )->firstOrFail()->id );
    }


    /**
     * Returns the translated texts of the page as returned by the provider.
     *
     * @return array<int, string>
     */
    protected function texts( Page $page ) : array
    {
        $texts = [];

        Resource::translation( $page->id, 'de', function( array $list ) use ( &$texts ) {
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
