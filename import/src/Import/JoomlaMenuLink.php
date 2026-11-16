<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * What a Joomla menu item shows, parsed once from its type and link.
 */
final readonly class JoomlaMenuLink
{
    /** Kinds of the menu items */
    public const KIND_FOLDER = 'folder';
    public const KIND_LINK = 'link';
    public const KIND_ARTICLE = 'article';
    public const KIND_CATEGORY = 'category';
    public const KIND_CATEGORIES = 'categories';
    /** Kind of the menu items of other views, components and types */
    public const KIND_OTHER = '';

    /** Kinds of the menu items showing a category or listing its subcategories */
    public const CATEGORY_KINDS = [self::KIND_CATEGORY, self::KIND_CATEGORIES];

    /** Kinds of the menu items of the views of the articles component with ID by view */
    public const VIEW_KINDS = [
        JoomlaMenuItem::VIEW_ARTICLE => self::KIND_ARTICLE,
        JoomlaMenuItem::VIEW_CATEGORY => self::KIND_CATEGORY,
        JoomlaMenuItem::VIEW_CATEGORIES => self::KIND_CATEGORIES,
    ];


    /**
     * Initializes the object.
     *
     * @param string $kind Kind of the menu item, one of the KIND_* constants
     * @param int|null $id ID of the article or category or NULL if there's none
     * @param string|null $view View of the articles component like "article" or "featured" or NULL if the menu item shows none
     * @param string $layout Layout of the view like "blog" or an empty string for the default layout
     */
    public function __construct(
        public string $kind,
        public ?int $id = null,
        public ?string $view = null,
        public string $layout = ''
    ) {
    }


    /**
     * Parses what the menu item shows.
     *
     * Headings, separators and URL menu items without URL are folders, the other URL and alias menu items are links.
     * Menu items of the article, category and categories views of the articles component get the ID of the article
     * or category. Lists of the root category have no ID because it's 1 in the database but 0 or missing in the links
     * of most menu items. Other views like the featured articles are of kind KIND_OTHER.
     */
    public static function of( \stdClass $menuItem ): self
    {
        $type = JoomlaMenuItem::type( $menuItem );

        if( JoomlaMenuItem::folderType( $type, $menuItem ) ) {
            return new self( self::KIND_FOLDER );
        }

        if( in_array( $type, JoomlaMenuItem::LINK_TYPES, true ) ) {
            return new self( self::KIND_LINK );
        }

        // the query is parsed once, it contains the view, the ID and the layout, other types than components show no view
        $query = JoomlaMenuItem::query( $menuItem );
        $view = $type === JoomlaMenuItem::TYPE_COMPONENT ? JoomlaMenuItem::queryView( $query ) : null;
        $layout = is_string( $value = $query['layout'] ?? null ) ? JoomlaMenuItem::normalLayout( $value ) : '';

        if( ( $kind = self::VIEW_KINDS[(string) $view] ?? null ) === null ) {
            return new self( self::KIND_OTHER, null, $view, $layout );
        }

        $id = JoomlaUri::id( $query['id'] ?? null );
        // the list of the subcategories of the root category lists all categories
        $valid = $id > 0 && ( $kind !== self::KIND_CATEGORIES || $id !== JoomlaRow::ROOT_ID );

        return new self( $kind, $valid ? $id : null, $view, $layout );
    }


    /**
     * Returns the ID of the article the menu item shows.
     *
     * @return int|null Article ID or NULL if the menu item shows no article
     */
    public function article(): ?int
    {
        return $this->kind === self::KIND_ARTICLE ? $this->id : null;
    }


    /**
     * Returns the ID of the category the menu item shows or lists the subcategories of.
     *
     * @return int|null Category ID or NULL if the menu item shows no category or lists the root category
     */
    public function category(): ?int
    {
        return in_array( $this->kind, self::CATEGORY_KINDS, true ) ? $this->id : null;
    }
}
