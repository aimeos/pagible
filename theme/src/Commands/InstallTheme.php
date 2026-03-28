<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallTheme extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:theme';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS theme package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing CMS theme files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\ThemeServiceProvider'] );

        $this->comment( '  Updating services configuration ...' );
        $result += $this->services();

        return $result ? 1 : 0;
    }


    /**
     * Updates the services configuration file
     *
     * @return int 0 on success, 1 on failure
     */
    protected function services() : int
    {
        $done = 0;
        $filename = 'config/services.php';
        $content = file_get_contents( base_path( $filename ) );

        if( $content === false ) {
            $this->error( "  File [$filename] not found!" );
            return 1;
        }

        if( strpos( $content, 'hcaptcha' ) === false && ( $pos = strrpos( $content, '],' ) ) !== false )
        {
            $content = substr_replace( $content, "

    'hcaptcha' => [
        'sitekey' => env('HCAPTCHA_SITEKEY'),
        'secret' => env('HCAPTCHA_SECRET'),
    ],", $pos + 2, 0 );
            $this->line( sprintf( '  Added HCaptcha configuration to [%1$s]' . PHP_EOL, $filename ) );
            $done++;
        }

        if( $done ) {
            file_put_contents( base_path( $filename ), $content );
        } else {
            $this->line( sprintf( '  File [%1$s] already up to date' . PHP_EOL, $filename ) );
        }

        return 0;
    }
}
