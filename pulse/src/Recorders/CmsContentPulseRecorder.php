<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\Added;
use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Events\Dropped;
use Aimeos\Cms\Events\Event;
use Aimeos\Cms\Events\Moved;
use Aimeos\Cms\Events\Published;
use Aimeos\Cms\Events\Purged;
use Aimeos\Cms\Events\Restored;
use Aimeos\Cms\Events\Saved;


class CmsContentPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [
        Added::class,
        Saved::class,
        Published::class,
        Dropped::class,
        Restored::class,
        Purged::class,
        Moved::class,
        Bulk::class,
    ];

    private const TYPES = [
        'page' => 'cms_page',
        'element' => 'cms_element',
        'file' => 'cms_file',
    ];

    /**
     * @var array<class-string<Event>, non-empty-string>
     */
    private const ACTIONS = [
        Added::class => 'add',
        Saved::class => 'save',
        Published::class => 'publish',
        Dropped::class => 'delete',
        Restored::class => 'restore',
        Purged::class => 'purge',
        Moved::class => 'move',
    ];


    public function record( mixed $event ) : void
    {
        if( $event instanceof Bulk ) {
            $this->bulk( $event );
        } elseif( $event instanceof Event ) {
            $this->single( $event );
        }
    }


    protected function single( Event $event ) : void
    {
        if( !( $type = self::TYPES[$event->contentType] ?? null ) ) {
            return;
        }

        $this->entry( $type, [
            'action' => $this->action( $event ),
            'source' => $event->source,
            'editor' => $event->editor,
            'tenant' => $event->tenant,
            'path' => $event->contentType === 'page' ? $event->data['path'] ?? null : null,
            'domain' => $event->contentType === 'page' ? $event->data['domain'] ?? null : null,
            'mime' => $event->contentType === 'file' ? $event->data['mime'] ?? null : null,
        ] );
    }


    protected function bulk( Bulk $event ) : void
    {
        if( !( $type = self::TYPES[$event->contentType] ?? null ) ) {
            return;
        }

        $this->entry( $type, [
            'action' => $event->source ? $event->source . ':bulk' : 'bulk',
            'source' => $event->source,
            'editor' => $event->editor,
            'tenant' => $event->tenant,
        ], count( $event->ids ), ['count', 'sum'] );
    }


    protected function action( Event $event ) : string
    {
        $action = self::ACTIONS[$event::class] ?? strtolower( class_basename( $event ) );

        return $event->source ? $event->source . ':' . $action : $action;
    }
}
