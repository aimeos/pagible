<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Import;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


/**
 * Database helpers shared by the importers.
 */
class Database
{
    /**
     * Tests the source database connection and prints a config example if it fails.
     *
     * @param Command $cmd Command used for the output
     * @param string $conn Database connection name
     * @param string $label Name of the source CMS, e.g. "WordPress"
     * @param string $prefix Prefix of the environment variables, e.g. "WP"
     * @param string $db Default database name
     * @return bool TRUE if the connection works, FALSE if not
     */
    public static function check( Command $cmd, string $conn, string $label, string $prefix, string $db ) : bool
    {
        try
        {
            DB::connection( $conn )->getPdo();
            return true;
        }
        catch( \Exception $e )
        {
            $cmd->error( "Cannot connect to {$label} database using connection \"{$conn}\"." );
            $cmd->error( "Add a \"{$conn}\" connection to config/database.php, e.g.:" );
            $cmd->line( "  '{$conn}' => [" );
            $cmd->line( "      'driver' => 'mysql'," );
            $cmd->line( "      'host' => env('{$prefix}_DB_HOST', '127.0.0.1')," );
            $cmd->line( "      'database' => env('{$prefix}_DB_DATABASE', '{$db}')," );
            $cmd->line( "      'username' => env('{$prefix}_DB_USERNAME', 'root')," );
            $cmd->line( "      'password' => env('{$prefix}_DB_PASSWORD', '')," );
            $cmd->line( "  ]" );
            return false;
        }
    }
}
