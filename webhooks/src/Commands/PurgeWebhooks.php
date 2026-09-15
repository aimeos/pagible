<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Models\Webhook;
use Aimeos\Cms\Tenancy;
use Aimeos\Cms\Utils;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;


class PurgeWebhooks extends Command
{
    protected $signature = 'cms:webhooks:purge {tenant : Exact tenant ID}';
    protected $description = 'Permanently delete all webhook subscriptions for one tenant';


    public function handle() : int
    {
        $value = $this->argument( 'tenant' );

        if( !is_string( $value ) ) {
            $this->error( 'The tenant argument must be a string.' );
            return self::FAILURE;
        }

        try {
            $tenant = Tenancy::check( $value );
        } catch( \InvalidArgumentException $e ) {
            $this->error( $e->getMessage() );
            return self::FAILURE;
        }

        if( $tenant === '' ) {
            $this->error( 'The tenant argument must not be empty.' );
            return self::FAILURE;
        }

        $seconds = max( 1, (int) config( 'cms.lock', 30 ) );
        $key = 'cms_webhooks_' . hash( 'sha256', $tenant );
        $count = Cache::lock( $key, $seconds )->block( $seconds, fn() => Utils::transaction(
            fn() => Webhook::withoutTenancy()->where( 'tenant_id', $tenant )->delete()
        ) );

        $this->info( sprintf( 'Deleted %d webhook subscription(s) for tenant %s.', $count, $tenant ) );
        return self::SUCCESS;
    }
}
