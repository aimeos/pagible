<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallPulse extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:pulse';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS Pulse package';


    /**
     * Execute command
     */
    public function handle() : int
    {
        $result = 0;

        if( !class_exists( \Laravel\Pulse\Pulse::class ) ) {
            $this->warn( '  Laravel Pulse is not installed; CMS Pulse cards will stay inactive.' );
        }

        $this->comment( '  Publishing CMS Pulse dashboard ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'cms-pulse-dashboard'] );

        $this->comment( '  Publishing CMS Pulse views ...' );
        $result += $this->call( 'vendor:publish', ['--tag' => 'cms-pulse-views'] );

        return $result ? 1 : 0;
    }
}
