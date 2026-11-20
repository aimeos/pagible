<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Responses\TextResponse;
use Aimeos\Prisma\Tools\Step;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\RateLimiter;


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


    public function testAiRateLimiter()
    {
        $this->assertNotNull( RateLimiter::limiter( 'cms-ai' ) );
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('elementChatRoles')]
    public function testElementChatRoles( string $role, bool $allowed )
    {
        $this->user->cmsperms = [$role];

        $this->assertSame( $allowed, \Aimeos\Cms\Permission::can( 'element:chat', $this->user ) );
    }


    public static function elementChatRoles() : array
    {
        return [
            'admin' => ['admin', true],
            'publisher' => ['publisher', true],
            'editor' => ['editor', true],
            'viewer' => ['viewer', false],
        ];
    }


    #[\PHPUnit\Framework\Attributes\DataProvider('chatPermissions')]
    public function testStreamAllowsChatPermission( string $permission )
    {
        $this->user->cmsperms = [$permission];
        $this->assertTrue( \Aimeos\Cms\Permission::get( $this->user )[$permission] );
        Prisma::fake( [TextResponse::fromText( 'Found the banner' )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Find the banner'] );

        $response->assertOk();
        $this->assertSame( "Found the banner\x1E", $response->streamedContent() );
    }


    public static function chatPermissions() : array
    {
        return [
            'page chat only' => ['page:chat'],
            'element chat only' => ['element:chat'],
            'file chat only' => ['file:chat'],
        ];
    }


    public function testStreamDeniesFilePermissionsWithoutChat()
    {
        $this->user->cmsperms = ['file:view', 'file:save'];

        $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Find the banner'] )
            ->assertForbidden();
    }


    public function testStreamDeniesElementPermissionsWithoutChat()
    {
        $this->user->cmsperms = ['element:view', 'element:save'];

        $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Find shared elements'] )
            ->assertForbidden();
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

        // The body is the raw markdown answer, streamed in chunks and ended by the marker (no error message)
        $content = $this->assertExecutionLimitRestored( fn() => $response->streamedContent() );

        $this->assertSame( "Created the page\x1E", $content );
    }


    public function testStreamJsonHistory()
    {
        Prisma::fake( [TextResponse::fromText( 'Done' )] );

        // clients of older versions send the history as JSON string, which isn't rejected
        $content = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), [
                'prompt' => 'Create a page',
                'messages' => json_encode( [['role' => 'user', 'content' => 'hello'], ['role' => 'assistant', 'content' => 'hi there']] ),
            ] )
            ->assertOk()
            ->streamedContent();

        $this->assertSame( "Done\x1E", $content );
    }


    public function testStreamEndsWithPrismaErrorMessage()
    {
        config( ['app.debug' => false] );

        $producer = function( TextResponse $response ) {
            yield 'Partial answer';
            throw new \Aimeos\Prisma\Exceptions\OverloadedException( 'Provider overloaded' );
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "Partial answer\x1EThe AI service is busy, please try again later", $response->streamedContent() );
    }


    public function testStreamShowsPrismaErrorInDebugMode()
    {
        config( ['app.debug' => true] );

        Prisma::fake( [new \Aimeos\Prisma\Exceptions\OverloadedException( 'Provider overloaded' )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "\x1EProvider overloaded", $response->streamedContent() );
    }


    public function testStreamRedactsPrismaErrorInDebugMode()
    {
        config( ['app.debug' => true] );

        Prisma::fake( [new \Aimeos\Prisma\Exceptions\OverloadedException( 'Invalid key sk-abc123' )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "\x1EInvalid key [REDACTED]", $response->streamedContent() );
    }


    public function testStreamSanitizesToolPath()
    {
        $producer = function( TextResponse $response ) {
            yield new \Aimeos\Prisma\Tools\Step( null, 'save-page', ['path' => "a**b\n`c`"] );
            yield new \Aimeos\Prisma\Tools\Step( null, 'add-page', ['path' => ['x']] );
            yield 'Done';
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "\n* **save-page** (/a b c)\n\n* **add-page**\nDone\x1E", $response->streamedContent() );
    }


    public function testStreamEndsWithTimeoutMessage()
    {
        $producer = function( TextResponse $response ) {
            yield 'Partial answer';
            throw new \GuzzleHttp\Exception\ConnectException( 'cURL error 28: Operation timed out after 300001 milliseconds',
                new \GuzzleHttp\Psr7\Request( 'POST', 'https://api.example.com' ), null, ['errno' => 28] );
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "Partial answer\x1EThe response took too long and was stopped", $response->streamedContent() );
    }


    public function testStreamHidesUnexpectedErrorDetails()
    {
        $producer = function( TextResponse $response ) {
            yield 'Partial answer';
            throw new \RuntimeException( 'SQLSTATE[HY000]: secret details' );
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "Partial answer\x1EAn unexpected error occurred", $response->streamedContent() );
    }


    public function testStreamStripsEndMarkerFromAnswer()
    {
        Prisma::fake( [TextResponse::fromText( "Created\x1E the page" )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $this->assertSame( "Created the page\x1E", $response->streamedContent() );
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


    public function testStreamShowsPagePathForToolSteps()
    {
        // A page tool call carries the affected path in its "path" argument; the controller surfaces it
        // in parentheses next to the tool name. An ID-only call (no path argument) shows just the name.
        $producer = function( TextResponse $response ) {
            $withPath = new Step( 'call_1', 'SavePage', ['path' => 'blog/cats'] );
            yield $withPath;
            $withPath->complete( '{}' );

            $idOnly = new Step( 'call_2', 'PublishPage', ['id' => ['123']] );
            yield $idOnly;
            $idOnly->complete( '{}' );

            $response->add( 'Done' );
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Update the cats page'] );

        $response->assertOk();
        $body = $response->streamedContent();

        $this->assertStringContainsString( '**SavePage** (/blog/cats)', $body ); // path shown for page tools
        $this->assertStringNotContainsString( '**PublishPage** (', $body ); // ID-only tool: no path appended
    }


    public function testStreamHandlesToolOnlyResponseWithoutProse()
    {
        // A model can finish on a tool call without emitting any prose: the stream yields only Steps
        // and never adds text, so text() is null. The controller must not pass that null to the
        // string-typed send() closure (which would throw a TypeError mid-stream).
        $producer = function( TextResponse $response ) {
            $step = new Step( 'call_1', 'SavePage', [] );
            yield $step;
            $step->complete( '{}' );
            yield $step;
            // no prose deltas and no $response->add(): text() stays null
        };

        Prisma::fake( [TextResponse::fromStream( $producer )] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );

        $response->assertOk();
        $body = $response->streamedContent();

        // Only the tool activity surfaces; no error banner from a caught TypeError
        $this->assertStringContainsString( '**SavePage**', $body );
        $this->assertStringNotContainsString( 'unexpected error', strtolower( $body ) );
    }


    public function testStreamRejectsEmptyPrompt()
    {
        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => '  '] );

        $response->assertStatus( 422 );
    }


    public function testStreamRejectsOversizedTotalInput()
    {
        $input = [
            'prompt' => str_repeat( 'p', 30 ),
            'context' => str_repeat( 'c', 30 ),
            'messages' => [
                ['role' => 'user', 'content' => str_repeat( 'u', 30 )],
                ['role' => 'assistant', 'content' => str_repeat( 'a', 30 )],
            ],
        ];
        config( ['cms.ai.maxinput' => strlen( (string) json_encode( $input ) ) - 1] );

        $response = $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), $input );

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


    public function testStreamAllowsParallelStreamsForTheSameUser()
    {
        Prisma::fake( [TextResponse::fromText( 'First page' ), TextResponse::fromText( 'Second page' )] );

        // the stream holds its slot until its content is sent
        $first = $this->chat();
        $second = $this->chat();

        $first->assertOk();
        $second->assertOk();
        $this->assertStringContainsString( 'First page', $first->streamedContent() );
        $this->assertStringContainsString( 'Second page', $second->streamedContent() );
    }


    public function testStreamRejectsTooManyStreamsForTheSameUser()
    {
        Prisma::fake( array_map( fn() => TextResponse::fromText( 'Created the page' ), range( 1, 3 ) ) );

        // three streams running for this user
        $responses = array_map( fn() => $this->chat(), range( 1, 3 ) );

        // 409 Conflict (all slots in use), distinct from the throttle middleware's 429
        $this->chat()->assertStatus( 409 );

        foreach( $responses as $response ) {
            $response->streamedContent();
        }
    }


    public function testStreamReleasesTheLockWhenItEnds()
    {
        Prisma::fake( array_map( fn() => TextResponse::fromText( 'Created the page' ), range( 1, 4 ) ) );

        // more streams than slots can run one after another if each one releases its slot at the end
        foreach( range( 1, 4 ) as $num )
        {
            $response = $this->chat();
            $response->assertOk();
            $response->streamedContent();
        }
    }


    public function testHistoryEnforcesAlternationAfterADroppedTurn()
    {
        // A client that drops an errored assistant turn can send two consecutive user turns; the
        // sanitizer must collapse them and not end on a user turn (providers 400 on that).
        $history = \Aimeos\Cms\Chat\History::clean( [
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
        $history = \Aimeos\Cms\Chat\History::clean( [
            ['role' => 'assistant', 'content' => 'earlier answer'],
            ['role' => 'user', 'content' => 'a question'],
            ['role' => 'assistant', 'content' => 'an answer'],
        ] );

        $this->assertSame(
            [['role' => 'user', 'content' => 'a question'], ['role' => 'assistant', 'content' => 'an answer']],
            $history
        );
    }


    public function testDraftsRemoveConfigAndImages()
    {
        $args = null;
        $schema = \Aimeos\Prisma\Schema\Schema::fromArray( 'args', ['type' => 'object', 'properties' => []] );
        $tool = \Aimeos\Prisma\Tools::make( 'save', 'Saves', $schema, function( ...$params ) use ( &$args ) {
            $args = $params;
            return 'ok';
        } );

        $result = ( new \Aimeos\Cms\Chat\Drafts( $tool ) )( ['config' => ['javascript' => ['type' => 'javascript', 'data' => ['text' => 'alert(1)'], 'files' => []]], 'content' => [['data' => [
            'text' => "A ![cat](https&#58;//evil.example/x) B ![a [b] c](https://evil.example/y) C ![dog] [link](https://example.com)\n\n[dog]: https://evil.example/z",
            'html' => '<p>A<IMG src="&#104;ttps&#58;&#47;&#47;evil.example/a">B<span title="a>b"style="background-image:&#117;rl(https://evil.example/c)">x</span><i/STYLE=\'x\'>y</i></p>',
        ]]]] );

        $data = $args[0]['content'][0]['data'] ?? [];

        $this->assertSame( 'ok', $result );
        $this->assertArrayNotHasKey( 'config', $args[0] ?? [] );
        $this->assertSame( "A [cat](https&#58;//evil.example/x) B [a [b] c](https://evil.example/y) C [dog] [link](https://example.com)\n\n[dog]: https://evil.example/z", $data['text'] ?? null );
        $this->assertSame( '<p>AB<span title="a>b">x</span><i/>y</i></p>', $data['html'] ?? null );

        $md = new \League\CommonMark\GithubFlavoredMarkdownConverter( ['html_input' => 'strip', 'allow_unsafe_links' => false] );
        $this->assertStringNotContainsString( '<img', (string) $md->convert( $data['text'] ?? '' ) );
        $this->assertStringNotContainsString( 'evil', \Aimeos\Cms\Utils::html( $data['html'] ?? '' ) );
    }


    public function testHistoryDropsTheOldestTurnsBeyondTheMaximumSize()
    {
        $long = str_repeat( 'x', 60000 );

        $history = \Aimeos\Cms\Chat\History::clean( [
            ['role' => 'user', 'content' => 'old question'],
            ['role' => 'assistant', 'content' => $long],
            ['role' => 'user', 'content' => 'new question'],
            ['role' => 'assistant', 'content' => $long],
        ] );

        $this->assertSame( [['role' => 'user', 'content' => 'new question'], ['role' => 'assistant', 'content' => $long]], $history );
    }


    protected function chat() : \Illuminate\Testing\TestResponse
    {
        return $this->actingAs( $this->user )
            ->withoutMiddleware( VerifyCsrfToken::class )
            ->post( route( 'cms.chat' ), ['prompt' => 'Create a page about cats'] );
    }
}
