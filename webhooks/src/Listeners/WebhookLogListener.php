<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Listeners;

use Aimeos\Cms\Events\WebhookChanged;
use Aimeos\Cms\Watch;
use Illuminate\Support\Facades\Log;


final class WebhookLogListener
{
    public function handle( WebhookChanged $event ) : void
    {
        try
        {
            $fields = Watch::fields( [
                'action' => $event->action,
                'actor' => $event->actor,
                'webhook_id' => $event->webhookId,
                'event_count' => $event->eventCount,
                'tenant_id' => $event->tenant,
                'old_host' => $event->oldHost,
                'new_host' => $event->newHost,
            ] );

            ( $channel = Watch::channel() )
                ? Log::channel( $channel )->warning( 'cms.webhook', $fields )
                : Log::warning( 'cms.webhook', $fields );
        }
        catch( \Throwable ) {
            error_log( 'CMS webhook audit listener error' );
        }
    }
}
