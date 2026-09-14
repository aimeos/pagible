<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Events;

use Illuminate\Foundation\Events\Dispatchable;


final class WebhookChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly string $actor,
        public readonly string $webhookId,
        public readonly int $eventCount,
        public readonly string $tenant,
        public readonly string $oldHost = '',
        public readonly string $newHost = '',
    ) {}
}
