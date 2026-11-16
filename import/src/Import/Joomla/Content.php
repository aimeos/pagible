<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;

use Aimeos\Cms\Import\Elements;
use Aimeos\Cms\Import\Html as BaseHtml;


/**
 * Builds the content elements of the Joomla articles and categories like Joomla renders them.
 *
 * The content plugins prepare the texts, the module shortcodes and e-mail addresses are handled by ModulePreparer
 * and the custom fields are rendered by CustomFields. The custom fields, tags and links are shown around the text
 * and the links to Joomla pages are replaced by the URL paths of the imported pages.
 *
 * @phpstan-import-type ContentElements from Elements
 */
class Content
{
    /** Tags of all languages are shown */
    protected const TAGS_ALL = 'all';
    /** Tags of the current language and for all languages are shown */
    protected const TAGS_CURRENT = 'current_language';
    /** Option of the tags component containing the languages of the shown tags */
    protected const TAGS_LANGUAGE = 'tag_list_language_filter';
    /** CSS classes of the lists of the links and the tags */
    protected const LINKS_CLASS = 'links';
    protected const TAGS_CLASS = 'tags';
    /** Link targets opening a new window, "1" with and "2" without browser navigation, "3" as modal dialog */
    protected const NEW_WINDOW = ['1', '2', '3'];
    /** Keys of the URL, the text and the target of the three links of the articles */
    protected const LINKS = [['urla', 'urlatext', 'targeta'], ['urlb', 'urlbtext', 'targetb'], ['urlc', 'urlctext', 'targetc']];


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the enabled plugins and the options of the tags component
     * @param BaseHtml $html Converts the HTML into content elements
     * @param Links $links Replaces the links to Joomla pages by the URL paths of the imported pages
     * @param Extras $extras Fetches the public tags of the articles and categories
     * @param CustomFields $fields Selects and renders the custom fields of the articles and categories
     * @param ModulePreparer $modules Replaces the module shortcodes and cloaks the e-mail addresses
     */
    public function __construct(
        protected Site $site,
        protected BaseHtml $html,
        protected Links $links,
        protected Extras $extras,
        protected CustomFields $fields,
        protected ModulePreparer $modules
    ) {
    }


    /**
     * Returns the rendering of the article text with the values of the custom fields Joomla shows for the article.
     *
     * @param string $language Joomla language tag the article is shown in
     * @param int $itemId ID of the menu item Joomla shows the article in, 0 for none
     */
    public function articleRendering( \stdClass $article, string $language, int $itemId ): Rendering
    {
        return Rendering::article( $this->fields->articleValues( $article, $language ), $language, $itemId );
    }


    /**
     * Returns the rendering of the category page with the values of the custom fields Joomla shows for the category.
     *
     * @param int $itemId ID of the menu item Joomla shows the category in, 0 for none
     */
    public function categoryRendering( \stdClass $category, int $itemId ): Rendering
    {
        return Rendering::category( $this->fields->categoryValues( $category ), Row::language( $category ), $itemId );
    }


    /**
     * Returns the rendering of the category description with the values of the custom fields of its field shortcodes.
     *
     * Joomla replaces the field shortcodes of the description without checking the categories of the fields.
     *
     * @param int $itemId ID of the menu item Joomla shows the category in, 0 for none
     */
    public function descriptionRendering( \stdClass $category, int $itemId ): Rendering
    {
        return Rendering::category( $this->fields->descriptionValues( $category ), Row::language( $category ), $itemId );
    }


    /**
     * Returns the list of the public tags of the article.
     *
     * @param string $language Joomla language tag the article is shown in
     * @return ContentElements|null Content or NULL if there are no public tags
     */
    public function articleTags( \stdClass $article, string $language ): ?array
    {
        return $this->tagContent( $this->extras->articleTags( $article->id ), $language );
    }


    /**
     * Returns the list of the public tags of the category.
     *
     * @return ContentElements|null Content or NULL if there are no public tags
     */
    public function categoryTags( \stdClass $category ): ?array
    {
        return $this->tagContent( $this->extras->categoryTags( $category->id ), Row::language( $category ) );
    }


    /**
     * Returns the content elements of an article text or category description.
     *
     * The page breaks of articles are replaced by headings, the shortcodes of the content plugins by their output
     * (teasers keep their page breaks, see Rendering::teaser()),
     * the e-mail addresses are linked or unlinked like the "Content - Email Cloaking" plugin does and the links to Joomla
     * pages are replaced by the URL paths of the imported pages.
     *
     * @param Rendering $rendering Article text or category description, its field values, language and menu item
     * @return ContentElements|null Content or NULL if there's no text
     */
    public function content( string $html, Rendering $rendering ): ?array
    {
        if( trim( $html ) === '' ) {
            return null;
        }

        return $this->elements( $this->prepare( $html, $rendering ), $rendering->language );
    }


    /**
     * Returns the custom fields Joomla shows at the position of the article or category page.
     *
     * The content plugins replace the shortcodes in editor and text area fields, but not the ones of the fields.
     *
     * @param int $position Position of the fields, one of the Fields constants
     * @param Rendering $rendering Article or category page with the values of the custom fields shown for it, its language and menu item
     * @return ContentElements|null Content or NULL if there are no fields
     */
    public function fieldContent( int $position, Rendering $rendering ): ?array
    {
        $html = $this->fields->position( $position, $rendering );

        return $html !== '' ? $this->elements( $html, $rendering->language ) : null;
    }


    /**
     * Returns the list of the links of the article, which are entered in the "Images and Links" tab.
     *
     * Links to Joomla pages are replaced by the URL paths of the imported pages, links without text show their URL.
     * Like Joomla, the content plugins don't change the links.
     * Links opened in a popup or modal window by Joomla are opened in a new window instead. The "rel" attributes
     * are the ones the sanitized HTML keeps for links opening a new window, Joomla also adds "nofollow".
     *
     * @param array<string, mixed> $urls Decoded links of the article with the keys "urla", "urlatext", "targeta", "urlb", etc.
     * @param array<string, mixed> $options Options of the article page with the default targets "targeta", "targetb" and "targetc"
     * @param string $language Joomla language tag of the article or Languages::ALL
     * @return ContentElements|null Content or NULL if there are no links
     */
    public function linkContent( array $urls, array $options, string $language ): ?array
    {
        $items = [];

        foreach( self::LINKS as [$urlKey, $textKey, $targetKey] )
        {
            if( ( $url = Value::string( $urls[$urlKey] ?? null ) ) !== '' )
            {
                $label = Value::string( $urls[$textKey] ?? null ) ?: $url;
                $target = Value::string( $urls[$targetKey] ?? null ) ?: Value::string( $options[$targetKey] ?? null );
                $items[] = Markup::link( $url, $label, in_array( $target, self::NEW_WINDOW, true ) );
            }
        }

        return $items ? $this->elements( Markup::list( $items, self::LINKS_CLASS ), $language ) : null;
    }


    /**
     * Prepares the HTML of an article text or category description by the content plugins like Joomla.
     *
     * The shortcodes are replaced, the page breaks of articles become headings and the e-mail addresses are cloaked.
     * Joomla prepares category descriptions twice, so the shortcodes in the output of the first run are replaced too.
     *
     * @param Rendering $rendering Article text or category description, its field values, language and menu item
     * @return string Prepared HTML
     */
    protected function prepare( string $html, Rendering $rendering ): string
    {
        $html = $this->prepareOnce( $html, $rendering );
        return $rendering->preparedTwice() ? $this->prepareOnce( $html, $rendering ) : $html;
    }


    /**
     * Prepares the HTML once by the content plugins.
     *
     * The "Content - Fields" plugin replaces the field shortcodes before the "Content - Load Modules" plugin
     * replaces the module shortcodes, so module shortcodes in editor and text area fields are replaced too.
     *
     * @param Rendering $rendering Article text or category description, its field values, language and menu item
     * @return string Prepared HTML
     */
    protected function prepareOnce( string $html, Rendering $rendering ): string
    {
        return $this->modules->cloak( $html, function( $html ) use ( $rendering ) {
            if( $this->site->plugins->contentFields() ) {
                $html = Shortcodes::fields( $html, fn( Shortcode $shortcode ) => $this->fields->shortcode( $shortcode, $rendering ) );
            }

            $html = $this->modules->replaceModules( $html, $rendering );

            // only the page breaks of articles are shown as pages
            if( $rendering->pageBreaks() && $this->site->plugins->pageBreak() ) {
                $html = PageBreaks::headings( $html, $this->site->languages->locale( $rendering->language ) );
            }

            return $html;
        } );
    }


    /**
     * Returns the content elements of the HTML whose links to Joomla pages are replaced.
     *
     * @param string $language Joomla language tag of the content the links are resolved in
     * @return ContentElements Content elements and IDs of the used files
     */
    protected function elements( string $html, string $language ): array
    {
        return $this->html->elements( $this->links->replace( $html, $language ) );
    }


    /**
     * Returns the list of the public tags of the article or category.
     *
     * The tags component can show only the tags of the current language or of a configured one and the ones for all languages.
     *
     * @param list<\stdClass> $tags Public Joomla tags with title, access level and language
     * @param string $language Joomla language tag of the article or category
     * @return ContentElements|null Content or NULL if there are no shown tags
     */
    protected function tagContent( array $tags, string $language ): ?array
    {
        $items = [];
        $filter = $this->tagFilter();

        foreach( $tags as $tag )
        {
            $tagLanguage = Row::language( $tag );

            $shown = match( $filter ) {
                self::TAGS_ALL => true,
                self::TAGS_CURRENT => $this->site->inPageLanguage( $tagLanguage, $language ),
                default => in_array( $tagLanguage, [Languages::ALL, $filter], true ),
            };

            if( $shown ) {
                $items[] = e( Row::title( $tag ) );
            }
        }

        return $items ? $this->elements( Markup::list( $items, self::TAGS_CLASS ), $language ) : null;
    }


    /**
     * Returns the languages of the tags the tags component shows.
     *
     * @return string One of the TAGS_* constants or a Joomla language tag
     */
    protected function tagFilter(): string
    {
        $params = $this->site->extensions->componentParams( Extensions::COMPONENT_TAGS );
        return Value::string( $params[self::TAGS_LANGUAGE] ?? null ) ?: self::TAGS_ALL;
    }
}
