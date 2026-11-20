<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Chat;

use Aimeos\Cms\AiClient;
use Aimeos\Prisma\Tools\Adapter\Adapter;
use Aimeos\Prisma\Tools\Adapter\Decorator;


/**
 * Removes page config, images and styles from the arguments of chat tools saving drafts.
 *
 * Injected instructions could add scripts or leak data via image URLs that draft previews load
 * without a click. URLs can be encoded in many ways, so all images are removed (CMS images use file fields).
 */
final class Drafts extends Decorator
{
    /**
     * Image patterns and replacements, escaped characters can't form Markdown or HTML syntax.
     */
    private const IMAGES = [
        // Markdown images become links
        '/!(?=\[)/' => '',
        '/<img\b[^>]*>/i' => '',
        // styles can load images by url(), also after "/" or quotes
        '/(?<![\w:.-])style\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]*)/i' => '',
    ];


    /**
     * Executes the tool without page config and images.
     *
     * @param array<string, mixed> $arguments Tool arguments
     * @return string Tool result
     */
    public function __invoke( array $arguments ) : string
    {
        // the CSP allows custom JS and CSS by hash, so previews would run them
        unset( $arguments['config'] );

        array_walk_recursive( $arguments, function( mixed &$value ) {
            if( is_string( $value ) ) {
                $value = (string) preg_replace( array_keys( self::IMAGES ), self::IMAGES, $value );
            }
        } );

        return parent::__invoke( $arguments );
    }


    /**
     * Returns the filtered Prisma tools of the MCP tool classes.
     *
     * @param array<int, string> $tools MCP tool classes saving drafts
     * @return array<int, self> Prisma tools
     */
    public static function tools( array $tools ) : array
    {
        return array_map( fn( Adapter $tool ) => new self( $tool ), AiClient::tools( $tools ) );
    }
}
