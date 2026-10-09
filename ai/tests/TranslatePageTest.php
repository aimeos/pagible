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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
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
        config( ['cms.ai.ratelimit' => 60, 'cms.ai.maxtranslate' => 100, 'queue.default' => 'sync'] );
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


    public function testTranslateRetryCached()
    {
        $texts = array_map( fn( $i ) => 'retry ' . $i, range( 1, 120 ) );
        $chunks = Ai::chunks( $texts );
        $responses = array_map( fn( $chunk ) => TextResponse::fromTexts( array_map( 'strtoupper', $chunk ) ), $chunks );
        $calls = [];

        $translator = Ai::translator( $this->user, function( int $num ) use ( &$calls ) {
            $calls[] = $num;
        } );
        $this->assertInstanceOf( \Closure::class, $translator );

        $fake = Prisma::fake( [$responses[0], new RateLimitException( 'Too many requests' )] );

        try {
            $translator( $texts, 'de', 'en', '' );
            $this->fail( 'Rate limit error not thrown' );
        } catch( RateLimitException $e ) {
            $this->assertCount( 2, $fake->calls() );
            $this->assertSame( [3], $calls );
        }

        // the retry only sends the chunks which haven't been translated yet
        $fake = Prisma::fake( [$responses[1], $responses[2]] );
        $result = $translator( $texts, 'de', 'en', '' );

        $this->assertSame( array_map( 'strtoupper', $texts ), $result );
        $this->assertSame( [$chunks[1], $chunks[2]], array_map( fn( $call ) => $call['arguments'][0], $fake->calls() ) );
        $this->assertSame( [3, 2], $calls );

        // all chunks are cached now
        $fake = Prisma::fake( [] );
        $this->assertSame( array_map( 'strtoupper', $texts ), $translator( $texts, 'de', 'en', '' ) );
        $this->assertCount( 0, $fake->calls() );
        $this->assertSame( [3, 2], $calls );
    }


    public function testTranslateForget()
    {
        $texts = array_map( fn( $i ) => 'forget ' . $i, range( 1, 60 ) );
        $fake = Prisma::fake( array_map( fn( $chunk ) => TextResponse::fromTexts( $chunk ), Ai::chunks( $texts ) ) );

        Ai::translate( $texts, 'de', 'en', null, null, $keys );

        $this->assertCount( 2, $keys );
        $this->assertTrue( \Illuminate\Support\Facades\Cache::has( $keys[0] ) );
        $this->assertTrue( \Illuminate\Support\Facades\Cache::has( $keys[1] ) );

        Ai::forget( $keys );

        $this->assertFalse( \Illuminate\Support\Facades\Cache::has( $keys[0] ) );
        $this->assertFalse( \Illuminate\Support\Facades\Cache::has( $keys[1] ) );
    }


    public function testTranslateCacheScope()
    {
        $texts = ['scope text'];
        $fake = Prisma::fake( array_fill( 0, 5, TextResponse::fromTexts( ['translated'] ) ) );

        Ai::translate( $texts, 'de', 'en' );
        Ai::translate( $texts, 'de', 'en' );
        $this->assertCount( 1, $fake->calls() );

        Ai::translate( $texts, 'fr', 'en' );
        Ai::translate( $texts, 'de', null );
        Ai::translate( $texts, 'de', 'en', 'Other context' );
        Tenancy::run( 'other', fn() => Ai::translate( $texts, 'de', 'en' ) );
        $this->assertCount( 5, $fake->calls() );
    }


    public function testTranslateCacheDisabled()
    {
        config( ['cms.ai.translatettl' => 0] );

        try
        {
            $fake = Prisma::fake( [TextResponse::fromTexts( ['eins'] ), TextResponse::fromTexts( ['zwei'] )] );

            $this->assertSame( ['eins'], Ai::translate( ['disabled'], 'de' ) );
            $this->assertSame( ['zwei'], Ai::translate( ['disabled'], 'de' ) );
            $this->assertCount( 2, $fake->calls() );
        }
        finally
        {
            config( ['cms.ai.translatettl' => 3600] );
        }
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


    public function testJobForgetsCache()
    {
        $page = $this->page();
        $fake = $this->fake( $page );
        $keys = [];

        $translator = Ai::translator( $this->user, null, function( array $list ) use ( &$keys ) {
            $keys = $list;
        } );
        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', $translator );

        $this->assertCount( 1, $fake->calls() );
        $this->assertNotEmpty( $keys );
        $this->assertTrue( \Illuminate\Support\Facades\Cache::has( $keys[0] ) );

        // the job uses the cached chunks and removes them after it succeeded
        $fake = Prisma::fake( [] );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->assertCount( 0, $fake->calls() );
        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
        $this->assertFalse( \Illuminate\Support\Facades\Cache::has( $keys[0] ) );
    }


    public function testJobFailedKeepsCache()
    {
        $page = $this->page();
        $texts = $this->texts( $page );
        $keys = [];

        $translator = Ai::translator( $this->user, null, function( array $list ) use ( &$keys ) {
            $keys = $list;
        } );

        $fake = Prisma::fake( [TextResponse::fromTexts( $texts )] );
        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', $translator );

        // job fails before translating, cached chunks stay for retries
        $job = $this->job( $page->id, 'de', $this->user( ['page:save', 'text:translate'] ) );
        $job->handle();

        $job->assertFailed();
        $this->assertTrue( \Illuminate\Support\Facades\Cache::has( $keys[0] ) );
    }


    public function testJobSameAsEditor()
    {
        $page = $this->page();
        $other = $this->page();

        $this->fake( $page );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->fake( $other );
        $preview = Sync::translate( Page::with( 'latest' )->findOrFail( $other->id ), null, 'de', Ai::translator( $this->user ) );

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
        Prisma::fake( [new RateLimitException( 'Too many requests' )] );

        $job = $this->job( $page->id, 'de' );

        try {
            $job->handle();
            $this->fail( 'Rate limit error not thrown for retrying' );
        } catch( RateLimitException $e ) {
            $job->assertNotFailed();
            $job->assertNotReleased();
        }

        $this->assertSame( [10, 60, 300], $job->backoff );
        $this->assertSame( 4, $job->maxExceptions );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobRetriesConnection()
    {
        $page = $this->page();
        $error = new ConnectException( 'Timeout', new Request( 'POST', 'https://api.deepl.com' ) );
        $texts = $this->texts( $page );

        Prisma::fake( [$error, new OverloadedException( 'Overloaded' ), TextResponse::fromTexts( $texts )] );

        foreach( [ConnectException::class, OverloadedException::class] as $class )
        {
            try {
                $this->job( $page->id, 'de' )->handle();
                $this->fail( $class . ' not thrown for retrying' );
            } catch( \Throwable $e ) {
                $this->assertInstanceOf( $class, $e );
            }
        }

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
        $this->assertSame( 2, RateLimiter::attempts( 'cms-translate:' . Tenancy::value() ) );

        RateLimiter::clear( 'cms-translate:' . Tenancy::value() );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertNotReleased();
        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 1, RateLimiter::attempts( 'cms-translate:' . Tenancy::value() ) );
    }


    public function testJobThrottledReservesAll()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $key = 'cms-translate:' . Tenancy::value();
        $method = new \ReflectionMethod( TranslatePage::class, 'throttle' );
        $job = $this->job( 'page-id', 'de' );

        RateLimiter::hit( $key, 60 );

        // the reservation of all calls exceeds the limit and is given back
        try {
            $method->invoke( $job, 2 );
            $this->fail( 'Throttled not thrown' );
        } catch( \Aimeos\Cms\Jobs\Throttled $e ) {
            $this->assertSame( 1, RateLimiter::attempts( $key ) );
        }

        $method->invoke( $job, 1 );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );

        // more calls than the limit are reserved if no other calls were made
        RateLimiter::clear( $key );
        $method->invoke( $job, 5 );
        $this->assertSame( 5, RateLimiter::attempts( $key ) );
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


    public function testJobQueueSkipsQueued()
    {
        Queue::fake();
        $page = $this->page();

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );
        $batch = TranslatePage::dispatchBatch( [$page->id], ['de', 'en'], $this->user->id );

        $this->assertSame( 1, $batch['total'] );
        $this->assertSame( ['total' => 1, 'done' => 0, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );
        Queue::assertPushed( TranslatePage::class, 2 );
    }


    public function testJobQueueChunks()
    {
        Queue::fake();

        $ids = array_map( fn( $idx ) => 'page-' . $idx, range( 1, 300 ) );
        $batch = TranslatePage::dispatchBatch( $ids, ['de', 'en', 'de'], $this->user->id );

        $this->assertSame( 600, $batch['total'] );
        $this->assertSame( ['total' => 600, 'done' => 0, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );
        Queue::assertPushed( TranslatePage::class, 600 );
    }


    public function testJobQueueInvalidLang()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Invalid language code "xx"' );

        TranslatePage::dispatchBatch( ['page-1'], ['de', 'xx'], $this->user->id );
    }


    public function testJobQueueVariantLangNotConfigured()
    {
        Queue::fake();

        $page = \Aimeos\Cms\Models\Page::firstOrFail();
        \Aimeos\Cms\Models\PageVariant::whereKey( $page->variant_id )->update( ['lang' => 'fr'] );

        $batch = TranslatePage::dispatchBatch( ['page-1'], ['fr'], $this->user->id );
        $this->assertSame( 1, $batch['total'] );
    }


    public function testJobQueueNoLocales()
    {
        Queue::fake();
        config( ['cms.locales' => []] );

        try {
            $batch = TranslatePage::dispatchBatch( ['page-1'], ['es'], $this->user->id );
            $this->assertSame( 1, $batch['total'] );

            $this->expectException( \Aimeos\Cms\Exception::class );
            TranslatePage::dispatchBatch( ['page-1'], ['no language'], $this->user->id );
        } finally {
            config( ['cms.locales' => ['en', 'de']] );
        }
    }


    public function testJobQueueTooMany()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'No more than 1000 items' );

        TranslatePage::dispatchBatch( array_map( fn( $idx ) => 'page-' . $idx, range( 1, 501 ) ), ['de', 'en'], $this->user->id );
    }


    public function testJobQueuePending()
    {
        Queue::fake();

        $pages = [$this->page(), $this->page(), $this->page(), $this->page()];
        $ids = array_map( fn( $page ) => (string) $page->id, $pages );

        foreach( array_slice( $ids, 1 ) as $id ) {
            Resource::translatePage( $id, 'de', $this->user );
        }

        // up to date, stale and trashed variants in "de"
        PageVariant::where( 'page_id', $ids[1] )->where( 'lang', 'de' )->update( ['stale' => false] );
        PageVariant::where( 'page_id', $ids[2] )->where( 'lang', 'de' )->update( ['stale' => true] );
        PageVariant::where( 'page_id', $ids[3] )->where( 'lang', 'de' )->update( ['stale' => true, 'deleted_at' => now()] );

        $batch = TranslatePage::dispatchPending( $ids, ['de', 'en'], $this->user->id );

        $this->assertSame( 2, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $ids[0] && $job->lang === 'de' );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $ids[2] && $job->lang === 'de' );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->lang === 'en' );
    }


    public function testJobQueuePendingNoAdd()
    {
        Queue::fake();

        $missing = $this->page();
        $stale = $this->page();

        Resource::translatePage( $stale->id, 'de', $this->user );
        PageVariant::where( 'page_id', $stale->id )->where( 'lang', 'de' )->update( ['stale' => true] );

        $batch = TranslatePage::dispatchPending( [$missing->id, $stale->id], ['de'], $this->user->id, false );

        $this->assertSame( 1, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $stale->id );
    }


    public function testJobQueuePendingTrashedPage()
    {
        Queue::fake();

        $page = $this->page();
        Resource::drop( Page::class, [$page->id], $this->user );

        $this->assertSame( 0, TranslatePage::dispatchPending( [$page->id], ['de'], $this->user->id )['total'] );
    }


    public function testJobFailedLogs()
    {
        $page = $this->page();

        Log::shouldReceive( 'error' )->once()->with( 'Page translation failed', \Mockery::on( fn( $ctx ) => $ctx['page'] === $page->id && $ctx['error'] === 'Retries exceeded' ) );
        ( new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user->id ) )->failed( new \RuntimeException( 'Retries exceeded' ) );
    }


    public function testJobProgress()
    {
        $page = $this->page();
        $this->fake( $page );

        config( ['cms.queue.connection' => 'database'] );

        try
        {
            $batch = TranslatePage::dispatchBatch( [$page->id], ['de', 'en'], $this->user->id );
            $this->assertSame( ['total' => 2, 'done' => 0, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );

            Artisan::call( 'queue:work', ['connection' => 'database', '--queue' => config( 'cms.queue.name' ) ?: 'default', '--stop-when-empty' => true,
                // the worker stops after each job if the test process already uses more memory
                '--memory' => (int) ceil( memory_get_usage( true ) / 1048576 ) + 256] );
        }
        finally
        {
            config( ['cms.queue.connection' => null] );
        }

        $this->assertSame( ['total' => 2, 'done' => 1, 'failed' => 1], TranslatePage::progress( $batch['id'] ) );
        $this->assertEquals( '[de] Title', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
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


    public function testGraphqlTranslatePageFilter()
    {
        Queue::fake();

        $pages = [$this->page( ['tag' => 'trx'] ), $this->page( ['tag' => 'trx'] ), $this->page()];

        $this->actingAs( $this->user )->graphQL( '
            mutation($filter: PageFilter, $lang: [String!]!) {
                translatePage(filter: $filter, lang: $lang) { id total }
            }
        ', ['filter' => ['tag' => 'trx'], 'lang' => ['de', 'en']] )
            ->assertGraphQLErrorFree()
            ->assertJsonPath( 'data.translatePage.total', 2 );

        Queue::assertPushed( TranslatePage::class, 2 );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->id === $pages[2]->id );
    }


    public function testGraphqlTranslatePageFilterState()
    {
        Queue::fake();

        $missing = $this->page();
        $stale = $this->page();

        Resource::translatePage( $stale->id, 'de', $this->user );
        PageVariant::where( 'page_id', $stale->id )->where( 'lang', 'de' )->update( ['stale' => true] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($filter: PageFilter, $lang: [String!]!) {
                translatePage(filter: $filter, filter_lang: "de", lang: $lang) { id total }
            }
        ', ['filter' => ['translation' => 'stale'], 'lang' => ['de']] )
            ->assertGraphQLErrorFree()
            ->assertJsonPath( 'data.translatePage.total', 1 );

        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $stale->id );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->id === $missing->id );
    }


    public function testGraphqlTranslatePageNoPages()
    {
        $this->actingAs( $this->user )->graphQL( '
            mutation { translatePage(lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Pass the IDs of the pages or a filter' );
    }


    public function testGraphqlTranslatePagePermission()
    {
        $this->actingAs( $this->user( ['page:view'] ) )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
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


    public function testGraphqlTranslateFilesBatched()
    {
        $files = File::with( 'latest' )->take( 2 )->get();

        foreach( $files as $file )
        {
            $file->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
            $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();
        }

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] ), TextResponse::fromTexts( ['Une image de test'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "fr"]) { id } }
        ', ['id' => $files->pluck( 'id' )->all()] )->assertGraphQLErrorFree();

        // one call per language, identical descriptions are sent once
        $this->assertCount( 2, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( 'cms-translate:' . Tenancy::value() ) );

        foreach( $files as $file )
        {
            $file = File::with( 'latest' )->findOrFail( $file->id );

            $this->assertEquals( 'Ein Testbild', $file->latest->aux->description->de );
            $this->assertEquals( 'Une image de test', $file->latest->aux->description->fr );
        }
    }


    public function testGraphqlTranslateFilesMaximum()
    {
        config( ['cms.ai.maxtranslate' => 3] );

        $fake = Prisma::fake( [] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "fr"]) { id } }
        ', ['id' => File::take( 2 )->pluck( 'id' )->all()] )
            ->assertGraphQLErrorMessage( 'No more than 3 file translations (files × languages) may be requested at once' );

        $this->assertCount( 0, $fake->calls() );
    }


    public function testGraphqlTranslateFilesThrottled()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $key = 'cms-translate:' . Tenancy::value();
        $file = File::firstOrFail();
        $file->forceFill( ['lang' => 'en', 'description' => ['en' => 'A test image']] )->saveQuietly();
        $file->latest?->forceFill( ['aux' => ['description' => ['en' => 'A test image']]] )->saveQuietly();
        $latestId = $file->latest_id;

        RateLimiter::hit( $key, 60 );
        RateLimiter::hit( $key, 60 );

        $fake = Prisma::fake( [TextResponse::fromTexts( ['Ein Testbild'] )] );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de"]) { id } }
        ', ['id' => [$file->id]] )->assertGraphQLErrorMessage( 'Too many translations, please try again later' );

        // the reservation is given back and nothing is translated or saved
        $this->assertCount( 0, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );
        $this->assertSame( $latestId, File::findOrFail( $file->id )->latest_id );

        RateLimiter::clear( $key );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de"]) { id } }
        ', ['id' => [$file->id]] )->assertJson( ['data' => ['translateFiles' => [['id' => $file->id]]]] );

        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 1, RateLimiter::attempts( $key ) );
        $this->assertEquals( 'Ein Testbild', File::with( 'latest' )->findOrFail( $file->id )->latest->aux->description->de );
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


    protected function page( array $data = [] ) : Page
    {
        return Resource::addPage( $data + [
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

        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', function( array $list ) use ( &$texts ) {
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
