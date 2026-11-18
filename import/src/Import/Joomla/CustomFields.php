<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Selects and renders the custom fields Joomla shows for the articles and categories.
 *
 * Fields assigned to categories are only shown for articles in these categories or their subcategories
 * and for these categories and their subcategories. With language filter, articles and categories in a
 * specific language only show the fields of their language and the ones for all languages.
 */
class CustomFields
{
    /** @var array<int|string, array<int|string, list<string>>> Values of the fields of the last category description by category ID, see descriptionValues() */
    protected array $described = [];


    /**
     * Initializes the object.
     *
     * @param Site $site Settings of the Joomla site with the languages and the enabled plugins
     * @param Extras $extras Fetches the custom field values of the articles and categories and the categories of the fields
     * @param ModulePreparer $preparer Prepares the HTML of the editor and text area fields by the content plugins
     * @param Fields $articleFields Renders the custom fields of the articles Joomla shows
     * @param Fields $categoryFields Renders the custom fields of the categories Joomla shows
     */
    public function __construct(
        protected Site $site,
        protected Extras $extras,
        protected ModulePreparer $preparer,
        protected Fields $articleFields,
        protected Fields $categoryFields
    ) {
    }


    /**
     * Returns the values of the custom fields Joomla shows for the article.
     *
     * @param string $language Joomla language tag the article is shown in
     * @return array<int|string, list<string>> Values of the shown fields by field ID, empty lists if there's no stored value
     */
    public function articleValues( \stdClass $article, string $language ): array
    {
        $values = $this->languageValues( Fields::ARTICLE_CONTEXT, $this->extras->articleValues( $article->id ), $language );
        return $this->assignedValues( $values, Article::catId( $article ) );
    }


    /**
     * Returns the values of the custom fields Joomla shows for the category.
     *
     * These are the values of the description whose fields are assigned to the category, see descriptionValues().
     *
     * @return array<int|string, list<string>> Values of the shown fields by field ID, empty lists if there's no stored value
     */
    public function categoryValues( \stdClass $category ): array
    {
        return $this->assignedValues( $this->descriptionValues( $category ), (int) $category->id );
    }


    /**
     * Returns the values of the custom fields for the shortcodes of the category description.
     *
     * Joomla replaces the field shortcodes of the description without checking the categories of the fields,
     * so the fields assigned to other categories are used too. The values of the last category are kept because
     * the category page needs them for its fields too, see categoryValues().
     *
     * @return array<int|string, list<string>> Values of the fields by field ID, empty lists if there's no stored value
     */
    public function descriptionValues( \stdClass $category ): array
    {
        if( !isset( $this->described[$category->id] ) )
        {
            $values = $this->languageValues( Fields::CATEGORY_CONTEXT, $this->extras->categoryValues( $category->id ), Row::language( $category ) );
            $this->described = [$category->id => $values];
        }

        return $this->described[$category->id];
    }


    /**
     * Returns the HTML of the custom fields Joomla shows at the position of the article or category page.
     *
     * The fields are shown by the "System - Fields" plugin. With language filter, pages for all languages
     * only show the fields for all languages and the ones of the current language, which is the import language.
     * The content plugins replace the module shortcodes in editor and text area fields, but not the field shortcodes.
     *
     * @param int $position Position of the fields, one of the Fields constants
     * @param Rendering $rendering Article or category page with the values of the custom fields shown for it, its language and menu item
     * @return string HTML of the fields or an empty string if there are none
     */
    public function position( int $position, Rendering $rendering ): string
    {
        if( !$this->site->plugins->systemFields() ) {
            return '';
        }

        $renderer = $this->renderer( $rendering->componentContext() );
        $shown = array_filter( $renderer->all(), fn( $field ) => $this->site->shows( Row::language( $field ), $rendering->language ) );

        if( !( $values = array_intersect_key( $rendering->values, $shown ) ) ) {
            return '';
        }

        $prepare = fn( string $html ) => $this->preparer->value( $html, $rendering );
        return $renderer->position( $position, new FieldContext( $values, $prepare, $this->site->languages->locale( $rendering->language ) ) );
    }


    /**
     * Returns the HTML of a custom field or field group shortcode.
     *
     * The module shortcodes in editor and text area fields are replaced afterwards together with the ones of the text.
     *
     * @param Shortcode $shortcode Field shortcode like "{field 3}" or "{fieldgroup *}" with the ID of the field or field group
     * @param Rendering $rendering Article text or category description, its field values, language and menu item
     * @return string HTML replacing the shortcode
     */
    public function shortcode( Shortcode $shortcode, Rendering $rendering ): string
    {
        if( !$rendering->values ) {
            return '';
        }

        $renderer = $this->renderer( $rendering->componentContext() );
        $context = new FieldContext( $rendering->values, locale: $this->site->languages->locale( $rendering->language ) );
        $id = $shortcode->args['id'] ?? '';

        return $shortcode->name === Shortcodes::FIELD_GROUP ? $renderer->group( $id, $context ) : $renderer->field( $id, $context );
    }


    /**
     * Returns the values of the custom fields of the language of the article or category.
     *
     * @param string $context Context of the custom fields, Fields::ARTICLE_CONTEXT or Fields::CATEGORY_CONTEXT
     * @param array<int|string, list<string>> $stored Stored values of the article or category by field ID
     * @param string $language Joomla language tag of the article or category
     * @return array<int|string, list<string>> Values of the fields by field ID in the order of the fields, empty lists if there's no stored value
     */
    protected function languageValues( string $context, array $stored, string $language ): array
    {
        $values = [];

        foreach( $this->renderer( $context )->all() as $id => $field )
        {
            // like Joomla, articles and categories for all languages get the fields of all languages, unlike the shown ones in position()
            if( $language === Languages::ALL || $this->site->shows( Row::language( $field ), $language ) ) {
                $values[$id] = $stored[$id] ?? [];
            }
        }

        return $values;
    }


    /**
     * Returns the values of the custom fields assigned to the category or one of its parent categories.
     *
     * Fields without categories are assigned to all categories.
     *
     * @param array<int|string, list<string>> $values Values of the fields by field ID, see languageValues()
     * @param int $catId ID of the category of the article or the ID of the category itself
     * @return array<int|string, list<string>> Values of the assigned fields by field ID
     */
    protected function assignedValues( array $values, int $catId ): array
    {
        $categories = $this->extras->fieldCategories();
        $path = null;

        foreach( array_keys( $values ) as $id )
        {
            if( !empty( $categories[$id] ) )
            {
                $path ??= $this->site->categoryIds( $catId );

                if( !array_intersect( $categories[$id], $path ) ) {
                    unset( $values[$id] );
                }
            }
        }

        return $values;
    }


    /**
     * Returns the renderer for the custom fields of the articles or categories guests can see.
     *
     * @param string $context Context of the custom fields, Fields::ARTICLE_CONTEXT or Fields::CATEGORY_CONTEXT
     */
    protected function renderer( string $context ): Fields
    {
        return $context === Fields::CATEGORY_CONTEXT ? $this->categoryFields : $this->articleFields;
    }
}
