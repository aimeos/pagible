<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Chat;


/**
 * Conversation history sent by the chat client.
 */
final class History
{
    /**
     * Maximum bytes of the history, it's resent on every agent step.
     */
    private const BYTES = 100000;


    /**
     * Keeps the well-formed user/assistant turns within the maximum size.
     *
     * @param array<mixed> $messages Raw messages
     * @return list<array{role: string, content: string}> Validated history
     */
    public static function clean( array $messages ) : array
    {
        $result = [];
        $prev = null;

        foreach( $messages as $entry )
        {
            $msg = (array) $entry;
            $role = $msg['role'] ?? null;
            $content = $msg['content'] ?? null;

            if( !in_array( $role, ['user', 'assistant'], true ) || !is_string( $content ) || $content === '' ) {
                continue;
            }

            // e.g. Gemini rejects consecutive turns of the same role
            if( $role === $prev ) {
                array_pop( $result );
            }

            $result[] = ['role' => $role, 'content' => $content];
            $prev = $role;
        }

        // the current prompt is appended as next user turn
        if( $prev === 'user' ) {
            array_pop( $result );
        }

        $size = array_sum( array_map( fn( $msg ) => strlen( $msg['content'] ), $result ) );

        while( $size > self::BYTES ) {
            $size -= strlen( array_shift( $result )['content'] ?? '' );
        }

        // providers require the first turn to be the user's
        if( ( $result[0]['role'] ?? null ) === 'assistant' ) {
            array_shift( $result );
        }

        return $result;
    }
}
