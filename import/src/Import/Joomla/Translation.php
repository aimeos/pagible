<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Associated Joomla article, category or menu item imported as variant of a page in the language it's shown in.
 */
final readonly class Translation
{
    /**
     * Initializes the object.
     *
     * @param \stdClass $row Associated article, category or menu item in another language
     * @param Kind $kind Type of the row
     * @param string $tag Joomla language tag the row is shown in
     * @param \stdClass|null $showing Menu item showing the article instead of its own one, e.g. the home menu item of its language
     */
    public function __construct(
        public \stdClass $row,
        public Kind $kind,
        public string $tag,
        public ?\stdClass $showing = null
    ) {
    }


    /**
     * Returns the translation shown in the language of the row.
     *
     * @param \stdClass $row Associated article, category or menu item in another language
     * @param Kind $kind Type of the row
     */
    public static function of( \stdClass $row, Kind $kind ): self
    {
        return new self( $row, $kind, Row::language( $row ) );
    }


    /**
     * Returns the article shown by the home menu item of another language in the language of the menu item.
     *
     * Articles for all languages are shown in the language of the home menu item, the same article can be shown by several ones.
     *
     * @param \stdClass $article Article shown by the home menu item
     * @param \stdClass $item Home menu item of the other language
     * @param string $tag Joomla language tag of the home menu item
     */
    public static function home( \stdClass $article, \stdClass $item, string $tag ): self
    {
        return new self( $article, Kind::Article, $tag, $item );
    }
}
