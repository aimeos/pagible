<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Tests;

use Aimeos\Cms\Events\PageInvalidated;


class PageInvalidationSpy
{
    /** @var list<array{domain: string, path: string}> */
    public array $events = [];


    public function handle( PageInvalidated $event ) : void
    {
        foreach( $event->paths as $path ) {
            $this->events[] = ['domain' => $event->domain, 'path' => $path];
        }
    }


    public function reset() : void
    {
        $this->events = [];
    }
}
