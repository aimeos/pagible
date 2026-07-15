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
        return $this->call( 'vendor:publish', [
            '--tag' => ['cms-pulse-dashboard', 'cms-pulse-views'],
        ] );
    }
}
