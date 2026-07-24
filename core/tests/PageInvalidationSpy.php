<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Events\PagesInvalidated;


class PageInvalidationSpy
{
    /** @var array<int, list<array{domain: string, path: string}>> */
    public array $batches = [];


    public function handle( PagesInvalidated $event ) : void
    {
        $this->batches[] = $event->routes;
    }


    public function reset() : void
    {
        $this->batches = [];
    }
}
