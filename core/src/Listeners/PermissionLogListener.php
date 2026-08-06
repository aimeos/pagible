<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\PermissionChanged;
use Aimeos\Cms\Watch;
use Illuminate\Support\Facades\Log;


/**
 * Writes CMS permission assignment changes to the audit log.
 *
 * Authorization changes are security-relevant, so the entry is always written at
 * warning level — to the dedicated watch channel when one is configured, otherwise
 * to the default log. The acting and target principals stay identifiable for
 * forensic use even when anonymization is on; only network metadata (IP, user
 * agent) follows the anonymization setting.
 */
class PermissionLogListener
{
    public function handle( PermissionChanged $event ) : void
    {
        try
        {
            $fields = Watch::fields( [
                'action' => 'permission',
                'actor' => $event->actorEmail,
                'target' => $event->targetEmail,
                'target_id' => $event->targetId,
                'assignments' => $event->assignments,
                'ip' => Watch::mask( $event->ip ),
                'user_agent' => Watch::mask( $event->userAgent ),
                'tenant_id' => $event->tenant,
            ] );

            ( $channel = Watch::channel() )
                ? Log::channel( $channel )->warning( 'cms.user', $fields )
                : Log::warning( 'cms.user', $fields );
        }
        catch( \Throwable $e )
        {
            error_log( 'CMS watch listener error: ' . $e->getMessage() );
        }
    }
}
