<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Authed;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for authentication actions.
 *
 * Active only when "cms.watch.channel" is set. PII (email, IP, user agent) is SHA-256 hashed
 * unless "cms.watch.anonymize" is disabled. A listener failure never breaks the request.
 */
class AuthLogListener
{
    public function handle( Authed $event ) : void
    {
        Watch::emit( 'cms.auth', $this->fields( $event ) );
    }


    /**
     * @return array<string, mixed>
     */
    protected function fields( Authed $event ) : array
    {
        return [
            'action' => $event->action,
            'email' => Watch::mask( $event->email ),
            'ip' => Watch::mask( $event->ip ),
            'user_agent' => Watch::mask( $event->userAgent ),
            'tenant_id' => $event->tenant,
        ];
    }
}
