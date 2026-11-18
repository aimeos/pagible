<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * What a Joomla menu item shows, parsed once from its type and link.
 *
 * The parsed links are cached per menu item and parsed again if the type or link of the menu item changes, see of().
 */
final readonly class MenuLink
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
        MenuItem::VIEW_ARTICLE => self::KIND_ARTICLE,
        MenuItem::VIEW_CATEGORY => self::KIND_CATEGORY,
        MenuItem::VIEW_CATEGORIES => self::KIND_CATEGORIES,
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
     *
     * The parsed link is cached for the menu item as long as its type and link don't change.
     */
    public static function of( \stdClass $menuItem ): self
    {
        /** @var \WeakMap<\stdClass, array{0: mixed, 1: mixed, 2: self}>|null $cache Parsed links with the type and link they were parsed from by menu item */
        static $cache = null;
        $cache ??= new \WeakMap();

        $type = $menuItem->type ?? null;
        $link = $menuItem->link ?? null;

        if( isset( $cache[$menuItem] ) && $cache[$menuItem][0] === $type && $cache[$menuItem][1] === $link ) {
            return $cache[$menuItem][2];
        }

        $parsed = self::parse( $menuItem );
        $cache[$menuItem] = [$type, $link, $parsed];

        return $parsed;
    }


    /**
     * Parses what the menu item shows from its type and link.
     */
    protected static function parse( \stdClass $menuItem ): self
    {
        $type = MenuItem::type( $menuItem );

        if( MenuItem::folderType( $menuItem ) ) {
            return new self( self::KIND_FOLDER );
        }

        if( in_array( $type, MenuItem::LINK_TYPES, true ) ) {
            return new self( self::KIND_LINK );
        }

        // the query is parsed once, it contains the view, the ID and the layout, other types than components show no view
        $query = MenuItem::query( $menuItem );
        $view = $type === MenuItem::TYPE_COMPONENT ? MenuItem::queryView( $query ) : null;
        $layout = is_string( $value = $query['layout'] ?? null ) ? MenuItem::normalLayout( $value ) : '';

        if( ( $kind = self::VIEW_KINDS[(string) $view] ?? null ) === null ) {
            return new self( self::KIND_OTHER, null, $view, $layout );
        }

        $id = Uri::id( $query['id'] ?? null );
        // the list of the subcategories of the root category lists all categories
        $valid = $id > 0 && ( $kind !== self::KIND_CATEGORIES || $id !== Row::ROOT_ID );

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
