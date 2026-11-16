<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Site-wide options of the Joomla router, the same for the routes of all languages.
 */
final readonly class JoomlaRouteOptions
{
    /**
     * Initializes the object.
     *
     * @param bool $noIds TRUE if the segments of the articles and categories contain no IDs, the "sef_ids" option of the articles component
     *  or its hidden "sef_advanced_link" option if Joomla 3 uses the legacy router
     * @param bool $strict TRUE if the "strictrouting" option of the "System - SEF" plugin in Joomla 5.2+ is on, so the home menu item isn't used
     * @param bool $legacy TRUE if Joomla 3 uses its legacy router because the "sef_advanced" option of the articles component is off
     */
    public function __construct(
        public bool $noIds,
        public bool $strict,
        public bool $legacy = false
    ) {
    }
}
