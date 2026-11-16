<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Describes for what the custom fields are rendered: the values shown for an article or category, how their HTML is prepared and the language.
 */
final readonly class FieldContext
{
    /**
     * Initializes the object.
     *
     * @param array<int|string, list<string>> $values Values of the fields shown for the article or category by field ID, fields without key aren't shown
     * @param (\Closure(string): string)|null $prepare Replaces the shortcodes of the content plugins in the HTML of editor and text area fields
     *  or NULL to keep them
     * @param string|null $locale Language code the texts are translated to, NULL for the current locale
     */
    public function __construct(
        public array $values,
        public ?\Closure $prepare = null,
        public ?string $locale = null
    ) {
    }


    /**
     * Returns the HTML of an editor or text area field prepared by the content plugins.
     *
     * @param string $html HTML of the field value
     * @return string Prepared HTML or the unchanged HTML if it isn't prepared
     */
    public function prepared( string $html ): string
    {
        return $this->prepare ? ( $this->prepare )( $html ) : $html;
    }
}
