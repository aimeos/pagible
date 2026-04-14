<?php

/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */


namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallAdmin extends Command
{
    /**
     * Command name
     */
    protected $signature = 'cms:install:admin';

    /**
     * Command description
     */
    protected $description = 'Installing Pagible CMS admin package';


    /**
     * Execute command
     */
    public function handle(): int
    {
        $result = 0;

        $this->comment( '  Publishing admin files ...' );
        $result += $this->call( 'vendor:publish', ['--provider' => 'Aimeos\Cms\AdminServiceProvider'] );

        return $result ? 1 : 0;
    }
}
