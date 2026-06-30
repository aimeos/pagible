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


abstract class ContentPulseRecorder extends Recorder
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

    protected string $contentType;
    protected string $pulseType;


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
        if( $event->contentType !== $this->contentType ) {
            return;
        }

        $this->entry( $this->pulseType, [
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
        if( $event->contentType !== $this->contentType ) {
            return;
        }

        $this->entry( $this->pulseType, [
            'action' => $event->source ? $event->source . ':bulk' : 'bulk',
            'source' => $event->source,
            'editor' => $event->editor,
            'tenant' => $event->tenant,
        ], count( $event->ids ), ['count', 'sum'] );
    }


    protected function action( Event $event ) : string
    {
        $action = match( class_basename( $event ) ) {
            'Added' => 'add',
            'Saved' => 'save',
            'Published' => 'publish',
            'Dropped' => 'delete',
            'Restored' => 'restore',
            'Purged' => 'purge',
            'Moved' => 'move',
            default => strtolower( class_basename( $event ) ),
        };

        return $event->source ? $event->source . ':' . $action : $action;
    }
}
