<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;


class PageInvalidationSpy
{
    /** @var list<array{domain: string, path: string|null}> */
    public array $events = [];


    public function handle( PageInvalidated $event ) : void
    {
        $this->events[] = ['domain' => $event->domain, 'path' => $event->path];
    }


    public function reset() : void
    {
        $this->events = [];
    }
}
