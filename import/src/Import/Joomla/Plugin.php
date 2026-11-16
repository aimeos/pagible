<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Access level and options of an installed and enabled Joomla plugin.
 */
final readonly class Plugin
{
    /**
     * Initializes the object.
     *
     * @param int $access Access level of the plugin, plugins of Joomla versions without access level are public
     * @param array<array-key, mixed> $params Options of the plugin
     */
    public function __construct(
        public int $access = Levels::PUBLIC_LEVEL,
        public array $params = []
    ) {
    }
}
