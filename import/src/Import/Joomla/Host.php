<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import\Joomla;


/**
 * Functions and settings of the import command the objects importing the Joomla site use.
 *
 * The import command passes all functions, so missing wiring fails instead of silently doing nothing.
 */
final readonly class Host
{
    /**
     * Initializes the object.
     *
     * @param \Closure(string): void $warn Shows a warning
     * @param \Closure(string): void $warnOnce Shows a warning only once, e.g. about removed modules of shortcodes in several texts
     * @param \Closure(string): void $debug Shows a message in the debug output, e.g. about texts which weren't prefetched
     * @param \Closure(string, string): ?string $image Imports the image of the path or URL with the alternative text and returns the file ID or NULL
     * @param \Closure(string, string, int, string): array<string, mixed> $pageData Returns the page data for the title, URL path, status and page tag
     * @param \DateTimeZone $timezone Time zone of the Joomla site the dates are shown in
     */
    public function __construct(
        public \Closure $warn,
        public \Closure $warnOnce,
        public \Closure $debug,
        public \Closure $image,
        public \Closure $pageData,
        public \DateTimeZone $timezone
    ) {
    }
}
