<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Contacted;
use Aimeos\Cms\Watch;


/**
 * Writes a structured JSON line to the CMS log channel for contact-form submissions.
 *
 * Active only when "cms.watch.channel" is set. PII (email, IP) is SHA-256 hashed unless
 * "cms.watch.anonymize" is disabled. A listener failure never breaks the request.
 */
class ContactLogListener
{
    public function handle( Contacted $event ) : void
    {
        Watch::emit( 'cms.contact', $this->fields( $event ) );
    }


    /**
     * @return array<string, mixed>
     */
    protected function fields( Contacted $event ) : array
    {
        return [
            'email' => Watch::mask( $event->email ),
            'ip' => Watch::mask( $event->ip ),
            'duration_ms' => round( $event->durationMs, 1 ),
            'tenant_id' => $event->tenant,
        ];
    }
}
