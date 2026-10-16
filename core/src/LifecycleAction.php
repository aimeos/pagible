<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;


/**
 * Lifecycle actions of pages, page variants, elements and files.
 *
 * The values are the past-tense action names used by the events.
 */
enum LifecycleAction : string
{
    case Dropped = 'dropped';
    case Purged = 'purged';
    case Restored = 'restored';


    /**
     * Returns the event class broadcast for the action.
     *
     * @return class-string<Event> Event class
     */
    public function event() : string
    {
        return match( $this ) {
            self::Dropped => Dropped::class,
            self::Purged => Purged::class,
            self::Restored => Restored::class,
        };
    }
}
