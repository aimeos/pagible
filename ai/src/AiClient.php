<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Generated;
use Aimeos\Prisma\Contracts\Provider;
use Aimeos\Prisma\Exceptions\PrismaException;
use Aimeos\Prisma\Prisma;
use Aimeos\Prisma\Tools;
use Aimeos\Prisma\Tools\Adapter\Adapter;


/**
 * Builds the Prisma requests configured in "cms.ai.<op>" and records them as Generated events.
 */
final class AiClient
{
    /**
     * AI operation writing texts, also used by chat and agents.
     */
    public const WRITE = 'write';

    /**
     * AI operation describing files, also used by the description command.
     */
    public const DESCRIBE = 'describe';

    /**
     * Client configuration keys which aren't request options.
     */
    private const CLIENT = ['provider', 'model', 'api_key', 'url'];

    /**
     * Seconds to wait for the connection to the provider.
     */
    private const CONNECT_TIMEOUT = 10;

    /**
     * Container binding set while lifted() controls the time limit.
     */
    private const LIFTED = 'cms.ai.lifted';


    /**
     * Tests if an AI provider is configured for the operation.
     *
     * @param string $op AI operation, e.g. "describe" or "write"
     * @return bool TRUE if the operation can be used
     */
    public static function available( string $op ) : bool
    {
        return self::name( $op ) !== '';
    }


    /**
     * Calls the provider of the AI operation within the execution time limit.
     *
     * @template T
     * @param string $type Media type, e.g. "text", "image" or "audio"
     * @param string $op AI operation, its configuration key is returned by config()
     * @param \Closure(Provider, array<string, mixed>): T $fn Receives the provider and the request options
     * @param int $calls Maximum number of provider requests made by the function
     * @param array<string, mixed>|null $options Request options passed to the function or NULL for the ones of options()
     * @return T Return value of the function
     * @throws PrismaException If no provider is configured or the function calls a method the provider doesn't support
     */
    public static function call( string $type, string $op, \Closure $fn, int $calls = 1, ?array $options = null ) : mixed
    {
        return self::lifted( fn() => $fn( self::prisma( $type, $op, null ), $options ?? self::options( $op ) ), $calls );
    }


    /**
     * Ensures the JSON input stays within "cms.ai.maxinput" and "cms.ai.maxdepth".
     *
     * @param mixed $value Input sent to the AI provider
     * @param string|null $name Translated name of the input used in the error message, "Content" by default
     * @return string JSON encoded input
     * @throws Exception If the input can't be encoded, is too large or too deeply nested
     */
    public static function checkInput( mixed $value, ?string $name = null ) : string
    {
        $name ??= __( 'Content' );
        $max = max( 1, (int) config( 'cms.ai.maxinput' ) );
        $depth = max( 1, (int) config( 'cms.ai.maxdepth' ) );
        $json = json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES, $depth );

        if( $json === false && json_last_error() === JSON_ERROR_DEPTH ) {
            throw new Exception( __( ':name exceeds the maximum nesting depth of :depth', ['name' => $name, 'depth' => $depth] ) );
        }

        if( $json === false ) {
            throw new Exception( __( ':name can\'t be encoded: :error', ['name' => $name, 'error' => json_last_error_msg()] ) );
        }

        if( strlen( $json ) > $max ) {
            throw new Exception( __( ':name exceeds the maximum input size of :max bytes', ['name' => $name, 'max' => $max] ) );
        }

        return $json;
    }


    /**
     * Returns the configuration key of the AI operation.
     *
     * @param string $op AI operation, e.g. "describe" or "write"
     * @return string Configuration key, e.g. "cms.ai.write"
     */
    public static function config( string $op ) : string
    {
        return 'cms.ai.' . $op;
    }


    /**
     * Returns the execution time for the requests, "cms.ai.timeout" each and "cms.ai.maxtime" in total.
     *
     * @param int $calls Maximum number of provider requests, e.g. agent steps
     * @return int Seconds or 0 for no limit
     */
    public static function duration( int $calls = 1 ) : int
    {
        $max = max( 0, (int) config( 'cms.ai.maxtime' ) );
        $time = self::timeout() * max( 1, $calls );

        return $max > 0 && ( $time === 0 || $time > $max ) ? $max : $time;
    }


    /**
     * Calls the AI provider with the "cms.ai.timeout" per request as execution time limit.
     *
     * Only the outermost call sets the limit because set_time_limit() restarts the timer,
     * afterwards the previous limit restarts (it counts CPU time only on Linux).
     *
     * @param \Closure $fn Function calling the provider
     * @param int $calls Maximum number of provider requests made by the function, e.g. agent steps
     * @return mixed Return value of the function
     */
    public static function lifted( \Closure $fn, int $calls = 1 ) : mixed
    {
        if( app()->bound( self::LIFTED ) ) {
            return $fn();
        }

        $limit = (int) ini_get( 'max_execution_time' );

        set_time_limit( self::duration( $calls ) );
        app()->instance( self::LIFTED, true );

        try {
            return $fn();
        } finally {
            app()->forgetInstance( self::LIFTED );
            set_time_limit( $limit );
        }
    }


    /**
     * Returns the name of the provider configured for the AI operation.
     *
     * @param string $op AI operation, its configuration key is returned by config()
     * @return string Lower case provider name, e.g. "openai", or an empty string if none is configured
     */
    public static function name( string $op ) : string
    {
        return strtolower( (string) config( self::config( $op ) . '.provider' ) );
    }


    /**
     * Returns the request options of the AI operation without client settings like the API key.
     *
     * @param string $op AI operation, its configuration key is returned by config()
     * @return array<string, mixed> Request options, e.g. the image size
     */
    public static function options( string $op ) : array
    {
        return array_diff_key( (array) config( self::config( $op ), [] ), array_flip( self::CLIENT ) );
    }


    /**
     * Returns a text request with the system prompt and tools.
     *
     * @param string $op AI operation, its configuration key is returned by config(), e.g. "write"
     * @param string $system System prompt
     * @param array<int, string|Adapter> $tools MCP tool classes, provider tool names like "web_search" or Prisma tools
     * @param int $steps Maximum number of requests the provider may make for tool calls
     * @param array<int, string> $once MCP tool classes which can be called once, e.g. for discovery
     * @param string|null $log Name the usage is logged as instead of the Prisma operation, e.g. "chat"
     * @return Provider Prisma text request
     * @throws PrismaException If no provider is configured
     */
    public static function prompt( string $op, string $system, array $tools = [], int $steps = 1, array $once = [], ?string $log = null ) : Provider
    {
        return self::text( $op, $log )
            ->withSystemPrompt( $system )
            ->withTools( [...self::tools( $once, 1 ), ...self::tools( $tools )] )
            ->withMaxSteps( $steps );
    }


    /**
     * Returns the maximum number of agent steps per request.
     *
     * @return int Maximum steps, at least one
     */
    public static function steps() : int
    {
        return max( 1, (int) config( 'cms.ai.maxsteps' ) );
    }


    /**
     * Joins the non-empty parts of a system prompt.
     *
     * @param string|null ...$parts Prompt parts like instructions and additional context
     * @return string System prompt
     */
    public static function system( ?string ...$parts ) : string
    {
        $parts = array_map( fn( $part ) => trim( (string) $part ), $parts );
        return implode( "\n", array_filter( $parts, fn( $part ) => $part !== '' ) );
    }


    /**
     * Returns the Prisma text provider, unchecked so callers can choose between methods.
     *
     * @param string $op AI operation, its configuration key is returned by config(), e.g. "write"
     * @param string|null $log Name the usage is logged as instead of the Prisma operation, e.g. "chat"
     * @return Provider Text provider with the configured timeout and token limit
     * @throws PrismaException If no provider is configured
     */
    public static function text( string $op, ?string $log = null ) : Provider
    {
        $max = (int) config( 'cms.ai.maxtoken' );
        return self::prisma( 'text', $op, $log )->withMaxTokens( $max > 0 ? $max : null );
    }


    /**
     * Maps MCP tool classes and provider tool names to Prisma tools, skipping uninstalled classes.
     *
     * @param array<int, string|Adapter> $tools MCP tool classes, provider tool names like "web_search" or Prisma tools
     * @param int|null $max Maximum number of calls per MCP tool or NULL for no limit
     * @return array<int, Adapter> Prisma tools
     */
    public static function tools( array $tools, ?int $max = null ) : array
    {
        $result = [];

        foreach( $tools as $tool )
        {
            if( $tool instanceof Adapter ) {
                $result[] = $tool;
            } elseif( !str_contains( $tool, '\\' ) ) {
                $result[] = Tools::provider( $tool );
            } elseif( class_exists( $tool ) ) {
                $adapter = Tools::laravel( $tool )->failed( AiError::tool( ... ) );
                $result[] = $max !== null ? $adapter->max( $max ) : $adapter;
            }
        }

        return $result;
    }


    /**
     * Returns the configured Prisma provider with the request timeouts.
     *
     * @param string $type Media type, e.g. "text", "image" or "audio"
     * @param string $op AI operation, its configuration key is returned by config()
     * @param string|null $log Name the usage is logged as instead of the Prisma operation
     * @return Provider Provider instance
     * @throws PrismaException If no provider is configured
     */
    private static function prisma( string $type, string $op, ?string $log ) : Provider
    {
        $config = (array) config( self::config( $op ), [] );

        return Prisma::type( $type )->observe( Generated::observer( $log ) )
            ->using( $config['provider'] ?? null, $config )
            ->model( $config['model'] ?? null )
            ->withClientOptions( ['timeout' => self::timeout(), 'connect_timeout' => self::CONNECT_TIMEOUT] );
    }


    /**
     * Returns the configured seconds a provider request may take.
     *
     * @return int Seconds or 0 for no limit
     */
    private static function timeout() : int
    {
        return max( 0, (int) config( 'cms.ai.timeout' ) );
    }
}
