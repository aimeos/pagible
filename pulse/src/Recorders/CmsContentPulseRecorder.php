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

    /**
     * Content types tracked as metrics; other types are ignored.
     *
     * @var list<non-empty-string>
     */
    private const TYPES = ['page', 'element', 'file'];

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

        if( !in_array( $event->contentType, self::TYPES, true ) ) {
            return;
        }

        // Content is aggregated into a per-transport bucket ("cms_" + source, e.g.
        // "cms_graphql", "cms_mcp") keyed by "<content type>:<action>" (e.g. "page:save"),
        // so each dashboard card reflects only one transport's traffic.
        $key = [
            'action' => $event->contentType . ':' . $this->action( $event ),
            'tenant' => $event->tenant,
            'domain' => $event->domain,
            'mime' => $event->mime,
        ];

        if( $event->value !== null ) {
            $this->entry( $this->type( $event ), $key, $event->value, ['count', 'sum'] );
            return;
        }

        $this->entry( $this->type( $event ), $key );
    }


    protected function action( ContentChanged $event ) : string
    {
        return self::ACTIONS[$event->action] ?? $event->action;
    }


    protected function type( ContentChanged $event ) : string
    {
        return 'cms_' . ( $event->source ?: 'cli' );
    }
}
