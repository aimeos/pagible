<?php

/**
 * @license MIT, https://opensource.org/license/mit
 */

namespace Aimeos\Cms\Commands;

use Illuminate\Console\Command;


class InstallCashier extends Command
{
    protected $signature = 'cms:install:cashier';

    protected $description = 'Installing Pagible CMS cashier package';


    /**
     * Publishes the migrations required by the installed Cashier provider.
     */
    public function handle(): int
    {
        return $this->call( 'vendor:publish', ['--tag' => 'cashier-migrations'] );
    }
}
