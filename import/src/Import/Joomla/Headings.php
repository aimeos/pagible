<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Elements;


/**
 * Builds the headings Joomla shows above the articles and categories, the page heading of the menu item and the title.
 *
 * @phpstan-import-type ContentElements from Elements
 */
final readonly class Headings
{
    /** Option showing the title of articles and home pages */
    protected const ARTICLE_TITLE = 'show_title';
    /** Option showing the title of categories */
    protected const CATEGORY_TITLE = 'show_category_title';
    /** Option of the menu item with the page heading */
    protected const PAGE_HEADING = 'page_heading';


    /**
     * Returns the headings Joomla shows above the article, the page heading of the menu item and the title.
     *
     * Articles without own menu item show the page heading of the menu item of their category. The page heading
     * of the menu item is used then, otherwise its browser title or its title.
     *
     * @param string $title Title of the article
     * @param array<string, mixed> $options Options of the page
     * @param Display $display Menu item linking to the article or the one of its category Joomla uses otherwise
     * @return list<ContentElements> Heading contents
     */
    public function article( string $title, array $options, Display $display ): array
    {
        $menuItem = $display->menuItem();
        $pageHeading = $menuItem ? $this->pageHeading( $options, $this->menuTitle( $options, $menuItem ) ) : '';

        return $this->headings( $pageHeading, Value::enabled( $options, self::ARTICLE_TITLE ) ? $title : '' );
    }


    /**
     * Returns the page heading Joomla shows above the content of a home menu item which shows no article or category.
     *
     * The page heading of the menu item is used, otherwise its browser title or its title.
     *
     * @param array<string, mixed> $options Options of the page
     * @param \stdClass $menuItem Home menu item, e.g. showing the featured articles
     * @return list<ContentElements> Heading contents
     */
    public function home( array $options, \stdClass $menuItem ): array
    {
        return $this->headings( $this->pageHeading( $options, $this->menuTitle( $options, $menuItem ) ), '' );
    }


    /**
     * Returns the headings Joomla shows above the category, the page heading of the menu item and the title.
     *
     * Categories without own menu item show the page heading of the menu item of their parent category. The page heading
     * of the menu item is used then, otherwise the title of the category. The subheading of the menu item is shown
     * before the category title, which is only shown if enabled, but the subheading is shown in any case.
     *
     * @param string $title Title of the category
     * @param string $subheading Subheading of the menu item or an empty string for none
     * @param array<string, mixed> $options Options of the page
     * @param Display $display Menu item linking to the category or the one of its parent category Joomla uses otherwise
     * @return list<ContentElements> Heading contents
     */
    public function category( string $title, string $subheading, array $options, Display $display ): array
    {
        $menuItem = $display->menuItem();
        // categories shown in the menu item of their parent category use their own title
        $default = $display->item ? $this->menuTitle( $options, $display->item ) : $title;
        $pageHeading = $menuItem ? $this->pageHeading( $options, $default ) : '';

        $shown = Value::enabled( $options, self::CATEGORY_TITLE );
        $titleHeading = match( true ) {
            $subheading === '' => $shown ? $title : '',
            $shown => $subheading . ' ' . $title,
            default => $subheading,
        };

        return $this->headings( $pageHeading, $titleHeading );
    }


    /**
     * Returns the contents of the page heading and the title heading.
     *
     * @param string $pageHeading Page heading of the menu item or an empty string if it's not shown
     * @param string $title Title of the article or category or an empty string if it's not shown
     * @return list<ContentElements> Heading contents, the title is a second level heading below the page heading
     */
    protected function headings( string $pageHeading, string $title ): array
    {
        $headings = [];

        if( $pageHeading !== '' ) {
            $headings[] = $this->heading( $pageHeading, 1 );
        }

        if( $title !== '' ) {
            $headings[] = $this->heading( $title, $headings ? 2 : 1 );
        }

        return $headings;
    }


    /**
     * Returns the content of a heading.
     *
     * @param string $title Text of the heading
     * @param int $level Level of the heading, 1 for the top level
     * @return ContentElements Heading content without files
     */
    protected function heading( string $title, int $level ): array
    {
        return Elements::content( Elements::create( 'heading', ['level' => $level, 'title' => $title] ) );
    }


    /**
     * Returns the page heading of the menu item if it's shown.
     *
     * @param array<string, mixed> $options Options of the page
     * @param string $default Page heading used if the menu item has none
     * @return string Page heading or an empty string if it's not shown
     */
    protected function pageHeading( array $options, string $default ): string
    {
        if( !Value::enabled( $options, Options::PAGE_HEADING_SHOWN, false ) ) {
            return '';
        }

        return Value::string( $options[self::PAGE_HEADING] ?? null ) ?: $default;
    }


    /**
     * Returns the browser title of the menu item or its title if it has none.
     *
     * @param array<string, mixed> $options Options of the page
     * @param \stdClass $menuItem Menu item Joomla shows the article or category in
     * @return string Browser title or title of the menu item
     */
    protected function menuTitle( array $options, \stdClass $menuItem ): string
    {
        return Value::string( $options[PageMeta::PAGE_TITLE] ?? null ) ?: Row::menuTitle( $menuItem );
    }
}
