<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallCore extends Command
{
    use PatchesFiles;


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

        $result = $this->patch( $filename, function( string $content ) use ( $filename ) {

            $content = str_replace( 'throttle:cms-admin', 'throttle:cms-broadcast', $content );

            if( preg_match( "/^[ \t]*'disks'[ \t]*=>/m", $content ) ) {
                return $content;
            }

            $pattern = "/^(?<indent>[ \t]*)'disk'[ \t]*=>[ \t]*(?<value>.+?)[ \t]*,[ \t]*$/m";

            if( !preg_match( $pattern, $content, $match ) ) {
                $this->error( "  File [$filename] contains no safely replaceable top-level [disk] entry." );
                $this->line( "  Replace it manually with [disks.public.name] and [disks.private.name/ttl]." );
                return null;
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

            if( ( $content = preg_replace( $pattern, $replacement, $content, 1 ) ) === null ) {
                $this->error( "  Updating file [$filename] failed!" );
            }

            return $content;
        } );

        if( $result ) {
            return $result;
        }

        $values = require base_path( $filename );

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

        if( config( "database.connections.{$name}.driver" ) !== 'sqlite' || $path === ':memory:' || file_exists( $path ) )
        {
            $this->line( '  Creating database is not necessary' . PHP_EOL );
            return 0;
        }

        if( !touch( $path ) )
        {
            $this->error( sprintf( '  Creating database [%1$s] failed!' . PHP_EOL, $path ) );
            return 1;
        }

        $this->line( sprintf( '  Created database [%1$s]' . PHP_EOL, $path ) );
        return 0;
    }
}
