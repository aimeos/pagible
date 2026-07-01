<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Recorders;

use Aimeos\Cms\Events\ContentChanged;


class CmsContentPulseRecorder extends Recorder
{
    /**
     * @var list<class-string>
     */
    public array $listen = [ContentChanged::class];

    private const TYPES = [
        'page' => 'cms_page',
        'element' => 'cms_element',
        'file' => 'cms_file',
    ];

    /**
     * @var array<non-empty-string, non-empty-string>
     */
    private const ACTIONS = [
        'added' => 'add',
        'saved' => 'save',
        'published' => 'publish',
        'dropped' => 'delete',
        'restored' => 'restore',
        'purged' => 'purge',
        'moved' => 'move',
    ];


    public function record( mixed $event ) : void
    {
        if( !$event instanceof ContentChanged ) {
            return;
        }

        if( !( $type = self::TYPES[$event->contentType] ?? null ) ) {
            return;
        }

        $key = [
            'action' => $this->action( $event ),
            'source' => $event->source,
            'tenant' => $event->tenant,
            'domain' => $event->domain,
            'mime' => $event->mime,
        ];

        if( $event->value !== null ) {
            $this->entry( $type, $key, $event->value, ['count', 'sum'] );
            return;
        }

        $this->entry( $type, $key );
    }


    protected function action( ContentChanged $event ) : string
    {
        $action = self::ACTIONS[$event->action] ?? $event->action;

        return $event->source ? $event->source . ':' . $action : $action;
    }
}
