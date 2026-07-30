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

        $this->comment( '  Updating CMS configuration ...' );
        $result += $this->config();

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
     * Updates existing CMS configuration entries.
     *
     * @return int 0 on success, 1 on failure
     */
    protected function config() : int
    {
        $filename = 'config/cms.php';
        $path = base_path( $filename );
        $content = file_get_contents( $path );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $updated = str_replace( 'throttle:cms-admin', 'throttle:cms-broadcast', $content );

        if( !preg_match( "/^[ \t]*'disks'[ \t]*=>/m", $updated ) )
        {
            $pattern = "/^(?<indent>[ \t]*)'disk'[ \t]*=>[ \t]*(?<value>.+?)[ \t]*,[ \t]*$/m";

            if( !preg_match( $pattern, $updated, $match ) ) {
                $this->error( "  File [$filename] contains no safely replaceable top-level [disk] entry." );
                $this->line( "  Replace it manually with [disks.public.name] and [disks.private.name/ttl]." );
                return 1;
            }

            $indent = $match['indent'];
            $value = trim( $match['value'] );
            $replacement = implode( PHP_EOL, [
                "{$indent}'disks' => [",
                "{$indent}    'public' => [",
                "{$indent}        'name' => {$value},",
                "{$indent}    ],",
                "{$indent}    'private' => [",
                "{$indent}        'name' => env( 'CMS_PRIVATE_DISK', 'local' ),",
                "{$indent}        'ttl' => (int) env( 'CMS_PRIVATE_TTL', 300 ),",
                "{$indent}    ],",
                "{$indent}],",
            ] );
            $updated = preg_replace( $pattern, $replacement, $updated, 1 );
        }

        if( !is_string( $updated ) ) {
            $this->error( "  Updating file [$filename] failed!" );
            return 1;
        }

        if( $updated === $content ) {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        } elseif( file_put_contents( $path, $updated ) === false ) {
            $this->error( "  Updating file [$filename] failed!" );
            return 1;
        } else {
            $this->line( sprintf( '  File [%1$s] updated' . PHP_EOL, $filename ) );
        }

        $values = require $path;

        if( !is_array( $values ) ) {
            $this->error( "  File [$filename] doesn't return a configuration array!" );
            return 1;
        }

        config( ['cms' => array_merge( (array) config( 'cms', [] ), $values )] );
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
