<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Aimeos\Prisma\Tools\Step;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Cache;


class ChatTest extends AiTestAbstract
{
    protected function defineEnvironment( $app )
    {
        parent::defineEnvironment( $app );

        // The chat uses Cache::lock(); the array store provides the atomic locks these tests rely on
        $app['config']->set( 'cache.default', 'array' );
    }


    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new \App\Models\User([
            'name' => 'Test editor',
            'email' => 'editor@testbench',
            'password' => 'secret',
        ]);
        $this->user->cmsperms = \Aimeos\Cms\Permission::all();
    }


    public function testStreamReturnsChunkedText()
    {
        Prisma::fake( [TextResponse::fromText( 'Created the page' )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), [
                'prompt' => 'Create a page about cats',
                'messages' => [
                    ['role' => 'user', 'content' => 'hello'],
                    ['role' => 'assistant', 'content' => 'hi there'],
                    ['role' => 'system', 'content' => 'dropped by the history sanitizer'],
                ],
            ] );

        $response->assertOk();
        $this->assertStringContainsString( 'text/plain', (string) $response->baseResponse->headers->get( 'Content-Type' ) );
        $this->assertSame( 'nosniff', $response->baseResponse->headers->get( 'X-Content-Type-Options' ) );

        // The body is the raw markdown answer, streamed in chunks with no framing or terminator
        $this->assertSame( 'Created the page', $response->streamedContent() );
    }


    public function testStreamConsumesLiveDeltasAndToolSteps()
    {
        // A stream-backed response yields prose deltas (string) and, per tool call, a Step before
        // execution (done() === false) and after (done() === true); the controller iterates stream().
        $producer = function( TextResponse $response ) {
            $step = new Step( 'call_1', 'SearchPages', [] );
            yield $step;                  // before execution: surfaced as a bold list item
            $step->complete( '[]' );
            yield $step;                  // after execution: done(), must NOT surface a second time

            yield 'Found ';
            yield 'two pages';
            $response->add( 'Found two pages' );
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Find pages about cats'] );

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString( 'Found two pages', $body ); // prose deltas streamed through
        $this->assertSame( 1, substr_count( $body, '**SearchPages**' ) ); // tool surfaced once (the done step is ignored)
    }


    public function testStreamRejectsEmptyPrompt()
    {
        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => '  '] );

        $response->assertStatus( 422 );
    }


    public function testStreamDeniesWithoutPermission()
    {
        $user = new \App\Models\User([
            'name' => 'No perms',
            'email' => 'noperms@testbench',
            'password' => 'secret',
        ]);
        $user->cmsperms = [];

        $response = $this->actingAs( $user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertStatus( 403 );
    }


    public function testStreamRequiresAuthentication()
    {
        $response = $this->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertStatus( 401 );
    }


    public function testStreamRejectsAConcurrentStreamForTheSameUser()
    {
        $key = 'cms_chat_' . \Aimeos\Cms\Tenancy::value() . '_' . $this->user->getAuthIdentifier();
        $lock = Cache::lock( $key, 60 );
        $this->assertTrue( $lock->get() ); // simulate an already-running stream for this user

        try {
            $response = $this->actingAs( $this->user )
                ->withoutMiddleware( VerifyCsrfToken::class )
                ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

            // 409 Conflict (a stream is already running), distinct from the throttle middleware's 429
            $response->assertStatus( 409 );
        } finally {
            $lock->release();
        }
    }


    public function testStreamReleasesTheLockWhenItEnds()
    {
        Prisma::fake( [TextResponse::fromText( 'Created the page' )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $response->streamedContent(); // run the stream to completion -> the finally releases the lock

        $lock = Cache::lock( 'cms_chat_' . \Aimeos\Cms\Tenancy::value() . '_' . $this->user->getAuthIdentifier(), 60 );
        $this->assertTrue( $lock->get(), 'the per-user lock should be free once the stream ended' );
        $lock->release();
    }


    public function testHistoryEnforcesAlternationAfterADroppedTurn()
    {
        // A client that drops an errored assistant turn can send two consecutive user turns; the
        // sanitizer must collapse them and not end on a user turn (providers 400 on that).
        $method = new \ReflectionMethod( \Aimeos\Cms\Controllers\ChatController::class, 'history' );
        $method->setAccessible( true );

        $history = $method->invoke( new \Aimeos\Cms\Controllers\ChatController(), [
            ['role' => 'user', 'content' => 'first question'],
            ['role' => 'assistant', 'content' => 'first answer'],
            ['role' => 'user', 'content' => 'failed prompt'],
            ['role' => 'user', 'content' => 'next prompt'],
        ] );

        $this->assertSame(
            [['role' => 'user', 'content' => 'first question'], ['role' => 'assistant', 'content' => 'first answer']],
            $history
        );
    }


    public function testHistoryDropsALeadingAssistantTurn()
    {
        // The last-20 window of a long chat can begin on an assistant turn; providers require the
        // first turn to be the user's, so a leading assistant turn must be dropped.
        $method = new \ReflectionMethod( \Aimeos\Cms\Controllers\ChatController::class, 'history' );
        $method->setAccessible( true );

        $history = $method->invoke( new \Aimeos\Cms\Controllers\ChatController(), [
            ['role' => 'assistant', 'content' => 'earlier answer'],
            ['role' => 'user', 'content' => 'a question'],
            ['role' => 'assistant', 'content' => 'an answer'],
        ] );

        $this->assertSame(
            [['role' => 'user', 'content' => 'a question'], ['role' => 'assistant', 'content' => 'an answer']],
            $history
        );
    }
}
