<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\WebhookManager;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;


class PurgeWebhooks extends Command
{
    use HandlesTenants;

    protected $signature = 'cms:webhooks:purge {tenant : Exact tenant ID}';
    protected $description = 'Permanently delete all webhook subscriptions for one tenant';


    public function __construct( private readonly WebhookManager $manager )
    {
        parent::__construct();
    }


    public function handle() : int
    {
        if( ( $tenant = $this->tenant( $this->argument( 'tenant' ), 'tenant argument' ) ) === null ) {
            return self::FAILURE;
        }

        if( $tenant === '' ) {
            $this->error( 'The tenant argument must not be empty.' );
            return self::FAILURE;
        }

        try {
            $count = $this->manager->purge( $tenant );
        } catch( LockTimeoutException ) {
            $this->error( 'Webhook configuration is busy; retry purging.' );
            return self::FAILURE;
        }

        $this->info( sprintf( 'Deleted %d webhook subscription(s) for tenant %s.', $count, $tenant ) );
        return self::SUCCESS;
    }
}
