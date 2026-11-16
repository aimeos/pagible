<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Record key and language domain of an imported Joomla row whose URL path was claimed.
 */
final readonly class JoomlaUrlKey
{
    /**
     * Initializes the object.
     *
     * @param string $key Record key from JoomlaKind::key() or JoomlaKind::translationKey(), e.g. "a5" or "ta7"
     * @param string $domain Domain of the language or empty string for the domain of the imported pages
     */
    public function __construct(
        public string $key,
        public string $domain = ''
    ) {
    }
}
