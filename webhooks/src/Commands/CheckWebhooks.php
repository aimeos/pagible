<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookConfig;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;


class CheckWebhooks extends Command
{
    protected $signature = 'cms:webhooks:check';
    protected $description = 'Check the webhook deny list, operator endpoints, delivery queue and stored subscriptions';


    public function handle( WebhookConfig $config ) : int
    {
        $problems = $config->problems();

        foreach( $problems as $key => $reason ) {
            $this->error( $key . ': ' . ( WebhookConfig::REASONS[$reason] ?? $reason ) );
        }

        // Only the tenants can rotate their secrets, so they mustn't stop the deploy.
        // Without an application key, nothing can be decrypted and the missing key is reported instead
        if( !isset( $problems['app.key'] ) && ( $count = $this->undecryptable() ) ) {
            $this->warn( sprintf( 'app.key: %d webhook subscription(s) can\'t be decrypted, add the old key to APP_PREVIOUS_KEYS or rotate their secrets', $count ) );
        }

        if( $problems !== [] ) {
            return self::FAILURE;
        }

        if( !(bool) config( 'cms.webhooks.enabled', false ) ) {
            $this->warn( 'The webhook configuration is valid but webhooks are disabled' );
            return self::SUCCESS;
        }

        $this->info( 'The webhook configuration is valid' );
        return self::SUCCESS;
    }


    /**
     * Returns the number of subscriptions whose secrets were encrypted with an application key that isn't configured any more.
     */
    private function undecryptable() : int
    {
        $count = 0;

        try
        {
            foreach( Webhook::withoutTenancy()->select( 'id', 'secrets' )->lazyById() as $webhook ) {
                $count += $webhook->decryptable() ? 0 : 1;
            }
        }
        catch( QueryException )
        {
            // The table doesn't exist until the migrations ran
        }

        return $count;
    }
}
