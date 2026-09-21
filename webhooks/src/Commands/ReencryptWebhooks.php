<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\WebhookManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;


/**
 * Rewrites encrypted columns using the current APP_KEY.
 *
 * Old keys must be present in Laravel's APP_PREVIOUS_KEYS configuration while this runs.
 */
class ReencryptWebhooks extends Command
{
    use HandlesTenants;

    protected $signature = 'cms:webhooks:reencrypt {--tenant= : Restrict to an exact tenant ID}';
    protected $description = 'Re-encrypt webhook destinations and secrets with the current application key';


    public function __construct( private readonly WebhookManager $manager )
    {
        parent::__construct();
    }


    public function handle() : int
    {
        $tenant = $this->option( 'tenant' );

        if( $tenant !== null && ( $tenant = $this->tenant( $tenant, 'tenant option' ) ) === null ) {
            return self::FAILURE;
        }

        // Unset variables in deploy scripts must not silently re-encrypt all tenants
        if( $tenant === '' ) {
            $this->error( 'The tenant option must not be empty.' );
            return self::FAILURE;
        }

        $query = Webhook::withoutTenancy()->select( 'id', 'tenant_id' )->orderBy( 'id' );

        if( $tenant !== null ) {
            $query->where( 'tenant_id', $tenant );
        }

        $count = 0;
        $busy = [];
        $skipped = [];

        try
        {
            foreach( $query->lazyById() as $webhook )
            {
                if( !is_string( $id = $webhook->id ) ) {
                    throw new \LogicException( 'Stored webhook has no ID.' );
                }

                // Stopping here would leave the following subscriptions encrypted with the old key
                try
                {
                    if( $this->manager->reencrypt( $webhook->tenant_id, $id ) ) {
                        $count++;
                    }
                }
                catch( DecryptException )
                {
                    $skipped[$webhook->tenant_id] = ( $skipped[$webhook->tenant_id] ?? 0 ) + 1;
                }
                catch( LockTimeoutException )
                {
                    $busy[$webhook->tenant_id] = ( $busy[$webhook->tenant_id] ?? 0 ) + 1;
                }
            }
        }
        catch( \Throwable $e ) {
            // Failures of single subscriptions are skipped above, so it's e.g. a database or encryption setup error
            report( $e );
            $this->error( 'Re-encryption failed: ' . $e->getMessage() );
            return self::FAILURE;
        }

        $this->info( sprintf( 'Re-encrypted %d webhook subscription(s).', $count ) );

        $failures = [
            '%d webhook subscription(s) can\'t be decrypted; add their key to APP_PREVIOUS_KEYS and retry, or replace their URLs.' => $skipped,
            '%d webhook subscription(s) were changed at the same time and weren\'t re-encrypted; retry re-encryption.' => $busy,
        ];

        foreach( $failures as $message => $counts )
        {
            if( $counts === [] ) {
                continue;
            }

            $this->error( sprintf( $message, array_sum( $counts ) ) );

            if( $tenants = $this->tenants( $counts ) ) {
                $this->error( 'Affected tenants: ' . $tenants );
            }
        }

        return $busy || $skipped ? self::FAILURE : self::SUCCESS;
    }
}
