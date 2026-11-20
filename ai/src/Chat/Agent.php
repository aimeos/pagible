<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Chat;

use Aimeos\Cms\AiClient;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\ConflictException;
use Aimeos\Cms\Permission;
use Aimeos\Cms\Tools as CmsTools;
use Aimeos\Cms\Toolset;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Builds and runs the agent requests of the chat.
 */
final class Agent
{
    /**
     * Permissions which allow using the chat.
     */
    private const PERMISSIONS = ['page:chat', 'element:chat', 'file:chat'];

    /**
     * Tools offered in the chat.
     *
     * Content may contain injected instructions, so no tool fetches URLs chosen by the model to prevent leaks.
     */
    private const TOOLS = [
        ...Toolset::READ,
        // read-only tools of the optional metrics package
        CmsTools\GetKeywordVolume::class,
        CmsTools\GetRelatedKeywords::class,
        // provider web search can't send content to URLs chosen by the model
        'web_search',
    ];

    /**
     * Tools which only save page and element drafts, editors review them before publishing.
     *
     * Drafts strips config and images from their previews, which could run custom JS or load model chosen URLs.
     */
    private const DRAFTS = [
        CmsTools\AddPage::class,
        CmsTools\SavePage::class,
        CmsTools\CopyPage::class,
        CmsTools\AddElement::class,
        CmsTools\SaveElement::class,
    ];


    /**
     * Tests if the user may use the chat.
     *
     * @param Authenticatable $user Authenticated user
     * @return bool TRUE if the user may chat about pages, elements or files
     */
    public static function allowed( Authenticatable $user ) : bool
    {
        foreach( self::PERMISSIONS as $action )
        {
            if( Permission::can( $action, $user ) ) {
                return true;
            }
        }

        return false;
    }


    /**
     * Validates the chat input, acquires a stream slot of the user and returns the function streaming the answer.
     *
     * @param Authenticatable $user User asking
     * @param string $prompt Current user message
     * @param string $context Additional context sent by the client, e.g. the current page
     * @param array<mixed> $messages Prior messages [{role, content}] sent by the client
     * @return \Closure(): void Writes the answer to the client using Stream::run()
     * @throws ConflictException If all stream slots of the user are in use
     * @throws Exception If the input is too large or the prompt is empty
     */
    public static function chat( Authenticatable $user, string $prompt, string $context, array $messages ) : \Closure
    {
        AiClient::checkInput( ['prompt' => $prompt, 'context' => $context, 'messages' => $messages], __( 'Chat input' ) );

        $prompt = trim( $prompt );

        if( $prompt === '' ) {
            throw new Exception( __( 'Prompt must not be empty' ) );
        }

        $system = AiClient::system( view( 'cms::prompts.chat' )->render(), view( 'cms::prompts.write' )->render(), $context );
        $steps = AiClient::steps();
        $prisma = AiClient::prompt( AiClient::WRITE, $system, [...self::TOOLS, ...Drafts::tools( self::DRAFTS )], $steps, Toolset::DISCOVERY, 'chat' );

        if( $history = History::clean( $messages ) ) {
            $prisma->withMessages( $history );
        }

        $options = AiClient::options( AiClient::WRITE );
        $slot = Slot::acquire( $user, $steps );

        // providers without streaming support return the complete answer
        $request = static fn() => $prisma->has( 'stream' )
            ? $prisma->stream( $prompt, [], $options ) // @phpstan-ignore-line method.notFound
            : $prisma->write( $prompt, [], $options ); // @phpstan-ignore-line method.notFound

        return static function() use ( $request, $steps, $slot ) {
            try {
                Stream::run( $request, $steps );
            } finally {
                $slot->release();
            }
        };
    }
}
