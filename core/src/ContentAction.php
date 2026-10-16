<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Added;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Saved;


/**
 * Content changes of pages, elements and files besides the lifecycle actions.
 *
 * The values are the past-tense action names used by the events.
 */
enum ContentAction : string
{
    case Added = 'added';
    case Moved = 'moved';
    case Published = 'published';
    case Saved = 'saved';


    /**
     * Returns the event class broadcast for the action.
     *
     * @return class-string<Event> Event class
     */
    public function event() : string
    {
        return match( $this ) {
            self::Added => Added::class,
            self::Moved => Moved::class,
            self::Published => Published::class,
            self::Saved => Saved::class,
        };
    }
}
