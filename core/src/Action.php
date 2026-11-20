<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms;

use Aimeos\Cms\Events\Added;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;


/**
 * Changes of pages, page variants, elements and files.
 *
 * The values are the past-tense action names used by the events. Content and lifecycle
 * actions have their own event class, the unversioned changes of page variants (ignored,
 * source and translated) are announced by Bulk events only.
 */
enum Action : string
{
    case Added = 'added';
    case Moved = 'moved';
    case Published = 'published';
    case Saved = 'saved';

    case Dropped = 'dropped';
    case Purged = 'purged';
    case Restored = 'restored';

    case Ignored = 'ignored';
    case Source = 'source';
    case Translated = 'translated';


    /**
     * Returns the event class broadcast for a single changed item.
     *
     * @return class-string<Event> Event class
     * @throws \LogicException If the action is announced by Bulk events only, see single()
     */
    public function event() : string
    {
        return $this->eventClass() ?? throw new \LogicException(
            sprintf( 'Action "%s" can only be announced by bulk events', $this->value )
        );
    }


    /**
     * Returns whether the action has an event class for single changed items.
     *
     * @return bool FALSE for the page variant actions (ignored, source and translated) announced by Bulk events only
     */
    public function single() : bool
    {
        return $this->eventClass() !== null;
    }


    /**
     * Returns the event class for single changed items if the action has one.
     *
     * @return class-string<Event>|null Event class or NULL if the action is announced by Bulk events only
     */
    private function eventClass() : ?string
    {
        return match( $this ) {
            self::Added => Added::class,
            self::Moved => Moved::class,
            self::Published => Published::class,
            self::Saved => Saved::class,
            self::Dropped => Dropped::class,
            self::Purged => Purged::class,
            self::Restored => Restored::class,
            self::Ignored, self::Source, self::Translated => null,
        };
    }


    /**
     * Returns whether the action changes the content, not only the lifecycle state.
     *
     * @return bool TRUE for added, moved, published and saved
     */
    public function content() : bool
    {
        return match( $this ) {
            self::Added, self::Moved, self::Published, self::Saved => true,
            default => false,
        };
    }
}
