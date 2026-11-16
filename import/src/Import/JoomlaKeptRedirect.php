<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Old URL path of a kept Joomla redirect and the URL it redirects to.
 */
final readonly class JoomlaKeptRedirect
{
    /**
     * Initializes the object.
     *
     * @param string $path Old URL path without leading slash, which isn't used by an imported page
     * @param string $url New URL of the Joomla redirect as it's stored, e.g. "index.php?option=com_content&view=article&id=5" or "about"
     * @param string $dir Directory of the old URL relative new URLs are relative to without leading and trailing slash,
     *  e.g. "old" for "old/page" and "old/page" for "old/page/" like browsers resolve them
     */
    public function __construct(
        public string $path,
        public string $url,
        public string $dir = ''
    ) {
    }
}
