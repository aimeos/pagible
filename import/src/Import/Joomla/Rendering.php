<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Describes what the content plugins prepare: an article text, an article teaser or a category description, its field values, language and menu item.
 */
final readonly class Rendering
{
    /**
     * Initializes the object.
     *
     * Use article(), category() or teaser() to create the renderings.
     *
     * @param bool $description TRUE for category descriptions, FALSE for article texts, teasers and the content of modules
     * @param array<int|string, list<string>> $values Custom field values of the article or category by field ID
     * @param string $language Joomla language tag of the article or category
     * @param int $itemId ID of the menu item Joomla shows the article or category in, 0 for none
     * @param bool $teaser TRUE for the intro text of articles guests can't access, FALSE otherwise
     */
    private function __construct(
        private bool $description,
        public array $values,
        public string $language,
        public int $itemId,
        private bool $teaser = false
    ) {
    }


    /**
     * Returns the rendering of an article text.
     *
     * @param array<int|string, list<string>> $values Custom field values of the article by field ID
     * @param string $language Joomla language tag of the article
     * @param int $itemId ID of the menu item Joomla shows the article in, 0 for none
     */
    public static function article( array $values, string $language, int $itemId ): self
    {
        return new self( false, $values, $language, $itemId );
    }


    /**
     * Returns the rendering of a category description.
     *
     * @param array<int|string, list<string>> $values Custom field values of the category by field ID
     * @param string $language Joomla language tag of the category
     * @param int $itemId ID of the menu item Joomla shows the category in, 0 for none
     */
    public static function category( array $values, string $language, int $itemId ): self
    {
        return new self( true, $values, $language, $itemId );
    }


    /**
     * Returns the rendering of the teaser of the article guests can't access.
     *
     * In onContentPrepare( 'com_content.article' ), the "Content - Fields" plugin replaces the field shortcodes in
     * $item->introtext as well as in $item->text (Joomla 5 plugins/content/fields/src/Extension/Fields.php:81-84,
     * Joomla 3.10 plugins/content/fields/fields.php:66-70), before the teaser layout (components/com_content/tmpl/article/default.php:127)
     * prepares the intro text again in the "content.prepare" step. Thus, the field shortcodes of teasers are replaced.
     * The "Content - Page Break" plugin only changes $item->text in the "com_content.article" context, so the page breaks
     * of teasers are kept as they are. The modules are replaced and the e-mail addresses cloaked like for the article texts.
     */
    public function teaser(): self
    {
        return new self( $this->description, $this->values, $this->language, $this->itemId, true );
    }


    /**
     * Returns the Joomla component context of the custom fields, Fields::ARTICLE_CONTEXT or Fields::CATEGORY_CONTEXT.
     *
     * This is the value of the "context" column of the Joomla fields like "com_content.article", unlike FieldContext,
     * which describes how the field values are rendered.
     */
    public function componentContext(): string
    {
        return $this->description ? Fields::CATEGORY_CONTEXT : Fields::ARTICLE_CONTEXT;
    }


    /**
     * Returns the rendering of the modules embedded by shortcodes.
     *
     * Joomla's mod_custom prepares them in its "mod_custom.content" context, which has no field values, in the language
     * and menu item of the page. Otherwise, they are prepared like article texts.
     */
    public function embedded(): self
    {
        return self::article( [], $this->language, $this->itemId );
    }


    /**
     * Tests if Joomla shows the page breaks as pages, which are only the ones of articles but not of their teasers.
     */
    public function pageBreaks(): bool
    {
        return !$this->description && !$this->teaser;
    }


    /**
     * Tests if Joomla prepares the HTML twice, which are only category descriptions.
     */
    public function preparedTwice(): bool
    {
        return $this->description;
    }
}
