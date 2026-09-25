<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Cdn;
use Aimeos\Cms\Jobs\PurgeCdn as PurgeJob;
use Illuminate\Console\Command;


class PurgeCdn extends Command
{
    protected $signature = 'cms:cdn:purge
        {urls?* : Absolute URLs or paths relative to the CDN or application URL}
        {--all : Remove all content from the CDN caches}
        {--client=* : Names of the CDN clients, all by default}';

    protected $description = 'Purge URLs or all content from the CDN caches';


    public function handle() : int
    {
        $all = (bool) $this->option( 'all' );
        $names = (array) $this->option( 'client' );
        $base = Cdn::base();

        $urls = array_values( array_map(
            fn( $url ) => str_contains( $url = (string) $url, '://' ) ? $url : $base . '/' . ltrim( $url, '/' ),
            (array) $this->argument( 'urls' ),
        ) );

        if( !$all && !$urls ) {
            $this->error( 'Pass the URLs to purge or use --all' );
            return self::FAILURE;
        }

        $failed = false;
        // Clients are purged in the configured order, so inner caches should be listed first
        foreach( Cdn::clients() as $name => $config )
        {
            if( $names && !in_array( $name, $names, true ) || !( $list = $all ? [Cdn::ALL] : Cdn::urls( $config, $urls ) ) ) {
                continue;
            }

            try
            {
                ( new PurgeJob( $name, $list ) )->handle();
                $this->info( $all ? sprintf( '%s: purged all content', $name ) : sprintf( '%s: purged %d URL(s)', $name, count( $list ) ) );
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
