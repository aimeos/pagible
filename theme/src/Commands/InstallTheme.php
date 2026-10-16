<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Aimeos\Cms\Concerns\PatchesFiles;
use Illuminate\Console\Command;


class InstallTheme extends Command
{
    use PatchesFiles;


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
        $string = "

    'hcaptcha' => [
        'sitekey' => env('HCAPTCHA_SITEKEY'),
        'secret' => env('HCAPTCHA_SECRET'),
    ],";

        return $this->insert( 'config/services.php', '],', $string, 'hcaptcha',
            '  Added HCaptcha configuration to [%1$s]' . PHP_EOL, true );
    }
}
