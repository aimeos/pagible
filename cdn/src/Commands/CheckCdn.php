<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Cdn;
use Illuminate\Console\Command;


class CheckCdn extends Command
{
    /** Warnings for enabled options the driver doesn't support */
    private const WARNINGS = [
        'queries' => '"queries" requires a driver supporting bans, only the URLs are purged',
        'refresh' => '"refresh" requires a driver supporting refreshes, the URLs are purged',
    ];

    protected $signature = 'cms:cdn:check
        {--send : Purge a test URL to check the credentials and servers too}';

    protected $description = 'Check the configuration of the CDN clients';


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

                foreach( Cdn::supports( $config, $invalidator ) as $option => $supported )
                {
                    if( !$supported ) {
                        $warnings[] = self::WARNINGS[$option];
                    }
                }

                if( is_string( $after = $config['after'] ?? null ) && Cdn::inner( $config, $clients ) === null ) {
                    $warnings[] = sprintf( '"after" uses the unknown client "%s", it is purged independently', $after );
                }

                // Only the purge requests show if the credentials and servers work
                if( $this->option( 'send' ) ) {
                    $invalidator->invalidatePath( $this->url( $config ) )->flush();
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


    /**
     * Returns the test URL purged from the client, using the first host the client is limited to.
     *
     * @param array<string, mixed> $config Client configuration
     */
    private function url( array $config ) : string
    {
        $base = Cdn::base();

        if( $hosts = (array) ( $config['hosts'] ?? [] ) ) {
            $base = ( parse_url( $base, PHP_URL_SCHEME ) ?: 'https' ) . '://' . (string) reset( $hosts );
        }

        return $base . '/cms-cdn-check';
    }
}
