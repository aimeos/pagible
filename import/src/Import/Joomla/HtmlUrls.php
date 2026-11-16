<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Html as BaseHtml;


/**
 * Rewrites the Joomla URLs in the article texts.
 */
class HtmlUrls
{
    /** Lookahead skipping URLs which start with a slash, a protocol, "#" or a single quote, the protocols are matched like the "System - SEF" plugin does */
    protected const RELATIVE_URL = '(?!\/|[a-zA-Z0-9\-]+:|#|\')';
    /** Regular expression matching the relative URLs of the double quoted "href", "src" and "poster" attributes */
    protected const URL_ATTR_PATTERN = '/(\s(?:href|src|poster)=")' . self::RELATIVE_URL . '([^"]*)"/';
    /** Regular expression matching the double quoted "srcset" attributes */
    protected const SRCSET_PATTERN = '/(\ssrcset=")([^"]+)"/';
    /** Regular expression matching the URLs with their optional width or density in an image set */
    protected const SRCSET_SOURCE_PATTERN = '/[^\s]+\s*(?:[\d\.]+[wx])?(?:,\s*)?/i';
    /** Regular expression matching a relative URL of an image set */
    protected const RELATIVE_SOURCE_PATTERN = '/^' . self::RELATIVE_URL . '(.+)/';


    /**
     * Replaces the links to Joomla pages like "index.php?option=com_content&view=article&id=5" by the URL paths of their pages.
     *
     * The links are matched like Links routes the URLs by the script, see Origin::indexUrlFragment(), so query
     * strings relative to the script like "?Itemid=3" or "/?Itemid=3" and the script without query string like "index.php" too.
     *
     * @param string $html HTML with links
     * @param \Closure(array<array-key, mixed>): ?string $resolve Returns the URL path of the page for the query parameters or NULL if it's unknown
     * @param array<int, string> $sites Hosts and paths of the Joomla site whose absolute HTTP(S) and scheme-relative links are replaced too, e.g. "example.com/joomla"
     * @return string HTML with replaced links, unknown links are kept
     */
    public static function links( string $html, \Closure $resolve, array $sites = [] ): string
    {
        // PCRE caches the compiled pattern, so building it for each text is cheap
        $pattern = '/((?<![\w-])href=)(["\'])' . self::sitePrefix( $sites ) . Origin::indexUrlFragment( '\#"\\\'' ) . '([^"\'#]*)(#[^"\']*)?\2/i';

        return (string) preg_replace_callback( $pattern, function( $match ) use ( $resolve ) {
            $path = $resolve( Uri::query( BaseHtml::decode( $match[3] ) ) );

            // the fragment is decoded like the query, so its HTML entities aren't encoded twice
            return $path !== null ? $match[1] . $match[2] . e( $path . BaseHtml::decode( $match[4] ?? '' ) ) . $match[2] : $match[0];
        }, $html );
    }


    /**
     * Makes the relative URLs of links, images and videos absolute like the "System - SEF" plugin of Joomla does.
     *
     * Only double quoted URLs which don't start with a slash, a protocol, "#" or a single quote are changed,
     * the URLs of image sets too.
     *
     * Unlike Joomla, the directory of Joomla sites in subdirectories isn't added: the links point to the imported
     * pages, which are at the root of their domain, and the image sources are relative to the base URL of the
     * files, which contains the directory already, see Origin::fileSource().
     *
     * @param string $html HTML with relative URLs, e.g. "images/logo.png"
     * @return string HTML with absolute URL paths, e.g. "/images/logo.png"
     */
    public static function absolute( string $html ): string
    {
        $html = (string) preg_replace( self::URL_ATTR_PATTERN, '$1/$2"', $html );

        return (string) preg_replace_callback( self::SRCSET_PATTERN, function( $match ) {
            preg_match_all( self::SRCSET_SOURCE_PATTERN, $match[2], $sources );
            $absolute = array_map( fn( $src ) => (string) preg_replace( self::RELATIVE_SOURCE_PATTERN, '/$1', $src ), $sources[0] );
            return $match[1] . implode( '', $absolute ) . '"';
        }, $html );
    }


    /**
     * Returns the optional regex prefix matching absolute URLs of the Joomla site.
     *
     * Like Origin::local(), URL paths without host of Joomla sites in subdirectories start with the directory,
     * e.g. "/joomla/index.php?Itemid=3".
     *
     * @param array<int, string> $sites Hosts and paths of the Joomla site, e.g. "example.com/joomla"
     * @return string Regex part matching the scheme, host, port and path or the path alone followed by a slash or a question mark, empty if there are no sites
     */
    protected static function sitePrefix( array $sites ): string
    {
        $hosts = $bases = [];

        foreach( $sites as $site )
        {
            if( ( $site = trim( $site, '/' ) ) === '' ) {
                continue;
            }

            $parts = explode( '/', $site, 2 );
            // the directory of the site is case-sensitive like in Origin, the host isn't
            $path = isset( $parts[1] ) ? '(?-i:\/' . preg_quote( $parts[1], '/' ) . ')' : '';
            // hosts without port match the default ports of HTTP and HTTPS only, see Origin::local()
            $port = str_contains( $parts[0], ':' ) ? '' : '(?::(?:80|443))?';
            $hosts[] = preg_quote( $parts[0], '/' ) . $port . $path;

            if( $path !== '' ) {
                $bases[$path] = $path;
            }
        }

        if( $hosts === [] ) {
            return '';
        }

        $alternatives = ['(?:https?:)?\/\/(?:' . implode( '|', $hosts ) . ')', ...array_values( $bases )];
        // the host or the directory must end before the slash of the script or the query string, e.g. not "/joomlaindex.php"
        return '(?:(?:' . implode( '|', $alternatives ) . ')(?=[\/?]))?';
    }
}
