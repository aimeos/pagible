<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Shortcode of a Joomla content plugin like "{loadposition banner}" or "{field 3}" found by JoomlaShortcodes.
 *
 * @phpstan-import-type Args from JoomlaShortcodes
 */
final readonly class JoomlaShortcode
{
    /**
     * Initializes the object.
     *
     * @param string $name Lower case shortcode name like "loadposition" or "field"
     * @param Args $args Arguments of the shortcode by name, see JoomlaShortcodes::fields() and JoomlaShortcodes::modules()
     * @param string $arg Raw arguments of the shortcode as written in the text, e.g. "banner,none"
     */
    public function __construct(
        public string $name,
        public array $args,
        public string $arg
    ) {
    }


    /**
     * Returns the shortcode as written in the text with the lower case name, e.g. "{loadposition banner,none}".
     *
     * Shortcodes with the same name and raw arguments insert the same modules.
     */
    public function text(): string
    {
        return '{' . $this->name . ' ' . $this->arg . '}';
    }
}
