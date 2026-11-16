<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * URL path Joomla uses for an article, category or menu item and the alias used if Joomla has none.
 *
 * Returned by JoomlaPaths.
 */
final readonly class JoomlaRoutePath
{
    /**
     * Initializes the object.
     *
     * @param string|null $route URL path Joomla uses or NULL if Joomla has no menu item for the row and its categories
     * @param string $alias Alias or slug of the title of the row
     */
    public function __construct(
        public ?string $route,
        public string $alias
    ) {
    }


    /**
     * Returns the URL path Joomla uses for the row and its alias.
     *
     * @param string|null $route URL path Joomla uses or NULL if there's none
     * @param \stdClass $row Article, category or menu item with alias or title
     */
    public static function of( ?string $route, \stdClass $row ): self
    {
        return new self( $route, JoomlaRow::aliasOrSlug( $row ) );
    }


    /**
     * Returns the URL path Joomla uses or the alias if Joomla has none.
     */
    public function path(): string
    {
        return $this->route ?? $this->alias;
    }


    /**
     * Returns the URL path of the non-empty parts.
     *
     * @param array<int, string> $parts URL path parts like ["blog", "news", "5-article"]
     * @return string URL path like "blog/news/5-article"
     */
    public static function join( array $parts ): string
    {
        return implode( '/', array_filter( $parts, fn( $part ) => $part !== '' ) );
    }
}
