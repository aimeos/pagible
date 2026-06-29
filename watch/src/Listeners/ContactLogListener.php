<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\Contacted;


/**
 * Writes a structured JSON line to the CMS log channel for contact-form submissions.
 *
 * Active only when "cms.watch.channel" is set. PII (email, IP) is SHA-256 hashed unless
 * "cms.watch.anonymize" is disabled. A listener failure never breaks the request.
 */
class ContactLogListener
{
    use WritesLog;


    /**
     * Logs the contact submission as a structured entry.
     */
    public function handle( Contacted $event ) : void
    {
        $this->emit( 'cms.contact', $this->context( $event ) );
    }


    /**
     * Builds the structured log context, anonymizing PII and dropping empty values.
     *
     * @return array<string, mixed>
     */
    protected function context( Contacted $event ) : array
    {
        $anon = (bool) config( 'cms.watch.anonymize', true );

        return array_filter( [
            'request_id' => $event->requestId,
            'email' => $this->mask( $event->email, $anon ),
            'ip' => $this->mask( $event->ip, $anon ),
            'duration_ms' => round( $event->durationMs, 1 ),
            'tenant_id' => $event->tenant,
        ], fn( $value ) => $value !== '' );
    }


    /**
     * Pseudonymizes the value with a keyed SHA-256 (HMAC) when anonymization is enabled.
     *
     * Keying with the app secret prevents the low-entropy email/IP hashes from being reversed
     * via rainbow tables while keeping them stable for correlation within a deployment.
     */
    protected function mask( string $value, bool $anon ) : string
    {
        if( $value === '' ) {
            return '';
        }

        return $anon ? hash_hmac( 'sha256', $value, (string) config( 'app.key' ) ) : $value;
    }
}
