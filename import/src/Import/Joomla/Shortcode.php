<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Shortcode of a Joomla content plugin like "{loadposition banner}" or "{field 3}" found by Shortcodes.
 *
 * @phpstan-import-type Args from Shortcodes
 */
final readonly class Shortcode
{
    /**
     * Initializes the object.
     *
     * @param string $name Lower case shortcode name like "loadposition" or "field"
     * @param Args $args Arguments of the shortcode by name, see Shortcodes::fields() and Shortcodes::modules()
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
