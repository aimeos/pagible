<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Page data and content of a Joomla article, category or menu item built by Pages.
 *
 * @phpstan-import-type PageContent from Pages
 */
final readonly class Page
{
    /**
     * Initializes the object.
     *
     * @param array<string, mixed> $data Page data
     * @param PageContent $content Content elements, IDs of the used files and meta data of the page
     * @param bool $teaser TRUE if the page contains only the intro text of an article guests can't access
     * @param string|null $reason Why guests can't see the page or NULL if they can
     */
    public function __construct(
        public array $data,
        public array $content,
        public bool $teaser = false,
        public ?string $reason = null
    ) {
    }


    /**
     * Returns what is done with the page in the import language because guests can't see it, for the notices.
     *
     * @return string Teasers are imported with the intro text only, other pages are disabled
     */
    public function notice(): string
    {
        return $this->teaser ? 'Imported only the intro text' : 'Disabled';
    }


    /**
     * Returns what is done with the page of a translation because guests can't see it, for the notices.
     *
     * @return string Teasers are imported with the intro text only, other pages are disabled
     */
    public function translationNotice(): string
    {
        return $this->teaser ? 'Imported only the intro text of translation' : 'Disabled translation';
    }
}
