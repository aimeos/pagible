<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Cdn;
use FOS\HttpCache\CacheInvalidator;
use Illuminate\Console\Command;


class CheckCdn extends Command
{
    protected $signature = 'cms:cdn:check';

    protected $description = 'Check the configuration of the CDN clients without sending requests';


    public function handle() : int
    {
        if( !$clients = Cdn::clients() ) {
            $this->info( 'No CDN clients configured' );
            return self::SUCCESS;
        }

        $failed = false;

        foreach( $clients as $name => $config )
        {
            try
            {
                $invalidator = Cdn::invalidator( $config );
                $warnings = [];

                if( !empty( $config['queries'] ) && !$invalidator->supports( CacheInvalidator::INVALIDATE ) ) {
                    $warnings[] = '"queries" requires a driver supporting bans, only the URLs are purged';
                }

                if( !empty( $config['refresh'] ) && !$invalidator->supports( CacheInvalidator::REFRESH ) ) {
                    $warnings[] = '"refresh" requires a driver supporting refreshes, the URLs are purged';
                }

                if( is_string( $after = $config['after'] ?? null ) && !isset( $clients[$after] ) ) {
                    $warnings[] = sprintf( '"after" uses the unknown client "%s", it is purged independently', $after );
                }

                foreach( $warnings as $warning ) {
                    $this->warn( sprintf( '%s: %s', $name, $warning ) );
                }

                if( !$warnings ) {
                    $this->info( sprintf( '%s: OK', $name ) );
                }
            }
            catch( \Throwable $e )
            {
                $this->error( sprintf( '%s: %s', $name, $e->getMessage() ) );
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
