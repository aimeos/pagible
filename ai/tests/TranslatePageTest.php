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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        RateLimiter::clear( 'cms-translate:' . sha1( (string) Tenancy::value() ) );
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
        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );

        $fake = Prisma::fake( [$responses[0], new RateLimitException( 'Too many requests' )] );

        try {
            Ai::retryable( fn() => Ai::translate( $texts, 'de', 'en' ), Tenancy::value() );
            $this->fail( 'Rate limit error not thrown' );
        } catch( RateLimitException $e ) {
            $this->assertCount( 2, $fake->calls() );
            $this->assertSame( 3, RateLimiter::attempts( $key ) );
        }

        // the retry only sends the chunks which haven't been translated yet
        $fake = Prisma::fake( [$responses[1], $responses[2]] );
        $result = Ai::retryable( fn() => Ai::translate( $texts, 'de', 'en' ), Tenancy::value() );

        $this->assertSame( array_map( 'strtoupper', $texts ), $result );
        $this->assertSame( [$chunks[1], $chunks[2]], array_map( fn( $call ) => $call['arguments'][0], $fake->calls() ) );
        $this->assertSame( 5, RateLimiter::attempts( $key ) );

        // the cached chunks are removed after the translation succeeded
        $fake = Prisma::fake( $responses );
        $this->assertSame( array_map( 'strtoupper', $texts ), Ai::translate( $texts, 'de', 'en' ) );
        $this->assertCount( 3, $fake->calls() );
    }


    public function testTranslateRetryable()
    {
        $texts = array_map( fn( $i ) => 'forget ' . $i, range( 1, 60 ) );
        $responses = array_map( fn( $chunk ) => TextResponse::fromTexts( $chunk ), Ai::chunks( $texts ) );

        // the chunks stay cached if the callback fails
        $fake = Prisma::fake( $responses );

        try {
            Ai::retryable( function() use ( $texts ) {
                Ai::translate( $texts, 'de', 'en' );
                throw new \RuntimeException( 'failed' );
            } );
            $this->fail( 'Exception not thrown' );
        } catch( \RuntimeException $e ) {
            $this->assertCount( 2, $fake->calls() );
        }

        // cached chunks are used and removed after the callback succeeded
        $fake = Prisma::fake( $responses );
        $this->assertSame( $texts, Ai::retryable( fn() => Ai::translate( $texts, 'de', 'en' ) ) );
        $this->assertCount( 0, $fake->calls() );

        Ai::translate( $texts, 'de', 'en' );
        $this->assertCount( 2, $fake->calls() );
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


    public function testJobRestoresUser()
    {
        $page = $this->page();
        $other = $this->page();
        $this->fake( $page );

        // long running queue workers mustn't keep the user for later jobs
        \Illuminate\Support\Facades\Auth::forgetUser();
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->assertFalse( \Illuminate\Support\Facades\Auth::hasUser() );

        // jobs running synchronously in a request keep the user of the request
        $current = new \App\Models\User( ['name' => 'Current', 'email' => 'current@testbench'] );
        \Illuminate\Support\Facades\Auth::setUser( $current );

        $this->fake( $other );
        TranslatePage::dispatch( $other->id, 'de', Tenancy::value(), $this->user->id );

        $this->assertSame( $current, \Illuminate\Support\Facades\Auth::user() );
        $this->assertTrue( Page::language( 'de' )->whereKey( [$page->id, $other->id] )->count() === 2 );

        \Illuminate\Support\Facades\Auth::forgetUser();
    }


    public function testJobForgetsCache()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', Ai::translator( $this->user ) );
        $this->assertCount( 1, $fake->calls() );

        // the job uses the cached chunks and removes them after it succeeded
        $fake = $this->fake( $page );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->assertCount( 0, $fake->calls() );
        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );

        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', Ai::translator( $this->user ) );
        $this->assertCount( 1, $fake->calls() );
    }


    public function testJobFailedKeepsCache()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', Ai::translator( $this->user ) );
        $this->assertCount( 1, $fake->calls() );

        // job fails before translating, cached chunks stay for retries
        $job = $this->job( $page->id, 'de', $this->user( ['page:save', 'text:translate'] ) );
        $job->handle();
        $job->assertFailed();

        $fake = Prisma::fake( [] );
        Sync::translate( Page::with( 'latest' )->findOrFail( $page->id ), null, 'de', Ai::translator( $this->user ) );
        $this->assertCount( 0, $fake->calls() );
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


    public function testJobTrashedPage()
    {
        $page = $this->page();
        $fake = Prisma::fake( [] );

        \Aimeos\Cms\Resource::drop( Page::class, [$page->id], $this->user );

        // pages moved to the trash after queuing are skipped without failing
        $job = $this->job( $page->id, 'de', $this->user );
        $job->handle();

        $job->assertNotFailed();
        $this->assertCount( 0, $fake->calls() );
        $this->assertFalse( PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
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


    public function testJobGuard()
    {
        $page = $this->page();
        $this->fake( $page );

        $user = $this->user;
        \Illuminate\Support\Facades\Auth::provider( 'cmstest', fn() => new class( $user ) implements \Illuminate\Contracts\Auth\UserProvider {
            public function __construct( private $user ) {}
            public function retrieveById( $id ) { return $id === 'cms-1' ? $this->user : null; }
            public function retrieveByToken( $id, $token ) { return null; }
            public function updateRememberToken( $user, $token ) {}
            public function retrieveByCredentials( array $credentials ) { return null; }
            public function validateCredentials( $user, array $credentials ) { return false; }
            public function rehashPasswordIfRequired( $user, array $credentials, bool $force = false ) {}
        } );

        $default = config( 'auth.defaults.guard' );
        config( ['auth.providers.cmstest' => ['driver' => 'cmstest'], 'auth.guards.cms' => ['driver' => 'session', 'provider' => 'cmstest']] );

        // the user is loaded by the provider of the guard active when the translation started
        \Illuminate\Support\Facades\Auth::shouldUse( 'cms' );
        $job = ( new TranslatePage( $page->id, 'de', Tenancy::value(), 'cms-1' ) )->withFakeQueueInteractions();
        \Illuminate\Support\Facades\Auth::shouldUse( $default );

        $job->handle();

        $this->assertSame( 'cms', $job->guard );
        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobUserModel()
    {
        $page = $this->page();
        $this->fake( $page );

        $this->actingAs( $this->user );
        $job = ( new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user->id ) )->withFakeQueueInteractions();
        $this->assertSame( get_class( $this->user ), $job->model );

        // users of another model with the same ID must not be used
        $job->model = \Illuminate\Auth\GenericUser::class;

        try {
            $job->handle();
        } catch( \Throwable $e ) {
        }

        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );

        $job->model = get_class( $this->user );
        $job->handle();

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
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

        RateLimiter::hit( 'cms-translate:' . sha1( (string) Tenancy::value() ), 60 );
        RateLimiter::hit( 'cms-translate:' . sha1( (string) Tenancy::value() ), 60 );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertReleased();
        $job->assertNotFailed();
        $this->assertCount( 0, $fake->calls() );
        $this->assertSame( 2, RateLimiter::attempts( 'cms-translate:' . sha1( (string) Tenancy::value() ) ) );

        RateLimiter::clear( 'cms-translate:' . sha1( (string) Tenancy::value() ) );

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertNotReleased();
        $this->assertCount( 1, $fake->calls() );
        $this->assertSame( 1, RateLimiter::attempts( 'cms-translate:' . sha1( (string) Tenancy::value() ) ) );
    }


    public function testJobThrottledReservesAll()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );

        RateLimiter::hit( $key, 60 );

        // the reservation of all calls exceeds the limit and is given back
        try {
            TranslatePage::reserve( Tenancy::value(), 2 );
            $this->fail( 'Throttled not thrown' );
        } catch( \Aimeos\Cms\Jobs\Throttled $e ) {
            $this->assertSame( 1, RateLimiter::attempts( $key ) );
        }

        TranslatePage::reserve( Tenancy::value(), 1 );
        $this->assertSame( 2, RateLimiter::attempts( $key ) );

        // more calls than the limit are reserved if no other calls were made
        RateLimiter::clear( $key );
        TranslatePage::reserve( Tenancy::value(), 5 );
        $this->assertSame( 5, RateLimiter::attempts( $key ) );

        // and the exceeding calls are taken from the following minutes
        $this->travel( 61 )->seconds();
        $this->assertFalse( RateLimiter::tooManyAttempts( $key, 2 ) );

        try {
            TranslatePage::reserve( Tenancy::value(), 1 );
            $this->fail( 'Throttled not thrown' );
        } catch( \Aimeos\Cms\Jobs\Throttled $e ) {
            $this->assertGreaterThan( 60, $e->delay );
        }

        $this->travel( 120 )->seconds();
        TranslatePage::reserve( Tenancy::value(), 1 );
        $this->assertSame( 1, RateLimiter::attempts( $key ) );
    }


    public function testJobThrottledAfterBurst()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $page = $this->page();
        $fake = $this->fake( $page );

        TranslatePage::reserve( Tenancy::value(), 6 );
        $this->travel( 61 )->seconds();

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertReleased();
        $this->assertCount( 0, $fake->calls() );

        \Illuminate\Support\Facades\Cache::forget( 'cms-translate:' . sha1( (string) Tenancy::value() ) . ':until' );
    }


    public function testJobQueueSkipsQueued()
    {
        Queue::fake();
        $page = $this->page();

        $this->assertSame( 1, TranslatePage::dispatchPending( [$page->id], ['de'], $this->user->id )['total'] );
        $batch = TranslatePage::dispatchPending( [$page->id], ['de', 'en'], $this->user->id );

        // "de" is already queued and "en" is the source language
        $this->assertSame( 0, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, 1 );
    }


    public function testJobQueueUniqueFor()
    {
        Queue::fake();

        $page = $this->page();
        $limit = config( 'cms.ai.ratelimit' );
        config( ['cms.locales' => ['en', 'de', 'fr'], 'cms.ai.ratelimit' => 1] );

        try {
            TranslatePage::dispatchPending( [$page->id], ['de', 'fr'], $this->user->id );
        } finally {
            config( ['cms.locales' => ['en', 'de'], 'cms.ai.ratelimit' => $limit] );
        }

        // locks of lost jobs expire after the jobs would have been retried
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->uniqueFor === $job->delay + 3600 * TranslatePage::HOURS + 600 );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->lang === 'fr' && $job->delay === 60 );
    }


    public function testJobQueueTooMany()
    {
        Queue::fake();

        $page = $this->page();
        $limit = config( 'cms.ai.ratelimit' );
        $langs = array_map( fn( $idx ) => sprintf( 'de-%03d', $idx ), range( 1, 60 * TranslatePage::HOURS + 1 ) );
        config( ['cms.locales' => $langs, 'cms.ai.ratelimit' => 1] );

        try {
            $this->expectException( \Aimeos\Cms\Exception::class );
            TranslatePage::dispatchPending( [$page->id], $langs, $this->user->id );
        } finally {
            config( ['cms.locales' => ['en', 'de'], 'cms.ai.ratelimit' => $limit] );
            Queue::assertNothingPushed();
        }
    }


    public function testJobRetryUntilDelay()
    {
        $job = ( new TranslatePage( 'page-1', 'de', Tenancy::value(), $this->user->id ) )->delay( 3600 );
        $hours = TranslatePage::HOURS;

        $this->assertEqualsWithDelta( now()->addHours( $hours + 1 )->getTimestamp(), $job->retryUntil()->getTimestamp(), 5 );
    }


    public function testJobQueueChunks()
    {
        Queue::fake();

        $page = $this->page();
        $langs = array_map( fn( $idx ) => sprintf( 'de-%03d', $idx ), range( 1, 600 ) );
        config( ['cms.locales' => $langs] );

        try {
            $batch = TranslatePage::dispatchPending( [$page->id], [...$langs, 'de-001'], $this->user->id );
        } finally {
            config( ['cms.locales' => ['en', 'de']] );
        }

        $this->assertSame( 600, $batch['total'] );
        $this->assertSame( ['total' => 600, 'done' => 0, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );
        Queue::assertPushed( TranslatePage::class, 600 );
    }


    public function testJobQueueInvalidLang()
    {
        $this->expectException( \Aimeos\Cms\Exception::class );
        $this->expectExceptionMessage( 'Invalid language code "xx"' );

        TranslatePage::dispatchPending( ['page-1'], ['de', 'xx'], $this->user->id );
    }


    public function testJobQueueVariantLangNotConfigured()
    {
        Queue::fake();

        $page = $this->page();
        Resource::translatePage( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['lang' => 'fr', 'stale' => true] );

        $batch = TranslatePage::dispatchPending( [$page->id], ['fr'], $this->user->id );
        $this->assertSame( 1, $batch['total'] );
    }


    public function testJobQueueVariantLangNotConfiguredNoAdd()
    {
        Queue::fake();

        $page = $this->page();
        $other = Page::where( 'id', '!=', $page->id )->whereNull( 'deleted_at' )->firstOrFail();
        Resource::translatePage( $page->id, 'de', $this->user );
        PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->update( ['lang' => 'fr', 'stale' => true] );

        // unconfigured languages of other pages mustn't become available for all pages
        $batch = TranslatePage::dispatchPending( [$page->id, $other->id], ['fr'], $this->user->id );

        $this->assertSame( 1, $batch['total'] );
        Queue::assertPushed( TranslatePage::class, fn( $job ) => $job->id === $page->id );
        Queue::assertNotPushed( TranslatePage::class, fn( $job ) => $job->id === $other->id );
    }


    public function testJobQueueNoLocales()
    {
        Queue::fake();
        config( ['cms.locales' => []] );

        try {
            $batch = TranslatePage::dispatchPending( [$this->page()->id], ['es'], $this->user->id );
            $this->assertSame( 1, $batch['total'] );

            $this->expectException( \Aimeos\Cms\Exception::class );
            TranslatePage::dispatchPending( ['page-1'], ['no language'], $this->user->id );
        } finally {
            config( ['cms.locales' => ['en', 'de']] );
        }
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


    public function testJobReleasesLock()
    {
        $page = $this->page();
        $this->fake( $page );

        TranslatePage::dispatchPending( [$page->id], ['de'], $this->user->id );

        // finished translations can be queued again
        $lock = new \Illuminate\Bus\UniqueLock( app( \Illuminate\Contracts\Cache\Repository::class ) );
        $job = new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user->id );

        $this->assertTrue( $lock->acquire( $job ) );
        $lock->release( $job );
    }


    public function testJobProgress()
    {
        $page = $this->page();
        $this->fake( $page );

        config( ['cms.queue.connection' => 'database'] );

        try
        {
            $batch = TranslatePage::dispatchPending( [$page->id], ['de'], $this->user->id );
            $this->assertSame( ['total' => 1, 'done' => 0, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );

            Artisan::call( 'queue:work', ['connection' => 'database', '--queue' => config( 'cms.queue.name' ) ?: 'default', '--stop-when-empty' => true,
                // the worker stops after each job if the test process already uses more memory
                '--memory' => (int) ceil( memory_get_usage( true ) / 1048576 ) + 256] );
        }
        finally
        {
            config( ['cms.queue.connection' => null] );
        }

        $this->assertSame( ['total' => 1, 'done' => 1, 'failed' => 0], TranslatePage::progress( $batch['id'] ) );
        $this->assertEquals( '[de] Title', Page::language( 'de' )->findOrFail( $page->id )->latest->data->title );
        $this->assertNull( TranslatePage::progress( 'unknown' ) );

        // batches of other tenants and of the application aren't reported
        $this->assertNull( Tenancy::run( 'other', fn() => TranslatePage::progress( $batch['id'] ) ) );
        $this->assertNull( TranslatePage::progress( \Illuminate\Support\Facades\Bus::batch( [] )->name( 'app' )->dispatch()->id ) );
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


    public function testGraphqlTranslatePageFilterTooMany()
    {
        Queue::fake();

        $page = $this->page( ['tag' => 'many'] );
        $node = (array) DB::table( 'cms_pages' )->where( 'id', $page->id )->first();
        $variant = (array) DB::table( 'cms_page_variants' )->where( 'page_id', $page->id )->first();
        $nodes = $variants = [];

        // the copies are outside of the tree, only the number of matching pages counts
        for( $i = 1; $i <= Page::MAX_BULK; $i++ )
        {
            $id = (string) Str::uuid();
            $nodes[] = ['id' => $id, '_lft' => 100000 + 2 * $i, '_rgt' => 100001 + 2 * $i] + $node;
            $variants[] = ['id' => (string) Str::uuid(), 'page_id' => $id, 'path' => 'many-' . $i] + $variant;
        }

        foreach( array_chunk( $nodes, 100 ) as $chunk ) {
            DB::table( 'cms_pages' )->insert( $chunk );
        }

        foreach( array_chunk( $variants, 50 ) as $chunk ) {
            DB::table( 'cms_page_variants' )->insert( $chunk );
        }

        $this->actingAs( $this->user )->graphQL( '
            mutation($filter: PageFilter, $lang: [String!]!) {
                translatePage(filter: $filter, lang: $lang) { id total }
            }
        ', ['filter' => ['tag' => 'many'], 'lang' => ['de']] )
            ->assertGraphQLErrorMessage( 'The filter matches 1001 pages, no more than 1000 pages can be translated at once' );

        Queue::assertNothingPushed();
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
        $this->assertSame( 2, RateLimiter::attempts( 'cms-translate:' . sha1( (string) Tenancy::value() ) ) );

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

        $key = 'cms-translate:' . sha1( (string) Tenancy::value() );
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


    public function testMcpTranslatePageSkipped()
    {
        $page = $this->page();
        $trashed = $this->page();
        Resource::drop( Page::class, [$trashed->id], $this->user );

        // source language and trashed pages aren't translated
        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, [
            'id' => $page->id,
            'lang' => ['en'],
        ] )->assertOk()->assertSee( ['"total":0'] );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, [
            'id' => $trashed->id,
            'lang' => ['de'],
        ] )->assertSee( ['Page not found'] );
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
