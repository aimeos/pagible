<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Builds the escaped HTML snippets of the Joomla content like lists, links and images.
 */
class JoomlaMarkup
{
    /**
     * Relations of the links opened in a new window, e.g. of URL fields to other sites or the links of the articles.
     *
     * Joomla also adds "nofollow", but the HTML sanitizer of Pagible only keeps "noreferrer noopener" for links opening a new window.
     */
    protected const NEW_WINDOW_REL = 'noopener noreferrer';


    /**
     * Returns the HTML list of the items.
     *
     * @param array<int, string> $items HTML of the list items
     * @param string $class CSS class of the list, which is escaped
     * @return string HTML list or an empty string if there are no items
     */
    public static function list( array $items, string $class ): string
    {
        if( $items === [] ) {
            return '';
        }

        return '<ul class="' . e( $class ) . '"><li>' . implode( '</li><li>', $items ) . '</li></ul>';
    }


    /**
     * Returns the HTML link to the URL.
     *
     * @param string $url URL of the link
     * @param string $text Text of the link, which is escaped
     * @param bool $newWindow TRUE to open the link in a new window, e.g. if the menu item or the article sets it
     * @return string HTML link
     */
    public static function link( string $url, string $text, bool $newWindow = false ): string
    {
        $attrs = $newWindow ? ' rel="' . self::NEW_WINDOW_REL . '" target="_blank"' : '';
        return '<a href="' . e( $url ) . '"' . $attrs . '>' . e( $text ) . '</a>';
    }


    /**
     * Returns the HTML image tag.
     *
     * @param string $src URL or path of the image
     * @param string|null $alt Alternative text or NULL to leave it out
     * @return string HTML image tag
     */
    public static function image( string $src, ?string $alt = '' ): string
    {
        return '<img src="' . e( $src ) . '"' . ( $alt !== null ? ' alt="' . e( $alt ) . '"' : '' ) . '>';
    }
}
