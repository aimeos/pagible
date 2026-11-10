<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Prisma\Exceptions as PrismaErrors;
use Aimeos\Prisma\Exceptions\PrismaException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


/**
 * Classifies, logs and reports the errors of the AI providers without leaking credentials.
 */
final class AiError
{
    /**
     * CURLE_OPERATION_TIMEDOUT, the curl extension may be missing.
     */
    private const CURL_TIMEOUT = 28;

    /**
     * Patterns of credentials and their replacements.
     */
    private const REDACT = [
        '/\b(sk-|AIza|xai-|gsk_|r8_)[\w-]+/' => '[REDACTED]', // OpenAI, Anthropic, Gemini, xAI, Groq and Replicate keys
        '/\b(Bearer|Basic|DeepL-Auth-Key)\s+\S+/i' => '$1 [REDACTED]', // authorization headers
        '/([?&](key|api_key|apikey|token|access_token)=)[^&\s"\']+/i' => '$1[REDACTED]', // keys in URLs
        '/\b[0-9a-f-]{36}:fx\b/i' => '[REDACTED]', // DeepL free keys
        '/\b[0-9a-f]{64,}\b/i' => '[REDACTED]', // hex keys like the ones of Clipdrop
    ];

    /**
     * Tests if all following requests will fail too, e.g. due to an invalid API key.
     *
     * @param \Throwable $e Error of the AI request
     * @return bool TRUE if no further requests should be sent to the provider
     */
    public static function fatal( \Throwable $e ) : bool
    {
        return $e instanceof PrismaErrors\UnauthorizedException
            || $e instanceof PrismaErrors\ForbiddenException
            || $e instanceof PrismaErrors\PaymentRequiredException;
    }


    /**
     * Logs an error with redacted message and trace.
     *
     * @param string $message Log message
     * @param \Throwable $e Error which occurred
     * @param array<string, mixed> $context Additional log context, e.g. the operation
     */
    public static function log( string $message, \Throwable $e, array $context = [] ) : void
    {
        Log::error( $message, $context + ['message' => self::redact( $e->getMessage() ), 'trace' => self::redact( $e->getTraceAsString() )] );
    }


    /**
     * Returns a user message, provider details are only shown redacted in debug mode.
     *
     * @param PrismaException $e Provider error
     * @return string Error message for the user
     */
    public static function message( PrismaException $e ) : string
    {
        if( config( 'app.debug' ) ) {
            return self::redact( $e->getMessage() );
        }

        return match( true ) {
            $e instanceof PrismaErrors\RateLimitException, $e instanceof PrismaErrors\OverloadedException => __( 'The AI service is busy, please try again later' ),
            $e instanceof PrismaErrors\PaymentRequiredException => __( 'The quota of the AI service is exhausted' ),
            $e instanceof PrismaErrors\UnauthorizedException, $e instanceof PrismaErrors\ForbiddenException => __( 'The AI service denied the access' ),
            $e instanceof PrismaErrors\NotImplementedException => __( 'The AI service doesn\'t support this operation' ),
            $e instanceof PrismaErrors\SizeException => __( 'The input is too large for the AI service' ),
            $e instanceof PrismaErrors\BadRequestException => __( 'The AI service rejected the request' ),
            $e instanceof PrismaErrors\NotFoundException => __( 'The AI model wasn\'t found' ),
            default => __( 'The AI service failed' ),
        };
    }


    /**
     * Removes API keys and other credentials from texts.
     *
     * @param string $text Text which may contain credentials
     * @return string Text with the credentials replaced by "[REDACTED]"
     */
    public static function redact( string $text ) : string
    {
        return (string) preg_replace( array_keys( self::REDACT ), self::REDACT, $text );
    }


    /**
     * Logs a provider error and returns the user message.
     *
     * @param PrismaException $e Provider error
     * @param array<string, mixed> $context Additional log context, e.g. the tool or mutation
     * @return string Error message for the user
     */
    public static function report( PrismaException $e, array $context = [] ) : string
    {
        self::log( 'AI service error', $e, $context );
        return self::message( $e );
    }


    /**
     * Tests if the request may succeed if it's sent again later.
     *
     * @param \Throwable $e Error of the AI request
     * @return bool TRUE for rate limit, overload and connection errors
     */
    public static function retryable( \Throwable $e ) : bool
    {
        return $e instanceof PrismaErrors\RateLimitException
            || $e instanceof PrismaErrors\OverloadedException
            || $e instanceof ConnectException;
    }


    /**
     * Tests if the provider request failed because of a timeout.
     *
     * @param \Throwable $e Error which occurred
     * @return bool TRUE if the HTTP client timed out
     */
    public static function timeout( \Throwable $e ) : bool
    {
        for( $ex = $e; $ex; $ex = $ex->getPrevious() )
        {
            $http = $ex instanceof ConnectException || $ex instanceof RequestException;
            $errno = $http ? $ex->getHandlerContext()['errno'] ?? null : null;

            if( $errno === self::CURL_TIMEOUT ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Returns the tool error for the model, others than CMS and validation errors are only logged.
     *
     * @param \Throwable $e Error thrown by the tool
     * @return string Error message for the model
     */
    public static function tool( \Throwable $e ) : string
    {
        $known = $e instanceof Exception || $e instanceof ValidationException;

        if( !$known ) {
            self::log( 'AI tool error', $e );
        }

        return 'Error: ' . ( $known ? $e->getMessage() : __( 'The tool failed' ) );
    }
}
