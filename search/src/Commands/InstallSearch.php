<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallSearch extends Command
{
    use PatchesFiles;


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

        return $this->patch( $filename, function( string $content ) use ( $filename ) {

            $search = "env('SCOUT_DRIVER', 'collection')";
            $replace = "env('SCOUT_DRIVER', 'cms')";

            if( strpos( $content, $replace ) === false )
            {
                $content = str_replace( $search, $replace, $content );
                $this->line( sprintf( '  Updated default Scout driver to "cms" in [%1$s]', $filename ) );
            }

            if( strpos( $content, "'soft_delete' => true" ) === false && strpos( $content, "'soft_delete' => false" ) !== false )
            {
                $content = str_replace( "'soft_delete' => false", "'soft_delete' => true", $content );
                $this->line( sprintf( '  Enabled Scout soft_delete in [%1$s]', $filename ) );
            }

            return $content;
        }, '' );
    }
}
