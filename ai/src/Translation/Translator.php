<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Translation;

use Aimeos\Cms\AiClient;
use Aimeos\Cms\Exception;
use Aimeos\Cms\Exceptions\ThrottledException;
use Aimeos\Cms\Permission;
use Aimeos\Prisma\Contracts\Provider;
use Illuminate\Contracts\Auth\Authenticatable;


/**
 * Translates texts in chunks within the rate limit of the tenant.
 */
final class Translator
{
    /**
     * Maximum number of texts per provider request.
     */
    private const MAX_TEXTS = 50;

    /**
     * Maximum number of bytes per provider request.
     */
    private const MAX_BYTES = 50000;

    /**
     * AI operation translating the texts.
     */
    private const OP = 'translate';


    /**
     * Splits texts into chunks sent in one provider request each.
     *
     * @param array<int, string> $texts Texts to translate
     * @param int $max Maximum texts per chunk
     * @param int $size Maximum bytes per chunk, larger texts get their own chunk
     * @return array<int, array<int, string>> List of chunks
     */
    public static function chunks( array $texts, int $max = self::MAX_TEXTS, int $size = self::MAX_BYTES ) : array
    {
        $chunks = [];
        $chunk = [];
        $bytes = 0;

        foreach( array_values( $texts ) as $text )
        {
            if( $chunk && ( count( $chunk ) >= $max || $bytes + strlen( $text ) > $size ) )
            {
                $chunks[] = $chunk;
                $chunk = [];
                $bytes = 0;
            }

            $chunk[] = $text;
            $bytes += strlen( $text );
        }

        if( $chunk ) {
            $chunks[] = $chunk;
        }

        return $chunks;
    }


    /**
     * Returns the translate callback for queued jobs (rate limited per tenant) or NULL if not allowed.
     *
     * @param Authenticatable|null $user User or NULL inside Permission::system()
     * @return (\Closure(array<int, string>, string, ?string, ?string): array<int, string>)|null Translate callback
     */
    public static function for( ?Authenticatable $user ) : ?\Closure
    {
        if( !AiClient::available( self::OP ) || !Permission::allowed( 'text:translate', $user ) ) {
            return null;
        }

        return fn( array $texts, string $to, ?string $from = null, ?string $context = null ) : array
            => self::translate( $texts, $to, $from, $context, true );
    }


    /**
     * Translates texts, interactive requests are rate limited per user by the API instead.
     *
     * @param array<int, string> $texts Texts to translate
     * @param string $to Target language code
     * @param string|null $from Source language code, auto-detected if NULL
     * @param string|null $context Additional context like the topic
     * @param bool $limited TRUE to reserve the requests from the tenant rate limit
     * @return array<int, string> Translated texts in the same order
     * @throws ThrottledException If the tenant rate limit would be exceeded
     */
    public static function translate( array $texts, string $to, ?string $from = null, ?string $context = null, bool $limited = false ) : array
    {
        $options = self::options();
        $xml = self::xml( $options );

        // providers mustn't get an empty context
        $ctx = $context ?: null;

        if( $ctx !== null && $xml ) {
            $ctx = self::escape( $ctx );
        }

        $result = self::send( $xml ? self::encode( $texts, $options ) : $texts, $to, $from, $ctx, $options, $limited );

        return $xml ? self::decode( $result ) : $result;
    }


    /**
     * Decodes the XML entities of the translated texts.
     *
     * @param array<int, string> $texts Translated texts
     * @return array<int, string> Decoded texts
     */
    private static function decode( array $texts ) : array
    {
        return array_map( fn( $text ) => htmlspecialchars_decode( $text, ENT_XML1 | ENT_QUOTES ), $texts );
    }


    /**
     * Escapes the texts for XML except the ignored tags.
     *
     * @param array<int, string> $texts Texts to escape
     * @param array<string, mixed> $options Provider request options
     * @return array<int, string> Escaped texts
     */
    private static function encode( array $texts, array $options ) : array
    {
        $tags = [];

        foreach( (array) ( $options['ignore_tags'] ?? [] ) as $tag )
        {
            $tags['&lt;' . $tag . '&gt;'] = '<' . $tag . '>';
            $tags['&lt;/' . $tag . '&gt;'] = '</' . $tag . '>';
        }

        return array_map( fn( $text ) => strtr( self::escape( $text ), $tags ), $texts );
    }


    /**
     * Escapes the text for XML.
     *
     * @param string $text Text to escape
     * @return string Escaped text
     */
    private static function escape( string $text ) : string
    {
        return htmlspecialchars( $text, ENT_XML1 | ENT_NOQUOTES, 'UTF-8' );
    }


    /**
     * Returns the provider request options including the DeepL defaults.
     *
     * @return array<string, mixed> Request options
     */
    private static function options() : array
    {
        $options = AiClient::options( self::OP );

        if( AiClient::name( self::OP ) === 'deepl' )
        {
            $options += [
                'ignore_tags' => ['x'],
                'tag_handling' => 'xml',
                'preserve_formatting' => true,
                'model_type' => 'prefer_quality_optimized',
            ];
        }

        return $options;
    }


    /**
     * Sends the texts in chunks to the translation provider.
     *
     * @param array<int, string> $texts Texts to translate
     * @param string $to Target language code
     * @param string|null $from Source language code, auto-detected if NULL
     * @param string|null $context Additional context like the topic
     * @param array<string, mixed> $options Request options
     * @param bool $limited TRUE to reserve the requests from the tenant rate limit
     * @return array<int, string> Translated texts in the same order
     * @throws ThrottledException If the tenant rate limit would be exceeded
     */
    private static function send( array $texts, string $to, ?string $from, ?string $context, array $options, bool $limited = false ) : array
    {
        $chunks = self::chunks( $texts );

        if( !$chunks ) {
            return [];
        }

        return AiClient::call( 'text', self::OP, function( Provider $provider, array $opts ) use ( $chunks, $to, $from, $context, $limited ) {
            // for all chunks before the first is sent
            $provider->ensure( 'translate' );

            if( $limited ) {
                Limit::reserve( count( $chunks ) );
            }

            $result = [];

            foreach( $chunks as $chunk )
            {
                $translated = array_values( $provider->translate( $chunk, $to, $from, $context, $opts )->texts() ); // @phpstan-ignore-line method.notFound

                if( count( $translated ) !== count( $chunk ) || in_array( null, $translated, true ) ) {
                    throw new Exception( __( 'Incomplete translation returned' ) );
                }

                array_push( $result, ...$translated );
            }

            return $result;
        }, count( $chunks ), $options );
    }


    /**
     * Tests if the provider parses the texts as XML.
     *
     * @param array<string, mixed> $options Provider request options
     * @return bool TRUE for XML
     */
    private static function xml( array $options ) : bool
    {
        return ( $options['tag_handling'] ?? null ) === 'xml';
    }
}
