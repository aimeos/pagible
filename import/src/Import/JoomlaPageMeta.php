<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Builds the browser titles and the meta data of the pages of the Joomla articles, categories and menu items.
 */
final readonly class JoomlaPageMeta
{
    /** Option of the menu item with the browser page title */
    public const PAGE_TITLE = 'page_title';
    /** Option of the article with its browser page title */
    protected const ARTICLE_PAGE_TITLE = 'article_page_title';
    /** Options of the menu item with the meta description and keywords */
    protected const META_DESCRIPTION = 'menu-meta_description';
    protected const META_KEYWORDS = 'menu-meta_keywords';
    /** Option of the menu item and meta data key of the articles and categories with the robots instructions */
    protected const ROBOTS = 'robots';


    /**
     * Initializes the object.
     *
     * @param JoomlaSite $site Settings of the Joomla site with the meta descriptions of the content languages
     */
    public function __construct( protected JoomlaSite $site )
    {
    }


    /**
     * Returns the browser title of the article page.
     *
     * Only the options of the menu item linking to the article are used, not the ones of the category menu item.
     *
     * @param string $articleTitle Title of the article
     * @param array<string, mixed> $attribs Options of the article
     * @param JoomlaDisplay $display Menu item linking to the article if there's one
     * @return string Browser title
     */
    public function articleTitle( string $articleTitle, array $attribs, JoomlaDisplay $display ): string
    {
        $item = $display->item;
        // Joomla 3 and 4 use the title of the menu item linking to the article instead of the browser title of the article
        $menuTitle = $item && $this->site->version->upTo( JoomlaVersion::JOOMLA_4 ) ? JoomlaRow::menuTitle( $item ) : '';
        $browserTitle = JoomlaValue::string( $attribs[self::ARTICLE_PAGE_TITLE] ?? null );

        return $this->title( $display->params, $menuTitle, $browserTitle, $articleTitle );
    }


    /**
     * Returns the meta data of the article or category page.
     *
     * The meta data of the article or category is used before the one of the menu item and the meta
     * description of the content language if there's none. Joomla 4+ doesn't show the meta keywords anymore.
     *
     * @param \stdClass|null $row Article, category or NULL for menu headings
     * @param array<string, mixed> $params Options of the menu item
     * @param string $language Joomla language tag the page is shown in
     * @return array<string, mixed> Meta data keyed by type
     */
    public function data( ?\stdClass $row, array $params, string $language ): array
    {
        $description = JoomlaValue::firstFilled( JoomlaValue::string( $row->metadesc ?? null ), JoomlaValue::string( $params[self::META_DESCRIPTION] ?? null ),
            $this->site->description( $language ) );
        // Joomla 4+ doesn't show the meta keywords anymore
        $keywords = $this->site->version->upTo( JoomlaVersion::JOOMLA_3 )
            ? JoomlaValue::firstFilled( JoomlaValue::string( $row->metakey ?? null ), JoomlaValue::string( $params[self::META_KEYWORDS] ?? null ) )
            : '';
        $metadata = JoomlaValue::decode( $row->metadata ?? null );
        $robots = JoomlaValue::firstFilled( JoomlaValue::string( $metadata[self::ROBOTS] ?? null ), JoomlaValue::string( $params[self::ROBOTS] ?? null ) );

        return array_filter( [
            'meta-tags' => Elements::metaTags( $description, $keywords ),
            'robots' => Elements::robots( $robots ),
        ] );
    }


    /**
     * Returns the browser title of the page.
     *
     * @param array<string, mixed> $params Options of the menu item whose page title is used first, empty if there's none
     * @param string ...$titles Titles used in this order if the menu item has no page title
     * @return string First title which isn't empty
     */
    public function title( array $params, string ...$titles ): string
    {
        return JoomlaValue::firstFilled( JoomlaValue::string( $params[self::PAGE_TITLE] ?? null ), ...$titles );
    }
}
