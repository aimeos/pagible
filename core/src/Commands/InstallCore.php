<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallCore extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:core  {--seed : Add example pages to the database}';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS core package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing core files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\CoreServiceProvider'] );

        $this->comment( '  Updating broadcast rate limiter ...' );
        $result += $this->broadcast();

        $this->comment( '  Creating database ...' );
        $result += $this->db();

        $this->comment( '  Migrating database ...' );
        $result += $this->call( 'migrate' );

        if( $this->option( 'seed' ) )
        {
            $this->comment( '  Seed database ...' );
            $result += $this->call( 'db:seed', ['--class' => 'TestSeeder'] );
        }

        $this->comment( '  Link public storage folder ...' );
        $result += $this->call( 'storage:link', ['--force' => null] );

        return $result ? 1 : 0;
    }


    /**
     * Updates the broadcast limiter in existing CMS configuration files.
     *
     * @return int 0 on success, 1 on failure
     */
    protected function broadcast() : int
    {
        $filename = 'config/cms.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $updated = str_replace( 'throttle:cms-admin', 'throttle:cms-broadcast', $content );

        if( $updated !== $content ) {
            file_put_contents( base_path( $filename ), $updated );
            $this->line( sprintf( '  File [%1$s] updated' . PHP_EOL, $filename ) );
        } else {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }


    /**
     * Creates the database if necessary
     *
     * @return int 0 on success, 1 on failure
     */
    protected function db() : int
    {
        $name = config( 'cms.db', 'sqlite' );
        $path = (string) config( "database.connections.{$name}.database", database_path( 'database.sqlite' ) );

        if( $name && !file_exists( $path ) )
        {
            if( touch( $path ) === true ) {
                $this->line( sprintf( '  Created database [%1$s]' . PHP_EOL, $path ) );
            } else {
                $this->error( sprintf( '  Creating database [%1$s] failed!' . PHP_EOL, $path ) ); exit( 1 );
            }
        }
        else
        {
            $this->line( '  Creating database is not necessary' . PHP_EOL );
        }

        return 0;
    }
}
