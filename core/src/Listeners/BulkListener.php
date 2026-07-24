<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Bulk;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for bulk content changes.
 */
class BulkListener
{
    public function handle( Bulk $event ) : void
    {
        Watch::emit( 'cms.' . $event->contentType, [
            'type' => $event->contentType,
            'source' => $event->source,
            'action' => $event->action,
            'ids' => array_values( $event->ids ),
            'editor' => $event->editor,
            'tenant_id' => $event->tenant,
        ] );
    }
}
