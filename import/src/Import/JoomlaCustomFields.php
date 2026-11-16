<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Selects and renders the custom fields Joomla shows for the articles and categories.
 *
 * Fields assigned to categories are only shown for articles in these categories or their subcategories
 * and for these categories and their subcategories. With language filter, articles and categories in a
 * specific language only show the fields of their language and the ones for all languages.
 */
class JoomlaCustomFields
{
    /**
     * Initializes the object.
     *
     * @param JoomlaSite $site Settings of the Joomla site with the languages and the enabled plugins
     * @param JoomlaExtras $extras Fetches the custom field values of the articles and categories and the categories of the fields
     * @param JoomlaModulePreparer $preparer Prepares the HTML of the editor and text area fields by the content plugins
     * @param JoomlaFields $articleFields Renders the custom fields of the articles Joomla shows
     * @param JoomlaFields $categoryFields Renders the custom fields of the categories Joomla shows
     */
    public function __construct(
        protected JoomlaSite $site,
        protected JoomlaExtras $extras,
        protected JoomlaModulePreparer $preparer,
        protected JoomlaFields $articleFields,
        protected JoomlaFields $categoryFields
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
        $values = $this->extras->articleValues( $article->id );

        return $this->values( JoomlaFields::ARTICLE_CONTEXT, $values, JoomlaArticle::catId( $article ), $language );
    }


    /**
     * Returns the values of the custom fields Joomla shows for the category.
     *
     * @return array<int|string, list<string>> Values of the shown fields by field ID, empty lists if there's no stored value
     */
    public function categoryValues( \stdClass $category ): array
    {
        $values = $this->extras->categoryValues( $category->id );
        return $this->values( JoomlaFields::CATEGORY_CONTEXT, $values, (int) $category->id, JoomlaRow::language( $category ) );
    }


    /**
     * Returns the values of the custom fields for the shortcodes of the category description.
     *
     * Joomla replaces the field shortcodes of the description without checking the categories of the fields,
     * so the fields assigned to other categories are used too.
     *
     * @return array<int|string, list<string>> Values of the fields by field ID, empty lists if there's no stored value
     */
    public function descriptionValues( \stdClass $category ): array
    {
        $values = $this->extras->categoryValues( $category->id );
        return $this->values( JoomlaFields::CATEGORY_CONTEXT, $values, (int) $category->id, JoomlaRow::language( $category ), false );
    }


    /**
     * Returns the HTML of the custom fields Joomla shows at the position of the article or category page.
     *
     * The fields are shown by the "System - Fields" plugin. With language filter, pages for all languages
     * only show the fields for all languages and the ones of the current language, which is the import language.
     * The content plugins replace the module shortcodes in editor and text area fields, but not the field shortcodes.
     *
     * @param int $position Position of the fields, one of the JoomlaFields constants
     * @param JoomlaRendering $rendering Article or category page with the values of the custom fields shown for it, its language and menu item
     * @return string HTML of the fields or an empty string if there are none
     */
    public function position( int $position, JoomlaRendering $rendering ): string
    {
        if( !$this->site->plugins->systemFields() ) {
            return '';
        }

        $renderer = $this->renderer( $rendering->componentContext() );
        $shown = array_filter( $renderer->all(), fn( $field ) => $this->site->shows( JoomlaRow::language( $field ), $rendering->language ) );

        if( !( $values = array_intersect_key( $rendering->values, $shown ) ) ) {
            return '';
        }

        $prepare = fn( string $html ) => $this->preparer->value( $html, $rendering );
        return $renderer->position( $position, new JoomlaFieldContext( $values, $prepare, $this->site->languages->locale( $rendering->language ) ) );
    }


    /**
     * Returns the HTML of a custom field or field group shortcode.
     *
     * The module shortcodes in editor and text area fields are replaced afterwards together with the ones of the text.
     *
     * @param JoomlaShortcode $shortcode Field shortcode like "{field 3}" or "{fieldgroup *}" with the ID of the field or field group
     * @param JoomlaRendering $rendering Article text or category description, its field values, language and menu item
     * @return string HTML replacing the shortcode
     */
    public function shortcode( JoomlaShortcode $shortcode, JoomlaRendering $rendering ): string
    {
        if( !$rendering->values ) {
            return '';
        }

        $renderer = $this->renderer( $rendering->componentContext() );
        $context = new JoomlaFieldContext( $rendering->values, locale: $this->site->languages->locale( $rendering->language ) );
        $id = $shortcode->args['id'] ?? '';

        return $shortcode->name === JoomlaShortcodes::FIELD_GROUP ? $renderer->group( $id, $context ) : $renderer->field( $id, $context );
    }


    /**
     * Returns the values of the custom fields Joomla shows for the article or category.
     *
     * @param string $context Context of the custom fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     * @param array<int|string, list<string>> $stored Stored values of the article or category by field ID
     * @param int $catId ID of the category of the article or the ID of the category itself
     * @param string $language Joomla language tag of the article or category
     * @param bool $assigned FALSE to use the fields assigned to other categories too, see descriptionValues()
     * @return array<int|string, list<string>> Values of the shown fields by field ID, empty lists if there's no stored value
     */
    protected function values( string $context, array $stored, int $catId, string $language, bool $assigned = true ): array
    {
        $categories = $assigned ? $this->extras->fieldCategories() : [];
        $path = null;
        $values = [];

        foreach( $this->renderer( $context )->all() as $id => $field )
        {
            // like Joomla, articles and categories for all languages get the fields of all languages, unlike the shown ones in position()
            if( $language !== JoomlaLanguages::ALL && !$this->site->shows( JoomlaRow::language( $field ), $language ) ) {
                continue;
            }

            if( !empty( $categories[$id] ) )
            {
                $path ??= $this->site->categoryIds( $catId );

                if( !array_intersect( $categories[$id], $path ) ) {
                    continue;
                }
            }

            $values[$id] = $stored[$id] ?? [];
        }

        return $values;
    }


    /**
     * Returns the renderer for the custom fields of the articles or categories guests can see.
     *
     * @param string $context Context of the custom fields, JoomlaFields::ARTICLE_CONTEXT or JoomlaFields::CATEGORY_CONTEXT
     */
    protected function renderer( string $context ): JoomlaFields
    {
        return $context === JoomlaFields::CATEGORY_CONTEXT ? $this->categoryFields : $this->articleFields;
    }
}
