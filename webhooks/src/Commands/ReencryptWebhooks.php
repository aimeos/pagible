<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\WebhookManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;


/**
 * Rewrites encrypted columns using the current APP_KEY.
 *
 * Old keys must be present in Laravel's APP_PREVIOUS_KEYS configuration while this runs.
 */
class ReencryptWebhooks extends Command
{
    protected $signature = 'cms:webhooks:reencrypt {--tenant= : Restrict to an exact tenant ID}';
    protected $description = 'Re-encrypt webhook destinations and secrets with the current application key';


    public function __construct( private readonly WebhookManager $manager )
    {
        parent::__construct();
    }


    public function handle() : int
    {
        $tenant = $this->option( 'tenant' );

        if( !is_string( $tenant ) && $tenant !== null ) {
            $this->error( 'The tenant option must be a string.' );
            return self::FAILURE;
        }

        if( $tenant !== null && $tenant !== '' ) {
            try {
                $tenant = Tenancy::check( $tenant );
            } catch( \InvalidArgumentException $e ) {
                $this->error( $e->getMessage() );
                return self::FAILURE;
            }
        }

        $query = Webhook::withoutTenancy()->select( 'id', 'tenant_id' )->orderBy( 'id' );

        if( is_string( $tenant ) && $tenant !== '' ) {
            $query->where( 'tenant_id', $tenant );
        }

        $count = 0;

        try
        {
            foreach( $query->lazyById() as $webhook ) {
                if( !is_string( $id = $webhook->id ) ) {
                    throw new \LogicException( 'Stored webhook has no ID.' );
                }

                if( $this->manager->reencrypt( $webhook->tenant_id, $id ) ) {
                    $count++;
                }
            }
        }
        catch( LockTimeoutException ) {
            $this->error( 'Webhook configuration is busy; retry re-encryption.' );
            return self::FAILURE;
        }
        catch( \Throwable $e ) {
            $this->error( 'Re-encryption failed; verify APP_PREVIOUS_KEYS before retrying.' );
            return self::FAILURE;
        }

        $this->info( sprintf( 'Re-encrypted %d webhook subscription(s).', $count ) );
        return self::SUCCESS;
    }
}
