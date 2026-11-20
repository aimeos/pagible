<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Intro and full text, decoded images, links and options of a Joomla article returned by Texts.
 */
final readonly class Text
{
    /**
     * Initializes the object.
     *
     * Articles without text row, e.g. deleted ones, have an empty text, so they are imported like articles without any text.
     *
     * @param string $introtext HTML of the intro text
     * @param string $fulltext HTML of the full text after the "Read more" break
     * @param array<string, mixed> $images Decoded intro and full text images with their alternative texts and captions
     * @param array<string, mixed> $urls Decoded links of the article and their options
     * @param array<string, mixed> $attribs Decoded options of the article
     * @param bool $teaser TRUE if only the teaser of the article is shown to guests, see asTeaser()
     */
    public function __construct(
        public string $introtext = '',
        public string $fulltext = '',
        public array $images = [],
        public array $urls = [],
        public array $attribs = [],
        public bool $teaser = false
    ) {
    }


    /**
     * Returns the text of a Joomla article row.
     *
     * @param \stdClass $row Article row with intro and full text, the images, links and options as JSON
     */
    public static function of( \stdClass $row ): self
    {
        return new self(
            Value::text( $row->introtext ?? null ),
            Value::text( $row->fulltext ?? null ),
            Article::images( $row ),
            Value::decode( $row->urls ?? null ),
            Value::decode( $row->attribs ?? null )
        );
    }


    /**
     * Returns the text as teaser guests see if they can't access the article.
     *
     * Teasers show the intro image and text instead of the full text image and text.
     */
    public function asTeaser(): self
    {
        return new self( $this->introtext, $this->fulltext, $this->images, $this->urls, $this->attribs, true );
    }
}
