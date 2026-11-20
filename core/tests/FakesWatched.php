<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Tests;

use Illuminate\Support\Facades\Event;


trait FakesWatched
{
    /**
     * Fakes the watched events, which Watch::dispatch() only creates if they have listeners.
     *
     * @param class-string ...$events Event classes
     */
    protected function fakeWatched( string ...$events ) : void
    {
        foreach( $events as $event ) {
            Event::listen( $event, fn() => null );
        }

        Event::fake( $events );
    }
}
