<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Home menu item of the Joomla site used for the root page and the article imported into the root page.
 *
 * Created once per run by Homes::root() before the URLs are assigned because they depend on the article of the root page.
 */
final readonly class HomeRoot
{
    /**
     * Initializes the object.
     *
     * @param \stdClass|null $item Home menu item of the site used for the root page, which can be one of another menu
     * @param int|null $articleId ID of the article imported into the root page or NULL if there's none
     * @param list<\stdClass> $translations Translations of the article imported into the root page, their links point to the home pages of their languages
     * @param string|null $reason Why the article of the home menu item isn't imported into the root page or NULL if it is or there's none
     * @param \stdClass|null $article Article imported into the root page, fetched once for the access checks and the import
     */
    public function __construct(
        public ?\stdClass $item = null,
        public ?int $articleId = null,
        public array $translations = [],
        public ?string $reason = null,
        public ?\stdClass $article = null
    ) {
    }


    /**
     * Returns the home menu item whose article is imported into the root page.
     *
     * @param \stdClass $item Home menu item of the site
     * @param int $articleId ID of the article imported into the root page
     * @param list<\stdClass> $translations Translations of the article
     * @param \stdClass|null $article Fetched article imported into the root page or NULL to fetch it when it's imported
     */
    public static function withArticle( \stdClass $item, int $articleId, array $translations = [], ?\stdClass $article = null ): self
    {
        return new self( $item, $articleId, $translations, article: $article );
    }


    /**
     * Returns the home menu item without article imported into the root page.
     *
     * @param \stdClass|null $item Home menu item of the site or NULL if there's none
     * @param string|null $reason Why the article of the home menu item isn't imported into the root page or NULL if it shows no article
     */
    public static function withoutArticle( ?\stdClass $item = null, ?string $reason = null ): self
    {
        return new self( $item, reason: $reason );
    }


    /**
     * Returns the ID of the article the home menu item links to, also if it isn't imported into the root page.
     *
     * Unlike $articleId, which is only set if the article is imported into the root page, this is the article
     * the home menu item shows in Joomla, e.g. one which is imported as normal page because the root page exists.
     *
     * @return int|null Article ID or NULL if the home menu item shows no single article or there's no home menu item
     */
    public function linkedArticleId(): ?int
    {
        return $this->item ? MenuItem::article( $this->item ) : null;
    }


    /**
     * Returns the fetched article imported into the root page and its translations.
     *
     * @return array<int|string, \stdClass>|null Article and translations by ID or NULL if the article wasn't fetched
     */
    public function rows(): ?array
    {
        if( !$this->article ) {
            return null;
        }

        $rows = [$this->article->id => $this->article];

        foreach( $this->translations as $row ) {
            $rows[$row->id] = $row;
        }

        return $rows;
    }


    /**
     * Tests if the article is the one imported into the root page.
     *
     * Used for all comparisons with the article of the root page, see same().
     *
     * @param int|string|null $id ID of the article from an array key or a database row
     */
    public function isArticle( int|string|null $id ): bool
    {
        return self::same( $id, $this->articleId );
    }


    /**
     * Tests if the menu item is the home menu item used for the root page.
     *
     * The home menu item is read separately from the imported menu, so the rows are compared by their IDs.
     */
    public function is( \stdClass $menuItem ): bool
    {
        return $this->item !== null && self::same( $menuItem->id ?? null, $this->item->id ?? null );
    }


    /**
     * Tests if both IDs are the same.
     *
     * IDs from array keys and database rows can be integers or strings, so they are compared as strings.
     * Missing IDs are never the same, also not if both are missing, so NULL can't match an empty string.
     * Used for all comparisons with the IDs of the home menu item and the article of the root page.
     *
     * @param mixed $a ID from an array key or a database row
     * @param mixed $b Other ID from an array key or a database row
     */
    public static function same( mixed $a, mixed $b ): bool
    {
        return ( is_int( $a ) || is_string( $a ) ) && ( is_int( $b ) || is_string( $b ) ) && $a !== '' && (string) $a === (string) $b;
    }
}
