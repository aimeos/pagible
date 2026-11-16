<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;


/**
 * Functions and settings of the import command the objects importing the Joomla site use.
 *
 * The import command passes all functions, so missing wiring fails instead of silently doing nothing.
 * Objects created without command, e.g. in tests, use forTests() with functions doing nothing instead.
 */
final readonly class JoomlaHost
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


    /**
     * Returns the functions and settings for objects created without import command, e.g. in tests.
     *
     * Functions which aren't given do nothing: no messages are shown, no images are imported and the page data is empty.
     *
     * @param (\Closure(string): void)|null $warn Shows a warning, ignored if NULL
     * @param (\Closure(string): void)|null $warnOnce Shows a warning only once, ignored if NULL
     * @param (\Closure(string): void)|null $debug Shows a message in the debug output, ignored if NULL
     * @param (\Closure(string, string): ?string)|null $image Imports an image and returns the file ID, no images are imported if NULL
     * @param (\Closure(string, string, int, string): array<string, mixed>)|null $pageData Returns the page data, no data if NULL
     * @param \DateTimeZone|null $timezone Time zone of the Joomla site, UTC if NULL
     */
    public static function forTests( ?\Closure $warn = null, ?\Closure $warnOnce = null, ?\Closure $debug = null,
        ?\Closure $image = null, ?\Closure $pageData = null, ?\DateTimeZone $timezone = null ): self
    {
        $none = function( string $msg ): void {};

        return new self(
            warn: $warn ?? $none,
            warnOnce: $warnOnce ?? $none,
            debug: $debug ?? $none,
            image: $image ?? fn( string $path, string $alt ): ?string => null,
            pageData: $pageData ?? fn( string $title, string $slug, int $status, string $tag ): array => [],
            timezone: $timezone ?? new \DateTimeZone( JoomlaDate::TIMEZONE )
        );
    }
}
