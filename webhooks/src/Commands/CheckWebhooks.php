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
    use HandlesTenants;

    protected $signature = 'cms:webhooks:check
        {--resolve : Also resolve the endpoint host names, results depend on the DNS of this server}';
    protected $description = 'Check the webhook deny list, operator endpoints, delivery queue and stored subscriptions';


    public function handle( WebhookConfig $config ) : int
    {
        $problems = $config->problems( (bool) $this->option( 'resolve' ) );

        foreach( $problems as $key => $reason ) {
            $this->error( $key . ': ' . ( WebhookConfig::REASONS[$reason] ?? $reason ) );
        }

        // A stalled queue isn't a configuration problem and mustn't stop the deploy which starts the workers
        if( $config->connection() && $config->stalled() ) {
            $this->warn( 'cms.webhooks.queue.name: No queued delivery was processed for more than 10 minutes, start a queue worker for this queue and connection' );
        }

        // Only the tenants can replace their subscriptions, so they mustn't stop the deploy either.
        // Without an application key, nothing can be decrypted and the missing key is reported instead
        if( !isset( $problems['app.key'] ) && ( $counts = $this->undecryptable() ) )
        {
            $this->warn( sprintf( 'app.key: %d webhook subscription(s) can\'t be decrypted, add the old key to APP_PREVIOUS_KEYS and run cms:webhooks:reencrypt, or replace their URLs', array_sum( $counts ) ) );

            if( $tenants = $this->tenants( $counts ) ) {
                $this->warn( 'Affected tenants: ' . $tenants );
            }
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
     * Returns the number of subscriptions by tenant which were encrypted with an application key that isn't configured any more.
     *
     * @return array<array-key, int> Number of subscriptions by tenant ID
     */
    private function undecryptable() : array
    {
        $counts = [];

        try
        {
            $query = Webhook::withoutTenancy()->select( 'id', 'tenant_id', 'url', 'secrets' );

            foreach( $query->lazyById() as $webhook )
            {
                if( !$webhook->decryptable() ) {
                    $counts[$webhook->tenant_id] = ( $counts[$webhook->tenant_id] ?? 0 ) + 1;
                }
            }
        }
        catch( QueryException )
        {
            // The table doesn't exist until the migrations ran
        }

        return $counts;
    }
}
