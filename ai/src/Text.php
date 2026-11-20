<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;


/**
 * Formats texts returned by the AI providers.
 */
final class Text
{
    /**
     * Opening and closing quotes.
     */
    private const QUOTES = ['"' => '"', '“' => '”', '„' => '“', '«' => '»'];


    /**
     * Trims whitespace and enclosing quotes and cuts the text at a word boundary.
     *
     * @param string $text Generated text
     * @param int|null $max Maximum number of characters or NULL for no limit
     * @return string Shortened text
     */
    public static function shorten( string $text, ?int $max ) : string
    {
        $text = trim( $text );

        if( self::quoted( $text ) ) {
            $text = trim( mb_substr( $text, 1, -1 ) );
        }

        if( $max === null || mb_strlen( $text ) <= $max ) {
            return $text;
        }

        $cut = mb_substr( $text, 0, $max + 1 );
        $pos = mb_strrpos( $cut, ' ' );

        return rtrim( mb_substr( $cut, 0, $pos ?: $max ), " ,;:-" );
    }


    /**
     * Formats a number of seconds as a "HH:MM:SS.mmm" timestamp.
     *
     * @param float $seconds Time in seconds
     * @return string Formatted timestamp, e.g. "00:01:23.500"
     */
    public static function timestamp( float $seconds ) : string
    {
        // rounded first, so float errors don't lose a millisecond
        $millis = (int) round( $seconds * 1000 );
        $secs = intdiv( $millis, 1000 );

        return sprintf( "%02d:%02d:%02d.%03d", intdiv( $secs, 3600 ), intdiv( $secs % 3600, 60 ), $secs % 60, $millis % 1000 );
    }


    /**
     * Tests if only a matching pair of quotes encloses the text.
     *
     * @param string $text Trimmed text
     * @return bool TRUE if the enclosing quotes can be removed
     */
    private static function quoted( string $text ) : bool
    {
        $inner = mb_substr( $text, 1, -1 );

        return mb_strlen( $text ) > 1
            && ( self::QUOTES[mb_substr( $text, 0, 1 )] ?? null ) === mb_substr( $text, -1 )
            && str_replace( [...array_keys( self::QUOTES ), ...self::QUOTES], '', $inner ) === $inner;
    }
}
