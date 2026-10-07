<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


/**
 * Audit event for unversioned changes of page language variants.
 *
 * Covers source language changes, added, deleted, restored and purged languages,
 * Translate runs and "Ignore changes".
 */
final class Translation
{
    use Dispatchable;

    /**
     * @param string $action Action, e.g. "source", "added", "dropped", "restored", "purged", "translated" or "ignored"
     * @param string $pageId Page UUID
     * @param array<int, string> $langs Affected language codes
     * @param string $editor Editor name
     * @param bool $ai Whether AI was used
     * @param string $tenant Tenant ID
     */
    public function __construct(
        public readonly string $action,
        public readonly string $pageId,
        public readonly array $langs,
        public readonly string $editor,
        public readonly bool $ai = false,
        public readonly string $tenant = '',
    ) {}
}
