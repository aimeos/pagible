<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Translation;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for unversioned page language changes.
 *
 * Active only when "cms.watch.channel" is set; a listener failure never breaks the originating operation.
 */
class TranslationListener
{
    public function handle( Translation $event ) : void
    {
        Watch::emit( 'cms.translation', [
            'type' => 'page',
            'action' => $event->action,
            'ids' => [$event->pageId],
            'langs' => $event->langs,
            'editor' => $event->editor,
            'ai' => $event->ai,
            'tenant_id' => $event->tenant,
        ] );
    }
}
