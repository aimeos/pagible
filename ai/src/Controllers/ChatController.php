<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Controllers;

use Aimeos\Cms\Chat;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\ConflictException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;


class ChatController extends Controller
{
    /**
     * Response headers of the chat stream.
     */
    private const HEADERS = [
        'Content-Type' => 'text/plain; charset=utf-8',
        'Cache-Control' => 'no-cache, no-transform',
        'X-Content-Type-Options' => 'nosniff',
        'X-Accel-Buffering' => 'no', // tell nginx not to buffer the stream
    ];


    /**
     * Streams a chat answer to the admin panel as a chunked text stream.
     *
     * The body is the raw markdown written by Chat\Stream.
     *
     * @param Request $request With "prompt", prior "messages" [{role, content}] and "context"
     * @return StreamedResponse text/plain response that produces the answer chunk by chunk
     */
    public function stream( Request $request ) : StreamedResponse
    {
        $user = $request->user() ?? abort( 401 );
        Chat\Agent::allowed( $user ) || abort( 403 );

        $input = $request->all();

        // clients of older versions send the history as JSON string
        if( is_string( $input['messages'] ?? null ) ) {
            $input['messages'] = json_decode( $input['messages'], true ) ?? [];
        }

        $validator = Validator::make( $input, [
            'prompt' => 'required|string',
            'context' => 'nullable|string',
            'messages' => 'nullable|array',
        ] );

        // return a status instead of redirecting back
        if( $validator->fails() ) {
            abort( 422, $validator->errors()->first() );
        }

        $prompt = $request->string( 'prompt' )->value();
        $context = $request->string( 'context' )->value();
        $messages = (array) ( $input['messages'] ?? [] );

        try {
            // released when the response is gone, also if discarded unsent
            $chat = Chat\Agent::chat( $user, $prompt, $context, $messages );
        } catch( ConflictException $e ) {
            abort( 409, $e->getMessage() ); // 429 is already used by the rate limiter
        } catch( Exception $e ) {
            abort( 422, $e->getMessage() );
        }

        return new StreamedResponse( $chat, 200, self::HEADERS );
    }
}
