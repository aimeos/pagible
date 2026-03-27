<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallSearch extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:search';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS search package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing Laravel Scout files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Laravel\Scout\ScoutServiceProvider'] );

        $this->comment( '  Updating Scout configuration ...' );
        $result += $this->scout();

        return $result ? 1 : 0;
    }


    /**
     * Updates Scout configuration
     *
     * @return int 0 on success, 1 on failure
     */
    protected function scout() : int
    {
        $filename = 'config/scout.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        $search = "env('SCOUT_DRIVER', 'collection')";
        $replace = "env('SCOUT_DRIVER', 'cms')";

        if( strpos( $content, $replace ) === false )
        {
            $content = str_replace( $search, $replace, $content );
            file_put_contents( base_path( $filename ), $content );
            $this->line( sprintf( '  Updated default Scout driver to "cms" in [%1$s]' . PHP_EOL, $filename ) );
        }
        else
        {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }
}
