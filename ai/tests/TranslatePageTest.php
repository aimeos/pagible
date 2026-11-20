<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Cms\Translation;
use Aimeos\Cms\Translation\Limit;
use Aimeos\Cms\Translation\Translator;
use Aimeos\Cms\Jobs\TranslatePage;
use Aimeos\Cms\Mcp\CmsServer;
use Aimeos\Cms\Models\File;
use Aimeos\Cms\Models\PageView;
use Aimeos\Cms\Models\PageVariant;
use Aimeos\Cms\Facades\Pages;
use Aimeos\Cms\Translation\Sync;
use Aimeos\Cms\Tenancy;
use Aimeos\Prisma\Exceptions\OverloadedException;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Exceptions\RateLimitException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;


class TranslatePageTest extends TranslateTestAbstract
{
    public function testJob()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user , 'web' );

        $de = PageView::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertCount( 1, $fake->calls() );
        $this->assertEquals( Sync::EDITOR, $de->latest->editor );
        $this->assertEquals( '[de] Title', $de->latest->data->title );
        $this->assertFalse( (bool) $de->stale );
    }


    public function testJobSystem()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        // jobs queued inside Permission::system() run without user in the queue worker
        \Illuminate\Support\Facades\Queue::fake();
        \Aimeos\Cms\Permission::system( fn() => Translation\Pages::dispatch( [$page->id], ['de'], null ) );
        $job = \Illuminate\Support\Facades\Queue::pushed( TranslatePage::class )->first();
        $job = unserialize( serialize( $job ) )->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotFailed();
        $de = PageView::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertCount( 1, $fake->calls() );
        $this->assertEquals( Sync::EDITOR, $de->latest->editor );
        $this->assertEquals( '[de] Title', $de->latest->data->title );

        // translations without user can't be started outside Permission::system()
        $this->expectException( \Aimeos\Cms\Exceptions\PermissionException::class );
        Translation\Pages::dispatch( [$page->id], ['fr'], null );
    }


    public function testJobRestoresUser()
    {
        $page = $this->page();
        $other = $this->page();
        $this->fake( $page );

        // long running queue workers mustn't keep the user for later jobs
        \Illuminate\Support\Facades\Auth::forgetUser();
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user , 'web' );

        $this->assertFalse( \Illuminate\Support\Facades\Auth::hasUser() );

        // jobs running synchronously in a request keep the user of the request
        $current = new \App\Models\User( ['name' => 'Current', 'email' => 'current@testbench'] );
        \Illuminate\Support\Facades\Auth::setUser( $current );

        $this->fake( $other );
        TranslatePage::dispatch( $other->id, 'de', Tenancy::value(), $this->user , 'web' );

        $this->assertSame( $current, \Illuminate\Support\Facades\Auth::user() );
        $this->assertTrue( PageView::language( 'de' )->whereKey( [$page->id, $other->id] )->count() === 2 );

        \Illuminate\Support\Facades\Auth::forgetUser();
    }


    public function testJobSameAsEditor()
    {
        $page = $this->page();
        $other = $this->page();

        $this->fake( $page );
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user , 'web' );

        $this->fake( $other );
        $preview = Sync::translate( PageView::with( 'latest' )->findOrFail( $other->id ), null, 'de', Translator::for( $this->user ) );

        $de = PageView::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertEquals( $preview['aux']['content'], $de->latest->aux->content );
        $this->assertEquals( $preview['hashes'], (array) $de->hashes );
    }


    public function testJobUntranslatedCopy()
    {
        $page = $this->page();
        $user = $this->user( ['page:save', 'page:add', 'page:view'] );
        $fake = Prisma::fake( [] );

        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $user , 'web' );

        $de = PageView::language( 'de' )->with( 'latest' )->findOrFail( $page->id );

        $this->assertCount( 0, $fake->calls() );
        $this->assertEquals( 'Title', $de->latest->data->title );
        $this->assertEquals( 'noperms1@testbench', $de->latest->editor );
        $this->assertTrue( (bool) $de->stale );
    }


    public function testJobTrashedPage()
    {
        $page = $this->page();
        $fake = Prisma::fake( [] );

        Pages::dropMany( [$page->id], $this->user );

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
        $deleted = ( clone $this->user )->forceFill( ['id' => -1] );
        $job = new TranslatePage( $page->id, 'de', Tenancy::value(), $deleted , 'web' );
        $job->withFakeQueueInteractions()->handle();

        $job->assertFailed();
    }


    public function testJobLanguagePermission()
    {
        $page = $this->page();
        $fake = $this->fake( $page );
        $user = $this->user( \Aimeos\Cms\Permission::all() );
        \Aimeos\Cms\Permission::set( $user, \Aimeos\Cms\Permission::assigned( $user ), ['fr'] );

        $job = $this->job( $page->id, 'de', $user );
        $job->handle();

        $job->assertFailed();
        $this->assertCount( 0, $fake->calls() );
        $this->assertFalse( PageVariant::withTrashed()->where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testGraphqlTranslatePageLanguagePermission()
    {
        $user = $this->user( \Aimeos\Cms\Permission::all() );
        \Aimeos\Cms\Permission::set( $user, \Aimeos\Cms\Permission::assigned( $user ), ['fr'] );

        $this->actingAs( $user )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions for language "de"' );
    }


    public function testJobGuard()
    {
        $page = $this->page();
        $this->fake( $page );

        $user = $this->user;
        $loaded = new \ArrayObject();
        \Illuminate\Support\Facades\Auth::provider( 'cmstest', fn() => new class( $user, $loaded ) implements \Illuminate\Contracts\Auth\UserProvider {
            public function __construct( private $user, private \ArrayObject $loaded ) {}
            public function retrieveById( $id ) { $this->loaded[] = $id; return $this->user; }
            public function retrieveByToken( $id, $token ) { return null; }
            public function updateRememberToken( $user, $token ) {}
            public function retrieveByCredentials( array $credentials ) { return null; }
            public function validateCredentials( $user, array $credentials ) { return false; }
            public function rehashPasswordIfRequired( $user, array $credentials, bool $force = false ) {}
        } );

        $default = config( 'auth.defaults.guard' );
        config( ['auth.providers.cmstest' => ['driver' => 'cmstest'], 'auth.guards.cms' => ['driver' => 'session', 'provider' => 'cmstest']] );

        // the user is loaded by the provider of the guard active when the translation started
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Auth::shouldUse( 'cms' );
        Translation\Pages::dispatch( [$page->id], ['de'], $this->user );
        \Illuminate\Support\Facades\Auth::shouldUse( $default );

        $job = \Illuminate\Support\Facades\Queue::pushed( TranslatePage::class )->first();
        $job->withFakeQueueInteractions()->handle();

        $this->assertSame( [$this->user->getAuthIdentifier()], $loaded->getArrayCopy() );
        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobLegacy()
    {
        $page = $this->page();
        $this->fake( $page );

        // jobs queued by older versions stored the user properties as public "model", "guard" and "userId"
        $job = new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user, 'web' );
        $payload = preg_replace_callback( '/s:\d+:"\0\*\0(model|guard|userId)"/', fn( $m ) => 's:' . strlen( $m[1] ) . ':"' . $m[1] . '"', serialize( $job ) );

        $job = unserialize( (string) $payload )->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotFailed();
        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobUniqueId()
    {
        // jobs queued by older versions used the same unique ID, so their locks are released
        $job = new TranslatePage( 'page-id', 'de', 'tenant' );
        $this->assertSame( hash( 'sha256', 'tenant:page-id:de' ), $job->uniqueId() );
    }


    public function testJobLegacyWithoutUser()
    {
        $page = $this->page();
        $fake = $this->fake( $page );

        // jobs of older versions queued without user outside Permission::system() don't run as system
        $job = new TranslatePage( $page->id, 'de', Tenancy::value() );
        $payload = str_replace( 's:9:"' . "\0*\0" . 'system";N;', 's:6:"system";b:0;', serialize( $job ) );
        $this->assertNotSame( serialize( $job ), $payload );

        $job = unserialize( $payload )->withFakeQueueInteractions();
        $job->handle();

        $job->assertFailed();
        $this->assertCount( 0, $fake->calls() );
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobUserModel()
    {
        $page = $this->page();
        $this->fake( $page );

        $this->actingAs( $this->user );

        // users of another model with the same ID must not be used
        $other = new \Illuminate\Auth\GenericUser( ['id' => $this->user->getAuthIdentifier()] );
        $job = ( new TranslatePage( $page->id, 'de', Tenancy::value(), $other , 'web' ) )->withFakeQueueInteractions();
        $job->handle();

        $job->assertFailed();
        $this->assertFalse( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );

        $job = ( new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user , 'web' ) )->withFakeQueueInteractions();
        $job->handle();

        $this->assertTrue( PageVariant::where( 'page_id', $page->id )->where( 'lang', 'de' )->exists() );
    }


    public function testJobTenant()
    {
        $page = $this->page();
        $this->fake( $page );

        Tenancy::run( 'other', fn() => TranslatePage::dispatch( $page->id, 'de', 'test', $this->user , 'web' ) );

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
        TranslatePage::dispatch( $page->id, 'de', Tenancy::value(), $this->user , 'web' );
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


    public function testJobThrottledAfterBurst()
    {
        config( ['cms.ai.ratelimit' => 2] );

        $page = $this->page();
        $fake = $this->fake( $page );

        Limit::reserve( 6 );
        $this->travel( 61 )->seconds();

        $job = $this->job( $page->id, 'de' );
        $job->handle();

        $job->assertReleased();
        $this->assertCount( 0, $fake->calls() );

        \Illuminate\Support\Facades\Cache::forget( 'cms-translate:' . sha1( (string) Tenancy::value() ) . ':until' );
    }


    public function testJobRetryUntilDelay()
    {
        $job = ( new TranslatePage( 'page-1', 'de', Tenancy::value(), $this->user , 'web' ) )->delay( 3600 );

        $this->assertEqualsWithDelta( now()->addSeconds( TranslatePage::RETRY + 3600 )->getTimestamp(), $job->retryUntil()->getTimestamp(), 5 );
    }


    public function testJobFailedLogs()
    {
        $page = $this->page();

        Log::shouldReceive( 'error' )->once()->with( 'Page translation failed', \Mockery::on( fn( $ctx ) => $ctx['page'] === $page->id && $ctx['message'] === 'Retries exceeded' ) );
        ( new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user , 'web' ) )->failed( new \RuntimeException( 'Retries exceeded' ) );
    }


    public function testJobReleasesLock()
    {
        $page = $this->page();
        $this->fake( $page );

        Translation\Pages::dispatch( [$page->id], ['de'], $this->user );

        // finished translations can be queued again
        $lock = new \Illuminate\Bus\UniqueLock( app( \Illuminate\Contracts\Cache\Repository::class ) );
        $job = new TranslatePage( $page->id, 'de', Tenancy::value(), $this->user , 'web' );

        $this->assertTrue( $lock->acquire( $job ) );
        $lock->release( $job );
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

        $this->assertEquals( '[de] Title', PageView::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testGraphqlTranslateLanguageNotConfigured()
    {
        $fake = Prisma::fake( [] );

        // languages of existing variants are allowed, so unknown ones are rejected by the translation
        $this->actingAs( $this->user )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["fr"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Invalid language code "fr"' );

        $this->actingAs( $this->user )->graphQL( '
            mutation($id: [ID!]!) { translateFiles(id: $id, lang: ["de", "fr"]) { id } }
        ', ['id' => File::take( 1 )->pluck( 'id' )->all()] )->assertGraphQLValidationKeys( ['lang.1'] );

        $this->assertCount( 0, $fake->calls() );
    }


    public function testGraphqlTranslatePagePermission()
    {
        $this->actingAs( $this->user( ['page:view'] ) )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );

        $this->actingAs( $this->user( ['page:view', 'page:save', 'page:add'] ) )->graphQL( '
            mutation { translatePage(id: ["x"], lang: ["de"]) { id } }
        ' )->assertGraphQLErrorMessage( 'Insufficient permissions' );
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
        $this->assertEquals( '[de] Title', PageView::language( 'de' )->findOrFail( $page->id )->latest->data->title );
    }


    public function testMcpTranslatePageExistingLanguage()
    {
        $page = $this->page();
        $this->fake( $page );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, ['id' => $page->id, 'lang' => ['de']] )->assertOk();

        // existing variants can be updated even if their language isn't configured anymore
        config( ['cms.locales' => ['en']] );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, ['id' => $page->id, 'lang' => ['de']] )
            ->assertOk()->assertSee( ['"total":0'] );

        CmsServer::actingAs( $this->user )->tool( \Aimeos\Cms\Tools\TranslatePage::class, ['id' => $page->id, 'lang' => ['fr']] )
            ->assertSee( ['Invalid language code'] );
    }


    public function testMcpTranslatePageSkipped()
    {
        $page = $this->page();
        $trashed = $this->page();
        Pages::dropMany( [$trashed->id], $this->user );

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
}
