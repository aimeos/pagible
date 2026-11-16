<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Where Joomla shows the links of an article which are entered in the "Images and Links" tab: above or below the text.
 */
final readonly class LinkPositions
{
    /** Option of the articles and the article pages with the position of the links */
    protected const URLS_POSITION = 'urls_position';
    /** Position of the links above the text */
    protected const URLS_ABOVE = '0';
    /** Position of the links below the text */
    protected const URLS_BELOW = '1';


    /**
     * Initializes the object.
     *
     * Use of() to get the positions of the links of an article.
     *
     * @param bool $above TRUE if the links are shown above the text
     * @param bool $below TRUE if the links are shown below the text
     */
    private function __construct(
        public bool $above,
        public bool $below
    ) {
    }


    /**
     * Returns where Joomla shows the links of the article.
     *
     * Joomla shows the links above the text only if neither the article nor the options hide them or show them below,
     * so shown above excludes shown below.
     *
     * @param array<string, mixed> $urls Links and their options of the article
     * @param array<string, mixed> $options Options of the article page
     */
    public static function of( array $urls, array $options ): self
    {
        $positions = [Value::string( $urls[self::URLS_POSITION] ?? null ), Value::string( $options[self::URLS_POSITION] ?? null )];

        return new self( !array_diff( $positions, ['', self::URLS_ABOVE] ), in_array( self::URLS_BELOW, $positions, true ) );
    }


    /**
     * Tests if the links are shown above or below the text.
     */
    public function shown(): bool
    {
        return $this->above || $this->below;
    }
}
