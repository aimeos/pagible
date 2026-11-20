<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Chat;

use Aimeos\Cms\AiClient;
use Aimeos\Cms\AiError;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Responses\TextResponse;
use Aimeos\Prisma\Tools\Step;


/**
 * Streams chat answers as markdown, ended by the END marker and an optional error message.
 */
final class Stream
{
    /**
     * End of the answer, missing if the stream was cut off.
     */
    private const END = "\x1E";

    /**
     * Request attribute set while streaming, for the shutdown handler.
     */
    private const STREAMING = 'cms-chat-streaming';

    /**
     * Minimum bytes sent at once.
     */
    private const CHUNK = 40;

    /**
     * TRUE if the shutdown handler is registered (per process).
     */
    private static bool $handler = false;


    /**
     * Streams the answer and the end marker, client disconnects don't abort so callers can clean up.
     *
     * @param \Closure(): TextResponse $request Sends the provider request
     * @param int $calls Maximum provider requests, i.e. agent steps
     */
    public static function run( \Closure $request, int $calls = 1 ) : void
    {
        $attributes = app( 'request' )->attributes;
        $attributes->set( self::STREAMING, true );
        $abort = (bool) ignore_user_abort( true );
        self::shutdown();

        try {
            self::end( self::emit( $request, $calls ) );
        } finally {
            $attributes->remove( self::STREAMING );
            ignore_user_abort( $abort );
        }
    }


    /**
     * Runs the request and writes the answer to the client.
     *
     * @param \Closure(): TextResponse $request Sends the provider request
     * @param int $calls Maximum provider requests, i.e. agent steps
     * @return string|null Error message or null on success or disconnect
     */
    private static function emit( \Closure $request, int $calls ) : ?string
    {
        // the provider's read timeout still stops stalled streams
        return AiClient::lifted( static function() use ( $request ) : ?string {
            try
            {
                self::relay( $request() );
                return null;
            }
            catch( \Throwable $e )
            {
                if( connection_aborted() ) {
                    return null;
                }

                AiError::log( 'Chat stream error', $e );

                if( AiError::timeout( $e ) ) {
                    return self::stopped();
                }

                return $e instanceof PrismaException ? AiError::message( $e ) : self::failed();
            }
        }, $calls );
    }


    /**
     * Ends the answer with the end marker and the error message.
     *
     * @param string|null $error Error message or null if complete
     */
    private static function end( ?string $error ) : void
    {
        echo self::END . str_replace( self::END, '', (string) $error );
        self::flush();
    }


    /**
     * Returns the message for unexpected errors.
     */
    private static function failed() : string
    {
        return __( 'An unexpected error occurred' );
    }


    /**
     * Sends the output to the client without closing the buffers.
     */
    private static function flush() : void
    {
        if( ob_get_level() > 0 ) {
            ob_flush();
        }

        flush();
    }


    /**
     * Sends the streamed answer to the client until it disconnects.
     *
     * @param TextResponse $response Streaming or complete provider response
     */
    private static function relay( TextResponse $response ) : void
    {
        $buffer = '';
        $prose = false;

        try
        {
            foreach( $response->stream() as $chunk )
            {
                    if( $chunk instanceof Step )
                {
                    if( !$chunk->done() && !self::send( self::step( $chunk ) ) ) {
                        return;
                    }
                    continue;
                }

                $buffer .= (string) $chunk;
                $prose = true;

                if( strlen( $buffer ) >= self::CHUNK )
                {
                    if( !self::send( $buffer ) ) {
                        return;
                    }

                    $buffer = '';
                }
            }
        }
        catch( \Throwable $e )
        {
            self::send( $buffer );
            throw $e;
        }

        // e.g. only tool calls were streamed
        if( self::send( $buffer ) && !$prose ) {
            self::send( $response->text() );
        }
    }


    /**
     * Writes the text to the client and flushes it.
     *
     * @param string|null $text Text to send
     * @return bool TRUE if the client is still connected
     */
    private static function send( ?string $text ) : bool
    {
        if( connection_aborted() ) {
            return false;
        }

        if( $text !== null && $text !== '' )
        {
            echo str_replace( self::END, '', $text ); // end marker only once
            self::flush();
        }

        // disconnects are only detected after writing
        return !connection_aborted();
    }


    /**
     * Registers a handler ending the stream on fatal errors, which skip finally blocks.
     */
    private static function shutdown() : void
    {
        if( self::$handler ) {
            return;
        }

        self::$handler = true;

        register_shutdown_function( static function() {
            $error = error_get_last();

            if( $error && $error['type'] === E_ERROR && app( 'request' )->attributes->get( self::STREAMING ) ) {
                self::end( str_contains( $error['message'], 'Maximum execution time' ) ? self::stopped() : self::failed() );
            }
        } );
    }


    /**
     * Returns the tool call as bold markdown list item.
     *
     * @param Step $step Tool call
     * @return string Markdown list item with tool name and path
     */
    private static function step( Step $step ) : string
    {
        // model input, may be no string or break the markdown
        $arg = $step->arguments()['path'] ?? null;
        $path = is_string( $arg ) ? trim( (string) preg_replace( '/[*`\[\]\s]+/', ' ', $arg ), " /" ) : '';

        return "\n* **" . $step->name() . "**" . ( $path !== '' ? ' (/' . $path . ')' : '' ) . "\n";
    }


    /**
     * Returns the message for answers stopped by the time limit.
     */
    private static function stopped() : string
    {
        return __( 'The response took too long and was stopped' );
    }
}
