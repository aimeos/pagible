<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Concerns\ObservesPrisma;
use Aimeos\Prisma\Prisma;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Cms\Utils;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Tools\Step;
use Aimeos\Prisma\Exceptions\PrismaException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ChatController extends Controller
{
    use ObservesPrisma;


    /**
     * Streams a chat answer to the admin panel as a chunked text stream.
     *
     * Runs the page-synthesis pipeline (tools + the cms::prompts.chat system prompt) and writes the
     * assistant markdown token-by-token to a single HTTP response (no message broker required) so the
     * editor sees the answer build up live. The body is the raw markdown - the client appends each
     * chunk as it arrives and the closing connection signals completion; no framing or terminator.
     *
     * @param Request $request Holds the current "prompt" and prior "messages" [{role, content}]
     * @return StreamedResponse text/plain response that produces the answer chunk by chunk
     */
    public function stream( Request $request ) : StreamedResponse
    {
        $user = $request->user();

        if( !$user ) {
            abort( 401 );
        }

        if( !Permission::can( 'page:chat', $user ) ) {
            abort( 403 );
        }

        $this->limit( $request->all() );
        $prompt = trim( (string) $request->input( 'prompt', '' ) );

        if( $prompt === '' ) {
            abort( 422, 'Prompt must not be empty' );
        }

        $config = config( 'cms.ai.write', [] );
        $editor = Utils::editor( $user );
        $history = $this->history( $request->input( 'messages' ) );
        $system = view( 'cms::prompts.chat' )->render() . "\n" . view( 'cms::prompts.write' )->render() . "\n";

        $prisma = Prisma::text()->observe( $this->observer( $editor, 'chat' ) )
            ->using( config( 'cms.ai.write.provider' ), $config )
            ->model( config( 'cms.ai.write.model' ) )
            ->withClientOptions( ['timeout' => (int) config( 'cms.ai.timeout' )] )
            ->withMaxTokens( config( 'cms.ai.maxtoken' ) )
            ->withSystemPrompt( $system . "\n" . (string) $request->input( 'context', '' ) )
            ->withTools( [
                Tools::laravel( CmsTools\GetLocales::class )->max( 1 ),
                Tools::laravel( CmsTools\GetSchemas::class )->max( 1 ),

                Tools::laravel( CmsTools\AddPage::class ),
                Tools::laravel( CmsTools\DropPage::class ),
                Tools::laravel( CmsTools\GetPage::class ),
                Tools::laravel( CmsTools\GetPageHistory::class ),
                Tools::laravel( CmsTools\GetPageMetrics::class ),
                Tools::laravel( CmsTools\GetPageTree::class ),
                Tools::laravel( CmsTools\MovePage::class ),
                Tools::laravel( CmsTools\PublishPage::class ),
                Tools::laravel( CmsTools\RestorePage::class ),
                Tools::laravel( CmsTools\SavePage::class ),
                Tools::laravel( CmsTools\SearchPages::class ),

                Tools::laravel( CmsTools\AddElement::class ),
                Tools::laravel( CmsTools\DropElement::class ),
                Tools::laravel( CmsTools\GetElement::class ),
                Tools::laravel( CmsTools\PublishElement::class ),
                Tools::laravel( CmsTools\RestoreElement::class ),
                Tools::laravel( CmsTools\SaveElement::class ),
                Tools::laravel( CmsTools\SearchElements::class ),

                Tools::laravel( CmsTools\AddFile::class ),
                Tools::laravel( CmsTools\DropFile::class ),
                Tools::laravel( CmsTools\GetFile::class ),
                Tools::laravel( CmsTools\PublishFile::class ),
                Tools::laravel( CmsTools\RestoreFile::class ),
                Tools::laravel( CmsTools\SaveFile::class ),
                Tools::laravel( CmsTools\SearchFiles::class ),

                Tools::provider( 'web_search' ),
                Tools::provider( 'web_fetch' ),
            ] )
            ->withToolChoice( \Aimeos\Prisma\Providers\Base::AUTO )
            ->withMaxSteps( 10 );

        if( $history ) {
            $prisma->withMessages( $history );
        }

        // The 300s TTL is only a backstop for a hard worker kill (SIGKILL/OOM, where the finally never
        // runs): every normal/error/abort/disconnect path releases the lock explicitly.
        $lock = null;
        $blocked = false;

        try {
            $lock = Cache::lock( 'cms_chat_' . Tenancy::value() . '_' . $user->getAuthIdentifier(), 300 );
            $blocked = !$lock->get();
        } catch( \Throwable $e ) {
            Log::warning( 'Chat concurrency lock unavailable, running without it', ['message' => $e->getMessage()] );
        }

        if( $blocked ) {
            // 409 Conflict, not 429: the route's "throttle:cms-ai" middleware already owns 429 for rate
            // limiting, so a distinct status lets the client tell "already generating" from "slow down".
            abort( 409, 'A response is already being generated' );
        }

        $response = new StreamedResponse( function() use ( $prisma, $prompt, $config, $lock ) {
            // Don't let PHP kill the script mid-flush on disconnect; emit() detects it and stops
            // cleanly so the lock is always released here (and token pulling/billing stops promptly).
            $abort = (bool) ignore_user_abort( true );

            try {
                $this->emit( $prisma, $prompt, $config );
            } finally {
                ignore_user_abort( $abort );
                $lock?->release();
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Content-Type-Options' => 'nosniff', // don't let a browser MIME-sniff the stream as HTML
            'X-Accel-Buffering' => 'no', // tell nginx not to buffer the stream
            'Connection' => 'keep-alive',
        ] );

        return $response;
    }


    /**
     * Runs the streaming request and writes each delta straight to the output buffer as raw text.
     *
     * Providers that support streaming push prose token-by-token; for the rest (and the test fake)
     * the final text is written in one go. Stops promptly and quietly when the client disconnects.
     *
     * @param \Aimeos\Prisma\Contracts\Provider $prisma Configured Prisma text request
     * @param string $prompt Current user message
     * @param array<string, mixed> $config AI provider configuration
     */
    protected function emit( \Aimeos\Prisma\Contracts\Provider $prisma, string $prompt, array $config ) : void
    {
        // AI generation streams for minutes; PHP's default 30s max_execution_time otherwise kills the
        // worker mid-read (fatal in fread()). Lift it for the whole stream - a genuinely stalled upstream
        // still trips the provider's own read timeout first (a catchable PrismaException).
        set_time_limit( (int) config( 'cms.ai.timeout' ) );

        $send = function( ?string $text ) {
            // text() is null when the model ended on tool calls with no prose - nothing to send
            if( $text === null ) {
                return;
            }

            echo $text;

            // Push the chunk downstream immediately without closing the buffer, so a wrapping
            // output buffer (FPM ini, middleware) streams progressively instead of holding the
            // whole response - and stays capturable by TestResponse::streamedContent() in tests.
            if( ob_get_level() > 0 ) {
                ob_flush();
            }

            flush();

            // Client hit Stop / navigated away: unwind out of the provider's stream loop so we stop
            // pulling tokens immediately; the caller's finally then releases the per-user lock.
            if( connection_aborted() ) {
                throw new \RuntimeException( 'client disconnected' );
            }
        };

        try
        {
            if( $prisma->has( 'stream' ) )
            {
                $buffer = '';
                $prose = false;
                $response = $prisma->ensure( 'stream' )->stream( $prompt, [], $config ); // @phpstan-ignore-line method.notFound

                // Coalesce deltas into ~40 char chunks (or one chunk per tool call) instead of flushing
                // per token, then send whatever is left once the stream ends.
                foreach( $response->stream() as $chunk )
                {
                    if( $chunk instanceof Step )
                    {
                        $path = $chunk->arguments()['path'] ?? null;

                        if( !$chunk->done() ) {
                            // surface tool activity as a bold list item, with the page path in parentheses when known
                            $send( "\n* **" . $chunk->name() . "**" . ( $path ? ' (/' . $path . ')' : '' ) . "\n" );
                        }
                        continue;
                    }

                    $buffer .= (string) $chunk;
                    $prose = true; // a real prose token streamed (tool steps alone don't count)

                    // byte length is a fine "buffered enough?" gate and avoids multibyte scanning per token
                    if( strlen( $buffer ) >= 40 ) {
                        $send( $buffer );
                        $buffer = '';
                    }
                }

                if( $buffer !== '' ) {
                    $send( $buffer );
                }

                // Provider that only streamed tool steps (or a non-stream-backed response, e.g. the test
                // fake): send the assembled answer text. text() drains any unconsumed stream first; it's
                // null when the model ended on tool calls with no prose, which send() skips.
                if( !$prose ) {
                    $send( $response->text() );
                }
            }
            else
            {
                $response = $prisma->ensure( 'write' )->write( $prompt, [], $config ); // @phpstan-ignore-line method.notFound
                $send( $response->text() );
            }

        }
        catch( \Throwable $e )
        {
            if( connection_aborted() ) {
                return; // client gone (or the disconnect we threw above) - just stop, nothing to report
            }

            // The response is already streaming (HTTP 200 sent), so surface the failure inline;
            // Prisma errors expose their message, anything else stays generic.
            $msg = $e instanceof PrismaException
                ? $e->getMessage()
                : 'An unexpected error occurred';

            Log::error( 'Chat stream error', ['controller' => 'Chat', 'message' => $e->getMessage()] );

            echo "\n\n**" . $msg . "**";

            if( ob_get_level() > 0 ) {
                ob_flush();
            }

            flush();
        }
    }


    /**
     * Sanitizes the client-supplied conversation history for a multi-turn chat.
     *
     * Keeps only well-formed user/assistant turns; everything else is dropped silently. The complete
     * request is size-limited before this method is called.
     *
     * @param mixed $messages Raw messages value from the request
     * @return list<array{role: string, content: string}> Validated conversation history
     */
    protected function history( mixed $messages ) : array
    {
        if( is_string( $messages ) ) {
            $messages = json_decode( $messages, true );
        }

        if( !is_array( $messages ) ) {
            return [];
        }

        $result = [];
        $prev = null;

        foreach( $messages as $msg )
        {
            $msg = (array) $msg;

            if( !in_array( $role = $msg['role'] ?? null, ['user', 'assistant'], true )
                || !is_string( $content = $msg['content'] ?? null ) || $content === '' ) {
                continue;
            }

            // Enforce strict user/assistant alternation: dropping an errored assistant turn client-side
            // can leave two consecutive user turns, which providers like Gemini reject (HTTP 400).
            // Replace the previous same-role turn with this newer one.
            if( $role === $prev ) {
                array_pop( $result );
            }

            $result[] = ['role' => (string) $role, 'content' => $content];
            $prev = $role;
        }

        // The current prompt is appended as the next user turn, so history must end on an assistant turn
        // (or be empty) to keep alternating; drop a trailing user turn (e.g. an errored, dropped exchange).
        if( $prev === 'user' ) {
            array_pop( $result );
        }

        // Providers also require the first turn to be the user's: the -20 window of a long chat can start
        // on an assistant turn, so drop a leading assistant turn to keep the user/assistant/... ordering.
        if( ( $result[0]['role'] ?? null ) === 'assistant' ) {
            array_shift( $result );
        }

        return $result;
    }


    /**
     * Rejects chat requests whose complete serialized input exceeds the server-side budget.
     *
     * @param array<string, mixed> $input
     */
    protected function limit( array $input ) : void
    {
        $max = max( 1, (int) config( 'cms.ai.maxinput', 1024 * 1024 ) );
        $json = json_encode( $input );

        if( $json === false || strlen( $json ) > $max ) {
            abort( 422, sprintf( 'Chat input exceeds the maximum input size of %d bytes', $max ) );
        }
    }
}
